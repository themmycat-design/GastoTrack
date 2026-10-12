import React from 'react';
import ReactTestRenderer from 'react-test-renderer';
import {Alert, Modal, Text, TouchableOpacity} from 'react-native';
import OrderQueueScreen from '../src/screens/staff/OrderQueueScreen';
import {useProducts} from '../src/context/ProductContext';
import {useTransactions} from '../src/context/TransactionContext';
import {useOrders} from '../src/context/OrderContext';
import {subscribeToNotifications} from '../src/modules/NotificationModule';

jest.mock('react-native-vector-icons/MaterialCommunityIcons', () => 'MockIcon');
jest.mock('../src/components/staff/StaffScreenHeader', () => () => null);
jest.mock('../src/context/ProductContext', () => ({useProducts: jest.fn()}));
jest.mock('../src/context/TransactionContext', () => ({useTransactions: jest.fn()}));
jest.mock('../src/context/OrderContext', () => ({useOrders: jest.fn()}));
jest.mock('../src/modules/NotificationModule', () => ({
  subscribeToNotifications: jest.fn(),
  isNotificationAccessGranted: jest.fn().mockResolvedValue(true),
  openNotificationSettings: jest.fn(),
}));

const product = {
  id: 7,
  name: 'Brown Sugar Milk Tea',
  price: 85,
  category: 'Milktea',
  description: 'Milk tea',
  image: null,
  ingredients: [],
  is_available: true,
};
const pendingOrder = {
  id: 41,
  total: 85,
  paymentCurrency: 'PHP',
  paymentStatus: 'pending',
};
const mountedScreens = [];

beforeEach(() => {
  jest.clearAllMocks();
  jest.useFakeTimers();
});

afterEach(() => {
  ReactTestRenderer.act(() => mountedScreens.splice(0).forEach(screen => screen.unmount()));
  jest.restoreAllMocks();
  jest.clearAllTimers();
  jest.useRealTimers();
});

const pressByLabel = async (screen, label) => {
  const button = screen.root.findAllByType(TouchableOpacity)
    .find(node => node.props.accessibilityLabel === label);
  expect(button).toBeDefined();
  await ReactTestRenderer.act(async () => button.props.onPress());
};

const setup = ({cart = []} = {}) => {
  const setCart = jest.fn();
  const createOrder = jest.fn().mockResolvedValue(pendingOrder);
  const confirmPaymentFromNotification = jest.fn().mockResolvedValue({
    order: {...pendingOrder, status: 'completed', paymentStatus: 'confirmed'},
  });
  const markPaymentFailed = jest.fn();
  const cancelOrder = jest.fn().mockResolvedValue({
    order: {...pendingOrder, status: 'cancelled', paymentStatus: 'cancelled'},
  });
  let notificationListener;

  useProducts.mockReturnValue({products: [product], isLoading: false});
  useTransactions.mockReturnValue({PRODUCT_CATEGORIES: ['Milktea']});
  useOrders.mockReturnValue({
    createOrder,
    confirmPaymentFromNotification,
    markPaymentFailed,
    cancelOrder,
    cart,
    setCart,
    cartHydrated: true,
  });
  subscribeToNotifications.mockImplementation(callback => {
    notificationListener = callback;
    return jest.fn();
  });

  let screen;
  ReactTestRenderer.act(() => {
    screen = ReactTestRenderer.create(
      <OrderQueueScreen navigation={{navigate: jest.fn(), setParams: jest.fn()}} route={{params: {}}} />,
    );
  });
  mountedScreens.push(screen);
  return {screen, setCart, createOrder, confirmPaymentFromNotification, cancelOrder, getNotificationListener: () => notificationListener};
};

