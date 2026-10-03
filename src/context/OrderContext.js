import React, {createContext, useCallback, useContext, useEffect, useState} from 'react';
import api from '../services/api';
import {AuthContext} from './AuthContext';
import {useTransactions} from './TransactionContext';
import {useStock} from './StockContext';
import {useProducts} from './ProductContext';

const OrderContext = createContext();

const normalize = order => ({
  ...order,
  orderNumber: order.order_number || order.orderNumber,
  customerName: order.customer_name || order.customerName,
  customerPhone: order.customer_phone || order.customerPhone,
  paymentMethod: order.payment_method || order.paymentMethod,
  createdAt: order.created_at || order.createdAt,
  completedAt: order.completed_at || order.completedAt,
  cancelReason: order.cancel_reason || order.cancelReason,
  total: Number(order.total || 0),
  subtotal: Number(order.subtotal || 0),
});
const normalizePagination = (meta, page, count) => ({
  currentPage: Number(meta?.current_page ?? page),
  lastPage: Number(meta?.last_page ?? page),
  total: Number(meta?.total ?? count),
});

export const OrderProvider = ({children}) => {
  const [orders, setOrders] = useState([]);
  const [isLoading, setIsLoading] = useState(false);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [pagination, setPagination] = useState({currentPage: 0, lastPage: 1, total: 0});
  const {userToken} = useContext(AuthContext);
  const {fetchTransactions} = useTransactions();
  const {fetchStock} = useStock();
  const {fetchProducts} = useProducts();

  const fetchOrders = useCallback(async ({page = 1, append = false} = {}) => {
    if (!userToken) return;
    append ? setIsLoadingMore(true) : setIsLoading(true);
    try {
      const response = await api.get('/orders', {params: {page}});
      const normalized = (response.data.orders || []).map(normalize);
      setOrders(current => append
        ? [...current, ...normalized.filter(record => !current.some(item => item.id === record.id))]
        : normalized,
      );
      setPagination(normalizePagination(response.data?.meta, page, normalized.length));
    } catch (error) {
      console.log('Error fetching orders:', error.response?.data || error.message);
    } finally { append ? setIsLoadingMore(false) : setIsLoading(false); }
  }, [userToken]);

  const hasMoreOrders = pagination.currentPage < pagination.lastPage;
  const loadMoreOrders = useCallback(() => {
    if (!isLoading && !isLoadingMore && pagination.currentPage < pagination.lastPage) {
      return fetchOrders({page: pagination.currentPage + 1, append: true});
    }
    return Promise.resolve();
  }, [fetchOrders, isLoading, isLoadingMore, pagination]);

  useEffect(() => {
    if (userToken) {
      fetchOrders();
    } else {
      setOrders([]);
      setPagination({currentPage: 0, lastPage: 1, total: 0});
    }
  }, [fetchOrders, userToken]);

  const createOrder = async orderData => {
    const payload = {
      items: orderData.items.map(item => ({product_id: item.product?.id || item.product_id, quantity: item.quantity})),
      customer_name: orderData.customerName,
      customer_phone: orderData.customerPhone,
      payment_method: String(orderData.paymentMethod || 'cash').toLowerCase(),
      notes: orderData.notes,
    };
    const response = await api.post('/orders', payload);
    const created = normalize(response.data.order);
    setOrders(current => [created, ...current]);
    setPagination(current => ({...current, total: current.total + 1}));
    await Promise.all([fetchTransactions(), fetchStock(), fetchProducts()]);
    return created;
  };

  return <OrderContext.Provider value={{
    orders,
    orderTotal: pagination.total,
    isLoading,
    isLoadingMore,
    hasMoreOrders,
    fetchOrders,
    loadMoreOrders,
    createOrder,
  }}>{children}</OrderContext.Provider>;
};

export const useOrders = () => useContext(OrderContext);
