-- ============================================
-- VitalWear Database Sample Data
-- ============================================

USE vitalwear;

-- ============================================
-- SAMPLE DATA
-- ============================================

-- Insert Management users
INSERT INTO management (mgmt_name, mgmt_email, mgmt_password) VALUES
('Operations Manager', 'ops@vitalwear.com', MD5('manager123')),
('Field Manager', 'field@vitalwear.com', MD5('manager123'));

-- Insert Admin users
INSERT INTO admin (admin_name, admin_email, admin_password) VALUES
('System Admin', 'admin1@vitalwear.com', MD5('admin123')),
('Audit Admin', 'admin2@vitalwear.com', MD5('admin123'));

-- Insert Responder users
INSERT INTO responder (resp_name, resp_email, resp_password, resp_contact) VALUES
('Juan Dela Cruz', 'juan@responder.com', MD5('resp123'), '09123456789'),
('Mark Villanueva', 'mark@responder.com', MD5('resp123'), '09112223344'),
('Leo Ramirez', 'leo@responder.com', MD5('resp123'), '09223334455'),
('Pedro Santos', 'pedro@responder.com', MD5('resp123'), '09334445566'),
('John Smith', 'john@responder.com', MD5('resp123'), '09445556677');

-- Insert Rescuer users
INSERT INTO rescuer (resc_name, resc_email, resc_password, resc_contact) VALUES
('Maria Santos', 'maria@rescuer.com', MD5('resc123'), '09987654321'),
('Ana Lopez', 'ana@rescuer.com', MD5('resc123'), '09887776655'),
('David Garcia', 'david@rescuer.com', MD5('resc123'), '09778889900'),
('Sarah Wilson', 'sarah@rescuer.com', MD5('resc123'), '09669990011'),
('Michael Brown', 'michael@rescuer.com', MD5('resc123'), '09550011223');

-- Insert Devices
INSERT INTO device (dev_serial, dev_status) VALUES
('DEV-001', 'assigned'),
('DEV-002', 'assigned'),
('DEV-003', 'assigned'),
('DEV-004', 'available'),
('DEV-005', 'available'),
('DEV-006', 'maintenance');

-- Insert Device Assignments
INSERT INTO device_log (dev_id, resp_id, mgmt_id) VALUES
(1, 1, 1),
(2, 2, 1),
(3, 3, 2);

-- Insert Patients
INSERT INTO patient (pat_name, birthdate, contact_number) VALUES
('Pedro Reyes', '1995-06-15', '09001112222'),
('Carlos Mendoza', '1988-02-10', '09110002233'),
('Ramon Torres', '1975-09-25', '09224445566'),
('Miguel Hernandez', '1990-12-01', '09335556677'),
('Antonio Garcia', '1982-07-18', '09446667788');

-- Insert Incidents
INSERT INTO incident (log_id, pat_id, resp_id, status, start_time) VALUES
(1, 1, 1, 'active', NOW() - INTERVAL 2 HOUR),
(2, 2, 2, 'pending', NOW() - INTERVAL 5 HOUR),
(3, 3, 3, 'transferred', NOW() - INTERVAL 1 DAY),
(1, 4, 1, 'completed', NOW() - INTERVAL 2 DAY),
(2, 5, 2, 'resolved', NOW() - INTERVAL 3 DAY);

-- Update incidents to transferred
UPDATE incident SET resc_id = 1, status = 'transferred' WHERE incident_id = 3;
UPDATE incident SET resc_id = 2 WHERE incident_id = 4;

-- Update incidents to completed
UPDATE incident SET status = 'completed', end_time = NOW() - INTERVAL 1 DAY WHERE incident_id = 4;
UPDATE incident SET status = 'resolved', end_time = NOW() - INTERVAL 2 DAY WHERE incident_id = 5;

-- Insert Vital Statistics
INSERT INTO vitalstat (incident_id, recorded_by, bp_systolic, bp_diastolic, heart_rate, oxygen_level, recorded_at) VALUES
(1, 'responder', 120, 80, 75, 98, NOW() - INTERVAL 1 HOUR),
(2, 'responder', 135, 85, 88, 96, NOW() - INTERVAL 4 HOUR),
(3, 'responder', 140, 90, 95, 94, NOW() - INTERVAL 12 HOUR),
(4, 'rescuer', 118, 78, 72, 99, NOW() - INTERVAL 1 DAY),
(5, 'rescuer', 130, 82, 85, 97, NOW() - INTERVAL 2 DAY),
(1, 'rescuer', 115, 75, 70, 99, NOW() - INTERVAL 30 MINUTE),
(3, 'rescuer', 125, 80, 78, 97, NOW() - INTERVAL 6 HOUR);

-- Insert Activity Logs
INSERT INTO activity_log (user_name, user_role, action_type, module, description) VALUES
('System', 'admin', 'create_user', 'management', 'Management accounts created'),
('System Admin', 'admin', 'create_user', 'admin', 'Admin accounts created'),
('System Admin', 'admin', 'create_user', 'responder', 'Responder accounts created'),
('System Admin', 'admin', 'create_user', 'rescuer', 'Rescuer accounts created'),
('Inventory System', 'admin', 'register_device', 'device', 'New monitoring devices registered'),
('Operations Manager', 'management', 'assign_device', 'device', 'Devices assigned to responders'),
('Responder', 'responder', 'register_patient', 'patient', 'Patient profiles created during incidents'),
('Responders', 'responder', 'create_incident', 'incident', 'New emergency incidents recorded'),
('Responder', 'responder', 'record_vitals', 'vital_monitoring', 'Patient vital signs recorded by responders'),
('Responder', 'responder', 'transfer_incident', 'incident', 'Incidents transferred to rescuers'),
('Rescuer', 'rescuer', 'update_vitals', 'vital_monitoring', 'Rescuers updated patient vital signs'),
('Rescuer', 'rescuer', 'complete_incident', 'incident', 'Emergency incidents successfully completed'),
('Operations Manager', 'management', 'return_device', 'device', 'Devices returned and verified');

-- Update device status after return
UPDATE device SET dev_status = 'available' WHERE dev_id IN (1, 2);

-- ============================================
-- QUICK LOGIN REFERENCE
-- ============================================
-- 
-- Admin:    admin1@vitalwear.com / admin123
-- Manager:  ops@vitalwear.com / manager123  
-- Responder: juan@responder.com / resp123
-- Rescuer:  maria@rescuer.com / resc123

