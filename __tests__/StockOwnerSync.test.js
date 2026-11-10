import React from 'react';
import TestRenderer, {act} from 'react-test-renderer';
import {AppState} from 'react-native';
import api from '../src/services/api';
import {AuthContext} from '../src/context/AuthContext';
import {StockProvider, useStock} from '../src/context/StockContext';

jest.mock('../src/services/api', () => ({
  __esModule: true,
  default: {get: jest.fn()},
}));
jest.mock('../src/services/staffOffline', () => ({
  getStaffSnapshot: jest.fn(),
  saveStaffSnapshot: jest.fn(() => Promise.resolve()),
  subscribeStaffOffline: jest.fn(() => () => {}),
}));
jest.mock('../src/services/offlineRecords', () => ({
  overlayStockActions: stock => stock,
  readPendingActions: jest.fn(() => Promise.resolve([])),
}));

let stockContext;
const Consumer = () => {
  stockContext = useStock();
  return null;
};

afterEach(() => {
  jest.restoreAllMocks();
  api.get.mockReset();
});

it('loads the owner’s changed stock unit, minimum, and cost when staff returns to the app', async () => {
  const before = {id: 7, name: 'Milk', unit: 'L', current_quantity: '2.0000',
    minimum_quantity: '0.5000', unit_cost: '10.0000', revision: 1};
  const after = {id: 7, name: 'Milk', unit: 'ml', current_quantity: '2000.0000',
    minimum_quantity: '250.0000', unit_cost: '0.0200', revision: 2};
  api.get.mockResolvedValueOnce({data: {stock: [before]}})
    .mockResolvedValueOnce({data: {stock: [after]}});
  let onAppStateChange;
  jest.spyOn(AppState, 'addEventListener').mockImplementation((_event, callback) => {
    onAppStateChange = callback;
    return {remove: jest.fn()};
  });

  let tree;
  await act(async () => {
    tree = TestRenderer.create(
      <AuthContext.Provider value={{userToken: 'token', user: {id: 3, role: 'staff', business_id: 1}, business: {id: 1}}}>
        <StockProvider><Consumer /></StockProvider>
      </AuthContext.Provider>,
    );
  });
  expect(stockContext.stockItems[0]).toMatchObject({unit: 'L', quantity: 2, threshold: 0.5, unitCost: 10});

  await act(async () => onAppStateChange('active'));
  expect(stockContext.stockItems[0]).toMatchObject({unit: 'ml', quantity: 2000, threshold: 250, unitCost: 0.02});
  act(() => tree.unmount());
});

it('refreshes every inventory page already loaded by staff', async () => {
  const page = (currentPage, item) => ({data: {stock: [item], meta: {
    current_page: currentPage, last_page: 2, total: 2,
  }}});
  api.get.mockResolvedValueOnce(page(1, {id: 1, name: 'Cups', unit: 'pcs', current_quantity: 10}))
    .mockResolvedValueOnce(page(2, {id: 2, name: 'Milk', unit: 'L', current_quantity: 2}))
    .mockResolvedValueOnce(page(1, {id: 1, name: 'Cups', unit: 'pcs', current_quantity: 12}))
    .mockResolvedValueOnce(page(2, {id: 2, name: 'Milk', unit: 'ml', current_quantity: 2000}));
  let onAppStateChange;
  jest.spyOn(AppState, 'addEventListener').mockImplementation((_event, callback) => {
    onAppStateChange = callback;
    return {remove: jest.fn()};
  });

  let tree;
  await act(async () => {
    tree = TestRenderer.create(
      <AuthContext.Provider value={{userToken: 'token', user: {id: 3, role: 'staff', business_id: 1}, business: {id: 1}}}>
        <StockProvider><Consumer /></StockProvider>
      </AuthContext.Provider>,
    );
  });
  await act(async () => stockContext.loadMoreStock());
  expect(stockContext.stockItems).toHaveLength(2);

  await act(async () => onAppStateChange('active'));
  expect(stockContext.stockItems).toMatchObject([
    {id: 1, quantity: 12}, {id: 2, unit: 'ml', quantity: 2000},
  ]);
  act(() => tree.unmount());
});
