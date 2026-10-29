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

$startInput = trim($_POST['starts_at'] ?? '');
$start = DateTime::createFromFormat('Y-m-d\\TH:i', $startInput);
$dateErrors = DateTime::getLastErrors();
$validStart = $start
    && $start->format('Y-m-d\\TH:i') === $startInput
    && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0));
$duration = (int)($_POST['duration_minutes'] ?? 30);
$type = $_POST['appointment_type'] ?? '';
$reason = trim($_POST['reason'] ?? '');

if (
    $patientId <= 0
    || !$validStart
    || $start <= new DateTime()
    || !in_array($duration, [15, 30, 45, 60, 90, 120], true)
    || !in_array($type, ['Face-to-face', 'Follow-up'], true)
    || $reason === ''
) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Enter a future date and time, appointment type, duration, and reason.'];
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

$startValue = $start->format('Y-m-d H:i:s');
$endValue = (clone $start)->modify('+' . $duration . ' minutes')->format('Y-m-d H:i:s');
$conflictStmt = $pdo->prepare("
    SELECT id FROM appointments
    WHERE account_id = ? AND status = 'Scheduled'
      AND starts_at < ?
      AND DATE_ADD(starts_at, INTERVAL duration_minutes MINUTE) > ?
    LIMIT 1
");
$conflictStmt->execute([(int)$_SESSION['staff_account_id'], $endValue, $startValue]);
if ($conflictStmt->fetch()) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'This time overlaps another appointment on your calendar.'];
    header('Location: ' . $returnUrl);
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO appointments (patient_id, account_id, starts_at, duration_minutes, appointment_type, reason)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $patientId,
    (int)$_SESSION['staff_account_id'],
    $startValue,
    $duration,
    $type,
    $reason,
]);
$appointmentId = (int)$pdo->lastInsertId();
audit_log($pdo, 'create_appointment', 'appointment', $appointmentId, 'Created patient appointment');

$_SESSION['flash'] = ['type' => 'success', 'message' => 'Appointment added to your calendar.'];
header('Location: calendar.php?month=' . $start->format('Y-m'));
exit;