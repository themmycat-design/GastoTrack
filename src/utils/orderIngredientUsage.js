export const ingredientUsageForCartLine = item => {
  const quantity = Number(item.quantity) || 0;
  return (item.product?.ingredients || []).map(ingredient => {
    const override = (item.ingredientOverrides || []).find(
      candidate => Number(candidate.stock_item_id) === Number(ingredient.stockId),
    );
    const perProduct = override ? Number(override.quantity) : Number(ingredient.quantity);
    return {
      stockId: Number(ingredient.stockId),
      name: ingredient.name || 'Ingredient',
      unit: ingredient.unit || '',
      perProduct,
      total: Number((perProduct * quantity).toFixed(4)),
    };
  });
};
