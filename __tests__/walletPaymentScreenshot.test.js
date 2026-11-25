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

  it('reads a GoTyme transfer to GCash without treating the sending app as the receiver', () => {
    const detail = 'Transferred\n₱ 100.00\ninstaPay\nTo\nMasked customer\nG-Xchange, Inc (GCash)\n'
      + 'From\n**** 3097\nGoTyme Bank\nFee\n₱ 0.00\nTotal\n₱ 100.00\n'
      + 'Reference No.\nITO240412104948002\nTrace ID\n948002\nDate\nApril 12 2024 at 06:49:49 PM';
    expect(parseWalletPaymentScreenshot(detail)).toEqual({
      source: 'GCash', amount: 100, sender: '', date: '2024-04-12', reference: 'ITO240412104948002',
    });
  });

  it('reads a complete Maya payment reference without using its reusable merchant reference', () => {
    const detail = 'Maya | Payment Received\nmaya BUSINESS\n20 Feb 2024, 3:38PM (PHT)\n'
      + 'Amount received\n₱ 68,740.00\nMerchant Reference No.\ntraveldealsph\n'
      + 'Maya Transaction No.\nMAYA-20240220-12345\nMaya Receipt No.\n405107123456';
    expect(parseWalletPaymentScreenshot(detail)).toEqual({
      source: 'Maya', amount: 68740, sender: '', date: '2024-02-20', reference: 'MAYA-20240220-12345',
    });
  });

  it('rejects a masked Maya transaction reference rather than using a merchant reference', () => {
    const detail = 'Maya | Payment Received\nAmount received\n₱ 68,740.00\n'
      + 'Merchant Reference No.\ntraveldealsph\nMaya Transaction No.\nc502-2007-3850-****\n'
      + 'Maya Receipt No.\n405107...';
    expect(parseWalletPaymentScreenshot(detail).reference).toBe('');
  });
});
