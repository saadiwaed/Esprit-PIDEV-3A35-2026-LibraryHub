package model;

import java.time.LocalDate;
import java.time.LocalDateTime;

public class LoanRequest {
    private Integer id;
    private Integer memberId;
    private Integer bookId;
    private LocalDate desiredLoanDate = LocalDate.now();
    private LocalDate desiredReturnDate = LocalDate.now().plusDays(14);
    private LocalDateTime requestedAt = LocalDateTime.now();
    private RequestStatus status = RequestStatus.PENDING;
    private String phoneNumber;
    private String notes;

    public Integer getId() {
        return id;
    }

    public void setId(Integer id) {
        this.id = id;
    }

    public Integer getMemberId() {
        return memberId;
    }

    public void setMemberId(Integer memberId) {
        this.memberId = memberId;
    }

    public Integer getBookId() {
        return bookId;
    }

    public void setBookId(Integer bookId) {
        this.bookId = bookId;
    }

    public LocalDate getDesiredLoanDate() {
        return desiredLoanDate;
    }

    public void setDesiredLoanDate(LocalDate desiredLoanDate) {
        this.desiredLoanDate = desiredLoanDate;
    }

    public LocalDate getDesiredReturnDate() {
        return desiredReturnDate;
    }

    public void setDesiredReturnDate(LocalDate desiredReturnDate) {
        this.desiredReturnDate = desiredReturnDate;
    }

    public LocalDateTime getRequestedAt() {
        return requestedAt;
    }

    public void setRequestedAt(LocalDateTime requestedAt) {
        this.requestedAt = requestedAt;
    }

    public RequestStatus getStatus() {
        return status;
    }

    public void setStatus(RequestStatus status) {
        this.status = status;
    }

    public String getPhoneNumber() {
        return phoneNumber;
    }

    public void setPhoneNumber(String phoneNumber) {
        this.phoneNumber = phoneNumber;
    }

    public String getNotes() {
        return notes;
    }

    public void setNotes(String notes) {
        this.notes = notes;
    }

    @Override
    public String toString() {
        return "Loan Request #" + (id == null ? "new" : id);
    }
}
