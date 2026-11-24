import {isUnlabelledGCashRemittance, parseWalletPaymentScreenshot} from '../src/services/walletPaymentScreenshot';

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

  it('reads a received GCash notification without confusing its balance for the paid amount', () => {
    const notification = 'You have received P3000.00 from Jamie Cruz on 10-16-2026 05:23 PM. '
      + 'This GCash remittance has Reference Number\n2026101690660000000000004232473.\n'
      + 'Your new balance is P3020.57.\nGCash Ref. No. 1046194345157.';
    expect(parseWalletPaymentScreenshot(notification)).toEqual({
      source: 'GCash', amount: 3000, sender: 'Jamie Cruz', date: '2026-10-16', reference: '1046194345157',
    });
  });

  it('uses the destination wallet in a cross-wallet transfer detail', () => {
    const transfer = 'Transaction Details\nPHP 10.00\nFrom\nJamie Cruz\nGCash\n'
      + 'To\nTaylor Reyes\nMariBank: 1234567890\nTransfer Amount\nPHP 10.00\n'
      + 'Reference Number\n009354\nTransfer Method\ninstaPay\nTransaction Date & Time\n01 Nov 2026, 22:21';
    expect(parseWalletPaymentScreenshot(transfer)).toEqual({
      source: 'MariBank', amount: 10, sender: 'Jamie Cruz', date: '2026-11-01', reference: '009354',
    });
  });

  it('reads an incoming GCash remittance detail without inventing a wallet name or sender', () => {
    const detail = 'Transaction Details\nInternational Remittance\nAmount\n+1,000.00\n'
      + 'Date & Time\nNov 19, 2026 4:30 PM\nReference Number\n1047445192527';
    const parsed = parseWalletPaymentScreenshot(detail);
    expect(parsed).toEqual({
      source: '', amount: 1000, sender: '', date: '2026-11-19', reference: '1047445192527',
    });
    expect(isUnlabelledGCashRemittance(detail, parsed)).toBe(true);
    expect(isUnlabelledGCashRemittance(detail.replace('+1,000.00', 'PHP 1,000.00'), parsed)).toBe(false);
  });
});
