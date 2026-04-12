package tn.esprit;

import model.LoanRequest;
import model.RequestStatus;
import org.junit.jupiter.api.Test;
import services.ServiceLoanRequest;

import java.time.LocalDate;
import java.time.LocalDateTime;

import static org.junit.jupiter.api.Assertions.assertDoesNotThrow;

class LoanRequestServicesTest {
    @Test
    void validatesALoanRequest() {
        LoanRequest request = new LoanRequest();
        request.setMemberId(1);
        request.setBookId(1);
        request.setDesiredLoanDate(LocalDate.now());
        request.setDesiredReturnDate(LocalDate.now().plusDays(7));
        request.setRequestedAt(LocalDateTime.now());
        request.setStatus(RequestStatus.PENDING);
        request.setPhoneNumber("+21612345678");

        assertDoesNotThrow(() -> new ServiceLoanRequest().validate(request));
    }
}
