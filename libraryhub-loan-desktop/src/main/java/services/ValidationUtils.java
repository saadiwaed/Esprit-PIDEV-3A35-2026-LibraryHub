package services;

import java.time.LocalDate;
import java.util.regex.Pattern;

final class ValidationUtils {
    private static final Pattern EMAIL_PATTERN = Pattern.compile("^[A-Za-z0-9+_.-]+@[A-Za-z0-9.-]+$");
    private static final Pattern TN_PHONE_PATTERN = Pattern.compile("^\\+216\\d{8}$");

    private ValidationUtils() {
    }

    static String requireText(String value, String fieldName, int minLength, int maxLength) {
        String normalized = value == null ? "" : value.trim();
        if (normalized.length() < minLength || normalized.length() > maxLength) {
            throw new IllegalArgumentException(fieldName + " must contain between " + minLength + " and " + maxLength + " characters.");
        }
        return normalized;
    }

    static String optionalText(String value, int maxLength) {
        String normalized = value == null ? null : value.trim();
        if (normalized == null || normalized.isBlank()) {
            return null;
        }
        if (normalized.length() > maxLength) {
            throw new IllegalArgumentException("The value cannot exceed " + maxLength + " characters.");
        }
        return normalized;
    }

    static String requireEmail(String value) {
        String normalized = requireText(value, "Email", 5, 180);
        if (!EMAIL_PATTERN.matcher(normalized).matches()) {
            throw new IllegalArgumentException("Please enter a valid email address.");
        }
        return normalized;
    }

    static String normalizeTunisianPhone(String value, boolean required) {
        String normalized = value == null ? "" : value.trim().replace(" ", "").replace("-", "").replace(".", "")
                .replace("(", "").replace(")", "");
        if (normalized.isEmpty()) {
            if (required) {
                throw new IllegalArgumentException("A Tunisian phone number is required.");
            }
            return null;
        }
        if (normalized.startsWith("216") && !normalized.startsWith("+216")) {
            normalized = "+" + normalized;
        } else if (!normalized.startsWith("+216") && normalized.length() == 8) {
            normalized = "+216" + normalized;
        }
        if (!TN_PHONE_PATTERN.matcher(normalized).matches()) {
            throw new IllegalArgumentException("The phone number must follow +216XXXXXXXX.");
        }
        return normalized;
    }

    static int requireInteger(Integer value, String fieldName, int minValue, int maxValue) {
        if (value == null || value < minValue || value > maxValue) {
            throw new IllegalArgumentException(fieldName + " must be between " + minValue + " and " + maxValue + ".");
        }
        return value;
    }

    static int requirePositiveId(Integer value, String fieldName) {
        if (value == null || value <= 0) {
            throw new IllegalArgumentException(fieldName + " is required.");
        }
        return value;
    }

    static LocalDate requireDate(LocalDate value, String fieldName) {
        if (value == null) {
            throw new IllegalArgumentException(fieldName + " is required.");
        }
        return value;
    }
}
