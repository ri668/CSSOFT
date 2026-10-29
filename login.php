<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/config/database.php';

if (isset($_SESSION['admin_id'])) {
    header('Location: admin/dashboard.php');
    exit;
}

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identifier === '' || $password === '') {
        $error = 'Please enter your username/email and password.';
    } else {
        $adminStmt = $pdo->prepare("SELECT id, username, password FROM admins WHERE username = ? LIMIT 1");
        $adminStmt->execute([$identifier]);
        $admin = $adminStmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            unset($_SESSION['user_id'], $_SESSION['user_username'], $_SESSION['user_email']);
            $_SESSION['admin_id'] = (int)$admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            header('Location: admin/dashboard.php');
            exit;
        }

        $stmt = $pdo->prepare("SELECT id, username, email, password, role, status FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password']) && $user['status'] === 'Active') {
            unset($_SESSION['admin_id'], $_SESSION['admin_username']);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_username'] = $user['username'];
            $_SESSION['user_email'] = $user['email'];
            header('Location: ' . ($user['role'] === 'Doctor' ? 'admin/dashboard.php' : 'dashboard.php'));
            exit;
        }

        $error = 'Invalid username/email or password, or the account is inactive.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in - CareStash Health</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/medical.css" rel="stylesheet">
</head>
<body class="medical-login-page">
    <main class="medical-login-card">
        <div class="text-center">
            <p class="text-success fw-semibold text-uppercase small mb-1">CareStash Health</p>
            <h1 class="h3 mb-2">Welcome back</h1>
            <p class="text-muted mb-4">Sign in to access your health records, clinic tools, or administration workspace.</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success py-2"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="mb-3">
                <label class="form-label" for="identifier">Username or email</label>
                <input id="identifier" type="text" name="identifier" class="form-control" autocomplete="username" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Password</label>
                <input id="password" type="password" name="password" class="form-control" autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2">Sign in</button>
        </form>

        <div class="text-center mt-3">
            <p class="mb-2">New to CareStash? <a href="register.php">Create an account</a></p>
            <p class="small text-muted mb-0">Admin and Doctor accounts use this same sign-in.</p>
        </div>
    </main>
</body>
</html>
