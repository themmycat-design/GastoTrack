const categoryIcons = {
  sales: 'cash-register',
  tips: 'hand-coin-outline',
  'other income': 'wallet-plus-outline',
  ingredients: 'food-apple-outline',
  rent: 'home-city-outline',
  salaries: 'account-cash-outline',
  utilities: 'lightning-bolt-outline',
  supplies: 'package-variant-closed',
  'other expense': 'file-document-outline',
  'sales refund': 'cash-refund',
};

const categoryHints = [
  [/food|ingredient|milk|coffee|tea|sugar/i, 'food-apple-outline'],
  [/rent|lease/i, 'home-city-outline'],
  [/salary|salaries|wage|payroll/i, 'account-cash-outline'],
  [/electric|water|internet|utility|utilities/i, 'lightning-bolt-outline'],
  [/supply|supplies|packag/i, 'package-variant-closed'],
  [/deliver|transport|shipping/i, 'truck-delivery-outline'],
  [/tip|gratuity/i, 'hand-coin-outline'],
  [/sale|order/i, 'cash-register'],
];

export const transactionTypeIcon = type =>
  String(type || '').trim().toLowerCase() === 'income' ? 'cash-plus' : 'cash-minus';

export const transactionCategoryIcon = category => {
  const name = String(category || '').trim().toLowerCase();
  if (!name) return 'tag-outline';
  if (categoryIcons[name]) return categoryIcons[name];
  return categoryHints.find(([pattern]) => pattern.test(name))?.[1] || 'tag-outline';
};
