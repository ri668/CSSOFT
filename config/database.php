<?php
$host    = 'localhost';
$dbname  = 'health_records_db';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $db_user,
        $db_pass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Keep existing installations compatible with the patient archive feature.
    $columnStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'patients'
          AND COLUMN_NAME = 'archived_at'
    ");
    $columnStmt->execute();
    if ((int)$columnStmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE patients ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL AFTER notes");
    }

    $pdo->exec("
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
        )
    ");
    $pdo->exec("
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
        )
    ");
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
