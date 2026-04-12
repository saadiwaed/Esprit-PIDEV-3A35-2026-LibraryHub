package controllers;

import javafx.beans.property.SimpleStringProperty;
import javafx.fxml.FXML;
import javafx.scene.control.TableColumn;
import javafx.scene.control.TableView;
import model.Renewal;
import services.ServiceRenewal;

import java.io.IOException;
import java.sql.SQLException;

public class AfficherRenewal {
    @FXML
    private TableView<Renewal> renewalTable;
    @FXML
    private TableColumn<Renewal, String> idColumn;
    @FXML
    private TableColumn<Renewal, String> loanIdColumn;
    @FXML
    private TableColumn<Renewal, String> previousDueColumn;
    @FXML
    private TableColumn<Renewal, String> newDueColumn;
    @FXML
    private TableColumn<Renewal, String> renewedAtColumn;
    @FXML
    private TableColumn<Renewal, String> renewalNumberColumn;

    private final ServiceRenewal serviceRenewal = new ServiceRenewal();

    @FXML
    private void initialize() {
        idColumn.setCellValueFactory(data -> new SimpleStringProperty(String.valueOf(data.getValue().getId())));
        loanIdColumn.setCellValueFactory(data -> new SimpleStringProperty(String.valueOf(data.getValue().getLoanId())));
        previousDueColumn.setCellValueFactory(data -> new SimpleStringProperty(String.valueOf(data.getValue().getPreviousDueDate())));
        newDueColumn.setCellValueFactory(data -> new SimpleStringProperty(String.valueOf(data.getValue().getNewDueDate())));
        renewedAtColumn.setCellValueFactory(data -> new SimpleStringProperty(FormParsers.formatDateTime(data.getValue().getRenewedAt())));
        renewalNumberColumn.setCellValueFactory(data -> new SimpleStringProperty(String.valueOf(data.getValue().getRenewalNumber())));
        refreshTable();
    }

    @FXML
    private void handleAdd() {
        try {
            ControllerHelper.openDialog("AjouterRenewal.fxml", "Add Renewal", null, this::refreshTable);
        } catch (IOException exception) {
            ControllerHelper.showError(exception.getMessage());
        }
    }

    @FXML
    private void handleEdit() {
        Renewal selected = renewalTable.getSelectionModel().getSelectedItem();
        if (selected == null) {
            ControllerHelper.showError("Select a renewal first.");
            return;
        }
        try {
            ControllerHelper.openDialog("AjouterRenewal.fxml", "Edit Renewal", selected, this::refreshTable);
        } catch (IOException exception) {
            ControllerHelper.showError(exception.getMessage());
        }
    }

    @FXML
    private void handleDelete() {
        Renewal selected = renewalTable.getSelectionModel().getSelectedItem();
        if (selected == null) {
            ControllerHelper.showError("Select a renewal first.");
            return;
        }
        if (!ControllerHelper.confirm("Delete renewal #" + selected.getId() + "?")) {
            return;
        }
        try {
            serviceRenewal.supprimer(selected.getId());
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
            renewalTable.getItems().setAll(serviceRenewal.afficher());
        } catch (SQLException exception) {
            ControllerHelper.showError(exception.getMessage());
        }
    }
}
