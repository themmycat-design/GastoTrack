import React from 'react';
import {Image, ScrollView, StyleSheet, Text, TouchableOpacity, View} from 'react-native';
import {SafeAreaView} from 'react-native-safe-area-context';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';

import {AppAlert as Alert} from '../../services/AppDialogService';
import StaffScreenHeader from '../../components/staff/StaffScreenHeader';
import {useAuth} from '../../context/AuthContext';
import {useStaffPin} from '../../context/StaffPinContext';
import {COLORS} from '../../theme';
import {getProfilePhotoSource} from '../../utils/profilePhoto';

const ProfileScreen = ({navigation}) => {
  const {user, userRole, business, logout, userToken} = useAuth();
  const staffPin = useStaffPin();

  const handleResetPin = () => {
    Alert.alert('Create a new MPIN?', 'Your new MPIN will replace the current one.', [
      {text: 'Back', style: 'cancel'},
      {text: 'New MPIN', onPress: () => staffPin?.startPinReset()},
    ]);
  };

  const handleLogout = () => {
    Alert.alert('Sign out?', 'You will need to sign in again to continue.', [
      {text: 'Cancel', style: 'cancel'},
      {text: 'Sign out', style: 'destructive', onPress: logout},
    ]);
  };

  const initial = user?.name?.trim()?.charAt(0)?.toUpperCase() || 'S';
  const photoSource = getProfilePhotoSource(user, userToken);
  const profileOptions = [
    {title: 'Personal information', icon: 'account-outline', route: 'PersonalInformation'},
    {title: 'Forgot MPIN', icon: 'lock-reset', onPress: handleResetPin},
    {title: 'Permissions', icon: 'shield-check-outline', route: 'Permissions'},
    {title: 'Your reports', icon: 'bug-outline', route: 'YourReports'},
    {title: 'Help', icon: 'help-circle-outline', route: 'Help'},
    {title: 'About', icon: 'information-outline', route: 'About'},
  ];

  return (
    <SafeAreaView style={styles.container} edges={[]}>
      <StaffScreenHeader
        title="Profile"
        subtitle="Account and app settings"
        icon="account-outline"
        actionIcon="cog-outline"
        actionLabel="Open settings"
        onActionPress={() => navigation.navigate('ProfileSettings')}
      />
      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.profileCard}>
          <View style={styles.avatar}>
            {photoSource
              ? <Image source={photoSource} style={styles.avatarImage} />
              : <Text style={styles.avatarText}>{initial}</Text>}
          </View>
          <Text style={styles.name}>{user?.name || 'Staff User'}</Text>
          <Text style={styles.role}>
            {userRole === 'staff' ? 'Staff / Cashier' : userRole || 'Staff'}
          </Text>
          {!!business?.name && <Text style={styles.businessName}>{business.name}</Text>}
        </View>

        <View style={styles.profileOptions}>
          {profileOptions.map(option => (
            <TouchableOpacity
              key={option.route || option.title}
              style={styles.optionCard}
              onPress={() => option.onPress ? option.onPress() : navigation.navigate(option.route)}
              accessibilityRole="button"
              accessibilityLabel={option.title}
              activeOpacity={0.75}>
              <View style={styles.optionIcon}>
                <Icon name={option.icon} size={22} color={COLORS.accentDark} />
              </View>
              <Text style={styles.optionTitle}>{option.title}</Text>
              <Icon name="chevron-right" size={24} color={COLORS.textMuted} />
            </TouchableOpacity>
          ))}
        </View>

        <TouchableOpacity style={styles.logoutButton} onPress={handleLogout} accessibilityRole="button">
          <Icon name="logout" size={20} color={COLORS.danger} />
          <Text style={styles.logoutText}>Sign out</Text>
        </TouchableOpacity>
      </ScrollView>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: {flex: 1, backgroundColor: COLORS.background},
  content: {paddingBottom: 28},
  profileCard: {
    backgroundColor: COLORS.textDark,
    alignItems: 'center',
    marginHorizontal: 16,
    marginTop: 12,
    paddingVertical: 20,
    paddingHorizontal: 16,
    borderRadius: 16,
  },
  avatar: {
    width: 72,
    height: 72,
    borderRadius: 36,
    backgroundColor: COLORS.surface,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 8,
  },
  avatarImage: {width: 72, height: 72, borderRadius: 36},
  avatarText: {fontSize: 28, fontWeight: 'bold', color: COLORS.accent},
  name: {fontSize: 20, fontWeight: 'bold', color: COLORS.textWhite, textAlign: 'center'},
  role: {fontSize: 13, color: 'rgba(255,255,255,0.8)', marginTop: 4},
  businessName: {fontSize: 13, fontWeight: '600', color: 'rgba(255,255,255,0.92)', marginTop: 7, textAlign: 'center'},
  profileOptions: {marginHorizontal: 16, marginTop: 5},
  optionCard: {
    minHeight: 60,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: COLORS.border,
    backgroundColor: COLORS.surface,
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 12,
    marginTop: 7,
    elevation: 1,
  },
  optionIcon: {
    width: 38,
    height: 38,
    borderRadius: 12,
    backgroundColor: COLORS.surfaceMuted,
    alignItems: 'center',
    justifyContent: 'center',
  },
  optionTitle: {
    flex: 1,
    marginLeft: 9,
    fontSize: 14,
    fontWeight: '700',
    color: COLORS.textDark,
  },
  logoutButton: {
    marginHorizontal: 16,
    marginTop: 12,
    minHeight: 48,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: '#F1C6C6',
    backgroundColor: '#FFF7F7',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  logoutText: {color: COLORS.danger, fontSize: 15, fontWeight: '700'},
});

export default ProfileScreen;
