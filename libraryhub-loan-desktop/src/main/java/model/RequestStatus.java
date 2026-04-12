package model;

public enum RequestStatus {
    PENDING("PENDING", "Pending"),
    APPROVED("APPROVED", "Approved"),
    REJECTED("REJECTED", "Rejected");

    private final String dbValue;
    private final String label;

    RequestStatus(String dbValue, String label) {
        this.dbValue = dbValue;
        this.label = label;
    }

    public String getDbValue() {
        return dbValue;
    }

    public static RequestStatus fromDbValue(String value) {
        for (RequestStatus status : values()) {
            if (status.dbValue.equalsIgnoreCase(value)) {
                return status;
            }
        }
        return PENDING;
    }

    @Override
    public String toString() {
        return label;
    }
}
