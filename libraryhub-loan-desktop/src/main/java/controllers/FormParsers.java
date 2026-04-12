package controllers;

import java.time.LocalDate;
import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.time.format.DateTimeParseException;

public final class FormParsers {
    private static final DateTimeFormatter DATE_TIME_FORMATTER = DateTimeFormatter.ofPattern("yyyy-MM-dd HH:mm");

    private FormParsers() {
    }

    public static Integer parseInteger(String value, String fieldName) {
        try {
            return Integer.parseInt(value.trim());
        } catch (Exception exception) {
            throw new IllegalArgumentException(fieldName + " must be a valid integer.");
        }
    }

    public static LocalDateTime parseDateTime(String value, String fieldName, boolean required) {
        String normalized = value == null ? "" : value.trim();
        if (normalized.isBlank()) {
            if (required) {
                throw new IllegalArgumentException(fieldName + " is required. Use yyyy-MM-dd HH:mm.");
            }
            return null;
        }
        try {
            return LocalDateTime.parse(normalized, DATE_TIME_FORMATTER);
        } catch (DateTimeParseException exception) {
            throw new IllegalArgumentException(fieldName + " must use yyyy-MM-dd HH:mm.");
        }
    }

    public static String formatDateTime(LocalDateTime value) {
        return value == null ? "" : value.format(DATE_TIME_FORMATTER);
    }

    public static LocalDate requireDate(LocalDate value, String fieldName) {
        if (value == null) {
            throw new IllegalArgumentException(fieldName + " is required.");
        }
        return value;
    }
}
