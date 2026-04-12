package utils;

import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.SQLException;
import java.sql.Statement;

public final class MyDatabase {
    private static final String URL = System.getenv().getOrDefault(
            "LIBRARYHUB_DB_URL",
            "jdbc:mysql://127.0.0.1:3306/libraryhub?createDatabaseIfNotExist=true&useSSL=false&allowPublicKeyRetrieval=true&serverTimezone=UTC"
    );
    private static final String USER = System.getenv().getOrDefault("LIBRARYHUB_DB_USER", "root");
    private static final String PASSWORD = System.getenv().getOrDefault("LIBRARYHUB_DB_PASSWORD", "");

    private static MyDatabase instance;
    private Connection connection;

    private MyDatabase() {
        try {
            Class.forName("com.mysql.cj.jdbc.Driver");
            this.connection = DriverManager.getConnection(URL, USER, PASSWORD);
            initializeSchema();
        } catch (ClassNotFoundException | SQLException e) {
            throw new IllegalStateException("Unable to initialize MySQL connection for LibraryHub desktop module.", e);
        }
    }

    public static synchronized MyDatabase getInstance() {
        if (instance == null) {
            instance = new MyDatabase();
        }
        return instance;
    }

    public Connection getConnection() throws SQLException {
        if (connection == null || connection.isClosed()) {
            connection = DriverManager.getConnection(URL, USER, PASSWORD);
            initializeSchema();
        }
        return connection;
    }

    private void initializeSchema() throws SQLException {
        try (Statement statement = connection.createStatement()) {
            createReferenceTables(statement);
            createCoreTables(statement);
        }
    }

    private void createReferenceTables(Statement statement) throws SQLException {
        statement.execute("""
                CREATE TABLE IF NOT EXISTS category (
                    id_cat INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    description_cat LONGTEXT NULL,
                    icon VARCHAR(255) NULL
                )
                """);

        statement.execute("""
                CREATE TABLE IF NOT EXISTS author (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    firstname VARCHAR(255) NOT NULL,
                    lastname VARCHAR(255) NOT NULL,
                    biography LONGTEXT NULL,
                    photo VARCHAR(500) NULL,
                    nationality VARCHAR(100) NULL
                )
                """);

        statement.execute("""
                INSERT INTO category (id_cat, name, description_cat, icon)
                SELECT 1, 'General', 'Placeholder category used by the desktop loan module.', NULL
                WHERE NOT EXISTS (SELECT 1 FROM category WHERE id_cat = 1)
                """);

        statement.execute("""
                INSERT INTO author (id, firstname, lastname, biography, photo, nationality)
                SELECT 1, 'Unknown', 'Author', NULL, NULL, NULL
                WHERE NOT EXISTS (SELECT 1 FROM author WHERE id = 1)
                """);
    }

