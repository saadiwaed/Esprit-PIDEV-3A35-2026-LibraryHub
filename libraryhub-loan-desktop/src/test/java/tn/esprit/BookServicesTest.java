package tn.esprit;

import model.Book;
import model.BookStatus;
import org.junit.jupiter.api.Test;
import services.ServiceBook;

import static org.junit.jupiter.api.Assertions.assertDoesNotThrow;

class BookServicesTest {
    @Test
    void validatesABook() {
        Book book = new Book();
        book.setTitle("Symfony to JavaFX");
        book.setDescription("Domain-specific book for the desktop rebuild.");
        book.setPublisher("LibraryHub");
        book.setPublicationYear(2026);
        book.setPageCount(240);
        book.setLanguage("FR");
        book.setCoverImage("/covers/libraryhub.png");
        book.setStatus(BookStatus.AVAILABLE);
        book.setCategoryId(1);
        book.setAuthorId(1);

        assertDoesNotThrow(() -> new ServiceBook().validate(book));
    }
}
