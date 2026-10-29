<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/audit.php';

$id = (int)($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';
$postedToken = $_POST['csrf_token'] ?? '';

if (empty($_SESSION['csrf_token']) || !is_string($postedToken) || !hash_equals($_SESSION['csrf_token'], $postedToken)
    || $id <= 0 || !in_array($action, ['archive', 'restore'], true)) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid archive request.'];
    header('Location: dashboard.php');
    exit;
}

$archivedAt = $action === 'archive' ? date('Y-m-d H:i:s') : null;
$stmt = $pdo->prepare("UPDATE patients SET archived_at = ? WHERE id = ?");
$stmt->execute([$archivedAt, $id]);
audit_log($pdo, $action === 'archive' ? 'archive_patient' : 'restore_patient', 'patient', $id, ucfirst($action) . 'd patient record');

$_SESSION['flash'] = [
    'type' => 'success',
    'message' => $action === 'archive' ? 'Patient record archived.' : 'Patient record restored.',
];
header('Location: ' . ($action === 'archive' ? 'dashboard.php' : 'old_records.php'));
exit;
