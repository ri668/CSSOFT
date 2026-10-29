<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/audit.php';

$page_title = 'Activity Monitoring';
$filters = [
    'user' => trim($_GET['user'] ?? ''),
    'action' => trim($_GET['action'] ?? ''),
    'date_from' => trim($_GET['date_from'] ?? ''),
    'date_to' => trim($_GET['date_to'] ?? ''),
    'record' => trim($_GET['record'] ?? ''),
];
$where = [];
$params = [];
if ($filters['user'] !== '') { $where[] = '(actor_name LIKE ? OR actor_role = ?)'; $params[] = '%' . $filters['user'] . '%'; $params[] = $filters['user']; }
if ($filters['action'] !== '') { $where[] = 'action = ?'; $params[] = $filters['action']; }
if ($filters['date_from'] !== '') { $where[] = 'occurred_at >= ?'; $params[] = $filters['date_from'] . ' 00:00:00'; }
if ($filters['date_to'] !== '') { $where[] = 'occurred_at < DATE_ADD(?, INTERVAL 1 DAY)'; $params[] = $filters['date_to']; }
if ($filters['record'] !== '' && ctype_digit($filters['record'])) { $where[] = 'entity_id = ?'; $params[] = (int)$filters['record']; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("SELECT * FROM audit_logs $whereSql ORDER BY occurred_at DESC, id DESC LIMIT 500");
$stmt->execute($params);
$logs = $stmt->fetchAll();
$summaryByAction = [];
$summaryByUser = [];
foreach ($logs as $log) {
    $summaryByAction[$log['action']] = ($summaryByAction[$log['action']] ?? 0) + 1;
    $summaryByUser[$log['actor_name']] = ($summaryByUser[$log['actor_name']] ?? 0) + 1;
}
$actions = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
$isExport = ($_GET['export'] ?? '') === '1';

if ($isExport) {
    audit_log($pdo, 'export_audit_report', 'audit_log', null, 'Exported filtered audit report');
}

if ($isExport):
?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title>Audit Report</title><link href="../assets/css/medical.css" rel="stylesheet"><style>@media print {.no-print{display:none!important}} body{padding:2rem}.table{font-size:.85rem}</style></head><body>
<div class="d-flex justify-content-between align-items-center mb-4"><div><p class="text-success text-uppercase fw-semibold small mb-1">CareStash Health</p><h1>Audit report</h1><p>Generated <?= htmlspecialchars(gmdate('Y-m-d H:i:s')) ?> UTC · <?= count($logs) ?> activities</p></div><button class="btn btn-primary no-print" onclick="window.print()">Print</button></div>
<h2>Summary</h2><p><?= count($logs) ?> activities recorded.</p>
<div class="row"><div class="col-md-6"><h3>By action</h3><ul><?php foreach ($summaryByAction as $action => $count): ?><li><?= htmlspecialchars($action) ?>: <?= $count ?></li><?php endforeach; ?></ul></div><div class="col-md-6"><h3>By user</h3><ul><?php foreach ($summaryByUser as $user => $count): ?><li><?= htmlspecialchars($user) ?>: <?= $count ?></li><?php endforeach; ?></ul></div></div>
<table class="table table-bordered"><thead><tr><th>Timestamp</th><th>User</th><th>Role</th><th>Action</th><th>Record</th><th>Details</th></tr></thead><tbody>
<?php foreach ($logs as $log): ?><tr><td><?= htmlspecialchars($log['occurred_at']) ?></td><td><?= htmlspecialchars($log['actor_name']) ?></td><td><?= htmlspecialchars($log['actor_role']) ?></td><td><?= htmlspecialchars($log['action']) ?></td><td><?= htmlspecialchars($log['entity_type'] . ($log['entity_id'] ? ' #' . $log['entity_id'] : '')) ?></td><td><?= htmlspecialchars($log['details'] ?? '') ?></td></tr><?php endforeach; ?>
</tbody></table></body></html><?php exit; endif;

include __DIR__ . '/includes/header.php';
?>
<div class="page-heading d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div><p class="text-success fw-semibold text-uppercase small mb-1">Security and compliance</p><h1 class="h2 mb-1">Activity monitoring</h1><p class="text-muted mb-0">Review timestamped clinic staff activity and system events.</p></div>
    <a class="btn btn-outline-primary" href="activity_monitoring.php?<?= http_build_query(array_merge($filters, ['export' => '1'])) ?>">Generate / Export audit report</a>
</div>
<div class="card p-3 mb-3"><form method="GET" class="row g-2">
    <div class="col-md-3"><label class="form-label small fw-semibold">User or role</label><input name="user" class="form-control" value="<?= htmlspecialchars($filters['user']) ?>" placeholder="Name or Doctor"></div>
    <div class="col-md-2"><label class="form-label small fw-semibold">Action</label><select name="action" class="form-select"><option value="">All actions</option><?php foreach ($actions as $action): ?><option <?= $filters['action'] === $action ? 'selected' : '' ?>><?= htmlspecialchars($action) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><label class="form-label small fw-semibold">From</label><input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($filters['date_from']) ?>"></div>
    <div class="col-md-2"><label class="form-label small fw-semibold">To</label><input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($filters['date_to']) ?>"></div>
    <div class="col-md-2"><label class="form-label small fw-semibold">Record ID</label><input name="record" class="form-control" value="<?= htmlspecialchars($filters['record']) ?>"></div>
    <div class="col-auto align-self-end"><button class="btn btn-primary">Filter</button></div>
</form></div>
<div class="row g-3 mb-3"><div class="col-md-4"><div class="summary-tile"><span class="label">Activities shown</span><strong class="fs-3"><?= count($logs) ?></strong></div></div><div class="col-md-4"><div class="summary-tile"><span class="label">Actions</span><strong class="fs-3"><?= count($summaryByAction) ?></strong></div></div><div class="col-md-4"><div class="summary-tile"><span class="label">Users</span><strong class="fs-3"><?= count($summaryByUser) ?></strong></div></div></div>
<div class="card p-3"><div class="d-flex justify-content-between mb-3"><h2 class="h5 mb-0">Audit trail</h2><span class="small text-muted"><?= count($logs) ?> events shown</span></div>
<div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Timestamp (UTC)</th><th>User</th><th>Role</th><th>Action</th><th>Record</th><th>Details</th></tr></thead><tbody>
<?php foreach ($logs as $log): ?><tr><td><?= htmlspecialchars($log['occurred_at']) ?></td><td><?= htmlspecialchars($log['actor_name']) ?></td><td><?= htmlspecialchars($log['actor_role']) ?></td><td><span class="badge text-bg-light"><?= htmlspecialchars($log['action']) ?></span></td><td><?= htmlspecialchars($log['entity_type'] . ($log['entity_id'] ? ' #' . $log['entity_id'] : '')) ?></td><td><?= htmlspecialchars($log['details'] ?? '') ?></td></tr><?php endforeach; if (!$logs): ?><tr><td colspan="6" class="text-center text-muted py-5">No activity matches the selected filters.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
