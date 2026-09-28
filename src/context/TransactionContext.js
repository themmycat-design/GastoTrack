import React, { createContext, useState, useEffect, useContext } from 'react';
import api from '../services/api';
import { AuthContext } from './AuthContext'; 

export const TransactionContext = createContext();

export const TransactionProvider = ({ children }) => {
  const [transactions, setTransactions] = useState([]);
  const [isLoading, setIsLoading] = useState(false);
  const { userToken } = useContext(AuthContext);

  // Kumuha ng transactions mula sa Laravel
  const fetchTransactions = async () => {
    if (!userToken) return;
    setIsLoading(true);
    try {
      const response = await api.get('/transactions');
      setTransactions(response.data);
    } catch (error) {
      console.log('Error fetching transactions:', error);
    } finally {
      setIsLoading(false);
    }
  };

  // Mag-save ng bagong transaction
  const addTransaction = async (transactionData) => {
    try {
      const response = await api.post('/transactions', transactionData);
      // I-add agad ang bagong data sa state para mag-update ang UI nang walang reload
      setTransactions([response.data, ...transactions]); 
      return { success: true };
    } catch (error) {
      console.log('Error adding transaction:', error);
      return { success: false, error: error.message };
    }
  };

  // I-load ang data kapag nag-login ang user
  useEffect(() => {
    console.log('[TransactionContext] userToken changed:', userToken ? 'Token exists' : 'No token');
    if (userToken) {
      console.log('[TransactionContext] Fetching transactions...');
      fetchTransactions();
    }
  }, [userToken]);

  return (
    <TransactionContext.Provider value={{ 
      transactions, 
      isLoading, 
      fetchTransactions, 
      addTransaction 
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