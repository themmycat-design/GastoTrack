export const createIdempotencyKey = () =>
  'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, character => {
    const value = Math.floor(Math.random() * 16);
    return (character === 'x' ? value : (value % 4) + 8).toString(16);
  });

export const createOrderIdempotencyKey = createIdempotencyKey;

export const orderRequestSignature = payload => JSON.stringify({
  items: [...(payload.items || [])]
    .map(item => {
      const normalized = {product_id: Number(item.product_id), quantity: Number(item.quantity)};
      if (item.ingredient_overrides?.length) {
        normalized.ingredient_overrides = item.ingredient_overrides.map(ingredient => ({
          stock_item_id: Number(ingredient.stock_item_id),
          quantity: Number(ingredient.quantity),
        })).sort((left, right) => left.stock_item_id - right.stock_item_id);
      }
      return normalized;
    })
    .sort((left, right) => left.product_id - right.product_id
      || JSON.stringify(left.ingredient_overrides || []).localeCompare(JSON.stringify(right.ingredient_overrides || []))),
  payment_method: String(payload.payment_method || '').toLowerCase(),
  customer_name: payload.customer_name || 'Walk-in Customer',
  customer_phone: payload.customer_phone || null,
  notes: payload.notes || null,
});
