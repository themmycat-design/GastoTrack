import {staffDayKey} from './useStaffDay';

const actionDay = value => {
  if (!value) return null;
  if (/^\d{4}-\d{2}-\d{2}$/.test(value)) return value;
  const timestamp = new Date(value);
  return Number.isNaN(timestamp.getTime()) ? null : staffDayKey(timestamp);
};

export const pendingIncomeForDay = (actions, day) => (actions || []).reduce((total, action) => {
  if (action.status !== 'pending') return total;
  const payload = action.payload || {};
  if (action.kind === 'transaction' && String(payload.type).toLowerCase() === 'income'
    && actionDay(payload.date) === day) {
    return {amount: total.amount + Number(payload.amount || 0), count: total.count + 1};
  }
  if (action.kind === 'cash_order' && actionDay(payload.offline_recorded_at) === day) {
    const amount = (payload.items || []).reduce((sum, item) =>
      sum + Number(item.expected_unit_price || 0) * Number(item.quantity || 0), 0);
    return {amount: total.amount + amount, count: total.count + 1};
  }
  return total;
}, {amount: 0, count: 0});
