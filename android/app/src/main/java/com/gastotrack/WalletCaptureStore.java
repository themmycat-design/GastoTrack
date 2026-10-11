package com.gastotrack;

import android.content.ContentValues;
import android.content.Context;
import android.content.SharedPreferences;
import android.database.Cursor;
import android.database.sqlite.SQLiteDatabase;
import android.database.sqlite.SQLiteOpenHelper;
import android.service.notification.StatusBarNotification;

import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;

import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.Locale;
import java.util.TimeZone;
import java.util.UUID;

/** App-private, durable queue for unverified wallet notification signals. */
public final class WalletCaptureStore extends SQLiteOpenHelper {
    private static WalletCaptureStore instance;
    private final SharedPreferences account;

    public static synchronized WalletCaptureStore getInstance(Context context) {
        if (instance == null) instance = new WalletCaptureStore(context.getApplicationContext());
        return instance;
    }

    private WalletCaptureStore(Context context) {
        super(context, "wallet_captures.db", null, 1);
        account = context.getSharedPreferences("wallet_capture_account", Context.MODE_PRIVATE);
    }

    @Override
    public void onCreate(SQLiteDatabase db) {
        db.execSQL("CREATE TABLE wallet_captures (client_id TEXT PRIMARY KEY, notification_key TEXT NOT NULL UNIQUE, user_id INTEGER NOT NULL, business_id INTEGER NOT NULL, payload TEXT NOT NULL)");
        db.execSQL("CREATE INDEX wallet_captures_account ON wallet_captures (user_id, business_id)");
    }

    @Override
    public void onUpgrade(SQLiteDatabase db, int oldVersion, int newVersion) {
        // Version 1 has no schema upgrades.
    }

    public void setActiveAccount(int userId, int businessId) {
        account.edit().putInt("user_id", userId).putInt("business_id", businessId).commit();
    }

    public JSONObject capture(StatusBarNotification notification, NotificationParser.ParsedTransaction parsed) {
        int userId = account.getInt("user_id", 0);
        int businessId = account.getInt("business_id", 0);
        if (userId <= 0 || businessId <= 0) return null;
        String source = parsed.appSource.equals("GCash") ? "gcash" : parsed.appSource.equals("Maya") ? "maya" : null;
        if (source == null) return null;

        try {
            String clientId = UUID.randomUUID().toString();
            String notificationKey = fingerprint(notification.getKey() + ":" + notification.getPostTime());
            String amountText = parsed.amount == null ? "" : parsed.amount.replaceAll("[^0-9.]", "");
            Double amount = null;
            try {
                double parsedAmount = Double.parseDouble(amountText);
                if (parsedAmount > 0 && parsedAmount <= 99999999.99) amount = parsedAmount;
            } catch (NumberFormatException ignored) {
                // Many wallet notifications have no recognizable amount.
            }
            SimpleDateFormat formatter = new SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss'Z'", Locale.US);
            formatter.setTimeZone(TimeZone.getTimeZone("UTC"));
            long postedAt = notification.getPostTime() > 0 ? notification.getPostTime() : System.currentTimeMillis();

            JSONObject payload = new JSONObject();
            payload.put("client_id", clientId);
            payload.put("source", source);
            payload.put("direction", parsed.transactionType == null ? "unknown" : parsed.transactionType);
            payload.put("amount", amount == null ? JSONObject.NULL : amount);
            payload.put("currency", "PHP");
            payload.put("merchant", parsed.merchant == null ? JSONObject.NULL : limit(parsed.merchant, 160));
            payload.put("message", limit(parsed.rawMessage == null ? "" : parsed.rawMessage, 1000));
            payload.put("captured_at", formatter.format(new Date(postedAt)));

            ContentValues values = new ContentValues();
            values.put("client_id", clientId);
            values.put("notification_key", notificationKey);
            values.put("user_id", userId);
            values.put("business_id", businessId);
            values.put("payload", payload.toString());
            long inserted = getWritableDatabase().insertWithOnConflict(
                "wallet_captures", null, values, SQLiteDatabase.CONFLICT_IGNORE);
            return inserted == -1 ? null : payload;
        } catch (JSONException | NoSuchAlgorithmException exception) {
            return null;
        }
    }

    public JSONArray pending() throws JSONException {
        JSONArray result = new JSONArray();
        int userId = account.getInt("user_id", 0);
        int businessId = account.getInt("business_id", 0);
        if (userId <= 0 || businessId <= 0) return result;
        try (Cursor cursor = getReadableDatabase().rawQuery(
            "SELECT payload FROM wallet_captures WHERE user_id = ? AND business_id = ? ORDER BY rowid ASC LIMIT 50",
            new String[]{String.valueOf(userId), String.valueOf(businessId)})) {
            while (cursor.moveToNext()) result.put(new JSONObject(cursor.getString(0)));
        }
        return result;
    }

    public void markSynced(String[] clientIds) {
        int userId = account.getInt("user_id", 0);
        int businessId = account.getInt("business_id", 0);
        if (userId <= 0 || businessId <= 0) return;
        SQLiteDatabase db = getWritableDatabase();
        db.beginTransaction();
        try {
            for (String clientId : clientIds) {
                db.delete("wallet_captures", "client_id = ? AND user_id = ? AND business_id = ?",
                    new String[]{clientId, String.valueOf(userId), String.valueOf(businessId)});
            }
            db.setTransactionSuccessful();
        } finally {
            db.endTransaction();
        }
    }

    private static String limit(String value, int maxLength) {
        return value.length() <= maxLength ? value : value.substring(0, maxLength);
    }

    private static String fingerprint(String value) throws NoSuchAlgorithmException {
        byte[] digest = MessageDigest.getInstance("SHA-256").digest(value.getBytes(StandardCharsets.UTF_8));
        StringBuilder hex = new StringBuilder(digest.length * 2);
        for (byte part : digest) hex.append(String.format(Locale.US, "%02x", part & 0xff));
        return hex.toString();
    }
}
