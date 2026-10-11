import AsyncStorage from '@react-native-async-storage/async-storage';
import * as Keychain from 'react-native-keychain';

const KEYCHAIN_SERVICE = 'com.gastotrack.auth-token';
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

const readAuthToken = async () => {
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
    return credentials.password;
  }

  // Migrate existing installs once. Never continue using plaintext storage if
  // writing to the platform credential store fails.
  let legacyToken;
  try {
    legacyToken = await AsyncStorage.getItem(LEGACY_TOKEN_KEY);
  } catch {
    throw storageError();
  }
  if (!legacyToken) return null;

  try {
    await Keychain.setGenericPassword('gastotrack', legacyToken, KEYCHAIN_OPTIONS);
  } catch {
    throw storageError();
  }
  try {
    await AsyncStorage.removeItem(LEGACY_TOKEN_KEY);
  } catch {
    throw storageError();
  }
  return legacyToken;
};

// The app starts several protected data fetches together after session
// restoration. Share one native Keychain read rather than issuing concurrent
// reads through the Android native module for every request.
export const getAuthToken = async () => {
  if (!authTokenReadInFlight) {
    authTokenReadInFlight = readAuthToken();
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

export const saveAuthToken = async token => {
  try {
    const saved = await Keychain.setGenericPassword('gastotrack', token, KEYCHAIN_OPTIONS);
    if (!saved) throw storageError();
  } catch {
    throw storageError();
  }
};

export const clearAuthToken = async () => {
  await Keychain.resetGenericPassword({service: KEYCHAIN_SERVICE});
  await AsyncStorage.removeItem(LEGACY_TOKEN_KEY);
};
