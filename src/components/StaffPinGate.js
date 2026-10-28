import React, {useCallback, useEffect, useRef, useState} from 'react';
import {ActivityIndicator, AppState, StyleSheet, Text, TouchableOpacity, View} from 'react-native';
import {
  clearStaffPin, getStaffPin, getStaffPinAttempts, recordStaffPinFailure,
  resetStaffPinAttempts, saveStaffPin,
} from '../services/staffPinStorage';
import StaffPinScreen from '../screens/auth/StaffPinScreen';
import {COLORS} from '../theme';

const MAX_ATTEMPTS = 5;
const BACKGROUND_LOCK_MS = 15 * 60 * 1000;

const StaffPinGate = ({userId, onSignOut, passwordAuthenticated = false, onPinUnlocked, children}) => {
  const [mode, setMode] = useState('loading');
  const [hasOpened, setHasOpened] = useState(false);
  const [pin, setPin] = useState('');
  const [firstPin, setFirstPin] = useState('');
  const [savedPin, setSavedPin] = useState(null);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const processing = useRef(false);
  const backgroundedAt = useRef(null);

  const loadPin = useCallback(async () => {
    setMode('loading');
    setError('');
    try {
      const [stored, failedAttempts] = await Promise.all([getStaffPin(userId), getStaffPinAttempts(userId)]);
      setSavedPin(stored);
      setMode(failedAttempts >= MAX_ATTEMPTS ? 'locked' : stored ? 'unlock' : 'setup');
    } catch {
      setMode('storage-error');
    }
  }, [userId]);

  useEffect(() => {
    let active = true;
    Promise.all([getStaffPin(userId), getStaffPinAttempts(userId)]).then(([stored, failedAttempts]) => {
      if (!active) return;
      setSavedPin(stored);
      setMode(failedAttempts >= MAX_ATTEMPTS ? 'locked' : stored ? 'unlock' : 'setup');
    }).catch(() => { if (active) setMode('storage-error'); });
    return () => { active = false; };
  }, [userId]);

  useEffect(() => {
    const subscription = AppState.addEventListener('change', next => {
      if (next === 'background') {
        if (backgroundedAt.current === null) backgroundedAt.current = Date.now();
      } else if (next === 'active' && backgroundedAt.current !== null) {
        const awayFor = Date.now() - backgroundedAt.current;
        backgroundedAt.current = null;
        if (awayFor < BACKGROUND_LOCK_MS) return;

        setMode(current => current === 'open' ? 'unlock' : current);
        setPin('');
        setError('');
      }
    });
    return () => subscription.remove();
  }, []);

  const completePin = async value => {
    if (processing.current) return;
    processing.current = true;
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
          await resetStaffPinAttempts(userId);
          setSavedPin(value);
          setHasOpened(true);
          setMode('open');
          onPinUnlocked?.();
        }
      } else if (mode === 'unlock') {
        if (value === savedPin) {
          await resetStaffPinAttempts(userId);
          setHasOpened(true);
          setMode('open');
          onPinUnlocked?.();
        } else {
          const nextAttempts = await recordStaffPinFailure(userId);
          if (nextAttempts >= MAX_ATTEMPTS) {
            setMode('locked');
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
      processing.current = false;
    }
  };

  const onDigit = digit => {
    if (busy || processing.current || mode === 'locked' || pin.length >= 4) return;
    const next = pin + digit;
    setPin(next);
    if (next.length === 4) completePin(next);
  };

  const recoverPin = async () => {
    if (!passwordAuthenticated || busy || processing.current) return;
    processing.current = true;
    setBusy(true);
    try {
      await clearStaffPin(userId);
      setSavedPin(null);
      setPin('');
      setFirstPin('');
      setError('Create a new MPIN after your password sign-in.');
      setMode('setup');
    } catch {
      setError('Secure PIN storage is unavailable. Try again.');
    } finally {
      setBusy(false);
      processing.current = false;
    }
  };

  if (mode === 'loading') return <View style={styles.center}><ActivityIndicator color={COLORS.accentDark} /></View>;
  if (mode === 'storage-error') return (
    <View style={styles.center}>
      <Text style={styles.message}>Secure PIN storage is unavailable. Restart the app or try again.</Text>
      <TouchableOpacity onPress={loadPin} style={styles.retry}><Text style={styles.retryText}>Retry</Text></TouchableOpacity>
      <TouchableOpacity onPress={onSignOut} style={styles.retry}><Text style={styles.retryText}>Sign out</Text></TouchableOpacity>
    </View>
  );
  const pinScreen = <StaffPinScreen mode={mode} pin={pin} error={error} busy={busy} onDigit={onDigit}
    onDelete={() => {setPin(current => current.slice(0, -1)); setError('');}}
    onRecover={passwordAuthenticated && mode === 'unlock' ? recoverPin : null} onSignOut={onSignOut} />;
  if (!hasOpened) return pinScreen;

  return <View style={styles.gate}>
    <View style={[styles.workspace, mode !== 'open' && styles.workspaceHidden]}
      pointerEvents={mode === 'open' ? 'auto' : 'none'}
      accessibilityElementsHidden={mode !== 'open'}
      importantForAccessibility={mode === 'open' ? 'auto' : 'no-hide-descendants'}>
      {children}
    </View>
    {mode !== 'open' ? pinScreen : null}
  </View>;
};

const styles = StyleSheet.create({
  gate: {flex: 1},
  workspace: {flex: 1},
  workspaceHidden: {display: 'none'},
  center: {flex: 1, alignItems: 'center', justifyContent: 'center', padding: 24, backgroundColor: '#F1FFF5'},
  message: {fontSize: 15, lineHeight: 22, color: COLORS.textDark, textAlign: 'center'},
  retry: {marginTop: 18, padding: 12},
  retryText: {color: COLORS.accentDark, fontWeight: '700'},
});

export default StaffPinGate;
