package tn.esprit;

import model.PaymentStatus;
import model.Penalty;
import org.junit.jupiter.api.Test;
import services.ServicePenalty;

import java.math.BigDecimal;
import java.time.LocalDate;

import static org.junit.jupiter.api.Assertions.assertDoesNotThrow;

class PenaltyServicesTest {
    @Test
    void validatesAPenalty() {
        Penalty penalty = new Penalty();
        penalty.setAmount(new BigDecimal("5.00"));
        penalty.setDailyRate(new BigDecimal("0.50"));
        penalty.setLateDays(10);
        penalty.setReason(Penalty.REASON_LATE_RETURN);
        penalty.setIssueDate(LocalDate.now());
        penalty.setStatus(PaymentStatus.UNPAID);
        penalty.setLoanId(1);

        assertDoesNotThrow(() -> new ServicePenalty().validate(penalty));
    }
}
