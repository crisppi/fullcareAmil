<?php
include_once("check_logado.php");
require_once("templates/header.php");
require_once(__DIR__ . "/app/cuidadoContinuado.php");

ensure_cuidado_continuado_schema($conn);

$feedback = null;
$feedbackType = 'success';
$userId = (int)($_SESSION['id_usuario'] ?? 0);
$responsavelNome = trim((string)($_SESSION['usuario_nome'] ?? ($_SESSION['nome_usuario'] ?? ($_SESSION['email_user'] ?? ('Usuário #' . $userId)))));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = trim((string)($_POST['cc_action'] ?? ''));
    $notes = trim((string)($_POST['observacoes'] ?? ''));
    if ($action === 'admitir_preventiva') {
        $ok = cc_admit_preventiva_from_cronico($conn, (int)($_POST['cronico_id'] ?? 0), $userId, $notes);
        $feedback = $ok ? 'Paciente admitido em Medicina Preventiva.' : 'Não foi possível admitir o paciente em Medicina Preventiva.';
        $feedbackType = $ok ? 'success' : 'danger';
    } elseif ($action === 'registrar_monitoramento') {
        $ok = cc_register_preventiva_followup(
            $conn,
            (int)($_POST['preventivo_id'] ?? 0),
            trim((string)($_POST['proximo_contato'] ?? '')) ?: null,
            $notes,
            $responsavelNome,
            trim((string)($_POST['tipo_acao'] ?? 'monitoramento_telefonico'))
        );
        $feedback = $ok ? 'Monitoramento telefônico registrado.' : 'Não foi possível registrar o monitoramento.';
        $feedbackType = $ok ? 'success' : 'danger';
    }
}

$search = trim((string)filter_input(INPUT_GET, 'q', FILTER_UNSAFE_RAW));
$summary = cc_fetch_preventiva_summary($conn);
$elegiveis = cc_fetch_preventiva_elegiveis($conn);
$monitorados = cc_fetch_preventiva_active($conn, $search);
$actions = cc_fetch_program_actions($conn, 'preventiva', 12);

function mp_fmt_date(?string $date): string
{
    if (!$date || $date === '0000-00-00') {
        return '-';
    }
    $dt = DateTime::createFromFormat('Y-m-d', substr((string)$date, 0, 10));
    return $dt ? $dt->format('d/m/Y') : (string)$date;
}

function mp_fmt_datetime(?string $date): string
{
    if (!$date) {
        return '-';
    }
    try {
        return (new DateTime($date))->format('d/m/Y H:i');
    } catch (Throwable $e) {
        return (string)$date;
    }
}

function mp_badge_class(string $risk): string
{
    if ($risk === 'alto') {
        return 'danger';
    }
    if ($risk === 'moderado') {
        return 'warning';
    }
    return 'secondary';
}

