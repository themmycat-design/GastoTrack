import {transactionDisplayName} from '../src/utils/transactionDisplay';

describe('transaction display name', () => {
  it('shows entered names rather than category labels', () => {
    expect(transactionDisplayName({type: 'Expense', category: 'Ingredients', notes: 'Milk and sugar'})).toBe('Milk and sugar');
    expect(transactionDisplayName({type: 'Income', category: 'Sales', notes: 'Catering payment'})).toBe('Catering payment');
  });

  it('shows product names for order sales and a generic label for old unnamed records', () => {
    expect(transactionDisplayName({type: 'Income', category: 'Sales', description: '2× Brown Sugar Milk Tea'})).toBe('2× Brown Sugar Milk Tea');
    expect(transactionDisplayName({type: 'Expense', category: 'Ingredients'})).toBe('Expense transaction');
  });
});
