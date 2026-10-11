export const transactionDisplayName = transaction => {
  const name = String(transaction?.notes || transaction?.description || '').trim();
  if (name) return name;
  return String(transaction?.type || '').toLowerCase() === 'income'
    ? 'Income transaction'
    : 'Expense transaction';
};
