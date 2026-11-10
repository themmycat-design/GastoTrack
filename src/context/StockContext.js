import React, {createContext, useCallback, useContext, useEffect, useMemo, useRef, useState} from 'react';
import {AppState} from 'react-native';
import api from '../services/api';
import {AuthContext} from './AuthContext';
import {getApiErrorMessage} from '../services/apiErrors';
import {getStaffSnapshot, recordStaffAction, saveStaffSnapshot, subscribeStaffOffline} from '../services/staffOffline';
import {overlayStockActions, readPendingActions} from '../services/offlineRecords';

const StockContext = createContext();

const normalize = item => ({
  ...item,
  quantity: Number(item.current_quantity ?? item.quantity ?? 0),
  threshold: Number(item.minimum_quantity ?? item.threshold ?? 0),
  unitCost: Number(item.unit_cost ?? item.unitCost ?? 0),
});
const normalizePagination = (meta, page, count) => ({
  currentPage: Number(meta?.current_page ?? page),
  lastPage: Number(meta?.last_page ?? page),
  total: Number(meta?.total ?? count),
});

export const StockProvider = ({children}) => {
  const [serverStockItems, setStockItems] = useState([]);
  const [localActions, setLocalActions] = useState([]);
  const [outOfStockCount, setOutOfStockCount] = useState(0);
  const [pendingStockAdjustments, setPendingStockAdjustments] = useState({});
  const [rejectedStockAdjustments, setRejectedStockAdjustments] = useState({});
  const [isLoading, setIsLoading] = useState(false);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [loadError, setLoadError] = useState(null);
  const [pagination, setPagination] = useState({currentPage: 0, lastPage: 1, total: 0});
  const loadedPageCount = useRef(1);
  const {userToken, user, business} = useContext(AuthContext);
  const offlineAccount = useMemo(() => user?.role === 'staff' && user?.id && (user?.business_id || business?.id)
    ? {userId: user.id, businessId: user.business_id || business.id} : null,
  [user?.role, user?.id, user?.business_id, business?.id]);
  const stockItems = useMemo(() => overlayStockActions(serverStockItems, localActions), [serverStockItems, localActions]);

  const fetchStock = useCallback(async ({page = 1, append = false, refreshLoaded = false} = {}) => {
    if (!userToken) return;
    if (!refreshLoaded) append ? setIsLoadingMore(true) : setIsLoading(true);
    try {
      const pageCount = refreshLoaded ? loadedPageCount.current : 1;
      const responses = refreshLoaded
        ? await Promise.all(Array.from({length: pageCount}, (_, index) => api.get('/stock', {params: {page: index + 1}})))
        : [await api.get('/stock', {params: {page}})];
      const response = responses[0];
      const normalized = responses.flatMap(result => (result.data.stock || []).map(normalize));
      if (offlineAccount && (page === 1 || refreshLoaded)) saveStaffSnapshot(offlineAccount, 'stock', normalized).catch(() => {});
      setStockItems(current => append
        ? [...current, ...normalized.filter(record => !current.some(item => item.id === record.id))]
        : normalized.filter((item, index) => normalized.findIndex(candidate => candidate.id === item.id) === index),
      );
      const nextPagination = normalizePagination(response.data?.meta, page, normalized.length);
      if (refreshLoaded) nextPagination.currentPage = Math.min(pageCount, nextPagination.lastPage);
      loadedPageCount.current = nextPagination.currentPage || 1;
      setPagination(nextPagination);
      setOutOfStockCount(Number(response.data?.meta?.out_of_stock_count ?? 0));
      setLoadError(null);
      return {success: true};
    } catch (error) {
      if (offlineAccount && page === 1) {
        try {
          const cached = await getStaffSnapshot(offlineAccount, 'stock');
          if (cached) {
            setStockItems(cached);
            setOutOfStockCount(cached.filter(item => item.quantity <= 0 && item.active !== false).length);
          }
        } catch {}
      }
      setLoadError(getApiErrorMessage(error));
      return {success: false, error};
    } finally {
      if (!refreshLoaded) append ? setIsLoadingMore(false) : setIsLoading(false);
    }
  }, [userToken, offlineAccount]);

  const hasMoreStock = pagination.currentPage < pagination.lastPage;
  useEffect(() => {
    if (!offlineAccount) {
      setPendingStockAdjustments({});
      setRejectedStockAdjustments({});
      setLocalActions([]);
      return undefined;
    }
    let active = true;
    const refreshPending = async () => {
      const actions = await readPendingActions(offlineAccount);
      if (!active) return;
      setLocalActions(actions);
      const counts = {};
      const rejected = {};
      actions.filter(action => action.kind === 'stock_adjustment' && action.status === 'pending')
        .forEach(action => {counts[action.payload.stock_id] = (counts[action.payload.stock_id] || 0) + 1;});
      actions.filter(action => action.kind === 'stock_adjustment' && action.status === 'rejected')
        .forEach(action => {rejected[action.payload.stock_id] = action.error || 'Server rejected this adjustment';});
      setPendingStockAdjustments(counts);
      setRejectedStockAdjustments(rejected);
    };
    refreshPending();
    const unsubscribe = subscribeStaffOffline(event => {
      if (event === 'queued' || event === 'synced' || event === 'rejected') refreshPending();
      if (event === 'synced' && userToken) fetchStock();
    });
    return () => {active = false; unsubscribe();};
  }, [fetchStock, offlineAccount, userToken]);
  const loadMoreStock = useCallback(() => {
    if (!isLoading && !isLoadingMore && pagination.currentPage < pagination.lastPage) {
      return fetchStock({page: pagination.currentPage + 1, append: true});
    }
    return Promise.resolve();
  }, [fetchStock, isLoading, isLoadingMore, pagination]);

  useEffect(() => {
    if (userToken) {
      fetchStock();
    } else {
      setStockItems([]);
      setOutOfStockCount(0);
      setLoadError(null);
      setPagination({currentPage: 0, lastPage: 1, total: 0});
    }
  }, [fetchStock, userToken]);

  useEffect(() => {
    if (!userToken) return undefined;
    const onAppStateChange = state => {
      if (state === 'active') fetchStock({refreshLoaded: true});
    };
    const subscription = AppState.addEventListener('change', onAppStateChange);
    const timer = setInterval(() => {
      if (AppState.currentState === 'active') fetchStock({refreshLoaded: true});
    }, 30000);
    return () => {
      clearInterval(timer);
      subscription.remove();
    };
  }, [fetchStock, userToken]);

  const getStatus = (quantity, threshold) => quantity <= 0 ? 'Out' : quantity <= threshold ? 'Low' : 'OK';

  const adjustStock = async (id, type, quantity, reason, movementCategory = 'adjustment', clientId = null, offlineRecordedAt = null) => {
    if (!Number.isInteger(Number(id))) throw new Error('This stock item must sync before it can be adjusted.');
    const payload = {type, quantity, reason, movement_category: movementCategory};
    const original = serverStockItems.find(item => Number(item.id) === Number(id));
    if (offlineAccount && (original?.revision == null || !Number.isInteger(Number(original.revision)))) {
      throw new Error('Refresh this stock item online once before adjusting it offline.');
    }
    const actions = offlineAccount ? await readPendingActions(offlineAccount) : [];
    const earlierAttempt = clientId ? actions.find(action => action.client_id === clientId && action.kind === 'stock_adjustment') : null;
    if (earlierAttempt) {
      const savedPayload = {...earlierAttempt.payload};
      delete savedPayload.client_id;
      const retry = await recordStaffAction(offlineAccount, 'stock_adjustment', savedPayload, clientId);
      if (!retry.synced) return {...stockItems.find(item => item.id === id), offlinePending: true};
      const updated = normalize(retry.data.item);
      setStockItems(current => current.map(item => item.id === updated.id ? updated : item));
      return updated;
    }
    // Earlier queued adjustments for this item will advance its server revision first.
    const queuedForItem = actions.filter(action =>
      action.kind === 'stock_adjustment' && action.status === 'pending'
      && Number(action.payload.stock_id) === Number(id)).length;
    const response = offlineAccount
      ? await recordStaffAction(offlineAccount, 'stock_adjustment', {
        ...payload, stock_id: id, expected_revision: Number(original.revision) + queuedForItem,
        offline_recorded_at: offlineRecordedAt || new Date().toISOString(),
      }, clientId || undefined)
      : {synced: true, data: (await api.post(`/stock/${id}/adjust`, {...payload, ...(clientId ? {client_id: clientId} : {})})).data};
    if (!response.synced) return {...stockItems.find(item => item.id === id), offlinePending: true};
    const updated = normalize(response.data.item);
    setStockItems(current => current.map(item => item.id === updated.id ? updated : item));
    const previous = stockItems.find(item => item.id === updated.id);
    if (previous && previous.active !== false && updated.active !== false) {
      setOutOfStockCount(current => Math.max(0, current + Number(updated.quantity <= 0) - Number(previous.quantity <= 0)));
    }
    return updated;
  };

  const createStock = async stockData => {
    if (offlineAccount) {
      const result = await recordStaffAction(offlineAccount, 'stock_create', {data: stockData});
      setLocalActions(await readPendingActions(offlineAccount));
      if (!result.synced) return normalize({...stockData, id: `local:${result.clientId}`, offlinePending: true});
      const created = normalize(result.data.item);
      setStockItems(current => [...current, created].sort((a, b) => a.name.localeCompare(b.name)));
      if (created.active !== false && created.quantity <= 0) setOutOfStockCount(current => current + 1);
      setPagination(current => ({...current, total: current.total + 1}));
      return created;
    }
    const response = await api.post('/stock', stockData);
    const created = normalize(response.data.item);
    setStockItems(current => [...current, created].sort((a, b) => a.name.localeCompare(b.name)));
    if (created.active !== false && created.quantity <= 0) setOutOfStockCount(current => current + 1);
    setPagination(current => ({...current, total: current.total + 1}));
    return created;
  };

  return <StockContext.Provider value={{
    stockItems,
    outOfStockCount,
    pendingStockAdjustments,
    rejectedStockAdjustments,
    loadError,
    stockTotal: pagination.total,
    isLoading,
    isLoadingMore,
    hasMoreStock,
    fetchStock,
    loadMoreStock,
    getStatus,
    adjustStock,
    createStock,
  }}>{children}</StockContext.Provider>;
};

export const useStock = () => useContext(StockContext);
