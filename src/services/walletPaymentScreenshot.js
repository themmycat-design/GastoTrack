const WALLET_NAMES = [
  {name: 'GCash', pattern: /\bg\s*-?\s*cash\b/i},
  {name: 'Maya', pattern: /\b(?:pay\s*maya|maya)\b/i},
  {name: 'MariBank', pattern: /\b(?:mari\s*bank|seabank)\b/i},
  {name: 'GoTyme', pattern: /\bgo\s*tyme\b/i},
];

const validDate = (year, month, day) => {
  const candidate = new Date(Date.UTC(year, month - 1, day));
  return candidate.getUTCFullYear() === year && candidate.getUTCMonth() + 1 === month && candidate.getUTCDate() === day
    ? `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}` : '';
};

const extractDate = text => {
  const labeledLine = text.split(/\r?\n/).find(line => /\b(?:date|received on|paid on|transaction time)\s*:/i.test(line));
  if (labeledLine) text = labeledLine;
  const iso = text.match(/\b(20\d{2})[-/](\d{1,2})[-/](\d{1,2})\b/);
  if (iso) return validDate(Number(iso[1]), Number(iso[2]), Number(iso[3]));
  const named = text.match(/\b(Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:tember)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)\s+(\d{1,2}),?\s+(20\d{2})\b/i);
  if (named) {
    const month = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'].indexOf(named[1].slice(0, 3).toLowerCase()) + 1;
    return validDate(Number(named[3]), month, Number(named[2]));
  }
  const numeric = text.match(/\b(\d{1,2})[-/](\d{1,2})[-/](20\d{2})\b/);
  if (!numeric) return '';
  // Philippine screenshots commonly use MM/DD/YYYY, but ambiguous dates need staff review.
  return validDate(Number(numeric[3]), Number(numeric[1]), Number(numeric[2]));
};

export const parseWalletPaymentScreenshot = ocrText => {
  const text = String(ocrText || '');
  const lines = text.split(/\r?\n/).map(line => line.trim()).filter(Boolean);
  const sourceCandidates = lines.slice(0, 6).flatMap((line, index) => {
    if (/\b(?:reference|ref\.?|transaction\s*(?:id|number))\b/i.test(line)) return [];
    return WALLET_NAMES.filter(wallet => wallet.pattern.test(line)).map(wallet => ({name: wallet.name, index}));
  });
  const topCandidates = sourceCandidates.filter(candidate => candidate.index === sourceCandidates[0]?.index);
  const source = topCandidates.length === 1 ? topCandidates[0].name : '';
  const referenceLine = lines.find(line => /\b(?:reference|ref\.?|transaction\s*(?:id|number|no\.?))\b/i.test(line)) || '';
  const referenceMatch = referenceLine.match(/\b(?:reference(?:\s*(?:number|no\.?|id|#))?|ref\.?\s*(?:number|no\.?|#)?|transaction\s*(?:id|number|no\.?))\s*[:#-]?\s*([A-Z0-9][A-Z0-9._/-]{5,119})\b/i);
  const reference = referenceMatch?.[1] || '';

  const monetaryValues = lines.flatMap(line => [...line.matchAll(/(?:₱|PHP\s*)\s*([\d,]+(?:\.\d{1,2})?)/gi)]
    .map(match => ({value: Number(match[1].replace(/,/g, '')), line})));
  const labeled = monetaryValues.filter(item => /\b(?:amount|received|payment|total)\b/i.test(item.line));
  const candidates = labeled.length ? labeled : monetaryValues;
  const uniqueAmounts = [...new Set(candidates.map(item => item.value).filter(amount => Number.isFinite(amount) && amount > 0))];
  const amount = uniqueAmounts.length === 1 ? uniqueAmounts[0] : null;

  const senderLine = lines.find(line => /^(?:from|sender(?:\s*name)?|sent\s*by|payer)\s*[:-]/i.test(line));
  const sender = senderLine?.replace(/^(?:from|sender(?:\s*name)?|sent\s*by|payer)\s*[:-]\s*/i, '')
    .split(/\b(?:reference|ref\.?|transaction|date|amount)\b/i)[0].trim().slice(0, 160) || '';

  return {source, amount, reference, sender, date: extractDate(text)};
};
