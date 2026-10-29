<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$page_title = 'Old Patient Records';
$search = trim($_GET['search'] ?? '');

if ($search !== '') {
    $stmt = $pdo->prepare("
        SELECT * FROM patients
        WHERE archived_at IS NOT NULL
          AND (full_name LIKE ? OR contact_number LIKE ? OR CAST(id AS CHAR) LIKE ?)
        ORDER BY archived_at DESC
    ");
    $like = '%' . $search . '%';
    $stmt->execute([$like, $like, $like]);
} else {
    $stmt = $pdo->query("SELECT * FROM patients WHERE archived_at IS NOT NULL ORDER BY archived_at DESC");
}
$patients = $stmt->fetchAll();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

include __DIR__ . '/includes/header.php';
?>

<div class="page-heading d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <p class="text-secondary fw-semibold text-uppercase small mb-1">Archive</p>
        <h1 class="h2 mb-1">Old patient records</h1>
        <p class="text-muted mb-0">Archived records are kept for reference and can be restored at any time.</p>
    </div>
    <a href="dashboard.php" class="btn btn-outline-secondary">Back to active records</a>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div>
<?php endif; ?>

<div class="card p-3 mb-3">
    <form method="GET" action="old_records.php" class="row g-2">
        <div class="col-sm-8 col-md-6">
            <label for="old-record-search" class="form-label small fw-semibold mb-1">Find an old record</label>
            <input id="old-record-search" type="search" name="search" class="form-control" placeholder="Name, phone number, or record number" value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-auto align-self-end"><button type="submit" class="btn btn-primary px-4">Search</button></div>
        <?php if ($search !== ''): ?>
            <div class="col-auto align-self-end"><a href="old_records.php" class="btn btn-outline-secondary">Clear search</a></div>
        <?php endif; ?>
    </form>
</div>

<div class="card p-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h5 mb-0">Archived records</h2>
        <span class="small text-muted"><?= count($patients) ?> shown</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>#</th><th>Full Name</th><th>DOB</th><th>Doctor</th><th>Archived</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
                <?php if (!$patients): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5"><?= $search !== '' ? 'No old records matched your search.' : 'No archived patient records.' ?></td></tr>
                <?php else: foreach ($patients as $p): ?>
                    <tr>
                        <td><?= (int)$p['id'] ?></td>
                        <td><?= htmlspecialchars($p['full_name']) ?></td>
                        <td><?= htmlspecialchars($p['date_of_birth']) ?></td>
                        <td><?= htmlspecialchars($p['doctor_name']) ?></td>
                        <td><?= htmlspecialchars($p['archived_at']) ?></td>
                        <td class="text-end">
                            <a href="view.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-primary">View record</a>
                            <form method="POST" action="archive.php" class="d-inline">
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <input type="hidden" name="action" value="restore">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-success">Restore</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
