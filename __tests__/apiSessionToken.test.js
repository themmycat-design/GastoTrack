import * as Keychain from 'react-native-keychain';
import api, {setApiAuthToken} from '../src/services/api';

describe('API session token', () => {
  afterEach(() => setApiAuthToken(null));

  it('attaches the restored token to data requests without reading native storage', () => {
    const requestInterceptor = api.interceptors.request.handlers[0].fulfilled;
    const request = requestInterceptor({method: 'get', url: '/products', headers: {}});
    expect(request.headers.Authorization).toBeUndefined();

    setApiAuthToken('saved-staff-token');
    const authenticated = requestInterceptor({method: 'get', url: '/transactions', headers: {}});
    expect(authenticated.headers.Authorization).toBe('Bearer saved-staff-token');
    expect(Keychain.getGenericPassword).not.toHaveBeenCalled();

    setApiAuthToken(null);
    const signedOut = requestInterceptor({method: 'get', url: '/products', headers: {Authorization: 'Bearer stale-token'}});
    expect(signedOut.headers.Authorization).toBeUndefined();
  });
});
