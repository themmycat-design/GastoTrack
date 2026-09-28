import React, { createContext, useContext, useState } from 'react';

const TransactionContext = createContext();

// Income categories
export const INCOME_CATEGORIES = ['Sales', 'Delivery', 'Catering', 'Others'];

// Expense categories
export const EXPENSE_CATEGORIES = [
  'Ingredients',
  'Packaging',
  'Utilities',
  'Rent',
  'Salaries',
  'Equipment',
  'Supplies',
  'Others',
];

// Source options
export const TRANSACTION_SOURCES = ['Cash', 'GCash', 'Maya', 'GrabPay', 'ShopeePay'];

// Entry methods
export const ENTRY_METHODS = {
  MANUAL: 'Manual',
  NOTIFICATION: 'Notification Capture',
  OCR: 'Receipt Scan',
};

// Default sample transactions for testing
const DEFAULT_TRANSACTIONS = [
  {
    id: '1',
    type: 'Income',
    amount: 850,
    source: 'GCash',
    category: 'Sales',
    date: '2026-09-24',
    notes: 'Brown Sugar Milk Tea sales',
    entryMethod: ENTRY_METHODS.NOTIFICATION,
    recordedBy: 'Staff User',
    timestamp: Date.now(),
  },
  {
    id: '2',
    type: 'Expense',
    amount: 1200,
    source: 'Cash',
    category: 'Ingredients',
    date: '2026-09-23',
    notes: 'Fresh milk and tapioca pearls restock',
    entryMethod: ENTRY_METHODS.MANUAL,
    recordedBy: 'Staff User',
    timestamp: Date.now() - 86400000,
  },
  {
    id: '3',
    type: 'Income',
    amount: 450,
    source: 'Cash',
    category: 'Sales',
    date: '2026-09-23',
    notes: 'Walk-in customers',
    entryMethod: ENTRY_METHODS.MANUAL,
    recordedBy: 'Staff User',
    timestamp: Date.now() - 86400000,
  },
  {
    id: '4',
    type: 'Income',
    amount: 950,
    source: 'Maya',
    category: 'Delivery',
    date: '2026-09-22',
    notes: 'Delivery orders',
    entryMethod: ENTRY_METHODS.NOTIFICATION,
    recordedBy: 'Staff User',
    timestamp: Date.now() - 172800000,
  },
  {
    id: '5',
    type: 'Expense',
    amount: 500,
    source: 'Cash',
    category: 'Utilities',
    date: '2026-09-22',
    notes: 'Electricity bill',
    entryMethod: ENTRY_METHODS.MANUAL,
    recordedBy: 'Staff User',
    timestamp: Date.now() - 172800000,
  },
];

export const TransactionProvider = ({ children }) => {
  const [transactions, setTransactions] = useState(DEFAULT_TRANSACTIONS);

  // Add a new transaction
  const addTransaction = transaction => {
    const newTransaction = {
      ...transaction,
      id: Date.now().toString(),
      timestamp: Date.now(),
    };
    setTransactions(prev => [newTransaction, ...prev]);
    return newTransaction;
  };

  // Update an existing transaction
  const updateTransaction = updated => {
    setTransactions(prev =>
      prev.map(t => (t.id === updated.id ? { ...updated, timestamp: t.timestamp } : t))
    );
  };

  // Delete a transaction
  const deleteTransaction = id => {
    setTransactions(prev => prev.filter(t => t.id !== id));
  };

  // Get total income
  const getTotalIncome = () => {
    return transactions
      .filter(t => t.type === 'Income')
      .reduce((sum, t) => sum + t.amount, 0);
  };

  // Get total expenses
  const getTotalExpenses = () => {
    return transactions
      .filter(t => t.type === 'Expense')
      .reduce((sum, t) => sum + t.amount, 0);
  };

  // Get net balance
  const getNetBalance = () => {
    return getTotalIncome() - getTotalExpenses();
  };

  // Get transactions by type
  const getTransactionsByType = type => {
    return transactions.filter(t => t.type === type);
  };

  // Get transactions by date range
  const getTransactionsByDateRange = (startDate, endDate) => {
    return transactions.filter(t => {
      const transDate = new Date(t.date);
      return transDate >= new Date(startDate) && transDate <= new Date(endDate);
    });
  };

  // Get today's transactions
  const getTodayTransactions = () => {
    const today = new Date().toISOString().split('T')[0];
    return transactions.filter(t => t.date === today);
  };

  // Get this week's transactions
  const getThisWeekTransactions = () => {
    const today = new Date();
    const weekAgo = new Date(today);
    weekAgo.setDate(weekAgo.getDate() - 7);
    return transactions.filter(t => new Date(t.date) >= weekAgo);
  };

  // Get this month's transactions
  const getThisMonthTransactions = () => {
    const today = new Date();
    const year = today.getFullYear();
    const month = today.getMonth();
    return transactions.filter(t => {
      const transDate = new Date(t.date);
      return transDate.getFullYear() === year && transDate.getMonth() === month;
    });
  };

  // Get sales today (for mini cards)
  const getSalesToday = () => {
    const today = new Date().toISOString().split('T')[0];
    return transactions
      .filter(t => t.type === 'Income' && t.date === today)
      .reduce((sum, t) => sum + t.amount, 0);
  };

  // Get expenses today (for mini cards)
  const getExpensesToday = () => {
    const today = new Date().toISOString().split('T')[0];
    return transactions
      .filter(t => t.type === 'Expense' && t.date === today)
      .reduce((sum, t) => sum + t.amount, 0);
  };

  // Get revenue for the current week.
  const getRevenueThisWeek = () => {
    const thisWeek = getThisWeekTransactions();
    return thisWeek
      .filter(t => t.type === 'Income')
      .reduce((sum, t) => sum + t.amount, 0);
  };

  // Get expenses for the current week.
  const getExpensesThisWeek = () => {
    const thisWeek = getThisWeekTransactions();
    return thisWeek
      .filter(t => t.type === 'Expense')
      .reduce((sum, t) => sum + t.amount, 0);
  };

  return (
    <TransactionContext.Provider
      value={{
        transactions,
        addTransaction,
        updateTransaction,
        deleteTransaction,
        getTotalIncome,
        getTotalExpenses,
        getNetBalance,
        getTransactionsByType,
        getTransactionsByDateRange,
        getTodayTransactions,
        getThisWeekTransactions,
        getThisMonthTransactions,
        getSalesToday,
        getExpensesToday,
        getRevenueThisWeek,
        getExpensesThisWeek,
        INCOME_CATEGORIES,
        EXPENSE_CATEGORIES,
        TRANSACTION_SOURCES,
        ENTRY_METHODS,
      }}>
      {children}
    </TransactionContext.Provider>
  );
};

export const useTransactions = () => useContext(TransactionContext);
