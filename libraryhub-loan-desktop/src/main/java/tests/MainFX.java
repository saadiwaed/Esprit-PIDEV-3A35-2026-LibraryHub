package tests;

import javafx.application.Application;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.scene.control.Tab;
import javafx.scene.control.TabPane;
import javafx.stage.Stage;

public class MainFX extends Application {
    @Override
    public void start(Stage stage) {
        TabPane tabPane = new TabPane(
                createTab("Members", "AfficherMember.fxml"),
                createTab("Books", "AfficherBook.fxml"),
                createTab("Copies", "AfficherBookCopy.fxml"),
                createTab("Loans", "AfficherLoan.fxml"),
                createTab("Penalties", "AfficherPenalty.fxml"),
                createTab("Renewals", "AfficherRenewal.fxml"),
                createTab("Loan Requests", "AfficherLoanRequest.fxml"),
                createTab("Renewal Requests", "AfficherRenewalRequest.fxml")
        );

        Scene scene = new Scene(tabPane, 1500, 900);
        stage.setTitle("LibraryHub Loan Desktop");
        stage.setScene(scene);
        stage.show();
    }

    private Tab createTab(String title, String fxmlFile) {
        try {
            Parent content = FXMLLoader.load(getClass().getResource("/" + fxmlFile));
            Tab tab = new Tab(title, content);
            tab.setClosable(false);
            return tab;
        } catch (Exception exception) {
            throw new IllegalStateException("Unable to load " + fxmlFile, exception);
        }
    }

    public static void main(String[] args) {
        launch(args);
    }
}
