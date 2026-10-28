import * as Keychain from 'react-native-keychain';

const optionsFor = userId => ({
  service: `com.gastotrack.staff-pin.${userId}`,
  authenticationPrompt: {title: 'GastoTrack staff PIN', cancel: 'Cancel'},
});
const attemptsOptionsFor = userId => ({service: `com.gastotrack.staff-pin-attempts.${userId}`});

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

export const getStaffPinAttempts = async userId => {
  const credentials = await Keychain.getGenericPassword(attemptsOptionsFor(userId));
  if (!credentials) return 0;
  const attempts = Number(credentials.password);
  if (!Number.isInteger(attempts) || attempts < 0 || attempts > 5) {
    throw new Error('Stored PIN lockout data is invalid.');
  }
  return attempts;
};

export const recordStaffPinFailure = async userId => {
  const attempts = Math.min(5, (await getStaffPinAttempts(userId)) + 1);
  const saved = await Keychain.setGenericPassword(String(userId), String(attempts), attemptsOptionsFor(userId));
  if (!saved) throw new Error('PIN lockout could not be stored securely.');
  return attempts;
};

export const resetStaffPinAttempts = userId => Keychain.resetGenericPassword(attemptsOptionsFor(userId));

export const clearStaffPin = async userId => {
  await Keychain.resetGenericPassword(optionsFor(userId));
  await resetStaffPinAttempts(userId);
};
