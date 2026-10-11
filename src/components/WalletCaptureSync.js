import {useCallback, useEffect, useRef} from 'react';
import {AppState} from 'react-native';
import {useAuth} from '../context/AuthContext';
import api from '../services/api';
import {
  getPendingWalletCaptures,
  markWalletCapturesSynced,
  setWalletCaptureAccount,
  subscribeToNotifications,
} from '../modules/NotificationModule';
import {emitWalletCaptureSync} from '../services/walletCaptureSyncEvents';

// The Android listener writes captures before emitting a JS event. Uploads can
// safely retry because Laravel has a unique client_id for each capture.
const WalletCaptureSync = () => {
  const {userToken, userRole, user, business} = useAuth();
  const staffId = userToken && userRole === 'staff' ? Number(user?.id) || 0 : 0;
  const businessId = staffId ? Number(user?.business_id || business?.id) || 0 : 0;
  const activeAccount = useRef('');
  const syncing = useRef(false);

  const sync = useCallback(async () => {
    const accountKey = `${staffId}:${businessId}`;
    if (!staffId || !businessId || syncing.current || activeAccount.current !== accountKey) return;
    syncing.current = true;
    try {
      while (activeAccount.current === accountKey) {
        const captures = await getPendingWalletCaptures();
        if (!captures.length || activeAccount.current !== accountKey) break;
        const response = await api.post('/wallet-notifications/batch', {captures});
        const savedIds = response.data?.saved_ids || [];
        if (savedIds.length !== captures.length || activeAccount.current !== accountKey) break;
        await markWalletCapturesSynced(savedIds);
        emitWalletCaptureSync();
      }
    } catch (error) {
      if (__DEV__) console.warn('Wallet captures will retry sync later:', error?.code || error?.response?.status || 'UNKNOWN');
    } finally {
      syncing.current = false;
    }
  }, [businessId, staffId]);

  useEffect(() => {
    const accountKey = `${staffId}:${businessId}`;
    activeAccount.current = '';
    let cancelled = false;
    setWalletCaptureAccount(staffId, businessId)
      .then(() => {
        if (!cancelled && staffId && businessId) {
          activeAccount.current = accountKey;
          sync();
        }
      })
      .catch(error => {
        if (__DEV__) console.warn('Wallet capture account could not be set:', error?.code || 'UNKNOWN');
      });
    if (!staffId || !businessId) return () => { cancelled = true; };

    const unsubscribe = subscribeToNotifications(() => {
      emitWalletCaptureSync();
      sync();
    });
    const appState = AppState.addEventListener('change', state => {
      if (state === 'active') sync();
    });
    const timer = setInterval(sync, 60000);
    return () => {
      cancelled = true;
      activeAccount.current = '';
      unsubscribe();
      appState.remove();
      clearInterval(timer);
    };
  }, [businessId, staffId, sync]);

  return null;
};

export default WalletCaptureSync;
