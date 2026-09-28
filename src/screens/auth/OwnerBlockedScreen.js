import React from 'react';
import { Linking, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import Icon from 'react-native-vector-icons/MaterialIcons';

const WEB_DASHBOARD_URL = 'https://gastotrack.com';

/** Display this from the auth navigator when an owner signs in. */
const OwnerBlockedScreen = ({ onLogout }) => {
  const openWebDashboard = async () => {
    if (await Linking.canOpenURL(WEB_DASHBOARD_URL)) {
      await Linking.openURL(WEB_DASHBOARD_URL);
    }
  };

  return (
    <View style={styles.container}>
      <View style={styles.iconWrap}>
        <Icon name="laptop-mac" size={56} color="#00C897" />
      </View>
      <Text style={styles.title}>Use the web dashboard</Text>
      <Text style={styles.message}>
        The GastoTrack mobile app is for staff. Business owners can manage their business from the web dashboard.
      </Text>
      <TouchableOpacity style={styles.primaryButton} onPress={openWebDashboard}>
        <Icon name="open-in-new" size={20} color="#FFFFFF" />
        <Text style={styles.primaryButtonText}>Open Web Dashboard</Text>
      </TouchableOpacity>
      {onLogout ? (
        <TouchableOpacity style={styles.logoutButton} onPress={onLogout}>
          <Text style={styles.logoutText}>Log out</Text>
        </TouchableOpacity>
      ) : null}
    </View>
  );
};

const styles = StyleSheet.create({
  container: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 28, backgroundColor: '#FFFFFF' },
  iconWrap: { width: 104, height: 104, borderRadius: 52, alignItems: 'center', justifyContent: 'center', backgroundColor: '#E8FBF5', marginBottom: 28 },
  title: { fontSize: 26, fontWeight: '700', color: '#1D1D1D', textAlign: 'center', marginBottom: 12 },
  message: { fontSize: 16, lineHeight: 24, color: '#666666', textAlign: 'center', marginBottom: 32 },
  primaryButton: { minHeight: 52, alignSelf: 'stretch', flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, borderRadius: 12, backgroundColor: '#00C897' },
  primaryButtonText: { fontSize: 16, fontWeight: '700', color: '#FFFFFF' },
  logoutButton: { marginTop: 20, padding: 12 },
  logoutText: { fontSize: 16, fontWeight: '600', color: '#00A77D' },
});

export default OwnerBlockedScreen;
