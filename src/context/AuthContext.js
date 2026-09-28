import React, { createContext, useState, useEffect } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import api from '../services/api';

export const AuthContext = createContext();

export const AuthProvider = ({ children }) => {
  const [isLoading, setIsLoading] = useState(true);
  const [userToken, setUserToken] = useState(null);
  const [userRole, setUserRole] = useState(null);

  const login = async (email, password) => {
    try {
      console.log('[AuthContext] Attempting login with:', email);
      const response = await api.post('/login', { email, password });
      console.log('[AuthContext] Login response:', JSON.stringify(response.data));
      
      if (response.data.token) {
        const token = response.data.token;
        const role = response.data.user.role;
        
        console.log('[AuthContext] Setting token:', token.substring(0, 20) + '...');
        console.log('[AuthContext] Setting role:', role);
        
        setUserToken(token);
        setUserRole(role);
        
        await AsyncStorage.setItem('userToken', token);
        await AsyncStorage.setItem('userRole', role);
        
        console.log('[AuthContext] Login successful, token saved');
        return { success: true };
      }
      
      console.log('[AuthContext] No token in response');
      return { success: false, message: 'Invalid response from server' };
    } catch (error) {
      console.log('[AuthContext] Login error:', error.response?.data || error.message);
      return { 
        success: false, 
        message: error.response?.data?.message || error.message || 'Connection error'
      };
    }
  };

  const logout = async () => {
    try {
      await api.post('/logout');
    } catch (e) {
      console.log('Error logging out API', e);
    }
    setUserToken(null);
    setUserRole(null);
    await AsyncStorage.removeItem('userToken');
    await AsyncStorage.removeItem('userRole');
  };

  const isLoggedIn = async () => {
    try {
      setIsLoading(true);
      const token = await AsyncStorage.getItem('userToken');
      const role = await AsyncStorage.getItem('userRole');
      if (token) {
        setUserToken(token);
        setUserRole(role);
      }
    } catch (e) {
      console.log(`isLoggedIn error ${e}`);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    isLoggedIn();
  }, []);

  return (
    <AuthContext.Provider value={{ login, logout, isLoading, userToken, userRole }}>
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