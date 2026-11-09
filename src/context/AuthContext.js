import React, {createContext, useCallback, useEffect, useRef, useState} from 'react';
import {AppState} from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import api, {OFFLINE_SESSION_TOKEN, setApiAuthToken, setApiOfflineSession, setUnauthorizedHandler} from '../services/api';
import {clearAuthToken, getAuthSession, saveAuthToken, STAFF_SESSION_MS} from '../services/authTokenStorage';
import {getApiErrorMessage} from '../services/apiErrors';
import {getStaffPin, resetStaffPinAttempts} from '../services/staffPinStorage';
import {clearOfflineStaffAccount, getOfflineStaffAccount, saveOfflineStaffAccount} from '../services/offlineStaffAccount';

export const AuthContext = createContext();

const withSessionTimeout = async (operation, message) => {
  let timeoutId;
  try {
    return await Promise.race([
      operation,
      new Promise((_, reject) => {
        timeoutId = setTimeout(() => {
          const error = new Error(message);
          error.code = 'AUTH_STORAGE_UNAVAILABLE';
          reject(error);
        }, 6000);
      }),
    ]);
  } finally {
    clearTimeout(timeoutId);
  }
};

const restoreSavedSession = () => withSessionTimeout(
  getAuthSession(),
  'Saved sign-in could not be restored. Please sign in again.',
);

