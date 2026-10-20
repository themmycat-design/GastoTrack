import React, {useCallback, useEffect, useRef, useState} from 'react';
import {ActivityIndicator, AppState, StyleSheet, Text, TouchableOpacity, View} from 'react-native';
import {getStaffPin, saveStaffPin} from '../services/staffPinStorage';
import StaffPinScreen from '../screens/auth/StaffPinScreen';
import {COLORS} from '../theme';

const MAX_ATTEMPTS = 5;

const StaffPinGate = ({userId, onSignOut, children}) => {
  const [mode, setMode] = useState('loading');
  const [pin, setPin] = useState('');
  const [firstPin, setFirstPin] = useState('');
  const [savedPin, setSavedPin] = useState(null);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [attempts, setAttempts] = useState(0);
  const appState = useRef(AppState.currentState);

  const loadPin = useCallback(async () => {
    setMode('loading');
    setError('');
    try {
      const stored = await getStaffPin(userId);
      setSavedPin(stored);
      setMode(stored ? 'unlock' : 'setup');
    } catch {
      setMode('storage-error');
    }
  }, [userId]);

  useEffect(() => {
    let active = true;
    getStaffPin(userId).then(stored => {
      if (!active) return;
      setSavedPin(stored);
      setMode(stored ? 'unlock' : 'setup');
    }).catch(() => { if (active) setMode('storage-error'); });
    return () => { active = false; };
  }, [userId]);

  useEffect(() => {
    const subscription = AppState.addEventListener('change', next => {
      if (appState.current === 'active' && next !== 'active') {
        setMode(current => current === 'open' ? 'unlock' : current);
        setPin('');
        setError('');
      }
      appState.current = next;
    });
    return () => subscription.remove();
  }, []);

  const completePin = async value => {
    setBusy(true);
    setPin('');
    setError('');
    try {
      if (mode === 'setup') {
        setFirstPin(value);
        setMode('confirm');
      } else if (mode === 'confirm') {
        if (value !== firstPin) {
          setFirstPin('');
          setMode('setup');
          setError('PINs did not match. Create your MPIN again.');
        } else {
          await saveStaffPin(userId, value);
          setSavedPin(value);
          setMode('open');
        }
      } else if (mode === 'unlock') {
        if (value === savedPin) {
          setAttempts(0);
          setMode('open');
        } else {
          const nextAttempts = attempts + 1;
          setAttempts(nextAttempts);
          if (nextAttempts >= MAX_ATTEMPTS) {
            setError('Too many incorrect PIN attempts. Sign in with your password again.');
            await onSignOut();
          } else {
            setError(`Incorrect MPIN. ${MAX_ATTEMPTS - nextAttempts} tries remaining.`);
          }
        }
      }
    } catch {
      setError('Could not save your MPIN securely. Please try again.');
    } finally {
      setBusy(false);
    }
  };

  const onDigit = digit => {
    if (busy || pin.length >= 4) return;
    const next = pin + digit;
    setPin(next);
    if (next.length === 4) completePin(next);
  };

  if (mode === 'open') return children;
  if (mode === 'loading') return <View style={styles.center}><ActivityIndicator color={COLORS.accentDark} /></View>;
  if (mode === 'storage-error') return (
    <View style={styles.center}>
      <Text style={styles.message}>Secure PIN storage is unavailable. Restart the app or try again.</Text>
      <TouchableOpacity onPress={loadPin} style={styles.retry}><Text style={styles.retryText}>Retry</Text></TouchableOpacity>
      <TouchableOpacity onPress={onSignOut} style={styles.retry}><Text style={styles.retryText}>Sign out</Text></TouchableOpacity>
    </View>
  );
  return <StaffPinScreen mode={mode} pin={pin} error={error} busy={busy} onDigit={onDigit}
    onDelete={() => {setPin(current => current.slice(0, -1)); setError('');}} onSignOut={onSignOut} />;
};

const styles = StyleSheet.create({
  center: {flex: 1, alignItems: 'center', justifyContent: 'center', padding: 24, backgroundColor: '#F1FFF5'},
  message: {fontSize: 15, lineHeight: 22, color: COLORS.textDark, textAlign: 'center'},
  retry: {marginTop: 18, padding: 12},
  retryText: {color: COLORS.accentDark, fontWeight: '700'},
});

export default StaffPinGate;
