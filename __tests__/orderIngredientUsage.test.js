import {ingredientUsageForCartLine} from '../src/utils/orderIngredientUsage';

describe('ingredient use shown at checkout', () => {
  const product = {ingredients: [
    {stockId: 1, name: 'Milk', quantity: 200, unit: 'ml'},
    {stockId: 2, name: 'Pearls', quantity: 40, unit: 'g'},
  ]};

  it('multiplies each recipe amount by the number ordered', () => {
    expect(ingredientUsageForCartLine({product, quantity: 3})).toEqual([
      {stockId: 1, name: 'Milk', unit: 'ml', perProduct: 200, total: 600},
      {stockId: 2, name: 'Pearls', unit: 'g', perProduct: 40, total: 120},
    ]);
  });

  it('uses custom amounts, including a removed ingredient', () => {
    expect(ingredientUsageForCartLine({product, quantity: 2, ingredientOverrides: [
      {stock_item_id: 1, quantity: 100},
      {stock_item_id: 2, quantity: 0},
    ]}).map(ingredient => ingredient.total)).toEqual([200, 0]);
  });
});
