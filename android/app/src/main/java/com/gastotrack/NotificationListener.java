package com.gastotrack;

import android.service.notification.NotificationListenerService;
import android.service.notification.StatusBarNotification;
import android.os.Bundle;
import android.app.Notification;

import com.facebook.react.bridge.ReactContext;
import com.facebook.react.modules.core.DeviceEventManagerModule;
import com.facebook.react.bridge.WritableMap;
import com.facebook.react.bridge.Arguments;

public class NotificationListener extends NotificationListenerService {

    private static final String[] SUPPORTED_APPS = {
        NotificationParser.GCASH,
        NotificationParser.MAYA,
        NotificationParser.GRABPAY,
        NotificationParser.SHOPEEPAY
    };

    // Static reference so the bridge can access the current instance
    public static NotificationListener instance;

    // Static reference to ReactContext so we can emit events to JS
    public static ReactContext reactContext;

    @Override
    public void onCreate() {
        super.onCreate();
        instance = this;
    }

    @Override
    public void onDestroy() {
        super.onDestroy();
        instance = null;
    }

    @Override
    public void onNotificationPosted(StatusBarNotification sbn) {
        String packageName = sbn.getPackageName();

        // Ignore unsupported apps
        if (!isSupportedApp(packageName)) return;

        // Extract notification title and text
        Bundle extras = sbn.getNotification().extras;
        String title = extras.getString(Notification.EXTRA_TITLE, "");
        String text  = "";
        CharSequence textSeq = extras.getCharSequence(Notification.EXTRA_TEXT);
        if (textSeq != null) text = textSeq.toString();

        // Skip empty notifications
        if (title.isEmpty() && text.isEmpty()) return;

        // Parse the notification
        NotificationParser.ParsedTransaction parsed =
            NotificationParser.parse(packageName, title, text);

        if (parsed == null) return;

        // Emit the parsed transaction to the React Native JS side
        emitTransactionEvent(parsed);
    }

    @Override
    public void onNotificationRemoved(StatusBarNotification sbn) {
        // Not needed
    }

    // ----------------------------------------------------------------
    // Emit event to JavaScript via React Native event emitter
    // ----------------------------------------------------------------
    private void emitTransactionEvent(NotificationParser.ParsedTransaction parsed) {
        if (reactContext == null) return;
        if (!reactContext.hasActiveCatalystInstance()) return;

        WritableMap params = Arguments.createMap();
        params.putString("appSource",        parsed.appSource);
        params.putString("amount",           parsed.amount);
        params.putString("transactionType",  parsed.transactionType);
        params.putString("merchant",         parsed.merchant != null ? parsed.merchant : "");
        params.putString("rawMessage",       parsed.rawMessage);

        reactContext
            .getJSModule(DeviceEventManagerModule.RCTDeviceEventEmitter.class)
            .emit("onNotificationReceived", params);
    }

    private boolean isSupportedApp(String packageName) {
        for (String app : SUPPORTED_APPS) {
            if (app.equals(packageName)) return true;
        }
        return false;
    }
}