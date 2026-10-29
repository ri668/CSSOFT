<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$requestedMonth = $_GET['month'] ?? date('Y-m');
$month = preg_match('/^\\d{4}-\\d{2}$/', $requestedMonth)
    ? DateTimeImmutable::createFromFormat('!Y-m', $requestedMonth)
    : false;
if (!$month || $month->format('Y-m') !== $requestedMonth) {
    $month = new DateTimeImmutable('first day of this month');
}

$monthStart = $month->format('Y-m-01 00:00:00');
$nextMonth = $month->modify('+1 month');
$monthEnd = $nextMonth->format('Y-m-01 00:00:00');
$stmt = $pdo->prepare("
    SELECT a.*, p.full_name
    FROM appointments a
    INNER JOIN patients p ON p.id = a.patient_id
    WHERE a.account_id = ? AND a.starts_at >= ? AND a.starts_at < ?
    ORDER BY a.starts_at ASC
");
$stmt->execute([(int)$_SESSION['staff_account_id'], $monthStart, $monthEnd]);
$appointments = $stmt->fetchAll();
$byDay = [];
foreach ($appointments as $appointment) {
    $byDay[substr($appointment['starts_at'], 0, 10)][] = $appointment;
}

$firstDay = $month->modify('first day of this month');
$daysInMonth = (int)$firstDay->format('t');
$leadingCells = (int)$firstDay->format('w');
$cellCount = (int)(ceil(($leadingCells + $daysInMonth) / 7) * 7);
$page_title = 'My Calendar';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h3 class="mb-0">My Calendar</h3>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="calendar.php?month=<?= $month->modify('-1 month')->format('Y-m') ?>" aria-label="Previous month">&larr;</a>
        <span class="btn btn-light border" aria-live="polite"><?= htmlspecialchars($month->format('F Y')) ?></span>
        <a class="btn btn-outline-secondary" href="calendar.php?month=<?= $month->modify('+1 month')->format('Y-m') ?>" aria-label="Next month">&rarr;</a>
    </div>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div>
<?php endif; ?>

<div class="table-responsive mb-4">
    <table class="table table-bordered bg-white align-top">
        <thead><tr><?php foreach (['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $weekday): ?><th><?= $weekday ?></th><?php endforeach; ?></tr></thead>
        <tbody>
            <?php for ($week = 0; $week < $cellCount / 7; $week++): ?>
                <tr>
                    <?php for ($weekday = 0; $weekday < 7; $weekday++):
                        $dayNumber = $week * 7 + $weekday - $leadingCells + 1;
                        $inMonth = $dayNumber >= 1 && $dayNumber <= $daysInMonth;
                        $dayKey = $inMonth ? $firstDay->setDate((int)$month->format('Y'), (int)$month->format('m'), $dayNumber)->format('Y-m-d') : '';
                    ?>
                        <td style="min-width: 125px; height: 112px;" class="<?= $inMonth ? '' : 'bg-light' ?>">
                            <?php if ($inMonth): ?>
                                <div class="fw-semibold mb-1"><?= $dayNumber ?></div>
                                <?php foreach ($byDay[$dayKey] ?? [] as $appointment): ?>
                                    <a class="d-block small text-decoration-none border-start border-3 border-primary ps-1 mb-1" href="view.php?id=<?= (int)$appointment['patient_id'] ?>" title="<?= htmlspecialchars($appointment['reason']) ?>">
                                        <?= htmlspecialchars(date('g:i A', strtotime($appointment['starts_at']))) ?>
                                        <?= htmlspecialchars($appointment['full_name']) ?>
                                        <?php if ($appointment['status'] !== 'Scheduled'): ?><span class="text-muted">(<?= htmlspecialchars($appointment['status']) ?>)</span><?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                    <?php endfor; ?>
                </tr>
            <?php endfor; ?>
        </tbody>
    </table>
</div>

<h4>Appointments this month</h4>
<?php if ($appointments): ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>Date and time</th><th>Patient</th><th>Type</th><th>Duration</th><th>Status</th><th>Reason</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($appointments as $appointment): ?>
                    <tr>
                        <td><?= htmlspecialchars(date('M j, Y g:i A', strtotime($appointment['starts_at']))) ?></td>
                        <td><a href="view.php?id=<?= (int)$appointment['patient_id'] ?>"><?= htmlspecialchars($appointment['full_name']) ?></a></td>
                        <td><?= htmlspecialchars($appointment['appointment_type']) ?></td>
                        <td><?= (int)$appointment['duration_minutes'] ?> min</td>
                        <td><?= htmlspecialchars($appointment['status']) ?></td>
                        <td><?= nl2br(htmlspecialchars($appointment['reason'])) ?></td>
                        <td>
                            <?php if ($appointment['status'] === 'Scheduled'): ?>
                                <form method="POST" action="appointment_status.php" class="d-flex gap-1">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="appointment_id" value="<?= (int)$appointment['id'] ?>">
                                    <button class="btn btn-sm btn-outline-success" name="status" value="Completed" type="submit">Complete</button>
                                    <button class="btn btn-sm btn-outline-danger" name="status" value="Cancelled" type="submit">Cancel</button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">No actions</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <p class="text-muted">No appointments in this month.</p>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>