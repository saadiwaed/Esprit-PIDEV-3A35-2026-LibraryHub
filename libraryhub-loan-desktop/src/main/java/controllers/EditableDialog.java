package controllers;

import javafx.stage.Stage;

public interface EditableDialog<T> {
    void setDialogStage(Stage dialogStage);

    void setOnSave(Runnable onSave);

    void setEntity(T entity);
}
