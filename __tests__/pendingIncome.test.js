import {pendingIncomeForDay} from '../src/utils/pendingIncome';

describe('pending daily income', () => {
  it('counts queued income and cash orders without treating rejected or expense entries as income', () => {
    expect(pendingIncomeForDay([
      {status: 'pending', kind: 'transaction', payload: {type: 'income', date: '2026-11-09', amount: 40}},
      {status: 'pending', kind: 'cash_order', payload: {offline_recorded_at: '2026-11-09T10:00:00',
        items: [{quantity: 2, expected_unit_price: 85}]}},
      {status: 'pending', kind: 'transaction', payload: {type: 'expense', date: '2026-11-09', amount: 30}},
      {status: 'rejected', kind: 'transaction', payload: {type: 'income', date: '2026-11-09', amount: 50}},
      {status: 'pending', kind: 'transaction', payload: {type: 'income', date: '2026-11-08', amount: 60}},
    ], '2026-11-09')).toEqual({amount: 210, count: 2});
  });
});
