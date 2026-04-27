<?php
include_once("check_logado.php");
require_once("globals.php");
require_once("db.php");
require_once("utils/audit_logger.php");
require_once("templates/header.php");

function auditE($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function auditDecode(?string $json): array
{
    if ($json === null || trim($json) === '') {
        return [];
    }
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function auditHighlightDetail($row): string
{
    $after = auditDecode($row->after_json ?? null);
    $before = auditDecode($row->before_json ?? null);
    $data = $after ?: $before;

    if (($row->entity_type ?? '') === 'login') {
        $parts = [];
        if (!empty($row->actor_user_id)) {
            $parts[] = 'ID: ' . (int)$row->actor_user_id;
        }
        if (!empty($data['usuario_user'])) {
            $parts[] = 'NOME: ' . (string)$data['usuario_user'];
        }
        if (!empty($data['email_user'])) {
            $parts[] = 'EMAIL: ' . (string)$data['email_user'];
        }
        if (!empty($data['cargo_user'])) {
            $parts[] = 'CARGO: ' . (string)$data['cargo_user'];
        }
        return $parts ? implode(' | ', $parts) : '--';
    }

    $fields = [
        'paciente' => ['nome_pac', 'matricula_pac', 'cpf_pac'],
        'hospital' => ['nome_hosp', 'cidade_hosp', 'estado_hosp'],
        'estipulante' => ['nome_est', 'cidade_est', 'estado_est'],
        'seguradora' => ['seguradora_seg', 'cidade_seg', 'estado_seg'],
        'acomodacao' => ['acomodacao_aco', 'valor_aco', 'fk_hospital'],
        'antecedente' => ['antecedente_ant', 'fk_cid_10_ant'],
        'hospital_user' => ['fk_usuario_hosp', 'fk_hospital_user'],
        'patologia' => ['patologia_pat', 'dias_pato', 'fk_cid_10_pat'],
        'censo' => ['senha_censo', 'fk_paciente_censo', 'fk_hospital_censo'],
        'gestao' => ['fk_internacao_ges', 'fk_visita_ges', 'fk_user_ges'],
        'negociacao' => ['troca_de', 'troca_para', 'qtd', 'saving'],
        'prorrogacao' => ['acomod1_pror', 'prorrog1_ini_pror', 'prorrog1_fim_pror'],
        'tuss' => ['tuss_solicitado', 'qtd_tuss_solicitado', 'qtd_tuss_liberado'],
        'usuario' => ['usuario_user', 'email_user', 'cargo_user'],
        'internacao' => ['senha_int', 'fk_paciente_int', 'fk_hospital_int'],
        'visita' => ['visita_no_vis', 'fk_internacao_vis', 'data_visita_vis'],
        'alta' => ['tipo_alta_alt', 'fk_id_int_alt', 'data_alta_alt'],
        'login' => ['email_user', 'cargo_user'],
    ];

    $picked = [];
    foreach (($fields[$row->entity_type] ?? []) as $field) {
        if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
            continue;
        }
        $picked[] = $field . ': ' . (string)$data[$field];
    }

    return $picked ? implode(' | ', $picked) : '--';
}

function auditCanView(): bool
{
    $nivel = (int)($_SESSION['nivel'] ?? 0);
    $email = mb_strtolower(trim((string)($_SESSION['email_user'] ?? '')), 'UTF-8');
    return in_array($nivel, [4, 5], true) || $email === 'crisppi@fullcare.com.br';
}

if (!auditCanView()) {
    http_response_code(403);
    die('Acesso negado.');
}

$dateFrom = trim((string)filter_input(INPUT_GET, 'data_ini')) ?: date('Y-m-d', strtotime('-30 days'));
$dateTo = trim((string)filter_input(INPUT_GET, 'data_fim')) ?: date('Y-m-d');
$action = trim((string)filter_input(INPUT_GET, 'action'));
$entityType = trim((string)filter_input(INPUT_GET, 'entity_type'));
$entityId = (int)(filter_input(INPUT_GET, 'entity_id', FILTER_VALIDATE_INT) ?: 0);
$actorUserId = (int)(filter_input(INPUT_GET, 'actor_user_id', FILTER_VALIDATE_INT) ?: 0);
$q = trim((string)filter_input(INPUT_GET, 'q'));
$limit = (int)(filter_input(INPUT_GET, 'limite', FILTER_VALIDATE_INT) ?: 200);
$limit = max(20, min(500, $limit));

$auditDao = fullcareAuditDao($conn, $BASE_URL);
$filters = [
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'action' => $action,
    'entity_type' => $entityType,
    'entity_id' => $entityId > 0 ? $entityId : null,
    'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
    'q' => $q,
];

$rows = $auditDao->search($filters, $limit);
$total = $auditDao->count($filters);

$users = [];
try {
    $stmt = $conn->query("SELECT id_usuario, usuario_user FROM tb_user ORDER BY usuario_user ASC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    error_log('[AUDIT_LOG][USERS] ' . $e->getMessage());
}
?>
<script src="js/timeout.js"></script>

<div class="container-fluid" style="padding:20px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 style="margin-bottom:4px;">Auditoria</h3>
            <div style="color:#6b7280;">Eventos de negócio gravados no banco.</div>
        </div>
        <div style="font-weight:600;"><?= (int)$total ?> registro(s)</div>
    </div>

    <form method="GET" class="card" style="padding:16px; margin-bottom:20px;">
        <div class="row g-3">
            <div class="col-md-2">
                <label class="form-label">Data inicial</label>
                <input type="date" class="form-control" name="data_ini" value="<?= auditE($dateFrom) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Data final</label>
                <input type="date" class="form-control" name="data_fim" value="<?= auditE($dateTo) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Ação</label>
                <input type="text" class="form-control" name="action" value="<?= auditE($action) ?>" placeholder="ex.: update">
            </div>
            <div class="col-md-2">
                <label class="form-label">Entidade</label>
                <input type="text" class="form-control" name="entity_type" value="<?= auditE($entityType) ?>" placeholder="ex.: internacao">
            </div>
            <div class="col-md-2">
                <label class="form-label">ID registro</label>
                <input type="number" class="form-control" name="entity_id" value="<?= $entityId > 0 ? (int)$entityId : '' ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Usuário</label>
                <select class="form-select" name="actor_user_id">
                    <option value="">Todos</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= (int)$user['id_usuario'] ?>" <?= $actorUserId === (int)$user['id_usuario'] ? 'selected' : '' ?>>
                            <?= auditE($user['usuario_user']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-8">
                <label class="form-label">Busca</label>
                <input type="text" class="form-control" name="q" value="<?= auditE($q) ?>" placeholder="Resumo, registro, usuário, origem">
            </div>
            <div class="col-md-2">
                <label class="form-label">Limite</label>
                <input type="number" class="form-control" name="limite" value="<?= (int)$limit ?>" min="20" max="500">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Ação</th>
                        <th>Entidade</th>
                        <th>Registro</th>
                        <th>Usuário</th>
                        <th>Detalhe</th>
                        <th>Resumo</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted" style="padding:24px;">Nenhum registro encontrado.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= auditE(date('d/m/Y H:i:s', strtotime((string)$row->created_at))) ?></td>
                            <td><?= auditE($row->action) ?></td>
                            <td><?= auditE($row->entity_type) ?></td>
                            <td><?= auditE($row->record_label ?: ('ID ' . (string)$row->entity_id)) ?></td>
                            <td><?=
                                auditE(
                                    $row->actor_user_id
                                        ? ('ID ' . (int)$row->actor_user_id . ' - ' . ($row->actor_user_name ?: ('Usuário #' . (string)$row->actor_user_id)))
                                        : ($row->actor_user_name ?: '--')
                                )
                            ?></td>
                            <td><?= auditE(auditHighlightDetail($row)) ?></td>
                            <td><?= auditE($row->summary) ?></td>
                            <td>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= $BASE_URL ?>show_audit_log.php?id_audit_log=<?= (int)$row->id_audit_log ?>">Ver</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
