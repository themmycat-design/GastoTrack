import axios from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';

// PALITAN ITO NG IP ADDRESS NG COMPUTER MO
// Halimbawa: http://192.168.1.15:8000/api
const API_URL = 'http://192.168.0.11:8000/api';

const api = axios.create({
  baseURL: API_URL,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
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