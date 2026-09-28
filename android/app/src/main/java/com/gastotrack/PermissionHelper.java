package com.gastotrack;

import android.Manifest;
import android.app.Activity;
import android.content.pm.PackageManager;
import android.os.Build;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;

/**
 * Helper class for requesting runtime permissions
 */
public class PermissionHelper {
    
    // Permission request codes
    public static final int CAMERA_PERMISSION_REQUEST = 100;
    public static final int STORAGE_PERMISSION_REQUEST = 101;
    public static final int ALL_PERMISSIONS_REQUEST = 102;
    
    /**
     * Check if camera permission is granted
     */
    public static boolean hasCameraPermission(Activity activity) {
        return ContextCompat.checkSelfPermission(
            activity, 
            Manifest.permission.CAMERA
        ) == PackageManager.PERMISSION_GRANTED;
    }
    
    /**
     * Check if storage permissions are granted
     */
    public static boolean hasStoragePermission(Activity activity) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            // Android 13+ uses READ_MEDIA_IMAGES
            return ContextCompat.checkSelfPermission(
                activity,
                Manifest.permission.READ_MEDIA_IMAGES
            ) == PackageManager.PERMISSION_GRANTED;
        } else {
            // Android 12 and below
            return ContextCompat.checkSelfPermission(
                activity,
                Manifest.permission.READ_EXTERNAL_STORAGE
            ) == PackageManager.PERMISSION_GRANTED;
        }
    }
    
    /**
     * Request camera permission
     */
    public static void requestCameraPermission(Activity activity) {
        ActivityCompat.requestPermissions(
            activity,
            new String[]{Manifest.permission.CAMERA},
            CAMERA_PERMISSION_REQUEST
        );
    }
    
    /**
     * Request storage permission (handles Android version differences)
     */
    public static void requestStoragePermission(Activity activity) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            // Android 13+
            ActivityCompat.requestPermissions(
                activity,
                new String[]{Manifest.permission.READ_MEDIA_IMAGES},
                STORAGE_PERMISSION_REQUEST
            );
        } else {
            // Android 12 and below
            ActivityCompat.requestPermissions(
                activity,
                new String[]{
                    Manifest.permission.READ_EXTERNAL_STORAGE,
                    Manifest.permission.WRITE_EXTERNAL_STORAGE
                },
                STORAGE_PERMISSION_REQUEST
            );
        }
    }
    
    /**
     * Request all required permissions at once
     */
    public static void requestAllPermissions(Activity activity) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            // Android 13+
            ActivityCompat.requestPermissions(
                activity,
                new String[]{
                    Manifest.permission.CAMERA,
                    Manifest.permission.READ_MEDIA_IMAGES
                },
                ALL_PERMISSIONS_REQUEST
            );
        } else {
            // Android 12 and below
            ActivityCompat.requestPermissions(
                activity,
                new String[]{
                    Manifest.permission.CAMERA,
                    Manifest.permission.READ_EXTERNAL_STORAGE,
                    Manifest.permission.WRITE_EXTERNAL_STORAGE
                },
                ALL_PERMISSIONS_REQUEST
            );
        }
    }
    
    /**
     * Check if all required permissions are granted
     */
    public static boolean hasAllPermissions(Activity activity) {
        return hasCameraPermission(activity) && hasStoragePermission(activity);
    }
    
    /**
     * Handle permission request result
     */
    public static boolean handlePermissionResult(
        int requestCode,
        String[] permissions,
        int[] grantResults
    ) {
        if (grantResults.length > 0) {
            // Check if all permissions were granted
            for (int result : grantResults) {
                if (result != PackageManager.PERMISSION_GRANTED) {
                    return false; // At least one permission was denied
                }
            }
            return true; // All permissions granted
        }
        return false;
    }
}
