package com.gastotrack;

import java.util.regex.Matcher;
import java.util.regex.Pattern;

public class NotificationParser {

    public static final String GCASH     = "com.globe.gcash.android";
    public static final String MAYA      = "com.voyager.pay";
    public static final String MAYA_ALT  = "com.paymaya";
    public static final String GRABPAY   = "com.grabtaxi.passenger";
    public static final String SHOPEEPAY = "com.shopee.ph";

    private static final Pattern AMOUNT_PATTERN = Pattern.compile("(?i)(?:PHP|\\x{20B1})\\s*([\\d,]+(?:\\.\\d{2})?)");
    private static final Pattern REFERENCE_PATTERN = Pattern.compile(
        "(?i)\\b(?:reference\\s*(?:(?:id|number|no)\\.?|#)?|ref\\.?\\s*(?:(?:id|number|no)\\.?|#)?|transaction\\s+(?:reference\\s*(?:(?:id|number|no)\\.?)?|(?:id|number|no)\\.?)|txn\\s*(?:reference|id|number|no)\\.?|transaction\\s+reference)\\s*[:#.-]?\\s*([A-Z0-9][A-Z0-9._/-]{5,119})\\b"
    );
    private static final Pattern NON_TRANSACTION_PATTERN = Pattern.compile(
        "(?i)\\b(?:promo(?:tion)?|offer|offers|discount|voucher|coupon|otp|one.time (?:pin|password)|verification|security|login|password|passcode|pin|reminder|due|pending|failed|declined|cancelled|canceled|unsuccessful|expired|request(?:ed)?)\\b"
    );
    private static final Pattern RECEIVED_PATTERN = Pattern.compile(
        "(?i)\\b(?:received|credited|deposited|cash[ -]?in|added money|top[ -]?up successful)\\b"
    );
    private static final Pattern SENT_PATTERN = Pattern.compile(
        "(?i)\\b(?:sent|paid|payment(?:\\s+(?:of|for|to|successful|completed))|transferred|transfer\\s+(?:to|successful|completed)|debited|purchase|purchased|withdrawn|cash[ -]?out|charged)\\b"
    );

    public static ParsedTransaction parse(String packageName, String title, String text) {
        title = title == null ? "" : title;
        text = text == null ? "" : text;
        ParsedTransaction t = new ParsedTransaction();
        t.rawMessage = redactSensitive(title) + " | " + redactSensitive(text);

        switch (packageName) {
            case GCASH:     return parseGCash(t, title, text);
            case MAYA:      return parseMaya(t, title, text);
            case MAYA_ALT:  return parseMaya(t, title, text);
            case GRABPAY:   return parseGrabPay(t, text);
            case SHOPEEPAY: return parseShopeePay(t, text);
            default:        return null;
        }
    }

    // ----------------------------------------------------------------
    // GCash
    // ----------------------------------------------------------------
    private static ParsedTransaction parseGCash(ParsedTransaction t, String title, String text) {
        t.appSource = "GCash";
        t.transactionType = classifyDirection(title + " " + text);
        if (t.transactionType == null) return null;
        t.reference = extractReference(title + " " + text);
        t.merchant = "received".equals(t.transactionType)
            ? extractAfterKeyword(title + " " + text, new String[]{"from "})
            : extractAfterKeyword(title + " " + text, new String[]{"to "});

        if (t.merchant != null) t.merchant = redactSensitive(t.merchant);
        t.amount = extractAmount(title + " " + text);
        if ("unknown".equals(t.amount)) return null;
        return t;
    }

    // ----------------------------------------------------------------
    // Maya
    // ----------------------------------------------------------------
    private static ParsedTransaction parseMaya(ParsedTransaction t, String title, String text) {
        t.appSource = "Maya";
        t.transactionType = classifyDirection(title + " " + text);
        if (t.transactionType == null) return null;
        t.reference = extractReference(title + " " + text);
        t.merchant = "received".equals(t.transactionType)
            ? extractAfterKeyword(title + " " + text, new String[]{"from "})
            : extractAfterKeyword(title + " " + text, new String[]{"to "});

        if (t.merchant != null) t.merchant = redactSensitive(t.merchant);
        t.amount = extractAmount(title + " " + text);
        if ("unknown".equals(t.amount)) return null;
        return t;
    }

    // ----------------------------------------------------------------
    // GrabPay
    // ----------------------------------------------------------------
    private static ParsedTransaction parseGrabPay(ParsedTransaction t, String text) {
        t.appSource = "GrabPay";
        String lower = text.toLowerCase();

        if (lower.contains("received") || lower.contains("reward")) {
            t.transactionType = "received";
            t.merchant = extractAfterKeyword(text, new String[]{"from "});
        } else if (lower.contains("payment") || lower.contains("paid") || lower.contains("sent")) {
            t.transactionType = "sent";
            t.merchant = extractAfterKeyword(text, new String[]{"to "});
        } else {
            t.transactionType = "unknown";
            t.merchant = null;
        }

        if (t.merchant != null) t.merchant = maskPhoneNumbers(t.merchant);
        t.amount = extractAmount(text);
        return t;
    }

