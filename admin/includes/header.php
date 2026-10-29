<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' - ' : '' ?>Health Records Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/medical.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark medical-navbar mb-4">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">🩺 CareStash Health Records</a>
        <?php if (isset($_SESSION['staff_account_id'])): ?>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="dashboard.php" class="btn btn-outline-light btn-sm">Patients</a>
            <a href="old_records.php" class="btn btn-outline-light btn-sm">Old records</a>
            <a href="calendar.php" class="btn btn-outline-light btn-sm">Calendar</a>
            <a href="document_verification.php" class="btn btn-outline-light btn-sm">Verify documents</a>
            <a href="activity_monitoring.php" class="btn btn-outline-light btn-sm">Activity</a>
            <?php if (($_SESSION['staff_role'] ?? '') === 'Doctor'): ?>
                <a href="../doctor_availability.php" class="btn btn-outline-light btn-sm">Availability</a>
            <?php endif; ?>
            <a href="users.php" class="btn btn-outline-light btn-sm">Users</a>
            <span class="text-white small me-1">Signed in as <?= htmlspecialchars($_SESSION['staff_name'] ?? '') ?> (<?= htmlspecialchars($_SESSION['staff_role'] ?? '') ?>)</span>
            <a href="logout.php" class="btn btn-light btn-sm">Sign out</a>
        </div>
        <?php endif; ?>
    </div>
</nav>

<div class="container pb-5">
