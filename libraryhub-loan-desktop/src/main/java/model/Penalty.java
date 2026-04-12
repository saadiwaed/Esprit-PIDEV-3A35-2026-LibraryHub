package model;

import java.math.BigDecimal;
import java.time.LocalDate;

public class Penalty {
    public static final String DAILY_LATE_REASON_PREFIX = "Retard journalier";
    public static final String REASON_LATE_RETURN = "late_return";
    public static final String REASON_DAMAGED_BOOK = "damaged_book";
    public static final String REASON_OTHER = "other";

    private Integer id;
    private BigDecimal amount = BigDecimal.ZERO;
    private BigDecimal dailyRate = new BigDecimal("0.50");
    private int lateDays;
    private String reason;
    private LocalDate issueDate;
    private String notes;
    private boolean waived;
    private PaymentStatus status = PaymentStatus.UNPAID;
    private Integer loanId;

    public Integer getId() {
        return id;
    }

    public void setId(Integer id) {
        this.id = id;
    }

    public BigDecimal getAmount() {
        return amount;
    }

    public void setAmount(BigDecimal amount) {
        this.amount = amount;
    }

    public BigDecimal getDailyRate() {
        return dailyRate;
    }

    public void setDailyRate(BigDecimal dailyRate) {
        this.dailyRate = dailyRate;
    }

    public int getLateDays() {
        return lateDays;
    }

    public void setLateDays(int lateDays) {
        this.lateDays = lateDays;
    }

    public String getReason() {
        return reason;
    }

    public void setReason(String reason) {
        this.reason = reason;
    }

    public LocalDate getIssueDate() {
        return issueDate;
    }

    public void setIssueDate(LocalDate issueDate) {
        this.issueDate = issueDate;
    }

    public String getNotes() {
        return notes;
    }

    public void setNotes(String notes) {
        this.notes = notes;
    }

    public boolean isWaived() {
        return waived;
    }

    public void setWaived(boolean waived) {
        this.waived = waived;
    }

    public PaymentStatus getStatus() {
        return status;
    }

    public void setStatus(PaymentStatus status) {
        this.status = status;
    }

    public Integer getLoanId() {
        return loanId;
    }

    public void setLoanId(Integer loanId) {
        this.loanId = loanId;
    }

    public boolean isDailyLateFee() {
        return reason != null && reason.startsWith(DAILY_LATE_REASON_PREFIX);
    }

    public boolean isLatePenaltyReason() {
        return REASON_LATE_RETURN.equals(reason) || isDailyLateFee();
    }

    @Override
    public String toString() {
        return "Penalty #" + (id == null ? "new" : id);
    }
}
