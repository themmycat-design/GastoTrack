import * as Keychain from 'react-native-keychain';
import {
  clearStaffPin, getStaffPin, getStaffPinAttempts, isFourDigitPin,
  recordStaffPinFailure, resetStaffPinAttempts, saveStaffPin,
} from '../src/services/staffPinStorage';

describe('staff MPIN secure storage', () => {
  beforeEach(() => jest.clearAllMocks());

  it('accepts only exactly four digits', async () => {
    expect(isFourDigitPin('0042')).toBe(true);
    expect(isFourDigitPin('123')).toBe(false);
    expect(isFourDigitPin('12345')).toBe(false);
    expect(isFourDigitPin('12a4')).toBe(false);
    await expect(saveStaffPin(17, '12a4')).rejects.toThrow('four numbers');
    expect(Keychain.setGenericPassword).not.toHaveBeenCalled();
  });

  it('stores separate staff PINs under account-specific secure services', async () => {
    await saveStaffPin(17, '0042');
    expect(Keychain.setGenericPassword).toHaveBeenCalledWith('17', '0042', expect.objectContaining({
      service: 'com.gastotrack.staff-pin.17',
    }));
    Keychain.getGenericPassword.mockResolvedValueOnce({username: '17', password: '0042'});
    await expect(getStaffPin(17)).resolves.toBe('0042');
    await clearStaffPin(18);
    expect(Keychain.resetGenericPassword).toHaveBeenCalledWith(expect.objectContaining({
      service: 'com.gastotrack.staff-pin.18',
    }));
  });

  it('stores failed attempts in an account-specific secure service', async () => {
    Keychain.getGenericPassword.mockResolvedValueOnce(false);
    await expect(recordStaffPinFailure(17)).resolves.toBe(1);
    expect(Keychain.setGenericPassword).toHaveBeenCalledWith('17', '1', expect.objectContaining({
      service: 'com.gastotrack.staff-pin-attempts.17',
    }));
    Keychain.getGenericPassword.mockResolvedValueOnce({username: '17', password: '5'});
    await expect(getStaffPinAttempts(17)).resolves.toBe(5);
    await resetStaffPinAttempts(17);
    expect(Keychain.resetGenericPassword).toHaveBeenCalledWith(expect.objectContaining({
      service: 'com.gastotrack.staff-pin-attempts.17',
    }));
  });
});
