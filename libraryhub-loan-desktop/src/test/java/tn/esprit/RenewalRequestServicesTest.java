package tn.esprit;

import model.RenewalRequest;
import model.RequestStatus;
import org.junit.jupiter.api.Test;
import services.ServiceRenewalRequest;

import java.time.LocalDateTime;

import static org.junit.jupiter.api.Assertions.assertDoesNotThrow;

class RenewalRequestServicesTest {
    @Test
    void validatesARenewalRequest() {
        RenewalRequest request = new RenewalRequest();
        request.setLoanId(1);
        request.setMemberId(1);
        request.setRequestedAt(LocalDateTime.now());
        request.setStatus(RequestStatus.PENDING);

        assertDoesNotThrow(() -> new ServiceRenewalRequest().validate(request));
    }
}
