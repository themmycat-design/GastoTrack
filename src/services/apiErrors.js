export const getApiErrorMessage = (error, fallback = 'The request could not be completed.') => {
  if (error?.code === 'API_URL_NOT_CONFIGURED') {
    return error.message;
  }
  if (error?.code === 'AUTH_STORAGE_UNAVAILABLE') {
    return error.message;
  }
  if (error?.code === 'ORDER_RETRY_STORAGE_UNAVAILABLE') {
    return error.message;
  }

  if (error?.code === 'ECONNABORTED' || error?.code === 'ETIMEDOUT') {
    return 'The server took too long to respond. Check your connection and try again.';
  }

  const status = error?.response?.status;
  const responseData = error?.response?.data;

  const isTransportError = error?.isAxiosError === true
    || ['ERR_NETWORK', 'ECONNREFUSED', 'ENOTFOUND', 'EAI_AGAIN', 'ERR_INTERNET_DISCONNECTED'].includes(error?.code);
  if (!error?.response && isTransportError) {
    return 'Cannot reach the GastoTrack server. Check that Laravel is running and that the device can reach the configured API URL.';
  }
  if (!error?.response) return fallback;
  if (status === 401) {
    return 'Your session has expired or is no longer valid. Sign in again to continue.';
  }
  if (status === 403) {
    return responseData?.message || 'You do not have permission to perform this action.';
  }
  if (status === 422) {
    const validationMessage = Object.values(responseData?.errors || {}).flat().filter(Boolean).join('\n');
    return validationMessage || responseData?.message || 'Some information is invalid. Review it and try again.';
  }
  if (status === 429) {
    const retryAfter = Number(error?.response?.headers?.['retry-after']);
    return Number.isFinite(retryAfter) && retryAfter > 0
      ? `Too many requests. Try again in ${Math.ceil(retryAfter)} seconds.`
      : 'Too many requests. Wait a moment, then try again.';
  }
  if (status >= 500) {
    return 'The GastoTrack server is temporarily unavailable. Please try again shortly.';
  }

  return responseData?.message || fallback;
};
