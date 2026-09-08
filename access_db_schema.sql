-- Scalable SQL Schema for access_db
CREATE DATABASE IF NOT EXISTS access_db;
USE access_db;

-- ==========================================
-- SYSTEM TABLES (Prefix: sys_)
-- ==========================================
CREATE TABLE IF NOT EXISTS sys_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'user',
    storage_limit_mb INT NOT NULL DEFAULT 100,
    storage_used_mb FLOAT DEFAULT 0.0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sys_deploy_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    project_name VARCHAR(100) NOT NULL,
    deploy_status VARCHAR(50) NOT NULL,
    deploy_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================
-- WEB API TABLES (Prefix: web_)
-- ==========================================
CREATE TABLE IF NOT EXISTS web_contact_forms (
    contact_id INT AUTO_INCREMENT PRIMARY KEY,
    project_name VARCHAR(100) NOT NULL,
    visitor_name VARCHAR(100) NOT NULL,
    visitor_email VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ==========================================
-- IOS API TABLES (Prefix: ios_)
-- ==========================================
CREATE TABLE IF NOT EXISTS ios_app_users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    app_name VARCHAR(100) NOT NULL,
    device_id VARCHAR(100) UNIQUE NOT NULL,
    username VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

