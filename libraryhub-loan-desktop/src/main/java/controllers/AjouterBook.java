package controllers;

import javafx.collections.FXCollections;
import javafx.fxml.FXML;
import javafx.scene.control.ComboBox;
import javafx.scene.control.TextArea;
import javafx.scene.control.TextField;
import model.Book;
import model.BookStatus;
import services.ServiceBook;

import java.sql.SQLException;
import java.time.LocalDateTime;

public class AjouterBook extends AbstractDialogController<Book> {
    @FXML
    private TextField titleField;
    @FXML
    private TextArea descriptionArea;
    @FXML
    private TextField publisherField;
    @FXML
    private TextField publicationYearField;
    @FXML
    private TextField pageCountField;
    @FXML
    private TextField languageField;
    @FXML
    private TextField coverImageField;
    @FXML
    private ComboBox<BookStatus> statusCombo;
    @FXML
    private TextField categoryIdField;
    @FXML
    private TextField authorIdField;

    private final ServiceBook serviceBook = new ServiceBook();

    @FXML
    private void initialize() {
        statusCombo.setItems(FXCollections.observableArrayList(BookStatus.values()));
        statusCombo.setValue(BookStatus.AVAILABLE);
        categoryIdField.setText("1");
        authorIdField.setText("1");
    }

    @Override
    protected void populateForm(Book book) {
        if (book == null) {
            return;
        }
        titleField.setText(book.getTitle());
        descriptionArea.setText(book.getDescription());
        publisherField.setText(book.getPublisher());
        publicationYearField.setText(book.getPublicationYear() == null ? "" : String.valueOf(book.getPublicationYear()));
        pageCountField.setText(book.getPageCount() == null ? "" : String.valueOf(book.getPageCount()));
        languageField.setText(book.getLanguage());
        coverImageField.setText(book.getCoverImage());
        statusCombo.setValue(book.getStatus());
        categoryIdField.setText(book.getCategoryId() == null ? "1" : String.valueOf(book.getCategoryId()));
        authorIdField.setText(book.getAuthorId() == null ? "1" : String.valueOf(book.getAuthorId()));
    }

    @FXML
    private void handleSave() {
        try {
            Book book = currentEntity == null ? new Book() : currentEntity;
            book.setTitle(titleField.getText());
            book.setDescription(descriptionArea.getText());
            book.setPublisher(publisherField.getText());
            book.setPublicationYear(FormParsers.parseInteger(publicationYearField.getText(), "Publication year"));
            book.setPageCount(FormParsers.parseInteger(pageCountField.getText(), "Page count"));
            book.setLanguage(languageField.getText());
            book.setCoverImage(coverImageField.getText());
            book.setStatus(statusCombo.getValue());
            book.setCategoryId(FormParsers.parseInteger(categoryIdField.getText(), "Category ID"));
            book.setAuthorId(FormParsers.parseInteger(authorIdField.getText(), "Author ID"));
            if (book.getCreatedAt() == null) {
                book.setCreatedAt(LocalDateTime.now());
            }

            if (book.getId() == null) {
                serviceBook.ajouter(book);
            } else {
                serviceBook.modifier(book);
            }
            notifySavedAndClose();
        } catch (IllegalArgumentException | SQLException exception) {
            showError(exception.getMessage());
        }
    }
}
