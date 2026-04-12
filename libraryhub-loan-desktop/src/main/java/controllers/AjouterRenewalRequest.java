package controllers;

import javafx.collections.FXCollections;
import javafx.fxml.FXML;
import javafx.scene.control.ComboBox;
import javafx.scene.control.TextArea;
import javafx.scene.control.TextField;
import model.RenewalRequest;
import model.RequestStatus;
import services.ServiceRenewalRequest;

import java.sql.SQLException;
import java.time.LocalDateTime;

public class AjouterRenewalRequest extends AbstractDialogController<RenewalRequest> {
    @FXML
    private TextField loanIdField;
    @FXML
    private TextField memberIdField;
    @FXML
    private TextField requestedAtField;
    @FXML
    private ComboBox<RequestStatus> statusCombo;
    @FXML
    private TextArea notesArea;

    private final ServiceRenewalRequest serviceRenewalRequest = new ServiceRenewalRequest();

    @FXML
    private void initialize() {
        statusCombo.setItems(FXCollections.observableArrayList(RequestStatus.values()));
        statusCombo.setValue(RequestStatus.PENDING);
        requestedAtField.setText(FormParsers.formatDateTime(LocalDateTime.now()));
    }

    @Override
    protected void populateForm(RenewalRequest request) {
        if (request == null) {
            return;
        }
        loanIdField.setText(request.getLoanId() == null ? "" : String.valueOf(request.getLoanId()));
        memberIdField.setText(request.getMemberId() == null ? "" : String.valueOf(request.getMemberId()));
        requestedAtField.setText(FormParsers.formatDateTime(request.getRequestedAt()));
        statusCombo.setValue(request.getStatus());
        notesArea.setText(request.getNotes());
    }

    @FXML
    private void handleSave() {
        try {
            RenewalRequest request = currentEntity == null ? new RenewalRequest() : currentEntity;
            request.setLoanId(FormParsers.parseInteger(loanIdField.getText(), "Loan ID"));
            request.setMemberId(FormParsers.parseInteger(memberIdField.getText(), "Member ID"));
            request.setRequestedAt(FormParsers.parseDateTime(requestedAtField.getText(), "Requested at", true));
            request.setStatus(statusCombo.getValue());
            request.setNotes(notesArea.getText());

            if (request.getId() == null) {
                serviceRenewalRequest.ajouter(request);
            } else {
                serviceRenewalRequest.modifier(request);
            }
            notifySavedAndClose();
        } catch (IllegalArgumentException | SQLException exception) {
            showError(exception.getMessage());
        }
    }
}
