<?php
require_once __DIR__ . '/user_auth_check.php';
require_once __DIR__ . '/config/database.php';

$userId = (int)$_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id, full_name, username, email, role, status, created_at FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}
$nameParts = preg_split('/\s+/', trim($user['full_name']));
$initials = strtoupper(substr($nameParts[0] ?? '', 0, 1) . substr($nameParts[count($nameParts) - 1] ?? '', 0, 1));

$linkStmt = $pdo->prepare("
    SELECT p.* FROM patient_accounts pa
    INNER JOIN patients p ON p.id = pa.patient_id
    WHERE pa.user_id = ? AND p.archived_at IS NULL
");
$linkStmt->execute([$userId]);
$patient = $linkStmt->fetch();

$appointments = [];
$history = [];
$labRequests = [];
$certificates = [];
if ($patient) {
    $appointmentsStmt = $pdo->prepare("SELECT * FROM appointments WHERE patient_id = ? ORDER BY starts_at DESC");
    $appointmentsStmt->execute([(int)$patient['id']]);
    $appointments = $appointmentsStmt->fetchAll();

    $historyStmt = $pdo->prepare("SELECT * FROM patient_history WHERE patient_id = ? ORDER BY changed_at DESC, id DESC");
    $historyStmt->execute([(int)$patient['id']]);
    $history = $historyStmt->fetchAll();

    $labStmt = $pdo->prepare("SELECT * FROM lab_requests WHERE patient_id = ? ORDER BY requested_at DESC");
    $labStmt->execute([(int)$patient['id']]);
    $labRequests = $labStmt->fetchAll();

    $certificatesStmt = $pdo->prepare("SELECT * FROM medical_certificates WHERE patient_id = ? ORDER BY issued_at DESC");
    $certificatesStmt->execute([(int)$patient['id']]);
    $certificates = $certificatesStmt->fetchAll();
}

$doctorsStmt = $pdo->query("
    SELECT u.id, u.full_name, da.status, da.clinic_location, da.weekly_schedule,
           da.status_message, da.unavailable_from, da.unavailable_to, da.unavailable_note,
           da.updated_at
    FROM users u
    LEFT JOIN doctor_availability da ON da.doctor_id = u.id
    WHERE u.role = 'Doctor' AND u.status = 'Active'
    ORDER BY u.full_name
");
$doctors = $doctorsStmt->fetchAll();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Health Records - CareStash</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/medical.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark medical-navbar">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">CareStash Health</a>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="#my-records" class="btn btn-outline-light btn-sm">My records</a>
                <?php if ($patient): ?><a href="#appointments" class="btn btn-outline-light btn-sm">Appointments</a><?php endif; ?>
                <a href="#doctors" class="btn btn-outline-light btn-sm">Doctors</a>
                <?php if ($user['role'] === 'Doctor'): ?>
                    <a href="doctor_availability.php" class="btn btn-outline-light btn-sm">Availability</a>
                <?php endif; ?>
                <span class="text-white small"><?= htmlspecialchars($_SESSION['user_username']) ?></span>
                <a href="logout.php" class="btn btn-light btn-sm">Sign out</a>
            </div>
        </div>
    </nav>

    <main class="container py-4 py-lg-5">
        <div class="profile-banner mb-4">
            <div class="d-flex align-items-center gap-3">
                <span class="profile-avatar" aria-hidden="true"><?= htmlspecialchars($initials) ?></span>
                <div>
                    <p class="text-success text-uppercase fw-semibold small mb-1">Your care space</p>
                    <h1 class="h3 mb-1">Welcome, <?= htmlspecialchars($user['full_name']) ?></h1>
                    <p class="text-muted mb-0">Your health information and clinic updates in one place.</p>
                </div>
            </div>
        </div>
        <div class="row g-3 mb-5">
            <?php foreach ([
                'Username' => $user['username'],
                'Email' => $user['email'],
                'Account type' => $user['role'],
                'Account status' => $user['status'],
            ] as $label => $value): ?>
                <div class="col-6 col-lg-3">
                    <div class="summary-tile">
                        <span class="label"><?= htmlspecialchars($label) ?></span>
                        <strong><?= htmlspecialchars($value) ?></strong>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <section class="mb-5" id="my-records">
            <h2 class="h4 section-heading">My medical records</h2>
            <?php if (!$patient): ?>
                <div class="alert alert-info">Your account is not yet linked to a patient record. Please contact the clinic to verify and link your account.</div>
            <?php else: ?>
                <?php
                $patientNameParts = preg_split('/\s+/', trim($patient['full_name']));
                $patientInitials = strtoupper(substr($patientNameParts[0] ?? '', 0, 1) . substr($patientNameParts[count($patientNameParts) - 1] ?? '', 0, 1));
                ?>
                <div class="profile-banner mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <span class="profile-avatar" aria-hidden="true"><?= htmlspecialchars($patientInitials) ?></span>
                        <div>
                            <span class="badge rounded-pill text-bg-light mb-1">Patient record #<?= (int)$patient['id'] ?></span>
                            <h3 class="h4 mb-1"><?= htmlspecialchars($patient['full_name']) ?></h3>
                            <span class="text-muted"><?= htmlspecialchars($patient['gender']) ?> · Born <?= htmlspecialchars(date('M j, Y', strtotime($patient['date_of_birth']))) ?></span>
                        </div>
                    </div>
                </div>

                <article class="card p-3 p-md-4 mb-4">
                    <h3 class="h5 section-heading">Personal information</h3>
                    <div class="row g-3">
                            <?php foreach ([
                                'Date of birth' => date('M j, Y', strtotime($patient['date_of_birth'])), 'Gender' => $patient['gender'],
                                'Contact number' => $patient['contact_number'], 'Address' => $patient['address'],
                                'Blood type' => $patient['blood_type'], 'Attending doctor' => $patient['doctor_name'],
                            ] as $label => $value): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="record-field"><span class="label"><?= htmlspecialchars($label) ?></span>
                                    <div><?= $value !== null && trim((string)$value) !== '' ? nl2br(htmlspecialchars($value)) : '<span class="text-muted">Not recorded</span>' ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                    </div>
                </article>

                <section class="mb-4" aria-labelledby="recent-records-title">
                    <h3 id="recent-records-title" class="h5 section-heading">Current clinical information</h3>
                    <div class="row g-3">
                        <?php foreach ([
                            'Consultation / diagnosis' => $patient['diagnosis'],
                            'Medications' => $patient['medications'],
                            'Known allergies' => $patient['allergies'],
                            'Clinical notes' => $patient['notes'],
                        ] as $label => $value): ?>
                            <div class="col-md-6">
                                <article class="card h-100 p-3">
                                    <h4 class="h6"><?= htmlspecialchars($label) ?></h4>
                                    <div><?= $value !== null && trim((string)$value) !== '' ? nl2br(htmlspecialchars($value)) : '<span class="text-muted">Not recorded</span>' ?></div>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="small text-muted mt-2 mb-0">Vitals are not currently captured as structured patient records.</p>
                </section>

                <section id="appointments" class="mb-4">
                <h3 class="h5 section-heading">Appointments</h3>
                <?php if ($appointments): ?>
                    <div class="card p-3 table-responsive mb-4">
                        <table class="table table-hover align-middle bg-white">
                            <thead><tr><th>Date and time</th><th>Type</th><th>Doctor</th><th>Status</th><th>Details</th></tr></thead>
                            <tbody>
                                <?php foreach ($appointments as $appointment): ?>
                                    <tr>
                                        <td><?= htmlspecialchars(date('M j, Y g:i A', strtotime($appointment['starts_at']))) ?></td>
                                        <td><?= htmlspecialchars($appointment['appointment_type']) ?></td>
                                        <td><?= htmlspecialchars($patient['doctor_name'] ?: 'Clinic staff') ?></td>
                                        <td><?= htmlspecialchars($appointment['status']) ?></td>
                                        <td><?= nl2br(htmlspecialchars($appointment['reason'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="card p-3 text-muted">No appointments are currently linked to your record.</div>
                <?php endif; ?>
                </section>

                <?php if ($labRequests): ?>
                    <h3 class="h5 section-heading">Laboratory requests</h3>
                    <div class="card p-3 table-responsive mb-4">
                        <table class="table table-hover align-middle bg-white">
                            <thead><tr><th>Requested</th><th>Clinician</th><th>Status</th><th>Tests</th><th>Clinical indication</th></tr></thead>
                            <tbody>
                                <?php foreach ($labRequests as $request): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($request['requested_at']) ?></td>
                                        <td><?= htmlspecialchars($request['requested_by']) ?></td>
                                        <td><?= htmlspecialchars($request['status']) ?></td>
                                        <td><?= nl2br(htmlspecialchars($request['tests'])) ?></td>
                                        <td><?= nl2br(htmlspecialchars($request['consultation'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <h3 class="h5 section-heading">Laboratory requests</h3>
                    <div class="card p-3 text-muted mb-4">No laboratory requests are linked to your record.</div>
                <?php endif; ?>

                <?php if ($certificates): ?>
                    <h3 class="h5 section-heading">Medical certificates</h3>
                    <?php foreach ($certificates as $certificate): ?>
                        <article class="border rounded p-3 mb-3 bg-white">
                            <div class="d-flex justify-content-between flex-wrap gap-2">
                                <strong>Examination date: <?= htmlspecialchars($certificate['examination_date']) ?></strong>
                                <span class="text-muted">Issued <?= htmlspecialchars($certificate['issued_at']) ?></span>
                            </div>
                            <p class="mb-2 mt-2"><strong>Purpose:</strong> <?= htmlspecialchars($certificate['purpose']) ?></p>
                            <p class="mb-2"><strong>Clinical findings:</strong><br><?= nl2br(htmlspecialchars($certificate['findings'])) ?></p>
                            <p class="mb-2"><strong>Recommendation:</strong><br><?= nl2br(htmlspecialchars($certificate['recommendation'])) ?></p>
                            <?php if ($certificate['leave_from'] && $certificate['leave_to']): ?>
                                <p class="mb-2"><strong>Recommended leave:</strong> <?= htmlspecialchars($certificate['leave_from']) ?> to <?= htmlspecialchars($certificate['leave_to']) ?></p>
                            <?php endif; ?>
                            <p class="small text-muted mb-0">Clinician: <?= htmlspecialchars($certificate['clinician_name']) ?> · License number: <?= htmlspecialchars($certificate['license_number'] ?: 'Not provided') ?></p>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <h3 class="h5 section-heading">Medical certificates</h3>
                    <div class="card p-3 text-muted mb-4">No medical certificates are linked to your record.</div>
                <?php endif; ?>

                <?php if ($history): ?>
                    <h3 class="h5 section-heading">Record history</h3>
                    <div class="record-timeline mb-4">
                        <?php foreach ($history as $version): $snapshot = json_decode($version['snapshot'], true) ?: []; ?>
                            <details class="record-timeline-item card p-3 mb-3">
                                <summary class="fw-semibold"><?= htmlspecialchars(date('M j, Y g:i A', strtotime($version['changed_at']))) ?></summary>
                                <div class="row pt-3">
                                    <?php foreach (['diagnosis' => 'Diagnosis', 'allergies' => 'Allergies', 'medications' => 'Medications', 'notes' => 'Notes'] as $key => $label): ?>
                                        <div class="col-md-6 mb-2"><strong><?= htmlspecialchars($label) ?>:</strong> <?= nl2br(htmlspecialchars($snapshot[$key] ?? 'Not recorded')) ?></div>
                                    <?php endforeach; ?>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <h3 class="h5 section-heading">Record history</h3>
                    <div class="card p-3 text-muted mb-4">There are no previous saved versions of this record.</div>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <section class="mt-5 mb-4" id="doctors">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h4 section-heading mb-0">Doctor availability</h2>
                <?php if ($user['role'] === 'Doctor'): ?><a href="doctor_availability.php" class="btn btn-outline-primary btn-sm">Update my status</a><?php endif; ?>
            </div>
            <?php if ($doctors): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle bg-white">
                        <thead><tr><th>Doctor</th><th>Status</th><th>Clinic</th><th>Regular hours</th><th>Updates</th></tr></thead>
                        <tbody>
                            <?php foreach ($doctors as $doctor):
                                $today = date('Y-m-d');
                                $withinUnavailableDates = $doctor['unavailable_from'] && $doctor['unavailable_to']
                                    && $today >= $doctor['unavailable_from'] && $today <= $doctor['unavailable_to'];
                                $shownStatus = $withinUnavailableDates ? 'Unavailable' : ($doctor['status'] ?: 'Status not set');
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($doctor['full_name']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $shownStatus === 'At clinic' ? 'success' : ($shownStatus === 'Available' ? 'primary' : 'secondary') ?>"><?= htmlspecialchars($shownStatus) ?></span>
                                        <?php if ($doctor['status_message']): ?><div class="small mt-1"><?= htmlspecialchars($doctor['status_message']) ?></div><?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($doctor['clinic_location'] ?: 'Not provided') ?></td>
                                    <td><?= $doctor['weekly_schedule'] ? nl2br(htmlspecialchars($doctor['weekly_schedule'])) : '<span class="text-muted">Not posted</span>' ?></td>
                                    <td>
                                        <?php if ($doctor['unavailable_from'] && $doctor['unavailable_to']): ?>
                                            <?= htmlspecialchars($doctor['unavailable_from']) ?> to <?= htmlspecialchars($doctor['unavailable_to']) ?>
                                            <?php if ($doctor['unavailable_note']): ?><div class="small"><?= htmlspecialchars($doctor['unavailable_note']) ?></div><?php endif; ?>
                                        <?php else: ?>
                                            <?= htmlspecialchars($doctor['unavailable_note'] ?: 'No planned absence posted') ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-muted">No doctor availability has been posted yet.</p>
            <?php endif; ?>
            <p class="small text-muted">Availability is maintained by doctors and may change. Contact the clinic to confirm before visiting. Public updates should not include sensitive personal details.</p>
        </section>
    </main>
</body>
</html>
