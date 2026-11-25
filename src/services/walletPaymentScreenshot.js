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
  const lines = text.split(/\r?\n/);
  const labelIndex = lines.findIndex(line => /\b(?:date|received on|paid on|transaction time)\b/i.test(line));
  const candidates = labelIndex < 0 ? [text] : [`${lines[labelIndex]} ${lines[labelIndex + 1] || ''}`, text];
  for (const candidate of candidates) {
    const date = extractDateCandidate(candidate);
    if (date) return date;
  }
  return '';
};

const extractDateCandidate = text => {
  const iso = text.match(/\b(20\d{2})[-/](\d{1,2})[-/](\d{1,2})\b/);
  if (iso) return validDate(Number(iso[1]), Number(iso[2]), Number(iso[3]));
  const monthName = '(Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:tember)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)';
  const named = text.match(new RegExp(`\\b${monthName}\\s+(\\d{1,2}),?\\s+(20\\d{2})\\b`, 'i'));
  if (named) {
    const month = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'].indexOf(named[1].slice(0, 3).toLowerCase()) + 1;
    return validDate(Number(named[3]), month, Number(named[2]));
  }
  const dayFirst = text.match(new RegExp(`\\b(\\d{1,2})\\s+${monthName}\\s+(20\\d{2})\\b`, 'i'));
  if (dayFirst) {
    const month = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'].indexOf(dayFirst[2].slice(0, 3).toLowerCase()) + 1;
    return validDate(Number(dayFirst[3]), month, Number(dayFirst[1]));
  }
  const numeric = text.match(/\b(\d{1,2})[-/](\d{1,2})[-/](20\d{2})\b/);
  if (!numeric) return '';
  // Philippine screenshots commonly use MM/DD/YYYY, but ambiguous dates need staff review.
  return validDate(Number(numeric[3]), Number(numeric[1]), Number(numeric[2]));
};

