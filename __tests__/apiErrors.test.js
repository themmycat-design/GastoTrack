import {getApiErrorMessage} from '../src/services/apiErrors';

describe('API error messages', () => {
  it('distinguishes an Axios transport failure from a local JavaScript error', () => {
    const networkError = Object.assign(new Error('Network Error'), {
      isAxiosError: true,
      code: 'ERR_NETWORK',
    });

    expect(getApiErrorMessage(networkError)).toMatch(/Cannot reach the GastoTrack server/);
    expect(getApiErrorMessage(new TypeError('AsyncStorage method is missing'), 'Sign-in failed.'))
      .toBe('Sign-in failed.');
  });

  it('reports a server failure only when the server returned an error status', () => {
    expect(getApiErrorMessage({response: {status: 503}}))
      .toBe('The GastoTrack server is temporarily unavailable. Please try again shortly.');
  });

  it('uses the rate-limit retry time when the API provides one', () => {
    expect(getApiErrorMessage({response: {status: 429, headers: {'retry-after': '42'}}}))
      .toBe('Too many requests. Try again in 42 seconds.');
  });
});
