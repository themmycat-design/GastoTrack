import React, {useCallback, useEffect, useState} from 'react';
import {AppState, StyleSheet, Text, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';

import StaffScreenHeader from '../../components/staff/StaffScreenHeader';
import {
  isNotificationAccessGranted,
  openNotificationSettings,
} from '../../modules/NotificationModule';
import {COLORS, SHADOWS} from '../../theme';

const PermissionsScreen = ({navigation}) => {
  const [permissionGranted, setPermissionGranted] = useState(null);

  const checkPermission = useCallback(async () => {
    setPermissionGranted(await isNotificationAccessGranted());
  }, []);

  useEffect(() => {
    checkPermission();
    const subscription = AppState.addEventListener('change', state => {
      if (state === 'active') {
        checkPermission();
      }
    });
    return () => subscription.remove();
  }, [checkPermission]);

  return (
    <View style={styles.container}>
      <StaffScreenHeader
        title="Permissions"
        leftIcon="arrow-left"
        leftLabel="Back to profile"
        onLeftPress={() => navigation.goBack()}
        centered
      />
      <View style={styles.content}>
        <View style={styles.card}>
          <View style={styles.titleRow}>
            <View style={styles.iconBox}>
              <Icon name="bell-ring-outline" size={23} color={COLORS.accentDark} />
            </View>
            <View style={styles.copy}>
              <Text style={styles.title}>Notification access</Text>
              <Text style={styles.description}>
                Allows GastoTrack to capture supported e-wallet payments automatically.
              </Text>
            </View>
          </View>

          <View style={[styles.status, permissionGranted ? styles.activeStatus : styles.offStatus]}>
            <Text style={permissionGranted ? styles.activeText : styles.offText}>
              {permissionGranted === null
                ? 'Checking permission…'
                : permissionGranted
                  ? 'Permission is active'
                  : 'Permission is turned off'}
            </Text>
          </View>

          <TouchableOpacity style={styles.button} onPress={openNotificationSettings}>
            <Text style={styles.buttonText}>
              {permissionGranted ? 'Manage permission' : 'Grant permission'}
            </Text>
          </TouchableOpacity>
        </View>
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {flex: 1, backgroundColor: COLORS.background},
  content: {padding: 20},
  card: {
    backgroundColor: COLORS.surface,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: COLORS.border,
    padding: 18,
    ...SHADOWS.card,
  },
  titleRow: {flexDirection: 'row', alignItems: 'flex-start'},
  iconBox: {
    width: 44,
    height: 44,
    borderRadius: 12,
    backgroundColor: COLORS.surfaceMuted,
    alignItems: 'center',
    justifyContent: 'center',
  },
  copy: {flex: 1, marginLeft: 12},
  title: {fontSize: 16, fontWeight: '700', color: COLORS.textDark},
  description: {fontSize: 13, lineHeight: 19, color: COLORS.textGray, marginTop: 4},
  status: {borderRadius: 10, padding: 12, marginTop: 18},
  activeStatus: {backgroundColor: '#E8F5E9'},
  offStatus: {backgroundColor: '#FFF8E1'},
  activeText: {fontSize: 13, fontWeight: '600', color: '#2E7D32'},
  offText: {fontSize: 13, fontWeight: '600', color: '#A66500'},
  button: {
    minHeight: 48,
    borderRadius: 12,
    backgroundColor: COLORS.accent,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 14,
  },
  buttonText: {fontSize: 14, fontWeight: '700', color: COLORS.textWhite},
});

export default PermissionsScreen;
