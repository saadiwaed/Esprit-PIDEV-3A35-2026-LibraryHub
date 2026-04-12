package model;

public enum LoanStatus {
    ACTIVE("active", "Active"),
    RETURNED("returned", "Returned"),
    OVERDUE("overdue", "Overdue");

    private final String dbValue;
    private final String label;

    LoanStatus(String dbValue, String label) {
        this.dbValue = dbValue;
        this.label = label;
    }

    public String getDbValue() {
        return dbValue;
    }

    public static LoanStatus fromDbValue(String value) {
        for (LoanStatus status : values()) {
            if (status.dbValue.equalsIgnoreCase(value)) {
                return status;
            }
        }
        return ACTIVE;
    }

    @Override
    public String toString() {
        return label;
    }
}
