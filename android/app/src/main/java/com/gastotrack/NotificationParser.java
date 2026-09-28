package com.gastotrack;

import java.util.regex.Matcher;
import java.util.regex.Pattern;

public class NotificationParser {

    public static final String GCASH     = "com.globe.gcash.android";
    public static final String MAYA      = "com.voyager.pay";
    public static final String GRABPAY   = "com.grabtaxi.passenger";
    public static final String SHOPEEPAY = "com.shopee.ph";

    public static ParsedTransaction parse(String packageName, String title, String text) {
        ParsedTransaction t = new ParsedTransaction();
        t.rawMessage = title + " | " + maskPhoneNumbers(text);

        switch (packageName) {
            case GCASH:     return parseGCash(t, text);
            case MAYA:      return parseMaya(t, text);
            case GRABPAY:   return parseGrabPay(t, text);
            case SHOPEEPAY: return parseShopeePay(t, text);
            default:        return null;
        }
    }

    // ----------------------------------------------------------------
    // GCash
    // ----------------------------------------------------------------
    private static ParsedTransaction parseGCash(ParsedTransaction t, String text) {
        t.appSource = "GCash";
        String lower = text.toLowerCase();

        if (lower.contains("received")) {
            t.transactionType = "received";
            t.merchant = extractAfterKeyword(text, new String[]{"from "});
        } else if (lower.contains("sent") || lower.contains("paid") || lower.contains("payment")) {
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
    // Maya
    // ----------------------------------------------------------------
    private static ParsedTransaction parseMaya(ParsedTransaction t, String text) {
        t.appSource = "Maya";
        String lower = text.toLowerCase();

        if (lower.contains("received") || lower.contains("incoming")) {
            t.transactionType = "received";
            t.merchant = extractAfterKeyword(text, new String[]{"from "});
        } else if (lower.contains("sent") || lower.contains("paid") || lower.contains("payment")) {
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
        Pattern pattern = Pattern.compile("(?:PHP|₱)\\s?([\\d,]+\\.\\d{2})");
        Matcher matcher = pattern.matcher(text);
        if (matcher.find()) {
            return "₱" + matcher.group(1);
        }
        return "unknown";
    }

    private static String extractAfterKeyword(String text, String[] keywords) {
        for (String keyword : keywords) {
            int index = text.toLowerCase().indexOf(keyword);
            if (index != -1) {
                String after = text.substring(index + keyword.length()).trim();
                if (after.endsWith(".")) after = after.substring(0, after.length() - 1).trim();
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

    // ----------------------------------------------------------------
    // Simple data holder
    // ----------------------------------------------------------------
    public static class ParsedTransaction {
        public String appSource;
        public String amount;
        public String transactionType;
        public String merchant;
        public String rawMessage;
    }
}