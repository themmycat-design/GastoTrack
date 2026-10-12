import TextRecognition from '@react-native-ml-kit/text-recognition';
import {buildReceiptCategoryDescription, formatForTransaction, parseReceiptData, scanReceipt, suggestReceiptCategory} from '../src/services/OCRService';

jest.mock('@react-native-ml-kit/text-recognition', () => ({
  __esModule: true,
  default: {recognize: jest.fn()},
}));

const expenseCategories = ['Ingredients', 'Rent', 'Salaries', 'Utilities', 'Supplies', 'Other Expense'];
const incomeCategories = ['Sales', 'Tips', 'Other Income'];

describe('receipt scanning and review suggestions', () => {
  it('reads text from the installed on-device OCR adapter', async () => {
    TextRecognition.recognize.mockResolvedValueOnce({text: 'Fresh milk\nTOTAL PHP 180.00', blocks: []});
    await expect(scanReceipt('file:///receipt.jpg')).resolves.toMatchObject({
      success: true, text: 'Fresh milk\nTOTAL PHP 180.00', method: 'mlkit',
    });
    expect(TextRecognition.recognize).toHaveBeenCalledWith('file:///receipt.jpg');
  });

  it('reports OCR failure without inventing a transaction', async () => {
    TextRecognition.recognize.mockRejectedValueOnce(new Error('Unreadable image'));
    await expect(scanReceipt('file:///blurry.jpg')).resolves.toMatchObject({success: false, error: 'Unreadable image'});
  });

  it('uses the receipt total, not cash tendered, and selects Ingredients', () => {
    const parsed = parseReceiptData('Food wholesaler\nFresh milk 2L PHP 180.00\nTOTAL PHP 180.00\nCASH TENDERED PHP 500.00');
    expect(parsed.amount).toBe(180);
    expect(parsed.type).toBe('Expense');
    expect(formatForTransaction(parsed, expenseCategories).category).toBe('Ingredients');
  });

  it('matches packaging to an available shop category, never an invisible option', () => {
    const parsed = parseReceiptData('Milk Tea Shop\nPaper cups\nTOTAL PHP 95.00');
    expect(formatForTransaction(parsed, expenseCategories).category).toBe('Supplies');
    expect(formatForTransaction(parsed, [...expenseCategories, 'Packaging']).category).toBe('Packaging');
  });

  it('selects Utilities and a custom category when supported', () => {
    const utility = parseReceiptData('Electricity bill\nAmount due PHP 1250.00');
    expect(formatForTransaction(utility, expenseCategories).category).toBe('Utilities');
    const repair = parseReceiptData('Equipment Repair\nTotal PHP 800.00');
    expect(formatForTransaction(repair, [...expenseCategories, 'Equipment Repair']).category).toBe('Equipment Repair');
  });

  it('does not classify a supplier receipt as income merely because it says received by', () => {
    const parsed = parseReceiptData('Received by: shop staff\nCoffee beans\nTOTAL PHP 420.00');
    expect(parsed.type).toBe('Expense');
    expect(formatForTransaction(parsed, expenseCategories).category).toBe('Ingredients');
  });

  it('recognizes printed date and wallet source without crediting default values as OCR evidence', () => {
    const dated = parseReceiptData('Milk shop\n2026-10-09\nPaid via GCash\nTOTAL PHP 85.00');
    expect(dated.date).toBe('2026-10-09');
    expect(dated.source).toBe('GCash');
    expect(dated.dateDetected).toBe(true);
    expect(dated.sourceDetected).toBe(true);
    const undated = parseReceiptData('Milk shop\nTOTAL PHP 85.00');
    expect(undated.dateDetected).toBe(false);
    expect(undated.sourceDetected).toBe(false);
    expect(undated.confidence).toBeLessThan(dated.confidence);
    expect(parseReceiptData('Maya Cafe\nCASH TENDERED PHP 100.00\nTOTAL PHP 85.00').source).toBe('Cash');
  });

  it('extracts a useful receipt name while skipping receipt headers and identifiers', () => {
    const parsed = parseReceiptData('OFFICIAL RECEIPT\nReceipt No: 1234567890\nFresh Start Cafe\nAddress: 42 Main Road\n2026-10-10\nIced Latte 2 x 90.00\nTOTAL PHP 180.00');
    expect(parsed.merchant).toBe('Fresh Start Cafe');
    expect(parsed.date).toBe('2026-10-10');
    expect(parsed.amount).toBe(180);
  });

  it('sends only receipt name and item details for server-side AI categorization', () => {
    const parsed = parseReceiptData([
      'Fresh Start Cafe',
      'Receipt No: 1234567890',
      'Address: 42 Main Road',
      'Phone: 09171234567',
      'Customer: Juan Dela Cruz',
      '2026-10-10',
      'Iced Latte 2 x 90.00',
      'TOTAL PHP 180.00',
      'Cash tendered PHP 500.00',
      'GCash Ref: 987654321012',
    ].join('\n'));
    const description = buildReceiptCategoryDescription(parsed);
    expect(description).toContain('Fresh Start Cafe');
    expect(description).toContain('Iced Latte');
    expect(description).not.toContain('1234567890');
    expect(description).not.toContain('42 Main Road');
    expect(description).not.toContain('09171234567');
    expect(description).not.toContain('Juan Dela Cruz');
    expect(description).not.toContain('2026-10-10');
    expect(description).not.toContain('180.00');
    expect(description).not.toContain('987654321012');
  });

  it('recognizes explicit income and leaves unknown categories for review', () => {
    const income = parseReceiptData('Daily sales report\nCake order\nTOTAL PHP 420.00');
    expect(income.type).toBe('Income');
    expect(formatForTransaction(income, incomeCategories).category).toBe('Sales');
    expect(parseReceiptData('Payment received\nCoffee beans\nTOTAL PHP 420.00').type).toBe('Expense');
    const unknown = parseReceiptData('Vendor ABC\nTOTAL PHP 50.00');
    expect(suggestReceiptCategory(unknown, expenseCategories)).toBe('');
  });
});
