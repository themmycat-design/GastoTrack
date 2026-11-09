import * as Keychain from 'react-native-keychain';
import {STAFF_SESSION_MS} from './authTokenStorage';

const SERVICE = 'com.gastotrack.offline-staff-account';
const options = {service: SERVICE, authenticationPrompt: {title: 'GastoTrack offline sign-in', cancel: 'Cancel'}};

export const saveOfflineStaffAccount = async (user, business, signedInAt) => {
  if (user?.role !== 'staff' || !user?.id || !user?.email || !business?.id) return;
  const account = {
    user: {id: user.id, name: user.name, email: user.email, role: 'staff', business_id: user.business_id || business.id},
    business: {id: business.id, name: business.name},
    expiresAt: signedInAt + STAFF_SESSION_MS,
  };
  const saved = await Keychain.setGenericPassword(String(user.id), JSON.stringify(account), options);
  if (!saved) throw new Error('Offline staff account could not be saved securely.');
};

export const getOfflineStaffAccount = async () => {
  const credentials = await Keychain.getGenericPassword(options);
  if (!credentials) return null;
  try {
    const account = JSON.parse(credentials.password);
    if (account.user?.role === 'staff' && account.user?.id && account.user?.email
      && account.business?.id && account.expiresAt > Date.now()) return account;
  } catch {}
  await Keychain.resetGenericPassword({service: SERVICE});
  return null;
};

export const clearOfflineStaffAccount = () => Keychain.resetGenericPassword({service: SERVICE});
