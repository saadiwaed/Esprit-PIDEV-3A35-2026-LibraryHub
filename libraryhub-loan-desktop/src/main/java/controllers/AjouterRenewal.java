package controllers;

import javafx.fxml.FXML;
import javafx.scene.control.DatePicker;
import javafx.scene.control.TextField;
import model.Renewal;
import services.ServiceRenewal;

import java.sql.SQLException;
import java.time.LocalDateTime;

public class AjouterRenewal extends AbstractDialogController<Renewal> {
    @FXML
    private DatePicker previousDueDatePicker;
    @FXML
    private DatePicker newDueDatePicker;
    @FXML
    private TextField renewedAtField;
    @FXML
    private TextField renewalNumberField;
    @FXML
    private TextField loanIdField;

    private final ServiceRenewal serviceRenewal = new ServiceRenewal();

    @FXML
    private void initialize() {
        renewedAtField.setText(FormParsers.formatDateTime(LocalDateTime.now()));
        renewalNumberField.setText("1");
    }

    @Override
    protected void populateForm(Renewal renewal) {
        if (renewal == null) {
            return;
        }
        previousDueDatePicker.setValue(renewal.getPreviousDueDate());
        newDueDatePicker.setValue(renewal.getNewDueDate());
        renewedAtField.setText(FormParsers.formatDateTime(renewal.getRenewedAt()));
        renewalNumberField.setText(String.valueOf(renewal.getRenewalNumber()));
        loanIdField.setText(renewal.getLoanId() == null ? "" : String.valueOf(renewal.getLoanId()));
    }

    @FXML
    private void handleSave() {
        try {
            Renewal renewal = currentEntity == null ? new Renewal() : currentEntity;
            renewal.setPreviousDueDate(FormParsers.requireDate(previousDueDatePicker.getValue(), "Previous due date"));
            renewal.setNewDueDate(FormParsers.requireDate(newDueDatePicker.getValue(), "New due date"));
            renewal.setRenewedAt(FormParsers.parseDateTime(renewedAtField.getText(), "Renewed at", true));
            renewal.setRenewalNumber(FormParsers.parseInteger(renewalNumberField.getText(), "Renewal number"));
            renewal.setLoanId(FormParsers.parseInteger(loanIdField.getText(), "Loan ID"));

            if (renewal.getId() == null) {
                serviceRenewal.ajouter(renewal);
            } else {
                serviceRenewal.modifier(renewal);
            }
            notifySavedAndClose();
        } catch (IllegalArgumentException | SQLException exception) {
            showError(exception.getMessage());
        }
    }
}
