export const createOrderIdempotencyKey = () =>
  'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, character => {
    const value = Math.floor(Math.random() * 16);
    return (character === 'x' ? value : (value % 4) + 8).toString(16);
  });

export const orderRequestSignature = payload => JSON.stringify({
  items: [...(payload.items || [])]
    .map(item => ({product_id: Number(item.product_id), quantity: Number(item.quantity)}))
    .sort((left, right) => left.product_id - right.product_id),
  payment_method: String(payload.payment_method || '').toLowerCase(),
  customer_name: payload.customer_name || 'Walk-in Customer',
  customer_phone: payload.customer_phone || null,
  notes: payload.notes || null,
});
