<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/audit.php';

$page_title = 'Create User';
$errors = [];
$canAssignRoles = in_array($_SESSION['staff_role'] ?? '', ['Admin', 'Doctor'], true);

$form = [
    'full_name' => '',
    'email' => '',
    'username' => '',
    'password' => '',
    'role' => 'User',
    'status' => 'Active',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $key => $default) {
        $form[$key] = trim($_POST[$key] ?? '');
    }
    if (!$canAssignRoles) {
        $form['role'] = 'User';
    }

    if ($form['full_name'] === '') {
        $errors[] = 'Full name is required.';
    }

    if ($form['email'] === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$form['email']]);
        if ($stmt->fetch()) {
            $errors[] = 'This email is already registered.';
        }
    }

    if ($form['username'] === '') {
        $errors[] = 'Username is required.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$form['username']]);
        if ($stmt->fetch()) {
            $errors[] = 'This username is already taken.';
        }
    }

    if ($form['password'] === '') {
        $errors[] = 'Password is required.';
    } elseif (strlen($form['password']) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }

    if ($canAssignRoles && !in_array($form['role'], ['User', 'Doctor', 'Admin'], true)) {
        $errors[] = 'Select a valid account role.';
    }

    if ($form['status'] === '') {
        $errors[] = 'Status is required.';
    }

    if (empty($errors)) {
        $hashedPassword = password_hash($form['password'], PASSWORD_BCRYPT);

        $stmt = $pdo->prepare(
            "INSERT INTO users (full_name, email, username, password, role, status) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $form['full_name'],
            $form['email'],
            $form['username'],
            $hashedPassword,
            $form['role'],
            $form['status'],
        ]);
        audit_log($pdo, 'create_user', 'user', (int)$pdo->lastInsertId(), 'Created ' . $form['role'] . ' account');

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'User created successfully.'];
        header('Location: users.php');
        exit;
    }
}

include __DIR__ . '/includes/header.php';
?>

<h3 class="mb-3">Add New User</h3>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card p-4">
    <form method="POST" action="user_create.php">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Full Name *</label>
                <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($form['full_name']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email *</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($form['email']) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Username *</label>
                <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($form['username']) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Password *</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Role</label>
                <?php if (!$canAssignRoles): ?><input type="hidden" name="role" value="User"><?php endif; ?>
                <select name="role" class="form-select" <?= $canAssignRoles ? '' : 'disabled' ?>>
                    <option value="User" <?= $form['role'] === 'User' ? 'selected' : '' ?>>User</option>
                    <?php if ($canAssignRoles): ?>
                        <option value="Admin" <?= $form['role'] === 'Admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="Doctor" <?= $form['role'] === 'Doctor' ? 'selected' : '' ?>>Doctor</option>
                    <?php endif; ?>
                </select>
                <?php if (!$canAssignRoles): ?><div class="form-text">Only authorized staff can assign account roles.</div><?php endif; ?>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="Active" <?= $form['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
                    <option value="Inactive" <?= $form['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-success">Save User</button>
            <a href="users.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
