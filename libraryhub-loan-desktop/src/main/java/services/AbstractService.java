package services;

import utils.MyDatabase;

import java.sql.Connection;
import java.sql.Date;
import java.sql.SQLException;
import java.sql.Timestamp;
import java.time.LocalDate;
import java.time.LocalDateTime;

abstract class AbstractService {
    protected static final int DEFAULT_LOAN_DAYS = 21;
    protected static final int DEFAULT_RENEWAL_DAYS = 14;
    protected static final int MAX_RENEWALS = 3;

    protected Connection getConnection() throws SQLException {
        return MyDatabase.getInstance().getConnection();
    }

    protected Timestamp toTimestamp(LocalDateTime value) {
        return value == null ? null : Timestamp.valueOf(value);
    }

    protected Date toDate(LocalDate value) {
        return value == null ? null : Date.valueOf(value);
    }

    protected LocalDateTime toLocalDateTime(Timestamp value) {
        return value == null ? null : value.toLocalDateTime();
    }

    protected LocalDate toLocalDate(Date value) {
        return value == null ? null : value.toLocalDate();
    }

    protected void require(boolean condition, String message) {
        if (!condition) {
            throw new IllegalArgumentException(message);
        }
    }
}
