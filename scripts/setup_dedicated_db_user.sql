-- Setup dedicated low-privilege MySQL user for ServerFlow & Aether
CREATE USER IF NOT EXISTS 'server_app'@'127.0.0.1' IDENTIFIED BY 'ServerAppSecurePass2026!';
CREATE USER IF NOT EXISTS 'server_app'@'localhost' IDENTIFIED BY 'ServerAppSecurePass2026!';

GRANT SELECT, INSERT, UPDATE, DELETE ON access_db.* TO 'server_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON access_db.* TO 'server_app'@'localhost';

GRANT SELECT, INSERT, UPDATE, DELETE ON ampache.* TO 'server_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON ampache.* TO 'server_app'@'localhost';

FLUSH PRIVILEGES;
