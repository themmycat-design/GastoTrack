package com.gastotrack;

import android.content.ContentValues;
import android.content.Context;
import android.database.Cursor;
import android.database.sqlite.SQLiteDatabase;
import android.database.sqlite.SQLiteOpenHelper;

import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;

/** Device-private, account-scoped outbox. Only server-confirmed actions are removed. */
public final class StaffOfflineStore extends SQLiteOpenHelper {
    private static StaffOfflineStore instance;

    public static synchronized StaffOfflineStore getInstance(Context context) {
        if (instance == null) instance = new StaffOfflineStore(context.getApplicationContext());
        return instance;
    }

    private StaffOfflineStore(Context context) {
        super(context, "staff_offline_actions.db", null, 1);
    }

    @Override
    public void onCreate(SQLiteDatabase db) {
        db.execSQL("CREATE TABLE actions (client_id TEXT PRIMARY KEY, user_id INTEGER NOT NULL, business_id INTEGER NOT NULL, kind TEXT NOT NULL, payload TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'pending', error TEXT, created_at INTEGER NOT NULL)");
        db.execSQL("CREATE INDEX actions_account ON actions (user_id, business_id, status, created_at)");
        db.execSQL("CREATE TABLE snapshots (user_id INTEGER NOT NULL, business_id INTEGER NOT NULL, kind TEXT NOT NULL, payload TEXT NOT NULL, saved_at INTEGER NOT NULL, PRIMARY KEY (user_id, business_id, kind))");
    }

    @Override
    public void onUpgrade(SQLiteDatabase db, int oldVersion, int newVersion) {
        // Future upgrades must preserve unsynced actions.
    }

    public void enqueue(int userId, int businessId, String clientId, String kind, String payload) {
        if (userId <= 0 || businessId <= 0 || clientId == null || clientId.isEmpty()) throw new IllegalArgumentException("Missing account or action identifier");
        if (!kind.equals("transaction") && !kind.equals("cash_order") && !kind.equals("stock_adjustment")
            && !kind.equals("product_create") && !kind.equals("product_update") && !kind.equals("product_delete") && !kind.equals("stock_create")
            && !kind.equals("transaction_update") && !kind.equals("transaction_delete")) {
            throw new IllegalArgumentException("Unsupported offline action");
        }
        SQLiteDatabase db = getWritableDatabase();
        db.beginTransaction();
        try {
            try (Cursor existing = db.query("actions", new String[]{"user_id", "business_id", "kind", "payload"}, "client_id = ?", new String[]{clientId}, null, null, null)) {
                if (existing.moveToFirst()) {
                    if (existing.getInt(0) != userId || existing.getInt(1) != businessId || !kind.equals(existing.getString(2)) || !payload.equals(existing.getString(3))) {
                        throw new IllegalStateException("Offline action identifier already belongs to another submission");
                    }
                    ContentValues retry = new ContentValues();
                    retry.put("status", "pending");
                    retry.putNull("error");
                    db.update("actions", retry, "client_id = ?", new String[]{clientId});
                } else {
                    ContentValues values = new ContentValues();
                    values.put("client_id", clientId);
                    values.put("user_id", userId);
                    values.put("business_id", businessId);
                    values.put("kind", kind);
                    values.put("payload", payload);
                    values.put("created_at", System.currentTimeMillis());
                    db.insertOrThrow("actions", null, values);
                }
            }
            db.setTransactionSuccessful();
        } finally {
            db.endTransaction();
        }
    }

    public JSONArray list(int userId, int businessId) throws JSONException {
        JSONArray result = new JSONArray();
        try (Cursor rows = getReadableDatabase().query("actions", new String[]{"client_id", "kind", "payload", "status", "error", "created_at"},
            "user_id = ? AND business_id = ?", new String[]{String.valueOf(userId), String.valueOf(businessId)}, null, null, "created_at ASC, rowid ASC")) {
            while (rows.moveToNext()) {
                JSONObject row = new JSONObject();
                row.put("client_id", rows.getString(0));
                row.put("kind", rows.getString(1));
                row.put("payload", new JSONObject(rows.getString(2)));
                row.put("status", rows.getString(3));
                row.put("error", rows.isNull(4) ? JSONObject.NULL : rows.getString(4));
                row.put("created_at", rows.getLong(5));
                result.put(row);
            }
        }
        return result;
    }

    public void markSynced(int userId, int businessId, String clientId) {
        getWritableDatabase().delete("actions", "user_id = ? AND business_id = ? AND client_id = ?", new String[]{String.valueOf(userId), String.valueOf(businessId), clientId});
    }

    public void markRejected(int userId, int businessId, String clientId, String error) {
        ContentValues values = new ContentValues();
        values.put("status", "rejected");
        values.put("error", error == null ? "Server rejected this action" : error.substring(0, Math.min(error.length(), 300)));
        getWritableDatabase().update("actions", values, "user_id = ? AND business_id = ? AND client_id = ?", new String[]{String.valueOf(userId), String.valueOf(businessId), clientId});
    }

    public void retry(int userId, int businessId, String clientId) {
        ContentValues values = new ContentValues();
        values.put("status", "pending");
        values.putNull("error");
        getWritableDatabase().update("actions", values, "user_id = ? AND business_id = ? AND client_id = ? AND status = 'rejected'",
            new String[]{String.valueOf(userId), String.valueOf(businessId), clientId});
    }

    public void saveSnapshot(int userId, int businessId, String kind, String payload) {
        if (!kind.equals("products") && !kind.equals("stock") && !kind.equals("transaction_options")
            && !kind.equals("transactions") && !kind.equals("orders") && !kind.equals("daily_income")) {
            throw new IllegalArgumentException("Unsupported snapshot");
        }
        ContentValues values = new ContentValues();
        values.put("user_id", userId);
        values.put("business_id", businessId);
        values.put("kind", kind);
        values.put("payload", payload);
        values.put("saved_at", System.currentTimeMillis());
        getWritableDatabase().insertWithOnConflict("snapshots", null, values, SQLiteDatabase.CONFLICT_REPLACE);
    }

    public String getSnapshot(int userId, int businessId, String kind) {
        try (Cursor row = getReadableDatabase().query("snapshots", new String[]{"payload"}, "user_id = ? AND business_id = ? AND kind = ?",
            new String[]{String.valueOf(userId), String.valueOf(businessId), kind}, null, null, null)) {
            return row.moveToFirst() ? row.getString(0) : null;
        }
    }
}
