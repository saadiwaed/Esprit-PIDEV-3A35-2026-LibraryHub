package model;

public enum BookStatus {
    AVAILABLE("available"),
    BORROWED("borrowed"),
    MAINTENANCE("maintenance"),
    RESERVED("reserved");

    private final String dbValue;

    BookStatus(String dbValue) {
        this.dbValue = dbValue;
    }

    public String getDbValue() {
        return dbValue;
    }

    public static BookStatus fromDbValue(String value) {
        for (BookStatus status : values()) {
            if (status.dbValue.equalsIgnoreCase(value)) {
                return status;
            }
        }
        return AVAILABLE;
    }

    @Override
    public String toString() {
        return name().charAt(0) + name().substring(1).toLowerCase();
    }
}
