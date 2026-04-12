package utils;

import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.SQLException;

public class MyConnection {
    private String url = "jdbc:mysql://localhost:3306/libraryhub";
    private String login = "root";
    private String pwd = "";
    private Connection cnx;
    public static MyConnection instance;

    private MyConnection() {
        try {
            this.cnx = DriverManager.getConnection(this.url, this.login, this.pwd);
            System.out.println("Connexion etablie!");
        } catch (SQLException e) {
            System.out.println(e.getMessage());
        }
    }

    public Connection getCnx() {
        return this.cnx;
    }

    public static MyConnection getInstance() {
        if (instance == null) {
            instance = new MyConnection();
        }

        return instance;
    }
}
