import React from 'react';
import {ScrollView, StyleSheet, Text, View} from 'react-native';

import StaffScreenHeader from '../../components/staff/StaffScreenHeader';
import {useAuth} from '../../context/AuthContext';
import {COLORS, SHADOWS} from '../../theme';

const PersonalInformationScreen = ({navigation}) => {
  const {user, userRole, business} = useAuth();
  const fields = [
    ['Name', user?.name || 'Not available'],
    ['Email', user?.email || 'Not available'],
    ['Role', userRole || 'staff'],
    ['Business', business?.name || 'Not available'],
  ];

  return (
    <View style={styles.container}>
      <StaffScreenHeader
        title="Personal information"
        leftIcon="arrow-left"
        leftLabel="Back to profile"
        onLeftPress={() => navigation.goBack()}
        centered
      />
      <ScrollView contentContainerStyle={styles.content}>
        <View style={styles.card}>
          {fields.map(([label, value], index) => (
            <View key={label} style={[styles.row, index === fields.length - 1 && styles.lastRow]}>
              <Text style={styles.label}>{label}</Text>
              <Text style={styles.value}>{value}</Text>
            </View>
          ))}
        </View>
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {flex: 1, backgroundColor: COLORS.background},
  content: {padding: 20, paddingBottom: 36},
  card: {
    backgroundColor: COLORS.surface,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: COLORS.border,
    paddingHorizontal: 18,
    ...SHADOWS.card,
  },
  row: {paddingVertical: 17, borderBottomWidth: 1, borderBottomColor: COLORS.border},
  lastRow: {borderBottomWidth: 0},
  label: {fontSize: 12, color: COLORS.textGray, marginBottom: 5},
  value: {fontSize: 15, fontWeight: '600', color: COLORS.textDark},
});

export default PersonalInformationScreen;
