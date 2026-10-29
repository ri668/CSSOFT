CREATE DATABASE IF NOT EXISTS health_records_db;
USE health_records_db;

-- Admin users who can log into the admin panel
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO admins (username, password) VALUES
('admin', '$2y$10$TIZsd2qtB.J7uaxya0EcP.q1ueyH3NL0PSO1WYq1vfaeBLObkHRqy');

-- Registered application users managed by the admin panel
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('User','Admin','Doctor') NOT NULL DEFAULT 'User',
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT IGNORE INTO users (full_name, email, username, password, role, status)
VALUES
('Sample User', 'user@example.com', 'user', '$2y$10$fffibGCBhp3cyAlO0UM3IeuQZWzdgG.HadZi77A.j/dDtIhxyuNYi', 'User', 'Active');

-- Patient health records
CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('Male','Female','Other') NOT NULL,
    contact_number VARCHAR(20),
    address VARCHAR(255),
    blood_type VARCHAR(5),
    diagnosis TEXT,
    allergies TEXT,
    medications TEXT,
    doctor_name VARCHAR(100),
    admission_date DATE,
    notes TEXT,
    archived_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Previous patient record versions, captured before each edit
CREATE TABLE IF NOT EXISTS patient_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    snapshot LONGTEXT NOT NULL,
    changed_by VARCHAR(50) NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_patient_history_patient_changed (patient_id, changed_at),
    CONSTRAINT fk_patient_history_patient FOREIGN KEY (patient_id)
        REFERENCES patients(id) ON DELETE CASCADE
);

-- Clinician-created laboratory requests linked to a patient record
CREATE TABLE IF NOT EXISTS lab_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    consultation TEXT NOT NULL,
    tests TEXT NOT NULL,
    requested_by VARCHAR(100) NOT NULL,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Requested','Completed','Cancelled') NOT NULL DEFAULT 'Requested',
    INDEX idx_lab_requests_patient_requested (patient_id, requested_at),
    CONSTRAINT fk_lab_requests_patient FOREIGN KEY (patient_id)
        REFERENCES patients(id) ON DELETE CASCADE
);

-- Appointments belong to both a patient and the admin account calendar
CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    account_id INT NOT NULL,
    starts_at DATETIME NOT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    appointment_type ENUM('Face-to-face','Follow-up') NOT NULL DEFAULT 'Face-to-face',
    reason TEXT NOT NULL,
    status ENUM('Scheduled','Completed','Cancelled') NOT NULL DEFAULT 'Scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_appointments_account_start (account_id, starts_at),
    INDEX idx_appointments_patient_start (patient_id, starts_at),
    CONSTRAINT fk_appointments_patient FOREIGN KEY (patient_id)
        REFERENCES patients(id) ON DELETE CASCADE,
    CONSTRAINT fk_appointments_account FOREIGN KEY (account_id)
        REFERENCES admins(id) ON DELETE CASCADE
);

-- Clinician-authored, printable medical certificate drafts
CREATE TABLE IF NOT EXISTS medical_certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    account_id INT NOT NULL,
    examination_date DATE NOT NULL,
    purpose VARCHAR(150) NOT NULL,
    findings TEXT NOT NULL,
    recommendation TEXT NOT NULL,
    leave_from DATE NULL,
    leave_to DATE NULL,
    clinician_name VARCHAR(100) NOT NULL,
    license_number VARCHAR(50) NULL,
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_certificates_patient_issued (patient_id, issued_at),
    INDEX idx_certificates_account_issued (account_id, issued_at),
    CONSTRAINT fk_certificates_patient FOREIGN KEY (patient_id)
        REFERENCES patients(id) ON DELETE CASCADE,
    CONSTRAINT fk_certificates_account FOREIGN KEY (account_id)
        REFERENCES admins(id) ON DELETE CASCADE
);

-- A patient account can access only its explicitly linked patient record
CREATE TABLE IF NOT EXISTS patient_accounts (
    user_id INT PRIMARY KEY,
    patient_id INT NOT NULL UNIQUE,
    linked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_patient_accounts_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_patient_accounts_patient FOREIGN KEY (patient_id)
        REFERENCES patients(id) ON DELETE CASCADE
);

-- Public doctor status and availability maintained by Doctor-role accounts
CREATE TABLE IF NOT EXISTS doctor_availability (
    doctor_id INT PRIMARY KEY,
    status ENUM('At clinic','Available','Unavailable') NOT NULL DEFAULT 'Unavailable',
    clinic_location VARCHAR(150) NULL,
    weekly_schedule TEXT NULL,
    status_message VARCHAR(255) NULL,
    unavailable_from DATE NULL,
    unavailable_to DATE NULL,
    unavailable_note VARCHAR(255) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_doctor_availability_user FOREIGN KEY (doctor_id)
        REFERENCES users(id) ON DELETE CASCADE
);

-- Tamper-evident audit trail for authorized clinic staff activity
CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_id INT NULL,
    actor_name VARCHAR(100) NOT NULL,
    actor_role VARCHAR(30) NOT NULL,
    action VARCHAR(80) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NULL,
    details TEXT NULL,
    occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_occurred (occurred_at),
    INDEX idx_audit_actor (actor_name, actor_role),
    INDEX idx_audit_entity (entity_type, entity_id),
    INDEX idx_audit_action (action)
);

-- Registered document hashes. chain_hash links each registration to the prior entry.
CREATE TABLE IF NOT EXISTS blockchain_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NULL,
    document_name VARCHAR(150) NOT NULL,
    document_hash CHAR(64) NOT NULL,
    chain_hash CHAR(64) NOT NULL,
    registered_by VARCHAR(100) NOT NULL,
    registered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_document_hash (document_hash),
    INDEX idx_blockchain_patient (patient_id),
    CONSTRAINT fk_blockchain_patient FOREIGN KEY (patient_id)
        REFERENCES patients(id) ON DELETE SET NULL
);
