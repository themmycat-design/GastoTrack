import React from 'react';
import ReactTestRenderer from 'react-test-renderer';
import {FlatList, Modal, Text, TextInput, TouchableOpacity} from 'react-native';
import OrderQueueScreen from '../src/screens/staff/OrderQueueScreen';
import {useProducts} from '../src/context/ProductContext';
import {useTransactions} from '../src/context/TransactionContext';
import {useOrders} from '../src/context/OrderContext';
import {isNotificationAccessGranted, subscribeToNotifications} from '../src/modules/NotificationModule';
import {AppAlert} from '../src/services/AppDialogService';
import api from '../src/services/api';
import {launchImageLibrary} from 'react-native-image-picker';
import {scanReceipt} from '../src/services/OCRService';

jest.mock('react-native-vector-icons/MaterialCommunityIcons', () => 'MockIcon');
jest.mock('../src/components/staff/StaffScreenHeader', () => () => null);
jest.mock('../src/context/ProductContext', () => ({useProducts: jest.fn()}));
jest.mock('../src/context/TransactionContext', () => ({useTransactions: jest.fn()}));
jest.mock('../src/context/OrderContext', () => ({useOrders: jest.fn()}));
jest.mock('../src/services/api', () => ({post: jest.fn()}));
jest.mock('react-native-image-picker', () => ({launchImageLibrary: jest.fn()}));
jest.mock('../src/services/OCRService', () => ({scanReceipt: jest.fn()}));
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
  isNotificationAccessGranted.mockResolvedValue(true);
  api.post.mockResolvedValue({data: {saved_ids: ['7e2e640f-c116-4294-9282-52f413523458']}});
  launchImageLibrary.mockResolvedValue({assets: [{uri: 'file:///payment-screenshot.png'}]});
  scanReceipt.mockResolvedValue({success: true, text: 'GCash\nAmount received: PHP 85.00\nFrom: Wallet Customer\nDate: Oct 12, 2026\nRef No: GCASH-1234'});
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

const setup = ({cart = [], selectedProduct = product, products = [selectedProduct]} = {}) => {
  const setCart = jest.fn();
  const fetchProducts = jest.fn().mockResolvedValue({success: true});
  const fetchTransactionOptions = jest.fn().mockResolvedValue({success: true});
  const createOrder = jest.fn().mockResolvedValue(pendingOrder);
  const confirmPaymentFromNotification = jest.fn().mockResolvedValue({
    order: {...pendingOrder, status: 'completed', paymentStatus: 'confirmed'},
  });
  const confirmPayment = jest.fn().mockResolvedValue({
    order: {...pendingOrder, status: 'completed', paymentStatus: 'confirmed'},
  });
  const markPaymentFailed = jest.fn();
  const cancelOrder = jest.fn().mockResolvedValue({
    order: {...pendingOrder, status: 'cancelled', paymentStatus: 'cancelled'},
  });
  const changeWallet = jest.fn().mockImplementation(async (_orderId, wallet) => ({
    order: {...pendingOrder, paymentMethod: wallet},
  }));
  let notificationListener;

  useProducts.mockReturnValue({products, isLoading: false, fetchProducts});
  useTransactions.mockReturnValue({PRODUCT_CATEGORIES: ['Milktea'], fetchTransactionOptions});
  useOrders.mockReturnValue({
    createOrder,
    confirmPaymentFromNotification,
    confirmPayment,
    markPaymentFailed,
    cancelOrder,
    changeWallet,
    cart,
    setCart,
    cartHydrated: true,
  });
  subscribeToNotifications.mockImplementation(callback => {
    notificationListener = callback;
    return jest.fn();
  });

  let screen;
  const navigation = {navigate: jest.fn(), setParams: jest.fn()};
  ReactTestRenderer.act(() => {
    screen = ReactTestRenderer.create(
      <OrderQueueScreen navigation={navigation} route={{params: {}}} />,
    );
  });
  mountedScreens.push(screen);
  return {screen, navigation, setCart, createOrder, confirmPaymentFromNotification, confirmPayment, changeWallet, cancelOrder, fetchProducts, fetchTransactionOptions, getNotificationListener: () => notificationListener};
};

