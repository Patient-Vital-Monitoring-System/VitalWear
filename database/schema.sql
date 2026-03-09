-- ============================================
-- VitalWear Database Schema
-- ============================================

-- Create database
CREATE DATABASE IF NOT EXISTS vitalwear;
USE vitalwear;

-- ============================================
-- TABLES
-- ============================================

-- Management table
CREATE TABLE IF NOT EXISTS management (
    mgmt_id INT PRIMARY KEY AUTO_INCREMENT,
    mgmt_name VARCHAR(100) NOT NULL,
    mgmt_email VARCHAR(100) UNIQUE NOT NULL,
    mgmt_password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Admin table
CREATE TABLE IF NOT EXISTS admin (
    admin_id INT PRIMARY KEY AUTO_INCREMENT,
    admin_name VARCHAR(100) NOT NULL,
    admin_email VARCHAR(100) UNIQUE NOT NULL,
    admin_password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Responder table
CREATE TABLE IF NOT EXISTS responder (
    resp_id INT PRIMARY KEY AUTO_INCREMENT,
    resp_name VARCHAR(100) NOT NULL,
    resp_email VARCHAR(100) UNIQUE NOT NULL,
    resp_password VARCHAR(255) NOT NULL,
    resp_contact VARCHAR(15) DEFAULT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Rescuer table
CREATE TABLE IF NOT EXISTS rescuer (
    resc_id INT PRIMARY KEY AUTO_INCREMENT,
    resc_name VARCHAR(100) NOT NULL,
    resc_email VARCHAR(100) UNIQUE NOT NULL,
    resc_password VARCHAR(255) NOT NULL,
    resc_contact VARCHAR(15) DEFAULT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Device table
CREATE TABLE IF NOT EXISTS device (
    dev_id INT PRIMARY KEY AUTO_INCREMENT,
    dev_serial VARCHAR(100) NOT NULL,
    dev_status ENUM('available','assigned','maintenance') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Device Log table
CREATE TABLE IF NOT EXISTS device_log (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    dev_id INT NOT NULL,
    resp_id INT NOT NULL,
    mgmt_id INT NOT NULL,
    date_assigned TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_returned TIMESTAMP NULL DEFAULT NULL,
    verified_return TINYINT(1) DEFAULT 0,
    FOREIGN KEY (dev_id) REFERENCES device(dev_id) ON UPDATE CASCADE,
    FOREIGN KEY (resp_id) REFERENCES responder(resp_id) ON UPDATE CASCADE,
    FOREIGN KEY (mgmt_id) REFERENCES management(mgmt_id) ON UPDATE CASCADE
);

-- Patient table
CREATE TABLE IF NOT EXISTS patient (
    pat_id INT PRIMARY KEY AUTO_INCREMENT,
    pat_name VARCHAR(100) NOT NULL,
    birthdate DATE NOT NULL,
    contact_number VARCHAR(15) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Incident table
CREATE TABLE IF NOT EXISTS incident (
    incident_id INT PRIMARY KEY AUTO_INCREMENT,
    log_id INT DEFAULT NULL,
    pat_id INT NOT NULL,
    resp_id INT NOT NULL,
    resc_id INT DEFAULT NULL,
    start_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    end_time TIMESTAMP NULL DEFAULT NULL,
    status ENUM('ongoing','transferred','completed') DEFAULT 'ongoing',
    FOREIGN KEY (log_id) REFERENCES device_log(log_id) ON UPDATE CASCADE,
    FOREIGN KEY (pat_id) REFERENCES patient(pat_id) ON UPDATE CASCADE,
    FOREIGN KEY (resp_id) REFERENCES responder(resp_id) ON UPDATE CASCADE,
    FOREIGN KEY (resc_id) REFERENCES rescuer(resc_id) ON DELETE SET NULL ON UPDATE CASCADE
);

-- Vital Statistics table
CREATE TABLE IF NOT EXISTS vitalstat (
    vital_id INT PRIMARY KEY AUTO_INCREMENT,
    incident_id INT NOT NULL,
    recorded_by ENUM('responder','rescuer') NOT NULL,
    bp_systolic INT DEFAULT NULL,
    bp_diastolic INT DEFAULT NULL,
    heart_rate INT DEFAULT NULL,
    oxygen_level INT DEFAULT NULL,
    recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (incident_id) REFERENCES incident(incident_id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- Activity Log table
CREATE TABLE IF NOT EXISTS activity_log (
    activity_id INT PRIMARY KEY AUTO_INCREMENT,
    user_name VARCHAR(100) DEFAULT NULL,
    user_role ENUM('admin','responder','rescuer','management') DEFAULT NULL,
    action_type VARCHAR(100) DEFAULT NULL,
    module VARCHAR(50) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Users table (unified view if needed)
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    name VARCHAR(255) DEFAULT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    last_login DATETIME DEFAULT NULL,
    source_table VARCHAR(50) DEFAULT NULL,
    source_id INT DEFAULT NULL
);