function mp_action_label(string $action): string
{
    $map = [
        'admissao' => 'Admissão',
        'monitoramento_telefonico' => 'Monitoramento telefônico',
        'orientacao' => 'Orientação',
        'encerramento' => 'Encerramento',
    ];
    return $map[$action] ?? ucfirst(str_replace('_', ' ', $action));
}
?>
<script src="js/timeout.js"></script>
<style>
    .mp-shell {
        padding: 18px 12px 20px;
        background: #f7faf5;
        min-height: 100vh;
    }
    .mp-hero {
        background: linear-gradient(135deg, #1b6a43, #3ba56b 58%, #c9e7b5);
        color: #fff;
        border-radius: 18px;
        padding: 14px 16px;
    }
    .mp-hero h1,
    .mp-hero h2,
    .mp-hero p,
    .mp-hero div {
        color: #fff !important;
    }
    .mp-hero .small {
        font-size: .62rem !important;
    }
    .mp-hero h1 {
        font-size: 1rem !important;
        margin-top: .35rem !important;
        margin-bottom: .35rem !important;
    }
    .mp-hero p {
        font-size: .74rem;
        line-height: 1.4;
    }
    .mp-hero .btn {
        min-height: 32px;
        padding: 6px 12px;
        font-size: .72rem;
    }
    .mp-mini-note {
        font-size: .68rem;
        color: #6b7280;
    }
    .mp-shell .card {
        border-radius: 16px;
    }
    .mp-shell .card-body {
        padding: 14px;
    }
    .mp-shell .text-muted.small,
    .mp-shell .small {
        font-size: .68rem !important;
    }
    .mp-shell .fs-3 {
        font-size: 1.35rem !important;
    }
    .mp-shell .form-label {
        font-size: .68rem;
        margin-bottom: 4px;
    }
    .mp-shell .form-control,
    .mp-shell .form-select,
    .mp-shell .btn {
        min-height: 32px;
        height: 32px;
        font-size: .72rem;
        line-height: 1.2;
    }
    .mp-shell .form-control::placeholder {
        font-size: .72rem;
        color: #c4c4c4;
    }
    .mp-shell .btn.btn-sm {
        min-height: 30px;
        font-size: .68rem;
        padding: 5px 10px;
    }
    .mp-shell .table thead th {
        font-size: .56rem;
        letter-spacing: .08em;
        padding: 7px 8px;
        text-transform: uppercase;
    }
    .mp-shell .table tbody td {
        font-size: .72rem;
        padding: 6px 8px;
        vertical-align: middle;
    }
</style>

<div class="mp-shell">
    <div class="container-fluid">
        <div class="mp-hero mb-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                <div>
                    <div class="text-uppercase small fw-semibold" style="letter-spacing:.08em;opacity:.85;">Cuidado Continuado</div>
                    <h1 class="h3 mt-2 mb-2">Medicina Preventiva</h1>
                    <p class="mb-0" style="max-width:840px;opacity:.92;">
                        A Medicina Preventiva funciona como um monitoramento telefônico estruturado. Os pacientes elegíveis são admitidos no programa e passam a ter rotina de contato, orientação e acompanhamento.
                    </p>
                </div>
                <div class="d-flex gap-2 flex-wrap align-items-start">
                    <a class="btn btn-light" href="<?= htmlspecialchars($BASE_URL . 'cuidado-continuado') ?>">Dashboard</a>
                    <a class="btn btn-outline-light" href="<?= htmlspecialchars($BASE_URL . 'cuidado-continuado/cronicos') ?>">Gestão de Crônicos</a>
                </div>
            </div>
        </div>

        <?php if ($feedback): ?>
            <div class="alert alert-<?= htmlspecialchars($feedbackType) ?>"><?= htmlspecialchars($feedback) ?></div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-12 col-md-6 col-xl-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Em monitoramento</div><div class="fs-3 fw-bold"><?= (int)$summary['ativos'] ?></div></div></div></div>
            <div class="col-12 col-md-6 col-xl-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Elegíveis para admissão</div><div class="fs-3 fw-bold text-primary"><?= (int)$summary['elegiveis'] ?></div></div></div></div>
            <div class="col-12 col-md-6 col-xl-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Pendentes de contato</div><div class="fs-3 fw-bold text-warning"><?= (int)$summary['pendentes'] ?></div></div></div></div>
            <div class="col-12 col-md-6 col-xl-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Alto risco</div><div class="fs-3 fw-bold text-danger"><?= (int)$summary['alto_risco'] ?></div></div></div></div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form class="row g-3 align-items-end" method="get">
                    <div class="col-12 col-lg-10">
                        <label class="form-label">Pesquisar paciente, matrícula ou foco do monitoramento</label>
                        <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Ex.: João, diabetes, matrícula">
                    </div>
                    <div class="col-12 col-lg-2 d-grid">
                        <button type="submit" class="btn btn-success">Filtrar</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
                    <div>
                        <h2 class="h5 mb-1">Elegíveis para entrar no monitoramento</h2>
                        <div class="text-muted small">Pacientes já admitidos em crônicos e ainda não acompanhados pela preventiva.</div>
                    </div>
                </div>
                <?php if (!$elegiveis): ?>
                    <div class="alert alert-light border mb-0">
                        Não há elegíveis pendentes para Medicina Preventiva.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Paciente</th>
                                    <th>Condição de origem</th>
                                    <th>Risco</th>
                                    <th>Próximo contato do crônico</th>
                                    <th class="text-end">Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($elegiveis as $row): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars((string)$row['nome_pac']) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars((string)($row['matricula_pac'] ?: 'Sem matrícula')) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars((string)$row['condicao']) ?></td>
                                        <td><span class="badge bg-<?= mp_badge_class((string)$row['nivel_risco']) ?>"><?= htmlspecialchars((string)$row['nivel_risco']) ?></span></td>
                                        <td><?= htmlspecialchars(mp_fmt_date($row['proximo_contato_cronico'] ?? null)) ?></td>
                                        <td class="text-end" style="min-width:280px;">
                                            <form method="post">
                                                <input type="hidden" name="cc_action" value="admitir_preventiva">
                                                <input type="hidden" name="cronico_id" value="<?= (int)$row['id_cronico'] ?>">
                                                <input type="text" name="observacoes" class="form-control form-control-sm mb-2" placeholder="Observação da admissão">
                                                <button type="submit" class="btn btn-sm btn-success w-100">Admitir em Medicina Preventiva</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
                    <div>
                        <h2 class="h5 mb-1">Pacientes em monitoramento telefônico</h2>
                        <div class="text-muted small">Ações da preventiva ficam registradas com data, próximo contato e observação.</div>
                    </div>
                </div>
                <?php if (!$monitorados): ?>
                    <div class="alert alert-light border mb-0">
                        Nenhum paciente ativo em Medicina Preventiva.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Paciente</th>
                                    <th>Foco</th>
                                    <th>Status</th>
                                    <th>Última ação</th>
                                    <th>Próximo contato</th>
                                    <th class="text-end">Registrar monitoramento</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($monitorados as $row): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars((string)$row['nome_pac']) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars((string)($row['matricula_pac'] ?: 'Sem matrícula')) ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars((string)$row['foco_monitoramento']) ?></div>
                                            <span class="badge bg-<?= mp_badge_class((string)$row['nivel_risco']) ?>"><?= htmlspecialchars((string)$row['nivel_risco']) ?></span>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars((string)$row['status_monitoramento']) ?></div>
                                            <div class="small text-muted">Último contato: <?= htmlspecialchars(mp_fmt_date($row['ultima_interacao'] ?? null)) ?></div>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars(mp_action_label((string)($row['ultima_acao'] ?? ''))) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars(mp_fmt_datetime($row['ultima_acao_em'] ?? null)) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars(mp_fmt_date($row['proximo_contato'] ?? null)) ?></td>
                                        <td class="text-end" style="min-width:330px;">
                                            <form method="post">
                                                <input type="hidden" name="cc_action" value="registrar_monitoramento">
                                                <input type="hidden" name="preventivo_id" value="<?= (int)$row['id_preventivo'] ?>">
                                                <div class="row g-2">
                                                    <div class="col-12">
                                                        <select name="tipo_acao" class="form-select form-select-sm" required>
                                                            <option value="monitoramento_telefonico">Monitoramento telefônico</option>
                                                            <option value="orientacao">Orientação</option>
                                                            <option value="encerramento">Encerrar no programa</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-12">
                                                        <input type="date" name="proximo_contato" class="form-control form-control-sm" value="<?= htmlspecialchars(date('Y-m-d', strtotime('+15 days'))) ?>">
                                                    </div>
                                                    <div class="col-12">
                                                        <input type="text" name="observacoes" class="form-control form-control-sm" placeholder="Resumo da ligação">
                                                    </div>
                                                    <div class="col-12 d-grid">
                                                        <button type="submit" class="btn btn-sm btn-outline-success">Salvar monitoramento</button>
                                                    </div>
                                                </div>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
                    <div>
                        <h2 class="h5 mb-1">Últimos movimentos da preventiva</h2>
                        <div class="text-muted small">Histórico recente de admissões e monitoramentos telefônicos.</div>
                    </div>
                </div>
                <?php if (!$actions): ?>
                    <div class="alert alert-light border mb-0">Sem movimentações recentes em Medicina Preventiva.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Quando</th>
                                    <th>Paciente</th>
                                    <th>Ação</th>
                                    <th>Foco</th>
                                    <th>Observações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($actions as $action): ?>
                                    <tr>
                                        <td><?= htmlspecialchars(mp_fmt_datetime($action['realizado_em'] ?? null)) ?></td>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars((string)$action['nome_pac']) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars((string)($action['matricula_pac'] ?: 'Sem matrícula')) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars(mp_action_label((string)$action['tipo_acao'])) ?></td>
                                        <td><?= htmlspecialchars((string)($action['foco'] ?: '-')) ?></td>
                                        <td class="mp-mini-note"><?= htmlspecialchars((string)($action['observacoes'] ?: '-')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include_once("templates/footer.php"); ?>
