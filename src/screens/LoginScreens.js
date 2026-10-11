import React, {useContext, useEffect, useRef, useState} from 'react';
import {
  AccessibilityInfo,
  ActivityIndicator,
  Alert,
  Animated,
  Image,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  useWindowDimensions,
} from 'react-native';
import {useSafeAreaInsets} from 'react-native-safe-area-context';
import {AuthContext} from '../context/AuthContext';

const WELCOME_HOLD_MS = 2000;
const BRAND_MOVE_MS = 750;

const LoginScreen = () => {
  const {height} = useWindowDimensions();
  const insets = useSafeAreaInsets();
  const {login, startupError} = useContext(AuthContext);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [introComplete, setIntroComplete] = useState(false);
  const [showLoginForm, setShowLoginForm] = useState(false);
  const movement = useRef(new Animated.Value(0)).current;
  const reveal = useRef(new Animated.Value(0)).current;
  const formReveal = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    let mounted = true;
    let holdTimer;

    AccessibilityInfo.isReduceMotionEnabled()
      .catch(() => false)
      .then(reduceMotion => {
        if (!mounted) return;
        holdTimer = setTimeout(() => {
          Animated.timing(movement, {
            toValue: 1,
            duration: reduceMotion ? 0 : BRAND_MOVE_MS,
            useNativeDriver: false,
          }).start(({finished}) => {
            if (!mounted || !finished) return;
            setIntroComplete(true);
            Animated.timing(reveal, {
              toValue: 1,
              duration: reduceMotion ? 0 : 450,
              useNativeDriver: true,
            }).start();
          });
        }, reduceMotion ? 0 : WELCOME_HOLD_MS);
      });

    return () => {
      mounted = false;
      clearTimeout(holdTimer);
      movement.stopAnimation();
      reveal.stopAnimation();
      formReveal.stopAnimation();
    };
  }, [movement, reveal, formReveal]);

  const openLogin = () => {
    setShowLoginForm(true);
    formReveal.setValue(0);
    Animated.timing(formReveal, {toValue: 1, duration: 300, useNativeDriver: true}).start();
  };

  const handleLogin = async () => {
    if (loading) return;
    if (!email.trim() || !password) {
      Alert.alert('Missing details', 'Enter your email and password.');
      return;
    }

    setLoading(true);
    const result = await login(email.trim().toLowerCase(), password);
    setLoading(false);
    if (!result.success) Alert.alert('Login failed', result.message);
  };

  const initialBrandTop = Math.max(0, height / 2 - 90);
  const finalBrandTop = Math.max(insets.top + 28, height * 0.12);
  const brandMove = movement.interpolate({
    inputRange: [0, 1],
    outputRange: [0, finalBrandTop - initialBrandTop],
  });
  const backgroundColor = movement.interpolate({
    inputRange: [0, 1],
    outputRange: ['#00C897', '#F1FFF5'],
  });
  const titleColor = movement.interpolate({
    inputRange: [0, 1],
    outputRange: ['#FFFFFF', '#00AE84'],
  });

  return (
    <Animated.View style={[styles.container, {backgroundColor}]}>
      <Animated.View style={[styles.brand, {top: initialBrandTop, transform: [{translateY: brandMove}]}]}>
        <Image
          source={require('../../assets/brand/gastotrack-logo.png')}
          style={styles.logo}
          resizeMode="contain"
          accessibilityLabel="GastoTrack logo"
        />
        <Animated.Text style={[styles.title, {color: titleColor}]}>GastoTrack</Animated.Text>
        <Animated.Text style={[styles.tagline, {opacity: reveal}]}>
          Keep your shop's daily work in one place.
        </Animated.Text>
      </Animated.View>

      <KeyboardAvoidingView
        style={styles.content}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        {!showLoginForm ? (
          <Animated.View
            pointerEvents={introComplete ? 'auto' : 'none'}
            style={[styles.actions, {paddingBottom: Math.max(insets.bottom + 22, 28), opacity: reveal,
              transform: [{translateY: reveal.interpolate({inputRange: [0, 1], outputRange: [18, 0]})}]}]}>
            {startupError ? <Text style={styles.startupError} accessibilityRole="alert">{startupError}</Text> : null}
            <TouchableOpacity accessibilityRole="button" style={styles.primaryButton} onPress={openLogin}>
              <Text style={styles.primaryButtonText}>Log in</Text>
            </TouchableOpacity>
          </Animated.View>
        ) : (
          <ScrollView
            keyboardShouldPersistTaps="handled"
            contentContainerStyle={[styles.formScroll, {
              paddingTop: Math.max(finalBrandTop + 215, 285),
              paddingBottom: Math.max(insets.bottom + 20, 24),
            }]}>
            <Animated.View style={[styles.formCard, {opacity: formReveal}]}>
              {startupError ? <Text style={styles.startupError} accessibilityRole="alert">{startupError}</Text> : null}

              <Text style={styles.label}>Email</Text>
              <TextInput
                style={styles.input}
                value={email}
                onChangeText={setEmail}
                placeholder="Enter your email"
                placeholderTextColor="#869D96"
                autoCapitalize="none"
                autoCorrect={false}
                keyboardType="email-address"
                editable={!loading}
              />
              <Text style={styles.label}>Password</Text>
              <TextInput
                style={styles.input}
                value={password}
                onChangeText={setPassword}
                secureTextEntry
                returnKeyType="done"
                onSubmitEditing={handleLogin}
                editable={!loading}
                placeholder="Enter your password"
                placeholderTextColor="#869D96"
              />
              <Text style={styles.helpText}>Need a password reset? Contact your business owner.</Text>
              <TouchableOpacity
                accessibilityRole="button"
                style={[styles.primaryButton, loading && styles.buttonDisabled]}
                onPress={handleLogin}
                disabled={loading}>
                {loading ? <ActivityIndicator color="#FFFFFF" /> : <Text style={styles.primaryButtonText}>Log in</Text>}
              </TouchableOpacity>
            </Animated.View>
          </ScrollView>
        )}
      </KeyboardAvoidingView>
    </Animated.View>
  );
};

