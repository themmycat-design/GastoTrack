package com.gastotrack;

import com.facebook.react.bridge.ReactApplicationContext;
import com.facebook.react.bridge.ReactContextBaseJavaModule;
import com.facebook.react.bridge.ReactMethod;
import com.facebook.react.bridge.Promise;
import com.facebook.react.bridge.ReadableArray;
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

    @ReactMethod
    public void queueStaffAction(int userId, int businessId, String clientId, String kind, String payload, Promise promise) {
        try {
            StaffOfflineStore.getInstance(getReactApplicationContext()).enqueue(userId, businessId, clientId, kind, payload);
            promise.resolve(true);
        } catch (Exception exception) {
            promise.reject("OFFLINE_WRITE_ERROR", "Could not safely save this action on the device.");
        }
    }

    @ReactMethod
    public void getStaffActions(int userId, int businessId, Promise promise) {
        try {
            promise.resolve(StaffOfflineStore.getInstance(getReactApplicationContext()).list(userId, businessId).toString());
        } catch (Exception exception) {
            promise.reject("OFFLINE_READ_ERROR", "Could not read offline actions.");
        }
    }

    @ReactMethod
    public void markStaffActionSynced(int userId, int businessId, String clientId, Promise promise) {
        try {
            StaffOfflineStore.getInstance(getReactApplicationContext()).markSynced(userId, businessId, clientId);
            promise.resolve(true);
        } catch (Exception exception) {
            promise.reject("OFFLINE_SYNC_ERROR", "Could not update the offline action.");
        }
    }

    @ReactMethod
    public void markStaffActionRejected(int userId, int businessId, String clientId, String reason, Promise promise) {
        try {
            StaffOfflineStore.getInstance(getReactApplicationContext()).markRejected(userId, businessId, clientId, reason);
            promise.resolve(true);
        } catch (Exception exception) {
            promise.reject("OFFLINE_REJECT_ERROR", "Could not save the sync error.");
        }
    }

    @ReactMethod
    public void retryStaffAction(int userId, int businessId, String clientId, Promise promise) {
        try {
            StaffOfflineStore.getInstance(getReactApplicationContext()).retry(userId, businessId, clientId);
            promise.resolve(true);
        } catch (Exception exception) {
            promise.reject("OFFLINE_RETRY_ERROR", "Could not retry the offline action.");
        }
    }

    @ReactMethod
    public void saveStaffSnapshot(int userId, int businessId, String kind, String payload, Promise promise) {
        try {
            StaffOfflineStore.getInstance(getReactApplicationContext()).saveSnapshot(userId, businessId, kind, payload);
            promise.resolve(true);
        } catch (Exception exception) {
            promise.reject("OFFLINE_SNAPSHOT_ERROR", "Could not cache staff data.");
        }
    }

    @ReactMethod
    public void getStaffSnapshot(int userId, int businessId, String kind, Promise promise) {
        try {
            promise.resolve(StaffOfflineStore.getInstance(getReactApplicationContext()).getSnapshot(userId, businessId, kind));
        } catch (Exception exception) {
            promise.reject("OFFLINE_SNAPSHOT_ERROR", "Could not read cached staff data.");
        }
    }

    @ReactMethod
    public void setCaptureAccount(int userId, int businessId, Promise promise) {
        try {
            WalletCaptureStore.getInstance(getReactApplicationContext()).setActiveAccount(userId, businessId);
            promise.resolve(true);
        } catch (Exception exception) {
            promise.reject("CAPTURE_ACCOUNT_ERROR", "Could not set the active capture account.");
        }
    }

    @ReactMethod
    public void getPendingCaptures(Promise promise) {
        try {
            promise.resolve(WalletCaptureStore.getInstance(getReactApplicationContext()).pending().toString());
        } catch (Exception exception) {
            promise.reject("CAPTURE_READ_ERROR", "Could not read captured wallet notifications.");
        }
    }

    @ReactMethod
    public void markCapturesSynced(ReadableArray ids, Promise promise) {
        try {
            String[] clientIds = new String[ids.size()];
            for (int index = 0; index < ids.size(); index++) clientIds[index] = ids.getString(index);
            WalletCaptureStore.getInstance(getReactApplicationContext()).markSynced(clientIds);
            promise.resolve(true);
        } catch (Exception exception) {
            promise.reject("CAPTURE_SYNC_ERROR", "Could not clear synced wallet notifications.");
        }
    }

    @ReactMethod
    public void markCaptureRejected(String clientId, String reason, Promise promise) {
        try {
            WalletCaptureStore.getInstance(getReactApplicationContext()).markRejected(clientId, reason);
            promise.resolve(true);
        } catch (Exception exception) {
            promise.reject("CAPTURE_REJECT_ERROR", "Could not save the rejected capture.");
        }
    }

    @ReactMethod
    public void getRejectedCaptures(Promise promise) {
        try {
            promise.resolve(WalletCaptureStore.getInstance(getReactApplicationContext()).rejected().toString());
        } catch (Exception exception) {
            promise.reject("CAPTURE_READ_ERROR", "Could not read rejected wallet captures.");
        }
    }

    @ReactMethod
    public void retryRejectedCapture(String clientId, Promise promise) {
        try {
            WalletCaptureStore.getInstance(getReactApplicationContext()).retryRejected(clientId);
            promise.resolve(true);
        } catch (Exception exception) {
            promise.reject("CAPTURE_RETRY_ERROR", "Could not retry the wallet capture.");
        }
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
