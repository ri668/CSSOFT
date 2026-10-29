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

$consultation = trim($_POST['consultation'] ?? '');
$tests = trim($_POST['tests'] ?? '');
$requestedBy = trim($_POST['requested_by'] ?? '');

if ($patientId <= 0 || $consultation === '' || $tests === '' || !preg_match('/^.{1,100}$/us', $requestedBy)) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Enter the consultation, requested tests, and clinician name.'];
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

$stmt = $pdo->prepare(
    "INSERT INTO lab_requests (patient_id, consultation, tests, requested_by) VALUES (?, ?, ?, ?)"
);
$stmt->execute([$patientId, $consultation, $tests, $requestedBy]);
$requestId = (int)$pdo->lastInsertId();
audit_log($pdo, 'create_lab_request', 'lab_request', $requestId, 'Created laboratory request');

$_SESSION['flash'] = ['type' => 'success', 'message' => 'Laboratory request saved. Review it before printing.'];
header('Location: lab_request.php?id=' . $requestId);
exit;