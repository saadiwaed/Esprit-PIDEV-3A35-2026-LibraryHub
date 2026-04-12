package controllers;

import javafx.beans.property.SimpleStringProperty;
import javafx.fxml.FXML;
import javafx.scene.control.TableColumn;
import javafx.scene.control.TableView;
import model.Penalty;
import services.ServicePenalty;

import java.io.IOException;
import java.sql.SQLException;

public class AfficherPenalty {
    @FXML
    private TableView<Penalty> penaltyTable;
    @FXML
    private TableColumn<Penalty, String> idColumn;
    @FXML
    private TableColumn<Penalty, String> loanIdColumn;
    @FXML
    private TableColumn<Penalty, String> amountColumn;
    @FXML
    private TableColumn<Penalty, String> lateDaysColumn;
    @FXML
    private TableColumn<Penalty, String> reasonColumn;
    @FXML
    private TableColumn<Penalty, String> issueDateColumn;
    @FXML
    private TableColumn<Penalty, String> statusColumn;
    @FXML
    private TableColumn<Penalty, String> waivedColumn;

    private final ServicePenalty servicePenalty = new ServicePenalty();

    @FXML
    private void initialize() {
        idColumn.setCellValueFactory(data -> new SimpleStringProperty(String.valueOf(data.getValue().getId())));
        loanIdColumn.setCellValueFactory(data -> new SimpleStringProperty(String.valueOf(data.getValue().getLoanId())));
        amountColumn.setCellValueFactory(data -> new SimpleStringProperty(data.getValue().getAmount().toPlainString()));
        lateDaysColumn.setCellValueFactory(data -> new SimpleStringProperty(String.valueOf(data.getValue().getLateDays())));
        reasonColumn.setCellValueFactory(data -> new SimpleStringProperty(data.getValue().getReason()));
        issueDateColumn.setCellValueFactory(data -> new SimpleStringProperty(String.valueOf(data.getValue().getIssueDate())));
        statusColumn.setCellValueFactory(data -> new SimpleStringProperty(String.valueOf(data.getValue().getStatus())));
        waivedColumn.setCellValueFactory(data -> new SimpleStringProperty(data.getValue().isWaived() ? "Yes" : "No"));
        refreshTable();
    }

    @FXML
    private void handleAdd() {
        try {
            ControllerHelper.openDialog("AjouterPenalty.fxml", "Add Penalty", null, this::refreshTable);
        } catch (IOException exception) {
            ControllerHelper.showError(exception.getMessage());
        }
    }

    @FXML
    private void handleEdit() {
        Penalty selected = penaltyTable.getSelectionModel().getSelectedItem();
        if (selected == null) {
            ControllerHelper.showError("Select a penalty first.");
            return;
        }
        try {
            ControllerHelper.openDialog("AjouterPenalty.fxml", "Edit Penalty", selected, this::refreshTable);
        } catch (IOException exception) {
            ControllerHelper.showError(exception.getMessage());
        }
    }

    @FXML
    private void handleDelete() {
        Penalty selected = penaltyTable.getSelectionModel().getSelectedItem();
        if (selected == null) {
            ControllerHelper.showError("Select a penalty first.");
            return;
        }
        if (!ControllerHelper.confirm("Delete penalty #" + selected.getId() + "?")) {
            return;
        }
        try {
            servicePenalty.supprimer(selected.getId());
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
            penaltyTable.getItems().setAll(servicePenalty.afficher());
        } catch (SQLException exception) {
            ControllerHelper.showError(exception.getMessage());
        }
    }
}
