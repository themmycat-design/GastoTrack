import React, { createContext, useState, useEffect, useContext, useCallback, useMemo } from 'react';
import api from '../services/api';
import { AuthContext } from './AuthContext'; 
import {getApiErrorMessage} from '../services/apiErrors';
import {productCategoriesFromOptions} from '../constants/productCategories';

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
  const { userToken } = useContext(AuthContext);

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
      setOptionsLoaded(true);
      setOptionsError(null);
    } catch (error) {
      setOptionsError(getApiErrorMessage(error));
    } finally {
      setOptionsLoading(false);
    }
  }, [userToken]);

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
      setTransactions(current => append
        ? [...current, ...normalized.filter(record => !current.some(item => item.id === record.id))]
        : normalized,
      );
      setPagination(normalizePagination(response.data?.meta, page, normalized.length));
      setLoadError(null);
      return {success: true};
    } catch (error) {
      setLoadError(getApiErrorMessage(error));
      return {success: false, error};
    } finally {
      append ? setIsLoadingMore(false) : setIsLoading(false);
    }
  }, [userToken]);

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
      return {
        success: true,
        transactions: records.map(normalizeTransaction),
        pagination: normalizePagination(response.data?.meta, page, records.length),
      };
    } catch (error) {
      return {success: false, error};
    }
  }, []);

  const fetchTransactionCategories = useCallback(async source => {
    try {
      const response = await api.get('/transactions/categories', {
        params: source && source !== 'All' ? {source} : {},
      });
      return {success: true, categories: response.data?.categories || []};
    } catch (error) {
      return {success: false, error};
    }
  }, []);

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
      const response = await api.post('/transactions', toApiPayload(transactionData));
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

  const deleteTransaction = async transactionId => {
    try {
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
