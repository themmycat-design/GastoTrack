import React from 'react';
import {StyleSheet, Text, TouchableOpacity, View} from 'react-native';
import {COLORS} from '../theme';

const ApiErrorBanner = ({message, onRetry}) => {
  if (!message) return null;
  return (
    <View style={styles.container} accessibilityRole="alert" accessibilityLiveRegion="polite">
      <Text style={styles.message}>{message}</Text>
      {onRetry ? (
        <TouchableOpacity onPress={onRetry} accessibilityRole="button" style={styles.button}>
          <Text style={styles.buttonText}>Retry</Text>
        </TouchableOpacity>
      ) : null}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {marginHorizontal: 16, marginVertical: 8, padding: 12, borderRadius: 11, borderWidth: 1, borderColor: '#EBCB9A', backgroundColor: '#FFF8E9'},
  message: {fontSize: 14, lineHeight: 20, color: COLORS.textDark},
  button: {alignSelf: 'flex-start', minHeight: 48, justifyContent: 'center', paddingHorizontal: 12, marginTop: 4, borderRadius: 10, backgroundColor: '#FFFFFF'},
  buttonText: {fontSize: 14, fontWeight: '700', color: COLORS.accentDark},
});

export default ApiErrorBanner;
