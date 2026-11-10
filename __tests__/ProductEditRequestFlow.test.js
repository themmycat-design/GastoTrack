import React from 'react';
import TestRenderer, {act} from 'react-test-renderer';
import {AppState} from 'react-native';
import api from '../src/services/api';
import {AuthContext} from '../src/context/AuthContext';
import {ProductProvider, useProducts} from '../src/context/ProductContext';
import {recordStaffAction} from '../src/services/staffOffline';

jest.mock('../src/services/api', () => ({
  __esModule: true,
  default: {get: jest.fn()},
  API_URL: 'https://example.test/api/v1',
}));
jest.mock('../src/services/staffOffline', () => ({
  getStaffSnapshot: jest.fn(),
  recordStaffAction: jest.fn(),
  saveStaffSnapshot: jest.fn(() => Promise.resolve()),
  subscribeStaffOffline: jest.fn(() => () => {}),
}));
jest.mock('../src/services/offlineRecords', () => ({
  overlayProductActions: products => products,
  readPendingActions: jest.fn(() => Promise.resolve([])),
}));

let productContext;
const Consumer = () => {
  productContext = useProducts();
  return null;
};

afterEach(() => {
  jest.restoreAllMocks();
  api.get.mockReset();
});

it('keeps the live product unchanged while an owner edit request is pending', async () => {
  const oldProduct = {id: 7, name: 'Tea', category: 'Drinks', price: '100.00',
    revision: 0, updated_at: '2026-11-10T00:00:00Z', product_ingredients: []};
  api.get.mockResolvedValue({data: {products: [oldProduct]}});
  recordStaffAction.mockResolvedValue({synced: true, data: {
    approval_pending: true, product: oldProduct,
  }});

  let tree;
  await act(async () => {
    tree = TestRenderer.create(
      <AuthContext.Provider value={{userToken: 'token', user: {id: 3, role: 'staff', business_id: 1}, business: {id: 1}}}>
        <ProductProvider><Consumer /></ProductProvider>
      </AuthContext.Provider>,
    );
  });

  let result;
  await act(async () => {
    result = await productContext.updateProduct(7, {
      name: 'New Tea', category: 'Drinks', price: '120', isAvailable: true, ingredients: [],
    });
  });

  expect(recordStaffAction).toHaveBeenCalledWith(
    {userId: 3, businessId: 1}, 'product_update', expect.objectContaining({target_id: 7}),
  );
  expect(result).toMatchObject({name: 'Tea', price: 100, approvalPending: true});
  expect(productContext.products[0]).toMatchObject({name: 'Tea', price: 100, approvalPending: true});
  act(() => tree.unmount());
});

it('loads the approved product when staff returns to the app', async () => {
  const pending = {id: 7, name: 'Tea', price: '100.00', approval_pending: true, product_ingredients: []};
  const approved = {id: 7, name: 'Iced Tea', price: '120.00', approval_pending: false, product_ingredients: []};
  api.get.mockResolvedValueOnce({data: {products: [pending]}})
    .mockResolvedValueOnce({data: {products: [approved]}});
  let appStateChange;
  jest.spyOn(AppState, 'addEventListener').mockImplementation((event, callback) => {
    appStateChange = callback;
    return {remove: jest.fn()};
  });

  let tree;
  await act(async () => {
    tree = TestRenderer.create(
      <AuthContext.Provider value={{userToken: 'token', user: {id: 3, role: 'staff', business_id: 1}, business: {id: 1}}}>
        <ProductProvider><Consumer /></ProductProvider>
      </AuthContext.Provider>,
    );
  });
  expect(productContext.products[0]).toMatchObject({name: 'Tea', approvalPending: true});

  await act(async () => appStateChange('active'));
  expect(productContext.products[0]).toMatchObject({name: 'Iced Tea', price: 120, approvalPending: false});
  act(() => tree.unmount());
});
