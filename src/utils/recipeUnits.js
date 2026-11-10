const alternateUnit = stockUnit => {
  const normalized = String(stockUnit || '').trim().toLowerCase();
  if (['kg', 'kilogram', 'kilograms'].includes(normalized)) return {unit: 'g', factor: 0.001};
  if (['l', 'liter', 'liters', 'litre', 'litres'].includes(normalized)) return {unit: 'ml', factor: 0.001};
  if (['ml', 'milliliter', 'milliliters', 'millilitre', 'millilitres'].includes(normalized)) return {unit: 'L', factor: 1000};
  return null;
};

export const recipeUnitOptions = stockUnit => {
  const alternate = alternateUnit(stockUnit);
  return alternate ? [stockUnit, alternate.unit] : [stockUnit];
};

export const recipeQuantityForDisplay = (quantity, stockUnit) => {
  const alternate = alternateUnit(stockUnit);
  const amount = Number(quantity);
  if (alternate && ((alternate.factor < 1 && amount > 0 && amount < 1) || (alternate.factor > 1 && amount >= 1000))) {
    const displayed = Number((amount / alternate.factor).toFixed(4));
    if (Math.abs(displayed * alternate.factor - amount) < 0.0000001) {
      return {quantity: String(displayed), unit: alternate.unit};
    }
  }
  return {quantity: String(quantity ?? ''), unit: stockUnit};
};

export const toStockQuantity = (quantity, chosenUnit, stockUnit) => {
  const amount = Number(quantity);
  if (!Number.isFinite(amount) || amount <= 0) return null;
  const alternate = alternateUnit(stockUnit);
  const converted = chosenUnit === stockUnit
    ? amount
    : chosenUnit === alternate?.unit ? amount * alternate.factor : NaN;
  const rounded = Number(converted.toFixed(4));
  return Number.isFinite(converted) && converted > 0 && converted <= 99999999.9999
    && Math.abs(converted - rounded) < 0.0000001 && rounded >= 0.0001
    ? rounded : null;
};
