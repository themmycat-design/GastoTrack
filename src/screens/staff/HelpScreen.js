import React, {useState} from 'react';
import {Image, ScrollView, StyleSheet, Text, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialCommunityIcons';

import StaffScreenHeader from '../../components/staff/StaffScreenHeader';
import {COLORS, RADIUS, SHADOWS, SPACING} from '../../theme';

const questions = [
  {
    icon: 'cart-outline',
    question: 'How do I place and complete an order?',
    answer: 'Open Orders, tap a product, add it to the cart, then tap View your cart. Choose Cash for an immediate online sale, or Cashless and a wallet to wait for a matching GCash or Maya notification. An offline cash order waits to sync before it becomes a completed sale.',
  },
  {
    icon: 'food-outline',
    question: 'Can I change the ingredients in an order?',
    answer: 'Yes. Open a product in Orders and choose regular, half, none, or a smaller amount for its ingredients before adding it to the cart. The changes apply to that cart line.',
  },
  {
    icon: 'package-variant-closed',
    question: 'Why can I not place an order when stock is low?',
    answer: 'The recipe may need more of an ingredient than the shop has available. Check the product ingredients and Inventory, then adjust stock if the physical count has changed.',
  },
  {
    icon: 'plus-minus',
    question: 'How do I add or deduct ingredient stock?',
    answer: 'Open Inventory, select a stock item, choose Add or Deduct, enter the quantity and reason, then save. An offline adjustment waits for the server to accept it before the displayed stock is final.',
  },
  {
    icon: 'wallet-outline',
    question: 'How do I know a cashless order was saved?',
    answer: 'After choosing GCash or Maya, wait for a matching received-payment notification. The app checks the wallet and exact amount, then saves the order through the server. A notification match is not verification from the wallet provider; check the official wallet account if payment is unclear.',
  },
  {
    icon: 'wifi-off',
    question: 'What can I record without internet?',
    answer: 'Manual transactions, cash orders, and stock adjustments can be saved on this device. The app retries syncing while it is open when the connection returns. They become server records only after sync succeeds. Cashless payments and AI need an internet connection.',
  },
  {
    icon: 'line-scan',
    question: 'How do I scan a receipt?',
    answer: 'Tap Scan receipt on Home, then take or choose a clear photo. The app reads the receipt and Gasto AI assigns a category before saving automatically. If it cannot read or categorize the receipt, retry or edit the details manually.',
  },
  {
    icon: 'robot-outline',
    question: 'Why is Gasto AI unavailable?',
    answer: 'AI features need internet access and a reachable GastoTrack server. Check your connection and try again. If receipt categorization fails, you can edit the receipt details and choose a category manually.',
  },
  {
    icon: 'receipt-text-outline',
    question: 'What is the difference between "Raw" and "Daily" transactions?',
    answer: 'Raw shows captured wallet notification logs for review in the owner web portal; staff do not have a Raw view. Raw captures alone do not count in totals. Daily shows recorded transactions, including completed order income and entered income or expenses.',
  },
  {
    icon: 'bell-outline',
    question: 'Why is my e-wallet notification not being captured?',
    answer: 'Open Profile > Permissions and check that Android notification access is enabled. Cashless orders wait for a supported GCash or Maya received-payment notification with the matching wallet, amount, and readable reference.',
  },
  {
    icon: 'lock-reset',
    question: 'What if I forget my MPIN?',
    answer: 'Open Profile > Forgot MPIN, then choose New MPIN and enter four digits twice. After the success message, unlock the app with your new MPIN. If you cannot unlock with the old MPIN, sign out and sign in with your email and password first, then open Profile.',
  },
  {
    icon: 'account-edit-outline',
    question: 'How do I update my name or profile photo?',
    answer: 'Open Profile > Personal information, tap Edit, change your name or choose a photo, then tap Save changes. A server connection is needed to save the update.',
  },
  {
    icon: 'bug-outline',
    question: 'How do I report a problem?',
    answer: 'Open Profile > Your reports > Report a problem, describe what happened, and submit the report. A server connection is needed to send it.',
  },
];

const HelpScreen = ({navigation}) => {
  const [expandedIndex, setExpandedIndex] = useState(null);

  return (
    <View style={styles.container}>
      <StaffScreenHeader
        title="Help"
        leftIcon="arrow-left"
        leftLabel="Back to profile"
        onLeftPress={() => navigation.goBack()}
        centered
      />
      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.introCard}>
          <View style={styles.introCopy}>
            <Text style={styles.introTitle}>How can we help?</Text>
            <Text style={styles.introText}>Find quick answers to everyday tasks in GastoTrack.</Text>
          </View>
          <Image source={require('../../../assets/mascot/gasto-chat-welcome.png')} style={styles.mascot} resizeMode="contain" accessibilityLabel="Gasto waving hello" />
        </View>
        <Text style={styles.sectionLabel}>COMMON QUESTIONS</Text>
        {questions.map((item, index) => {
          const expanded = expandedIndex === index;
          return (
            <View key={item.question} style={[styles.card, expanded && styles.cardExpanded]}>
              <TouchableOpacity
                style={styles.questionRow}
                onPress={() => setExpandedIndex(expanded ? null : index)}
                accessibilityRole="button"
                accessibilityLabel={item.question}
                accessibilityState={{expanded}}
                accessibilityHint={expanded ? 'Collapse the answer' : 'Show the answer'}
                activeOpacity={0.8}>
                <View style={[styles.iconFrame, expanded && styles.iconFrameExpanded]}>
                  <Icon name={item.icon} size={21} color={COLORS.accentDark} />
                </View>
                <Text style={styles.question}>{item.question}</Text>
                <Icon name={expanded ? 'chevron-up' : 'chevron-down'} size={22} color={expanded ? COLORS.accentDark : COLORS.textMuted} />
              </TouchableOpacity>
              {expanded ? <View style={styles.answerWrap}><Text style={styles.answer}>{item.answer}</Text></View> : null}
            </View>
          );
        })}
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {flex: 1, backgroundColor: COLORS.background},
  content: {padding: SPACING.lg, paddingBottom: SPACING.xxxl},
  introCard: {flexDirection: 'row', alignItems: 'center', minHeight: 112, backgroundColor: COLORS.surfaceMuted, borderRadius: RADIUS.lg, borderWidth: 1, borderColor: COLORS.border, paddingLeft: SPACING.lg, paddingRight: SPACING.sm, marginBottom: SPACING.xl},
  introCopy: {flex: 1, paddingVertical: SPACING.lg},
  introTitle: {fontSize: 18, fontWeight: '800', color: COLORS.textDark},
  introText: {fontSize: 13, lineHeight: 19, color: COLORS.textGray, marginTop: SPACING.xs},
  mascot: {width: 88, height: 88, marginLeft: SPACING.sm},
  sectionLabel: {fontSize: 11, fontWeight: '800', letterSpacing: 1, color: COLORS.accentDark, marginBottom: SPACING.md, marginLeft: SPACING.xs},
  card: {backgroundColor: COLORS.surface, borderRadius: RADIUS.lg, borderWidth: 1, borderColor: COLORS.border, paddingHorizontal: SPACING.md, marginBottom: SPACING.sm, ...SHADOWS.card},
  cardExpanded: {borderColor: COLORS.borderStrong},
  questionRow: {minHeight: 64, flexDirection: 'row', alignItems: 'center'},
  iconFrame: {width: 36, height: 36, borderRadius: RADIUS.md, backgroundColor: COLORS.surfaceMuted, alignItems: 'center', justifyContent: 'center', marginRight: SPACING.md},
  iconFrameExpanded: {backgroundColor: COLORS.tagIncBg},
  question: {flex: 1, fontSize: 14, lineHeight: 20, fontWeight: '700', color: COLORS.textDark, paddingVertical: SPACING.md, paddingRight: SPACING.sm},
  answerWrap: {borderTopWidth: 1, borderTopColor: COLORS.border, paddingVertical: SPACING.md, paddingHorizontal: SPACING.xs},
  answer: {fontSize: 13, lineHeight: 20, color: COLORS.textGray},
});

export default HelpScreen;
