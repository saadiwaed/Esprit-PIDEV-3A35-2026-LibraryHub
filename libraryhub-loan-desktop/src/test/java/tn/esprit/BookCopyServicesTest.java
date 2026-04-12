package tn.esprit;

import model.BookCopy;
import org.junit.jupiter.api.Test;
import services.ServiceBookCopy;

import static org.junit.jupiter.api.Assertions.assertDoesNotThrow;

class BookCopyServicesTest {
    @Test
    void validatesABookCopy() {
        assertDoesNotThrow(() -> new ServiceBookCopy().validate(new BookCopy()));
    }
}
