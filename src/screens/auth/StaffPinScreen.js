import React from 'react';
import {ActivityIndicator, Image, StyleSheet, Text, TouchableOpacity, View} from 'react-native';
import {useSafeAreaInsets} from 'react-native-safe-area-context';
import {COLORS} from '../../theme';

const keys = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '', '0', 'delete'];

const StaffPinScreen = ({mode, pin, error, busy, onDigit, onDelete, onCancel, onSignOut}) => {
  const insets = useSafeAreaInsets();
  const title = mode === 'setup' ? 'Create your MPIN' : mode === 'confirm' ? 'Confirm your MPIN'
    : mode === 'locked' ? 'MPIN locked' : 'Enter your MPIN';
  const hint = mode === 'setup'
    ? 'Choose four numbers to protect your staff workspace.'
    : mode === 'confirm' ? 'Enter the same four numbers again.'
      : mode === 'locked' ? 'Sign in with your email and password to reset the attempt limit.'
        : 'Unlock your staff workspace. If you forgot your MPIN, sign out and sign in with your password, then open Profile.';

  return (
    <View style={[styles.page, {paddingTop: Math.max(insets.top, 20) + 28, paddingBottom: Math.max(insets.bottom, 16)}]}>
      <View style={styles.header}>
        <Image source={require('../../../assets/mascot/gasto-login.png')}
          style={styles.mascot} resizeMode="contain" accessibilityLabel="Gasto the cat" />
        <Text style={styles.title}>{title}</Text>
        <Text style={styles.hint}>{hint}</Text>
        <View style={styles.dots} accessibilityLabel={`${pin.length} of 4 digits entered`}>
          {[0, 1, 2, 3].map(index => <View key={index} style={[styles.dot, pin.length > index && styles.dotFilled]} />)}
        </View>
        {error ? <Text style={styles.error} accessibilityRole="alert">{error}</Text> : null}
        {busy ? <ActivityIndicator color={COLORS.accentDark} /> : null}
      </View>
      {mode !== 'locked' ? <View style={styles.keypad}>
        {keys.map((key, index) => key ? (
          <TouchableOpacity
            key={key}
            style={styles.key}
            accessibilityRole="button"
            accessibilityLabel={key === 'delete' ? 'Delete digit' : key}
            disabled={busy}
            onPress={() => key === 'delete' ? onDelete() : onDigit(key)}>
            <Text style={[styles.keyText, key === 'delete' && styles.deleteText]}>{key === 'delete' ? '⌫' : key}</Text>
          </TouchableOpacity>
        ) : <View key={`empty-${index}`} style={styles.key} />)}
      </View> : <View />}
      <View style={styles.footer}>
        {onCancel ? <TouchableOpacity accessibilityRole="button" accessibilityLabel="Back to profile"
          onPress={onCancel} disabled={busy} style={styles.signOut}>
          <Text style={styles.signOutText}>Back to profile</Text>
        </TouchableOpacity> : null}
        <TouchableOpacity accessibilityRole="button" onPress={onSignOut} disabled={busy} style={styles.signOut}>
          <Text style={styles.signOutText}>Sign out</Text>
        </TouchableOpacity>
      </View>
    </View>
  );
};

const styles = StyleSheet.create({
  page: {flex: 1, backgroundColor: '#F1FFF5', paddingHorizontal: 24, justifyContent: 'space-between', alignItems: 'center'},
  header: {alignItems: 'center', width: '100%'},
  mascot: {width: 112, height: 112},
  title: {marginTop: 14, fontSize: 28, fontWeight: '800', color: COLORS.textDark, textAlign: 'center'},
  hint: {marginTop: 10, fontSize: 14, lineHeight: 21, color: COLORS.textGray, textAlign: 'center'},
  dots: {flexDirection: 'row', gap: 18, marginTop: 32, marginBottom: 14},
  dot: {width: 15, height: 15, borderRadius: 8, borderWidth: 2, borderColor: COLORS.accentDark},
  dotFilled: {backgroundColor: COLORS.accentDark},
  error: {marginTop: 6, color: COLORS.danger, textAlign: 'center', fontSize: 13},
  keypad: {width: '100%', maxWidth: 310, flexDirection: 'row', flexWrap: 'wrap', alignSelf: 'center'},
  key: {width: '33.333%', height: 68, alignItems: 'center', justifyContent: 'center'},
  keyText: {fontSize: 28, fontWeight: '600', color: COLORS.textDark},
  deleteText: {fontSize: 27, color: COLORS.accentDark},
  footer: {width: '100%', alignItems: 'center'},
  signOut: {minHeight: 48, padding: 12, alignItems: 'center', justifyContent: 'center'},
  signOutText: {color: COLORS.accentDark, fontSize: 13, fontWeight: '700', textAlign: 'center'},
});

export default StaffPinScreen;
