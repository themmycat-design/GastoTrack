import * as Keychain from 'react-native-keychain';

const optionsFor = userId => ({
  service: `com.gastotrack.staff-pin.${userId}`,
  authenticationPrompt: {title: 'GastoTrack staff PIN', cancel: 'Cancel'},
});

export const isFourDigitPin = value => /^\d{4}$/.test(value);

export const getStaffPin = async userId => {
  const credentials = await Keychain.getGenericPassword(optionsFor(userId));
  return credentials ? credentials.password : null;
};

export const saveStaffPin = async (userId, pin) => {
  if (!isFourDigitPin(pin)) throw new Error('PIN must contain exactly four numbers.');
  const saved = await Keychain.setGenericPassword(String(userId), pin, optionsFor(userId));
  if (!saved) throw new Error('The PIN could not be saved securely on this device.');
};

export const clearStaffPin = userId => Keychain.resetGenericPassword(optionsFor(userId));
