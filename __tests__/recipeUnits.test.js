import {recipeQuantityForDisplay, recipeUnitOptions, toStockQuantity} from '../src/utils/recipeUnits';

describe('recipe quantities', () => {
  it('uses the stock unit for subtraction while allowing compatible recipe units', () => {
    expect(recipeUnitOptions('L')).toEqual(['L', 'ml']);
    expect(recipeUnitOptions('kg')).toEqual(['kg', 'g']);
    expect(toStockQuantity('10', 'ml', 'L')).toBe(0.01);
    expect(toStockQuantity('10', 'g', 'kg')).toBe(0.01);
    expect(recipeQuantityForDisplay(0.01, 'L')).toEqual({quantity: '10', unit: 'ml'});
    expect(recipeUnitOptions('ml')).toEqual(['ml', 'L']);
    expect(toStockQuantity('0.25', 'L', 'ml')).toBe(250);
  });

  it('rejects incompatible units and amounts too small for stock precision', () => {
    expect(toStockQuantity('10', 'g', 'L')).toBeNull();
    expect(toStockQuantity('0.01', 'ml', 'L')).toBeNull();
  });
});
