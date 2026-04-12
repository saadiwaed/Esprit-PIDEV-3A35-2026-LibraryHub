package controllers;

import javafx.collections.FXCollections;
import javafx.fxml.FXML;
import javafx.scene.control.ComboBox;
import javafx.scene.control.DatePicker;
import javafx.scene.control.TextArea;
import javafx.scene.control.TextField;
import model.LoanRequest;
import model.RequestStatus;
import services.ServiceLoanRequest;

import java.sql.SQLException;
import java.time.LocalDate;
import java.time.LocalDateTime;

public class AjouterLoanRequest extends AbstractDialogController<LoanRequest> {
    @FXML
    private TextField memberIdField;
    @FXML
    private TextField bookIdField;
    @FXML
    private DatePicker desiredLoanDatePicker;
    @FXML
    private DatePicker desiredReturnDatePicker;
    @FXML
    private TextField requestedAtField;
    @FXML
    private ComboBox<RequestStatus> statusCombo;
    @FXML
    private TextField phoneField;
    @FXML
    private TextArea notesArea;

    private final ServiceLoanRequest serviceLoanRequest = new ServiceLoanRequest();

    @FXML
    private void initialize() {
        statusCombo.setItems(FXCollections.observableArrayList(RequestStatus.values()));
        statusCombo.setValue(RequestStatus.PENDING);
        desiredLoanDatePicker.setValue(LocalDate.now());
        desiredReturnDatePicker.setValue(LocalDate.now().plusDays(14));
        requestedAtField.setText(FormParsers.formatDateTime(LocalDateTime.now()));
    }

    @Override
    protected void populateForm(LoanRequest request) {
        if (request == null) {
            return;
        }
        memberIdField.setText(request.getMemberId() == null ? "" : String.valueOf(request.getMemberId()));
        bookIdField.setText(request.getBookId() == null ? "" : String.valueOf(request.getBookId()));
        desiredLoanDatePicker.setValue(request.getDesiredLoanDate());
        desiredReturnDatePicker.setValue(request.getDesiredReturnDate());
        requestedAtField.setText(FormParsers.formatDateTime(request.getRequestedAt()));
        statusCombo.setValue(request.getStatus());
        phoneField.setText(request.getPhoneNumber());
        notesArea.setText(request.getNotes());
    }

    @FXML
    private void handleSave() {
        try {
            LoanRequest request = currentEntity == null ? new LoanRequest() : currentEntity;
            request.setMemberId(FormParsers.parseInteger(memberIdField.getText(), "Member ID"));
            request.setBookId(FormParsers.parseInteger(bookIdField.getText(), "Book ID"));
            request.setDesiredLoanDate(FormParsers.requireDate(desiredLoanDatePicker.getValue(), "Desired loan date"));
            request.setDesiredReturnDate(FormParsers.requireDate(desiredReturnDatePicker.getValue(), "Desired return date"));
            request.setRequestedAt(FormParsers.parseDateTime(requestedAtField.getText(), "Requested at", true));
            request.setStatus(statusCombo.getValue());
            request.setPhoneNumber(phoneField.getText());
            request.setNotes(notesArea.getText());

            if (request.getId() == null) {
                serviceLoanRequest.ajouter(request);
            } else {
                serviceLoanRequest.modifier(request);
            }
            notifySavedAndClose();
        } catch (IllegalArgumentException | SQLException exception) {
            showError(exception.getMessage());
        }
    }
}
