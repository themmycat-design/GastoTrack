const smallerUnit = stockUnit => {
  const normalized = String(stockUnit || '').trim().toLowerCase();
  if (['l', 'liter', 'liters', 'litre', 'litres'].includes(normalized)) return 'ml';
  if (['kg', 'kilogram', 'kilograms'].includes(normalized)) return 'g';
  return null;
};

export const recipeUnitOptions = stockUnit => {
  const smaller = smallerUnit(stockUnit);
  return smaller ? [stockUnit, smaller] : [stockUnit];
};

export const recipeQuantityForDisplay = (quantity, stockUnit) => {
  const smaller = smallerUnit(stockUnit);
  const amount = Number(quantity);
  if (smaller && amount > 0 && amount < 1) {
    return {quantity: String(Number((amount * 1000).toFixed(4))), unit: smaller};
  }
  return {quantity: String(quantity ?? ''), unit: stockUnit};
};

export const toStockQuantity = (quantity, chosenUnit, stockUnit) => {
  const amount = Number(quantity);
  if (!Number.isFinite(amount) || amount <= 0) return null;
  const converted = chosenUnit === stockUnit
    ? amount
    : chosenUnit === smallerUnit(stockUnit) ? amount / 1000 : NaN;
  const rounded = Number(converted.toFixed(4));
  return Number.isFinite(converted) && converted > 0 && converted <= 99999999.9999
    && Math.abs(converted - rounded) < 0.0000001 && rounded >= 0.0001
    ? rounded : null;
};
