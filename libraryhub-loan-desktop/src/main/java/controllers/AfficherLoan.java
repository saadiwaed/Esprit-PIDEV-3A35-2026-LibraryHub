package controllers;

import javafx.beans.property.SimpleStringProperty;
import javafx.fxml.FXML;
import javafx.scene.control.TableColumn;
import javafx.scene.control.TableView;
import model.Loan;
import services.ServiceLoan;

import java.io.IOException;
import java.math.BigDecimal;
import java.sql.SQLException;
import java.time.LocalDate;

public class AfficherLoan {
    @FXML
    private TableView<Loan> loanTable;
    @FXML
    private TableColumn<Loan, String> idColumn;
    @FXML
    private TableColumn<Loan, String> copyColumn;
    @FXML
    private TableColumn<Loan, String> memberColumn;
    @FXML
    private TableColumn<Loan, String> checkoutColumn;
    @FXML
    private TableColumn<Loan, String> dueColumn;
    @FXML
    private TableColumn<Loan, String> returnColumn;
    @FXML
    private TableColumn<Loan, String> statusColumn;
    @FXML
    private TableColumn<Loan, String> renewalsColumn;
    @FXML
    private TableColumn<Loan, String> lateDaysColumn;

    private final ServiceLoan serviceLoan = new ServiceLoan();

    @FXML
    private void initialize() {
        idColumn.setCellValueFactory(data -> new SimpleStringProperty(String.valueOf(data.getValue().getId())));
        copyColumn.setCellValueFactory(data -> new SimpleStringProperty(String.valueOf(data.getValue().getBookCopyId())));
        memberColumn.setCellValueFactory(data -> new SimpleStringProperty(String.valueOf(data.getValue().getMemberId())));
        checkoutColumn.setCellValueFactory(data -> new SimpleStringProperty(FormParsers.formatDateTime(data.getValue().getCheckoutTime())));
        dueColumn.setCellValueFactory(data -> new SimpleStringProperty(String.valueOf(data.getValue().getDueDate())));
        returnColumn.setCellValueFactory(data -> new SimpleStringProperty(FormParsers.formatDateTime(data.getValue().getReturnDate())));
        statusColumn.setCellValueFactory(data -> new SimpleStringProperty(String.valueOf(data.getValue().getStatus())));
        renewalsColumn.setCellValueFactory(data -> new SimpleStringProperty(String.valueOf(data.getValue().getRenewalCount())));
        lateDaysColumn.setCellValueFactory(data -> new SimpleStringProperty(String.valueOf(data.getValue().getDaysLate())));
        refreshTable();
    }

    @FXML
    private void handleAdd() {
        try {
            ControllerHelper.openDialog("AjouterLoan.fxml", "Add Loan", null, this::refreshTable);
        } catch (IOException exception) {
            ControllerHelper.showError(exception.getMessage());
        }
    }

    @FXML
    private void handleEdit() {
        Loan selected = getSelectedLoan();
        if (selected == null) {
            return;
        }
        try {
            ControllerHelper.openDialog("AjouterLoan.fxml", "Edit Loan", selected, this::refreshTable);
        } catch (IOException exception) {
            ControllerHelper.showError(exception.getMessage());
        }
    }

    @FXML
    private void handleDelete() {
        Loan selected = getSelectedLoan();
        if (selected == null || !ControllerHelper.confirm("Delete loan #" + selected.getId() + "?")) {
            return;
        }
        try {
            serviceLoan.supprimer(selected.getId());
            refreshTable();
        } catch (SQLException exception) {
            ControllerHelper.showError(exception.getMessage());
        }
    }

    @FXML
    private void handleReturn() {
        Loan selected = getSelectedLoan();
        if (selected == null) {
            return;
        }
        try {
            serviceLoan.returnLoan(selected.getId());
            refreshTable();
        } catch (SQLException | IllegalArgumentException exception) {
            ControllerHelper.showError(exception.getMessage());
        }
    }

    @FXML
    private void handleRenew() {
        Loan selected = getSelectedLoan();
        if (selected == null) {
            return;
        }
        String defaultDate = String.valueOf(selected.getDueDate().plusDays(14));
        ControllerHelper.prompt("Renew Loan", "Enter the new due date (yyyy-MM-dd):", defaultDate).ifPresent(value -> {
            try {
                serviceLoan.renewLoan(selected.getId(), LocalDate.parse(value.trim()));
                refreshTable();
            } catch (Exception exception) {
                ControllerHelper.showError(exception.getMessage());
            }
        });
    }

    @FXML
    private void handleRefreshOverdue() {
        try {
            int updated = serviceLoan.refreshOverdueStatuses();
            refreshTable();
            ControllerHelper.showInfo(updated + " loan(s) updated to OVERDUE.");
        } catch (SQLException exception) {
            ControllerHelper.showError(exception.getMessage());
        }
    }

    @FXML
    private void handleSyncPenalties() {
        try {
            int updated = serviceLoan.generateOrUpdateOverduePenalties();
            ControllerHelper.showInfo(updated + " late penalty record(s) synced.");
        } catch (SQLException exception) {
            ControllerHelper.showError(exception.getMessage());
        }
    }

    @FXML
    private void handleCreateLatePenalty() {
        Loan selected = getSelectedLoan();
        if (selected == null) {
            return;
        }
        ControllerHelper.prompt("Create Late Penalty", "Enter daily rate for the penalty:", "0.50").ifPresent(value -> {
            try {
                serviceLoan.createLatePenalty(selected.getId(), new BigDecimal(value.trim()), null);
                ControllerHelper.showInfo("Late penalty created.");
            } catch (Exception exception) {
                ControllerHelper.showError(exception.getMessage());
            }
        });
    }

    @FXML
    private void handleRefresh() {
        refreshTable();
    }

    private Loan getSelectedLoan() {
        Loan selected = loanTable.getSelectionModel().getSelectedItem();
        if (selected == null) {
            ControllerHelper.showError("Select a loan first.");
        }
        return selected;
    }

    private void refreshTable() {
        try {
            loanTable.getItems().setAll(serviceLoan.afficher());
        } catch (SQLException exception) {
            ControllerHelper.showError(exception.getMessage());
        }
    }
}