const extractReference = (lines, source) => {
  const label = /\b(?:reference\s+(?:number|no\.?|id)|ref\.?\s+(?:number|no\.?)|transaction\s+(?:id|number|no\.?)|receipt\s+(?:no\.?|number)|reference|ref\.?)\s*[:#.-]?\s*/i;
  const references = lines.flatMap((line, index) => {
    const match = label.exec(line);
    if (!match) return [];
    // Merchant and request references can be reused; only the wallet's
    // transaction/receipt reference identifies this specific payment.
    if (/\b(?:merchant|request)\s+reference\b/i.test(line)) return [];
    const tail = line.slice(match.index + match[0].length).trim();
    const rawValue = tail || lines[index + 1] || '';
    if (/[\u2026*\u2022]|\.\s*\.\s*\./.test(rawValue)) return [];
    const value = rawValue.match(/^([A-Z0-9][A-Z0-9._/-]{5,119})(?=$|[\s.,;])/i)?.[1];
    if (!value) return [];
    const prefix = line.slice(0, match.index).trim();
    return [{
      value: value.replace(/[.,;]+$/, ''),
      walletSpecific: Boolean(source && prefix.toLowerCase() === source.toLowerCase()),
      transactionSpecific: /\b(?:transaction|receipt)\b/i.test(line),
    }];
  });
  return references.find(item => item.walletSpecific)?.value
    || references.find(item => item.transactionSpecific)?.value
    || references[0]?.value || '';
};

export const parseWalletPaymentScreenshot = ocrText => {
  const text = String(ocrText || '');
  const lines = text.split(/\r?\n/).map(line => line.trim()).filter(Boolean);
  const walletsIn = line => WALLET_NAMES.filter(wallet => wallet.pattern.test(line)).map(wallet => wallet.name);
  // On transfer details, the receiving wallet is the destination, not the app
  // named under "From". The staff still checks the actual receiving account.
  const toIndex = lines.findIndex(line => /^to(?:\s*[:-]\s*|\s+|$)/i.test(line));
  const destinationWallets = toIndex < 0 ? [] : [...new Set(lines.slice(toIndex, toIndex + 4).flatMap(walletsIn))];
  const sourceCandidates = lines.slice(0, 6).flatMap((line, index) => {
    if (/^(?:reference|ref\.?|transaction\s*(?:id|number))\b/i.test(line)) return [];
    return WALLET_NAMES.filter(wallet => wallet.pattern.test(line)).map(wallet => ({name: wallet.name, index}));
  });
  const topCandidates = sourceCandidates.filter(candidate => candidate.index === sourceCandidates[0]?.index);
  const source = destinationWallets.length === 1 ? destinationWallets[0]
    : destinationWallets.length > 1 ? '' : topCandidates.length === 1 ? topCandidates[0].name : '';
  const reference = extractReference(lines, source);

  const monetaryValues = lines.flatMap((line, index) => [...line.matchAll(/(?:PHP\s*|[P\u20B1]\s*)([\d,]+(?:\.\d{1,2})?)/gi)]
    .map(match => ({value: Number(match[1].replace(/,/g, '')), line, previous: lines[index - 1] || ''})));
  // Some receiving-wallet details print a positive amount without a currency
  // symbol. Accept it only beside an Amount label, never as a bare number.
  const positiveAmounts = lines.flatMap((line, index) => {
    const match = line.match(/(?:^|\bamount\s*:?\s*)\+\s*([\d,]+\.\d{2})\b/i);
    if (!match || !(/\bamount\b/i.test(line) || /^\s*amount\s*:?\s*$/i.test(lines[index - 1] || ''))) return [];
    return [{value: Number(match[1].replace(/,/g, '')), line: `Amount ${line}`, previous: lines[index - 1] || ''}];
  });
  monetaryValues.push(...positiveAmounts);
  const withoutBalance = monetaryValues.filter(item => !/\b(?:balance|available funds)\b/i.test(item.line));
  const labelText = item => /^(?:PHP\s*|[P\u20B1]\s*|\+\s*)[\d,]+(?:\.\d{1,2})?$/i.test(item.line)
    ? `${item.previous} ${item.line}` : item.line;
  const transferred = withoutBalance.filter(item => /\b(?:transfer\s+amount|amount\s+received|transferred|received\s+payment)\b/i.test(labelText(item))
    && !/\b(?:fee|balance|account)\b/i.test(item.line));
  const labeled = withoutBalance.filter(item => /\b(?:amount|payment|total)\b/i.test(labelText(item)));
  const candidates = transferred.length ? transferred : labeled.length ? labeled : withoutBalance;
  const uniqueAmounts = [...new Set(candidates.map(item => item.value).filter(amount => Number.isFinite(amount) && amount > 0))];
  const amount = uniqueAmounts.length === 1 ? uniqueAmounts[0] : null;

  const receivedSender = text.match(/\breceived\s+(?:PHP\s*|[P\u20B1]\s*)[\d,]+(?:\.\d{1,2})?\s+from\s+([\s\S]{2,160}?)\s+on\s+(?:\d{1,2}[-/]\d{1,2}[-/]20\d{2}|[A-Za-z]{3,9}\s+\d{1,2})/i)?.[1];
  const senderIndex = lines.findIndex(line => /^(?:from|sender(?:\s*name)?|sent\s*by|payer)(?:\s*[:-]\s*|\s+|$)/i.test(line));
  const senderLine = senderIndex < 0 ? '' : lines[senderIndex].replace(/^(?:from|sender(?:\s*name)?|sent\s*by|payer)\s*[:-]?\s*/i, '') || lines[senderIndex + 1] || '';
  const paymentSender = text.match(/\breceived\s+payment\s+from\s+([^\r\n]{2,160})/i)?.[1];
  const senderCandidate = (receivedSender || paymentSender || senderLine)
    .split(/\b(?:reference|ref\.?|transaction|date|amount)\b/i)[0].trim().slice(0, 160);
  const sender = /[*\u2026]|\.{2,}|\d{4,}/.test(senderCandidate) ? '' : senderCandidate;

  return {source, amount, reference, sender, date: extractDate(text)};
};

// This GCash detail layout omits the app name and sender from its readable
// text. It may be reviewed against the wallet selected at checkout, but the
// source must be shown as unverified rather than claimed as OCR-detected.
export const isUnlabelledGCashRemittance = (ocrText, parsed) => {
  const text = String(ocrText || '');
  return !parsed.source && parsed.amount !== null && Boolean(parsed.reference && parsed.date)
    && /\btransaction\s+details\b/i.test(text)
    && /\binternational\s+remittance\b/i.test(text)
    && /\bamount\b\s*:?\s*\+\s*[\d,]+\.\d{2}\b/i.test(text)
    && /\breference\s+number\b/i.test(text);
};
