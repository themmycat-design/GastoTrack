import React, {createContext, useCallback, useContext, useEffect, useMemo, useState} from 'react';
import api from '../services/api';
import {AuthContext} from './AuthContext';
import {getApiErrorMessage} from '../services/apiErrors';
import {getStaffSnapshot, recordStaffAction, saveStaffSnapshot, subscribeStaffOffline} from '../services/staffOffline';

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
  const [stockItems, setStockItems] = useState([]);
  const [outOfStockCount, setOutOfStockCount] = useState(0);
  const [isLoading, setIsLoading] = useState(false);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [loadError, setLoadError] = useState(null);
  const [pagination, setPagination] = useState({currentPage: 0, lastPage: 1, total: 0});
  const {userToken, user, business} = useContext(AuthContext);
  const offlineAccount = useMemo(() => user?.role === 'staff' && user?.id && (user?.business_id || business?.id)
    ? {userId: user.id, businessId: user.business_id || business.id} : null,
  [user?.role, user?.id, user?.business_id, business?.id]);

  const fetchStock = useCallback(async ({page = 1, append = false} = {}) => {
    if (!userToken) return;
    append ? setIsLoadingMore(true) : setIsLoading(true);
    try {
      const response = await api.get('/stock', {params: {page}});
      const normalized = (response.data.stock || []).map(normalize);
      if (offlineAccount && page === 1) saveStaffSnapshot(offlineAccount, 'stock', normalized).catch(() => {});
      setStockItems(current => append
        ? [...current, ...normalized.filter(record => !current.some(item => item.id === record.id))]
        : normalized,
      );
      setPagination(normalizePagination(response.data?.meta, page, normalized.length));
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
      append ? setIsLoadingMore(false) : setIsLoading(false);
    }
  }, [userToken, offlineAccount]);

  const hasMoreStock = pagination.currentPage < pagination.lastPage;
  useEffect(() => subscribeStaffOffline(event => {
    if (event === 'synced' && userToken) fetchStock();
  }), [fetchStock, userToken]);
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

  const getStatus = (quantity, threshold) => quantity <= 0 ? 'Out' : quantity <= threshold ? 'Low' : 'OK';

  const adjustStock = async (id, type, quantity, reason, movementCategory = 'adjustment', clientId = null, offlineRecordedAt = null) => {
    const payload = {type, quantity, reason, movement_category: movementCategory};
    const response = offlineAccount
      ? await recordStaffAction(offlineAccount, 'stock_adjustment', {
        ...payload, stock_id: id, offline_recorded_at: offlineRecordedAt || new Date().toISOString(),
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
