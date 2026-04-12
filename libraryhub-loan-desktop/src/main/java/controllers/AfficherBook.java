package controllers;

import javafx.beans.property.SimpleStringProperty;
import javafx.fxml.FXML;
import javafx.scene.control.TableColumn;
import javafx.scene.control.TableView;
import model.Book;
import services.ServiceBook;

import java.io.IOException;
import java.sql.SQLException;

public class AfficherBook {
    @FXML
    private TableView<Book> bookTable;
    @FXML
    private TableColumn<Book, String> idColumn;
    @FXML
    private TableColumn<Book, String> titleColumn;
    @FXML
    private TableColumn<Book, String> statusColumn;
    @FXML
    private TableColumn<Book, String> yearColumn;
    @FXML
    private TableColumn<Book, String> pagesColumn;

    private final ServiceBook serviceBook = new ServiceBook();

    @FXML
    private void initialize() {
        idColumn.setCellValueFactory(data -> new SimpleStringProperty(String.valueOf(data.getValue().getId())));
        titleColumn.setCellValueFactory(data -> new SimpleStringProperty(data.getValue().getTitle()));
        statusColumn.setCellValueFactory(data -> new SimpleStringProperty(String.valueOf(data.getValue().getStatus())));
        yearColumn.setCellValueFactory(data -> new SimpleStringProperty(String.valueOf(data.getValue().getPublicationYear())));
        pagesColumn.setCellValueFactory(data -> new SimpleStringProperty(String.valueOf(data.getValue().getPageCount())));
        refreshTable();
    }

    @FXML
    private void handleAdd() {
        try {
            ControllerHelper.openDialog("AjouterBook.fxml", "Add Book", null, this::refreshTable);
        } catch (IOException exception) {
            ControllerHelper.showError(exception.getMessage());
        }
    }

    @FXML
    private void handleEdit() {
        Book selected = bookTable.getSelectionModel().getSelectedItem();
        if (selected == null) {
            ControllerHelper.showError("Select a book first.");
            return;
        }
        try {
            ControllerHelper.openDialog("AjouterBook.fxml", "Edit Book", selected, this::refreshTable);
        } catch (IOException exception) {
            ControllerHelper.showError(exception.getMessage());
        }
    }

    @FXML
    private void handleDelete() {
        Book selected = bookTable.getSelectionModel().getSelectedItem();
        if (selected == null) {
            ControllerHelper.showError("Select a book first.");
            return;
        }
        if (!ControllerHelper.confirm("Delete book #" + selected.getId() + "?")) {
            return;
        }
        try {
            serviceBook.supprimer(selected.getId());
            refreshTable();
        } catch (SQLException exception) {
            ControllerHelper.showError(exception.getMessage());
        }
    }

    @FXML
    private void handleRefresh() {
        refreshTable();
    }

    private void refreshTable() {
        try {
            bookTable.getItems().setAll(serviceBook.afficher());
        } catch (SQLException exception) {
            ControllerHelper.showError(exception.getMessage());
        }
    }
}
