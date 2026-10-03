import React, { createContext, useState, useEffect, useContext, useCallback } from 'react';
import api, {API_URL} from '../services/api';
import { AuthContext } from './AuthContext';

export const ProductContext = createContext();
export const PRODUCT_CATEGORIES = ['Milktea', 'Coffee', 'Food', 'Drinks', 'Other'];

const normalizeProduct = product => ({
  ...product,
  price: Number(product.price || 0),
  image: product.image
    ? `${API_URL}/product-images/${product.id}?v=${encodeURIComponent(product.updated_at || '')}`
    : null,
  emoji: product.emoji || '☕',
  ingredients: (product.product_ingredients || product.ingredients || []).map(ingredient => ({
    ...ingredient,
    stockId: ingredient.stock_item_id || ingredient.stock_id || ingredient.stockId,
    name: ingredient.name || ingredient.stock_item?.name || ingredient.stockItem?.name,
  })),
});

const extractProducts = data => (
  data?.products || (Array.isArray(data) ? data : [])
).map(normalizeProduct);

const toApiPayload = product => ({
  name: product.name?.trim(),
  category: product.category,
  price: Number(product.price),
  description: product.description?.trim() || null,
  emoji: product.emoji?.trim() || '☕',
  is_available: product.isAvailable,
  ingredients: (product.ingredients || []).map(ingredient => ({
    stock_id: ingredient.stockId,
    quantity: Number(ingredient.quantity),
  })),
});

const toMultipartPayload = (product, method) => {
  const payload = toApiPayload(product);
  const form = new FormData();
  if (method) form.append('_method', method);
  form.append('name', payload.name);
  form.append('category', payload.category);
  form.append('price', String(payload.price));
  if (payload.description) form.append('description', payload.description);
  form.append('emoji', payload.emoji);
  form.append('is_available', payload.is_available ? '1' : '0');
  form.append('replace_ingredients', '1');
  payload.ingredients.forEach((ingredient, index) => {
    form.append(`ingredients[${index}][stock_id]`, String(ingredient.stock_id));
    form.append(`ingredients[${index}][quantity]`, String(ingredient.quantity));
  });
  form.append('image', {
    uri: product.imageAsset.uri,
    type: product.imageAsset.type || 'image/jpeg',
    name: product.imageAsset.fileName || `product-${Date.now()}.jpg`,
  });
  return form;
};

export const ProductProvider = ({ children }) => {
  const [products, setProducts] = useState([]);
  const [isLoading, setIsLoading] = useState(false);
  const { userToken } = useContext(AuthContext);

  const fetchProducts = useCallback(async () => {
    if (!userToken) return;
    setIsLoading(true);
    try {
      const response = await api.get('/products');
      setProducts(extractProducts(response.data));
    } catch (error) {
      console.log('Error fetching products:', error);
    } finally {
      setIsLoading(false);
    }
  }, [userToken]);

  useEffect(() => {
    console.log('[ProductContext] userToken changed:', userToken ? 'Token exists' : 'No token');
    if (userToken) {
      console.log('[ProductContext] Fetching products...');
      fetchProducts();
    } else {
      setProducts([]);
    }
  }, [userToken, fetchProducts]);

  const createProduct = async productData => {
    const hasImage = Boolean(productData.imageAsset?.uri);
    const response = await api.post(
      '/products',
      hasImage ? toMultipartPayload(productData) : toApiPayload(productData),
      hasImage ? {headers: {'Content-Type': 'multipart/form-data'}} : undefined,
    );
    const product = normalizeProduct(response.data.product);
    setProducts(current => [...current, product]);
    return product;
  };

  const updateProduct = async (id, productData) => {
    const hasImage = Boolean(productData.imageAsset?.uri);
    const response = hasImage
      ? await api.post(`/products/${id}`, toMultipartPayload(productData, 'PUT'), {
        headers: {'Content-Type': 'multipart/form-data'},
      })
      : await api.put(`/products/${id}`, toApiPayload(productData));
    const product = normalizeProduct(response.data.product);
    setProducts(current => current.map(item => item.id === product.id ? product : item));
    return product;
  };

  const deleteProduct = async id => {
    await api.delete(`/products/${id}`);
    setProducts(current => current.filter(item => item.id !== id));
  };

  return (
    <ProductContext.Provider value={{
      products,
      isLoading,
      fetchProducts,
      createProduct,
      updateProduct,
      deleteProduct,
      PRODUCT_CATEGORIES,
    }}>
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
