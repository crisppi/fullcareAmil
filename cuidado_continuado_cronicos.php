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
    if ($action === 'admitir_prelista') {
        $ok = cc_admit_cronico_candidate($conn, (int)($_POST['candidate_id'] ?? 0), $userId, $notes);
        $feedback = $ok ? 'Paciente admitido em Gestão de Crônicos.' : 'Não foi possível admitir o paciente na Gestão de Crônicos.';
        $feedbackType = $ok ? 'success' : 'danger';
    } elseif ($action === 'descartar_prelista') {
        $ok = cc_discard_candidate($conn, (int)($_POST['candidate_id'] ?? 0), $userId, $notes);
        $feedback = $ok ? 'Sugestão removida da pré-lista.' : 'Não foi possível descartar a sugestão.';
        $feedbackType = $ok ? 'success' : 'danger';
    } elseif ($action === 'registrar_acompanhamento') {
        $ok = cc_register_cronico_followup(
            $conn,
            (int)($_POST['cronico_id'] ?? 0),
            trim((string)($_POST['tipo_acao'] ?? '')),
            trim((string)($_POST['proximo_contato'] ?? '')) ?: null,
            $notes,
            $responsavelNome
        );
        $feedback = $ok ? 'Acompanhamento registrado na Gestão de Crônicos.' : 'Não foi possível registrar o acompanhamento.';
        $feedbackType = $ok ? 'success' : 'danger';
    }
}

$search = trim((string)filter_input(INPUT_GET, 'q', FILTER_UNSAFE_RAW));
$risk = trim((string)filter_input(INPUT_GET, 'risco', FILTER_UNSAFE_RAW));
$summary = cc_fetch_cronicos_summary($conn);
$prelist = cc_fetch_cronicos_prelist($conn, $search, $risk);
$rows = cc_fetch_cronicos_list($conn, $search, $risk);
$actions = cc_fetch_program_actions($conn, 'cronicos', 12);

function cc_fmt_date(?string $date): string
{
    if (!$date || $date === '0000-00-00') {
        return '-';
    }
    $dt = DateTime::createFromFormat('Y-m-d', substr((string)$date, 0, 10));
    return $dt ? $dt->format('d/m/Y') : (string)$date;
}

function cc_fmt_datetime(?string $date): string
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

function cc_badge_class(string $risk): string
{
    if ($risk === 'alto') {
        return 'danger';
    }
    if ($risk === 'moderado') {
        return 'warning';
    }
    return 'secondary';
}

