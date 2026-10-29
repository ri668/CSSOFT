<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/audit.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: users.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: users.php');
    exit;
}

$page_title = 'Edit User';
$errors = [];
$canAssignRoles = in_array($_SESSION['staff_role'] ?? '', ['Admin', 'Doctor'], true);
$linkStmt = $pdo->prepare("SELECT patient_id FROM patient_accounts WHERE user_id = ?");
$linkStmt->execute([$id]);
$linkedPatientId = $linkStmt->fetchColumn();
$linkedPatientId = $linkedPatientId === false ? '' : (string)$linkedPatientId;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$patientsStmt = $pdo->prepare("
    SELECT p.id, p.full_name, p.date_of_birth
    FROM patients p
    LEFT JOIN patient_accounts pa ON pa.patient_id = p.id
    WHERE p.archived_at IS NULL AND (pa.user_id IS NULL OR pa.user_id = ?)
    ORDER BY p.full_name
");
$patientsStmt->execute([$id]);
$availablePatients = $patientsStmt->fetchAll();

$form = [
    'full_name' => $user['full_name'],
    'email' => $user['email'],
    'username' => $user['username'],
    'password' => '',
    'role' => $user['role'],
    'status' => $user['status'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $key => $default) {
        $form[$key] = trim($_POST[$key] ?? '');
    }
    if (!$canAssignRoles) {
        $form['role'] = $user['role'];
    }
    if ($canAssignRoles && !in_array($form['role'], ['User', 'Doctor', 'Admin'], true)) {
        $errors[] = 'Select a valid account role.';
    }

    if ($form['full_name'] === '') {
        $errors[] = 'Full name is required.';
    }

    if ($form['email'] === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$form['email'], $id]);
        if ($stmt->fetch()) {
            $errors[] = 'This email is already registered.';
        }
    }

    if ($form['username'] === '') {
        $errors[] = 'Username is required.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$form['username'], $id]);
        if ($stmt->fetch()) {
            $errors[] = 'This username is already taken.';
        }
    }

    if ($form['password'] !== '' && strlen($form['password']) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }

    $postedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($postedToken) || !hash_equals($_SESSION['csrf_token'], $postedToken)) {
        $errors[] = 'The request expired. Reload the page and try again.';
    }

    $requestedPatientId = trim($_POST['patient_id'] ?? '');
    if ($requestedPatientId !== '') {
        if (!ctype_digit($requestedPatientId) || $form['role'] !== 'User') {
            $errors[] = 'Only a User account can be linked to a patient record.';
        } else {
            $patientCheck = $pdo->prepare("SELECT id FROM patients WHERE id = ?");
            $patientCheck->execute([(int)$requestedPatientId]);
            if (!$patientCheck->fetch()) {
                $errors[] = 'Select an existing patient record.';
            } else {
                $ownerCheck = $pdo->prepare("SELECT user_id FROM patient_accounts WHERE patient_id = ?");
                $ownerCheck->execute([(int)$requestedPatientId]);
                $ownerId = $ownerCheck->fetchColumn();
                if ($ownerId !== false && (int)$ownerId !== $id) {
                    $errors[] = 'That patient record is already linked to another account.';
                }
            }
        }
    }

    if (empty($errors)) {
        $sql = "UPDATE users SET full_name = ?, email = ?, username = ?, role = ?, status = ?";
        $params = [
            $form['full_name'],
            $form['email'],
            $form['username'],
            $form['role'],
            $form['status'],
        ];

        if ($form['password'] !== '') {
            $sql .= ", password = ?";
            $params[] = password_hash($form['password'], PASSWORD_BCRYPT);
        }

        $sql .= " WHERE id = ?";
        $params[] = $id;

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            if ($requestedPatientId === '') {
                $unlinkStmt = $pdo->prepare("DELETE FROM patient_accounts WHERE user_id = ?");
                $unlinkStmt->execute([$id]);
            } else {
                $linkStmt = $pdo->prepare("
                    INSERT INTO patient_accounts (user_id, patient_id) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE patient_id = VALUES(patient_id)
                ");
                $linkStmt->execute([$id, (int)$requestedPatientId]);
            }
            $pdo->commit();
            audit_log($pdo, 'update_user', 'user', $id, 'Updated user account and role');
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'User updated successfully.'];
        header('Location: users.php');
        exit;
    }
}

include __DIR__ . '/includes/header.php';
?>

<h3 class="mb-3">Edit User</h3>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card p-4">
    <form method="POST" action="user_edit.php?id=<?= (int)$id ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
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
                <label class="form-label">New Password</label>
                <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current password">
            </div>
            <div class="col-md-2">
                <label class="form-label">Role</label>
                <?php if (!$canAssignRoles): ?><input type="hidden" name="role" value="<?= htmlspecialchars($user['role']) ?>"><?php endif; ?>
                <select name="role" class="form-select" <?= $canAssignRoles ? '' : 'disabled' ?>>
                    <option value="User" <?= $form['role'] === 'User' ? 'selected' : '' ?>>User</option>
                    <?php if ($canAssignRoles): ?>
                        <option value="Admin" <?= $form['role'] === 'Admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="Doctor" <?= $form['role'] === 'Doctor' ? 'selected' : '' ?>>Doctor</option>
                    <?php elseif ($form['role'] !== 'User'): ?>
                        <option value="<?= htmlspecialchars($form['role']) ?>" selected><?= htmlspecialchars($form['role']) ?></option>
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
            <div class="col-12">
                <label class="form-label" for="patient_id">Linked patient record</label>
                <select id="patient_id" name="patient_id" class="form-select">
                    <option value="">No patient record linked</option>
                    <?php foreach ($availablePatients as $patient): ?>
                        <option value="<?= (int)$patient['id'] ?>" <?= (string)$patient['id'] === (string)($_POST['patient_id'] ?? $linkedPatientId) ? 'selected' : '' ?>>
                            #<?= (int)$patient['id'] ?> · <?= htmlspecialchars($patient['full_name']) ?> · <?= htmlspecialchars($patient['date_of_birth']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Only this account will be able to see the linked patient's records and appointments.</div>
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-success">Update User</button>
            <a href="users.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
