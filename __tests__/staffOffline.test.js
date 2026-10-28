import {NativeModules, Platform} from 'react-native';
import api from '../src/services/api';
import {getStaffOfflineActions, recordStaffAction, syncStaffOfflineActions} from '../src/services/staffOffline';

jest.mock('../src/services/api', () => ({__esModule: true, default: {post: jest.fn()}}));

const account = {userId: 7, businessId: 2};
let rows;

beforeEach(() => {
  Platform.OS = 'android';
  rows = [];
  jest.clearAllMocks();
  NativeModules.NotificationModule = {
    queueStaffAction: jest.fn(async (userId, businessId, clientId, kind, serialized) => {
      if (!rows.some(row => row.client_id === clientId)) rows.push({userId, businessId, client_id: clientId, kind, payload: JSON.parse(serialized), status: 'pending'});
    }),
    getStaffActions: jest.fn(async (userId, businessId) => JSON.stringify(rows.filter(row => row.userId === userId && row.businessId === businessId))),
    markStaffActionSynced: jest.fn(async (_userId, _businessId, clientId) => { rows = rows.filter(row => row.client_id !== clientId); }),
    markStaffActionRejected: jest.fn(async (_userId, _businessId, clientId, error) => {
      const row = rows.find(item => item.client_id === clientId);
      row.status = 'rejected';
      row.error = error;
    }),
  };
});

describe('staff SQLite outbox', () => {
  it('keeps a manual transaction on device when the network fails, then syncs once', async () => {
    api.post.mockRejectedValueOnce(new Error('offline'))
      .mockResolvedValueOnce({data: {transaction: {id: 44}}});
    const result = await recordStaffAction(account, 'transaction', {amount: 12, type: 'income'}, 'client-one');
    expect(result).toMatchObject({synced: false, clientId: 'client-one'});
    expect(await getStaffOfflineActions(account)).toHaveLength(1);
    await syncStaffOfflineActions(account);
    expect(api.post).toHaveBeenNthCalledWith(2, '/transactions', expect.objectContaining({client_id: 'client-one'}));
    expect(await getStaffOfflineActions(account)).toHaveLength(0);
  });

  it('does not delete an action on an ambiguous server response', async () => {
    api.post.mockResolvedValue({data: {}});
    await recordStaffAction(account, 'cash_order', {payment_method: 'cash', items: [{product_id: 3, quantity: 1}]}, 'order-one');
    expect(rows).toHaveLength(1);
    expect(NativeModules.NotificationModule.markStaffActionSynced).not.toHaveBeenCalled();
  });

  it('quarantines a rejected action and continues with a later one', async () => {
    rows = [
      {userId: 7, businessId: 2, client_id: 'bad', kind: 'stock_adjustment', payload: {stock_id: 3, client_id: 'bad'}, status: 'pending'},
      {userId: 7, businessId: 2, client_id: 'good', kind: 'transaction', payload: {client_id: 'good'}, status: 'pending'},
    ];
    api.post.mockRejectedValueOnce({response: {status: 422, data: {message: 'Insufficient stock'}}})
      .mockResolvedValueOnce({data: {transaction: {id: 99}}});
    await syncStaffOfflineActions(account);
    expect(rows).toHaveLength(1);
    expect(rows[0]).toMatchObject({client_id: 'bad', status: 'rejected', error: 'Insufficient stock'});
    expect(api.post).toHaveBeenCalledTimes(2);
  });

  it('does not read another staff account’s queued records', async () => {
    rows = [{userId: 8, businessId: 2, client_id: 'other', kind: 'transaction', payload: {}, status: 'pending'}];
    expect(await getStaffOfflineActions(account)).toEqual([]);
    await syncStaffOfflineActions(account);
    expect(api.post).not.toHaveBeenCalled();
  });

  it('keeps actions pending when the login expires until the same staff member signs in again', async () => {
    rows = [{userId: 7, businessId: 2, client_id: 'auth-wait', kind: 'transaction', payload: {client_id: 'auth-wait'}, status: 'pending'}];
    api.post.mockRejectedValue({response: {status: 401}});
    await syncStaffOfflineActions(account);
    expect(rows[0].status).toBe('pending');
    expect(NativeModules.NotificationModule.markStaffActionRejected).not.toHaveBeenCalled();
  });
});
