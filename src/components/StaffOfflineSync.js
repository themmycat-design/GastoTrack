import {useEffect} from 'react';
import {AppState} from 'react-native';
import {useAuth} from '../context/AuthContext';
import {setActiveStaffOfflineAccount, syncStaffOfflineActions} from '../services/staffOffline';

const StaffOfflineSync = () => {
  const {userToken, userRole, user, business, offlineMode} = useAuth();
  const userId = userToken && userRole === 'staff' ? Number(user?.id) : 0;
  const businessId = userId ? Number(user?.business_id || business?.id) : 0;

  useEffect(() => {
    if (!userId || !businessId) {
      setActiveStaffOfflineAccount(null);
      return undefined;
    }
    const account = {userId, businessId};
    setActiveStaffOfflineAccount(account);
    if (offlineMode) return () => setActiveStaffOfflineAccount(null);
    const sync = () => syncStaffOfflineActions(account).catch(() => {});
    sync();
    const appState = AppState.addEventListener('change', state => {
      if (state === 'active') sync();
    });
    const timer = setInterval(sync, 30000);
    return () => {
      setActiveStaffOfflineAccount(null);
      appState.remove();
      clearInterval(timer);
    };
  }, [businessId, userId, offlineMode]);
  return null;
};

export default StaffOfflineSync;
