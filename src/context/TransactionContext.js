import React, { createContext, useState, useEffect, useContext, useCallback } from 'react';
import api from '../services/api';
import { AuthContext } from './AuthContext'; 

export const TransactionContext = createContext();

// Transaction constants
export const INCOME_CATEGORIES = ['Sales', 'Tips', 'Other Income'];
export const EXPENSE_CATEGORIES = ['Ingredients', 'Rent', 'Salaries', 'Utilities', 'Supplies', 'Other Expense'];
export const TRANSACTION_SOURCES = ['Cash', 'GCash', 'Maya', 'Bank Transfer', 'Credit Card'];

const titleCaseType = type => type?.toLowerCase() === 'income' ? 'Income' : 'Expense';
const normalizeTransaction = transaction => ({
  ...transaction,
  type: titleCaseType(transaction.type),
  date: transaction.transaction_date || transaction.date,
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
  entry_method: String(transaction.entryMethod || transaction.entry_method || 'manual')
    .toLowerCase()
    .replace(/\s+/g, '_'),
  metadata: transaction.metadata || undefined,
});

export const TransactionProvider = ({ children }) => {
  const [transactions, setTransactions] = useState([]);
  const [isLoading, setIsLoading] = useState(false);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [pagination, setPagination] = useState({currentPage: 0, lastPage: 1, total: 0});
  const { userToken } = useContext(AuthContext);

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
    } catch (error) {
      console.log('Error fetching transactions:', error);
    } finally {
      append ? setIsLoadingMore(false) : setIsLoading(false);
    }
  }, [userToken]);

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
      console.log('Error adding transaction:', error);
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
      console.log('Error updating transaction:', error.response?.data || error.message);
      return { success: false, error };
    }
  };

  // I-load ang data kapag nag-login ang user
  useEffect(() => {
    console.log('[TransactionContext] userToken changed:', userToken ? 'Token exists' : 'No token');
    if (userToken) {
      console.log('[TransactionContext] Fetching transactions...');
      fetchTransactions();
    } else {
      setTransactions([]);
      setPagination({currentPage: 0, lastPage: 1, total: 0});
    }
  }, [userToken, fetchTransactions]);

  return (
    <TransactionContext.Provider value={{ 
      transactions, 
      isLoading, 
      isLoadingMore,
      hasMoreTransactions,
      transactionTotal: pagination.total,
      fetchTransactions, 
      loadMoreTransactions,
      addTransaction,
      updateTransaction,
      INCOME_CATEGORIES,
      EXPENSE_CATEGORIES,
      TRANSACTION_SOURCES
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
