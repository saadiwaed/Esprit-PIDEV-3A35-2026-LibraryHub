package services;

import model.BookCopy;

import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Statement;
import java.util.ArrayList;
import java.util.List;
import java.util.Optional;

public class ServiceBookCopy extends AbstractService implements IService<BookCopy> {
    public void validate(BookCopy bookCopy) {
        require(bookCopy != null, "Book copy cannot be null.");
    }

    @Override
    public void ajouter(BookCopy bookCopy) throws SQLException {
        validate(bookCopy);
        try (PreparedStatement ps = getConnection().prepareStatement("INSERT INTO book_copy () VALUES ()", Statement.RETURN_GENERATED_KEYS)) {
            ps.executeUpdate();
            try (ResultSet rs = ps.getGeneratedKeys()) {
                if (rs.next()) {
                    bookCopy.setId(rs.getInt(1));
                }
            }
        }
    }

    @Override
    public void modifier(BookCopy bookCopy) {
        validate(bookCopy);
    }

    @Override
    public void supprimer(int id) throws SQLException {
        try (PreparedStatement ps = getConnection().prepareStatement("DELETE FROM book_copy WHERE id = ?")) {
            ps.setInt(1, id);
            ps.executeUpdate();
        }
    }

    @Override
    public List<BookCopy> afficher() throws SQLException {
        List<BookCopy> copies = new ArrayList<>();
        try (PreparedStatement ps = getConnection().prepareStatement("SELECT id FROM book_copy ORDER BY id DESC");
             ResultSet rs = ps.executeQuery()) {
            while (rs.next()) {
                BookCopy copy = new BookCopy();
                copy.setId(rs.getInt("id"));
                copies.add(copy);
            }
        }
        return copies;
    }

    @Override
    public Optional<BookCopy> getById(int id) throws SQLException {
        try (PreparedStatement ps = getConnection().prepareStatement("SELECT id FROM book_copy WHERE id = ?")) {
            ps.setInt(1, id);
            try (ResultSet rs = ps.executeQuery()) {
                if (rs.next()) {
                    BookCopy copy = new BookCopy();
                    copy.setId(rs.getInt("id"));
                    return Optional.of(copy);
                }
            }
        }
        return Optional.empty();
    }
}
