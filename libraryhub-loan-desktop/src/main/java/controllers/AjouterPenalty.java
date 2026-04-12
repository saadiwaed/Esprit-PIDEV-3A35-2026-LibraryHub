package controllers;

import javafx.collections.FXCollections;
import javafx.fxml.FXML;
import javafx.scene.control.CheckBox;
import javafx.scene.control.ComboBox;
import javafx.scene.control.DatePicker;
import javafx.scene.control.TextArea;
import javafx.scene.control.TextField;
import model.PaymentStatus;
import model.Penalty;
import services.ServicePenalty;

import java.math.BigDecimal;
import java.sql.SQLException;
import java.time.LocalDate;

public class AjouterPenalty extends AbstractDialogController<Penalty> {
    @FXML
    private TextField amountField;
    @FXML
    private TextField dailyRateField;
    @FXML
    private TextField lateDaysField;
    @FXML
    private ComboBox<String> reasonCombo;
    @FXML
    private TextField customReasonField;
    @FXML
    private DatePicker issueDatePicker;
    @FXML
    private TextArea notesArea;
    @FXML
    private CheckBox waivedCheck;
    @FXML
    private ComboBox<PaymentStatus> statusCombo;
    @FXML
    private TextField loanIdField;

    private final ServicePenalty servicePenalty = new ServicePenalty();

    @FXML
    private void initialize() {
        reasonCombo.setItems(FXCollections.observableArrayList(
                Penalty.REASON_LATE_RETURN,
                Penalty.REASON_DAMAGED_BOOK,
                Penalty.REASON_OTHER
        ));
        reasonCombo.setValue(Penalty.REASON_LATE_RETURN);
        statusCombo.setItems(FXCollections.observableArrayList(PaymentStatus.values()));
        statusCombo.setValue(PaymentStatus.UNPAID);
        amountField.setText("0.50");
        dailyRateField.setText("0.50");
        lateDaysField.setText("1");
        issueDatePicker.setValue(LocalDate.now());
    }

    @Override
    protected void populateForm(Penalty penalty) {
        if (penalty == null) {
            return;
        }
        amountField.setText(penalty.getAmount() == null ? "" : penalty.getAmount().toPlainString());
        dailyRateField.setText(penalty.getDailyRate() == null ? "" : penalty.getDailyRate().toPlainString());
        lateDaysField.setText(String.valueOf(penalty.getLateDays()));
        if (Penalty.REASON_LATE_RETURN.equals(penalty.getReason()) || Penalty.REASON_DAMAGED_BOOK.equals(penalty.getReason())) {
            reasonCombo.setValue(penalty.getReason());
            customReasonField.clear();
        } else {
            reasonCombo.setValue(Penalty.REASON_OTHER);
            customReasonField.setText(penalty.getReason());
        }
        issueDatePicker.setValue(penalty.getIssueDate());
        notesArea.setText(penalty.getNotes());
        waivedCheck.setSelected(penalty.isWaived());
        statusCombo.setValue(penalty.getStatus());
        loanIdField.setText(penalty.getLoanId() == null ? "" : String.valueOf(penalty.getLoanId()));
    }

    @FXML
    private void handleSave() {
        try {
            Penalty penalty = currentEntity == null ? new Penalty() : currentEntity;
            penalty.setAmount(new BigDecimal(amountField.getText().trim()));
            penalty.setDailyRate(new BigDecimal(dailyRateField.getText().trim()));
            penalty.setLateDays(FormParsers.parseInteger(lateDaysField.getText(), "Late days"));
            String reason = Penalty.REASON_OTHER.equals(reasonCombo.getValue()) ? customReasonField.getText() : reasonCombo.getValue();
            penalty.setReason(reason);
            penalty.setIssueDate(FormParsers.requireDate(issueDatePicker.getValue(), "Issue date"));
            penalty.setNotes(notesArea.getText());
            penalty.setWaived(waivedCheck.isSelected());
            penalty.setStatus(statusCombo.getValue());
            penalty.setLoanId(FormParsers.parseInteger(loanIdField.getText(), "Loan ID"));

            if (penalty.getId() == null) {
                servicePenalty.ajouter(penalty);
            } else {
                servicePenalty.modifier(penalty);
            }
            notifySavedAndClose();
        } catch (IllegalArgumentException | SQLException exception) {
            showError(exception.getMessage());
        }
    }
}
