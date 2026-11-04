import glyphs from 'react-native-vector-icons/glyphmaps/MaterialCommunityIcons.json';
import {transactionCategoryIcon, transactionTypeIcon} from '../src/utils/transactionIcons';

describe('transaction icons', () => {
  it('uses different valid icons for income, expense, and every default category', () => {
    const categories = [
      'Sales', 'Tips', 'Other Income', 'Ingredients', 'Rent',
      'Salaries', 'Utilities', 'Supplies', 'Other Expense',
    ];
    const icons = categories.map(transactionCategoryIcon);

    expect(new Set(icons).size).toBe(categories.length);
    [...icons, transactionTypeIcon('Income'), transactionTypeIcon('Expense')]
      .forEach(icon => expect(glyphs[icon]).toBeDefined());
    expect(transactionTypeIcon('Income')).not.toBe(transactionTypeIcon('Expense'));
  });

  it('uses a relevant icon or a safe fallback for custom categories', () => {
    expect(transactionCategoryIcon('Delivery fees')).toBe('truck-delivery-outline');
    expect(transactionCategoryIcon('Marketing')).toBe('tag-outline');
    expect(transactionCategoryIcon('')).toBe('tag-outline');
  });
});