    private void createCoreTables(Statement statement) throws SQLException {
        statement.execute("""
                CREATE TABLE IF NOT EXISTS `user` (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    email VARCHAR(180) NOT NULL UNIQUE,
                    password VARCHAR(255) NOT NULL,
                    first_name VARCHAR(100) NOT NULL,
                    last_name VARCHAR(100) NOT NULL,
                    phone VARCHAR(20) NULL,
                    address VARCHAR(500) NULL,
                    avatar VARCHAR(255) NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
                    created_at DATETIME NOT NULL,
                    last_login_at DATETIME NULL,
                    email_verified_at DATETIME NULL
                )
                """);

        statement.execute("""
                CREATE TABLE IF NOT EXISTS book_copy (
                    id INT AUTO_INCREMENT PRIMARY KEY
                )
                """);

        statement.execute("""
                CREATE TABLE IF NOT EXISTS book (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(500) NOT NULL,
                    description LONGTEXT NULL,
                    publisher VARCHAR(255) NULL,
                    publication_year INT NULL,
                    page_count INT NULL,
                    language VARCHAR(50) NULL,
                    cover_image VARCHAR(500) NULL,
                    status VARCHAR(50) NOT NULL DEFAULT 'available',
                    created_at DATETIME NOT NULL,
                    category_id INT NOT NULL DEFAULT 1,
                    author_id INT NOT NULL DEFAULT 1,
                    CONSTRAINT fk_book_category_desktop FOREIGN KEY (category_id) REFERENCES category(id_cat),
                    CONSTRAINT fk_book_author_desktop FOREIGN KEY (author_id) REFERENCES author(id)
                )
                """);

        statement.execute("""
                CREATE TABLE IF NOT EXISTS loan (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    checkout_time DATETIME NOT NULL,
                    due_date DATE NOT NULL,
                    return_date DATETIME NULL,
                    status VARCHAR(20) NOT NULL,
                    renewal_count INT NOT NULL DEFAULT 0,
                    notes LONGTEXT NULL,
                    book_copy_id INT NOT NULL,
                    member_id INT NOT NULL,
                    phone_number VARCHAR(15) NULL,
                    last_email_reminder_sent_at DATETIME NULL,
                    last_sms_reminder_sent_at DATETIME NULL,
                    last_reminder_sent_at DATETIME NULL,
                    last_sms_sent_at DATETIME NULL,
                    penalty_last_notified_at DATETIME NULL,
                    INDEX idx_loan_book_copy (book_copy_id),
                    INDEX idx_loan_member (member_id),
                    CONSTRAINT fk_loan_book_copy_desktop FOREIGN KEY (book_copy_id) REFERENCES book_copy(id),
                    CONSTRAINT fk_loan_member_desktop FOREIGN KEY (member_id) REFERENCES `user`(id)
                )
                """);

        statement.execute("""
                CREATE TABLE IF NOT EXISTS penalty (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    amount DECIMAL(10,2) NOT NULL,
                    daily_rate DECIMAL(10,2) NOT NULL DEFAULT 0.50,
                    late_days INT NOT NULL DEFAULT 0,
                    reason VARCHAR(255) NOT NULL,
                    issue_date DATE NOT NULL,
                    notes LONGTEXT NULL,
                    waived TINYINT(1) NOT NULL DEFAULT 0,
                    status VARCHAR(20) NOT NULL,
                    loan_id INT NOT NULL,
                    INDEX idx_penalty_loan (loan_id),
                    CONSTRAINT fk_penalty_loan_desktop FOREIGN KEY (loan_id) REFERENCES loan(id) ON DELETE CASCADE
                )
                """);

        statement.execute("""
                CREATE TABLE IF NOT EXISTS renewal (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    previous_due_date DATE NOT NULL,
                    new_due_date DATE NOT NULL,
                    renewed_at DATETIME NOT NULL,
                    renewal_number INT NOT NULL,
                    loan_id INT NOT NULL,
                    INDEX idx_renewal_loan (loan_id),
                    CONSTRAINT fk_renewal_loan_desktop FOREIGN KEY (loan_id) REFERENCES loan(id) ON DELETE CASCADE
                )
                """);

        statement.execute("""
                CREATE TABLE IF NOT EXISTS loan_request (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    member_id INT NOT NULL,
                    book_id INT NOT NULL,
                    desired_loan_date DATE NOT NULL,
                    desired_return_date DATE NOT NULL,
                    requested_at DATETIME NOT NULL,
                    status VARCHAR(20) NOT NULL,
                    phone_number VARCHAR(15) NOT NULL,
                    notes LONGTEXT NULL,
                    last_email_reminder_sent_at DATETIME NULL,
                    last_sms_reminder_sent_at DATETIME NULL,
                    INDEX idx_loan_request_member (member_id),
                    CONSTRAINT fk_loan_request_member_desktop FOREIGN KEY (member_id) REFERENCES `user`(id) ON DELETE CASCADE
                )
                """);

        statement.execute("""
                CREATE TABLE IF NOT EXISTS renewal_request (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    loan_id INT NOT NULL,
                    member_id INT NOT NULL,
                    requested_at DATETIME NOT NULL,
                    status VARCHAR(20) NOT NULL,
                    notes LONGTEXT NULL,
                    last_email_reminder_sent_at DATETIME NULL,
                    last_sms_reminder_sent_at DATETIME NULL,
                    INDEX idx_renewal_request_loan (loan_id),
                    INDEX idx_renewal_request_member (member_id),
                    CONSTRAINT fk_renewal_request_loan_desktop FOREIGN KEY (loan_id) REFERENCES loan(id) ON DELETE CASCADE,
                    CONSTRAINT fk_renewal_request_member_desktop FOREIGN KEY (member_id) REFERENCES `user`(id) ON DELETE CASCADE
                )
                """);
    }
}
