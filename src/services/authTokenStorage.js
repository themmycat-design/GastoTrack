import AsyncStorage from '@react-native-async-storage/async-storage';
import * as Keychain from 'react-native-keychain';

const KEYCHAIN_SERVICE = 'com.gastotrack.auth-token';
export const STAFF_SESSION_MS = 30 * 24 * 60 * 60 * 1000;
const USERNAME_PREFIX = 'gastotrack:';
let authTokenReadInFlight = null;
const KEYCHAIN_OPTIONS = {
  service: KEYCHAIN_SERVICE,
  // react-native-keychain's Android module builds PromptInfo even when the
  // selected storage does not require biometrics, so provide a valid title.
  authenticationPrompt: {
    title: 'GastoTrack secure sign-in',
    cancel: 'Cancel',
  },
};
const LEGACY_TOKEN_KEY = 'userToken';

const storageError = () => {
  const error = new Error('Secure sign-in storage is unavailable on this device. Restart the app or reinstall its secure-storage dependencies, then sign in again.');
  error.code = 'AUTH_STORAGE_UNAVAILABLE';
  return error;
};

const readAuthSession = async () => {
  let credentials;
  try {
    credentials = await Keychain.getGenericPassword(KEYCHAIN_OPTIONS);
  } catch {
    throw storageError();
  }
  if (credentials) {
    try {
      await AsyncStorage.removeItem(LEGACY_TOKEN_KEY);
    } catch {
      throw storageError();
    }
    const signedInAt = Number(String(credentials.username || '').replace(USERNAME_PREFIX, ''));
    const validTimestamp = String(credentials.username || '').startsWith(USERNAME_PREFIX)
      && Number.isSafeInteger(signedInAt) && signedInAt > 0 && signedInAt <= Date.now();
    if (!validTimestamp || Date.now() - signedInAt >= STAFF_SESSION_MS) {
      await Keychain.resetGenericPassword({service: KEYCHAIN_SERVICE});
      return {token: null, expiresAt: null, expired: true};
    }
    return {token: credentials.password, expiresAt: signedInAt + STAFF_SESSION_MS, expired: false};
  }

  // Migrate existing installs once. Never continue using plaintext storage if
  // writing to the platform credential store fails.
  let legacyToken;
  try {
    legacyToken = await AsyncStorage.getItem(LEGACY_TOKEN_KEY);
  } catch {
    throw storageError();
  }
  if (!legacyToken) return {token: null, expiresAt: null, expired: false};

  // Older plaintext sessions have no reliable sign-in date. Require password
  // login instead of silently granting them a fresh 30-day window.
  try {
    await AsyncStorage.removeItem(LEGACY_TOKEN_KEY);
  } catch {
    throw storageError();
  }
  return {token: null, expiresAt: null, expired: true};
};

// The app starts several protected data fetches together after session
// restoration. Share one native Keychain read rather than issuing concurrent
// reads through the Android native module for every request.
export const getAuthSession = async () => {
  if (!authTokenReadInFlight) {
    authTokenReadInFlight = readAuthSession();
  }

  const read = authTokenReadInFlight;
  try {
    return await read;
  } finally {
    if (authTokenReadInFlight === read) {
      authTokenReadInFlight = null;
    }
  }
};

export const getAuthToken = async () => (await getAuthSession()).token;

export const saveAuthToken = async (token, signedInAt = Date.now()) => {
  try {
    const saved = await Keychain.setGenericPassword(`${USERNAME_PREFIX}${signedInAt}`, token, KEYCHAIN_OPTIONS);
    if (!saved) throw storageError();
  } catch {
    throw storageError();
  }
};

export const clearAuthToken = async () => {
  await Keychain.resetGenericPassword({service: KEYCHAIN_SERVICE});
  await AsyncStorage.removeItem(LEGACY_TOKEN_KEY);
};
