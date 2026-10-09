/**
 * OCR Service for Receipt Scanning.
 * Uses on-device ML Kit, so receipt scans do not require network access or an
 * AI credential in the mobile app.
 */

// Common keywords found in receipts
const AMOUNT_KEYWORDS = ['total', 'amount', 'subtotal', 'grand total', 'sum'];

// E-wallet and payment source patterns
const PAYMENT_SOURCES = {
  gcash: /gcash|g-?cash/i,
  maya: /maya|paymaya/i,
  grabpay: /grab\s?pay|grabpay/i,
  shopeepay: /shopee\s?pay|shopeepay/i,
  cash: /cash/i,
};

// Category detection patterns
const CATEGORY_PATTERNS = {
  // Expenses
  ingredients: /ingredient|supply|food|grocery|market/i,
  packaging: /packaging|container|cup|straw|plastic/i,
  utilities: /electric|water|internet|wifi|utility|bill/i,
  rent: /rent|rental|lease/i,
  salaries: /salary|wage|payroll|staff/i,
  equipment: /equipment|machine|appliance|tool/i,
  supplies: /office|supply|supplies|paper/i,
  
  // Income
  sales: /sales|customer|order|payment received/i,
  delivery: /delivery|grab|foodpanda|lalamove/i,
  catering: /catering|event|party|booking/i,
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
    console.error('OCR Error:', error);
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
  const lines = ocrText.split('\n').map(line => line.trim()).filter(Boolean);
  
  const parsed = {
    amount: null,
    date: null,
    merchant: null,
    source: 'Cash',
    category: null,
    type: 'Expense', // Default to expense
    confidence: 0,
    rawText: ocrText,
  };

  // Extract amount
  parsed.amount = extractAmount(lines);
  
  // Extract date
  parsed.date = extractDate(lines);
  
  // Extract merchant name
  parsed.merchant = extractMerchant(lines);
  
  // Detect payment source
  parsed.source = detectPaymentSource(ocrText);
  
  // Detect category and type
  const categoryResult = detectCategory(ocrText);
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
  
  for (const line of lines) {
    // Look for PHP currency patterns
    const phpPattern = /(?:php|₱|p)\s*(\d{1,3}(?:,\d{3})*(?:\.\d{2})?)/i;
    const phpMatch = line.match(phpPattern);
    if (phpMatch) {
      amounts.push(parseFloat(phpMatch[1].replace(/,/g, '')));
      continue;
    }
    
    // Look for "total" or "amount" followed by number
    const totalPattern = new RegExp(
      `(?:${AMOUNT_KEYWORDS.join('|')})\\s*:?\\s*([\\d,]+\\.?\\d*)`,
      'i'
    );
    const totalMatch = line.match(totalPattern);
    if (totalMatch) {
      amounts.push(parseFloat(totalMatch[1].replace(/,/g, '')));
      continue;
    }
    
    // Look for standalone numbers (likely amounts)
    const numberPattern = /(\d{1,3}(?:,\d{3})*(?:\.\d{2}))/;
    const numberMatch = line.match(numberPattern);
    if (numberMatch) {
      const num = parseFloat(numberMatch[1].replace(/,/g, ''));
      if (num > 10 && num < 1000000) { // Reasonable amount range
        amounts.push(num);
      }
    }
  }
  
  // Return the largest amount found (usually the total)
  return amounts.length > 0 ? Math.max(...amounts) : null;
};

/**
 * Extract date from receipt text
 */
const extractDate = (lines) => {
  const today = new Date();
  
  for (const line of lines) {
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
  return today.toISOString().split('T')[0];
};

/**
 * Extract merchant/store name
 */
const extractMerchant = (lines) => {
  // First line is usually merchant name
  if (lines.length > 0) {
    const firstLine = lines[0];
    // Clean up common receipt header text
    return firstLine
      .replace(/receipt|invoice|bill/gi, '')
      .trim()
      .substring(0, 50);
  }
  return 'Unknown Merchant';
};

/**
 * Detect payment source from text
 */
const detectPaymentSource = (text) => {
  for (const [source, pattern] of Object.entries(PAYMENT_SOURCES)) {
    if (pattern.test(text)) {
      // Capitalize first letter
      return source.charAt(0).toUpperCase() + source.slice(1);
    }
  }
  return 'Cash';
};

/**
 * Detect category and transaction type
 */
const detectCategory = (text) => {
  const textLower = text.toLowerCase();
  
  // Check for income indicators
  const incomeIndicators = [
    'received', 'payment received', 'income', 'revenue',
    'customer', 'order #', 'order no',
  ];
  
  const isIncome = incomeIndicators.some(indicator => 
    textLower.includes(indicator)
  );
  
  if (isIncome) {
    // Try to detect income category
    for (const [category, pattern] of Object.entries(CATEGORY_PATTERNS)) {
      if (['sales', 'delivery', 'catering'].includes(category) && pattern.test(text)) {
        return {
          type: 'Income',
          category: category.charAt(0).toUpperCase() + category.slice(1),
        };
      }
    }
    return { type: 'Income', category: 'Sales' };
  }
  
  // Try to detect expense category
  for (const [category, pattern] of Object.entries(CATEGORY_PATTERNS)) {
    if (pattern.test(text)) {
      return {
        type: 'Expense',
        category: category.charAt(0).toUpperCase() + category.slice(1),
      };
    }
  }
  
  // Default to expense with no category
  return { type: 'Expense', category: null };
};

/**
 * Calculate confidence score based on extracted data
 */
const calculateConfidence = (parsed) => {
  let score = 0;
  const weights = {
    amount: 40,
    date: 20,
    merchant: 15,
    source: 10,
    category: 15,
  };
  
  if (parsed.amount) score += weights.amount;
  if (parsed.date) score += weights.date;
  if (parsed.merchant && parsed.merchant !== 'Unknown Merchant') {
    score += weights.merchant;
  }
  if (parsed.source) score += weights.source;
  if (parsed.category) score += weights.category;
  
  return score;
};

/**
 * Format parsed data for transaction entry
 */
export const formatForTransaction = (parsed) => {
  return {
    amount: parsed.amount?.toFixed(2) || '',
    type: parsed.type,
    source: parsed.source,
    category: parsed.category || '',
    date: parsed.date || new Date().toISOString().split('T')[0],
    notes: parsed.merchant ? `From: ${parsed.merchant}` : '',
    entryMethod: 'OCR',
    confidence: parsed.confidence,
  };
};

export default {
  scanReceipt,
  parseReceiptData,
  formatForTransaction,
};
