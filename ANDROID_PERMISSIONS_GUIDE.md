# Android Permissions Guide for GastoTrack

## 📋 Overview

Android permissions are declared in **TWO places**:

1. **AndroidManifest.xml** (XML) - Static permission declarations
2. **MainActivity.java** (Java) - Runtime permission requests

## 🗂️ File Structure

```
android/app/src/main/
├── AndroidManifest.xml          ← Permission declarations (XML)
├── java/com/gastotrack/
│   ├── MainActivity.java        ← Main activity with permission handling
│   └── PermissionHelper.java    ← Permission utility class (NEW)
```

---

## 1️⃣ AndroidManifest.xml (XML Configuration)

**Location**: `android/app/src/main/AndroidManifest.xml`

**Purpose**: Declares what permissions your app MIGHT need

```xml
<manifest xmlns:android="http://schemas.android.com/apk/res/android">

    <!-- Internet for API calls -->
    <uses-permission android:name="android.permission.INTERNET" />
    
    <!-- Camera for OCR receipt scanning -->
    <uses-permission android:name="android.permission.CAMERA" />
    
    <!-- Storage for saving/reading images -->
    <uses-permission android:name="android.permission.READ_EXTERNAL_STORAGE" />
    <uses-permission android:name="android.permission.WRITE_EXTERNAL_STORAGE" />
    
    <!-- Android 13+ media permissions -->
    <uses-permission android:name="android.permission.READ_MEDIA_IMAGES" />

    <!-- Camera features (not required, won't block install) -->
    <uses-feature android:name="android.hardware.camera" android:required="false" />
    <uses-feature android:name="android.hardware.camera.autofocus" android:required="false" />

    <application ...>
        <!-- Your app components -->
    </application>
</manifest>
```

### ⚠️ Important Notes:
- **This file is XML, NOT Java** ✅
- Declares permissions at install time
- Android 13+ requires `READ_MEDIA_IMAGES` instead of `READ_EXTERNAL_STORAGE`
- `required="false"` means devices without camera can still install the app

---

## 2️⃣ MainActivity.java (Runtime Permission Requests)

**Location**: `android/app/src/main/java/com/gastotrack/MainActivity.java`

**Purpose**: Handles runtime permission requests and results

```java
package com.gastotrack;

import android.os.Bundle;
import androidx.annotation.NonNull;
import com.facebook.react.ReactActivity;

public class MainActivity extends ReactActivity {

  @Override
  protected void onCreate(Bundle savedInstanceState) {
    super.onCreate(savedInstanceState);
    
    // React Native libraries handle permissions automatically
    // No manual request needed for camera/gallery
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
        requestCode, permissions, grantResults
    );
  }
}
```

---

## 3️⃣ PermissionHelper.java (Utility Class)

**Location**: `android/app/src/main/java/com/gastotrack/PermissionHelper.java`

**Purpose**: Utility methods for checking and requesting permissions

### Key Methods:

```java
// Check if camera permission is granted
PermissionHelper.hasCameraPermission(activity);

// Check if storage permission is granted
PermissionHelper.hasStoragePermission(activity);

// Request camera permission
PermissionHelper.requestCameraPermission(activity);

// Request storage permission (handles Android 13+ automatically)
PermissionHelper.requestStoragePermission(activity);

// Request all permissions at once
PermissionHelper.requestAllPermissions(activity);

// Check if all permissions are granted
PermissionHelper.hasAllPermissions(activity);
```

### Android Version Handling:
- **Android 13+ (API 33+)**: Uses `READ_MEDIA_IMAGES`
- **Android 12 and below**: Uses `READ_EXTERNAL_STORAGE` + `WRITE_EXTERNAL_STORAGE`

---

## 🔄 How Permissions Work in GastoTrack

### Automatic Permission Flow (React Native Libraries):

```
User taps 📸 button
    ↓
react-native-image-picker library
    ↓
Checks AndroidManifest.xml for CAMERA permission
    ↓
Requests permission from user (if not granted)
    ↓
User grants/denies
    ↓
Camera opens (if granted) or error shown (if denied)
```

### You DON'T need to manually request permissions because:
1. ✅ `react-native-image-picker` handles it automatically
2. ✅ `react-native-vision-camera` handles it automatically
3. ✅ `@react-native-ml-kit/text-recognition` works on-device (no permissions needed)

---

## 📱 User Experience

### First Time User Opens Camera:

1. **User taps 📸 Scan Receipt**
2. **System shows permission dialog**:
   ```
   ┌─────────────────────────────────┐
   │  Allow GastoTrack to take       │
   │  pictures and record video?     │
   │                                 │
   │  [Don't allow]  [Allow]         │
   └─────────────────────────────────┘
   ```
3. **User taps "Allow"**
4. **Camera opens** ✅

### Subsequent Uses:
- No permission dialog (already granted)
- Camera opens immediately ⚡

---

## 🛠️ Testing Permissions

### Reset Permissions (for testing):
1. Go to **Settings → Apps → GastoTrack**
2. Tap **Permissions**
3. Toggle **Camera** or **Files and media** off/on
4. Retest the app

### Check Permission Status Programmatically:
```java
if (PermissionHelper.hasCameraPermission(this)) {
    Log.d("MainActivity", "Camera permission granted ✅");
} else {
    Log.d("MainActivity", "Camera permission denied ❌");
}
```

---

## 🔍 Common Questions

### Q: Why do I need AndroidManifest.xml AND Java code?
**A**: 
- **AndroidManifest.xml**: Declares what permissions your app CAN use
- **Java code**: Requests permissions at runtime (when user needs them)

### Q: Is AndroidManifest.xml a Java file?
**A**: ❌ NO! It's an **XML file**. It's the configuration file for Android apps.

### Q: Do I need to write Java code for permissions?
**A**: ✅ Already done! I created:
- `PermissionHelper.java` - Utility class
- Updated `MainActivity.java` - Handles permission callbacks

### Q: When does the app ask for camera permission?
**A**: Automatically when user taps the 📸 button for the first time.

### Q: What if user denies permission?
**A**: 
- User sees an error message
- Can grant permission later in Settings
- App continues to work for other features

---

## 📝 Summary

| File | Type | Purpose |
|------|------|---------|
| AndroidManifest.xml | XML | Declare permissions |
| MainActivity.java | Java | Handle permission results |
| PermissionHelper.java | Java | Utility methods |
| React Native Libraries | JavaScript | Auto-request permissions |

### ✅ Current Setup:
1. ✅ AndroidManifest.xml has all required permissions
2. ✅ MainActivity.java handles permission callbacks
3. ✅ PermissionHelper.java provides utility methods
4. ✅ React Native libraries handle automatic requests
5. ✅ OCR feature ready to use!

### 🎯 You're All Set!
The permissions are properly configured. Users will be prompted automatically when they try to use the camera.

---

## 🚀 Next Steps

Want to test the OCR feature?

1. **Run the app**: `npx react-native run-android`
2. **Switch to Staff role**
3. **Go to Transactions tab**
4. **Tap the 📸 button**
5. **Grant camera permission** when prompted
6. **Take a photo** of a receipt
7. **See the magic!** ✨

The app will automatically ask for permissions when needed!
