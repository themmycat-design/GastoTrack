import * as Keychain from 'react-native-keychain';
import AsyncStorage from '@react-native-async-storage/async-storage';
import {getAuthSession, saveAuthToken, STAFF_SESSION_MS} from '../src/services/authTokenStorage';

describe('secure authentication token storage', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('provides Android Keychain the required authentication prompt labels', async () => {
    await saveAuthToken('session-token', 123456789);

    expect(Keychain.setGenericPassword).toHaveBeenCalledWith(
      'gastotrack:123456789',
      'session-token',
      expect.objectContaining({
        service: 'com.gastotrack.auth-token',
        authenticationPrompt: {
          title: 'GastoTrack secure sign-in',
          cancel: 'Cancel',
        },
      }),
    );
  });

  it('restores an unexpired session without changing its login date', async () => {
    const signedInAt = Date.now() - STAFF_SESSION_MS + 1000;
    Keychain.getGenericPassword.mockResolvedValueOnce({username: `gastotrack:${signedInAt}`, password: 'session-token'});
    await expect(getAuthSession()).resolves.toEqual({token: 'session-token', expiresAt: signedInAt + STAFF_SESSION_MS, expired: false});
  });

  it('expires a 30-day session without removing the separate staff PIN', async () => {
    const signedInAt = Date.now() - STAFF_SESSION_MS;
    Keychain.getGenericPassword.mockResolvedValueOnce({username: `gastotrack:${signedInAt}`, password: 'session-token'});
    await expect(getAuthSession()).resolves.toEqual({token: null, expiresAt: null, expired: true});
    expect(Keychain.resetGenericPassword).toHaveBeenCalledWith({service: 'com.gastotrack.auth-token'});
    expect(Keychain.resetGenericPassword).not.toHaveBeenCalledWith(expect.objectContaining({service: 'com.gastotrack.staff-pin.17'}));
  });

  it('requires login for old sessions without a recorded sign-in date', async () => {
    Keychain.getGenericPassword.mockResolvedValueOnce({username: 'gastotrack', password: 'old-token'});
    await expect(getAuthSession()).resolves.toMatchObject({token: null, expired: true});
    expect(AsyncStorage.removeItem).toHaveBeenCalledWith('userToken');
  });
});
