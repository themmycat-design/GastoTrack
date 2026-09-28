package com.gastotrack;

import com.facebook.react.bridge.ReactApplicationContext;
import com.facebook.react.bridge.ReactContextBaseJavaModule;
import com.facebook.react.bridge.ReactMethod;
import com.facebook.react.bridge.Promise;
import com.facebook.react.modules.core.PermissionAwareActivity;
import com.facebook.react.modules.core.PermissionListener;

import android.content.ComponentName;
import android.content.Intent;
import android.provider.Settings;
import android.text.TextUtils;

/**
 * React Native Module for Notification Listener Service
 * Provides methods to check permission status and open settings
 */
public class NotificationModule extends ReactContextBaseJavaModule {

    private static final String MODULE_NAME = "NotificationModule";

    public NotificationModule(ReactApplicationContext reactContext) {
        super(reactContext);
        // Set the static reference so NotificationListener can emit events
        NotificationListener.reactContext = reactContext;
    }

    @Override
    public String getName() {
        return MODULE_NAME;
    }

    /**
     * Check if notification access permission is granted
     */
    @ReactMethod
    public void isNotificationAccessGranted(Promise promise) {
        try {
            ReactApplicationContext context = getReactApplicationContext();
            String packageName = context.getPackageName();
            
            String flat = Settings.Secure.getString(
                context.getContentResolver(),
                "enabled_notification_listeners"
            );

            if (flat == null || flat.isEmpty()) {
                promise.resolve(false);
                return;
            }

            String[] names = flat.split(":");
            for (String name : names) {
                ComponentName cn = ComponentName.unflattenFromString(name);
                if (cn != null && TextUtils.equals(packageName, cn.getPackageName())) {
                    promise.resolve(true);
                    return;
                }
            }

            promise.resolve(false);
        } catch (Exception e) {
            promise.reject("ERROR", "Failed to check notification access: " + e.getMessage());
        }
    }

    /**
     * Open notification access settings screen
     */
    @ReactMethod
    public void openNotificationSettings(Promise promise) {
        try {
            Intent intent = new Intent(Settings.ACTION_NOTIFICATION_LISTENER_SETTINGS);
            intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
            
            ReactApplicationContext context = getReactApplicationContext();
            context.startActivity(intent);
            
            promise.resolve(true);
        } catch (Exception e) {
            promise.reject("ERROR", "Failed to open settings: " + e.getMessage());
        }
    }

    /**
     * Get service status (running or not)
     */
    @ReactMethod
    public void isServiceRunning(Promise promise) {
        try {
            boolean isRunning = NotificationListener.instance != null;
            promise.resolve(isRunning);
        } catch (Exception e) {
            promise.reject("ERROR", "Failed to check service status: " + e.getMessage());
        }
    }
}
