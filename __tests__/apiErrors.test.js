import {getApiErrorMessage} from '../src/services/apiErrors';

describe('API error messages', () => {
  it('distinguishes an Axios transport failure from a local JavaScript error', () => {
    const networkError = Object.assign(new Error('Network Error'), {
      isAxiosError: true,
      code: 'ERR_NETWORK',
    });

    expect(getApiErrorMessage(networkError)).toBe('No internet connection.');
    expect(getApiErrorMessage(new TypeError('AsyncStorage method is missing'), 'Sign-in failed.'))
      .toBe('Sign-in failed.');
  });

  it('reports a server failure only when the server returned an error status', () => {
    expect(getApiErrorMessage({response: {status: 503}}))
      .toBe('The GastoTrack server is temporarily unavailable. Please try again shortly.');
  });

  it('shows a rejected offline action reason without exposing unrelated JavaScript errors', () => {
    expect(getApiErrorMessage({code: 'STAFF_OFFLINE_ACTION_REJECTED', message: 'Insufficient stock for Milk.'}))
      .toBe('Insufficient stock for Milk.');
    expect(getApiErrorMessage(new Error('Internal detail'), 'Order not submitted.'))
      .toBe('Order not submitted.');
  });

  it('shows the server guidance for an unavailable AI provider', () => {
    expect(getApiErrorMessage({response: {
      status: 503,
      data: {message: 'AI category suggestions are temporarily unavailable. Choose a category manually.'},
    }})).toBe('AI category suggestions are temporarily unavailable. Choose a category manually.');
  });

  it('uses the rate-limit retry time when the API provides one', () => {
    expect(getApiErrorMessage({response: {status: 429, headers: {'retry-after': '42'}}}))
      .toBe('Too many requests. Try again in 42 seconds.');
  });
});
