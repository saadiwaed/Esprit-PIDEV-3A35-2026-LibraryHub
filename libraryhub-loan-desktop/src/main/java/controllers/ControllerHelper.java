package controllers;

import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.scene.control.Alert;
import javafx.scene.control.ButtonType;
import javafx.scene.control.TextInputDialog;
import javafx.stage.Modality;
import javafx.stage.Stage;

import java.io.IOException;
import java.util.Optional;

public final class ControllerHelper {
    private ControllerHelper() {
    }

    public static void showInfo(String message) {
        new Alert(Alert.AlertType.INFORMATION, message, ButtonType.OK).showAndWait();
    }

    public static void showError(String message) {
        new Alert(Alert.AlertType.ERROR, message, ButtonType.OK).showAndWait();
    }

    public static boolean confirm(String message) {
        Alert alert = new Alert(Alert.AlertType.CONFIRMATION, message, ButtonType.YES, ButtonType.NO);
        Optional<ButtonType> result = alert.showAndWait();
        return result.isPresent() && result.get() == ButtonType.YES;
    }

    public static Optional<String> prompt(String title, String message, String defaultValue) {
        TextInputDialog dialog = new TextInputDialog(defaultValue);
        dialog.setTitle(title);
        dialog.setHeaderText(message);
        return dialog.showAndWait();
    }

    public static <T, C extends EditableDialog<T>> void openDialog(
            String fxmlFile,
            String title,
            T entity,
            Runnable onSave
    ) throws IOException {
        FXMLLoader loader = new FXMLLoader(ControllerHelper.class.getResource("/" + fxmlFile));
        Parent root = loader.load();
        C controller = loader.getController();

        Stage dialog = new Stage();
        dialog.setTitle(title);
        dialog.initModality(Modality.APPLICATION_MODAL);
        dialog.setScene(new Scene(root));

        controller.setDialogStage(dialog);
        controller.setOnSave(onSave);
        controller.setEntity(entity);

        dialog.showAndWait();
    }
}
