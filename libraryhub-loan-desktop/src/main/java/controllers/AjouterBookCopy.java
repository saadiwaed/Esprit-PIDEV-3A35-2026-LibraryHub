package controllers;

import javafx.fxml.FXML;
import javafx.scene.control.Label;
import model.BookCopy;
import services.ServiceBookCopy;

import java.sql.SQLException;

public class AjouterBookCopy extends AbstractDialogController<BookCopy> {
    @FXML
    private Label infoLabel;

    private final ServiceBookCopy serviceBookCopy = new ServiceBookCopy();

    @Override
    protected void populateForm(BookCopy bookCopy) {
        if (bookCopy == null || bookCopy.getId() == null) {
            infoLabel.setText("Creating a new copy will only generate a new ID, matching the Symfony BookCopy entity.");
            return;
        }
        infoLabel.setText("Copy #" + bookCopy.getId() + " has no editable attributes in the source Symfony model.");
    }

    @FXML
    private void handleSave() {
        try {
            BookCopy copy = currentEntity == null ? new BookCopy() : currentEntity;
            if (copy.getId() == null) {
                serviceBookCopy.ajouter(copy);
            } else {
                serviceBookCopy.modifier(copy);
            }
            notifySavedAndClose();
        } catch (IllegalArgumentException | SQLException exception) {
            showError(exception.getMessage());
        }
    }
}
