import {filterCachedTransactions, overlayProductActions, overlayStockActions, pendingOrderTransactions, pendingOrders, pendingTransactions} from '../src/services/offlineRecords';

describe('offline staff records', () => {
  const actions = [
    {kind: 'transaction', status: 'pending', client_id: 'tx-1', created_at: 1000,
      payload: {type: 'expense', category: 'Ingredients', source: 'Cash', date: '2026-11-05', amount: 20, notes: 'Milk'}},
    {kind: 'transaction', status: 'rejected', client_id: 'tx-2', payload: {amount: 30}},
    {kind: 'cash_order', status: 'pending', client_id: 'order-1', created_at: 2000,
      payload: {customer_name: 'Walk-in Customer', items: [{quantity: 2, expected_unit_price: '85.00'}]}},
  ];

  it('shows only pending local records without calling them completed sales', () => {
    expect(pendingTransactions(actions)).toMatchObject([
      {id: 'local:tx-1', offlinePending: true, entry_method: 'offline_pending'},
      {id: 'local:tx-2', offlineRejected: true, entry_method: 'offline_rejected'},
    ]);
    expect(pendingOrders(actions)).toMatchObject([{id: 'local:order-1', status: 'completed', payment_status: 'confirmed', localCompleted: true, total: 170}]);
  });

  it('filters cached transactions by day, source, category and search', () => {
    const records = pendingTransactions(actions);
    expect(filterCachedTransactions(records, {from: '2026-11-05', to: '2026-11-05', source: 'Cash', category: 'Ingredients', search: 'milk'})).toHaveLength(1);
    expect(filterCachedTransactions(records, {source: 'GCash'})).toHaveLength(0);
  });

  it('projects device-completed cash orders as sales awaiting server sync', () => {
    const queued = {kind: 'cash_order', status: 'pending', client_id: 'order-2',
      payload: {offline_recorded_at: '2026-11-12T04:00:00.000Z',
        _local_item_names: ['Mountain Dew'], items: [{quantity: 2, expected_unit_price: '85.00'}]}};
    const [sale] = pendingOrderTransactions([queued]);

    expect(sale).toMatchObject({
      id: 'local:order-2', client_id: 'order-2', type: 'income', source: 'Cash',
      category: 'Sales', amount: 170, transaction_date: '2026-11-12',
      description: '2× Mountain Dew', entry_method: 'order_system', offlinePending: true, localCompleted: true,
    });
    expect(filterCachedTransactions([sale], {from: '2026-11-12', to: '2026-11-12', source: 'Cash', search: 'mountain dew'})).toHaveLength(1);
    expect(filterCachedTransactions([sale], {from: '2026-11-13', to: '2026-11-13'})).toHaveLength(0);
    expect(pendingOrderTransactions([{...queued, status: 'synced'}])).toHaveLength(0);
  });

  it('keeps rejected orders visible with their sync error', () => {
    expect(pendingOrderTransactions([{kind: 'cash_order', status: 'rejected', client_id: 'order-3', error: 'Insufficient stock',
      payload: {items: [{quantity: 1, expected_unit_price: '50.00'}]}}])).toMatchObject([
      {id: 'local:order-3', amount: 50, offlinePending: true, offlineRejected: true, localCompleted: false, offlineError: 'Insufficient stock'},
    ]);
  });

  it('keeps unsynced product and stock creations visibly provisional', () => {
    const product = overlayProductActions([], [{kind: 'product_create', status: 'pending', client_id: 'p1', payload: {data: {name: 'Latte', price: 85}}}]);
    const stock = overlayStockActions([], [{kind: 'stock_create', status: 'pending', client_id: 's1', payload: {data: {name: 'Milk', current_quantity: 4}}}]);
    expect(product).toMatchObject([{id: 'local:p1', offlinePending: true, is_available: false}]);
    expect(stock).toMatchObject([{id: 'local:s1', offlinePending: true, active: false}]);
  });
});
