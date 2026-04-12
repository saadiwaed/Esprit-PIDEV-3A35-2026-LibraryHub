package controllers;

import javafx.beans.property.SimpleStringProperty;
import javafx.fxml.FXML;
import javafx.scene.control.TableColumn;
import javafx.scene.control.TableView;
import model.BookCopy;
import services.ServiceBookCopy;

import java.io.IOException;
import java.sql.SQLException;

public class AfficherBookCopy {
    @FXML
    private TableView<BookCopy> copyTable;
    @FXML
    private TableColumn<BookCopy, String> idColumn;

    private final ServiceBookCopy serviceBookCopy = new ServiceBookCopy();

    @FXML
    private void initialize() {
        idColumn.setCellValueFactory(data -> new SimpleStringProperty(String.valueOf(data.getValue().getId())));
        refreshTable();
    }

    @FXML
    private void handleAdd() {
        try {
            ControllerHelper.openDialog("AjouterBookCopy.fxml", "Add Book Copy", null, this::refreshTable);
        } catch (IOException exception) {
            ControllerHelper.showError(exception.getMessage());
        }
    }

    @FXML
    private void handleEdit() {
        BookCopy selected = copyTable.getSelectionModel().getSelectedItem();
        if (selected == null) {
            ControllerHelper.showError("Select a copy first.");
            return;
        }
        try {
            ControllerHelper.openDialog("AjouterBookCopy.fxml", "View Book Copy", selected, this::refreshTable);
        } catch (IOException exception) {
            ControllerHelper.showError(exception.getMessage());
        }
    }

    @FXML
    private void handleDelete() {
        BookCopy selected = copyTable.getSelectionModel().getSelectedItem();
        if (selected == null) {
            ControllerHelper.showError("Select a copy first.");
            return;
        }
        if (!ControllerHelper.confirm("Delete copy #" + selected.getId() + "?")) {
            return;
        }
        try {
            serviceBookCopy.supprimer(selected.getId());
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
            copyTable.getItems().setAll(serviceBookCopy.afficher());
        } catch (SQLException exception) {
            ControllerHelper.showError(exception.getMessage());
        }
    }
}
