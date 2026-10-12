/**
 * OCR Service for Receipt Scanning.
 * Uses on-device ML Kit, so receipt scans do not require network access or an
 * AI credential in the mobile app.
 */

// Common keywords found in receipts
const AMOUNT_KEYWORDS = ['grand total', 'total due', 'amount due', 'total', 'amount paid'];

// E-wallet and payment source patterns
const PAYMENT_SOURCES = {
  gcash: /gcash|g-?cash/i,
  maya: /maya|paymaya/i,
  grabpay: /grab\s?pay|grabpay/i,
  shopeepay: /shopee\s?pay|shopeepay/i,
  cash: /cash/i,
};
const PAYMENT_SOURCE_LABELS = {gcash: 'GCash', maya: 'Maya', grabpay: 'GrabPay', shopeepay: 'ShopeePay', cash: 'Cash'};

// These are suggestions only; the review screen must match them against the
// business's currently available categories before selecting one.
const CATEGORY_RULES = [
  {type: 'Expense', category: 'Ingredients', pattern: /\b(?:ingredients?|milk|coffee|beans?|sugar|flour|tea|syrup|eggs?|butter|cream|chocolate|yeast|baking|grocery|groceries)\b/i},
  {type: 'Expense', category: 'Rent', pattern: /\b(?:rent|rental|lease)\b/i},
  {type: 'Expense', category: 'Salaries', pattern: /\b(?:salary|salaries|wages?|payroll)\b/i},
  {type: 'Expense', category: 'Utilities', pattern: /\b(?:electricity|electric|water bill|internet|wifi|utilities|utility)\b/i},
  {type: 'Expense', category: 'Packaging', pattern: /\b(?:packaging|containers?|cups?|lids?|straws?|plastic bags?)\b/i},
  {type: 'Expense', category: 'Supplies', pattern: /\b(?:supplies|stationery|office|paper|napkins?|tissues?)\b/i},
  {type: 'Expense', category: 'Equipment', pattern: /\b(?:equipment|machine|appliance|blender|oven|refrigerator)\b/i},
  {type: 'Income', category: 'Tips', pattern: /\b(?:tips?|gratuity)\b/i},
  {type: 'Income', category: 'Catering', pattern: /\b(?:catering|event booking)\b/i},
  {type: 'Income', category: 'Delivery', pattern: /\b(?:delivery sale|foodpanda payout|grabfood payout)\b/i},
  {type: 'Income', category: 'Sales', pattern: /\b(?:sales?|customer payment|payment received)\b/i},
];

const localDate = () => {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
};

const matchingCategory = (categories, name) => categories.find(category => category.toLowerCase() === name.toLowerCase());

export const suggestReceiptCategory = (parsed, categories, type = parsed.type) => {
  if (!Array.isArray(categories) || !categories.length) return '';
  const text = String(parsed.rawText || '');
  // A custom category written explicitly on the receipt takes priority.
  const explicit = [...categories].sort((a, b) => b.length - a.length).find(category => {
    if (category.length < 4 || /^other (?:income|expense)$/i.test(category)) return false;
    const escaped = category.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return new RegExp(`\\b${escaped}\\b`, 'i').test(text);
  });
  if (explicit) return explicit;

  const detected = type === parsed.type && parsed.category
    ? parsed.category
    : CATEGORY_RULES.find(rule => rule.type === type && rule.pattern.test(text))?.category;
  if (!detected) return '';
  const direct = matchingCategory(categories, detected);
  if (direct) return direct;
  // Only use a fallback when it has a clear accounting equivalent.
  if (type === 'Expense' && ['Packaging', 'Equipment'].includes(detected)) {
    return matchingCategory(categories, 'Supplies') || '';
  }
  if (type === 'Income' && ['Catering', 'Delivery'].includes(detected)) {
    return matchingCategory(categories, 'Other Income') || '';
  }
  return '';
};

/**
 * Scan receipt image and extract text using on-device ML Kit.
 */
export const scanReceipt = async (imageUri) => {
  try {
    const TextRecognition = require('@react-native-ml-kit/text-recognition').default;
    const result = await TextRecognition.recognize(imageUri);
    return {
      success: true,
      text: result.text,
      blocks: result.blocks,
      method: 'mlkit',
    };
  } catch (error) {
    return {
      success: false,
      error: error.message,
    };
  }
};

/**
 * Parse receipt text and extract transaction data
 */
