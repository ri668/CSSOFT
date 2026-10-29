<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $pdo->prepare("UPDATE patients SET archived_at = ? WHERE id = ?");
    $stmt->execute([date('Y-m-d H:i:s'), $id]);
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'Patient record archived.'];
} else {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Invalid record.'];
}

header('Location: dashboard.php');
exit;
