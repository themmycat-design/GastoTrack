import { NativeModules, NativeEventEmitter, Platform } from 'react-native';

const { NotificationModule } = NativeModules;

// Event emitter — uses NotificationModule which is the registered native package
const emitter = NotificationModule
  ? new NativeEventEmitter(NotificationModule)
  : null;

// ----------------------------------------------------------------
// Check if notification access is granted
// Returns: true or false
// ----------------------------------------------------------------
export const isNotificationAccessGranted = async () => {
  if (Platform.OS !== 'android' || !NotificationModule) return false;
  try {
    const granted = await NotificationModule.isNotificationAccessGranted();
    return granted;
  } catch (error) {
    console.error('isNotificationAccessGranted error:', error);
    return false;
  }
};

// ----------------------------------------------------------------
// Open Android notification access settings
// ----------------------------------------------------------------
export const openNotificationSettings = async () => {
  if (Platform.OS !== 'android' || !NotificationModule) return;
  try {
    await NotificationModule.openNotificationSettings();
  } catch (error) {
    console.error('openNotificationSettings error:', error);
  }
};

// ----------------------------------------------------------------
// Subscribe to incoming notifications
// Returns an unsubscribe function — call it in useEffect cleanup
//
// Usage:
//   const unsubscribe = subscribeToNotifications((transaction) => {
//     console.log(transaction);
//   });
//   return () => unsubscribe(); // cleanup
// ----------------------------------------------------------------
export const subscribeToNotifications = (callback) => {
  if (!emitter) return () => {};

  const subscription = emitter.addListener(
    'onNotificationReceived',
    (transaction) => {
      callback(transaction);
    }
  );

  return () => subscription.remove();
};
