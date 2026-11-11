/**
 * OCR Service for Receipt Scanning.
 * Uses on-device ML Kit, so receipt scans do not require network access or an
 * AI credential in the mobile app.
 */

// Common keywords found in receipts
const AMOUNT_KEYWORDS = ['grand total', 'total due', 'amount due', 'amount payable', 'amount to pay', 'net amount', 'total', 'amount paid', 'amount'];

// E-wallet and payment source patterns
const PAYMENT_SOURCES = {
  gcash: /\bg-?cash\b/i,
  maya: /\b(?:paymaya|maya)\b/i,
  grabpay: /\bgrab\s?pay\b/i,
  shopeepay: /\bshopee\s?pay\b/i,
  bank: /\bbank\s*(?:transfer|payment)\b/i,
  card: /\b(?:credit\s*card|card\s*payment)\b/i,
  cash: /\bcash\b/i,
};
const PAYMENT_SOURCE_LABELS = {gcash: 'GCash', maya: 'Maya', grabpay: 'GrabPay', shopeepay: 'ShopeePay', bank: 'Bank Transfer', card: 'Credit Card', cash: 'Cash'};

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
export const parseReceiptData = (ocrText, blocks = []) => {
  const text = String(ocrText || '');
  const lines = text.split('\n').map(line => line.trim()).filter(Boolean);
  
  const parsed = {
    amount: null,
    date: null,
    merchant: null,
    source: 'Cash',
    category: null,
    type: null,
    confidence: 0,
    rawText: text,
  };

  // Extract amount
  parsed.amount = extractAmount(lines);
  
  // Extract date
  const detectedDate = extractDate(lines);
  parsed.date = detectedDate;
  parsed.dateDetected = Boolean(detectedDate);
  
  // Extract merchant name
  parsed.merchant = extractMerchant(lines, blocks);
  
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
 * An automatic transaction requires an explicit total. A larger tendered
 * amount or an item price is never a safe substitute for a missing total.
 */
const extractAmount = (lines) => {
  const totals = [];
  const totalPattern = new RegExp(`\\b(?:${AMOUNT_KEYWORDS.join('|')})\\b`, 'i');
  const moneyPattern = /^(?:PHP|₱|P\s*)?\s*([\d,]+(?:\.\d{1,2})?)\s*$/i;
  for (let index = 0; index < lines.length; index += 1) {
    const line = lines[index];
    if (/\b(?:sub\s*total|total\s+(?:items?|savings?|discount)|cash\s+tendered|change|balance)\b/i.test(line)) continue;
    const label = totalPattern.exec(line);
    if (!label) continue;
    const sameLine = line.slice(label.index + label[0].length).replace(/^\s*[:-]?\s*/, '');
    const candidate = moneyPattern.exec(sameLine)
      || (sameLine === '' ? moneyPattern.exec(lines[index + 1] || '') : null);
    if (!candidate) continue;
    const amount = Number(candidate[1].replace(/,/g, ''));
    if (Number.isFinite(amount) && amount > 0 && amount <= 99999999.99) totals.push(amount);
  }
  const uniqueCents = [...new Set(totals.map(amount => Math.round(amount * 100)))];
  return uniqueCents.length === 1 ? uniqueCents[0] / 100 : null;
};

/**
 * Extract date from receipt text
 */
const extractDate = (lines) => {
  const validDate = (year, month, day) => {
    const date = new Date(year, month - 1, day);
    const endOfToday = new Date();
    endOfToday.setHours(23, 59, 59, 999);
    return date.getFullYear() === year && date.getMonth() === month - 1
      && date.getDate() === day && date <= endOfToday
      ? `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}` : null;
  };
  for (const line of lines) {
    const isoMatch = line.match(/\b(20\d{2})-(\d{2})-(\d{2})\b/);
    if (isoMatch) return validDate(Number(isoMatch[1]), Number(isoMatch[2]), Number(isoMatch[3]));
    // A date such as 03/04 is ambiguous without a printed format cue.
    const datePattern1 = /(\d{1,2})[/-](\d{1,2})[/-](\d{2,4})/;
    const match1 = line.match(datePattern1);
    if (match1) {
      const first = Number(match1[1]);
      const second = Number(match1[2]);
      const year = Number(match1[3].length === 2 ? `20${match1[3]}` : match1[3]);
      if (first > 12 && second <= 12) return validDate(year, second, first);
      if (second > 12 && first <= 12) return validDate(year, first, second);
      if (first === second) return validDate(year, first, second);
      // Use a numeric date only when one interpretation is a real past date.
      const dayFirst = validDate(year, second, first);
      const monthFirst = validDate(year, first, second);
      return dayFirst && !monthFirst ? dayFirst : monthFirst && !dayFirst ? monthFirst : null;
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
      return validDate(Number(year), Number(monthNum), Number(day));
    }
  }
  
  return null;
};

/**
 * Extract merchant/store name
 */
const extractMerchant = (lines, blocks = []) => {
  const genericLines = /^(?:official\s+receipt|sales\s+invoice|receipt|invoice|bill|tax\s+invoice|customer\s+copy|merchant\s+copy|duplicate|thank\s+you(?:\s+for\s+your\s+(?:visit|purchase))?|welcome|come\s+again|daily\s+sales\s+report|sales\s+report|payment\s+received|electricity\s+bill|water\s+bill|internet\s+bill|utility\s+bill)$/i;
  const metadataLine = /^(?:receipt\s*(?:no|number|#)|invoice\s*(?:no|number|#)|or\s*(?:no|number|#)|date|time|address|tel(?:ephone)?\.?|phone|mobile|email|tin|vat|cashier|customer|served\s+by|table|order\s*(?:no|number|#)?|terminal|transaction\s*(?:id|no|number)|reference|ref\.?\s*(?:no|number|#)?|branch\s*(?:no|number|#)?|store\s*(?:no|number|#)?)\b/i;
  const receiptBodyStart = /^(?:date|time|address|tel(?:ephone)?\.?|phone|mobile|email|tin|vat|cashier|customer|served\s+by|table|items?|description|qty|quantity|unit\s+price|subtotal|grand\s+total|total\s+due|amount\s+due|payment|paid\s+via|cash\s+tendered|change|balance)\b/i;
  const businessWords = /\b(?:cafe|caf[e\u00e9]|bakery|bakeshop|restaurant|eatery|coffee|tea\s+shop|milk\s+tea|market|supermarket|grocery|store|shop|bistro|diner|foods?|food\s+services?|trading|corporation|corp\.?|inc\.?|limited|ltd\.?|enterprises?|company|co\.?)\b/i;
  const priceOrIdentifier = /(?:\u20B1|PHP|\bP\s*)\s*\d|\b\d{8,}\b|\b\d+[.,]\d{2}\b|[\w.+-]+@[\w.-]+\.[a-z]{2,}/i;
  const addressText = /\b(?:street|st\.|road|rd\.|avenue|ave\.|boulevard|blvd\.|barangay|brgy\.?|poblacion|zip\s*code|postal\s*code|philippines)\b/i;
  const dateOrContact = /\b(?:20\d{2}[-/]\d{1,2}[-/]\d{1,2}|\d{1,2}[-/]\d{1,2}[-/]\d{2,4}|(?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\s+\d{1,2},?\s+20\d{2})\b|(?:\+?63|0)\d(?:[\s-]?\d){8,}/i;

  const positionedLines = Array.isArray(blocks)
    ? blocks.flatMap((block, blockIndex) => (Array.isArray(block?.lines) ? block.lines : []).map((line, lineIndex) => ({
        text: String(line?.text || '').trim(),
        top: Number.isFinite(line?.frame?.top) ? line.frame.top : null,
        left: Number.isFinite(line?.frame?.left) ? line.frame.left : null,
        height: Number.isFinite(line?.frame?.height) ? line.frame.height : null,
        blockIndex,
        lineIndex,
      }))).filter(line => line.text)
    : [];

  const orderedLines = positionedLines.length
    ? positionedLines.sort((a, b) => {
        if (a.top !== null && b.top !== null && Math.abs(a.top - b.top) > 2) return a.top - b.top;
        if (a.left !== null && b.left !== null && a.left !== b.left) return a.left - b.left;
        return a.blockIndex - b.blockIndex || a.lineIndex - b.lineIndex;
      })
    : lines.map((text, index) => ({text, top: null, left: null, height: null, blockIndex: 0, lineIndex: index}));

  const seen = new Set();
  const uniqueLines = orderedLines.filter(line => {
    const normalized = line.text.toLowerCase().replace(/\s+/g, ' ').trim();
    if (!normalized || seen.has(normalized)) return false;
    seen.add(normalized);
    return true;
  });
  const imageTextHeight = Math.max(1, ...uniqueLines.map(line => (
    line.top !== null ? line.top + (line.height || 0) : 0
  )));
  const measuredHeights = uniqueLines.map(line => line.height).filter(height => height !== null && height > 0).sort((a, b) => a - b);
  const medianLineHeight = measuredHeights.length ? measuredHeights[Math.floor(measuredHeights.length / 2)] : null;
  const candidates = [];

  for (const [index, sourceLine] of uniqueLines.slice(0, 18).entries()) {
    const line = sourceLine.text
      .replace(/^[^a-z0-9\u00c0-\u024f]+/i, '')
      .replace(/\s+/g, ' ')
      .trim();
    if (receiptBodyStart.test(line)) break;
    if (line.length < 3 || line.length > 60 || genericLines.test(line) || metadataLine.test(line)) continue;
    if (!/[a-z]/i.test(line) || priceOrIdentifier.test(line) || addressText.test(line) || dateOrContact.test(line)) continue;
    if (/^(?:\d+[.)-]?\s*)+$/.test(line) || /\b(?:subtotal|grand\s+total|amount\s+due|cash\s+tendered|change|balance|gcash|maya|paid\s+via)\b/i.test(line)) continue;

    const words = line.split(/\s+/).filter(Boolean);
    const uppercaseRatio = (line.match(/[A-Z]/g) || []).length / Math.max(1, (line.match(/[a-z]/gi) || []).length);
    const topRatio = sourceLine.top !== null ? sourceLine.top / imageTextHeight : null;
    if (topRatio !== null && topRatio > 0.45) continue;
    let score = Math.max(0, 16 - index);
    if (words.length >= 2 && words.length <= 6) score += 3;
    if (businessWords.test(line)) score += 5;
    if (uppercaseRatio > 0.75) score += 1;
    if (medianLineHeight && sourceLine.height && sourceLine.height > medianLineHeight * 1.25) score += 3;
    if (topRatio !== null) score += topRatio <= 0.2 ? 4 : topRatio <= 0.4 ? 2 : topRatio > 0.65 ? -6 : 0;
    candidates.push({line, score, sourceLine, words});
  }

  candidates.sort((a, b) => b.score - a.score);
  const best = candidates[0];
  if (!best || best.score < 8) return 'Unknown Merchant';

  // ML Kit may split a two-word logo across adjacent lines. Join it only when
  // both lines belong to the same block and sit close together at the top.
  const next = uniqueLines.find(line => line.blockIndex === best.sourceLine.blockIndex
    && line.lineIndex > best.sourceLine.lineIndex
    && line.text.trim()
    && !receiptBodyStart.test(line.text.trim())
    && !genericLines.test(line.text.trim())
    && !metadataLine.test(line.text.trim())
    && !priceOrIdentifier.test(line.text.trim())
    && !dateOrContact.test(line.text.trim()));
  if (best.words.length <= 2 && next) {
    const verticalGap = best.sourceLine.top !== null && next.top !== null
      ? next.top - (best.sourceLine.top + (best.sourceLine.height || 0))
      : 0;
    const closeLines = best.sourceLine.top === null || next.top === null
      ? next.lineIndex - best.sourceLine.lineIndex <= 1
      : verticalGap >= -2 && verticalGap <= Math.max(best.sourceLine.height || 0, next.height || 0) * 1.5;
    if (closeLines && next.text.trim().split(/\s+/).length <= 3) {
      return `${best.line} ${next.text.trim()}`.replace(/\s+/g, ' ').substring(0, 80);
    }
  }

  return best.line.substring(0, 80);
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
  const requestedMerchant = String(transactionName || parsed?.merchant || '').trim();
  const merchant = /^unknown merchant$/i.test(requestedMerchant) ? '' : requestedMerchant;
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
  const lines = text.split('\n').map(line => line.trim());
  const paymentLines = lines.filter(line => /\b(?:paid|payment|tendered|method|mode|source|via|reference|ref)\b/i.test(line));
  const standaloneSources = lines.filter(line => /^(?:g-?cash|paymaya|maya|grab\s?pay|shopee\s?pay|cash|bank\s*transfer|credit\s*card)$/i.test(line));
  for (const candidate of [...paymentLines, ...standaloneSources]) {
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
    ? 'Income' : /\b(?:receipt|invoice|bill|total|amount(?:\s*(?:due|payable|to pay|paid))?)\b/i.test(text)
      ? 'Expense' : null;
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
    date: parsed.date || '',
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
