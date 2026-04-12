package tn.esprit;

import model.Renewal;
import org.junit.jupiter.api.Test;
import services.ServiceRenewal;

import java.time.LocalDate;
import java.time.LocalDateTime;

import static org.junit.jupiter.api.Assertions.assertDoesNotThrow;

class RenewalServicesTest {
    @Test
    void validatesARenewal() {
        Renewal renewal = new Renewal();
        renewal.setPreviousDueDate(LocalDate.now());
        renewal.setNewDueDate(LocalDate.now().plusDays(14));
        renewal.setRenewedAt(LocalDateTime.now());
        renewal.setRenewalNumber(1);
        renewal.setLoanId(1);

        assertDoesNotThrow(() -> new ServiceRenewal().validate(renewal));
    }
}
