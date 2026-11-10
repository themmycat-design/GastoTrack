import {recipeQuantityForDisplay, recipeUnitOptions, toStockQuantity} from '../src/utils/recipeUnits';

describe('recipe quantities', () => {
  it('uses the stock unit for subtraction while allowing compatible recipe units', () => {
    expect(recipeUnitOptions('kg')).toEqual(['kg', 'g']);
    expect(toStockQuantity('10', 'g', 'kg')).toBe(0.01);
    expect(recipeQuantityForDisplay(0.01, 'kg')).toEqual({quantity: '10', unit: 'g'});
    expect(recipeUnitOptions('L')).toEqual(['L', 'ml']);
    expect(recipeUnitOptions('ml')).toEqual(['ml', 'L']);
    expect(toStockQuantity('250', 'ml', 'L')).toBe(0.25);
    expect(toStockQuantity('0.25', 'L', 'ml')).toBe(250);
    expect(recipeQuantityForDisplay(0.25, 'L')).toEqual({quantity: '250', unit: 'ml'});
    expect(recipeQuantityForDisplay(1500, 'ml')).toEqual({quantity: '1.5', unit: 'L'});
    expect(recipeQuantityForDisplay(1000.0001, 'ml')).toEqual({quantity: '1000.0001', unit: 'ml'});
  });

  it('rejects incompatible units and amounts too small for stock precision', () => {
    expect(toStockQuantity('10', 'g', 'L')).toBeNull();
    expect(toStockQuantity('0.01', 'ml', 'L')).toBeNull();
    expect(toStockQuantity('0.01', 'g', 'kg')).toBeNull();
  });
});
