export const DEVELOPMENT_API_URL = 'http://127.0.0.1:8000/api/v1';

export const API_CONFIGURATION_MESSAGE =
  'The API URL is not configured for this build. Set API_URL in the selected root .env file, then rebuild the app.';

export const resolveApiBaseUrl = (configuredUrl, isDevelopmentBuild) => {
  const value = String(configuredUrl || '').trim();
  if (!value) return isDevelopmentBuild ? DEVELOPMENT_API_URL : null;
  if (!/^https?:\/\/[^\s/]+(?:\/[^\s]*)?$/i.test(value)) return null;
  if (!isDevelopmentBuild && !value.toLowerCase().startsWith('https://')) return null;
  return value.replace(/\/+$/, '');
};

export const resolveNativeApiBaseUrl = (nativeConfig, isDevelopmentBuild) =>
  resolveApiBaseUrl(nativeConfig?.API_URL, isDevelopmentBuild);
