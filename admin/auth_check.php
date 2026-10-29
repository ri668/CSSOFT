<?php
require_once __DIR__ . '/../includes/session.php';

if (isset($_SESSION['admin_id'])) {
    $_SESSION['staff_account_id'] = (int)$_SESSION['admin_id'];
    $_SESSION['staff_name'] = $_SESSION['admin_username'];
    $_SESSION['staff_role'] = 'Admin';
    $_SESSION['is_admin'] = true;
    return;
}

if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config/database.php';
    $staffStmt = $pdo->prepare("SELECT id, username, full_name, role, status FROM users WHERE id = ?");
    $staffStmt->execute([(int)$_SESSION['user_id']]);
    $staff = $staffStmt->fetch();

    if ($staff && $staff['status'] === 'Active' && $staff['role'] === 'Doctor') {
        $accountStmt = $pdo->query("SELECT id FROM admins ORDER BY id LIMIT 1");
        $accountId = $accountStmt->fetchColumn();
        if ($accountId !== false) {
            $_SESSION['staff_account_id'] = (int)$accountId;
            $_SESSION['staff_name'] = $staff['full_name'];
            $_SESSION['staff_role'] = 'Doctor';
            $_SESSION['is_admin'] = false;
            return;
        }
    }
}

if (!isset($_SESSION['staff_account_id'])) {
    header('Location: ../login.php');
    exit;
}
