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
    resp_contact VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Rescuer table
CREATE TABLE IF NOT EXISTS rescuer (
    resc_id INT PRIMARY KEY AUTO_INCREMENT,
    resc_name VARCHAR(100) NOT NULL,
    resc_email VARCHAR(100) UNIQUE NOT NULL,
    resc_password VARCHAR(255) NOT NULL,
    resc_contact VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Device table
CREATE TABLE IF NOT EXISTS device (
    dev_id INT PRIMARY KEY AUTO_INCREMENT,
    dev_serial VARCHAR(50) UNIQUE NOT NULL,
    dev_status ENUM('available', 'assigned', 'maintenance', 'inactive') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Device Log table
CREATE TABLE IF NOT EXISTS device_log (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    dev_id INT NOT NULL,
    resp_id INT,
    mgmt_id INT,
    date_assigned TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_returned TIMESTAMP NULL,
    verified_return TINYINT(1) DEFAULT 0,
    FOREIGN KEY (dev_id) REFERENCES device(dev_id),
    FOREIGN KEY (resp_id) REFERENCES responder(resp_id),
    FOREIGN KEY (mgmt_id) REFERENCES management(mgmt_id)
);

-- Patient table
CREATE TABLE IF NOT EXISTS patient (
    pat_id INT PRIMARY KEY AUTO_INCREMENT,
    pat_name VARCHAR(100) NOT NULL,
    birthdate DATE,
    contact_number VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Incident table
CREATE TABLE IF NOT EXISTS incident (
    incident_id INT PRIMARY KEY AUTO_INCREMENT,
    log_id INT,
    pat_id INT NOT NULL,
    resp_id INT,
    resc_id INT,
    status ENUM('active', 'pending', 'transferred', 'completed', 'resolved') DEFAULT 'active',
    start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_time TIMESTAMP NULL,
    FOREIGN KEY (log_id) REFERENCES device_log(log_id),
    FOREIGN KEY (pat_id) REFERENCES patient(pat_id),
    FOREIGN KEY (resp_id) REFERENCES responder(resp_id),
    FOREIGN KEY (resc_id) REFERENCES rescuer(resc_id)
);

-- Vital Statistics table
CREATE TABLE IF NOT EXISTS vitalstat (
    vital_id INT PRIMARY KEY AUTO_INCREMENT,
    incident_id INT NOT NULL,
    recorded_by VARCHAR(50),
    bp_systolic INT,
    bp_diastolic INT,
    heart_rate INT,
    oxygen_level INT,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (incident_id) REFERENCES incident(incident_id)
);

-- Activity Log table
CREATE TABLE IF NOT EXISTS activity_log (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_name VARCHAR(100),
    user_role VARCHAR(50),
    action_type VARCHAR(50),
    module VARCHAR(50),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

