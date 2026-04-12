package model;

import java.time.LocalDate;
import java.time.LocalDateTime;
import java.time.temporal.ChronoUnit;

public class Loan {
    private Integer id;
    private LocalDateTime checkoutTime;
    private LocalDate dueDate;
    private LocalDateTime returnDate;
    private LoanStatus status = LoanStatus.ACTIVE;
    private int renewalCount;
    private String notes;
    private Integer bookCopyId;
    private Integer memberId;
    private String phoneNumber;

    public Integer getId() {
        return id;
    }

    public void setId(Integer id) {
        this.id = id;
    }

    public LocalDateTime getCheckoutTime() {
        return checkoutTime;
    }

    public void setCheckoutTime(LocalDateTime checkoutTime) {
        this.checkoutTime = checkoutTime;
    }

    public LocalDate getDueDate() {
        return dueDate;
    }

    public void setDueDate(LocalDate dueDate) {
        this.dueDate = dueDate;
    }

    public LocalDateTime getReturnDate() {
        return returnDate;
    }

    public void setReturnDate(LocalDateTime returnDate) {
        this.returnDate = returnDate;
    }

    public LoanStatus getStatus() {
        return status;
    }

    public void setStatus(LoanStatus status) {
        this.status = status;
    }

    public int getRenewalCount() {
        return renewalCount;
    }

    public void setRenewalCount(int renewalCount) {
        this.renewalCount = renewalCount;
    }

    public String getNotes() {
        return notes;
    }

    public void setNotes(String notes) {
        this.notes = notes;
    }

    public Integer getBookCopyId() {
        return bookCopyId;
    }

    public void setBookCopyId(Integer bookCopyId) {
        this.bookCopyId = bookCopyId;
    }

    public Integer getMemberId() {
        return memberId;
    }

    public void setMemberId(Integer memberId) {
        this.memberId = memberId;
    }

    public String getPhoneNumber() {
        return phoneNumber;
    }

    public void setPhoneNumber(String phoneNumber) {
        this.phoneNumber = phoneNumber;
    }

    public int getDaysLate() {
        if (dueDate == null) {
            return 0;
        }
        LocalDate effectiveEnd = returnDate == null ? LocalDate.now() : returnDate.toLocalDate();
        if (!effectiveEnd.isAfter(dueDate)) {
            return 0;
        }
        return (int) ChronoUnit.DAYS.between(dueDate, effectiveEnd);
    }

    public boolean canBeRenewed() {
        return returnDate == null && (status == LoanStatus.ACTIVE || status == LoanStatus.OVERDUE);
    }

    public boolean maxRenewalsReached(int maxRenewals) {
        return renewalCount >= maxRenewals;
    }

    public void refreshStatusFromDates() {
        if (returnDate != null) {
            status = LoanStatus.RETURNED;
            return;
        }
        if (dueDate != null && dueDate.isBefore(LocalDate.now())) {
            status = LoanStatus.OVERDUE;
            return;
        }
        status = LoanStatus.ACTIVE;
    }

    @Override
    public String toString() {
        return "Loan #" + (id == null ? "new" : id);
    }
}
