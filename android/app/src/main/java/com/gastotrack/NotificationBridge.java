package com.gastotrack;

import android.content.ComponentName;
import android.provider.Settings;
import android.text.TextUtils;

import com.facebook.react.bridge.ReactApplicationContext;
import com.facebook.react.bridge.ReactContextBaseJavaModule;
import com.facebook.react.bridge.ReactMethod;
import com.facebook.react.bridge.Promise;

public class NotificationBridge extends ReactContextBaseJavaModule {

    private final ReactApplicationContext reactContext;

    public NotificationBridge(ReactApplicationContext reactContext) {
        super(reactContext);
        this.reactContext = reactContext;

        // Give the NotificationListener access to the ReactContext
        // so it can emit events to JavaScript
        NotificationListener.reactContext = reactContext;
    }

    // The name JavaScript uses to call this module
    @Override
    public String getName() {
        return "NotificationBridge";
    }

    // ----------------------------------------------------------------
    // Called from JS to check if notification access is granted
    // ----------------------------------------------------------------
    @ReactMethod
    public void isNotificationAccessGranted(Promise promise) {
        try {
            String enabledListeners = Settings.Secure.getString(
                reactContext.getContentResolver(),
                "enabled_notification_listeners"
            );

            if (TextUtils.isEmpty(enabledListeners)) {
                promise.resolve(false);
                return;
            }

            String ourComponent = new ComponentName(
                reactContext,
                NotificationListener.class
            ).flattenToString();

            promise.resolve(enabledListeners.contains(ourComponent));
        } catch (Exception e) {
            promise.reject("ERROR", e.getMessage());
        }
    }

    // ----------------------------------------------------------------
    // Called from JS to open Android notification access settings
    // ----------------------------------------------------------------
    @ReactMethod
    public void openNotificationSettings(Promise promise) {
        try {
            android.content.Intent intent = new android.content.Intent(
                Settings.ACTION_NOTIFICATION_LISTENER_SETTINGS
            );
            intent.addFlags(android.content.Intent.FLAG_ACTIVITY_NEW_TASK);
            reactContext.startActivity(intent);
            promise.resolve(true);
        } catch (Exception e) {
            promise.reject("ERROR", e.getMessage());
        }
    }

    // ----------------------------------------------------------------
    // Required for React Native event emitter — prevents warnings
    // ----------------------------------------------------------------
    @ReactMethod
    public void addListener(String eventName) {
        // Required but no implementation needed
    }

    @ReactMethod
    public void removeListeners(Integer count) {
        // Required but no implementation needed
    }
}