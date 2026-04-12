package controllers;

import javafx.fxml.FXML;
import javafx.scene.control.Alert;
import javafx.scene.control.ButtonType;
import javafx.stage.Stage;

public abstract class AbstractDialogController<T> implements EditableDialog<T> {
    protected Stage dialogStage;
    protected Runnable onSave;
    protected T currentEntity;

    @Override
    public void setDialogStage(Stage dialogStage) {
        this.dialogStage = dialogStage;
    }

    @Override
    public void setOnSave(Runnable onSave) {
        this.onSave = onSave;
    }

    @Override
    public void setEntity(T entity) {
        this.currentEntity = entity;
        populateForm(entity);
    }

    protected abstract void populateForm(T entity);

    protected void notifySavedAndClose() {
        if (onSave != null) {
            onSave.run();
        }
        closeDialog();
    }

    protected void closeDialog() {
        if (dialogStage != null) {
            dialogStage.close();
        }
    }

    protected void showError(String message) {
        Alert alert = new Alert(Alert.AlertType.ERROR, message, ButtonType.OK);
        alert.showAndWait();
    }

    @FXML
    protected void handleCancel() {
        closeDialog();
    }
}