export const parseReceiptData = (ocrText) => {
  const text = String(ocrText || '');
  const lines = text.split('\n').map(line => line.trim()).filter(Boolean);
  
  const parsed = {
    amount: null,
    date: null,
    merchant: null,
    source: 'Cash',
    category: null,
    type: 'Expense', // Default to expense
    confidence: 0,
    rawText: text,
  };

  // Extract amount
  parsed.amount = extractAmount(lines);
  
  // Extract date
  const detectedDate = extractDate(lines);
  parsed.date = detectedDate || localDate();
  parsed.dateDetected = Boolean(detectedDate);
  
  // Extract merchant name
  parsed.merchant = extractMerchant(lines);
  
  // Detect payment source
  const detectedSource = detectPaymentSource(text);
  parsed.source = detectedSource || 'Cash';
  parsed.sourceDetected = Boolean(detectedSource);
  
  // Detect category and type
  const categoryResult = detectCategory(text);
  parsed.category = categoryResult.category;
  parsed.type = categoryResult.type;
  
  // Calculate confidence score
  parsed.confidence = calculateConfidence(parsed);
  
  return parsed;
};

/**
 * Extract amount from receipt text
 * Looks for numbers with currency symbols or after "total" keywords
 */
const extractAmount = (lines) => {
  const amounts = [];
  const totals = [];
  
  for (const line of lines) {
    if (/\bsub\s*total\b/i.test(line)) continue;
    const totalPattern = new RegExp(
      `(?:${AMOUNT_KEYWORDS.join('|')})\\s*:?\\s*(?:PHP|₱|P)?\\s*([\\d,]+(?:\\.\\d{2})?)`,
      'i'
    );
    const totalMatch = line.match(totalPattern);
    if (totalMatch) {
      totals.push(parseFloat(totalMatch[1].replace(/,/g, '')));
      continue;
    }
    if (/\b(?:change|tendered|cash received|balance)\b/i.test(line)) continue;
    const phpPattern = /(?:\bPHP\s*|₱\s*|\bP\s*)(\d{1,3}(?:,\d{3})*(?:\.\d{2})?)/i;
    const phpMatch = line.match(phpPattern);
    if (phpMatch) {
      amounts.push(parseFloat(phpMatch[1].replace(/,/g, '')));
      continue;
    }
    const numberPattern = /(\d{1,3}(?:,\d{3})*(?:\.\d{2}))/;
    const numberMatch = line.match(numberPattern);
    if (numberMatch) {
      const num = parseFloat(numberMatch[1].replace(/,/g, ''));
      if (num > 10 && num < 1000000) { // Reasonable amount range
        amounts.push(num);
      }
    }
  }
  
  // Prefer an explicit total over cash tendered or a larger line-item price.
  return totals.length ? totals[totals.length - 1] : amounts.length ? Math.max(...amounts) : null;
};

/**
 * Extract date from receipt text
 */
