import React from 'react';
import TestRenderer, {act} from 'react-test-renderer';
import AsyncStorage from '@react-native-async-storage/async-storage';
import api from '../src/services/api';
import {AuthContext} from '../src/context/AuthContext';
import {OrderProvider, useOrders} from '../src/context/OrderContext';

let mockStaffDay = '2026-10-11';
jest.mock('../src/utils/useStaffDay', () => ({
  useStaffDay: () => mockStaffDay,
  staffDayKey: () => mockStaffDay,
}));

jest.mock('../src/services/api', () => ({
  __esModule: true,
  default: {
    get: jest.fn(),
    post: jest.fn(),
  },
}));

jest.mock('../src/context/TransactionContext', () => ({
  useTransactions: () => ({fetchTransactions: jest.fn(() => Promise.resolve())}),
}));
jest.mock('../src/context/StockContext', () => ({
  useStock: () => ({fetchStock: jest.fn(() => Promise.resolve())}),
}));
jest.mock('../src/context/ProductContext', () => ({
  useProducts: () => ({fetchProducts: jest.fn(() => Promise.resolve())}),
}));

const flushEffects = () => new Promise(resolve => setTimeout(resolve, 0));
let activeOrdersContext;
const OrdersConsumer = () => {
  activeOrdersContext = useOrders();
  return null;
};

