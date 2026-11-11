import React, {useContext} from 'react';
import TestRenderer, {act} from 'react-test-renderer';
import api from '../src/services/api';
import {AuthContext} from '../src/context/AuthContext';
import {TransactionContext, TransactionProvider} from '../src/context/TransactionContext';
import {getStaffOfflineActions} from '../src/services/staffOffline';

jest.mock('../src/services/api', () => ({__esModule: true, default: {get: jest.fn()}}));
jest.mock('../src/services/staffOffline', () => ({
  getStaffOfflineActions: jest.fn(),
  getStaffSnapshot: jest.fn().mockResolvedValue([]),
  saveStaffSnapshot: jest.fn().mockResolvedValue(undefined),
  recordStaffAction: jest.fn(),
  subscribeStaffOffline: jest.fn(() => () => {}),
}));

let context;
const Consumer = () => {
  context = useContext(TransactionContext);
  return null;
};

it('lists an offline cash order as a transaction and replaces it after sync', async () => {
  const queuedOrder = {kind: 'cash_order', status: 'pending', client_id: 'order-1',
    payload: {offline_recorded_at: '2026-11-12T04:00:00.000Z',
      _local_item_names: ['Latte'], items: [{quantity: 2, expected_unit_price: '85.00'}]}};
  getStaffOfflineActions.mockResolvedValue([queuedOrder]);
  api.get.mockImplementation(path => path === '/transaction-options'
    ? Promise.resolve({data: {options: []}}) : Promise.reject(new Error('offline')));

  let tree;
  await act(async () => {
    tree = TestRenderer.create(
      <AuthContext.Provider value={{userToken: 'staff-token', user: {id: 7, role: 'staff', business_id: 3}}}>
        <TransactionProvider><Consumer /></TransactionProvider>
      </AuthContext.Provider>,
    );
  });

  let result;
  await act(async () => {
    result = await context.queryTransactions({from: '2026-11-12', to: '2026-11-12'});
  });
  expect(result).toMatchObject({success: true, offline: true});
  expect(result.transactions).toMatchObject([{
    id: 'local:order-1', amount: 170, type: 'Income', source: 'Cash',
    category: 'Sales', date: '2026-11-12', entryMethod: 'order_system', offlinePending: true, localCompleted: true,
  }]);

  getStaffOfflineActions.mockResolvedValue([{...queuedOrder, status: 'synced'}]);
  api.get.mockImplementation(path => path === '/transaction-options'
    ? Promise.resolve({data: {options: []}})
    : Promise.resolve({data: {transactions: [{id: 41, order_id: 9, type: 'income', source: 'cash',
      category: 'Sales', amount: 170, transaction_date: '2026-11-12', description: '2× Latte', entry_method: 'order_system'}],
      meta: {current_page: 1, last_page: 1, total: 1}}}));
  await act(async () => {
    result = await context.queryTransactions({from: '2026-11-12', to: '2026-11-12'});
  });
  expect(result.transactions).toHaveLength(1);
  expect(result.transactions[0]).toMatchObject({id: 41, amount: 170});
  expect(result.transactions[0]).not.toHaveProperty('offlinePending');

  await act(async () => tree.unmount());
});
