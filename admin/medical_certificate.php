<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';

$certificateId = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT c.*, p.full_name, p.date_of_birth, p.gender
    FROM medical_certificates c
    INNER JOIN patients p ON p.id = c.patient_id
    WHERE c.id = ? AND c.account_id = ?
");
$stmt->execute([$certificateId, (int)$_SESSION['staff_account_id']]);
$certificate = $stmt->fetch();

if (!$certificate) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Medical certificate not found.'];
    header('Location: dashboard.php');
    exit;
}

$page_title = 'Medical Certificate Draft';
include __DIR__ . '/includes/header.php';
?>

<style>
    .certificate-sheet { max-width: 850px; margin: 0 auto; min-height: 720px; }
    .draft-notice { border: 1px solid #a65d00; color: #6a3b00; background: #fff4dc; padding: 10px 14px; }
    @media print {
        body { background: #fff !important; }
        .navbar, .print-actions { display: none !important; }
        .container { max-width: none !important; width: 100% !important; padding: 0 !important; }
        .certificate-sheet { max-width: none; min-height: auto; border: 0 !important; box-shadow: none !important; }
        .draft-notice { color: #000; background: #fff; }
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 print-actions">
    <a class="btn btn-outline-secondary" href="view.php?id=<?= (int)$certificate['patient_id'] ?>">Back to Patient</a>
    <button type="button" class="btn btn-primary" onclick="window.print()">Print Draft</button>
</div>

<article class="card certificate-sheet p-4 p-md-5">
    <div class="text-center border-bottom pb-3 mb-4">
        <h1 class="h2">Medical Certificate</h1>
        <p class="mb-0">Certificate #<?= (int)$certificate['id'] ?></p>
    </div>

    <div class="draft-notice mb-4"><strong>DRAFT:</strong> The issuing clinician must verify all details and sign this certificate before it is used.</div>

    <p>This is to certify that <strong><?= htmlspecialchars($certificate['full_name']) ?></strong>, born <?= htmlspecialchars($certificate['date_of_birth']) ?>, was examined on <?= htmlspecialchars($certificate['examination_date']) ?>.</p>

    <h2 class="h6 mt-4">Clinical findings</h2>
    <div><?= nl2br(htmlspecialchars($certificate['findings'])) ?></div>

    <h2 class="h6 mt-4">Recommendation</h2>
    <div><?= nl2br(htmlspecialchars($certificate['recommendation'])) ?></div>

    <?php if ($certificate['leave_from'] && $certificate['leave_to']): ?>
        <p class="mt-4"><strong>Recommended leave dates:</strong> <?= htmlspecialchars($certificate['leave_from']) ?> to <?= htmlspecialchars($certificate['leave_to']) ?></p>
    <?php endif; ?>

    <p class="mt-4">This certificate is issued upon request for <strong><?= htmlspecialchars($certificate['purpose']) ?></strong>.</p>

    <div class="row mt-5 pt-5">
        <div class="col-md-6 mb-4">
            <div class="border-top pt-2">Signature of issuing clinician</div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="border-top pt-2">
                <?= htmlspecialchars($certificate['clinician_name']) ?><br>
                License number: <?= htmlspecialchars($certificate['license_number'] ?: 'Not provided') ?><br>
                Issued: <?= htmlspecialchars($certificate['issued_at']) ?>
            </div>
        </div>
    </div>
</article>

<?php include __DIR__ . '/includes/footer.php'; ?>