describe('OrderQueueScreen ordering flow', () => {
  it('opens product details before adding the selected product to the cart', async () => {
    const {screen, setCart} = setup();

    await pressByLabel(screen, 'View Brown Sugar Milk Tea details');
    expect(screen.root.findAllByType(Modal).some(modal => modal.props.visible)).toBe(true);

    await pressByLabel(screen, 'Add Brown Sugar Milk Tea to cart');
    expect(setCart).toHaveBeenCalledTimes(1);
    expect(setCart.mock.calls[0][0]([])).toEqual([{product, quantity: 1}]);
    expect(screen.root.findAllByType(Modal).some(modal => modal.props.visible)).toBe(false);
  });

  it('chooses cashless wallet, waits for an exact notification match, then saves automatically', async () => {
    const {screen, createOrder, confirmPaymentFromNotification, getNotificationListener} = setup({
      cart: [{product, quantity: 1}],
    });
    expect(screen.root.findAllByType(TouchableOpacity)
      .filter(button => button.props.accessibilityLabel === 'View your cart')).toHaveLength(1);

    await pressByLabel(screen, 'View your cart');
    await pressByLabel(screen, 'Select Cashless payment method');
    const walletChoices = screen.root.findAllByType(TouchableOpacity)
      .filter(button => ['Choose GCash', 'Choose Maya'].includes(button.props.accessibilityLabel));
    expect(walletChoices).toHaveLength(2);
    expect(walletChoices[0].parent.props.style).toMatchObject({flexDirection: 'column', alignItems: 'stretch'});
    expect(screen.root.findAllByType(TouchableOpacity).some(button => button.props.accessibilityLabel === 'Choose GCash')).toBe(true);
    await pressByLabel(screen, 'Choose GCash');
    expect(createOrder).toHaveBeenCalledWith(expect.objectContaining({paymentMethod: 'gcash'}));
    expect(getNotificationListener()).toEqual(expect.any(Function));
    const cancelButton = screen.root.findAllByType(TouchableOpacity)
      .find(button => button.props.accessibilityLabel === 'Cancel pending order');
    expect(cancelButton.parent.props.style).toMatchObject({flexDirection: 'row'});

    await ReactTestRenderer.act(async () => {
      getNotificationListener()({
        transactionType: 'received',
        appSource: 'GCash',
        amount: '84.00',
        merchant: 'GastoTrack Shop',
      });
    });
    expect(confirmPaymentFromNotification).not.toHaveBeenCalled();
    expect(screen.root.findAllByType(Text).some(node =>
      String(node.props.children).includes('does not match the order total'))).toBe(true);

    await ReactTestRenderer.act(async () => {
      getNotificationListener()({
        transactionType: 'received',
        appSource: 'GCash',
        amount: '85.00',
        merchant: 'GastoTrack Shop',
      });
      await Promise.resolve();
      await Promise.resolve();
    });

    expect(confirmPaymentFromNotification).toHaveBeenCalledWith(41, expect.objectContaining({
      amount: 85,
      currency: 'PHP',
      source: 'gcash',
      notification_id: expect.stringMatching(/^[a-z0-9]+-[a-z0-9]+$/),
    }));
    expect(screen.root.findAllByType(Modal).some(modal => modal.props.visible)).toBe(false);
    expect(screen.root.findAllByType(Text).some(node => node.props.children === 'Payment notification matched the order and the order was saved. GastoTrack did not verify it with the wallet provider.')).toBe(true);
  });

  it('cancels a waiting wallet order and returns to checkout with the cart restored', async () => {
    const cart = [{product, quantity: 1}];
    const {screen, setCart, createOrder, cancelOrder} = setup({cart});
    const alertSpy = jest.spyOn(Alert, 'alert').mockImplementation(() => {});

    await pressByLabel(screen, 'View your cart');
    await pressByLabel(screen, 'Select Cashless payment method');
    await pressByLabel(screen, 'Choose GCash');
    expect(createOrder).toHaveBeenCalledWith(expect.objectContaining({paymentMethod: 'gcash'}));

    await pressByLabel(screen, 'Cancel pending order');
    const cancelAction = alertSpy.mock.calls.at(-1)[2].find(button => button.style === 'destructive');
    await ReactTestRenderer.act(async () => {
      await cancelAction.onPress();
    });

    expect(cancelOrder).toHaveBeenCalledWith(41, expect.objectContaining({payment_not_received: true}));
    expect(setCart).toHaveBeenCalledWith([{product: {...product}, quantity: 1}]);
    expect(screen.root.findAllByType(Modal).filter(modal => modal.props.visible)).toHaveLength(1);
    expect(screen.root.findAllByType(TouchableOpacity).some(button => button.props.accessibilityLabel === 'Select Cash payment method')).toBe(true);

    alertSpy.mockRestore();
  });

  it('saves a cash order with cash selected at checkout', async () => {
    const {screen, createOrder} = setup({cart: [{product, quantity: 1}]});

    await pressByLabel(screen, 'View your cart');
    await pressByLabel(screen, 'Select Cash payment method');
    await pressByLabel(screen, 'Save cash order');

    expect(createOrder).toHaveBeenCalledWith(expect.objectContaining({paymentMethod: 'cash'}));
  });
});
