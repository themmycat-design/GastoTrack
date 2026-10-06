import React, {createContext, useCallback, useContext, useEffect, useState} from 'react';
import api from '../services/api';
import {AuthContext} from './AuthContext';

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
  const [isLoading, setIsLoading] = useState(false);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [pagination, setPagination] = useState({currentPage: 0, lastPage: 1, total: 0});
  const {userToken} = useContext(AuthContext);

  const fetchStock = useCallback(async ({page = 1, append = false} = {}) => {
    if (!userToken) return;
    append ? setIsLoadingMore(true) : setIsLoading(true);
    try {
      const response = await api.get('/stock', {params: {page}});
      const normalized = (response.data.stock || []).map(normalize);
      setStockItems(current => append
        ? [...current, ...normalized.filter(record => !current.some(item => item.id === record.id))]
        : normalized,
      );
      setPagination(normalizePagination(response.data?.meta, page, normalized.length));
    } catch (error) {
      console.log('Error fetching stock:', error.response?.data || error.message);
    } finally {
      append ? setIsLoadingMore(false) : setIsLoading(false);
    }
  }, [userToken]);

  const hasMoreStock = pagination.currentPage < pagination.lastPage;
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
      setPagination({currentPage: 0, lastPage: 1, total: 0});
    }
  }, [fetchStock, userToken]);

  const getStatus = (quantity, threshold) => quantity <= 0 ? 'Out' : quantity <= threshold ? 'Low' : 'OK';

  const adjustStock = async (id, type, quantity, reason) => {
    const response = await api.post(`/stock/${id}/adjust`, {type, quantity, reason});
    const updated = normalize(response.data.item);
    setStockItems(current => current.map(item => item.id === updated.id ? updated : item));
    return updated;
  };

  const createStock = async stockData => {
    const response = await api.post('/stock', stockData);
    const created = normalize(response.data.item);
    setStockItems(current => [...current, created].sort((a, b) => a.name.localeCompare(b.name)));
    setPagination(current => ({...current, total: current.total + 1}));
    return created;
  };

  return <StockContext.Provider value={{
    stockItems,
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
