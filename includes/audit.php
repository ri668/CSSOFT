<?php

function audit_log(PDO $pdo, string $action, string $entityType, ?int $entityId = null, string $details = ''): void
{
    $actorId = isset($_SESSION['admin_id'])
        ? (int)$_SESSION['admin_id']
        : (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
    $actorName = (string)($_SESSION['staff_name']
        ?? $_SESSION['admin_username']
        ?? $_SESSION['user_username']
        ?? 'system');
    $actorRole = (string)($_SESSION['staff_role'] ?? 'System');

    $stmt = $pdo->prepare("
        INSERT INTO audit_logs
            (actor_id, actor_name, actor_role, action, entity_type, entity_id, details, occurred_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
    ");
    $stmt->execute([$actorId, $actorName, $actorRole, $action, $entityType, $entityId, $details]);
}