const styles = StyleSheet.create({
  container: {flex: 1},
  brand: {position: 'absolute', left: 24, right: 24, zIndex: 1, alignItems: 'center'},
  logo: {width: 112, height: 112},
  title: {marginTop: 6, fontSize: 34, fontWeight: '800', textAlign: 'center', letterSpacing: -1},
  tagline: {maxWidth: 255, marginTop: 10, color: '#577A70', fontSize: 13, lineHeight: 19, textAlign: 'center'},
  content: {flex: 1},
  actions: {flex: 1, justifyContent: 'flex-end', paddingHorizontal: 28},
  primaryButton: {minHeight: 52, borderRadius: 16, backgroundColor: '#00C897', alignItems: 'center', justifyContent: 'center'},
  primaryButtonText: {color: '#053B31', fontSize: 16, fontWeight: '800'},
  formScroll: {flexGrow: 1, justifyContent: 'flex-end', paddingHorizontal: 20},
  formCard: {padding: 22, borderWidth: 1, borderColor: '#D7ECE0', borderRadius: 24, backgroundColor: '#FFFFFF'},
  label: {marginBottom: 7, color: '#365B51', fontSize: 13, fontWeight: '700'},
  input: {minHeight: 50, marginBottom: 17, paddingHorizontal: 14, borderWidth: 1, borderColor: '#D7ECE0', borderRadius: 13, backgroundColor: '#F7FBF8', color: '#0A2E2A', fontSize: 15},
  helpText: {marginBottom: 18, color: '#647A73', fontSize: 12, lineHeight: 18},
  buttonDisabled: {opacity: 0.6},
  startupError: {marginBottom: 16, padding: 11, borderRadius: 10, backgroundColor: '#FFF1EE', color: '#9C2F24', fontSize: 13, lineHeight: 19},
});

export default LoginScreen;
