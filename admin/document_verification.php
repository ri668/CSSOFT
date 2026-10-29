<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/audit.php';

$page_title = 'Blockchain Verification';
$errors = [];
$message = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$patients = $pdo->query("SELECT id, full_name FROM patients ORDER BY full_name")->fetchAll();
$verification = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $errors[] = 'The request expired. Reload the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'register') {
        $name = trim($_POST['document_name'] ?? '');
        $hash = strtolower(trim($_POST['document_hash'] ?? ''));
        $patientId = (int)($_POST['patient_id'] ?? 0);

        if ($name === '' || strlen($name) > 150) {
            $errors[] = 'Enter a document name of 1 to 150 characters.';
        }
        if (!preg_match('/^[a-f0-9]{64}$/', $hash)) {
            $errors[] = 'Document hash must be a SHA-256 hash (64 hexadecimal characters).';
        }
        if ($patientId > 0) {
            $patientCheck = $pdo->prepare("SELECT id FROM patients WHERE id = ?");
            $patientCheck->execute([$patientId]);
            if (!$patientCheck->fetch()) {
                $errors[] = 'The selected patient record does not exist.';
            }
        }

        if (!$errors) {
            $previous = $pdo->query("SELECT chain_hash FROM blockchain_documents ORDER BY id DESC LIMIT 1")->fetchColumn() ?: str_repeat('0', 64);
            $registeredBy = (string)($_SESSION['staff_name'] ?? 'staff');
            $chainHash = hash('sha256', $previous . '|' . $hash . '|' . $name . '|' . $registeredBy . '|' . gmdate('c'));
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO blockchain_documents
                        (patient_id, document_name, document_hash, chain_hash, registered_by)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$patientId > 0 ? $patientId : null, $name, $hash, $chainHash, $registeredBy]);
                $documentId = (int)$pdo->lastInsertId();
                audit_log($pdo, 'register_document_hash', 'blockchain_document', $documentId, 'Registered SHA-256 document hash: ' . $name);
                $message = 'Document hash registered successfully.';
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errors[] = 'That document hash is already registered.';
                } else {
                    throw $e;
                }
            }
        }
    } elseif (($_POST['action'] ?? '') === 'verify') {
        $hash = strtolower(trim($_POST['verify_hash'] ?? ''));
        if (!preg_match('/^[a-f0-9]{64}$/', $hash)) {
            $errors[] = 'Enter a valid SHA-256 hash to verify.';
        } else {
            $stmt = $pdo->prepare("
                SELECT bd.*, p.full_name
                FROM blockchain_documents bd
                LEFT JOIN patients p ON p.id = bd.patient_id
                WHERE bd.document_hash = ?
                LIMIT 1
            ");
            $stmt->execute([$hash]);
            $verification = $stmt->fetch() ?: ['document_hash' => $hash];
            audit_log($pdo, 'verify_document_hash', 'blockchain_document', isset($verification['id']) ? (int)$verification['id'] : null, 'Verified SHA-256 hash');
        }
    }
}

$registered = $pdo->query("
    SELECT bd.*, p.full_name
    FROM blockchain_documents bd
    LEFT JOIN patients p ON p.id = bd.patient_id
    ORDER BY bd.registered_at DESC, bd.id DESC
    LIMIT 100
")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="page-heading d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <p class="text-success fw-semibold text-uppercase small mb-1">Integrity ledger</p>
        <h1 class="h2 mb-1">Blockchain verification</h1>
        <p class="text-muted mb-0">Register and verify SHA-256 document hashes using the tamper-evident local chain.</p>
    </div>
</div>

<?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card p-4 h-100">
            <h2 class="h5 section-heading">Register document hash</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="register">
                <label class="form-label" for="document_name">Document name</label>
                <input id="document_name" name="document_name" class="form-control mb-3" maxlength="150" required>
                <label class="form-label" for="patient_id">Patient record <span class="text-muted">(optional)</span></label>
                <select id="patient_id" name="patient_id" class="form-select mb-3">
                    <option value="0">Not linked to a patient</option>
                    <?php foreach ($patients as $patient): ?><option value="<?= (int)$patient['id'] ?>">#<?= (int)$patient['id'] ?> · <?= htmlspecialchars($patient['full_name']) ?></option><?php endforeach; ?>
                </select>
                <label class="form-label" for="document_hash">SHA-256 document hash</label>
                <input id="document_hash" name="document_hash" class="form-control font-monospace" pattern="[A-Fa-f0-9]{64}" maxlength="64" required>
                <div class="form-text mb-3">Generate the hash from the exact document bytes before registering it.</div>
                <button class="btn btn-success">Register hash</button>
            </form>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card p-4 h-100">
            <h2 class="h5 section-heading">Verify document</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="verify">
                <label class="form-label" for="verify_hash">SHA-256 document hash</label>
                <input id="verify_hash" name="verify_hash" class="form-control font-monospace mb-3" pattern="[A-Fa-f0-9]{64}" maxlength="64" required>
                <button class="btn btn-primary">Verify document</button>
            </form>
            <?php if ($verification): ?>
                <div class="alert <?= isset($verification['id']) ? 'alert-success' : 'alert-warning' ?> mt-4 mb-0">
                    <?php if (isset($verification['id'])): ?>
                        <strong>Authentic match found.</strong><br>
                        <?= htmlspecialchars($verification['document_name']) ?> · Registered <?= htmlspecialchars($verification['registered_at']) ?><br>
                        <?= $verification['full_name'] ? 'Patient: ' . htmlspecialchars($verification['full_name']) : 'No patient linked' ?>
                    <?php else: ?>
                        <strong>No registered match found.</strong><br>This hash is not in the integrity ledger.
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card p-3 mt-4">
    <h2 class="h5 mb-3">Registered document hashes</h2>
    <div class="table-responsive"><table class="table table-hover align-middle">
        <thead><tr><th>Document</th><th>Patient</th><th>SHA-256 hash</th><th>Registered by</th><th>Timestamp</th></tr></thead>
        <tbody><?php foreach ($registered as $document): ?><tr>
            <td><?= htmlspecialchars($document['document_name']) ?></td>
            <td><?= $document['full_name'] ? htmlspecialchars($document['full_name']) : '<span class="text-muted">—</span>' ?></td>
            <td class="font-monospace small"><?= htmlspecialchars($document['document_hash']) ?></td>
            <td><?= htmlspecialchars($document['registered_by']) ?></td>
            <td><?= htmlspecialchars($document['registered_at']) ?></td>
        </tr><?php endforeach; if (!$registered): ?><tr><td colspan="5" class="text-center text-muted py-4">No document hashes registered.</td></tr><?php endif; ?></tbody>
    </table></div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
