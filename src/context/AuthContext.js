import React, {createContext, useCallback, useEffect, useState} from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import api from '../services/api';

export const AuthContext = createContext();

export const AuthProvider = ({ children }) => {
  const [isLoading, setIsLoading] = useState(true);
  const [userToken, setUserToken] = useState(null);
  const [userRole, setUserRole] = useState(null);
  const [user, setUser] = useState(null);
  const [business, setBusiness] = useState(null);

  const clearLocalSession = useCallback(async () => {
    setUserToken(null);
    setUserRole(null);
    setUser(null);
    setBusiness(null);
    await AsyncStorage.removeMany(['userToken', 'userRole', 'user', 'business']);
  }, []);

  const login = async (email, password) => {
    try {
      console.log('[AuthContext] Attempting login with:', email);
      const response = await api.post('/login', { email, password });
      if (response.data.token) {
        const token = response.data.token;
        const role = response.data.user.role;

        setUserToken(token);
        setUserRole(role);
        setUser(response.data.user);
        setBusiness(response.data.business || null);
        
        await AsyncStorage.setItem('userToken', token);
        await AsyncStorage.setItem('userRole', role);
        await AsyncStorage.setItem('user', JSON.stringify(response.data.user));
        if (response.data.business) {
          await AsyncStorage.setItem('business', JSON.stringify(response.data.business));
        }
        
        console.log('[AuthContext] Login successful, token saved');
        return { success: true };
      }
      
      console.log('[AuthContext] No token in response');
      return { success: false, message: 'Invalid response from server' };
    } catch (error) {
      console.log('[AuthContext] Login error:', error.response?.data || error.message);
      const isNetworkError = !error.response;
      return { 
        success: false, 
        message: isNetworkError
          ? 'Cannot reach the GastoTrack server. Make sure Laravel is running and the mobile API URL is correct.'
          : error.response?.data?.message || 'Login failed. Please check your credentials.'
      };
    }
  };

  const logout = async () => {
    try {
      await api.post('/logout');
    } catch (e) {
      console.log('Error logging out API', e);
    }
    await clearLocalSession();
  };

  const isLoggedIn = useCallback(async () => {
    try {
      setIsLoading(true);
      const token = await AsyncStorage.getItem('userToken');
      const role = await AsyncStorage.getItem('userRole');
      const storedUser = await AsyncStorage.getItem('user');
      const storedBusiness = await AsyncStorage.getItem('business');
      if (token) {
        setUserToken(token);
        setUserRole(role);
        setUser(storedUser ? JSON.parse(storedUser) : null);
        setBusiness(storedBusiness ? JSON.parse(storedBusiness) : null);

        try {
          const response = await api.get('/user');
          setUser(response.data.user);
          setBusiness(response.data.business || null);
        } catch (error) {
          if (error.response?.status === 401) {
            console.log('[AuthContext] Saved session expired; returning to sign in');
            await clearLocalSession();
          } else if (!error.response) {
            console.log('[AuthContext] Using saved profile while offline');
          } else {
            console.log(
              '[AuthContext] Unable to validate saved session:',
              error.response?.data || error.message,
            );
          }
        }
      }
    } catch (e) {
      console.log(`isLoggedIn error ${e}`);
    } finally {
      setIsLoading(false);
    }
  }, [clearLocalSession]);

  useEffect(() => {
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
