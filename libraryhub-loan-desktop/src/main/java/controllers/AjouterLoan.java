package controllers;

import javafx.collections.FXCollections;
import javafx.fxml.FXML;
import javafx.scene.control.ComboBox;
import javafx.scene.control.DatePicker;
import javafx.scene.control.TextArea;
import javafx.scene.control.TextField;
import model.Loan;
import model.LoanStatus;
import services.ServiceLoan;

import java.sql.SQLException;
import java.time.LocalDateTime;

public class AjouterLoan extends AbstractDialogController<Loan> {
    @FXML
    private TextField checkoutTimeField;
    @FXML
    private DatePicker dueDatePicker;
    @FXML
    private TextField returnDateField;
    @FXML
    private ComboBox<LoanStatus> statusCombo;
    @FXML
    private TextField renewalCountField;
    @FXML
    private TextArea notesArea;
    @FXML
    private TextField bookCopyIdField;
    @FXML
    private TextField memberIdField;
    @FXML
    private TextField phoneField;

    private final ServiceLoan serviceLoan = new ServiceLoan();

    @FXML
    private void initialize() {
        statusCombo.setItems(FXCollections.observableArrayList(LoanStatus.values()));
        statusCombo.setValue(LoanStatus.ACTIVE);
        renewalCountField.setText("0");
        checkoutTimeField.setText(FormParsers.formatDateTime(LocalDateTime.now()));
    }

    @Override
    protected void populateForm(Loan loan) {
        if (loan == null) {
            return;
        }
        checkoutTimeField.setText(FormParsers.formatDateTime(loan.getCheckoutTime()));
        dueDatePicker.setValue(loan.getDueDate());
        returnDateField.setText(FormParsers.formatDateTime(loan.getReturnDate()));
        statusCombo.setValue(loan.getStatus());
        renewalCountField.setText(String.valueOf(loan.getRenewalCount()));
        notesArea.setText(loan.getNotes());
        bookCopyIdField.setText(loan.getBookCopyId() == null ? "" : String.valueOf(loan.getBookCopyId()));
        memberIdField.setText(loan.getMemberId() == null ? "" : String.valueOf(loan.getMemberId()));
        phoneField.setText(loan.getPhoneNumber());
    }

    @FXML
    private void handleSave() {
        try {
            Loan loan = currentEntity == null ? new Loan() : currentEntity;
            LocalDateTime checkoutTime = FormParsers.parseDateTime(checkoutTimeField.getText(), "Checkout time", true);
            loan.setCheckoutTime(checkoutTime);
            loan.setDueDate(dueDatePicker.getValue() == null ? serviceLoan.calculateDueDate(checkoutTime) : dueDatePicker.getValue());
            loan.setReturnDate(FormParsers.parseDateTime(returnDateField.getText(), "Return date", false));
            loan.setStatus(statusCombo.getValue());
            loan.setRenewalCount(FormParsers.parseInteger(renewalCountField.getText(), "Renewal count"));
            loan.setNotes(notesArea.getText());
            loan.setBookCopyId(FormParsers.parseInteger(bookCopyIdField.getText(), "Book copy ID"));
            loan.setMemberId(FormParsers.parseInteger(memberIdField.getText(), "Member ID"));
            loan.setPhoneNumber(phoneField.getText());

            if (loan.getId() == null) {
                serviceLoan.ajouter(loan);
            } else {
                serviceLoan.modifier(loan);
            }
            notifySavedAndClose();
        } catch (IllegalArgumentException | SQLException exception) {
            showError(exception.getMessage());
        }
    }
}
