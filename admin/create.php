<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/audit.php';

$page_title = 'Add New Record';
$errors = [];

$form = [
    'full_name'      => '',
    'date_of_birth'  => '',
    'gender'         => '',
    'contact_number' => '',
    'address'        => '',
    'blood_type'     => '',
    'diagnosis'      => '',
    'allergies'      => '',
    'medications'    => '',
    'doctor_name'    => '',
    'admission_date' => '',
    'notes'          => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $key => $default) {
        $form[$key] = trim($_POST[$key] ?? '');
    }

    if ($form['full_name'] === '')     $errors[] = 'Full name is required.';
    if ($form['date_of_birth'] === '') $errors[] = 'Date of birth is required.';
    if ($form['gender'] === '')        $errors[] = 'Gender is required.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO patients
                (full_name, date_of_birth, gender, contact_number, address, blood_type,
                 diagnosis, allergies, medications, doctor_name, admission_date, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $form['full_name'],
            $form['date_of_birth'],
            $form['gender'],
            $form['contact_number'],
            $form['address'],
            $form['blood_type'],
            $form['diagnosis'],
            $form['allergies'],
            $form['medications'],
            $form['doctor_name'],
            $form['admission_date'] ?: null,
            $form['notes'],
        ]);
        audit_log($pdo, 'create_patient', 'patient', (int)$pdo->lastInsertId(), 'Created patient record');

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Patient record created successfully.'];
        header('Location: dashboard.php');
        exit;
    }
}

include __DIR__ . '/includes/header.php';
?>

<h3 class="mb-3">Add New Patient Record</h3>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card p-4">
    <form method="POST" action="create.php">
        <h2 class="h5 section-heading">Patient details</h2>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Full Name *</label>
                <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($form['full_name']) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Date of Birth *</label>
                <input type="date" name="date_of_birth" class="form-control" value="<?= htmlspecialchars($form['date_of_birth']) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Gender *</label>
                <select name="gender" class="form-select" required>
                    <option value="">-- Select --</option>
                    <?php foreach (['Male','Female','Other'] as $g): ?>
                        <option value="<?= $g ?>" <?= $form['gender'] === $g ? 'selected' : '' ?>><?= $g ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Contact Number</label>
                <input type="text" name="contact_number" class="form-control" value="<?= htmlspecialchars($form['contact_number']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Blood Type</label>
                <input type="text" name="blood_type" class="form-control" placeholder="e.g. O+" value="<?= htmlspecialchars($form['blood_type']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Admission Date</label>
                <input type="date" name="admission_date" class="form-control" value="<?= htmlspecialchars($form['admission_date']) ?>">
            </div>

            <div class="col-12">
                <label class="form-label">Address</label>
                <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($form['address']) ?>">
            </div>

            <div class="col-12 mt-4">
                <h2 class="h5 section-heading">Clinical information</h2>
                <p class="small text-muted">Add information confirmed by the patient or clinician. Leave fields blank if not known.</p>
            </div>
            <div class="col-md-6">
                <label class="form-label">Diagnosis</label>
                <textarea name="diagnosis" class="form-control" rows="2"><?= htmlspecialchars($form['diagnosis']) ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Allergies</label>
                <textarea name="allergies" class="form-control" rows="2"><?= htmlspecialchars($form['allergies']) ?></textarea>
            </div>

            <div class="col-md-6">
                <label class="form-label">Medications</label>
                <textarea name="medications" class="form-control" rows="2"><?= htmlspecialchars($form['medications']) ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Attending Doctor</label>
                <input type="text" name="doctor_name" class="form-control" value="<?= htmlspecialchars($form['doctor_name']) ?>">
            </div>

            <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($form['notes']) ?></textarea>
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-success">Save Record</button>
            <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