    // ----------------------------------------------------------------
    // ShopeePay
    // ----------------------------------------------------------------
    private static ParsedTransaction parseShopeePay(ParsedTransaction t, String text) {
        t.appSource = "ShopeePay";
        String lower = text.toLowerCase();

        if (lower.contains("added") || lower.contains("received") || lower.contains("cashback")) {
            t.transactionType = "received";
            t.merchant = extractAfterKeyword(text, new String[]{"from "});
        } else if (lower.contains("paid") || lower.contains("payment") || lower.contains("sent")) {
            t.transactionType = "sent";
            t.merchant = extractAfterKeyword(text, new String[]{"to "});
        } else {
            t.transactionType = "unknown";
            t.merchant = null;
        }

        if (t.merchant != null) t.merchant = maskPhoneNumbers(t.merchant);
        t.amount = extractAmount(text);
        return t;
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------
    private static String extractAmount(String text) {
        Matcher matcher = AMOUNT_PATTERN.matcher(text == null ? "" : text);
        if (matcher.find()) {
            return "\u20B1" + matcher.group(1);
        }
        return "unknown";
    }

    private static String extractReference(String text) {
        Matcher matcher = REFERENCE_PATTERN.matcher(text == null ? "" : text);
        return matcher.find() ? matcher.group(1).trim() : null;
    }

    /** Returns a direction only for completed, amount-bearing transaction alerts. */
    private static String classifyDirection(String message) {
        if (message == null || message.trim().isEmpty() || NON_TRANSACTION_PATTERN.matcher(message).find()) return null;
        if (RECEIVED_PATTERN.matcher(message).find()) return "received";
        if (SENT_PATTERN.matcher(message).find()) return "sent";
        return null;
    }

    /** Defensive validation shared by local queue storage and retry cleanup. */
    public static boolean isValidWalletTransaction(String source, String direction, String amount, String message) {
        if (source == null || !(source.equalsIgnoreCase("gcash") || source.equalsIgnoreCase("maya"))) return false;
        if (amount == null || "unknown".equalsIgnoreCase(amount)) return false;
        Matcher amountMatcher = AMOUNT_PATTERN.matcher(amount);
        String numeric = amountMatcher.find() ? amountMatcher.group(1).replace(",", "") : amount.replaceAll("[^0-9.]", "");
        try {
            if (Double.parseDouble(numeric) <= 0) return false;
        } catch (NumberFormatException exception) {
            return false;
        }
        return direction != null && direction.equals(classifyDirection(message));
    }

    private static String extractAfterKeyword(String text, String[] keywords) {
        for (String keyword : keywords) {
            int index = text.toLowerCase().indexOf(keyword);
            if (index != -1) {
                String after = text.substring(index + keyword.length()).trim();
                Matcher referenceMatcher = REFERENCE_PATTERN.matcher(after);
                if (referenceMatcher.find()) after = after.substring(0, referenceMatcher.start()).trim();
                int newline = after.indexOf('\n');
                if (newline >= 0) after = after.substring(0, newline).trim();
                String lowerAfter = after.toLowerCase();
                int boundaryIndex = -1;
                for (String boundary : new String[]{" on ", " at ", " via ", " ref ", " reference "}) {
                    int candidateIndex = lowerAfter.indexOf(boundary);
                    if (candidateIndex >= 0 && (boundaryIndex < 0 || candidateIndex < boundaryIndex)) {
                        boundaryIndex = candidateIndex;
                    }
                }
                if (boundaryIndex >= 0) after = after.substring(0, boundaryIndex).trim();
                after = after.replaceAll("[\\s.,;:!?]+$", "").trim();
                after = maskPhoneNumbers(after);
                String[] words = after.split("\\s+");
                StringBuilder result = new StringBuilder();
                for (int i = 0; i < Math.min(4, words.length); i++) {
                    if (i > 0) result.append(" ");
                    result.append(words[i]);
                }
                return result.toString().trim();
            }
        }
        return null;
    }

    private static String maskPhoneNumbers(String text) {
        if (text == null) return null;
        text = text.replaceAll("\\b0\\d{10}\\b", "09*******");
        text = text.replaceAll("\\+639\\d{9}\\b", "+639*******");
        return text;
    }

    private static String redactSensitive(String text) {
        String masked = maskPhoneNumbers(text);
        if (masked == null) return "";
        return masked.replaceAll("(?i)(\\b(?:otp|one.time (?:pin|password)|verification code|security code|passcode|pin)\\b[^0-9]{0,25})\\d{4,8}\\b", "$1[redacted]");
    }

    // ----------------------------------------------------------------
    // Simple data holder
    // ----------------------------------------------------------------
    public static class ParsedTransaction {
        public String appSource;
        public String amount;
        public String transactionType;
        public String merchant;
        public String reference;
        public String rawMessage;
    }
}