export const AuthProvider = ({ children }) => {
  const [isLoading, setIsLoading] = useState(true);
  const [userToken, setUserToken] = useState(null);
  const [userRole, setUserRole] = useState(null);
  const [user, setUser] = useState(null);
  const [business, setBusiness] = useState(null);
  const [startupError, setStartupError] = useState(null);
  const [sessionStorageWarning, setSessionStorageWarning] = useState(null);
  const [sessionExpiresAt, setSessionExpiresAt] = useState(null);
  const [freshPasswordSignIn, setFreshPasswordSignIn] = useState(false);
  const [offlineLoginAvailable, setOfflineLoginAvailable] = useState(false);
  const bootstrapStarted = useRef(false);

  const checkOfflineLogin = useCallback(async () => {
    const account = await getOfflineStaffAccount();
    const available = Boolean(account && await getStaffPin(account.user.id));
    setOfflineLoginAvailable(available);
    return available;
  }, []);

  const clearLocalSession = useCallback(async ({preserveOfflineAccount = false} = {}) => {
    setApiOfflineSession(false);
    setApiAuthToken(null);
    setUserToken(null);
    setUserRole(null);
    setUser(null);
    setBusiness(null);
    setStartupError(null);
    setSessionStorageWarning(null);
    setSessionExpiresAt(null);
    setFreshPasswordSignIn(false);
    if (!preserveOfflineAccount) setOfflineLoginAvailable(false);
    setIsLoading(false);
    await Promise.allSettled([
      clearAuthToken(),
      AsyncStorage.removeMany(['userToken', 'userRole', 'user', 'business']),
      ...(preserveOfflineAccount ? [] : [clearOfflineStaffAccount()]),
    ]);
  }, []);

  useEffect(() => setUnauthorizedHandler(() => {
    clearLocalSession().catch(() => {});
  }), [clearLocalSession]);

  useEffect(() => {
    setApiOfflineSession(userToken === OFFLINE_SESSION_TOKEN);
    setApiAuthToken(userToken === OFFLINE_SESSION_TOKEN ? null : userToken);
  }, [userToken]);

  const login = async (email, password) => {
    try {
      const response = await api.post('/login', { email, password });
      if (response.data.token) {
        const token = response.data.token;
        const role = response.data.user.role;

        const signedInAt = Date.now();

        await resetStaffPinAttempts(response.data.user.id);

        setApiAuthToken(token);
        setApiOfflineSession(false);
        setUserToken(token);
        setUserRole(role);
        setUser(response.data.user);
        setBusiness(response.data.business || null);
        setStartupError(null);
        setSessionStorageWarning(null);
        setSessionExpiresAt(signedInAt + STAFF_SESSION_MS);
        // The password check at the API is the required reauthentication for
        // PIN recovery and for clearing a previous PIN attempt lockout.
        setFreshPasswordSignIn(true);

        // A native secure-storage call can stall on some Android devices.
        // Server-confirmed login remains usable for this process; never store
        // the token in plaintext when secure persistence fails.
        const saveCredentials = saveAuthToken(token, signedInAt).then(() => role === 'staff'
          ? saveOfflineStaffAccount(response.data.user, response.data.business, signedInAt)
          : clearOfflineStaffAccount());
        withSessionTimeout(Promise.all([
          saveCredentials,
          AsyncStorage.setMany({
            userRole: role,
            user: JSON.stringify(response.data.user),
            business: JSON.stringify(response.data.business || null),
          }),
        ]), 'Secure sign-in could not be saved on this device.')
          .catch(() => setSessionStorageWarning(
            'Signed in for this session, but secure sign-in could not be saved. You may need to sign in again after restarting the app.',
          ));
        return { success: true };
      }
      return { success: false, message: 'Invalid response from server' };
    } catch (error) {
      return {success: false, message: getApiErrorMessage(error, 'Login failed. Please check your credentials.')};
    }
  };

  const loginOffline = async () => {
    try {
      const account = await getOfflineStaffAccount();
      if (!account || !await getStaffPin(account.user.id)) {
        setOfflineLoginAvailable(false);
        return {success: false};
      }
      setApiOfflineSession(true);
      setApiAuthToken(null);
      setUserToken(OFFLINE_SESSION_TOKEN);
      setUserRole('staff');
      setUser(account.user);
      setBusiness(account.business);
      setSessionExpiresAt(account.expiresAt);
      setStartupError(null);
      setSessionStorageWarning(null);
      setFreshPasswordSignIn(false);
      return {success: true};
    } catch {
      return {success: false};
    }
  };

  const logout = async () => {
    if (userToken !== OFFLINE_SESSION_TOKEN) {
      try {
        await api.post('/logout');
      } catch {
        // Local credentials are cleared below even when the server is unreachable.
      }
    }
    await clearLocalSession({preserveOfflineAccount: true});
    checkOfflineLogin().catch(() => setOfflineLoginAvailable(false));
  };

  const updateProfile = useCallback(async (profileData, hasPhoto = false) => {
    try {
      const response = await api.post('/user/profile', profileData, hasPhoto
        ? {headers: {'Content-Type': 'multipart/form-data'}, timeout: 60000}
        : undefined);
      const updatedUser = response.data?.user;
      if (!updatedUser) {
        return {success: false, message: 'The server did not return the updated profile.'};
      }

      setUser(updatedUser);
      AsyncStorage.setMany({user: JSON.stringify(updatedUser)}).catch(() => {});
      return {success: true, user: updatedUser};
    } catch (error) {
      const message = getApiErrorMessage(error, 'Could not update your personal information.');
      if (hasPhoto && error?.response?.status === 413) {
        return {success: false, error, message: 'The photo exceeds the server upload limit. Choose a smaller photo or ask the administrator to enable 5 MB photo uploads.'};
      }
      if (hasPhoto && error?.response?.status === 422
        && message === 'Some information is invalid. Review it and try again.') {
        return {success: false, error, message: 'The server rejected this photo. Choose a JPG, PNG, or WebP under 5 MB, then try again. If it still fails, check the server photo upload limit.'};
      }
      return {success: false, error, message};
    }
  }, []);

  const isLoggedIn = useCallback(async () => {
    try {
      setIsLoading(true);
      if (__DEV__) console.info('[GastoTrack Auth] Starting local session restore');
      const session = await restoreSavedSession();
      const token = session.token;
      if (__DEV__) console.info(`[GastoTrack Auth] Secure token lookup ${token ? 'completed' : 'found no saved token'}`);
      const role = await AsyncStorage.getItem('userRole');
      const storedUser = await AsyncStorage.getItem('user');
      const storedBusiness = await AsyncStorage.getItem('business');
      if (__DEV__) console.info('[GastoTrack Auth] Local session storage read complete');
      if (token) {
        setSessionExpiresAt(session.expiresAt);
        setFreshPasswordSignIn(false);
        setApiAuthToken(token);
        setUserToken(token);
        setUserRole(role);
        setUser(storedUser ? JSON.parse(storedUser) : null);
        setBusiness(storedBusiness ? JSON.parse(storedBusiness) : null);
        setStartupError(null);

        // Restore the local session immediately so startup does not wait for
        // Laravel to answer before rendering the app. Validate the token in
        // the background and clear the session if the server rejects it.
        setIsLoading(false);

        try {
          const response = await api.get('/user');
          setUser(response.data.user);
          setUserRole(response.data.user?.role || null);
          setBusiness(response.data.business || null);
          await AsyncStorage.setMany({
            userRole: response.data.user?.role || '',
            user: JSON.stringify(response.data.user),
            business: JSON.stringify(response.data.business || null),
          });
        } catch (error) {
          if (error.response?.status === 401) {
            // The API response interceptor already clears the rejected session.
            // Repeating the native Keychain reset can stall the next sign-in.
          } else if (!error.response) {
            // Keep the cached profile visible, but protected API requests still
            // require a reachable server; this is not offline synchronization.
          }
        }
      } else if (session.expired) {
        setStartupError('Your 30-day session expired. Sign in with your email and password. Your MPIN is unchanged.');
      }
    } catch (error) {
      if (__DEV__) console.warn('[GastoTrack Auth] Local session restore failed:', error?.code || 'UNKNOWN');
      setApiAuthToken(null);
      setUserToken(null);
      setUserRole(null);
      setUser(null);
      setBusiness(null);
      setSessionExpiresAt(null);
      setStartupError(getApiErrorMessage(error, 'Saved sign-in could not be restored. Please sign in again.'));
    } finally {
      setIsLoading(false);
      checkOfflineLogin().catch(() => setOfflineLoginAvailable(false));
    }
  }, [checkOfflineLogin]);

  useEffect(() => {
    if (bootstrapStarted.current) return;
    bootstrapStarted.current = true;
    isLoggedIn();
  }, [isLoggedIn]);

  useEffect(() => {
    if (!userToken || !sessionExpiresAt) return undefined;
    let expiring = false;
    const expireIfNeeded = () => {
      if (expiring || Date.now() < sessionExpiresAt) return;
      expiring = true;
      clearLocalSession().then(() => setStartupError(
        'Your 30-day session expired. Sign in with your email and password. Your MPIN is unchanged.',
      ));
    };
    const interval = setInterval(expireIfNeeded, 60 * 1000);
    const subscription = AppState.addEventListener('change', state => {
      if (state === 'active') expireIfNeeded();
    });
    expireIfNeeded();
    return () => {clearInterval(interval); subscription.remove();};
  }, [userToken, sessionExpiresAt, clearLocalSession]);

  return (
    <AuthContext.Provider value={{
      login,
      loginOffline,
      offlineLoginAvailable,
      logout,
      updateProfile,
      isLoading,
      userToken,
      offlineMode: userToken === OFFLINE_SESSION_TOKEN,
      userRole,
      user,
      business,
      startupError,
      sessionStorageWarning,
      freshPasswordSignIn,
      consumeFreshPasswordSignIn: () => setFreshPasswordSignIn(false),
    }}>
      {children}
    </AuthContext.Provider>
  );
};

// Export useAuth hook
export const useAuth = () => {
  const context = React.useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};