const extractDate = (lines) => {
  for (const line of lines) {
    const isoMatch = line.match(/\b(20\d{2})-(\d{2})-(\d{2})\b/);
    if (isoMatch) return `${isoMatch[1]}-${isoMatch[2]}-${isoMatch[3]}`;
    // Pattern: MM/DD/YYYY or DD/MM/YYYY
    const datePattern1 = /(\d{1,2})[/-](\d{1,2})[/-](\d{2,4})/;
    const match1 = line.match(datePattern1);
    if (match1) {
      const part1 = match1[1];
      const part2 = match1[2];
      const year = match1[3];
      const fullYear = year.length === 2 ? `20${year}` : year;
      // Assume MM/DD/YYYY format
      return `${fullYear}-${part1.padStart(2, '0')}-${part2.padStart(2, '0')}`;
    }
    
    // Pattern: Month DD, YYYY
    const datePattern2 = /(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\s+(\d{1,2}),?\s+(\d{4})/i;
    const match2 = line.match(datePattern2);
    if (match2) {
      const month = match2[1];
      const day = match2[2];
      const year = match2[3];
      const monthMap = {
        jan: '01', feb: '02', mar: '03', apr: '04',
        may: '05', jun: '06', jul: '07', aug: '08',
        sep: '09', oct: '10', nov: '11', dec: '12',
      };
      const monthNum = monthMap[month.toLowerCase().substring(0, 3)];
      return `${year}-${monthNum}-${day.padStart(2, '0')}`;
    }
  }
  
  // Default to today
  return null;
};

/**
 * Extract merchant/store name
 */
const extractMerchant = (lines) => {
  const headerNoise = /\b(?:official receipt|sales invoice|receipt\s*(?:no|number|#)|invoice\s*(?:no|number|#)|or\s*(?:no|number|#)|date|time|tin|vat|telephone|tel\.?|phone|mobile|email|address|cashier|customer copy|transaction id|reference|total|subtotal|amount|change|cash|gcash|maya|tax|discount|quantity|price)\b/i;
  const merchant = lines
    .map(line => line.replace(/^(?:official\s+)?(?:receipt|invoice|bill)\b\s*[:#-]?\s*/i, '').trim())
    .find(line => (
      line.length >= 2
      && /[a-z]/i.test(line)
      && !headerNoise.test(line)
      && !/\b\d{8,}\b/.test(line)
    ));

  return merchant?.substring(0, 80) || 'Unknown Merchant';
};

/**
 * Keep receipt classification on the server while limiting the OCR text sent
 * for categorization to merchant/item details, excluding common personal and
 * payment identifiers from the request.
 */
export const buildReceiptCategoryDescription = (parsed, transactionName = parsed?.merchant) => {
  const privateLine = /(?:\b(?:address|phone|mobile|telephone|email|e-mail|tin|vat\s*(?:reg|registration)?|(?:or|receipt|invoice)\s*(?:no|number|#)|reference|transaction\s*(?:id|no|number)|card\s*(?:no|number)|cashier|customer(?:\s*(?:name|id))?|serial\s*(?:no|number)|terminal\s*(?:id|no|number)|approval\s*(?:code|no)|auth(?:orization)?\s*code)\b|[\w.+-]+@[\w.-]+\.[a-z]{2,}|\b(?:\+?63|0)\d(?:[\s-]?\d){8,}\b|\b\d{10,}\b)/i;
  const receiptNoise = /\b(?:sub\s*total|grand\s*total|total\s*(?:due|paid)?|amount\s*(?:due|paid)|cash tendered|change|paid via|payment method|gcash|maya|cash|receipt|invoice|date|time|reference)\b/i;
  const dateOnly = /^\s*(?:20\d{2}-\d{1,2}-\d{1,2}|\d{1,2}[/-]\d{1,2}[/-]\d{2,4})\s*$/;
  const merchant = String(transactionName || '').trim();
  const itemLines = String(parsed?.rawText || '')
    .split('\n')
    .map(line => line.trim())
    .filter(line => line.length >= 2 && !privateLine.test(line) && !receiptNoise.test(line) && !dateOnly.test(line))
    .slice(0, 12);

  return [...new Set([merchant, ...itemLines].filter(Boolean))].join(' | ').slice(0, 500);
};

/**
 * Detect payment source from text
 */
const detectPaymentSource = (text) => {
  const paymentLines = text.split('\n').filter(line => /\b(?:paid|payment|tendered|method|via|mode of payment)\b/i.test(line));
  for (const candidate of [paymentLines.join('\n'), text.split('\n').slice(1).join('\n'), text]) {
    for (const [source, pattern] of Object.entries(PAYMENT_SOURCES)) {
      if (pattern.test(candidate)) return PAYMENT_SOURCE_LABELS[source];
    }
  }
  return null;
};

/**
 * Detect category and transaction type
 */
const detectCategory = (text) => {
  // A shop buying ingredients can have "received by" on its receipt; that
  // alone must never turn an expense into income.
  const type = /\b(?:sales report|daily sales|sales income|revenue report|income receipt|tips received|catering payout)\b/i.test(text)
    ? 'Income' : 'Expense';
  const itemText = text.split('\n').slice(1)
    .filter(line => !/\b(?:total|amount due|cash|gcash|maya|receipt|invoice)\b/i.test(line))
    .join('\n');
  const category = CATEGORY_RULES.find(rule => rule.type === type && rule.pattern.test(itemText))?.category
    || CATEGORY_RULES.find(rule => rule.type === type && rule.pattern.test(text))?.category
    || null;
  return {type, category};
};

/**
 * Calculate confidence score based on extracted data
 */
const calculateConfidence = (parsed) => {
  let score = 0;
  const weights = {
    amount: 45,
    date: 25,
    merchant: 20,
    source: 10,
  };
  
  if (parsed.amount) score += weights.amount;
  if (parsed.dateDetected) score += weights.date;
  if (parsed.merchant && parsed.merchant !== 'Unknown Merchant') {
    score += weights.merchant;
  }
  if (parsed.sourceDetected) score += weights.source;
  return score;
};

/**
 * Format parsed data for transaction entry
 */
export const formatForTransaction = (parsed, availableCategories) => {
  return {
    amount: parsed.amount?.toFixed(2) || '',
    type: parsed.type,
    source: parsed.source,
    category: availableCategories ? suggestReceiptCategory(parsed, availableCategories, parsed.type) : parsed.category || '',
    date: parsed.date || localDate(),
    notes: parsed.merchant && parsed.merchant !== 'Unknown Merchant' ? parsed.merchant : '',
    entryMethod: 'OCR',
    confidence: parsed.confidence,
  };
};

export default {
  scanReceipt,
  parseReceiptData,
  buildReceiptCategoryDescription,
  formatForTransaction,
};
