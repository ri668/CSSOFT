<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: calendar.php');
    exit;
}

$sessionToken = $_SESSION['csrf_token'] ?? '';
$postedToken = $_POST['csrf_token'] ?? '';
if (!is_string($postedToken) || $sessionToken === '' || !hash_equals($sessionToken, $postedToken)) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'The request expired. Please try again.'];
    header('Location: calendar.php');
    exit;
}

$appointmentId = (int)($_POST['appointment_id'] ?? 0);
$status = $_POST['status'] ?? '';
if ($appointmentId <= 0 || !in_array($status, ['Completed', 'Cancelled'], true)) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid appointment update.'];
    header('Location: calendar.php');
    exit;
}

$stmt = $pdo->prepare("
    UPDATE appointments SET status = ?
    WHERE id = ? AND account_id = ? AND status = 'Scheduled'
");
$stmt->execute([$status, $appointmentId, (int)$_SESSION['staff_account_id']]);
audit_log($pdo, 'update_appointment_status', 'appointment', $appointmentId, 'Changed appointment status to ' . $status);
$_SESSION['flash'] = $stmt->rowCount()
    ? ['type' => 'success', 'message' => 'Appointment marked ' . strtolower($status) . '.']
    : ['type' => 'warning', 'message' => 'That appointment could not be updated.'];

header('Location: calendar.php');
exit;