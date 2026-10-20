import * as Keychain from 'react-native-keychain';
import {clearStaffPin, getStaffPin, isFourDigitPin, saveStaffPin} from '../src/services/staffPinStorage';

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
});
