export const needsStockAlternativeCheck = (stockItems, hasMoreStock) =>
  Boolean(hasMoreStock) || stockItems.some(item => Number(item.quantity ?? item.current_quantity) <= 0);
