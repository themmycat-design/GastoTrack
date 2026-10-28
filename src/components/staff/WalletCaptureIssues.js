import React, {useCallback, useEffect, useState} from 'react';
import {StyleSheet, Text, TouchableOpacity, View} from 'react-native';
import {useIsFocused} from '@react-navigation/native';
import {getRejectedWalletCaptures, retryRejectedWalletCapture} from '../../modules/NotificationModule';
import {emitWalletCaptureSync, subscribeWalletCaptureSync} from '../../services/walletCaptureSyncEvents';
import {COLORS} from '../../theme';

const WalletCaptureIssues = () => {
  const focused = useIsFocused();
  const [captures, setCaptures] = useState([]);
  const [busyId, setBusyId] = useState(null);
  const [error, setError] = useState('');
  const refresh = useCallback(async () => {
    try {
      setCaptures(await getRejectedWalletCaptures());
      setError('');
    } catch {
      setError('Could not check wallet captures on this device.');
    }
  }, []);

  useEffect(() => {
    if (!focused) return undefined;
    refresh();
    return subscribeWalletCaptureSync(refresh);
  }, [focused, refresh]);

  const retry = async clientId => {
    setBusyId(clientId);
    try {
      await retryRejectedWalletCapture(clientId);
      await refresh();
      emitWalletCaptureSync();
    } catch {
      setError('Could not retry this wallet capture. Try again later.');
    } finally {
      setBusyId(null);
    }
  };

  if (!captures.length && !error) return null;
  return <View style={styles.container}>
    <Text style={styles.heading}>Wallet captures needing review</Text>
    {error ? <Text style={styles.error}>{error}</Text> : null}
    {captures.map(capture => <View key={capture.client_id}
      style={styles.row}>
      <View style={styles.details}>
        <Text style={styles.source}>{capture.source || 'Wallet'} alert</Text>
        <Text style={styles.reason}>{capture.reason || 'Upload was rejected.'}</Text>
      </View>
      <TouchableOpacity accessibilityRole="button" accessibilityLabel="Retry rejected wallet capture"
        disabled={busyId === capture.client_id} onPress={() => retry(capture.client_id)}
        style={styles.retry}>
        <Text style={styles.retryText}>Retry</Text>
      </TouchableOpacity>
    </View>)}
  </View>;
};

const styles = StyleSheet.create({
  container: {marginHorizontal: 16, marginTop: 8, padding: 12, borderRadius: 12, backgroundColor: '#FFF8E8', borderWidth: 1, borderColor: '#E8C984'},
  heading: {fontWeight: '700', color: COLORS.textDark},
  error: {marginTop: 5, color: COLORS.danger},
  row: {flexDirection: 'row', alignItems: 'center', marginTop: 8, gap: 8},
  details: {flex: 1},
  source: {fontSize: 13, color: COLORS.textDark},
  reason: {fontSize: 12, color: COLORS.textGray},
  retry: {minHeight: 44, paddingHorizontal: 12, justifyContent: 'center'},
  retryText: {fontWeight: '700', color: COLORS.accentDark},
});

export default WalletCaptureIssues;
