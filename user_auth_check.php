<?php
require_once __DIR__ . '/includes/session.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/config/database.php';
$stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
$stmt->execute([(int)$_SESSION['user_id']]);
if ($stmt->fetchColumn() !== 'Active') {
    unset($_SESSION['user_id'], $_SESSION['user_username'], $_SESSION['user_email']);
    $_SESSION['flash_message'] = 'Your account is inactive. Please contact the clinic.';
    header('Location: login.php');
    exit;
}

$roleStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$roleStmt->execute([(int)$_SESSION['user_id']]);
if ($roleStmt->fetchColumn() === 'Doctor') {
    header('Location: admin/dashboard.php');
    exit;
}
