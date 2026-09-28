import React, { createContext, useContext, useState } from 'react';

const StockContext = createContext();

const DEFAULT_STOCK = [
  { id: '1', name: 'Fresh milk', quantity: 5000, unit: 'mL', threshold: 1000 },
  { id: '2', name: 'Brown sugar syrup', quantity: 800, unit: 'mL', threshold: 200 },
  { id: '3', name: 'Tapioca pearls', quantity: 0, unit: 'g', threshold: 300 }, // OUT OF STOCK - Will trigger alert
  { id: '4', name: 'Black tea', quantity: 3000, unit: 'mL', threshold: 500 },
  { id: '5', name: 'Matcha powder', quantity: 80, unit: 'g', threshold: 100 }, // LOW STOCK - Will trigger alert
  { id: '6', name: 'Strawberry syrup', quantity: 600, unit: 'mL', threshold: 200 },
  { id: '7', name: 'Coffee base', quantity: 1000, unit: 'mL', threshold: 300 },
  { id: '8', name: 'Sugar', quantity: 2000, unit: 'g', threshold: 500 },
];

export const StockProvider = ({ children }) => {
  const [stockItems, setStockItems] = useState(DEFAULT_STOCK);

  const getStatus = (quantity, threshold) => {
    if (quantity <= 0) return 'Out';
    if (quantity <= threshold) return 'Low';
    return 'OK';
  };

  const deductIngredients = (ingredients) => {
    setStockItems(prev =>
      prev.map(item => {
        const ingr = ingredients.find(i => i.stockId === item.id);
        if (!ingr) return item;
        return {
          ...item,
          quantity: Math.max(0, item.quantity - ingr.quantity),
        };
      })
    );
  };

  const addStockItem = item => {
    setStockItems(prev => [...prev, item]);
  };

  const updateStockItem = updated => {
    setStockItems(prev =>
      prev.map(i => (i.id === updated.id ? updated : i))
    );
  };

  const deleteStockItem = id => {
    setStockItems(prev => prev.filter(i => i.id !== id));
  };

  return (
    <StockContext.Provider value={{
      stockItems,
      getStatus,
      deductIngredients,
      addStockItem,
      updateStockItem,
      deleteStockItem,
    }}>
      {children}
    </StockContext.Provider>
  );
};

export const useStock = () => useContext(StockContext);