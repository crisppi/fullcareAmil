<?php
include_once("check_logado.php");
require_once("globals.php");
require_once("db.php");
require_once("utils/audit_logger.php");
require_once("templates/header.php");

function auditDetailE($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function auditPrettyJson(?string $json): string
{
    if ($json === null || trim($json) === '') {
        return '--';
    }
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return auditDetailE($json);
    }
    return auditDetailE(json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function auditPrettyFieldList(?string $json): string
{
    if ($json === null || trim($json) === '') {
        return '--';
    }
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return auditDetailE($json);
    }

    $lines = [];
    foreach ($decoded as $key => $value) {
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $lines[] = $key . ': ' . (string)$value;
    }

    return auditDetailE(implode(PHP_EOL, $lines));
}

$idAuditLog = (int)(filter_input(INPUT_GET, 'id_audit_log', FILTER_VALIDATE_INT) ?: 0);
$auditDao = fullcareAuditDao($conn, $BASE_URL);
$log = $idAuditLog > 0 ? $auditDao->findById($idAuditLog) : null;

if (!$log) {
    http_response_code(404);
    die('Registro de auditoria não encontrado.');
}
?>
<script src="js/timeout.js"></script>

<div class="container-fluid" style="padding:20px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 style="margin:0;">Detalhe da Auditoria</h3>
        <a class="btn btn-outline-secondary" href="<?= $BASE_URL ?>list_audit_log.php">Voltar</a>
    </div>

    <div class="card" style="padding:20px;">
        <div class="row">
            <div class="col-md-6">
                <p><strong>ID:</strong> <?= (int)$log->id_audit_log ?></p>
                <p><strong>Data:</strong> <?= auditDetailE(date('d/m/Y H:i:s', strtotime((string)$log->created_at))) ?></p>
                <p><strong>Ação:</strong> <?= auditDetailE($log->action) ?></p>
                <p><strong>Entidade:</strong> <?= auditDetailE($log->entity_type) ?></p>
                <p><strong>ID registro:</strong> <?= auditDetailE($log->entity_id) ?></p>
            </div>
            <div class="col-md-6">
                <p><strong>Registro:</strong> <?= auditDetailE($log->record_label) ?></p>
                <p><strong>Executado por:</strong> <?= auditDetailE($log->actor_user_name) ?><?= $log->actor_user_id ? ' (#' . (int)$log->actor_user_id . ')' : '' ?></p>
                <p><strong>IP:</strong> <?= auditDetailE($log->ip_address) ?></p>
                <p><strong>Trace ID:</strong> <?= auditDetailE($log->trace_id) ?></p>
                <p><strong>Origem:</strong> <?= auditDetailE($log->source) ?></p>
            </div>
        </div>

        <hr>
        <p><strong>Resumo:</strong> <?= auditDetailE($log->summary) ?></p>

        <div class="row" style="margin-top:20px;">
            <div class="col-md-4">
                <h5>Antes</h5>
                <pre style="background:#f8fafc; border:1px solid #e5e7eb; padding:12px; min-height:240px; white-space:pre-wrap;"><?= auditPrettyFieldList($log->before_json) ?></pre>
            </div>
            <div class="col-md-4">
                <h5>Depois</h5>
                <pre style="background:#f8fafc; border:1px solid #e5e7eb; padding:12px; min-height:240px; white-space:pre-wrap;"><?= auditPrettyFieldList($log->after_json) ?></pre>
            </div>
            <div class="col-md-4">
                <h5>Contexto</h5>
                <pre style="background:#f8fafc; border:1px solid #e5e7eb; padding:12px; min-height:240px; white-space:pre-wrap;"><?= auditPrettyFieldList($log->context_json) ?></pre>
            </div>
        </div>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
