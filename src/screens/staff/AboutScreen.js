import React from 'react';
import {StyleSheet, Text, View} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';

import StaffScreenHeader from '../../components/staff/StaffScreenHeader';
import {COLORS, SHADOWS} from '../../theme';

const AboutScreen = ({navigation}) => (
  <View style={styles.container}>
    <StaffScreenHeader
      title="About"
      leftIcon="arrow-left"
      leftLabel="Back to profile"
      onLeftPress={() => navigation.goBack()}
      centered
    />
    <View style={styles.content}>
      <View style={styles.card}>
        <View style={styles.logo}>
          <Icon name="wallet-outline" size={36} color={COLORS.textWhite} />
        </View>
        <Text style={styles.title}>GastoTrack</Text>
        <Text style={styles.description}>
          GastoTrack is a financial and operations management system designed for
          small food and beverage businesses, including coffee, milk tea, and bake
          shops.
        </Text>
        <Text style={styles.description}>
          It helps staff manage customer orders, record income and expenses, monitor
          live inventory, upload product photos, and capture supported e-wallet
          payments. Receipt scanning with OCR makes transaction recording faster and
          more accurate.
        </Text>
        <Text style={styles.description}>
          Business owners can monitor performance, transactions, inventory, staff,
          and financial goals from their dashboard. The built-in AI assistant also
          provides business insights and suggests alternative ingredients when stock
          is unavailable.
        </Text>
        <Text style={styles.version}>Version 0.0.1</Text>
      </View>
    </View>
  </View>
);

const styles = StyleSheet.create({
  container: {flex: 1, backgroundColor: COLORS.background},
  content: {padding: 20},
  card: {
    alignItems: 'center',
    backgroundColor: COLORS.surface,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: COLORS.border,
    padding: 28,
    ...SHADOWS.card,
  },
  logo: {
    width: 72,
    height: 72,
    borderRadius: 20,
    backgroundColor: COLORS.accent,
    alignItems: 'center',
    justifyContent: 'center',
  },
  title: {fontSize: 22, fontWeight: '800', color: COLORS.textDark, marginTop: 16},
  description: {
    alignSelf: 'stretch',
    fontSize: 14,
    lineHeight: 21,
    color: COLORS.textGray,
    textAlign: 'left',
    marginTop: 12,
  },
  version: {fontSize: 12, color: COLORS.textMuted, marginTop: 20},
});

export default AboutScreen;
