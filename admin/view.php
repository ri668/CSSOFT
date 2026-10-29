<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/audit.php';

$page_title = 'View Record';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->execute([$id]);
$p = $stmt->fetch();

if (!$p) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Record not found.'];
    header('Location: dashboard.php');
    exit;
}
audit_log($pdo, 'view_patient', 'patient', $id, 'Viewed patient record');

$historyStmt = $pdo->prepare("SELECT * FROM patient_history WHERE patient_id = ? ORDER BY changed_at DESC, id DESC");
$historyStmt->execute([$id]);
$historyRows = $historyStmt->fetchAll();

$labStmt = $pdo->prepare("SELECT * FROM lab_requests WHERE patient_id = ? ORDER BY requested_at DESC, id DESC");
$labStmt->execute([$id]);
$labRequests = $labStmt->fetchAll();
$accountId = (int)$_SESSION['staff_account_id'];

$appointmentStmt = $pdo->prepare("SELECT * FROM appointments WHERE patient_id = ? AND account_id = ? ORDER BY starts_at DESC");
$appointmentStmt->execute([$id, $accountId]);
$appointments = $appointmentStmt->fetchAll();

$certificateStmt = $pdo->prepare("SELECT id, examination_date, purpose, issued_at FROM medical_certificates WHERE patient_id = ? AND account_id = ? ORDER BY issued_at DESC");
$certificateStmt->execute([$id, $accountId]);
$certificates = $certificateStmt->fetchAll();
$isArchived = $p['archived_at'] !== null;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$consultation = trim(implode("\n", array_filter([$p['diagnosis'], $p['notes']])));
$appointmentReason = trim(implode(' - ', array_filter([$p['diagnosis'], $p['notes']])));
$requestedBy = trim($p['doctor_name'] ?: ($_SESSION['staff_name'] ?? ''));
$minimumAppointmentDate = (new DateTime())->format('Y-m-d\\TH:i');
$nameParts = preg_split('/\s+/', trim($p['full_name']));
$initials = strtoupper(substr($nameParts[0] ?? '', 0, 1) . substr($nameParts[count($nameParts) - 1] ?? '', 0, 1));
$age = (new DateTimeImmutable($p['date_of_birth']))->diff(new DateTimeImmutable('today'))->y;

function row($label, $value) {
    echo '<div class="col-md-6"><div class="record-field"><span class="label">' . htmlspecialchars($label) . '</span>';
    echo '<div>' . (trim((string)$value) !== '' ? nl2br(htmlspecialchars($value)) : '<span class="text-muted">&mdash;</span>') . '</div></div></div>';
}

include __DIR__ . '/includes/header.php';
?>

<?php if (!empty($_SESSION['flash'])): $flash = $_SESSION['flash']; unset($_SESSION['flash']); ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div>
<?php endif; ?>

<div class="profile-banner mb-4">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <span class="profile-avatar" aria-hidden="true"><?= htmlspecialchars($initials) ?></span>
            <div>
                <div class="small text-success fw-semibold text-uppercase">Patient profile · Record #<?= (int)$p['id'] ?></div>
                <h1 class="h3 mb-1"><?= htmlspecialchars($p['full_name']) ?></h1>
                <div class="text-muted"><?= htmlspecialchars($p['gender']) ?> · <?= $age ?> years old<?= $p['blood_type'] ? ' · Blood type ' . htmlspecialchars($p['blood_type']) : '' ?></div>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php if (!$isArchived): ?><a href="edit.php?id=<?= (int)$p['id'] ?>" class="btn btn-primary">Edit patient</a><?php endif; ?>
            <?php if ($isArchived): ?>
                <form method="POST" action="archive.php" class="d-inline"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="action" value="restore"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"><button type="submit" class="btn btn-success">Restore patient</button></form>
            <?php endif; ?>
            <a href="dashboard.php" class="btn btn-outline-secondary">Back to patients</a>
        </div>
    </div>
    <?php if ($isArchived): ?><div class="alert alert-warning mt-3 mb-0">This patient record is archived and is read-only until restored.</div><?php endif; ?>
</div>

<nav class="d-flex gap-2 flex-wrap mb-4" aria-label="Patient record sections">
    <a class="btn btn-sm btn-outline-primary" href="#personal-info">Profile</a>
    <a class="btn btn-sm btn-outline-primary" href="#clinical-summary">Clinical summary</a>
    <a class="btn btn-sm btn-outline-primary" href="#appointments">Appointments</a>
    <a class="btn btn-sm btn-outline-primary" href="#lab-requests">Lab requests</a>
    <a class="btn btn-sm btn-outline-primary" href="#certificates">Certificates</a>
    <a class="btn btn-sm btn-outline-secondary" href="#versions">Record history</a>
