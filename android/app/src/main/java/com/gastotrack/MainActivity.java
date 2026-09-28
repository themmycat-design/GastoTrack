package com.gastotrack;

import android.os.Bundle;
import androidx.annotation.NonNull;
import com.facebook.react.ReactActivity;
import com.facebook.react.ReactActivityDelegate;
import com.facebook.react.defaults.DefaultNewArchitectureEntryPoint;
import com.facebook.react.defaults.DefaultReactActivityDelegate;

public class MainActivity extends ReactActivity {

  @Override
  protected String getMainComponentName() {
    return "GastoTrack";
  }

  @Override
  protected ReactActivityDelegate createReactActivityDelegate() {
    return new DefaultReactActivityDelegate(
        this,
        getMainComponentName(),
        DefaultNewArchitectureEntryPoint.getFabricEnabled());
  }

  @Override
  protected void onCreate(Bundle savedInstanceState) {
    super.onCreate(savedInstanceState);
    
    // Request permissions when activity is created
    // Note: React Native libraries usually handle permission requests themselves
    // This is here as a fallback/reference
    if (!PermissionHelper.hasAllPermissions(this)) {
      // Permissions will be requested by react-native-image-picker and react-native-vision-camera
      // when user tries to use camera/gallery
      // No need to request upfront
    }
  }

  @Override
  public void onRequestPermissionsResult(
      int requestCode,
      @NonNull String[] permissions,
      @NonNull int[] grantResults
  ) {
    super.onRequestPermissionsResult(requestCode, permissions, grantResults);
    
    // Handle permission results
    boolean granted = PermissionHelper.handlePermissionResult(
        requestCode,
        permissions,
        grantResults
    );
    
    // React Native libraries will handle their own permission callbacks
    // This is just for logging/debugging
    if (granted) {
      android.util.Log.d("MainActivity", "Permissions granted");
    } else {
      android.util.Log.w("MainActivity", "Permissions denied");
    }
  }
}
