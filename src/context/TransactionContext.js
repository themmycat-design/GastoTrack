import React, { createContext, useState, useEffect, useContext, useCallback, useMemo } from 'react';
import api from '../services/api';
import { AuthContext } from './AuthContext'; 
import {getApiErrorMessage} from '../services/apiErrors';
import {productCategoriesFromOptions} from '../constants/productCategories';
import {getStaffSnapshot, recordStaffAction, saveStaffSnapshot, subscribeStaffOffline} from '../services/staffOffline';
import {filterCachedTransactions, overlayTransactionMutations, pendingOrderTransactions, pendingTransactions, readPendingActions} from '../services/offlineRecords';

export const TransactionContext = createContext();

// Transaction constants
export const INCOME_CATEGORIES = ['Sales', 'Tips', 'Other Income'];
export const EXPENSE_CATEGORIES = ['Ingredients', 'Rent', 'Salaries', 'Utilities', 'Supplies', 'Other Expense'];
export const TRANSACTION_SOURCES = ['Cash', 'GCash', 'Maya', 'Bank Transfer', 'Credit Card'];

const mergeOptions = (defaults, custom) => {
  const seen = new Set();
  return [...defaults, ...custom].filter(value => {
    const key = String(value).trim().toLowerCase();
    if (!key || seen.has(key)) return false;
    seen.add(key);
    return true;
  });
};

const titleCaseType = type => type?.toLowerCase() === 'income' ? 'Income' : 'Expense';
const dateOnly = value => value ? String(value).split('T')[0] : value;
const normalizeSource = source => {
  const key = String(source || '').trim().toLowerCase().replace(/[_-]+/g, ' ');
  return {
    cash: 'Cash',
    gcash: 'GCash',
    maya: 'Maya',
    bank: 'Bank Transfer',
    'bank transfer': 'Bank Transfer',
    'credit card': 'Credit Card',
  }[key] || source;
};
const normalizeTransaction = transaction => ({
  ...transaction,
  type: titleCaseType(transaction.type),
  source: normalizeSource(transaction.source),
  date: dateOnly(transaction.transaction_date || transaction.date),
  notes: transaction.description || transaction.notes,
  entryMethod: transaction.entry_method || transaction.entryMethod,
});
const pendingActivity = actions => [...pendingTransactions(actions), ...pendingOrderTransactions(actions)]
  .sort((left, right) => new Date(right.created_at).getTime() - new Date(left.created_at).getTime());
const normalizePagination = (meta, page, count) => ({
  currentPage: Number(meta?.current_page ?? page),
  lastPage: Number(meta?.last_page ?? page),
  total: Number(meta?.total ?? count),
});

const toApiPayload = transaction => ({
  amount: transaction.amount,
  type: transaction.type?.toLowerCase(),
  source: transaction.source,
  category: transaction.category,
  date: transaction.date,
  notes: transaction.notes || null,
  client_id: transaction.clientId || transaction.client_id || undefined,
  entry_method: String(transaction.entryMethod || transaction.entry_method || 'manual')
    .toLowerCase()
    .replace(/\s+/g, '_'),
  metadata: transaction.metadata || undefined,
});