</nav>

<section id="personal-info" class="mb-4">
    <h2 class="h5 section-heading">Personal information</h2>
    <div class="card p-4">
    <div class="row">
        <?php
        row('Date of Birth', $p['date_of_birth']);
        row('Gender', $p['gender']);
        row('Contact Number', $p['contact_number']);
        row('Blood Type', $p['blood_type']);
        row('Admission Date', $p['admission_date']);
        row('Address', $p['address']);
        row('Attending Doctor', $p['doctor_name']);
        ?>
    </div>
    </div>
</section>

<section id="clinical-summary" class="mb-4">
    <h2 class="h5 section-heading">Current clinical information</h2>
    <div class="row g-3">
        <?php foreach ([
            'Diagnosis / consultation' => $p['diagnosis'],
            'Current medications' => $p['medications'],
            'Known allergies' => $p['allergies'],
            'Clinical notes' => $p['notes'],
        ] as $label => $value): ?>
            <div class="col-md-6">
                <article class="card h-100 p-3">
                    <h3 class="h6"><?= htmlspecialchars($label) ?></h3>
                    <div><?= trim((string)$value) !== '' ? nl2br(htmlspecialchars($value)) : '<span class="text-muted">Not recorded</span>' ?></div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="small text-muted mt-2 mb-0">Vitals are not currently captured as structured patient records.</p>
</section>

<section id="lab-requests" class="mt-4">
    <h2 class="h5 section-heading">Laboratory requests</h2>
    <div class="card p-4 mb-3">
        <h5 class="mb-3">Create Request</h5>
        <form method="POST" action="lab_request_create.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="patient_id" value="<?= (int)$p['id'] ?>">
            <div class="mb-3">
                <label class="form-label" for="consultation">Consultation / clinical indication</label>
                <textarea id="consultation" name="consultation" class="form-control" rows="3" required><?= htmlspecialchars($consultation) ?></textarea>
            </div>
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label" for="tests">Requested laboratory tests</label>
                    <textarea id="tests" name="tests" class="form-control" rows="3" placeholder="Enter one test per line" required></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="requested_by">Requesting clinician</label>
                    <input id="requested_by" name="requested_by" class="form-control" maxlength="100" value="<?= htmlspecialchars($requestedBy) ?>" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-3">Save and Prepare Printable Request</button>
        </form>
    </div>

    <?php if ($labRequests): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Requested</th><th>Clinician</th><th>Status</th><th class="text-end">Request</th></tr></thead>
                <tbody>
                    <?php foreach ($labRequests as $request): ?>
                        <tr>
                            <td><?= htmlspecialchars($request['requested_at']) ?></td>
                            <td><?= htmlspecialchars($request['requested_by']) ?></td>
                            <td><?= htmlspecialchars($request['status']) ?></td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="lab_request.php?id=<?= (int)$request['id'] ?>">View / Print</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted">No laboratory requests have been saved for this record.</p>
    <?php endif; ?>
</section>

<section id="appointments" class="mt-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h5 section-heading mb-0">Appointments</h2>
        <a class="btn btn-sm btn-outline-primary" href="calendar.php">Open My Calendar</a>
    </div>
    <div class="card p-4 mb-3">
        <h5 class="mb-3">Schedule consultation</h5>
        <form method="POST" action="appointment_create.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="patient_id" value="<?= (int)$p['id'] ?>">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="starts_at">Date and time</label>
                    <input id="starts_at" type="datetime-local" name="starts_at" class="form-control" min="<?= htmlspecialchars($minimumAppointmentDate) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="appointment_type">Appointment type</label>
                    <select id="appointment_type" name="appointment_type" class="form-select" required>
                        <option value="Face-to-face">Face-to-face consultation</option>
                        <option value="Follow-up">Follow-up</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="duration_minutes">Duration</label>
                    <select id="duration_minutes" name="duration_minutes" class="form-select">
                        <?php foreach ([15, 30, 45, 60, 90, 120] as $duration): ?>
                            <option value="<?= $duration ?>" <?= $duration === 30 ? 'selected' : '' ?>><?= $duration ?> min</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="appointment_reason">Consultation context</label>
                    <input id="appointment_reason" type="text" name="reason" class="form-control" maxlength="500" value="<?= htmlspecialchars($appointmentReason) ?>" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-3">Add to My Calendar</button>
        </form>
    </div>

    <?php if ($appointments): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Date and time</th><th>Type</th><th>Status</th><th>Duration</th></tr></thead>
                <tbody>
                    <?php foreach ($appointments as $appointment): ?>
                        <tr>
                            <td><?= htmlspecialchars(date('M j, Y g:i A', strtotime($appointment['starts_at']))) ?></td>
                            <td><?= htmlspecialchars($appointment['appointment_type']) ?></td>
                            <td><?= htmlspecialchars($appointment['status']) ?></td>
                            <td><?= (int)$appointment['duration_minutes'] ?> min</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted">No appointments have been scheduled by this account for this patient.</p>
    <?php endif; ?>
