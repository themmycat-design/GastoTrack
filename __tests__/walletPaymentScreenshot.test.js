import {parseWalletPaymentScreenshot} from '../src/services/walletPaymentScreenshot';

describe('payment screenshot extraction', () => {
  it('reads a payment app, amount, sender, date, and reference', () => {
    expect(parseWalletPaymentScreenshot('MariBank\nAmount received: ₱1,250.00\nFrom: Alex Cruz\nDate: Oct 12, 2026\nReference No: MARI-20261012-1234'))
      .toEqual({source: 'MariBank', amount: 1250, sender: 'Alex Cruz', date: '2026-10-12', reference: 'MARI-20261012-1234'});
  });

  it('does not guess an ambiguous amount', () => {
    expect(parseWalletPaymentScreenshot('GoTyme\nAmount: ₱85.00\nTotal: ₱95.00\nReference: GOTYME-123456').amount).toBeNull();
  });

  it('uses the screenshot app name when a transfer mentions another wallet', () => {
    expect(parseWalletPaymentScreenshot('GoTyme\nTransfer from GCash\nAmount received: PHP 85.00\nReference: GOTYME-123456').source).toBe('GoTyme');
  });
});
