package model;

import java.time.LocalDate;
import java.time.LocalDateTime;

public class Renewal {
    private Integer id;
    private LocalDate previousDueDate;
    private LocalDate newDueDate;
    private LocalDateTime renewedAt = LocalDateTime.now();
    private int renewalNumber;
    private Integer loanId;

    public Integer getId() {
        return id;
    }

    public void setId(Integer id) {
        this.id = id;
    }

    public LocalDate getPreviousDueDate() {
        return previousDueDate;
    }

    public void setPreviousDueDate(LocalDate previousDueDate) {
        this.previousDueDate = previousDueDate;
    }

    public LocalDate getNewDueDate() {
        return newDueDate;
    }

    public void setNewDueDate(LocalDate newDueDate) {
        this.newDueDate = newDueDate;
    }

    public LocalDateTime getRenewedAt() {
        return renewedAt;
    }

    public void setRenewedAt(LocalDateTime renewedAt) {
        this.renewedAt = renewedAt;
    }

    public int getRenewalNumber() {
        return renewalNumber;
    }

    public void setRenewalNumber(int renewalNumber) {
        this.renewalNumber = renewalNumber;
    }

    public Integer getLoanId() {
        return loanId;
    }

    public void setLoanId(Integer loanId) {
        this.loanId = loanId;
    }

    @Override
    public String toString() {
        return "Renewal #" + (id == null ? "new" : id);
    }
}
