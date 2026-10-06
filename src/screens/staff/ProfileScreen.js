import React from 'react';
import {Alert, ScrollView, StyleSheet, Text, TouchableOpacity, View} from 'react-native';
import {SafeAreaView} from 'react-native-safe-area-context';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';

import StaffScreenHeader from '../../components/staff/StaffScreenHeader';
import {useAuth} from '../../context/AuthContext';
import {COLORS} from '../../theme';

const ProfileScreen = ({navigation}) => {
  const {user, userRole, logout} = useAuth();

  const handleLogout = () => {
    Alert.alert('Sign out?', 'You will need to sign in again to continue.', [
      {text: 'Cancel', style: 'cancel'},
      {text: 'Sign out', style: 'destructive', onPress: logout},
    ]);
  };

  const initial = user?.name?.trim()?.charAt(0)?.toUpperCase() || 'S';
  const profileOptions = [
    {title: 'Personal information', icon: 'account-outline', route: 'PersonalInformation'},
    {title: 'Permissions', icon: 'shield-check-outline', route: 'Permissions'},
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
            <Text style={styles.avatarText}>{initial}</Text>
          </View>
          <Text style={styles.name}>{user?.name || 'Staff User'}</Text>
          <Text style={styles.role}>
            {userRole === 'staff' ? 'Staff / Cashier' : userRole || 'Staff'}
          </Text>
        </View>

        <View style={styles.profileOptions}>
          {profileOptions.map(option => (
            <TouchableOpacity
              key={option.route}
              style={styles.optionCard}
              onPress={() => navigation.navigate(option.route)}
              activeOpacity={0.75}>
              <View style={styles.optionIcon}>
                <Icon name={option.icon} size={22} color={COLORS.accentDark} />
              </View>
              <Text style={styles.optionTitle}>{option.title}</Text>
              <Icon name="chevron-right" size={24} color={COLORS.textMuted} />
            </TouchableOpacity>
          ))}
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
  container: {flex: 1, backgroundColor: COLORS.background},
  content: {paddingBottom: 28},
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
    backgroundColor: COLORS.surface,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 12,
  },
  avatarText: {fontSize: 28, fontWeight: 'bold', color: COLORS.accent},
  name: {fontSize: 20, fontWeight: 'bold', color: COLORS.textWhite},
  role: {fontSize: 13, color: 'rgba(255,255,255,0.8)', marginTop: 4},
  profileOptions: {marginHorizontal: 20, marginTop: 7},
  optionCard: {
    minHeight: 68,
    borderRadius: 14,
    borderWidth: 1,
    borderColor: COLORS.border,
    backgroundColor: COLORS.surface,
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 13,
    marginTop: 9,
    elevation: 1,
  },
  optionIcon: {
    width: 42,
    height: 42,
    borderRadius: 12,
    backgroundColor: COLORS.surfaceMuted,
    alignItems: 'center',
    justifyContent: 'center',
  },
  optionTitle: {
    flex: 1,
    marginLeft: 11,
    fontSize: 14,
    fontWeight: '700',
    color: COLORS.textDark,
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
  logoutText: {color: COLORS.danger, fontSize: 15, fontWeight: '700'},
});

export default ProfileScreen;
