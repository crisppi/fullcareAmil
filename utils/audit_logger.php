<?php

if (!function_exists('fullcareAuditLog')) {
    function fullcareAuditLog(PDO $conn, array $payload, string $baseUrl = ''): ?int
    {
        try {
            $action = (string)($payload['action'] ?? 'audit.event');
            $entityType = (string)($payload['entity_type'] ?? 'generic');
            $entityId = (int)($payload['entity_id'] ?? 0);
            $summary = trim((string)($payload['summary'] ?? ''));

            error_log('[AUDIT] action=' . $action
                . ' entity=' . $entityType
                . ' entity_id=' . $entityId
                . ($summary !== '' ? ' summary=' . $summary : '')
            );
        } catch (Throwable $e) {
            error_log('[AUDIT][ERROR] ' . $e->getMessage());
        }

        return null;
    }
}
