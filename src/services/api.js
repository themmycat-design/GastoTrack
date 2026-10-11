import axios from 'axios';
import {resolveNativeApiBaseUrl, API_CONFIGURATION_MESSAGE} from '../config/apiConfig';

// react-native-config is native code. A Metro refresh against an older APK can
// temporarily have no native module registered; don't crash before the login
// screen can explain the rebuild requirement. Development then uses the
// documented localhost default, while an unconfigured release still refuses
// to send requests to an arbitrary server.
const getNativeConfig = () => {
  try {
    const configModule = require('react-native-config');
    return configModule?.default || configModule || {};
  } catch {
    return {};
  }
};

export const API_URL = resolveNativeApiBaseUrl(getNativeConfig(), __DEV__);

let unauthorizedHandler = null;
let sessionToken = null;

// AuthContext restores this once at startup and updates it on login/logout.
// Protected requests must not wait on a native Keychain call for every fetch.
export const setApiAuthToken = token => {
  sessionToken = token || null;
};

export const setUnauthorizedHandler = handler => {
  unauthorizedHandler = handler;
  return () => {
    if (unauthorizedHandler === handler) unauthorizedHandler = null;
  };
};

const api = axios.create({
  baseURL: API_URL || undefined,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
  timeout: 15000,
});

const describeRequest = config => {
  const method = String(config?.method || 'GET').toUpperCase();
  const path = String(config?.url || '').split('?')[0];
  return `${method} ${path}`;
};

// Awtomatikong isama ang token kung nakapag-login na
api.interceptors.request.use(config => {
  if (!API_URL) {
    const error = new Error(API_CONFIGURATION_MESSAGE);
    error.code = 'API_URL_NOT_CONFIGURED';
    return Promise.reject(error);
  }

  if (sessionToken) {
    config.headers.Authorization = `Bearer ${sessionToken}`;
  } else {
    delete config.headers.Authorization;
  }
  if (__DEV__) console.info(`[GastoTrack API] -> ${describeRequest(config)}`);
  return config;
});

api.interceptors.response.use(
  response => {
    if (__DEV__) {
      console.info(`[GastoTrack API] <- ${response.status} ${describeRequest(response.config)}`);
    }
    return response;
  },
  error => {
    const url = String(error?.config?.url || '');
    const isLoginOrRegistration = /(?:^|\/)(?:auth\/)?(?:login|register)(?:$|\?)/i.test(url);
    if (__DEV__) {
      const outcome = error?.response?.status || error?.code || 'NETWORK_ERROR';
      console.warn(`[GastoTrack API] <- ${outcome} ${describeRequest(error?.config)}`);
    }
    if (error?.response?.status === 401 && !isLoginOrRegistration) {
      unauthorizedHandler?.();
    }
    return Promise.reject(error);
  },
);

export default api;
