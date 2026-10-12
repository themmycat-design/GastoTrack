import React, {createContext, useCallback, useContext, useEffect, useRef, useState} from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import api from '../services/api';
import {AuthContext} from './AuthContext';
import {useTransactions} from './TransactionContext';
import {useStock} from './StockContext';
import {useProducts} from './ProductContext';
import {createOrderIdempotencyKey, orderRequestSignature} from '../config/orderIdempotency';
import {getApiErrorMessage} from '../services/apiErrors';
import {getEffectiveOrderPaymentStatus} from '../utils/orderHistoryStatus';
import {staffDayKey, useStaffDay} from '../utils/useStaffDay';

const OrderContext = createContext();
const CART_STORAGE_PREFIX = 'gastotrack.cart.';
const ORDER_ATTEMPT_PREFIX = 'gastotrack.order-attempt.';
const orderRetryStorageError = () => {
  const error = new Error('GastoTrack could not save or read this order retry identifier. No order was sent; check available app storage before trying again.');
  error.code = 'ORDER_RETRY_STORAGE_UNAVAILABLE';
  return error;
};

const normalize = order => ({
  ...order,
  orderNumber: order.order_number || order.orderNumber,
  customerName: order.customer_name || order.customerName,
  customerPhone: order.customer_phone || order.customerPhone,
  paymentMethod: order.payment_method || order.paymentMethod,
  paymentStatus: getEffectiveOrderPaymentStatus(order),
  paymentCurrency: order.payment_currency || order.paymentCurrency || 'PHP',
  paymentReference: order.payment_reference || order.paymentReference,
  detectedPaymentAmount: order.detected_payment_amount ?? order.detectedPaymentAmount,
  refundReason: order.refund_reason || order.refundReason,
  refundReference: order.refund_reference || order.refundReference,
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

const buildOrderPayload = orderData => {
  if (!Array.isArray(orderData?.items) || orderData.items.length === 0) {
    throw new Error('Add at least one product before placing the order.');
  }

  const items = orderData.items.map(item => ({
    product_id: Number(item?.product?.id ?? item?.product_id),
    quantity: Number(item?.quantity),
  }));
  if (items.some(item => !Number.isInteger(item.product_id) || item.product_id < 1
    || !Number.isInteger(item.quantity) || item.quantity < 1)) {
    throw new Error('One or more cart items are invalid. Remove them and add the products again.');
  }

  return {
    items,
    customer_name: orderData.customerName || 'Walk-in Customer',
    customer_phone: orderData.customerPhone || null,
    payment_method: String(orderData.paymentMethod || '').toLowerCase(),
    notes: typeof orderData.notes === 'string' ? orderData.notes.trim() || null : null,
  };
};

export const OrderProvider = ({children}) => {
  const [orders, setOrders] = useState([]);
  const [cart, setCartState] = useState([]);
  const [cartHydrated, setCartHydrated] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [loadError, setLoadError] = useState(null);
  const [pagination, setPagination] = useState({currentPage: 0, lastPage: 1, total: 0});
  const {userToken, user} = useContext(AuthContext);
  const {fetchTransactions} = useTransactions();
  const {fetchStock} = useStock();
  const {fetchProducts} = useProducts();
  const activeUserId = useRef(null);
  const activeSubmission = useRef(null);
  const ordersRequestId = useRef(0);
  const day = useStaffDay();
  const activeCartDay = useRef(day);

  const setCart = useCallback(nextCart => {
    setCartState(current => typeof nextCart === 'function' ? nextCart(current) : nextCart);
  }, []);

  const cartStorageKey = user?.id ? `${CART_STORAGE_PREFIX}${user.id}` : null;
  const attemptStorageKey = user?.id ? `${ORDER_ATTEMPT_PREFIX}${user.id}` : null;

  useEffect(() => {
    if (!userToken || !user?.id) {
      ordersRequestId.current += 1;
      const previousUserId = activeUserId.current;
      activeUserId.current = null;
      setCartHydrated(false);
      setCartState([]);
      setOrders([]);
      setLoadError(null);
      setPagination({currentPage: 0, lastPage: 1, total: 0});
      if (previousUserId) {
        AsyncStorage.removeMany([
          `${CART_STORAGE_PREFIX}${previousUserId}`,
          `${ORDER_ATTEMPT_PREFIX}${previousUserId}`,
        ]).catch(error => console.warn('Saved cart data could not be cleared:', error?.message));
      }
      return undefined;
    }

    const userId = String(user.id);
    activeUserId.current = userId;
    let mounted = true;
    setCartHydrated(false);
    setCartState([]);
    Promise.all([
      AsyncStorage.getItem(`${CART_STORAGE_PREFIX}${userId}`),
      AsyncStorage.getItem(`${ORDER_ATTEMPT_PREFIX}${userId}`),
    ])
      .then(([value, attemptValue]) => {
        if (!mounted || !value) return;
        const restored = JSON.parse(value);
        const savedDay = Array.isArray(restored) ? null : restored?.day;
        const items = Array.isArray(restored) ? restored : restored?.items;
        const attempt = attemptValue ? JSON.parse(attemptValue) : null;
        if (savedDay !== staffDayKey() && !attempt?.client_id) return;
        if (Array.isArray(items)) {
          setCartState(items.filter(item => (
            item?.product
            && Number.isInteger(Number(item.product.id))
            && Number(item.product.id) > 0
            && Number.isInteger(Number(item.quantity))
            && Number(item.quantity) > 0
          )).map(item => ({
            ...item,
            product: {...item.product, price: Number(item.product.price || 0)},
            quantity: Number(item.quantity),
          })));
        }
      })
      .catch(error => console.warn('Saved cart could not be restored:', error?.message))
      .finally(() => {
        if (mounted) setCartHydrated(true);
      });

    return () => {
      mounted = false;
    };
  }, [userToken, user?.id]);

  useEffect(() => {
    if (activeCartDay.current === day) return;
    if (!userToken || !cartStorageKey || !attemptStorageKey) {
      activeCartDay.current = day;
      return;
    }
    let active = true;
    setCartHydrated(false);
    AsyncStorage.getItem(attemptStorageKey).then(value => {
      if (!active) return;
      const attempt = value ? JSON.parse(value) : null;
      // An ambiguous order submission must remain retryable with its original
      // client_id; clearing it could create a duplicate sale after a timeout.
      if (!activeSubmission.current && !attempt?.client_id) {
        setCartState([]);
      }
      activeCartDay.current = day;
    }).catch(error => {
      console.warn('Could not check the saved order attempt during day change:', error?.message);
    }).finally(() => {
      if (active) setCartHydrated(true);
    });
    return () => { active = false; };
  }, [attemptStorageKey, cartStorageKey, day, userToken]);

  useEffect(() => {
    if (!userToken || !cartStorageKey || !cartHydrated || activeCartDay.current !== day) return;
    AsyncStorage.setItem(cartStorageKey, JSON.stringify({day, items: cart}))
      .catch(error => console.warn('Cart changes could not be saved:', error?.message));
  }, [cart, cartHydrated, cartStorageKey, day, userToken]);

  const fetchOrders = useCallback(async ({page = 1, append = false} = {}) => {
    if (!userToken) return;
    const requestId = ++ordersRequestId.current;
    append ? setIsLoadingMore(true) : setIsLoading(true);
    try {
      const response = await api.get('/orders', {params: {page}});
      if (requestId !== ordersRequestId.current) return {success: false, stale: true};
      const normalized = (response.data.orders || []).map(normalize);
      setOrders(current => append
        ? [...current, ...normalized.filter(record => !current.some(item => item.id === record.id))]
        : normalized,
      );
      setPagination(normalizePagination(response.data?.meta, page, normalized.length));
      setLoadError(null);
      return {success: true};
    } catch (error) {
      if (requestId !== ordersRequestId.current) return {success: false, stale: true};
      setLoadError(getApiErrorMessage(error));
      return {success: false, error};
    } finally {
      append ? setIsLoadingMore(false) : setIsLoading(false);
    }
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
    }
  }, [fetchOrders, userToken]);

  const mergeOrder = useCallback(orderValue => {
    const order = normalize(orderValue);
    const existed = orders.some(item => item.id === order.id);
    setOrders(current => {
      const existingIndex = current.findIndex(item => item.id === order.id);
      if (existingIndex < 0) return [order, ...current];
      return current.map(item => item.id === order.id ? {...item, ...order} : item);
    });
    if (!existed) setPagination(current => ({...current, total: current.total + 1}));
    return order;
  }, [orders]);

  const refreshOrderRelatedData = useCallback(async ({financialsChanged = false} = {}) => {
    const refreshes = [fetchOrders({page: 1})];
    if (financialsChanged) {
      refreshes.push(fetchTransactions(), fetchStock(), fetchProducts());
    }
    await Promise.allSettled(refreshes);
  }, [fetchOrders, fetchProducts, fetchStock, fetchTransactions]);

  const createOrder = useCallback(async orderData => {
    const payload = buildOrderPayload(orderData);
    if (!['cash', 'gcash', 'maya', 'bank'].includes(payload.payment_method)) {
      throw new Error('Choose a valid payment method before placing the order.');
    }
    const signature = orderRequestSignature(payload);
    if (!attemptStorageKey || !cartStorageKey) {
      throw new Error('Sign in again before placing an order.');
    }

    if (activeSubmission.current) {
      if (activeSubmission.current.signature !== signature) {
        throw new Error('An order is already being submitted. Wait for it to finish before changing the cart.');
      }
      return activeSubmission.current.promise;
    }

    const submit = async () => {
      let previousAttempt = null;
      try {
        const savedAttempt = await AsyncStorage.getItem(attemptStorageKey);
        previousAttempt = savedAttempt ? JSON.parse(savedAttempt) : null;
        if (savedAttempt && (
          !previousAttempt
          || typeof previousAttempt.signature !== 'string'
          || !/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(previousAttempt.client_id || '')
        )) {
          throw orderRetryStorageError();
        }
      } catch {
        throw orderRetryStorageError();
      }

      if (previousAttempt?.signature === signature && previousAttempt?.completed && previousAttempt?.order) {
        const cachedOrder = normalize(previousAttempt.order);
        try {
          const latest = await api.get(`/orders/${cachedOrder.id}`);
          previousAttempt.order = latest.data.order;
        } catch {
          // The saved server response and idempotency key are retained for a safe retry.
        }
        const order = mergeOrder(previousAttempt.order);
        setCartState([]);
        try {
          await AsyncStorage.setItem(cartStorageKey, JSON.stringify({day: staffDayKey(), items: []}));
          await AsyncStorage.removeItem(attemptStorageKey);
        } catch {
          // Keeping the completed attempt is safer than generating another order.
        }
        await refreshOrderRelatedData({financialsChanged: order.paymentStatus === 'confirmed' || order.paymentStatus === 'refunded'});
        return order;
      }

      const clientId = previousAttempt?.signature === signature
        ? previousAttempt.client_id
        : createOrderIdempotencyKey();
      const attempt = {client_id: clientId, signature, completed: false, order: null};
      try {
        await AsyncStorage.setItem(attemptStorageKey, JSON.stringify(attempt));
      } catch {
        throw orderRetryStorageError();
      }

      // If a timeout/lost response occurs, this attempt stays persisted. Retrying
      // the same cart reuses this client_id and the server returns the same order.
      const response = await api.post('/orders', {...payload, client_id: clientId});
      if (!response?.data?.order?.id) {
        throw new Error('The server response did not include the saved order. Your cart is still available; retry the same order.');
      }
      const order = normalize(response.data.order);
      attempt.completed = true;
      attempt.order = order;
      try {
        await AsyncStorage.setItem(attemptStorageKey, JSON.stringify(attempt));
      } catch {
        // Keep the cart and the original id. Retrying still receives the same
        // server order even if saving the completed response failed.
        throw orderRetryStorageError();
      }

      mergeOrder(order);
      setCartState([]);
      try {
        await AsyncStorage.setItem(cartStorageKey, JSON.stringify({day: staffDayKey(), items: []}));
        await AsyncStorage.removeItem(attemptStorageKey);
      } catch {
        // The persisted completed attempt prevents an accidental second order.
      }

      await refreshOrderRelatedData({financialsChanged: order.paymentStatus === 'confirmed'});
      return order;
    };

    const promise = submit();
    activeSubmission.current = {signature, promise};
    try {
      return await promise;
    } finally {
      if (activeSubmission.current?.promise === promise) activeSubmission.current = null;
    }
  }, [attemptStorageKey, cartStorageKey, mergeOrder, refreshOrderRelatedData]);

  const postOrderAction = useCallback(async (orderId, path, body = {}, options = {}) => {
    const response = await api.post(`/orders/${orderId}/${path}`, body);
    const order = mergeOrder(response.data.order);
    await refreshOrderRelatedData({financialsChanged: Boolean(options.financialsChanged)});
    return {order, message: response.data.message, data: response.data};
  }, [mergeOrder, refreshOrderRelatedData]);

  const recordPaymentDetection = useCallback((orderId, signal) =>
    postOrderAction(orderId, 'payments/detected', signal), [postOrderAction]);

  const confirmPaymentFromNotification = useCallback((orderId, signal) =>
    postOrderAction(orderId, 'payments/confirm-notification', signal, {financialsChanged: true}), [postOrderAction]);

  const confirmPayment = useCallback((orderId, paymentDetails) =>
    postOrderAction(orderId, 'payments/confirm', paymentDetails, {financialsChanged: true}), [postOrderAction]);

  const markPaymentFailed = useCallback((orderId, reason) =>
    postOrderAction(orderId, 'payments/fail', typeof reason === 'string'
      ? {reason, payment_not_received: true}
      : reason), [postOrderAction]);

  const retryPayment = useCallback(orderId =>
    postOrderAction(orderId, 'payments/retry'), [postOrderAction]);

  const cancelOrder = useCallback(async (orderId, details) => {
    const result = await postOrderAction(orderId, 'cancel', details, {financialsChanged: true});
    if (result.order.status === 'cancelled' && attemptStorageKey) {
      try {
        const saved = await AsyncStorage.getItem(attemptStorageKey);
        const attempt = saved ? JSON.parse(saved) : null;
        if (attempt && (
          attempt.client_id === result.order.client_id
          || Number(attempt.order?.id) === Number(orderId)
        )) {
          await AsyncStorage.removeItem(attemptStorageKey);
        }
      } catch {
        // A canceled server order remains auditable; a later retry is still
        // protected by the server-side unique idempotency key.
      }
    }
    return result;
  }, [attemptStorageKey, postOrderAction]);

  const requestRefund = useCallback((orderId, details) =>
    postOrderAction(orderId, 'refund/request', details), [postOrderAction]);

  const confirmRefund = useCallback((orderId, details) =>
    postOrderAction(orderId, 'refund/confirm', details, {financialsChanged: true}), [postOrderAction]);

  return (
    <OrderContext.Provider value={{
      orders,
      cart,
      setCart,
      cartHydrated,
      orderTotal: pagination.total,
      isLoading,
      isLoadingMore,
      loadError,
      hasMoreOrders,
      fetchOrders,
      loadMoreOrders,
      createOrder,
      recordPaymentDetection,
    confirmPaymentFromNotification,
      confirmPayment,
      markPaymentFailed,
      retryPayment,
      cancelOrder,
      requestRefund,
      confirmRefund,
    }}>
      {children}
    </OrderContext.Provider>
  );
};

export const useOrders = () => useContext(OrderContext);
