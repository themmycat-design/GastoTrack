import { NativeModules, NativeEventEmitter, Platform } from 'react-native';

const { NotificationModule } = NativeModules;
const notificationEmitter = new NativeEventEmitter(NativeModules.NotificationModule);

/**
 * Service to handle e-wallet notification capture
 * Interfaces with native NotificationListenerService on Android
 */
class NotificationService {
  constructor() {
    this.listeners = [];
    this.isListening = false;
  }

  /**
   * Check if notification access permission is granted
   */
  async isPermissionGranted() {
    if (Platform.OS !== 'android') {
      return false;
    }

    try {
      const granted = await NotificationModule.isNotificationAccessGranted();
      return granted;
    } catch (error) {
      console.error('Error checking notification permission:', error);
      return false;
    }
  }

  /**
   * Open Android notification access settings
   */
  async requestPermission() {
    if (Platform.OS !== 'android') {
      return false;
    }

    try {
      await NotificationModule.openNotificationSettings();
      return true;
    } catch (error) {
      console.error('Error opening notification settings:', error);
      return false;
    }
  }

  /**
   * Check if the notification listener service is running
   */
  async isServiceRunning() {
    if (Platform.OS !== 'android') {
      return false;
    }

    try {
      const running = await NotificationModule.isServiceRunning();
      return running;
    } catch (error) {
      console.error('Error checking service status:', error);
      return false;
    }
  }

  /**
   * Start listening for e-wallet notifications
   * @param {Function} callback - Called when notification is captured
   */
  startListening(callback) {
    if (this.isListening) {
      console.warn('Already listening for notifications');
      return;
    }

    const subscription = notificationEmitter.addListener(
      'onNotificationReceived',
      (data) => {
        console.log('Notification received:', data);
        
        // Parse the notification data
        const parsed = this.parseNotification(data);
        
        // Call the callback with parsed data
        if (callback && typeof callback === 'function') {
          callback(parsed);
        }
      }
    );

    this.listeners.push(subscription);
    this.isListening = true;

    console.log('Started listening for e-wallet notifications');
  }

  /**
   * Stop listening for notifications
   */
  stopListening() {
    if (!this.isListening) {
      return;
    }

    this.listeners.forEach(subscription => {
      subscription.remove();
    });

    this.listeners = [];
    this.isListening = false;

    console.log('Stopped listening for e-wallet notifications');
  }

  /**
   * Parse notification data into transaction format
   */
  parseNotification(data) {
    const { appSource, amount, transactionType, merchant, rawMessage } = data;

    // Determine transaction type (Income or Expense)
    const type = transactionType === 'received' ? 'Income' : 'Expense';

    // Determine category based on type
    const category = type === 'Income' ? 'Sales' : 'Others';

    // Map app source to payment method
    const sourceMap = {
      'com.globe.gcash.android': 'GCash',
      'com.paymaya': 'Maya',
      'com.grabtaxi.passenger': 'GrabPay',
      'com.shopee.ph': 'ShopeePay',
    };

    const source = sourceMap[appSource] || 'Others';

    return {
      amount: parseFloat(amount) || 0,
      type,
      source,
      category,
      date: new Date().toISOString().split('T')[0],
      notes: merchant ? `From ${merchant}` : rawMessage || 'Auto-captured from notification',
      entryMethod: 'Notification Capture',
      rawData: data,
    };
  }

  /**
   * Get supported e-wallet apps
   */
  getSupportedApps() {
    return [
      { name: 'GCash', package: 'com.globe.gcash.android', icon: '💚' },
      { name: 'Maya', package: 'com.paymaya', icon: '🟢' },
      { name: 'GrabPay', package: 'com.grabtaxi.passenger', icon: '🟩' },
      { name: 'ShopeePay', package: 'com.shopee.ph', icon: '🟠' },
    ];
  }
}

export default new NotificationService();
