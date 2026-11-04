import React from 'react';
import {ScrollView, StyleSheet, Text, View} from 'react-native';

import StaffScreenHeader from '../../components/staff/StaffScreenHeader';
import {COLORS, RADIUS, SHADOWS, SPACING} from '../../theme';

const sections = [
  {
    heading: 'Who can use GastoTrack',
    body: 'GastoTrack is intended for participating food and beverage businesses and their authorized staff. Use the account assigned to you for your business. Do not use another person’s account or enter information for a business you are not authorized to manage.',
  },
  {
    heading: 'Accounts and roles',
    body: 'Staff and cashiers use the Android app to record orders, income, expenses, and stock changes. Business owners use the web portal to manage their business, staff, products, reports, and settings. Super Administrators manage platform access and business registrations. Each role has different access to business information.',
  },
  {
    heading: 'What the app records',
    body: 'The app records actions you submit, including orders, payment methods, transactions, inventory changes, and bug reports. Some staff actions can be saved on the device while offline and sent to the server when the connection returns. A queued action is not a completed server record until it syncs successfully.',
  },
  {
    heading: 'E-wallet payment signals',
    body: 'With Android notification access enabled, GastoTrack can capture supported GCash and Maya transaction notifications and match a received-payment signal to an order. A match is based on notification details; it is not confirmation from the wallet provider. Check the official wallet account when a payment is disputed or unclear.',
  },
  {
    heading: 'AI and receipt scanning',
    body: 'Receipt scanning reads text from a selected image and may suggest a transaction category. The AI assistant can provide answers and ingredient suggestions based on available business data. These outputs can be incomplete or wrong. Review important financial, stock, and payment decisions against the original records.',
  },
  {
    heading: 'Changes to these terms',
    body: 'The terms may need to change as GastoTrack is updated. The final version should identify who operates the service, how changes will be announced, and when revised terms take effect before the app is released.',
  },
];

const TermsScreen = ({navigation}) => (
  <View style={styles.container}>
    <StaffScreenHeader title="Terms and Conditions" leftIcon="arrow-left" leftLabel="Back to About" onLeftPress={() => navigation.goBack()} centered />
    <ScrollView contentContainerStyle={styles.content}>
      <View style={styles.card}>
        {sections.map((section, index) => (
          <View key={section.heading} style={[styles.section, index > 0 && styles.sectionDivider]}>
            <Text style={styles.heading}>{index + 1}. {section.heading}</Text>
            <Text style={styles.body}>{section.body}</Text>
          </View>
        ))}
      </View>
    </ScrollView>
  </View>
);

const styles = StyleSheet.create({
  container: {flex: 1, backgroundColor: COLORS.background},
  content: {padding: SPACING.lg, paddingBottom: SPACING.xxxl},
  card: {backgroundColor: COLORS.surface, borderRadius: RADIUS.lg, borderWidth: 1, borderColor: COLORS.border, padding: SPACING.lg, ...SHADOWS.card},
  section: {paddingBottom: SPACING.lg},
  sectionDivider: {borderTopWidth: 1, borderTopColor: COLORS.border, paddingTop: SPACING.lg},
  heading: {fontSize: 15, fontWeight: '700', color: COLORS.textDark},
  body: {fontSize: 13, lineHeight: 20, color: COLORS.textGray, marginTop: SPACING.sm},
});

export default TermsScreen;
