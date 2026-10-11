const normalizeState = value => String(value || '').trim().toLowerCase();

export const getEffectiveOrderPaymentStatus = order => {
  const orderStatus = normalizeState(order?.status);
  const paymentStatus = normalizeState(order?.paymentStatus ?? order?.payment_status);
  const paymentMethod = normalizeState(order?.paymentMethod ?? order?.payment_method);
  const paymentType = normalizeState(order?.payment_type ?? order?.paymentType);
  const isCash = paymentMethod === 'cash' || paymentType === 'cash';

  // Completing a cash order is the staff member's attestation that cash was
  // received. Older API/database rows may have payment_status missing or left
  // at its default, so don't display those completed cash sales as unpaid.
  if (orderStatus === 'completed' && isCash && (!paymentStatus || paymentStatus === 'pending')) {
    return 'confirmed';
  }

  return paymentStatus || 'pending';
};

export const getOrderHistoryStatus = order => {
  const orderStatus = normalizeState(order?.status) || 'pending';
  const paymentStatus = getEffectiveOrderPaymentStatus(order);

  if (orderStatus !== 'completed') return orderStatus.replace(/_/g, ' ');

  const unconfirmedPaymentLabels = {
    pending: 'awaiting payment',
    detected: 'payment verification required',
    failed: 'payment failed',
    refund_pending: 'refund pending',
    refunded: 'refunded',
  };

  return (unconfirmedPaymentLabels[paymentStatus] || orderStatus).replace(/_/g, ' ');
};

export const canManuallyConfirmOrderPayment = order => {
  const orderStatus = normalizeState(order?.status) || 'pending';
  const paymentStatus = getEffectiveOrderPaymentStatus(order);
  const paymentMethod = normalizeState(order?.paymentMethod ?? order?.payment_method);
  const paymentType = normalizeState(order?.payment_type ?? order?.paymentType);
  const isCashless = paymentType === 'cashless' || ['gcash', 'maya', 'bank'].includes(paymentMethod);
  const isConfirmableOrder = orderStatus === 'pending'
    || (orderStatus === 'completed' && Boolean(order?.financials_committed_at));

  return isCashless
    && isConfirmableOrder
    && ['pending', 'detected'].includes(paymentStatus);
};
