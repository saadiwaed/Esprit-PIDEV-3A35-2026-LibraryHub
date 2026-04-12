package services;

import model.Book;
import model.BookStatus;

import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Statement;
import java.time.LocalDateTime;
import java.util.ArrayList;
import java.util.List;
import java.util.Optional;

public class ServiceBook extends AbstractService implements IService<Book> {
    public void validate(Book book) {
        require(book != null, "Book cannot be null.");
        book.setTitle(ValidationUtils.requireText(book.getTitle(), "Title", 3, 500));
        book.setDescription(ValidationUtils.requireText(book.getDescription(), "Description", 3, 10000));
        book.setPublisher(ValidationUtils.requireText(book.getPublisher(), "Publisher", 2, 255));
        book.setPublicationYear(ValidationUtils.requireInteger(book.getPublicationYear(), "Publication year", 1000, 2100));
        book.setPageCount(ValidationUtils.requireInteger(book.getPageCount(), "Page count", 1, 50000));
        book.setLanguage(ValidationUtils.requireText(book.getLanguage(), "Language", 2, 50));
        book.setCoverImage(ValidationUtils.requireText(book.getCoverImage(), "Cover image", 3, 500));
        require(book.getStatus() != null, "Book status is required.");
        if (book.getCreatedAt() == null) {
            book.setCreatedAt(LocalDateTime.now());
        }
        ValidationUtils.requirePositiveId(book.getCategoryId(), "Category ID");
        ValidationUtils.requirePositiveId(book.getAuthorId(), "Author ID");
    }

    @Override
    public void ajouter(Book book) throws SQLException {
        validate(book);
        String sql = """
                INSERT INTO book (title, description, publisher, publication_year, page_count, language, cover_image, status, created_at, category_id, author_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                """;
        try (PreparedStatement ps = getConnection().prepareStatement(sql, Statement.RETURN_GENERATED_KEYS)) {
            fillStatement(ps, book, false);
            ps.executeUpdate();
            try (ResultSet rs = ps.getGeneratedKeys()) {
                if (rs.next()) {
                    book.setId(rs.getInt(1));
                }
            }
        }
    }

    @Override
    public void modifier(Book book) throws SQLException {
        validate(book);
        ValidationUtils.requirePositiveId(book.getId(), "Book ID");
        String sql = """
                UPDATE book
                SET title = ?, description = ?, publisher = ?, publication_year = ?, page_count = ?, language = ?, cover_image = ?, status = ?, created_at = ?, category_id = ?, author_id = ?
                WHERE id = ?
                """;
        try (PreparedStatement ps = getConnection().prepareStatement(sql)) {
            fillStatement(ps, book, true);
            ps.executeUpdate();
        }
    }

    @Override
    public void supprimer(int id) throws SQLException {
        try (PreparedStatement ps = getConnection().prepareStatement("DELETE FROM book WHERE id = ?")) {
            ps.setInt(1, id);
            ps.executeUpdate();
        }
    }

    @Override
    public List<Book> afficher() throws SQLException {
        List<Book> books = new ArrayList<>();
        try (PreparedStatement ps = getConnection().prepareStatement("SELECT * FROM book ORDER BY id DESC");
             ResultSet rs = ps.executeQuery()) {
            while (rs.next()) {
                books.add(mapRow(rs));
            }
        }
        return books;
    }

    @Override
    public Optional<Book> getById(int id) throws SQLException {
        try (PreparedStatement ps = getConnection().prepareStatement("SELECT * FROM book WHERE id = ?")) {
            ps.setInt(1, id);
            try (ResultSet rs = ps.executeQuery()) {
                if (rs.next()) {
                    return Optional.of(mapRow(rs));
                }
            }
        }
        return Optional.empty();
    }

    private void fillStatement(PreparedStatement ps, Book book, boolean includeIdAtEnd) throws SQLException {
        ps.setString(1, book.getTitle());
        ps.setString(2, book.getDescription());
        ps.setString(3, book.getPublisher());
        ps.setInt(4, book.getPublicationYear());
        ps.setInt(5, book.getPageCount());
        ps.setString(6, book.getLanguage());
        ps.setString(7, book.getCoverImage());
        ps.setString(8, book.getStatus().getDbValue());
        ps.setTimestamp(9, toTimestamp(book.getCreatedAt()));
        ps.setInt(10, book.getCategoryId());
        ps.setInt(11, book.getAuthorId());
        if (includeIdAtEnd) {
            ps.setInt(12, book.getId());
        }
    }

    private Book mapRow(ResultSet rs) throws SQLException {
        Book book = new Book();
        book.setId(rs.getInt("id"));
        book.setTitle(rs.getString("title"));
        book.setDescription(rs.getString("description"));
        book.setPublisher(rs.getString("publisher"));
        book.setPublicationYear(rs.getInt("publication_year"));
        book.setPageCount(rs.getInt("page_count"));
        book.setLanguage(rs.getString("language"));
        book.setCoverImage(rs.getString("cover_image"));
        book.setStatus(BookStatus.fromDbValue(rs.getString("status")));
        book.setCreatedAt(toLocalDateTime(rs.getTimestamp("created_at")));
        book.setCategoryId(rs.getInt("category_id"));
        book.setAuthorId(rs.getInt("author_id"));
        return book;
    }
}
