import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  AppState,
} from 'react-native';

import {
  isNotificationAccessGranted,
  openNotificationSettings,
} from '../../modules/NotificationModule';
import { COLORS } from '../../theme';

const ProfileScreen = () => {
  // null = still checking, true/false = result
  const [permissionGranted, setPermissionGranted] = useState(null);

  const checkPermission = useCallback(async () => {
    const granted = await isNotificationAccessGranted();
    setPermissionGranted(granted);
  }, []);

  useEffect(() => {
    checkPermission();
  }, [checkPermission]);

  // Re-check whenever the app comes back to foreground (user returns from Settings)
  useEffect(() => {
    const subscription = AppState.addEventListener('change', (nextState) => {
      if (nextState === 'active') {
        checkPermission();
      }
    });
    return () => subscription.remove();
  }, [checkPermission]);

  const handleGrantPermission = async () => {
    await openNotificationSettings();
  };

  return (
    <ScrollView style={styles.container}>

      {/* Header */}
      <View style={styles.header}>
        <View style={styles.avatar}>
          <Text style={styles.avatarText}>S</Text>
        </View>
        <Text style={styles.name}>Staff User</Text>
        <Text style={styles.role}>Staff / Cashier</Text>
      </View>

      {/* Notification Access Setting */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Permissions</Text>

        <View style={styles.settingRow}>
          <View style={styles.settingInfo}>
            <Text style={styles.settingLabel}>Notification Access</Text>
            <Text style={styles.settingDesc}>
              Required to capture GCash, Maya, GrabPay, and ShopeePay transactions automatically.
            </Text>
          </View>

          {permissionGranted === null ? (
            <View style={styles.checkingBadge}>
              <Text style={styles.checkingText}>Checking…</Text>
            </View>
          ) : permissionGranted ? (
            <TouchableOpacity
              style={styles.revokeButton}
              onPress={handleGrantPermission}>
              <Text style={styles.revokeButtonText}>Turn Off</Text>
            </TouchableOpacity>
          ) : (
            <TouchableOpacity
              style={styles.grantButton}
              onPress={handleGrantPermission}>
              <Text style={styles.grantButtonText}>Grant</Text>
            </TouchableOpacity>
          )}
        </View>

        {/* Status badge */}
        {permissionGranted !== null && (
          <View style={[
            styles.statusBanner,
            permissionGranted ? styles.statusGranted : styles.statusDenied,
          ]}>
            <Text style={[
              styles.statusText,
              permissionGranted ? styles.statusTextGranted : styles.statusTextDenied,
            ]}>
              {permissionGranted
                ? '✅ Notification access is active. Transactions will be captured automatically.'
                : '⚠️ Notification access is not granted. Tap Grant to enable automatic transaction capture.'}
            </Text>
          </View>
        )}
      </View>

      {/* Account Info */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Account</Text>
        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Role</Text>
          <Text style={styles.infoValue}>Staff / Cashier</Text>
        </View>
        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Business</Text>
          <Text style={styles.infoValue}>—</Text>
        </View>
        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Linked Since</Text>
          <Text style={styles.infoValue}>—</Text>
        </View>
      </View>

    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#F5F5F5',
  },
  header: {
    backgroundColor: COLORS.bgDark,
    alignItems: 'center',
    paddingVertical: 32,
    paddingTop: 48,
  },
  avatar: {
    width: 72,
    height: 72,
    borderRadius: 36,
    backgroundColor: '#FFFFFF',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 12,
  },
  avatarText: {
    fontSize: 28,
    fontWeight: 'bold',
    color: COLORS.accent,
  },
  name: {
    fontSize: 20,
    fontWeight: 'bold',
    color: COLORS.textDark,
  },
  role: {
    fontSize: 13,
    color: 'rgba(0,0,0,0.5)',
    marginTop: 4,
  },
  section: {
    backgroundColor: '#FFFFFF',
    marginTop: 16,
    marginHorizontal: 16,
    borderRadius: 12,
    padding: 16,
    elevation: 1,
  },
  sectionTitle: {
    fontSize: 13,
    fontWeight: 'bold',
    color: '#888888',
    marginBottom: 12,
    textTransform: 'uppercase',
    letterSpacing: 1,
  },
  settingRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  settingInfo: {
    flex: 1,
    marginRight: 12,
  },
  settingLabel: {
    fontSize: 15,
    fontWeight: 'bold',
    color: '#1A1A1A',
  },
  settingDesc: {
    fontSize: 12,
    color: '#888888',
    marginTop: 4,
    lineHeight: 18,
  },
  checkingBadge: {
    backgroundColor: '#F5F5F5',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 20,
  },
  checkingText: {
    fontSize: 12,
    color: '#AAAAAA',
    fontWeight: 'bold',
  },
  grantButton: {
    backgroundColor: COLORS.accent,
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 20,
  },
  grantButtonText: {
    fontSize: 12,
    color: '#FFFFFF',
    fontWeight: 'bold',
  },
  revokeButton: {
    backgroundColor: '#FFEBEE',
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 20,
  },
  revokeButtonText: {
    fontSize: 12,
    color: '#C62828',
    fontWeight: 'bold',
  },
  statusBanner: {
    borderRadius: 8,
    padding: 12,
    marginTop: 12,
  },
  statusGranted: {
    backgroundColor: '#E8F5E9',
  },
  statusDenied: {
    backgroundColor: '#FFF8E1',
  },
  statusText: {
    fontSize: 12,
    lineHeight: 18,
  },
  statusTextGranted: {
    color: '#2E7D32',
  },
  statusTextDenied: {
    color: '#F57F17',
  },
  infoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: 10,
    borderBottomWidth: 1,
    borderBottomColor: '#F0F0F0',
  },
  infoLabel: {
    fontSize: 14,
    color: '#555555',
  },
  infoValue: {
    fontSize: 14,
    color: '#1A1A1A',
    fontWeight: '500',
  },
});

export default ProfileScreen;
