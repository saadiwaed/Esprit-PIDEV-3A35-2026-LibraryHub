package controllers;

import javafx.collections.FXCollections;
import javafx.fxml.FXML;
import javafx.scene.control.ComboBox;
import javafx.scene.control.TextArea;
import javafx.scene.control.TextField;
import model.Member;
import model.MemberStatus;
import services.ServiceMember;

import java.sql.SQLException;
import java.time.LocalDateTime;

public class AjouterMember extends AbstractDialogController<Member> {
    @FXML
    private TextField emailField;
    @FXML
    private TextField firstNameField;
    @FXML
    private TextField lastNameField;
    @FXML
    private TextField phoneField;
    @FXML
    private TextArea addressArea;
    @FXML
    private ComboBox<MemberStatus> statusCombo;

    private final ServiceMember serviceMember = new ServiceMember();

    @FXML
    private void initialize() {
        statusCombo.setItems(FXCollections.observableArrayList(MemberStatus.values()));
        statusCombo.setValue(MemberStatus.PENDING);
    }

    @Override
    protected void populateForm(Member member) {
        if (member == null) {
            return;
        }
        emailField.setText(member.getEmail());
        firstNameField.setText(member.getFirstName());
        lastNameField.setText(member.getLastName());
        phoneField.setText(member.getPhone());
        addressArea.setText(member.getAddress());
        statusCombo.setValue(member.getStatus());
    }

    @FXML
    private void handleSave() {
        try {
            Member member = currentEntity == null ? new Member() : currentEntity;
            member.setEmail(emailField.getText());
            member.setFirstName(firstNameField.getText());
            member.setLastName(lastNameField.getText());
            member.setPhone(phoneField.getText());
            member.setAddress(addressArea.getText());
            member.setStatus(statusCombo.getValue());
            if (member.getCreatedAt() == null) {
                member.setCreatedAt(LocalDateTime.now());
            }

            if (member.getId() == null) {
                serviceMember.ajouter(member);
            } else {
                serviceMember.modifier(member);
            }
            notifySavedAndClose();
        } catch (IllegalArgumentException | SQLException exception) {
            showError(exception.getMessage());
        }
    }
}
