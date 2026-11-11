// Local records are provisional. Server totals and stock remain authoritative after sync.
import {getStaffOfflineActions} from './staffOffline';

export const readPendingActions = async account => {
  try {return await getStaffOfflineActions(account);} catch {return [];}
};
export const pendingTransactions = actions => actions
  .filter(action => action.kind === 'transaction' && ['pending', 'rejected'].includes(action.status))
  .map(action => ({
    ...action.payload,
    id: `local:${action.client_id}`,
    client_id: action.client_id,
    entry_method: action.status === 'rejected' ? 'offline_rejected' : 'offline_pending',
    offlinePending: true,
    offlineRejected: action.status === 'rejected',
    offlineError: action.error || null,
    transaction_date: action.payload.date,
    description: action.payload.notes,
    created_at: new Date(action.created_at || Date.now()).toISOString(),
  }));

export const pendingOrders = actions => actions
  .filter(action => action.kind === 'cash_order' && ['pending', 'rejected'].includes(action.status))
  .map(action => ({
    ...action.payload,
    id: `local:${action.client_id}`,
    client_id: action.client_id,
    customer_name: action.payload.customer_name || 'Walk-in Customer',
    payment_method: 'cash',
    // Cash was received and the sale was completed on this phone. The server
    // still has to accept the order before shared stock and reports change.
    payment_status: action.status === 'rejected' ? 'offline_rejected' : 'confirmed',
    status: action.status === 'rejected' ? 'rejected' : 'completed',
    localCompleted: action.status === 'pending',
    offlinePending: true,
    offlineRejected: action.status === 'rejected',
    offlineError: action.error || null,
    total: Number(action.payload.items?.reduce((sum, item) =>
      sum + Number(item.expected_unit_price || 0) * Number(item.quantity || 0), 0) || 0),
    items: action.payload.items?.map((item, index) => ({
      ...item,
      product_name: action.payload._local_item_names?.[index] || 'Product',
      unit_price: Number(item.expected_unit_price || 0),
    })) || [],
    created_at: action.payload.offline_recorded_at || new Date(action.created_at || Date.now()).toISOString(),
    completed_at: action.status === 'pending'
      ? action.payload.offline_recorded_at || new Date(action.created_at || Date.now()).toISOString() : null,
  }));

// Show locally completed cash orders in activity without recording a second
// sale. The server creates the authoritative order transaction during sync.
export const pendingOrderTransactions = actions => pendingOrders(actions).map(order => {
  const recordedAt = new Date(order.created_at);
  const date = Number.isNaN(recordedAt.getTime()) ? String(order.created_at).slice(0, 10)
    : `${recordedAt.getFullYear()}-${String(recordedAt.getMonth() + 1).padStart(2, '0')}-${String(recordedAt.getDate()).padStart(2, '0')}`;
  const description = order.items.map(item => `${item.quantity}× ${item.product_name}`).join(', ')
    || order.customer_name;
  return {
    id: order.id,
    client_id: order.client_id,
    type: 'income',
    source: 'Cash',
    category: 'Sales',
    amount: order.total,
    transaction_date: date,
    description,
    entry_method: 'order_system',
    offlinePending: true,
    offlineRejected: order.offlineRejected,
    localCompleted: order.localCompleted,
    offlineError: order.offlineError,
    offlineAction: 'Order',
    created_at: order.created_at,
  };
});

export const filterCachedTransactions = (records, {search, source, category, from, to} = {}) => {
  const term = String(search || '').trim().toLowerCase();
  return records.filter(item => {
    const day = String(item.transaction_date || item.date || item.created_at || '').slice(0, 10);
    return (!from || day >= from) && (!to || day <= to)
      && (!source || source === 'All' || String(item.source || '').toLowerCase() === source.toLowerCase())
      && (!category || category === 'All' || String(item.category || '').toLowerCase() === category.toLowerCase())
      && (!term || [item.description, item.notes, item.category, item.source, item.type]
        .some(value => String(value || '').toLowerCase().includes(term)));
  });
};

const localState = action => ({
  offlinePending: action.status === 'pending',
  offlineRejected: action.status === 'rejected',
  offlineError: action.error || null,
});

export const overlayProductActions = (products, actions) => {
  const visible = [...products];
  actions.filter(action => ['pending', 'rejected'].includes(action.status)).forEach(action => {
    if (action.kind === 'product_create') {
      const values = action.payload.data || {};
      visible.unshift({...values, ...localState(action), id: `local:${action.client_id}`,
        client_id: action.client_id, is_available: false, price: Number(values.price || 0)});
    } else if (['product_update', 'product_delete'].includes(action.kind)) {
      const index = visible.findIndex(item => Number(item.id) === Number(action.payload.target_id));
      if (index >= 0) visible[index] = {...visible[index], ...localState(action),
        offlineAction: action.kind === 'product_delete' ? 'Delete' : 'Edit'};
    }
  });
  return visible;
};

export const overlayStockActions = (stock, actions) => {
  const visible = [...stock];
  actions.filter(action => action.kind === 'stock_create' && ['pending', 'rejected'].includes(action.status))
    .forEach(action => {
      const values = action.payload.data || {};
      visible.unshift({...values, ...localState(action), id: `local:${action.client_id}`,
        client_id: action.client_id, quantity: Number(values.current_quantity || 0),
        threshold: Number(values.minimum_quantity || 0), unitCost: Number(values.unit_cost || 0), active: false});
    });
  return visible;
};

export const overlayTransactionMutations = (records, actions) => records.map(item => {
  const action = actions.find(row => ['transaction_update', 'transaction_delete'].includes(row.kind)
    && ['pending', 'rejected'].includes(row.status)
    && Number(row.payload.target_id) === Number(item.id));
  return action ? {...item, ...localState(action),
    offlineAction: action.kind === 'transaction_delete' ? 'Delete' : 'Edit'} : item;
});
