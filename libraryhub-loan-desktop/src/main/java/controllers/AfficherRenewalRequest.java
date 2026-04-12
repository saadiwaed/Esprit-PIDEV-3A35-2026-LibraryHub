package controllers;

import javafx.beans.property.SimpleStringProperty;
import javafx.fxml.FXML;
import javafx.scene.control.TableColumn;
import javafx.scene.control.TableView;
import model.RenewalRequest;
import services.ServiceRenewalRequest;

import java.io.IOException;
import java.sql.SQLException;

public class AfficherRenewalRequest {
    @FXML
    private TableView<RenewalRequest> requestTable;
    @FXML
    private TableColumn<RenewalRequest, String> idColumn;
    @FXML
    private TableColumn<RenewalRequest, String> loanIdColumn;
    @FXML
    private TableColumn<RenewalRequest, String> memberIdColumn;
    @FXML
    private TableColumn<RenewalRequest, String> requestedAtColumn;
    @FXML
    private TableColumn<RenewalRequest, String> statusColumn;
    @FXML
    private TableColumn<RenewalRequest, String> notesColumn;

    private final ServiceRenewalRequest serviceRenewalRequest = new ServiceRenewalRequest();

    @FXML
    private void initialize() {
        idColumn.setCellValueFactory(data -> new SimpleStringProperty(String.valueOf(data.getValue().getId())));
        loanIdColumn.setCellValueFactory(data -> new SimpleStringProperty(String.valueOf(data.getValue().getLoanId())));
        memberIdColumn.setCellValueFactory(data -> new SimpleStringProperty(String.valueOf(data.getValue().getMemberId())));
        requestedAtColumn.setCellValueFactory(data -> new SimpleStringProperty(FormParsers.formatDateTime(data.getValue().getRequestedAt())));
        statusColumn.setCellValueFactory(data -> new SimpleStringProperty(String.valueOf(data.getValue().getStatus())));
        notesColumn.setCellValueFactory(data -> new SimpleStringProperty(data.getValue().getNotes() == null ? "" : data.getValue().getNotes()));
        refreshTable();
    }

    @FXML
    private void handleAdd() {
        try {
            ControllerHelper.openDialog("AjouterRenewalRequest.fxml", "Add Renewal Request", null, this::refreshTable);
        } catch (IOException exception) {
            ControllerHelper.showError(exception.getMessage());
        }
    }

    @FXML
    private void handleEdit() {
        RenewalRequest selected = getSelectedRequest();
        if (selected == null) {
            return;
        }
        try {
            ControllerHelper.openDialog("AjouterRenewalRequest.fxml", "Edit Renewal Request", selected, this::refreshTable);
        } catch (IOException exception) {
            ControllerHelper.showError(exception.getMessage());
        }
    }

    @FXML
    private void handleDelete() {
        RenewalRequest selected = getSelectedRequest();
        if (selected == null || !ControllerHelper.confirm("Delete renewal request #" + selected.getId() + "?")) {
            return;
        }
        try {
            serviceRenewalRequest.supprimer(selected.getId());
            refreshTable();
        } catch (SQLException exception) {
            ControllerHelper.showError(exception.getMessage());
        }
    }

    @FXML
    private void handleApprove() {
        RenewalRequest selected = getSelectedRequest();
        if (selected == null) {
            return;
        }
        try {
            serviceRenewalRequest.approveRequest(selected.getId());
            refreshTable();
        } catch (SQLException | IllegalArgumentException exception) {
            ControllerHelper.showError(exception.getMessage());
        }
    }

    @FXML
    private void handleReject() {
        RenewalRequest selected = getSelectedRequest();
        if (selected == null) {
            return;
        }
        ControllerHelper.prompt("Reject Renewal Request", "Optional rejection reason:", "").ifPresent(reason -> {
            try {
                serviceRenewalRequest.rejectRequest(selected.getId(), reason);
                refreshTable();
            } catch (SQLException | IllegalArgumentException exception) {
                ControllerHelper.showError(exception.getMessage());
            }
        });
    }

    @FXML
    private void handleRefresh() {
        refreshTable();
    }

    private RenewalRequest getSelectedRequest() {
        RenewalRequest selected = requestTable.getSelectionModel().getSelectedItem();
        if (selected == null) {
            ControllerHelper.showError("Select a renewal request first.");
        }
        return selected;
    }

    private void refreshTable() {
        try {
            requestTable.getItems().setAll(serviceRenewalRequest.afficher());
        } catch (SQLException exception) {
            ControllerHelper.showError(exception.getMessage());
        }
    }
}
