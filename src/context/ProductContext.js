import React, { createContext, useState, useEffect, useContext } from 'react';
import api from '../services/api';
import { AuthContext } from './AuthContext';

export const ProductContext = createContext();

export const ProductProvider = ({ children }) => {
  const [products, setProducts] = useState([]);
  const [isLoading, setIsLoading] = useState(false);
  const { userToken } = useContext(AuthContext);

  const fetchProducts = async () => {
    if (!userToken) return;
    setIsLoading(true);
    try {
      const response = await api.get('/products');
      setProducts(response.data);
    } catch (error) {
      console.log('Error fetching products:', error);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    console.log('[ProductContext] userToken changed:', userToken ? 'Token exists' : 'No token');
    if (userToken) {
      console.log('[ProductContext] Fetching products...');
      fetchProducts();
    }
  }, [userToken]);

  return (
    <ProductContext.Provider value={{ products, isLoading, fetchProducts }}>
      {children}
    </ProductContext.Provider>
  );
};

// Export useProducts hook
export const useProducts = () => {
  const context = useContext(ProductContext);
  if (!context) {
    throw new Error('useProducts must be used within a ProductProvider');
  }
  return context;
};