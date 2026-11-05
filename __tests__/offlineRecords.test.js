import {filterCachedTransactions, overlayProductActions, overlayStockActions, pendingOrders, pendingTransactions} from '../src/services/offlineRecords';

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
    expect(pendingOrders(actions)).toMatchObject([{id: 'local:order-1', status: 'queued', payment_status: 'offline_pending', total: 170}]);
  });

  it('filters cached transactions by day, source, category and search', () => {
    const records = pendingTransactions(actions);
    expect(filterCachedTransactions(records, {from: '2026-11-05', to: '2026-11-05', source: 'Cash', category: 'Ingredients', search: 'milk'})).toHaveLength(1);
    expect(filterCachedTransactions(records, {source: 'GCash'})).toHaveLength(0);
  });

  it('keeps unsynced product and stock creations visibly provisional', () => {
    const product = overlayProductActions([], [{kind: 'product_create', status: 'pending', client_id: 'p1', payload: {data: {name: 'Latte', price: 85}}}]);
    const stock = overlayStockActions([], [{kind: 'stock_create', status: 'pending', client_id: 's1', payload: {data: {name: 'Milk', current_quantity: 4}}}]);
    expect(product).toMatchObject([{id: 'local:p1', offlinePending: true, is_available: false}]);
    expect(stock).toMatchObject([{id: 'local:s1', offlinePending: true, active: false}]);
  });
});