export const TransactionProvider = ({ children }) => {
  const [transactions, setTransactions] = useState([]);
  const [isLoading, setIsLoading] = useState(false);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [loadError, setLoadError] = useState(null);
  const [pagination, setPagination] = useState({currentPage: 0, lastPage: 1, total: 0});
  const [transactionOptions, setTransactionOptions] = useState([]);
  const [optionsLoaded, setOptionsLoaded] = useState(false);
  const [optionsLoading, setOptionsLoading] = useState(false);
  const [optionsError, setOptionsError] = useState(null);
  const {userToken, user, business} = useContext(AuthContext);
  const offlineAccount = useMemo(() => user?.role === 'staff' && user?.id && (user?.business_id || business?.id)
    ? {userId: user.id, businessId: user.business_id || business.id} : null,
  [user?.role, user?.id, user?.business_id, business?.id]);

  const incomeCategories = useMemo(() => optionsLoaded
    ? mergeOptions([], transactionOptions.filter(option => option.kind === 'category' && option.transaction_type === 'income').map(option => option.name))
    : INCOME_CATEGORIES, [optionsLoaded, transactionOptions]);
  const expenseCategories = useMemo(() => optionsLoaded
    ? mergeOptions([], transactionOptions.filter(option => option.kind === 'category' && option.transaction_type === 'expense').map(option => option.name))
    : EXPENSE_CATEGORIES, [optionsLoaded, transactionOptions]);
  const transactionSources = useMemo(() => optionsLoaded
    ? mergeOptions([], transactionOptions.filter(option => option.kind === 'source').map(option => option.name))
    : TRANSACTION_SOURCES, [optionsLoaded, transactionOptions]);
  const productCategories = useMemo(
    () => productCategoriesFromOptions(transactionOptions, optionsLoaded),
    [optionsLoaded, transactionOptions],
  );

  const fetchTransactionOptions = useCallback(async () => {
    if (!userToken) return;
    setOptionsLoading(true);
    try {
      const response = await api.get('/transaction-options');
      setTransactionOptions(response.data?.options || []);
      if (offlineAccount) saveStaffSnapshot(offlineAccount, 'transaction_options', response.data?.options || []).catch(() => {});
      setOptionsLoaded(true);
      setOptionsError(null);
    } catch (error) {
      if (offlineAccount) {
        try {
          const cached = await getStaffSnapshot(offlineAccount, 'transaction_options');
          if (cached) {
            setTransactionOptions(cached);
            setOptionsLoaded(true);
          }
        } catch {}
      }
      setOptionsError(getApiErrorMessage(error));
    } finally {
      setOptionsLoading(false);
    }
  }, [userToken, offlineAccount]);

  const addTransactionOption = async optionData => {
    try {
      const response = await api.post('/transaction-options', optionData);
      const option = response.data?.option;
      if (option) setTransactionOptions(current => [...current, option]);
      return {success: true, option};
    } catch (error) {
      return {success: false, error};
    }
  };

  const deleteTransactionOption = async optionId => {
    try {
      await api.delete(`/transaction-options/${optionId}`);
      setTransactionOptions(current => current.filter(option => option.id !== optionId));
      return {success: true};
    } catch (error) {
      return {success: false, error};
    }
  };

  const updateTransactionOption = async (optionId, optionData) => {
    try {
      const response = await api.put(`/transaction-options/${optionId}`, optionData);
      const option = response.data?.option;
      if (option) setTransactionOptions(current => current.map(item => item.id === option.id ? option : item));
      return {success: true, option};
    } catch (error) {
      return {success: false, error};
    }
  };

  // Kumuha ng transactions mula sa Laravel
  const fetchTransactions = useCallback(async ({page = 1, append = false} = {}) => {
    if (!userToken) return;
    append ? setIsLoadingMore(true) : setIsLoading(true);
    try {
      const response = await api.get('/transactions', {params: {page}});
      const records = response.data?.transactions || (Array.isArray(response.data) ? response.data : []);
      const normalized = records.map(normalizeTransaction);
      if (offlineAccount && page === 1) saveStaffSnapshot(offlineAccount, 'transactions', normalized).catch(() => {});
      const actions = offlineAccount && page === 1 ? await readPendingActions(offlineAccount) : [];
      const pending = offlineAccount && page === 1
        ? pendingActivity(actions).map(normalizeTransaction)
          .filter(local => !normalized.some(remote => remote.client_id === local.client_id)) : [];
      setTransactions(current => append
        ? [...current, ...normalized.filter(record => !current.some(item => item.id === record.id))]
        : [...pending, ...overlayTransactionMutations(normalized, actions)],
      );
      setPagination(normalizePagination(response.data?.meta, page, normalized.length));
      setLoadError(null);
      return {success: true};
    } catch (error) {
      if (offlineAccount && page === 1) {
        try {
          const cached = await getStaffSnapshot(offlineAccount, 'transactions') || [];
          const actions = await readPendingActions(offlineAccount);
          const pending = pendingActivity(actions).map(normalizeTransaction);
          setTransactions([...pending, ...overlayTransactionMutations(cached, actions)]);
          setPagination(normalizePagination(null, 1, cached.length + pending.length));
        } catch {}
      }
      setLoadError(getApiErrorMessage(error));
      return {success: false, error};
    } finally {
      append ? setIsLoadingMore(false) : setIsLoading(false);
    }
  }, [userToken, offlineAccount]);

  useEffect(() => subscribeStaffOffline(event => {
    if (['queued', 'synced', 'rejected'].includes(event) && userToken) fetchTransactions();
  }), [fetchTransactions, userToken]);

  const queryTransactions = useCallback(async ({page = 1, search, source, category, from, to} = {}) => {
    const params = {page};
    if (search?.trim()) params.search = search.trim();
    if (source && source !== 'All') params.source = source;
    if (category && category !== 'All') params.category = category;
    if (from) params.from = from;
    if (to) params.to = to;

    try {
      const response = await api.get('/transactions', {params});
      const records = response.data?.transactions || (Array.isArray(response.data) ? response.data : []);
      const normalized = records.map(normalizeTransaction);
      const actions = offlineAccount && page === 1 ? await readPendingActions(offlineAccount) : [];
      const pending = offlineAccount && page === 1
        ? filterCachedTransactions(pendingActivity(actions), {search, source, category, from, to})
          .map(normalizeTransaction).filter(local => !normalized.some(remote => remote.client_id === local.client_id)) : [];
      return {
        success: true,
        transactions: [...pending, ...overlayTransactionMutations(normalized, actions)],
        pagination: normalizePagination(response.data?.meta, page, records.length),
      };
    } catch (error) {
      if (offlineAccount) {
        try {
          const cached = await getStaffSnapshot(offlineAccount, 'transactions') || [];
          const actions = await readPendingActions(offlineAccount);
          const pending = pendingActivity(actions);
          const filtered = filterCachedTransactions([...pending, ...cached], {search, source, category, from, to});
          return {
            success: true, offline: true,
            transactions: overlayTransactionMutations(filtered.map(normalizeTransaction), actions),
            pagination: normalizePagination(null, 1, filtered.length),
          };
        } catch {}
      }
      return {success: false, error};
    }
  }, [offlineAccount]);

  const fetchTransactionCategories = useCallback(async source => {
    try {
      const response = await api.get('/transactions/categories', {
        params: source && source !== 'All' ? {source} : {},
      });
      return {success: true, categories: response.data?.categories || []};
    } catch (error) {
      if (offlineAccount) {
        try {
          const cached = await getStaffSnapshot(offlineAccount, 'transactions') || [];
          const categories = [...new Set(cached.filter(item => !source || source === 'All' || item.source === source)
            .map(item => item.category).filter(Boolean))].sort();
          return {success: true, categories};
        } catch {}
      }
      return {success: false, error};
    }
  }, [offlineAccount]);

  const hasMoreTransactions = pagination.currentPage < pagination.lastPage;
  const loadMoreTransactions = useCallback(() => {
    if (!isLoading && !isLoadingMore && pagination.currentPage < pagination.lastPage) {
      return fetchTransactions({page: pagination.currentPage + 1, append: true});
    }
    return Promise.resolve();
  }, [fetchTransactions, isLoading, isLoadingMore, pagination]);

  // Mag-save ng bagong transaction
  const addTransaction = async (transactionData) => {
    try {
      const recordMethod = String(transactionData.entryMethod || 'manual').toLowerCase();
      const response = offlineAccount && ['manual', 'ocr'].includes(recordMethod)
        ? await recordStaffAction(offlineAccount, 'transaction', toApiPayload(transactionData), transactionData.clientId || transactionData.client_id)
        : {synced: true, data: (await api.post('/transactions', toApiPayload(transactionData))).data};
      if (!response.synced) return {success: true, queued: true, clientId: response.clientId};
      const transaction = normalizeTransaction(response.data.transaction || response.data);
      setTransactions(current => [transaction, ...current]);
      setPagination(current => ({...current, total: current.total + 1}));
      return { success: true, transaction };
    } catch (error) {
      return { success: false, error };
    }
  };

  const updateTransaction = async transactionData => {
    try {
      if (offlineAccount) {
        if (!transactionData.updated_at || !Number.isInteger(Number(transactionData.revision))) throw new Error('Refresh this transaction online once before editing it offline.');
        const actions = await readPendingActions(offlineAccount);
        if (actions.some(action => action.status === 'pending' && ['transaction_update', 'transaction_delete'].includes(action.kind)
          && Number(action.payload.target_id) === Number(transactionData.id))) {
          throw new Error('This transaction already has a change waiting to sync.');
        }
        const result = await recordStaffAction(offlineAccount, 'transaction_update', {
          target_id: transactionData.id, expected_updated_at: transactionData.updated_at,
          expected_revision: Number(transactionData.revision),
          data: toApiPayload(transactionData),
        });
        if (!result.synced) return {success: true, queued: true, transaction: {...transactionData, offlinePending: true}};
        const transaction = normalizeTransaction(result.data.transaction);
        setTransactions(current => current.map(item => item.id === transaction.id ? transaction : item));
        return {success: true, transaction};
      }
      const response = await api.put(
        `/transactions/${transactionData.id}`,
        toApiPayload(transactionData),
      );
      const transaction = normalizeTransaction(response.data.transaction || response.data);
      setTransactions(current => current.map(item => item.id === transaction.id ? transaction : item));
      return { success: true, transaction };
    } catch (error) {
      return { success: false, error };
    }
  };

  const deleteTransaction = async (transactionId, record = null) => {
    try {
      if (offlineAccount) {
        const original = record || transactions.find(item => item.id === transactionId);
        if (!original?.updated_at || !Number.isInteger(Number(original.revision))) throw new Error('Refresh this transaction online once before deleting it offline.');
        const actions = await readPendingActions(offlineAccount);
        if (actions.some(action => action.status === 'pending' && ['transaction_update', 'transaction_delete'].includes(action.kind)
          && Number(action.payload.target_id) === Number(transactionId))) {
          throw new Error('This transaction already has a change waiting to sync.');
        }
        const result = await recordStaffAction(offlineAccount, 'transaction_delete', {
          target_id: transactionId, expected_updated_at: original.updated_at,
          expected_revision: Number(original.revision), data: {},
        });
        if (!result.synced) return {success: true, queued: true};
        setTransactions(current => current.filter(item => item.id !== transactionId));
        setPagination(current => ({...current, total: Math.max(0, current.total - 1)}));
        return {success: true};
      }
      await api.delete(`/transactions/${transactionId}`);
      setTransactions(current => current.filter(item => item.id !== transactionId));
      setPagination(current => ({...current, total: Math.max(0, current.total - 1)}));
      return {success: true};
    } catch (error) {
      return {success: false, error};
    }
  };

  // I-load ang data kapag nag-login ang user
  useEffect(() => {
    if (userToken) {
      fetchTransactions();
      fetchTransactionOptions();
    } else {
      setTransactions([]);
      setLoadError(null);
      setPagination({currentPage: 0, lastPage: 1, total: 0});
      setTransactionOptions([]);
      setOptionsLoaded(false);
      setOptionsError(null);
    }
  }, [userToken, fetchTransactionOptions, fetchTransactions]);

  return (
    <TransactionContext.Provider value={{ 
      transactions, 
      isLoading, 
      loadError,
      isLoadingMore,
      hasMoreTransactions,
      transactionTotal: pagination.total,
      fetchTransactions, 
      queryTransactions,
      fetchTransactionCategories,
      loadMoreTransactions,
      addTransaction,
      updateTransaction,
      deleteTransaction,
      transactionOptions,
      optionsLoading,
      optionsError,
      fetchTransactionOptions,
      addTransactionOption,
      updateTransactionOption,
      deleteTransactionOption,
      INCOME_CATEGORIES: incomeCategories,
      EXPENSE_CATEGORIES: expenseCategories,
      TRANSACTION_SOURCES: transactionSources,
      PRODUCT_CATEGORIES: productCategories,
    }}>
      {children}
    </TransactionContext.Provider>
  );
};

// Export useTransactions hook
export const useTransactions = () => {
  const context = useContext(TransactionContext);
  if (!context) {
    throw new Error('useTransactions must be used within a TransactionProvider');
  }
  return context;
};
