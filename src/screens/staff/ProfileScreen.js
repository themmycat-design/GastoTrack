import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  AppState,
  Alert,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';

import {
  isNotificationAccessGranted,
  openNotificationSettings,
} from '../../modules/NotificationModule';
import { COLORS } from '../../theme';
import { useAuth } from '../../context/AuthContext';
import StaffScreenHeader from '../../components/staff/StaffScreenHeader';

const ProfileScreen = () => {
  const { user, userRole, business, logout } = useAuth();
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

  const handleLogout = () => {
    Alert.alert('Sign out?', 'You will need to sign in again to continue.', [
      { text: 'Cancel', style: 'cancel' },
      { text: 'Sign out', style: 'destructive', onPress: logout },
    ]);
  };

  const initial = user?.name?.trim()?.charAt(0)?.toUpperCase() || 'S';

  return (
    <SafeAreaView style={styles.container} edges={[]}>
    <StaffScreenHeader
      title="Profile"
      subtitle="Account and app settings"
      icon="account-outline"
    />
    <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>

      {/* Header */}
      <View style={styles.profileCard}>
        <View style={styles.avatar}>
          <Text style={styles.avatarText}>{initial}</Text>
        </View>
        <Text style={styles.name}>{user?.name || 'Staff User'}</Text>
        <Text style={styles.role}>{userRole === 'staff' ? 'Staff / Cashier' : userRole || 'Staff'}</Text>
      </View>

      {/* Notification Access Setting */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Permissions</Text>

        <View style={styles.settingRow}>
          <View style={styles.settingInfo}>
            <View style={styles.settingTitleRow}>
              <Icon name="bell-ring-outline" size={20} color={COLORS.accentDark} />
              <Text style={styles.settingLabel}>Notification access</Text>
            </View>
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
                ? 'Notification access is active. E-wallet payments can be captured automatically.'
                : 'Notification access is off. Enable it to capture supported e-wallet payments.'}
            </Text>
          </View>
        )}
      </View>

      {/* Account Info */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Account</Text>
        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Role</Text>
          <Text style={styles.infoValue}>{userRole || 'staff'}</Text>
        </View>
        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Business</Text>
          <Text style={styles.infoValue}>{business?.name || 'Not available'}</Text>
        </View>
        <View style={styles.infoRow}>
          <Text style={styles.infoLabel}>Email</Text>
          <Text style={styles.infoValue} numberOfLines={1}>{user?.email || 'Not available'}</Text>
        </View>
      </View>

      <TouchableOpacity style={styles.logoutButton} onPress={handleLogout}>
        <Icon name="logout" size={20} color={COLORS.danger} />
        <Text style={styles.logoutText}>Sign out</Text>
      </TouchableOpacity>

    </ScrollView>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  content: {
    paddingBottom: 28,
  },
  profileCard: {
    backgroundColor: COLORS.textDark,
    alignItems: 'center',
    marginHorizontal: 20,
    marginTop: 20,
    paddingVertical: 28,
    borderRadius: 16,
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
    color: COLORS.textWhite,
  },
  role: {
    fontSize: 13,
    color: 'rgba(255,255,255,0.8)',
    marginTop: 4,
  },
  section: {
    backgroundColor: '#FFFFFF',
    marginTop: 16,
    marginHorizontal: 20,
    borderRadius: 16,
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
  settingTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
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
    maxWidth: '62%',
    textAlign: 'right',
    textTransform: 'capitalize',
  },
  logoutButton: {
    marginHorizontal: 20,
    marginTop: 16,
    minHeight: 52,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#F1C6C6',
    backgroundColor: '#FFF7F7',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  logoutText: {
    color: COLORS.danger,
    fontSize: 15,
    fontWeight: '700',
  },
});

export default ProfileScreen;
