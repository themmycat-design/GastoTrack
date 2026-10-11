import * as Keychain from 'react-native-keychain';
import {saveAuthToken} from '../src/services/authTokenStorage';

describe('secure authentication token storage', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('provides Android Keychain the required authentication prompt labels', async () => {
    await saveAuthToken('session-token');

    expect(Keychain.setGenericPassword).toHaveBeenCalledWith(
      'gastotrack',
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
});
