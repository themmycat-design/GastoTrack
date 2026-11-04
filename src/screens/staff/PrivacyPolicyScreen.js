import React from 'react';
import {ScrollView, StyleSheet, Text, View} from 'react-native';

import StaffScreenHeader from '../../components/staff/StaffScreenHeader';
import {COLORS, RADIUS, SHADOWS, SPACING} from '../../theme';

const sections = [
  {
    heading: 'What data the app processes',
    body: 'GastoTrack processes account details, business and staff information, orders, transactions, stock changes, and bug reports with optional screenshots. With Android notification access enabled, it captures supported GCash and Maya transaction signals, including available amount, date, reference, sender or merchant, and notification message. Receipt scanning reads a photo selected or captured on the device; the resulting transaction details can be saved to the business account. The current scanner does not upload the full receipt photo as part of a saved transaction.',
  },
  {
    heading: 'Why it is processed',
    body: 'This information supports sign-in, daily sales and expense recording, payment review, stock tracking, owner reports, and bug investigation. Some staff actions and snapshots are stored on the device for offline work and sync when connectivity returns. Receipt category requests send extracted receipt details through the GastoTrack server to the configured AI provider. AI chat and ingredient suggestions also send the relevant question and business context to that provider.',
  },
  {
    heading: 'Who can see it',
    body: 'Authorized staff use their business’s mobile records; business owners use the web portal to review their own business records and staff activity. Super Administrators manage accounts, subscriptions, and bug reports. The platform console is designed to show platform-level information rather than private tenant financial records. The configured AI provider receives only the data sent for an AI request; its own handling of that data must be reviewed before release.',
  },
  {
    heading: 'Storage and retention',
    body: 'The server stores business records and submitted bug reports. The Android app also keeps some account information, offline records, and wallet captures on the device. For this capstone demonstration, the proposed schedule is to remove demo accounts and business records within 90 days after final evaluation, and remove raw wallet captures and bug screenshots within 30 days after they are no longer needed for payment or report review. Device queues and cached records should be cleared after successful sync or account closure. These are proposed targets, not automatic deletion rules in the current app. Before release, the team must implement and verify deletion for server data, device data, and backups, and review any legal requirement to retain real business records longer.',
  },
  {
    heading: 'Your rights under RA 10173',
    body: 'The Philippine Data Privacy Act of 2012 recognizes rights that include being informed, access, objection, correction, erasure or blocking in applicable cases, data portability, and filing a complaint. These rights have legal conditions and limits. The final policy must explain how to send a request and how the responsible organization will handle it.',
  },
  {
    heading: 'Contact for privacy requests',
    body: 'For privacy or data requests, email paghari-oncc.694.stud@cdd.edu.ph. Staff can also raise an account concern with their business owner. The responsible organization and request-handling process must be confirmed before release.',
  },
];

const PrivacyPolicyScreen = ({navigation}) => (
  <View style={styles.container}>
    <StaffScreenHeader title="Privacy Policy" leftIcon="arrow-left" leftLabel="Back to About" onLeftPress={() => navigation.goBack()} centered />
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

export default PrivacyPolicyScreen;
