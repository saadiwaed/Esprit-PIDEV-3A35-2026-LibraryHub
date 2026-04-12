package model;

public enum PaymentStatus {
    UNPAID("unpaid", "Unpaid"),
    PAID("paid", "Paid"),
    PARTIAL("partial", "Partial");

    private final String dbValue;
    private final String label;

    PaymentStatus(String dbValue, String label) {
        this.dbValue = dbValue;
        this.label = label;
    }

    public String getDbValue() {
        return dbValue;
    }

    public static PaymentStatus fromDbValue(String value) {
        for (PaymentStatus status : values()) {
            if (status.dbValue.equalsIgnoreCase(value)) {
                return status;
            }
        }
        return UNPAID;
    }

    @Override
    public String toString() {
        return label;
    }
}
