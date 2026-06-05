<?php
include_once("check_logado.php");
require_once("templates/header.php");

function audit_e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function audit_allowed(): bool
{
    $nivel = (int)($_SESSION['nivel'] ?? 0);
    $email = mb_strtolower(trim((string)($_SESSION['email_user'] ?? '')), 'UTF-8');

    return in_array($nivel, [4, 5], true) || $email === 'crisppi@fullcare.com.br';
}

function audit_log_candidates(): array
{
    $configured = trim((string)ini_get('error_log'));
    $paths = [
        $configured,
        __DIR__ . '/logs/php_error.log',
        __DIR__ . '/logs/error.log',
        __DIR__ . '/logs/flow_operacional.log',
        '/Applications/AMPPS/apache/logs/error_log',
        '/Applications/AMPPS/apache/logs/error.log',
        '/Applications/AMPPS/php/logs/php_error.log',
    ];

    return array_values(array_unique(array_filter($paths)));
}

function audit_tail_lines(string $path, int $limit = 2500): array
{
    if (!is_readable($path)) {
        return [];
    }

    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return [];
    }

    return array_slice($lines, -$limit);
}

function audit_parse_line(string $line, string $source): ?array
{
    if (strpos($line, '[AUDIT]') !== false) {
        $date = '';
        if (preg_match('/^\[([^\]]+)\]/', $line, $dateMatch)) {
            $date = $dateMatch[1];
        }

        preg_match('/action=([^\s]+)/', $line, $actionMatch);
        preg_match('/entity=([^\s]+)/', $line, $entityMatch);
        preg_match('/entity_id=([0-9]+)/', $line, $idMatch);
        preg_match('/summary=(.*)$/', $line, $summaryMatch);

        return [
            'date' => $date,
            'user' => '-',
            'action' => $actionMatch[1] ?? 'audit.event',
            'entity' => $entityMatch[1] ?? 'generic',
            'entity_id' => $idMatch[1] ?? '-',
            'summary' => trim($summaryMatch[1] ?? ''),
            'source' => basename($source),
        ];
    }

    $jsonPos = strpos($line, '{');
    if ($jsonPos === false) {
        return null;
    }

    $payload = json_decode(substr($line, $jsonPos), true);
    if (!is_array($payload)) {
        return null;
    }

    $stage = (string)($payload['stage'] ?? '');
    $flow = (string)($payload['flow'] ?? '');
    if ($stage !== 'page.access' && $flow !== 'page_access') {
        return null;
    }

    $ctx = is_array($payload['ctx'] ?? null) ? $payload['ctx'] : [];
    $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
    $script = (string)($data['script'] ?? '');
    $query = (string)($data['query_string'] ?? '');

    return [
        'date' => (string)($payload['ts'] ?? ''),
        'user' => (string)($ctx['session_user_name'] ?? '-'),
        'action' => 'page.access',
        'entity' => $script !== '' ? $script : 'Página',
        'entity_id' => '-',
        'summary' => $query !== '' ? '?' . $query : (string)($ctx['request_uri'] ?? ''),
        'source' => basename($source),
    ];
}

function audit_load_events(): array
{
    $events = [];
    foreach (audit_log_candidates() as $path) {
        foreach (audit_tail_lines($path) as $line) {
            $event = audit_parse_line((string)$line, $path);
            if ($event !== null) {
                $events[] = $event;
            }
        }
    }

    return array_reverse(array_slice($events, -300));
}

$events = audit_allowed() ? audit_load_events() : [];
?>

<div id="main-container" class="container-fluid py-4">
    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                <div>
                    <h3 class="mb-1" style="color:#5e2363;">Auditoria</h3>
                    <div class="text-muted">Eventos de auditoria e acessos recentes do sistema.</div>
                </div>
                <a class="btn btn-outline-secondary" href="<?= audit_e($BASE_URL ?? '') ?>menu.php">
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
            </div>

            <?php if (!audit_allowed()): ?>
                <div class="alert alert-warning mb-0">Você não tem permissão para visualizar a auditoria.</div>
            <?php elseif (!$events): ?>
                <div class="alert alert-info mb-0">
                    Nenhum evento de auditoria encontrado nos logs disponíveis.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Usuário</th>
                                <th>Ação</th>
                                <th>Origem</th>
                                <th>ID</th>
                                <th>Resumo</th>
                                <th>Log</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events as $event): ?>
                                <tr>
                                    <td><?= audit_e($event['date']) ?></td>
                                    <td><?= audit_e($event['user']) ?></td>
                                    <td><span class="badge text-bg-light"><?= audit_e($event['action']) ?></span></td>
                                    <td><?= audit_e($event['entity']) ?></td>
                                    <td><?= audit_e($event['entity_id']) ?></td>
                                    <td><?= audit_e($event['summary']) ?></td>
                                    <td class="text-muted"><?= audit_e($event['source']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
