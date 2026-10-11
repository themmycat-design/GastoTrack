import {
  canManuallyConfirmOrderPayment,
  getEffectiveOrderPaymentStatus,
  getOrderHistoryStatus,
} from '../src/utils/orderHistoryStatus';

describe('Order History status labels', () => {
  it('does not label a completed but unpaid order as completed', () => {
    expect(getOrderHistoryStatus({status: 'completed', payment_status: 'pending'})).toBe('awaiting payment');
  });

  it('shows legacy detected payments as needing verification', () => {
    expect(getOrderHistoryStatus({status: 'completed', paymentStatus: 'detected'})).toBe('payment verification required');
  });

  it('keeps a completed, confirmed cash order completed', () => {
    expect(getOrderHistoryStatus({status: 'completed', payment_status: 'confirmed'})).toBe('completed');
  });

  it('treats a completed cash sale with a legacy pending value as confirmed', () => {
    const order = {status: 'completed', payment_method: 'cash', payment_status: 'pending'};
    expect(getEffectiveOrderPaymentStatus(order)).toBe('confirmed');
    expect(getOrderHistoryStatus(order)).toBe('completed');
  });

  it('does not infer confirmation for pending cashless orders', () => {
    expect(getEffectiveOrderPaymentStatus({
      status: 'pending',
      payment_method: 'gcash',
      payment_status: 'pending',
    })).toBe('pending');
  });

  it('defaults a missing order status to pending instead of completed', () => {
    expect(getOrderHistoryStatus({payment_status: 'pending'})).toBe('pending');
  });

  it('offers manual confirmation for a pending GCash order', () => {
    expect(canManuallyConfirmOrderPayment({
      status: 'pending',
      payment_status: 'pending',
      payment_method: 'gcash',
    })).toBe(true);
  });

  it('offers manual confirmation for a detected Maya payment', () => {
    expect(canManuallyConfirmOrderPayment({
      status: 'pending',
      paymentStatus: 'detected',
      paymentMethod: 'Maya',
    })).toBe(true);
  });

  it('does not offer wallet confirmation for cash orders or cancelled orders', () => {
    expect(canManuallyConfirmOrderPayment({
      status: 'pending',
      payment_status: 'pending',
      payment_method: 'cash',
    })).toBe(false);
    expect(canManuallyConfirmOrderPayment({
      status: 'cancelled',
      payment_status: 'pending',
      payment_method: 'gcash',
    })).toBe(false);
  });

  it('offers review on completed legacy records only when their accounting effects are flagged', () => {
    expect(canManuallyConfirmOrderPayment({
      status: 'completed',
      payment_status: 'pending',
      payment_method: 'maya',
    })).toBe(false);
    expect(canManuallyConfirmOrderPayment({
      status: 'completed',
      payment_status: 'detected',
      payment_method: 'maya',
      financials_committed_at: '2026-10-01T10:00:00Z',
    })).toBe(true);
  });
});
