package controllers;

import javafx.beans.property.SimpleStringProperty;
import javafx.fxml.FXML;
import javafx.scene.control.TableColumn;
import javafx.scene.control.TableView;
import model.LoanRequest;
import services.ServiceLoanRequest;

import java.io.IOException;
import java.sql.SQLException;

public class AfficherLoanRequest {
    @FXML
    private TableView<LoanRequest> requestTable;
    @FXML
    private TableColumn<LoanRequest, String> idColumn;
    @FXML
    private TableColumn<LoanRequest, String> memberIdColumn;
    @FXML
    private TableColumn<LoanRequest, String> bookIdColumn;
    @FXML
    private TableColumn<LoanRequest, String> desiredLoanDateColumn;
    @FXML
    private TableColumn<LoanRequest, String> desiredReturnDateColumn;
    @FXML
    private TableColumn<LoanRequest, String> statusColumn;
    @FXML
    private TableColumn<LoanRequest, String> phoneColumn;

    private final ServiceLoanRequest serviceLoanRequest = new ServiceLoanRequest();

    @FXML
    private void initialize() {
        idColumn.setCellValueFactory(data -> new SimpleStringProperty(String.valueOf(data.getValue().getId())));
        memberIdColumn.setCellValueFactory(data -> new SimpleStringProperty(String.valueOf(data.getValue().getMemberId())));
        bookIdColumn.setCellValueFactory(data -> new SimpleStringProperty(String.valueOf(data.getValue().getBookId())));
        desiredLoanDateColumn.setCellValueFactory(data -> new SimpleStringProperty(String.valueOf(data.getValue().getDesiredLoanDate())));
        desiredReturnDateColumn.setCellValueFactory(data -> new SimpleStringProperty(String.valueOf(data.getValue().getDesiredReturnDate())));
        statusColumn.setCellValueFactory(data -> new SimpleStringProperty(String.valueOf(data.getValue().getStatus())));
        phoneColumn.setCellValueFactory(data -> new SimpleStringProperty(data.getValue().getPhoneNumber()));
        refreshTable();
    }

    @FXML
    private void handleAdd() {
        try {
            ControllerHelper.openDialog("AjouterLoanRequest.fxml", "Add Loan Request", null, this::refreshTable);
        } catch (IOException exception) {
            ControllerHelper.showError(exception.getMessage());
        }
    }

    @FXML
    private void handleEdit() {
        LoanRequest selected = getSelectedRequest();
        if (selected == null) {
            return;
        }
        try {
            ControllerHelper.openDialog("AjouterLoanRequest.fxml", "Edit Loan Request", selected, this::refreshTable);
        } catch (IOException exception) {
            ControllerHelper.showError(exception.getMessage());
        }
    }

    @FXML
    private void handleDelete() {
        LoanRequest selected = getSelectedRequest();
        if (selected == null || !ControllerHelper.confirm("Delete loan request #" + selected.getId() + "?")) {
            return;
        }
        try {
            serviceLoanRequest.supprimer(selected.getId());
            refreshTable();
        } catch (SQLException exception) {
            ControllerHelper.showError(exception.getMessage());
        }
    }

    @FXML
    private void handleApprove() {
        LoanRequest selected = getSelectedRequest();
        if (selected == null) {
            return;
        }
        try {
            serviceLoanRequest.approveRequest(selected.getId());
            refreshTable();
        } catch (SQLException | IllegalArgumentException exception) {
            ControllerHelper.showError(exception.getMessage());
        }
    }

    @FXML
    private void handleReject() {
        LoanRequest selected = getSelectedRequest();
        if (selected == null) {
            return;
        }
        ControllerHelper.prompt("Reject Loan Request", "Optional rejection reason:", "").ifPresent(reason -> {
            try {
                serviceLoanRequest.rejectRequest(selected.getId(), reason);
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

    private LoanRequest getSelectedRequest() {
        LoanRequest selected = requestTable.getSelectionModel().getSelectedItem();
        if (selected == null) {
            ControllerHelper.showError("Select a loan request first.");
        }
        return selected;
    }

    private void refreshTable() {
        try {
            requestTable.getItems().setAll(serviceLoanRequest.afficher());
        } catch (SQLException exception) {
            ControllerHelper.showError(exception.getMessage());
        }
    }
}
