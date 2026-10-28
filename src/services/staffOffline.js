import {NativeModules, Platform} from 'react-native';
import api from './api';
import {createIdempotencyKey} from '../config/orderIdempotency';

const nativeStore = () => {
  const store = NativeModules.NotificationModule;
  if (Platform.OS !== 'android' || !store?.queueStaffAction) {
    throw new Error('Offline recording requires an updated Android app. Rebuild and reinstall it before recording offline.');
  }
  return store;
};

const accountIds = account => {
  const userId = Number(account?.userId);
  const businessId = Number(account?.businessId);
  if (!Number.isInteger(userId) || userId <= 0 || !Number.isInteger(businessId) || businessId <= 0) {
    throw new Error('Sign in to a business before recording offline.');
  }
  return [userId, businessId];
};

const listeners = new Set();
export const subscribeStaffOffline = listener => {
  listeners.add(listener);
  return () => listeners.delete(listener);
};
const notify = event => listeners.forEach(listener => listener(event));

export const getStaffOfflineActions = async account => {
  const [userId, businessId] = accountIds(account);
  return JSON.parse(await nativeStore().getStaffActions(userId, businessId) || '[]');
};

export const retryStaffOfflineAction = async (account, clientId) => {
  const [userId, businessId] = accountIds(account);
  await nativeStore().retryStaffAction(userId, businessId, clientId);
  notify('retry');
  return syncStaffOfflineActions(account);
};

export const saveStaffSnapshot = async (account, kind, value) => {
  const [userId, businessId] = accountIds(account);
  await nativeStore().saveStaffSnapshot(userId, businessId, kind, JSON.stringify(value));
};

export const getStaffSnapshot = async (account, kind) => {
  const [userId, businessId] = accountIds(account);
  const value = await nativeStore().getStaffSnapshot(userId, businessId, kind);
  return value ? JSON.parse(value) : null;
};

const endpoints = {
  transaction: action => ['/transactions', action.payload],
  cash_order: action => ['/orders', action.payload],
  stock_adjustment: action => [`/stock/${action.payload.stock_id}/adjust`, action.payload],
};

const inFlight = new Map();
let activeAccountKey = null;
export const setActiveStaffOfflineAccount = account => {
  activeAccountKey = account ? accountIds(account).join(':') : '';
};
export const syncStaffOfflineActions = async account => {
  const [userId, businessId] = accountIds(account);
  const key = `${userId}:${businessId}`;
  if (activeAccountKey !== null && activeAccountKey !== key) return [];
  if (inFlight.has(key)) return inFlight.get(key);
  const run = (async () => {
    const results = [];
    const rows = await getStaffOfflineActions(account);
    for (const action of rows) {
      if (activeAccountKey !== null && activeAccountKey !== key) break;
      if (action.status !== 'pending') continue;
      const endpoint = endpoints[action.kind];
      if (!endpoint) {
        await nativeStore().markStaffActionRejected(userId, businessId, action.client_id, 'Unsupported offline action');
        results.push({clientId: action.client_id, status: 'rejected'});
        continue;
      }
      try {
        const [path, payload] = endpoint(action);
        const response = await api.post(path, payload);
        const saved = action.kind === 'transaction' ? response.data?.transaction
          : action.kind === 'cash_order' ? response.data?.order : response.data?.item;
        if (!saved?.id) throw new Error('The server did not confirm the saved record.');
        await nativeStore().markStaffActionSynced(userId, businessId, action.client_id);
        results.push({clientId: action.client_id, status: 'synced', data: response.data});
        notify('synced');
      } catch (error) {
        const status = error?.response?.status;
        if (status === 401) {
          // Login can expire while the phone is offline. Keep the action ready
          // for the same staff member's next authenticated session.
          results.push({clientId: action.client_id, status: 'pending'});
          break;
        }
        if (status && status >= 400 && status < 500 && status !== 408 && status !== 429) {
          const reason = error.response?.data?.message || 'The server rejected this record. Review it before retrying.';
          await nativeStore().markStaffActionRejected(userId, businessId, action.client_id, reason);
          results.push({clientId: action.client_id, status: 'rejected', reason});
          notify('rejected');
          if (status === 403) break;
        } else {
          results.push({clientId: action.client_id, status: 'pending'});
          break; // Keep FIFO order when the server is unreachable.
        }
      }
    }
    return results;
  })();
  inFlight.set(key, run);
  try {
    return await run;
  } finally {
    inFlight.delete(key);
  }
};

/** Persist first. Never claim a sale/adjustment is completed until Laravel confirms it. */
export const recordStaffAction = async (account, kind, payload, clientId = createIdempotencyKey()) => {
  const [userId, businessId] = accountIds(account);
  const action = {...payload, client_id: clientId};
  await nativeStore().queueStaffAction(userId, businessId, clientId, kind, JSON.stringify(action));
  notify('queued');
  let result;
  try {
    const results = await syncStaffOfflineActions(account);
    result = results.find(item => item.clientId === clientId);
  } catch {
    // The SQLite row is durable and will be retried by the foreground worker.
  }
  if (result?.status === 'rejected') throw new Error(result.reason);
  return {clientId, synced: result?.status === 'synced', data: result?.data};
};
