package tn.esprit;

import model.Loan;
import model.LoanStatus;
import org.junit.jupiter.api.Test;
import services.ServiceLoan;

import java.time.LocalDate;
import java.time.LocalDateTime;

import static org.junit.jupiter.api.Assertions.assertDoesNotThrow;
import static org.junit.jupiter.api.Assertions.assertThrows;

class LoanServicesTest {
    @Test
    void validatesALoan() {
        Loan loan = new Loan();
        loan.setCheckoutTime(LocalDateTime.now());
        loan.setDueDate(LocalDate.now().plusDays(7));
        loan.setStatus(LoanStatus.ACTIVE);
        loan.setRenewalCount(0);
        loan.setBookCopyId(1);
        loan.setMemberId(1);

        assertDoesNotThrow(() -> new ServiceLoan().validate(loan));
    }

    @Test
    void rejectsPastDueDateAtCreation() {
        Loan loan = new Loan();
        loan.setCheckoutTime(LocalDateTime.now());
        loan.setDueDate(LocalDate.now().minusDays(1));
        loan.setStatus(LoanStatus.ACTIVE);
        loan.setBookCopyId(1);
        loan.setMemberId(1);

        assertThrows(IllegalArgumentException.class, () -> new ServiceLoan().validate(loan));
    }
}
