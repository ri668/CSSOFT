<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$patientId = (int)($_POST['patient_id'] ?? 0);
$returnUrl = $patientId > 0 ? 'view.php?id=' . $patientId : 'dashboard.php';
$sessionToken = $_SESSION['csrf_token'] ?? '';
$postedToken = $_POST['csrf_token'] ?? '';

if (!is_string($postedToken) || $sessionToken === '' || !hash_equals($sessionToken, $postedToken)) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'The request expired. Please try again.'];
    header('Location: ' . $returnUrl);
    exit;
}

$examinationDate = trim($_POST['examination_date'] ?? '');
$purpose = trim($_POST['purpose'] ?? '');
$findings = trim($_POST['findings'] ?? '');
$recommendation = trim($_POST['recommendation'] ?? '');
$leaveFrom = trim($_POST['leave_from'] ?? '');
$leaveTo = trim($_POST['leave_to'] ?? '');
$clinicianName = trim($_POST['clinician_name'] ?? '');
$licenseNumber = trim($_POST['license_number'] ?? '');

$validDate = static function (string $value): bool {
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    $errors = DateTime::getLastErrors();
    return $date !== false
        && $date->format('Y-m-d') === $value
        && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
};

$hasLeaveDates = $leaveFrom !== '' || $leaveTo !== '';
$leaveDatesValid = !$hasLeaveDates
    || ($leaveFrom !== '' && $leaveTo !== '' && $validDate($leaveFrom) && $validDate($leaveTo) && $leaveFrom <= $leaveTo);

if (
    $patientId <= 0
    || !$validDate($examinationDate)
    || $examinationDate > date('Y-m-d')
    || $purpose === ''
    || strlen($purpose) > 150
    || $findings === ''
    || $recommendation === ''
    || !$leaveDatesValid
    || !preg_match('/^.{1,100}$/us', $clinicianName)
    || strlen($licenseNumber) > 50
) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Complete the certificate fields and check the dates and clinician details.'];
    header('Location: ' . $returnUrl);
    exit;
}

$patientStmt = $pdo->prepare("SELECT id FROM patients WHERE id = ? AND archived_at IS NULL");
$patientStmt->execute([$patientId]);
if (!$patientStmt->fetch()) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Patient record not found.'];
    header('Location: dashboard.php');
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO medical_certificates
        (patient_id, account_id, examination_date, purpose, findings, recommendation,
         leave_from, leave_to, clinician_name, license_number)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $patientId,
    (int)$_SESSION['staff_account_id'],
    $examinationDate,
    $purpose,
    $findings,
    $recommendation,
    $leaveFrom !== '' ? $leaveFrom : null,
    $leaveTo !== '' ? $leaveTo : null,
    $clinicianName,
    $licenseNumber !== '' ? $licenseNumber : null,
]);
$certificateId = (int)$pdo->lastInsertId();
audit_log($pdo, 'create_medical_certificate', 'medical_certificate', $certificateId, 'Created medical certificate');

header('Location: medical_certificate.php?id=' . (int)$pdo->lastInsertId());
exit;