-- Fix: "Host 'localhost' is not allowed to connect to this MariaDB/MySQL server"
-- Run after connecting with skip-grant-tables (see CONNECTION_FIX.md).

FLUSH PRIVILEGES;

CREATE USER IF NOT EXISTS 'root'@'localhost' IDENTIFIED BY '';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;

CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED BY '';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;

FLUSH PRIVILEGES;

CREATE DATABASE IF NOT EXISTS libreryhub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
