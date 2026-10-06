import axios from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';
import {Platform} from 'react-native';

// Development devices reach the host services through ADB reverse forwarding.
// Run: adb reverse tcp:8000 tcp:8000
const DEVELOPMENT_API_URL = Platform.select({
  android: 'http://127.0.0.1:8000/api/v1',
  ios: 'http://127.0.0.1:8000/api/v1',
  default: 'http://127.0.0.1:8000/api/v1',
});

export const API_URL = process.env.EXPO_PUBLIC_API_URL || DEVELOPMENT_API_URL;

const api = axios.create({
  baseURL: API_URL,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
  timeout: 15000,
});

// Awtomatikong isama ang token kung nakapag-login na
api.interceptors.request.use(async (config) => {
  const token = await AsyncStorage.getItem('userToken');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

export default api;
