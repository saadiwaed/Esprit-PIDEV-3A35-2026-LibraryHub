package tests;

import services.ServiceBook;
import services.ServiceLoan;
import services.ServiceMember;
import utils.MyDatabase;

public class Main {
    public static void main(String[] args) {
        try {
            MyDatabase.getInstance().getConnection();
            System.out.println("Database connection initialized.");
            System.out.println("Members: " + new ServiceMember().afficher().size());
            System.out.println("Books: " + new ServiceBook().afficher().size());
            System.out.println("Loans: " + new ServiceLoan().afficher().size());
        } catch (Exception exception) {
            exception.printStackTrace();
        }
    }
}
