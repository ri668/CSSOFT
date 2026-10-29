<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/audit.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);
    audit_log($pdo, 'delete_user', 'user', $id, 'Deleted user account');
    $_SESSION['flash'] = ['type' => 'success', 'message' => 'User deleted successfully.'];
}

header('Location: users.php');
exit;
