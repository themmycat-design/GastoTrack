import {DEVELOPMENT_API_URL, resolveApiBaseUrl, resolveNativeApiBaseUrl} from '../src/config/apiConfig';
import {createOrderIdempotencyKey, orderRequestSignature} from '../src/config/orderIdempotency';
import {getApiErrorMessage} from '../src/services/apiErrors';

describe('native API configuration', () => {
  it('keeps localhost as the development default and refuses an unconfigured release', () => {
    expect(resolveApiBaseUrl('', true)).toBe(DEVELOPMENT_API_URL);
    expect(resolveApiBaseUrl('', false)).toBeNull();
  });

  it('accepts device and HTTPS deployment URLs while normalizing trailing slashes', () => {
    expect(resolveApiBaseUrl('http://192.168.1.20:8000/api/v1/', true)).toBe('http://192.168.1.20:8000/api/v1');
    expect(resolveApiBaseUrl('https://api.example.com/v1', false)).toBe('https://api.example.com/v1');
  });

  it('rejects malformed URLs instead of silently switching servers', () => {
    expect(resolveApiBaseUrl('api.example.com', false)).toBeNull();
    expect(resolveApiBaseUrl('file:///tmp/api', true)).toBeNull();
    expect(resolveApiBaseUrl('http://192.168.1.20:8000/api/v1', false)).toBeNull();
    expect(resolveApiBaseUrl('http://192.168.1.20:8000/api/v1', true)).toMatch(/^http:/);
  });

  it('handles an older native binary without the config module safely', () => {
    expect(resolveNativeApiBaseUrl(null, true)).toBe(DEVELOPMENT_API_URL);
    expect(resolveNativeApiBaseUrl(null, false)).toBeNull();
  });
});

describe('order retry identity', () => {
  it('creates UUID-shaped identifiers that differ between new attempts', () => {
    const first = createOrderIdempotencyKey();
    const second = createOrderIdempotencyKey();
    expect(first).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i);
    expect(second).not.toBe(first);
  });

  it('canonicalizes item order so retries match the original request', () => {
    const first = {items: [{product_id: 2, quantity: 1}, {product_id: 1, quantity: 2}], payment_method: 'GCASH'};
    const retry = {items: [{product_id: 1, quantity: 2}, {product_id: 2, quantity: 1}], payment_method: 'gcash'};
    expect(orderRequestSignature(first)).toBe(orderRequestSignature(retry));
  });
});

describe('API error feedback', () => {
  it('distinguishes network and expired-session failures', () => {
    expect(getApiErrorMessage({isAxiosError: true, code: 'ERR_NETWORK', request: {}})).toMatch(/Cannot reach/);
    expect(getApiErrorMessage(new TypeError('local error'))).toBe('The request could not be completed.');
    expect(getApiErrorMessage({response: {status: 401}})).toMatch(/session has expired/);
  });
});