</section>

<section id="certificates" class="mt-4">
    <h2 class="h5 section-heading">Medical certificates</h2>
    <div class="card p-4 mb-3">
        <h5 class="mb-3">Generate certificate draft</h5>
        <form method="POST" action="medical_certificate_create.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="patient_id" value="<?= (int)$p['id'] ?>">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="examination_date">Examination date</label>
                    <input id="examination_date" type="date" name="examination_date" class="form-control" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="purpose">Purpose</label>
                    <input id="purpose" type="text" name="purpose" class="form-control" maxlength="150" placeholder="For example, school or employment" required>
                </div>
                <div class="col-12">
                    <label class="form-label" for="findings">Clinical findings</label>
                    <textarea id="findings" name="findings" class="form-control" rows="2" required><?= htmlspecialchars($p['diagnosis']) ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label" for="recommendation">Clinician recommendation</label>
                    <textarea id="recommendation" name="recommendation" class="form-control" rows="2" required></textarea>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="leave_from">Leave from (optional)</label>
                    <input id="leave_from" type="date" name="leave_from" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="leave_to">Leave through (optional)</label>
                    <input id="leave_to" type="date" name="leave_to" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="clinician_name">Issuing clinician</label>
                    <input id="clinician_name" type="text" name="clinician_name" class="form-control" maxlength="100" value="<?= htmlspecialchars($requestedBy) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="license_number">License number</label>
                    <input id="license_number" type="text" name="license_number" class="form-control" maxlength="50">
                </div>
            </div>
            <p class="small text-muted mt-3 mb-2">The generated document is a draft. The clinician must verify the content and sign it before use.</p>
            <button type="submit" class="btn btn-primary">Generate Draft</button>
        </form>
    </div>

    <?php if ($certificates): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Examined</th><th>Purpose</th><th>Generated</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($certificates as $certificate): ?>
                        <tr>
                            <td><?= htmlspecialchars($certificate['examination_date']) ?></td>
                            <td><?= htmlspecialchars($certificate['purpose']) ?></td>
                            <td><?= htmlspecialchars($certificate['issued_at']) ?></td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="medical_certificate.php?id=<?= (int)$certificate['id'] ?>">View / Print</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted">No certificates have been generated by this account for this patient.</p>
    <?php endif; ?>
</section>

<section id="versions" class="mt-4">
    <h2 class="h5 section-heading">Previous record versions</h2>
    <?php if ($historyRows): ?>
        <div class="record-timeline">
            <?php foreach ($historyRows as $version): $snapshot = json_decode($version['snapshot'], true) ?: []; ?>
                <details class="record-timeline-item card p-3 mb-3">
                    <summary class="fw-semibold">Saved <?= htmlspecialchars(date('M j, Y g:i A', strtotime($version['changed_at']))) ?> by <?= htmlspecialchars($version['changed_by']) ?></summary>
                    <div class="row pt-2">
                        <?php
                        foreach ([
                            'full_name' => 'Full Name', 'date_of_birth' => 'Date of Birth', 'gender' => 'Gender',
                            'contact_number' => 'Contact Number', 'blood_type' => 'Blood Type', 'admission_date' => 'Admission Date',
                            'address' => 'Address', 'doctor_name' => 'Attending Doctor', 'diagnosis' => 'Diagnosis',
                            'allergies' => 'Allergies', 'medications' => 'Medications', 'notes' => 'Notes',
                            'created_at' => 'Record Created', 'updated_at' => 'Last Updated',
                        ] as $key => $label) {
                            row($label, $snapshot[$key] ?? '');
                        }
                        ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="text-muted">No previous versions yet. Each saved edit will preserve the version it replaces.</p>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