function cc_action_label(string $action): string
{
    $map = [
        'admissao' => 'Admissão',
        'ligacao' => 'Ligação',
        'visita_medica' => 'Visita médica',
        'visita_enfermagem' => 'Visita enfermagem',
        'orientacao' => 'Orientação',
        'encerramento' => 'Encerramento',
    ];
    return $map[$action] ?? ucfirst(str_replace('_', ' ', $action));
}
?>
<script src="js/timeout.js"></script>
<style>
    .cc-module-shell {
        padding: 18px 12px 20px;
        background: #f6f8fc;
        min-height: 100vh;
    }
    .cc-module-hero {
        background: linear-gradient(135deg, #6b2230, #b6475f 60%, #f2b8c6);
        color: #fff;
        border-radius: 18px;
        padding: 14px 16px;
    }
    .cc-module-hero h1,
    .cc-module-hero h2,
    .cc-module-hero p,
    .cc-module-hero div {
        color: #fff !important;
    }
    .cc-module-hero .small {
        font-size: .62rem !important;
    }
    .cc-module-hero h1 {
        font-size: 1rem !important;
        margin-top: .35rem !important;
        margin-bottom: .35rem !important;
    }
    .cc-module-hero p {
        font-size: .74rem;
        line-height: 1.4;
    }
    .cc-module-hero .btn {
        min-height: 32px;
        padding: 6px 12px;
        font-size: .72rem;
    }
    .cc-quick-form textarea {
        min-height: 42px;
    }
    .cc-mini-note {
        font-size: .68rem;
        color: #6b7280;
    }
    .cc-module-shell .card {
        border-radius: 16px;
    }
    .cc-module-shell .card-body {
        padding: 14px;
    }
    .cc-module-shell .text-muted.small,
    .cc-module-shell .small {
        font-size: .68rem !important;
    }
    .cc-module-shell .fs-3 {
        font-size: 1.35rem !important;
    }
    .cc-module-shell .form-label {
        font-size: .68rem;
        margin-bottom: 4px;
    }
    .cc-module-shell .form-control,
    .cc-module-shell .form-select,
    .cc-module-shell .btn {
        min-height: 32px;
        height: 32px;
        font-size: .72rem;
        line-height: 1.2;
    }
    .cc-module-shell .form-control::placeholder {
        font-size: .72rem;
        color: #c4c4c4;
    }
    .cc-module-shell .btn.btn-sm {
        min-height: 30px;
        font-size: .68rem;
        padding: 5px 10px;
    }
    .cc-module-shell .table thead th {
        font-size: .56rem;
        letter-spacing: .08em;
        padding: 7px 8px;
        text-transform: uppercase;
    }
    .cc-module-shell .table tbody td {
        font-size: .72rem;
        padding: 6px 8px;
        vertical-align: middle;
    }
</style>

<div class="cc-module-shell">
    <div class="container-fluid">
        <div class="cc-module-hero mb-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                <div>
                    <div class="text-uppercase small fw-semibold" style="letter-spacing:.08em;opacity:.85;">Cuidado Continuado</div>
                    <h1 class="h3 mt-2 mb-2">Gestão de Crônicos</h1>
                    <p class="mb-0" style="max-width:840px;opacity:.92;">
                        Casos sugeridos pela auditoria entram primeiro em uma pré-lista. A partir da admissão manual, o paciente passa a integrar o programa e pode receber ligação, visita médica ou visita de enfermagem.
                    </p>
                </div>
                <div class="d-flex gap-2 flex-wrap align-items-start">
                    <a class="btn btn-light" href="<?= htmlspecialchars($BASE_URL . 'cuidado-continuado') ?>">Dashboard</a>
                    <a class="btn btn-outline-light" href="<?= htmlspecialchars($BASE_URL . 'cuidado-continuado/medicina-preventiva') ?>">Medicina Preventiva</a>
                </div>
            </div>
        </div>

        <?php if ($feedback): ?>
            <div class="alert alert-<?= htmlspecialchars($feedbackType) ?>"><?= htmlspecialchars($feedback) ?></div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-12 col-md-6 col-xl-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Ativos no programa</div><div class="fs-3 fw-bold"><?= (int)$summary['ativos'] ?></div></div></div></div>
            <div class="col-12 col-md-6 col-xl-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Pré-lista auditoria</div><div class="fs-3 fw-bold text-primary"><?= (int)$summary['prelista'] ?></div></div></div></div>
            <div class="col-12 col-md-6 col-xl-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Alto risco</div><div class="fs-3 fw-bold text-danger"><?= (int)$summary['alto_risco'] ?></div></div></div></div>
            <div class="col-12 col-md-6 col-xl-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Contato pendente</div><div class="fs-3 fw-bold text-warning"><?= (int)$summary['pendentes'] ?></div></div></div></div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form class="row g-3 align-items-end" method="get">
                    <div class="col-12 col-lg-7">
                        <label class="form-label">Pesquisar paciente, matrícula ou condição</label>
                        <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Ex.: diabetes, João, matrícula">
                    </div>
                    <div class="col-12 col-lg-3">
                        <label class="form-label">Risco</label>
                        <select name="risco" class="form-select">
                            <option value="">Todos</option>
                            <option value="baixo"<?= $risk === 'baixo' ? ' selected' : '' ?>>Baixo</option>
                            <option value="moderado"<?= $risk === 'moderado' ? ' selected' : '' ?>>Moderado</option>
                            <option value="alto"<?= $risk === 'alto' ? ' selected' : '' ?>>Alto</option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-2 d-grid">
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
                    <div>
                        <h2 class="h5 mb-1">Pré-lista oriunda da auditoria</h2>
                        <div class="text-muted small">Somente depois da admissão o paciente entra efetivamente no programa.</div>
                    </div>
                </div>

                <?php if (!$prelist): ?>
                    <div class="alert alert-light border mb-0">
                        Nenhum caso pendente na pré-lista neste momento.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Paciente</th>
                                    <th>Condição sugerida</th>
                                    <th>Origem</th>
                                    <th>Resumo</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($prelist as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars((string)$item['nome_pac']) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars((string)($item['matricula_pac'] ?: 'Sem matrícula')) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars(cc_fmt_datetime($item['created_at'] ?? null)) ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars((string)$item['condicao']) ?></div>
                                            <span class="badge bg-<?= cc_badge_class((string)$item['nivel_risco']) ?>"><?= htmlspecialchars((string)$item['nivel_risco']) ?></span>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars((string)($item['origem_descricao'] ?: $item['origem_tipo'])) ?></div>
                                            <div class="small text-muted">
                                                <?php if (!empty($item['fk_internacao'])): ?>Internação #<?= (int)$item['fk_internacao'] ?><?php endif; ?>
                                                <?php if (!empty($item['fk_visita'])): ?><?php if (!empty($item['fk_internacao'])): ?> • <?php endif; ?>Visita #<?= (int)$item['fk_visita'] ?><?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="cc-mini-note"><?= htmlspecialchars((string)($item['resumo_clinico'] ?: 'Sem resumo clínico disponível.')) ?></div>
                                        </td>
                                        <td class="text-end" style="min-width:280px;">
                                            <form method="post" class="mb-2">
                                                <input type="hidden" name="cc_action" value="admitir_prelista">
                                                <input type="hidden" name="candidate_id" value="<?= (int)$item['id_prelista'] ?>">
                                                <input type="text" name="observacoes" class="form-control form-control-sm mb-2" placeholder="Observação da admissão">
                                                <button type="submit" class="btn btn-sm btn-primary w-100">Admitir no programa</button>
                                            </form>
                                            <form method="post">
                                                <input type="hidden" name="cc_action" value="descartar_prelista">
                                                <input type="hidden" name="candidate_id" value="<?= (int)$item['id_prelista'] ?>">
                                                <input type="text" name="observacoes" class="form-control form-control-sm mb-2" placeholder="Motivo do descarte">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary w-100">Descartar sugestão</button>
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
                        <h2 class="h5 mb-1">Pacientes ativos no programa</h2>
                        <div class="text-muted small">Registre aqui ligações e visitas presenciais da equipe.</div>
                    </div>
                </div>

                <?php if (!$rows): ?>
                    <div class="alert alert-light border mb-0">
                        Nenhum paciente admitido em Gestão de Crônicos.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Paciente</th>
                                    <th>Condição</th>
                                    <th>Status</th>
                                    <th>Última ação</th>
                                    <th>Próximo contato</th>
                                    <th class="text-end">Registrar acompanhamento</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $row): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars((string)$row['nome_pac']) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars((string)($row['matricula_pac'] ?: 'Sem matrícula')) ?></div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars((string)$row['condicao']) ?></div>
                                            <span class="badge bg-<?= cc_badge_class((string)$row['nivel_risco']) ?>"><?= htmlspecialchars((string)$row['nivel_risco']) ?></span>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars((string)$row['status_acompanhamento']) ?></div>
                                            <div class="small text-muted">Última consulta: <?= htmlspecialchars(cc_fmt_date($row['ultima_consulta'] ?? null)) ?></div>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars(cc_action_label((string)($row['ultima_acao'] ?? ''))) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars(cc_fmt_datetime($row['ultima_acao_em'] ?? null)) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars(cc_fmt_date($row['proximo_contato'] ?? null)) ?></td>
                                        <td class="text-end" style="min-width:330px;">
                                            <form method="post" class="cc-quick-form">
                                                <input type="hidden" name="cc_action" value="registrar_acompanhamento">
                                                <input type="hidden" name="cronico_id" value="<?= (int)$row['id_cronico'] ?>">
                                                <div class="row g-2">
                                                    <div class="col-12">
                                                        <select name="tipo_acao" class="form-select form-select-sm" required>
                                                            <option value="ligacao">Ligação</option>
                                                            <option value="visita_medica">Visita médica</option>
                                                            <option value="visita_enfermagem">Visita enfermagem</option>
                                                            <option value="orientacao">Orientação</option>
                                                            <option value="encerramento">Encerrar no programa</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-12">
                                                        <input type="date" name="proximo_contato" class="form-control form-control-sm" value="<?= htmlspecialchars(date('Y-m-d', strtotime('+30 days'))) ?>">
                                                    </div>
                                                    <div class="col-12">
                                                        <input type="text" name="observacoes" class="form-control form-control-sm" placeholder="Resumo do contato">
                                                    </div>
                                                    <div class="col-12 d-grid">
                                                        <button type="submit" class="btn btn-sm btn-outline-primary">Salvar acompanhamento</button>
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
                        <h2 class="h5 mb-1">Últimos movimentos do programa</h2>
                        <div class="text-muted small">Histórico recente de admissões, ligações e visitas.</div>
                    </div>
                </div>

                <?php if (!$actions): ?>
                    <div class="alert alert-light border mb-0">Sem movimentações recentes na Gestão de Crônicos.</div>
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
                                        <td><?= htmlspecialchars(cc_fmt_datetime($action['realizado_em'] ?? null)) ?></td>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars((string)$action['nome_pac']) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars((string)($action['matricula_pac'] ?: 'Sem matrícula')) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars(cc_action_label((string)$action['tipo_acao'])) ?></td>
                                        <td><?= htmlspecialchars((string)($action['foco'] ?: '-')) ?></td>
                                        <td class="cc-mini-note"><?= htmlspecialchars((string)($action['observacoes'] ?: '-')) ?></td>
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
