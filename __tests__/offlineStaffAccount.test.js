import * as Keychain from 'react-native-keychain';
import {STAFF_SESSION_MS} from '../src/services/authTokenStorage';
import {getOfflineStaffAccount, saveOfflineStaffAccount} from '../src/services/offlineStaffAccount';

describe('offline staff account', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    Keychain.getGenericPassword.mockResolvedValue(false);
  });

  it('stores only a previously verified staff identity in secure storage', async () => {
    await saveOfflineStaffAccount(
      {id: 17, name: 'Ava', email: 'ava@example.test', role: 'staff', business_id: 4},
      {id: 4, name: 'Cafe'}, Date.now());
    const [userId, saved, options] = Keychain.setGenericPassword.mock.calls[0];
    expect(userId).toBe('17');
    expect(JSON.parse(saved)).toMatchObject({user: {id: 17, email: 'ava@example.test'}, business: {id: 4}});
    expect(saved).not.toContain('password');
    expect(saved).not.toContain('token');
    expect(options.service).toBe('com.gastotrack.offline-staff-account');
  });

  it('rejects an expired offline identity', async () => {
    const expired = {user: {id: 17, role: 'staff', email: 'ava@example.test'},
      business: {id: 4}, expiresAt: Date.now() - STAFF_SESSION_MS};
    Keychain.getGenericPassword.mockResolvedValue({password: JSON.stringify(expired)});
    await expect(getOfflineStaffAccount()).resolves.toBeNull();
    expect(Keychain.resetGenericPassword).toHaveBeenCalledWith({service: 'com.gastotrack.offline-staff-account'});
  });
});