describe('OrderProvider user-scoped storage', () => {
  let tree;
  let storage;

  beforeEach(() => {
    jest.clearAllMocks();
    mockStaffDay = '2026-10-11';
    storage = new Map();
    AsyncStorage.getItem.mockImplementation(async key => storage.get(key) ?? null);
    AsyncStorage.setItem.mockImplementation(async (key, value) => storage.set(key, value));
    AsyncStorage.removeItem.mockImplementation(async key => storage.delete(key));
    AsyncStorage.removeMany.mockResolvedValue(undefined);
    api.get.mockResolvedValue({data: {orders: [], meta: {current_page: 1, last_page: 1, total: 0}}});
  });

  afterEach(() => {
    if (tree) act(() => tree.unmount());
    tree = null;
  });

  it('removes the previous staff member cart and retry data using removeMany on logout', async () => {
    const renderWithSession = session => (
      <AuthContext.Provider value={session}>
        <OrderProvider>
          <OrdersConsumer />
        </OrderProvider>
      </AuthContext.Provider>
    );

    await act(async () => {
      tree = TestRenderer.create(renderWithSession({
        userToken: 'staff-token',
        user: {id: 17},
      }));
      await flushEffects();
    });

    await act(async () => {
      tree.update(renderWithSession({userToken: null, user: null}));
      await flushEffects();
    });

    expect(AsyncStorage.removeMany).toHaveBeenCalledWith([
      'gastotrack.cart.17',
      'gastotrack.order-attempt.17',
    ]);
    expect(AsyncStorage.multiRemove).toBeUndefined();
  });

  it('submits a cash order with server-facing item fields and clears the cart only after success', async () => {
    const session = {userToken: 'staff-token', user: {id: 17}};
    await act(async () => {
      tree = TestRenderer.create(
        <AuthContext.Provider value={session}>
          <OrderProvider><OrdersConsumer /></OrderProvider>
        </AuthContext.Provider>,
      );
      await flushEffects();
    });

    await act(async () => {
      activeOrdersContext.setCart([{product: {id: 42, name: 'Latte', price: 85}, quantity: 2}]);
      await flushEffects();
    });
    api.post.mockResolvedValueOnce({data: {order: {
      id: 301,
      status: 'completed',
      payment_status: 'confirmed',
      payment_method: 'cash',
      subtotal: '170.00',
      total: '170.00',
      items: [],
    }}});

    let createdOrder;
    await act(async () => {
      createdOrder = await activeOrdersContext.createOrder({
        items: [{product: {id: 42}, quantity: 2}],
        paymentMethod: 'cash',
      });
      await flushEffects();
    });

    expect(createdOrder.id).toBe(301);
    expect(api.post).toHaveBeenCalledWith('/orders', expect.objectContaining({
      items: [{product_id: 42, quantity: 2}],
      payment_method: 'cash',
      client_id: expect.stringMatching(/^[0-9a-f-]{36}$/i),
    }));
    expect(activeOrdersContext.cart).toEqual([]);
  });

  it('reuses the same order idempotency key after a failed response and preserves the cart', async () => {
    await act(async () => {
      tree = TestRenderer.create(
        <AuthContext.Provider value={{userToken: 'staff-token', user: {id: 17}}}>
          <OrderProvider><OrdersConsumer /></OrderProvider>
        </AuthContext.Provider>,
      );
      await flushEffects();
    });
    const items = [{product: {id: 42}, quantity: 1}];
    await act(async () => {
      activeOrdersContext.setCart([{product: {id: 42, name: 'Latte', price: 85}, quantity: 1}]);
      await flushEffects();
    });
    api.post.mockRejectedValueOnce(Object.assign(new Error('Request timed out'), {code: 'ECONNABORTED'}));

    await act(async () => {
      await expect(activeOrdersContext.createOrder({items, paymentMethod: 'cash'})).rejects.toThrow('timed out');
    });
    const attemptKey = 'gastotrack.order-attempt.17';
    const originalClientId = JSON.parse(storage.get(attemptKey)).client_id;
    expect(activeOrdersContext.cart).toHaveLength(1);

    api.post.mockResolvedValueOnce({data: {order: {
      id: 302,
      status: 'completed',
      payment_status: 'confirmed',
      payment_method: 'cash',
      subtotal: '85.00',
      total: '85.00',
      items: [],
    }}});
    await act(async () => {
      await activeOrdersContext.createOrder({items, paymentMethod: 'cash'});
      await flushEffects();
    });

    expect(api.post.mock.calls[1][1].client_id).toBe(originalClientId);
    expect(activeOrdersContext.cart).toEqual([]);
  });

  it('clears an unfinished cart on the next staff day without touching orders', async () => {
    const renderSession = () => (
      <AuthContext.Provider value={{userToken: 'staff-token', user: {id: 17}}}>
        <OrderProvider><OrdersConsumer /></OrderProvider>
      </AuthContext.Provider>
    );
    await act(async () => {
      tree = TestRenderer.create(renderSession());
      await flushEffects();
    });
    await act(async () => {
      activeOrdersContext.setCart([{product: {id: 42, price: 85}, quantity: 1}]);
      await flushEffects();
    });
    expect(activeOrdersContext.cart).toHaveLength(1);

    mockStaffDay = '2026-10-12';
    await act(async () => {
      tree.update(renderSession());
      await flushEffects();
    });

    expect(activeOrdersContext.cart).toEqual([]);
    expect(JSON.parse(storage.get('gastotrack.cart.17'))).toEqual({day: mockStaffDay, items: []});
    expect(api.post).not.toHaveBeenCalled();
  });

  it('keeps an ambiguous timed-out order retry available across days', async () => {
    const renderSession = () => (
      <AuthContext.Provider value={{userToken: 'staff-token', user: {id: 17}}}>
        <OrderProvider><OrdersConsumer /></OrderProvider>
      </AuthContext.Provider>
    );
    await act(async () => {
      tree = TestRenderer.create(renderSession());
      await flushEffects();
    });
    await act(async () => {
      activeOrdersContext.setCart([{product: {id: 42, price: 85}, quantity: 1}]);
      await flushEffects();
    });
    storage.set('gastotrack.order-attempt.17', JSON.stringify({client_id: 'attempt-1', completed: false}));

    mockStaffDay = '2026-10-12';
    await act(async () => {
      tree.update(renderSession());
      await flushEffects();
    });

    expect(activeOrdersContext.cart).toHaveLength(1);
    expect(storage.has('gastotrack.order-attempt.17')).toBe(true);
  });
});
