import React, { createContext, useState, useEffect, useContext, useCallback, useMemo } from 'react';
import api, {API_URL} from '../services/api';
import { AuthContext } from './AuthContext';
import {getApiErrorMessage} from '../services/apiErrors';
import {productPhotoUpload} from '../utils/productPhoto';
import {getStaffSnapshot, recordStaffAction, saveStaffSnapshot, subscribeStaffOffline} from '../services/staffOffline';
import {overlayProductActions, readPendingActions} from '../services/offlineRecords';

export const ProductContext = createContext();

const normalizeProduct = product => ({
  ...product,
  approvalPending: Boolean(product.approvalPending || product.approval_pending),
  price: Number(product.price || 0),
  image: product.image
    ? `${API_URL}/product-images/${product.id}?v=${encodeURIComponent(product.updated_at || '')}`
    : null,
  emoji: product.emoji || '☕',
  ingredients: (product.product_ingredients || product.ingredients || []).map(ingredient => ({
    ...ingredient,
    stockId: ingredient.stock_item_id || ingredient.stock_id || ingredient.stockId,
    name: ingredient.name || ingredient.stock_item?.name || ingredient.stockItem?.name,
    unit: ingredient.unit || ingredient.stock_item?.unit || ingredient.stockItem?.unit,
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
  form.append('description', payload.description || '');
  form.append('emoji', payload.emoji);
  form.append('is_available', payload.is_available ? '1' : '0');
  form.append('replace_ingredients', '1');
  payload.ingredients.forEach((ingredient, index) => {
    form.append(`ingredients[${index}][stock_id]`, String(ingredient.stock_id));
    form.append(`ingredients[${index}][quantity]`, String(ingredient.quantity));
  });
  form.append('image', productPhotoUpload(product.imageAsset));
  return form;
};

export const ProductProvider = ({ children }) => {
  const [serverProducts, setProducts] = useState([]);
  const [localActions, setLocalActions] = useState([]);
  const [isLoading, setIsLoading] = useState(false);
  const [loadError, setLoadError] = useState(null);
  const {userToken, user, business} = useContext(AuthContext);
  const offlineAccount = useMemo(() => user?.role === 'staff' && user?.id && (user?.business_id || business?.id)
    ? {userId: user.id, businessId: user.business_id || business.id} : null,
  [user?.role, user?.id, user?.business_id, business?.id]);
  const products = useMemo(() => overlayProductActions(serverProducts, localActions), [serverProducts, localActions]);
  const refreshLocalActions = useCallback(async () => {
    setLocalActions(offlineAccount ? await readPendingActions(offlineAccount) : []);
  }, [offlineAccount]);

  const fetchProducts = useCallback(async () => {
    if (!userToken) return;
    setIsLoading(true);
    try {
      const response = await api.get('/products');
      const fetched = extractProducts(response.data);
      setProducts(fetched);
      if (offlineAccount) saveStaffSnapshot(offlineAccount, 'products', fetched).catch(() => {});
      setLoadError(null);
      return {success: true};
    } catch (error) {
      if (offlineAccount) {
        try {
          const cached = await getStaffSnapshot(offlineAccount, 'products');
          if (cached) setProducts(cached);
        } catch {}
      }
      setLoadError(getApiErrorMessage(error));
      return {success: false, error};
    } finally {
      setIsLoading(false);
    }
  }, [userToken, offlineAccount]);

  useEffect(() => {
    if (userToken) {
      fetchProducts();
    } else {
      setProducts([]);
      setLoadError(null);
    }
  }, [userToken, fetchProducts]);

  useEffect(() => subscribeStaffOffline(event => {
    if (['queued', 'synced', 'rejected'].includes(event)) refreshLocalActions();
    if (event === 'synced' && userToken) fetchProducts();
  }), [fetchProducts, refreshLocalActions, userToken]);

  useEffect(() => {refreshLocalActions();}, [refreshLocalActions]);

  const createProduct = async productData => {
    const hasImage = Boolean(productData.imageAsset?.uri);
    if (offlineAccount && !hasImage) {
      const result = await recordStaffAction(offlineAccount, 'product_create', {data: toApiPayload(productData)});
      await refreshLocalActions();
      if (!result.synced) return normalizeProduct({...productData, id: `local:${result.clientId}`, offlinePending: true});
      const product = normalizeProduct(result.data.product);
      setProducts(current => [...current, product]);
      return product;
    }
    const response = await api.post(
      '/products',
      hasImage ? toMultipartPayload(productData) : toApiPayload(productData),
      hasImage ? {headers: {'Content-Type': 'multipart/form-data'}, timeout: 60000} : undefined,
    );
    const product = normalizeProduct(response.data.product);
    setProducts(current => [...current, product]);
    return product;
  };

  const updateProduct = async (id, productData) => {
    const hasImage = Boolean(productData.imageAsset?.uri);
    const original = serverProducts.find(item => Number(item.id) === Number(id));
    if (original?.approvalPending) throw new Error('This product already has an edit awaiting owner review.');
    if (offlineAccount && !hasImage) {
      if (!original?.updated_at || !Number.isInteger(Number(original.revision))) throw new Error('Refresh products online once before editing this product offline.');
      if (localActions.some(action => action.status === 'pending' && ['product_update', 'product_delete'].includes(action.kind)
        && Number(action.payload.target_id) === Number(id))) {
        throw new Error('This product already has an edit waiting to sync.');
      }
      const result = await recordStaffAction(offlineAccount, 'product_update', {
        target_id: id, expected_updated_at: original.updated_at,
        expected_revision: Number(original.revision), data: toApiPayload(productData),
      });
      await refreshLocalActions();
      if (!result.synced) return {...original, offlinePending: true};
      const product = normalizeProduct({...result.data.product, approvalPending: result.data.approval_pending});
      setProducts(current => current.map(item => item.id === product.id ? product : item));
      return product;
    }
    const response = hasImage
      ? await api.post(`/products/${id}`, toMultipartPayload(productData, 'PUT'), {
        headers: {'Content-Type': 'multipart/form-data'},
        timeout: 60000,
      })
      : await api.put(`/products/${id}`, toApiPayload(productData));
    const product = normalizeProduct({...response.data.product, approvalPending: response.data.approval_pending});
    setProducts(current => current.map(item => item.id === product.id ? product : item));
    return product;
  };

  const deleteProduct = async id => {
    if (offlineAccount) {
      const original = serverProducts.find(item => Number(item.id) === Number(id));
      if (!original?.updated_at || !Number.isInteger(Number(original.revision))) throw new Error('Refresh products online once before deleting this product offline.');
      if (localActions.some(action => action.status === 'pending' && ['product_update', 'product_delete'].includes(action.kind)
        && Number(action.payload.target_id) === Number(id))) {
        throw new Error('This product already has a change waiting to sync.');
      }
      const result = await recordStaffAction(offlineAccount, 'product_delete', {
        target_id: id, expected_updated_at: original.updated_at,
        expected_revision: Number(original.revision), data: {},
      });
      await refreshLocalActions();
      if (!result.synced) return {offlinePending: true};
      setProducts(current => current.filter(item => item.id !== id));
      return;
    }
    await api.delete(`/products/${id}`);
    setProducts(current => current.filter(item => item.id !== id));
  };

  return (
    <ProductContext.Provider value={{
      products,
      isLoading,
      loadError,
      fetchProducts,
      createProduct,
      updateProduct,
      deleteProduct,
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