describe('OrderQueueScreen ordering flow', () => {
  it('offers GoTyme as a cashless payment method', async () => {
    const {screen, createOrder} = setup({cart: [{product, quantity: 1}]});
    await pressByLabel(screen, 'View your cart');
    await pressByLabel(screen, 'Select Cashless payment method');
    await pressByLabel(screen, 'Choose GoTyme');
    expect(createOrder).toHaveBeenCalledWith(expect.objectContaining({paymentMethod: 'gotyme'}));
  });

  it('shows only configured sizes under the category and filters the order list', async () => {
    const small = {...product, id: 8, size: 'Small'};
    const medium = {...product, id: 9, size: 'Medium'};
    const {screen} = setup({products: [small, medium]});

    await pressByLabel(screen, 'Filter products by Milktea');
    expect(screen.root.findAllByType(TouchableOpacity).some(node => node.props.accessibilityLabel === 'Filter Milktea by Large')).toBe(false);
    await pressByLabel(screen, 'Filter Milktea by Medium');
    expect(screen.root.findAllByType(FlatList).find(list => list.props.numColumns === 2).props.data).toEqual([medium]);
  });

  it('reloads products and categories when the catalog is pulled down', async () => {
    const {screen, fetchProducts, fetchTransactionOptions} = setup();
    const productList = screen.root.findAllByType(FlatList).find(list => list.props.numColumns === 2);

    await ReactTestRenderer.act(async () => productList.props.refreshControl.props.onRefresh());

    expect(fetchProducts).toHaveBeenCalledTimes(1);
    expect(fetchTransactionOptions).toHaveBeenCalledTimes(1);
  });

  it('opens product details before adding the selected product to the cart', async () => {
    const {screen, setCart} = setup();

    await pressByLabel(screen, 'View Brown Sugar Milk Tea details');
    expect(screen.root.findAllByType(Modal).some(modal => modal.props.visible)).toBe(true);

    await pressByLabel(screen, 'Add Brown Sugar Milk Tea to cart');
    expect(setCart).toHaveBeenCalledTimes(1);
    expect(setCart.mock.calls[0][0]([])).toEqual([{product, quantity: 1}]);
    expect(screen.root.findAllByType(Modal).some(modal => modal.props.visible)).toBe(false);
  });

  it('saves a no-sugar recipe as a distinct cart line', async () => {
    const customizedProduct = {...product, ingredients: [
      {stockId: 11, name: 'Sugar', quantity: 20, unit: 'g'},
    ]};
    const {screen, setCart} = setup({selectedProduct: customizedProduct});

    await pressByLabel(screen, 'View Brown Sugar Milk Tea details');
    await pressByLabel(screen, 'None Sugar');
    await pressByLabel(screen, 'Add Brown Sugar Milk Tea to cart');

    const customized = setCart.mock.calls[0][0]([]);
    expect(customized).toEqual([{
      product: customizedProduct, quantity: 1,
      ingredientOverrides: [{stock_item_id: 11, quantity: 0}],
    }]);

    await pressByLabel(screen, 'View Brown Sugar Milk Tea details');
    await pressByLabel(screen, 'Add Brown Sugar Milk Tea to cart');
    expect(setCart.mock.calls[1][0](customized)).toEqual([
      customized[0], {product: customizedProduct, quantity: 1},
    ]);
  });

  it('lets staff edit a cart line before checkout', async () => {
    const customizedProduct = {...product, ingredients: [
      {stockId: 11, name: 'Sugar', quantity: 20, unit: 'g'},
    ]};
    const initialCart = [{product: customizedProduct, quantity: 1}];
    const {screen, setCart} = setup({cart: initialCart, selectedProduct: customizedProduct});

    await pressByLabel(screen, 'View your cart');
    await pressByLabel(screen, 'Edit Brown Sugar Milk Tea cart item');
    ReactTestRenderer.act(() => jest.advanceTimersByTime(350));
    await pressByLabel(screen, 'Half Sugar');
    await pressByLabel(screen, 'Save Brown Sugar Milk Tea cart changes');

    expect(setCart.mock.calls[0][0](initialCart)).toEqual([{
      product: customizedProduct, quantity: 1,
      ingredientOverrides: [{stock_item_id: 11, quantity: 10}],
    }]);
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
      .filter(button => ['Choose GCash', 'Choose Maya', 'Choose MariBank', 'Choose GoTyme'].includes(button.props.accessibilityLabel));
    expect(walletChoices).toHaveLength(4);
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
        transactionType: 'received', appSource: 'GCash', amount: '85.00',
        reference: 'GCASH-REF-1',
      });
    });
    expect(confirmPaymentFromNotification).not.toHaveBeenCalled();

    await ReactTestRenderer.act(async () => {
      getNotificationListener()({
        transactionType: 'received',
        appSource: 'GCash',
        amount: '85.00',
        merchant: 'GastoTrack Shop',
        reference: 'GCASH-REF-1',
        capturePayload: JSON.stringify({client_id: '7e2e640f-c116-4294-9282-52f413523458', source: 'gcash', reference: 'GCASH-REF-1'}),
      });
      await Promise.resolve();
      await Promise.resolve();
    });

    expect(confirmPaymentFromNotification).toHaveBeenCalledWith(41, expect.objectContaining({
      amount: 85,
      currency: 'PHP',
      source: 'gcash',
      notification_id: '7e2e640f-c116-4294-9282-52f413523458',
    }));
    expect(screen.root.findAllByType(Modal).some(modal => modal.props.visible)).toBe(false);
    expect(screen.root.findAllByType(Text).some(node => node.props.children === 'Payment notification matched the order and the order was saved. GastoTrack did not verify it with the wallet provider.')).toBe(true);
  });

  it('cancels a waiting wallet order and returns to checkout with the cart restored', async () => {
    const cart = [{product, quantity: 1}];
    const {screen, setCart, createOrder, cancelOrder} = setup({cart});
    const alertSpy = jest.spyOn(AppAlert, 'alert').mockImplementation(() => {});

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

  it('scans a payment screenshot, lets staff review it, and confirms only after approval', async () => {
    const {screen, confirmPayment} = setup({cart: [{product, quantity: 1}]});
    const alertSpy = jest.spyOn(AppAlert, 'alert').mockImplementation(() => {});

    await pressByLabel(screen, 'View your cart');
    await pressByLabel(screen, 'Select Cashless payment method');
    await pressByLabel(screen, 'Choose GCash');
    await pressByLabel(screen, 'Payment received');
    expect(confirmPayment).not.toHaveBeenCalled();
    await pressByLabel(screen, 'Choose payment screenshot from gallery');
    expect(launchImageLibrary).toHaveBeenCalled();
    expect(scanReceipt).toHaveBeenCalledWith('file:///payment-screenshot.png');

    const referenceInput = screen.root.findAllByType(TextInput)
      .find(input => input.props.accessibilityLabel === 'Wallet transaction reference');
    const senderInput = screen.root.findAllByType(TextInput)
      .find(input => input.props.accessibilityLabel === 'Wallet sender name');
    expect(senderInput.props.value).toBe('Wallet Customer');
    expect(referenceInput.props.value).toBe('GCASH-1234');
    await pressByLabel(screen, 'Confirm payment received');
    expect(confirmPayment).not.toHaveBeenCalled();
    expect(alertSpy.mock.calls.at(-1)[0]).toBe('Confirm payment received?');
    const confirmAction = alertSpy.mock.calls.at(-1)[2].find(button => button.text === 'Confirm payment');
    await ReactTestRenderer.act(async () => confirmAction.onPress());

    expect(confirmPayment).toHaveBeenCalledWith(41, expect.objectContaining({
      amount: 85, currency: 'PHP', reference: 'GCASH-1234', sender_name: 'Wallet Customer',
      received_on: expect.stringMatching(/^\d{4}-\d{2}-\d{2}$/),
    }));
    expect(screen.root.findAllByType(Modal).some(modal => modal.props.visible)).toBe(false);
  });

  it('does not offer confirmation for a screenshot with the wrong payment app', async () => {
    const {screen, confirmPayment} = setup({cart: [{product, quantity: 1}]});
    scanReceipt.mockResolvedValue({success: true, text: 'Maya\nAmount received: PHP 85.00\nRef No: MAYA-1234'});
    await pressByLabel(screen, 'View your cart');
    await pressByLabel(screen, 'Select Cashless payment method');
    await pressByLabel(screen, 'Choose GCash');
    await pressByLabel(screen, 'Payment received');
    await pressByLabel(screen, 'Choose payment screenshot from gallery');
    expect(screen.root.findAllByType(TouchableOpacity).some(button => button.props.accessibilityLabel === 'Confirm payment received')).toBe(false);
    expect(confirmPayment).not.toHaveBeenCalled();
  });

  it('returns directly to wallet selection and switches the same pending order', async () => {
    const {screen, createOrder, changeWallet, cancelOrder} = setup({cart: [{product, quantity: 1}]});
    const alertSpy = jest.spyOn(AppAlert, 'alert').mockImplementation(() => {});

    await pressByLabel(screen, 'View your cart');
    await pressByLabel(screen, 'Select Cashless payment method');
    await pressByLabel(screen, 'Choose GCash');
    await pressByLabel(screen, 'Back to wallet selection');
    expect(alertSpy).not.toHaveBeenCalled();
    expect(screen.root.findAllByType(TouchableOpacity).some(button => button.props.accessibilityLabel === 'Choose Maya')).toBe(true);
    await pressByLabel(screen, 'Choose Maya');
    expect(changeWallet).toHaveBeenCalledWith(41, 'maya');
    expect(createOrder).toHaveBeenCalledTimes(1);
    expect(cancelOrder).not.toHaveBeenCalled();
    expect(screen.root.findAllByType(Text).some(node =>
      Array.isArray(node.props.children) && node.props.children.join('') === 'Waiting for Maya payment')).toBe(true);
  });

  it('lets staff continue to manual wallet checking when notification access is restricted', async () => {
    isNotificationAccessGranted.mockResolvedValue(false);
    const {screen, createOrder} = setup({cart: [{product, quantity: 1}]});
    const alertSpy = jest.spyOn(AppAlert, 'alert').mockImplementation(() => {});

    await pressByLabel(screen, 'View your cart');
    await pressByLabel(screen, 'Select Cashless payment method');
    await pressByLabel(screen, 'Choose Maya');
    expect(createOrder).not.toHaveBeenCalled();
    const continueAction = alertSpy.mock.calls.at(-1)[2].find(button => button.text === 'Continue manually');
    await ReactTestRenderer.act(async () => continueAction.onPress());

    expect(createOrder).toHaveBeenCalledWith(expect.objectContaining({paymentMethod: 'maya'}));
    expect(screen.root.findAllByType(TouchableOpacity).some(button => button.props.accessibilityLabel === 'Payment received')).toBe(true);
  });

  it('saves a cash order with cash selected at checkout', async () => {
    const {screen, createOrder} = setup({cart: [{product, quantity: 1}]});

    await pressByLabel(screen, 'View your cart');
    await pressByLabel(screen, 'Select Cash payment method');
    await pressByLabel(screen, 'Save cash order');

    expect(createOrder).toHaveBeenCalledWith(expect.objectContaining({paymentMethod: 'cash'}));
  });

  it('offers the saved offline cash order in Order history', async () => {
    const {screen, navigation, createOrder} = setup({cart: [{product, quantity: 1}]});
    const alertSpy = jest.spyOn(AppAlert, 'alert').mockImplementation(() => {});
    createOrder.mockResolvedValue({offlinePending: true, clientId: 'offline-order-1'});

    await pressByLabel(screen, 'View your cart');
    await pressByLabel(screen, 'Select Cash payment method');
    await pressByLabel(screen, 'Save cash order');

    const [, , buttons] = alertSpy.mock.calls.find(([title]) => title === 'Order completed on this device');
    buttons.find(button => button.text === 'View order').onPress();
    expect(navigation.navigate).toHaveBeenCalledWith('OrderHistory');
  });

  it('explains an insufficient-stock rejection and keeps checkout available', async () => {
    const {screen, createOrder} = setup({cart: [{product, quantity: 1}]});
    const alertSpy = jest.spyOn(AppAlert, 'alert').mockImplementation(() => {});
    createOrder.mockRejectedValue(Object.assign(new Error('Insufficient stock for Milk. No payment or inventory changes were made.'), {
      code: 'STAFF_OFFLINE_ACTION_REJECTED',
    }));

    await pressByLabel(screen, 'View your cart');
    await pressByLabel(screen, 'Select Cash payment method');
    await pressByLabel(screen, 'Save cash order');

    expect(alertSpy).toHaveBeenCalledWith('Not enough stock', 'Insufficient stock for Milk.');
    expect(screen.root.findAllByType(Modal).some(modal => modal.props.visible)).toBe(true);
    alertSpy.mockRestore();
  });
});
