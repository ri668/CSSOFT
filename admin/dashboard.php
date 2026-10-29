<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$page_title = 'Dashboard';

$search = trim($_GET['search'] ?? '');

if ($search !== '') {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE archived_at IS NULL AND (full_name LIKE ? OR contact_number LIKE ? OR CAST(id AS CHAR) LIKE ?) ORDER BY created_at DESC");
    $like = '%' . $search . '%';
    $stmt->execute([$like, $like, $like]);
} else {
    $stmt = $pdo->query("SELECT * FROM patients WHERE archived_at IS NULL ORDER BY created_at DESC");
}
$patients = $stmt->fetchAll();
$patientCount = (int)$pdo->query("SELECT COUNT(*) FROM patients WHERE archived_at IS NULL")->fetchColumn();
$archivedCount = (int)$pdo->query("SELECT COUNT(*) FROM patients WHERE archived_at IS NOT NULL")->fetchColumn();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

include __DIR__ . '/includes/header.php';
?>

<div class="page-heading d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <p class="text-success fw-semibold text-uppercase small mb-1">Clinic workspace</p>
        <h1 class="h2 mb-1">Patient records</h1>
        <p class="text-muted mb-0">A clear overview of your patients and their care records.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="old_records.php" class="btn btn-outline-secondary">View old records<?= $archivedCount ? ' (' . $archivedCount . ')' : '' ?></a>
        <a href="create.php" class="btn btn-success px-4">+ Add patient</a>
    </div>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-4">
        <div class="summary-tile">
            <span class="label">Patients in your records</span>
            <strong class="fs-3"><?= $patientCount ?></strong>
            <div class="small text-muted">Use search to quickly find a patient.</div>
        </div>
    </div>
</div>

<div class="card p-3 mb-3">
    <form method="GET" action="dashboard.php" class="row g-2">
        <div class="col-sm-8 col-md-6">
            <label for="patient-search" class="form-label small fw-semibold mb-1">Find a patient</label>
            <input id="patient-search" type="search" name="search" class="form-control" placeholder="Name, phone number, or record number" value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-auto align-self-end">
            <button type="submit" class="btn btn-primary px-4">Search</button>
        </div>
        <?php if ($search !== ''): ?>
        <div class="col-auto align-self-end">
            <a href="dashboard.php" class="btn btn-outline-secondary">Clear search</a>
        </div>
        <?php endif; ?>
    </form>
</div>

<div class="card p-3">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
        <h2 class="h5 mb-0">Patient directory</h2>
        <span class="small text-muted"><?= count($patients) ?> shown<?= $search !== '' ? ' for ' . htmlspecialchars($search) : '' ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Full Name</th>
                    <th>DOB</th>
                    <th>Gender</th>
                    <th>Contact</th>
                    <th>Blood Type</th>
                    <th>Doctor</th>
                    <th>Admission Date</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($patients) === 0): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5">
                            <p class="text-muted mb-3"><?= $search !== '' ? 'No patients matched your search. Try a different name or record number.' : 'No patient records yet. Add a patient to get started.' ?></p>
                            <?php if ($search === ''): ?><a href="create.php" class="btn btn-primary">Add first patient</a><?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($patients as $p): ?>
                    <tr>
                        <td><?= $p['id'] ?></td>
                        <td><?= htmlspecialchars($p['full_name']) ?></td>
                        <td><?= htmlspecialchars($p['date_of_birth']) ?></td>
                        <td><?= htmlspecialchars($p['gender']) ?></td>
                        <td><?= htmlspecialchars($p['contact_number']) ?></td>
                        <td><?= htmlspecialchars($p['blood_type']) ?></td>
                        <td><?= htmlspecialchars($p['doctor_name']) ?></td>
                        <td><?= htmlspecialchars($p['admission_date']) ?></td>
                        <td class="text-end">
                            <a href="view.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary">View record</a>
                            <a href="edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                            <form method="POST" action="archive.php" class="d-inline" onsubmit="return confirm('Archive the record for <?= htmlspecialchars(addslashes($p['full_name'])) ?>? It will remain available in old records.');">
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <input type="hidden" name="action" value="archive">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-warning">Archive</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
