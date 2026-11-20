-- Local development and tests only. Each account is limited to its own database.
CREATE DATABASE IF NOT EXISTS userdb_dev CHARACTER SET utf8 COLLATE utf8_czech_ci;
CREATE DATABASE IF NOT EXISTS userdb_test CHARACTER SET utf8 COLLATE utf8_czech_ci;
CREATE USER IF NOT EXISTS 'userdb_dev'@'localhost' IDENTIFIED BY 'dev-only';
CREATE USER IF NOT EXISTS 'userdb_test'@'localhost' IDENTIFIED BY 'test-only';
GRANT ALL PRIVILEGES ON userdb_dev.* TO 'userdb_dev'@'localhost';
GRANT ALL PRIVILEGES ON userdb_test.* TO 'userdb_test'@'localhost';
