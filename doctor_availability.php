<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/config/database.php';

$userId = (int)$_SESSION['user_id'];
$userStmt = $pdo->prepare("SELECT id, full_name, role, status FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$user = $userStmt->fetch();

if (!$user || $user['status'] !== 'Active' || $user['role'] !== 'Doctor') {
    header('Location: dashboard.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$profileStmt = $pdo->prepare("SELECT * FROM doctor_availability WHERE doctor_id = ?");
$profileStmt->execute([$userId]);
$profile = $profileStmt->fetch() ?: [
    'status' => 'Unavailable',
    'clinic_location' => '',
    'weekly_schedule' => '',
    'status_message' => '',
    'unavailable_from' => '',
    'unavailable_to' => '',
    'unavailable_note' => '',
];
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($postedToken) || !hash_equals($_SESSION['csrf_token'], $postedToken)) {
        $errors[] = 'The request expired. Reload the page and try again.';
    }

    foreach (['status', 'clinic_location', 'weekly_schedule', 'status_message', 'unavailable_from', 'unavailable_to', 'unavailable_note'] as $field) {
        $profile[$field] = trim($_POST[$field] ?? '');
    }

    if (!in_array($profile['status'], ['At clinic', 'Available', 'Unavailable'], true)) {
        $errors[] = 'Choose a valid status.';
    }
    if (strlen($profile['clinic_location']) > 150) {
        $errors[] = 'Clinic location must be 150 characters or fewer.';
    }
    if (strlen($profile['weekly_schedule']) > 4000) {
        $errors[] = 'Regular hours are too long.';
    }
    if (strlen($profile['status_message']) > 255 || strlen($profile['unavailable_note']) > 255) {
        $errors[] = 'Public messages must be 255 characters or fewer.';
    }

    $validDate = static function (string $value): bool {
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        $dateErrors = DateTime::getLastErrors();
        return $date !== false
            && $date->format('Y-m-d') === $value
            && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0));
    };
    $from = $profile['unavailable_from'];
    $to = $profile['unavailable_to'];
    if (($from === '') !== ($to === '')) {
        $errors[] = 'Enter both dates for a planned absence or leave both blank.';
    } elseif ($from !== '' && (!$validDate($from) || !$validDate($to) || $from > $to)) {
        $errors[] = 'The planned absence dates are invalid.';
    }

    if (!$errors) {
        $saveStmt = $pdo->prepare("
            INSERT INTO doctor_availability
                (doctor_id, status, clinic_location, weekly_schedule, status_message,
                 unavailable_from, unavailable_to, unavailable_note)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                clinic_location = VALUES(clinic_location),
                weekly_schedule = VALUES(weekly_schedule),
                status_message = VALUES(status_message),
                unavailable_from = VALUES(unavailable_from),
                unavailable_to = VALUES(unavailable_to),
                unavailable_note = VALUES(unavailable_note)
        ");
        $saveStmt->execute([
            $userId,
            $profile['status'],
            $profile['clinic_location'] !== '' ? $profile['clinic_location'] : null,
            $profile['weekly_schedule'] !== '' ? $profile['weekly_schedule'] : null,
            $profile['status_message'] !== '' ? $profile['status_message'] : null,
            $from !== '' ? $from : null,
            $to !== '' ? $to : null,
            $profile['unavailable_note'] !== '' ? $profile['unavailable_note'] : null,
        ]);
        $success = 'Your public availability has been updated.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Availability - CareStash Health</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/medical.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark medical-navbar">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">CareStash Health</a>
            <div class="d-flex gap-2 align-items-center">
                <a href="dashboard.php#doctors" class="btn btn-outline-light btn-sm">Doctor directory</a>
                <a href="dashboard.php" class="btn btn-outline-light btn-sm">Dashboard</a>
                <a href="logout.php" class="btn btn-light btn-sm">Sign out</a>
            </div>
        </div>
    </nav>

    <main class="container py-4" style="max-width: 900px;">
        <div class="page-heading">
            <p class="text-success fw-semibold text-uppercase small mb-1">Doctor profile</p>
            <h1 class="h2 mb-2">Manage your availability</h1>
            <p class="text-muted mb-0">Patients can see the information you publish here. Keep personal or sensitive details out of public messages.</p>
        </div>

        <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($errors): ?>
            <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <div class="card p-3 p-md-4">
            <div class="card-body p-0">
                <form method="POST" action="doctor_availability.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="status" class="form-label">Current status</label>
                            <select id="status" name="status" class="form-select" required>
                                <?php foreach (['At clinic', 'Available', 'Unavailable'] as $status): ?>
                                    <option value="<?= htmlspecialchars($status) ?>" <?= $profile['status'] === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label for="clinic_location" class="form-label">Clinic / location</label>
                            <input id="clinic_location" name="clinic_location" class="form-control" maxlength="150" value="<?= htmlspecialchars($profile['clinic_location']) ?>" placeholder="Clinic name or public location">
                        </div>
                        <div class="col-12">
                            <label for="weekly_schedule" class="form-label">Regular clinic hours</label>
                            <textarea id="weekly_schedule" name="weekly_schedule" class="form-control" rows="3" maxlength="4000" placeholder="Mon-Wed: 9:00 AM-2:00 PM&#10;Friday: 1:00 PM-5:00 PM"><?= htmlspecialchars($profile['weekly_schedule']) ?></textarea>
                        </div>
                        <div class="col-12">
                            <label for="status_message" class="form-label">Public status message</label>
                            <input id="status_message" name="status_message" class="form-control" maxlength="255" value="<?= htmlspecialchars($profile['status_message']) ?>" placeholder="For example, accepting walk-ins until 3 PM">
                        </div>
                        <div class="col-md-6">
                            <label for="unavailable_from" class="form-label">Planned absence from</label>
                            <input id="unavailable_from" type="date" name="unavailable_from" class="form-control" value="<?= htmlspecialchars($profile['unavailable_from']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="unavailable_to" class="form-label">Planned absence through</label>
                            <input id="unavailable_to" type="date" name="unavailable_to" class="form-control" value="<?= htmlspecialchars($profile['unavailable_to']) ?>">
                        </div>
                        <div class="col-12">
                            <label for="unavailable_note" class="form-label">Public absence note</label>
                            <input id="unavailable_note" name="unavailable_note" class="form-control" maxlength="255" value="<?= htmlspecialchars($profile['unavailable_note']) ?>" placeholder="Optional; do not include private personal or medical details">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-4">Save Availability</button>
                </form>
            </div>
        </div>
    </main>
</body>
</html>