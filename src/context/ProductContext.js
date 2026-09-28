import React, { createContext, useContext, useState } from 'react';

const ProductContext = createContext();

const DEFAULT_PRODUCTS = [
  {
    id: '1',
    name: 'Brown Sugar Milk Tea',
    category: 'Milk Tea',
    price: 1,
    description:
      'A rich and creamy milk tea made with brown sugar syrup, fresh milk, and chewy tapioca pearls. A customer favorite!',
    emoji: '🧋',
    ingredients: [
      { stockId: '1', name: 'Fresh milk', quantity: 250, unit: 'mL' },
      { stockId: '2', name: 'Brown sugar syrup', quantity: 30, unit: 'mL' },
      { stockId: '3', name: 'Tapioca pearls', quantity: 50, unit: 'g' },
      { stockId: '4', name: 'Black tea', quantity: 150, unit: 'mL' },
    ],
  },
  {
    id: '2',
    name: 'Matcha Latte',
    category: 'Milk Tea',
    price: 1,
    description:
      'A smooth and earthy matcha latte made with premium matcha powder and fresh milk. Perfect for matcha lovers.',
    emoji: '🍵',
    ingredients: [
      { stockId: '1', name: 'Fresh milk', quantity: 300, unit: 'mL' },
      { stockId: '5', name: 'Matcha powder', quantity: 10, unit: 'g' },
      { stockId: '8', name: 'Sugar', quantity: 20, unit: 'g' },
    ],
  },
  {
    id: '3',
    name: 'Strawberry Fruit Tea',
    category: 'Fruit Tea',
    price: 1,
    description:
      'A refreshing fruit tea bursting with strawberry flavor. Light, sweet, and perfect for hot days.',
    emoji: '🥤',
    ingredients: [
      { stockId: '4', name: 'Black tea', quantity: 200, unit: 'mL' },
      { stockId: '6', name: 'Strawberry syrup', quantity: 40, unit: 'mL' },
      { stockId: '8', name: 'Sugar', quantity: 15, unit: 'g' },
    ],
  },
  {
    id: '4',
    name: 'Creamy Coffee',
    category: 'Coffee',
    price: 1,
    description:
      'A rich and creamy coffee drink made with our signature coffee base and fresh milk. A great pick-me-up!',
    emoji: '☕',
    ingredients: [
      { stockId: '7', name: 'Coffee base', quantity: 150, unit: 'mL' },
      { stockId: '1', name: 'Fresh milk', quantity: 100, unit: 'mL' },
      { stockId: '8', name: 'Sugar', quantity: 20, unit: 'g' },
    ],
  },
];

const PRODUCT_CATEGORIES = [
  'Milk Tea', 'Fruit Tea', 'Coffee',
  'Smoothie', 'Juice', 'Food', 'Snacks', 'Others',
];

const PRODUCT_EMOJIS = [
  '🧋', '🍵', '🥤', '☕', '🍹', '🧃', '🍔',
  '🍕', '🍜', '🍱', '🧆', '🍩', '🍪', '🍰',
];

export const ProductProvider = ({ children }) => {
  const [products, setProducts] = useState(DEFAULT_PRODUCTS);

  const addProduct = product => {
    setProducts(prev => [product, ...prev]);
  };

  const updateProduct = updated => {
    setProducts(prev =>
      prev.map(p => (p.id === updated.id ? updated : p))
    );
  };

  const deleteProduct = id => {
    setProducts(prev => prev.filter(p => p.id !== id));
  };

  return (
    <ProductContext.Provider value={{
      products,
      addProduct,
      updateProduct,
      deleteProduct,
      PRODUCT_CATEGORIES,
      PRODUCT_EMOJIS,
    }}>
      {children}
    </ProductContext.Provider>
  );
};

export const useProducts = () => useContext(ProductContext);