import React, {createContext, useCallback, useEffect, useRef, useState} from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import api, {setApiAuthToken, setUnauthorizedHandler} from '../services/api';
import {clearAuthToken, getAuthToken, saveAuthToken} from '../services/authTokenStorage';
import {getApiErrorMessage} from '../services/apiErrors';

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

const restoreSavedToken = () => withSessionTimeout(
  getAuthToken(),
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
  const bootstrapStarted = useRef(false);

  const clearLocalSession = useCallback(async () => {
    setApiAuthToken(null);
    setUserToken(null);
    setUserRole(null);
    setUser(null);
    setBusiness(null);
    setStartupError(null);
    setSessionStorageWarning(null);
    setIsLoading(false);
    await Promise.allSettled([
      clearAuthToken(),
      AsyncStorage.removeMany(['userToken', 'userRole', 'user', 'business']),
    ]);
  }, []);

  useEffect(() => setUnauthorizedHandler(() => {
    clearLocalSession().catch(() => {});
  }), [clearLocalSession]);

  useEffect(() => {
    setApiAuthToken(userToken);
  }, [userToken]);

  const login = async (email, password) => {
    try {
      const response = await api.post('/login', { email, password });
      if (response.data.token) {
        const token = response.data.token;
        const role = response.data.user.role;

        setApiAuthToken(token);
        setUserToken(token);
        setUserRole(role);
        setUser(response.data.user);
        setBusiness(response.data.business || null);
        setStartupError(null);
        setSessionStorageWarning(null);

        // A native secure-storage call can stall on some Android devices.
        // Server-confirmed login remains usable for this process; never store
        // the token in plaintext when secure persistence fails.
        withSessionTimeout(Promise.all([
          saveAuthToken(token),
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

  const logout = async () => {
    try {
      await api.post('/logout');
    } catch {
      // Local credentials are cleared below even when the server is unreachable.
    }
    await clearLocalSession();
  };

  const isLoggedIn = useCallback(async () => {
    try {
      setIsLoading(true);
      if (__DEV__) console.info('[GastoTrack Auth] Starting local session restore');
      const token = await restoreSavedToken();
      if (__DEV__) console.info(`[GastoTrack Auth] Secure token lookup ${token ? 'completed' : 'found no saved token'}`);
      const role = await AsyncStorage.getItem('userRole');
      const storedUser = await AsyncStorage.getItem('user');
      const storedBusiness = await AsyncStorage.getItem('business');
      if (__DEV__) console.info('[GastoTrack Auth] Local session storage read complete');
      if (token) {
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
      }
    } catch (error) {
      if (__DEV__) console.warn('[GastoTrack Auth] Local session restore failed:', error?.code || 'UNKNOWN');
      setApiAuthToken(null);
      setUserToken(null);
      setUserRole(null);
      setUser(null);
      setBusiness(null);
      setStartupError(getApiErrorMessage(error, 'Saved sign-in could not be restored. Please sign in again.'));
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    if (bootstrapStarted.current) return;
    bootstrapStarted.current = true;
    isLoggedIn();
  }, [isLoggedIn]);

  return (
    <AuthContext.Provider value={{
      login,
      logout,
      isLoading,
      userToken,
      userRole,
      user,
      business,
      startupError,
      sessionStorageWarning,
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
