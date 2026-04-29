<?php
include_once("check_logado.php");
require_once("templates/header.php");
require_once(__DIR__ . "/app/cuidadoContinuado.php");

ensure_cuidado_continuado_schema($conn);

$cronicos = cc_fetch_cronicos_summary($conn);
$preventiva = cc_fetch_preventiva_summary($conn);
$prelistaCronicos = cc_fetch_cronicos_prelist($conn);
$elegiveis = cc_fetch_preventiva_elegiveis($conn);
$acoesCronicos = cc_fetch_program_actions($conn, 'cronicos', 5);
$acoesPreventiva = cc_fetch_program_actions($conn, 'preventiva', 5);

function cc_card(string $title, string $value, string $subtitle, string $accent): void
{
    ?>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <span class="badge rounded-pill cc-kpi-badge" style="background: <?= htmlspecialchars($accent) ?>1a; color: <?= htmlspecialchars($accent) ?>;">
                    <?= htmlspecialchars($title) ?>
                </span>
                <div class="mt-2 cc-kpi-value" style="font-weight:700;color:#24324a;line-height:1;">
                    <?= htmlspecialchars($value) ?>
                </div>
                <div class="mt-1 text-muted small cc-kpi-subtitle"><?= htmlspecialchars($subtitle) ?></div>
            </div>
        </div>
    </div>
    <?php
}

function cc_fmt_datetime_dash(?string $value): string
{
    if (!$value) {
        return '-';
    }
    try {
        return (new DateTime($value))->format('d/m/Y H:i');
    } catch (Throwable $e) {
        return (string)$value;
    }
}
?>
<script src="js/timeout.js"></script>
<style>
    .cc-shell {
        padding: 18px 12px 20px;
        background: linear-gradient(180deg, #f5f8ff 0%, #ffffff 180px);
        min-height: 100vh;
    }
    .cc-hero {
        background: linear-gradient(135deg, #0f3d63, #1d6a96 58%, #71c2cb);
        color: #fff;
        border-radius: 18px;
        padding: 14px 16px;
        box-shadow: 0 14px 34px rgba(15, 61, 99, 0.16);
    }
    .cc-hero h1,
    .cc-hero h2,
    .cc-hero p,
    .cc-hero div {
        color: #fff !important;
    }
    .cc-hero .small {
        font-size: .62rem !important;
    }
    .cc-hero h1 {
        font-size: 1rem !important;
        margin-top: .35rem !important;
        margin-bottom: .35rem !important;
    }
    .cc-hero p {
        font-size: .74rem;
        line-height: 1.4;
    }
    .cc-hero .btn {
        min-height: 32px;
        padding: 6px 12px;
        font-size: .72rem;
    }
    .cc-link-card {
        display: block;
        text-decoration: none;
        color: inherit;
        background: #fff;
        border-radius: 16px;
        padding: 14px;
        height: 100%;
        box-shadow: 0 10px 26px rgba(36, 50, 74, 0.08);
        border: 1px solid rgba(15, 61, 99, 0.08);
    }
    .cc-link-card:hover {
        color: inherit;
        transform: translateY(-1px);
    }
    .cc-shell .card {
        border-radius: 16px;
    }
    .cc-shell .card-body {
        padding: 14px;
    }
    .cc-shell .h4,
    .cc-shell .h5,
    .cc-shell h2,
    .cc-shell h3 {
        font-size: .9rem !important;
    }
    .cc-shell .text-muted.small,
    .cc-shell .small {
        font-size: .68rem !important;
    }
    .cc-shell .fs-5,
    .cc-kpi-value {
        font-size: 1.4rem !important;
    }
    .cc-kpi-badge {
        font-size: .64rem;
        padding: .28rem .55rem;
    }
    .cc-kpi-subtitle {
        font-size: .66rem !important;
    }
    .cc-shell .btn.btn-sm {
        min-height: 30px;
        font-size: .68rem;
        padding: 5px 10px;
    }
    .cc-shell .table thead th {
        font-size: .56rem;
        letter-spacing: .08em;
        padding: 7px 8px;
        text-transform: uppercase;
    }
    .cc-shell .table tbody td {
        font-size: .72rem;
        padding: 6px 8px;
        vertical-align: middle;
    }
    .cc-shell .list-group-item {
        font-size: .72rem;
        padding-top: .45rem;
        padding-bottom: .45rem;
    }
</style>

<div class="cc-shell">
    <div class="container-fluid">
        <div class="cc-hero mb-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end">
                <div>
                    <div class="text-uppercase small fw-semibold" style="letter-spacing:.08em;opacity:.8;">Cuidado Continuado</div>
                    <h1 class="h3 mt-2 mb-2">Gestão operacional de programas assistenciais</h1>
                    <p class="mb-0" style="max-width:860px;opacity:.92;">
                        Os casos identificados pela auditoria entram primeiro em pré-lista. Depois da admissão manual, seguem para Gestão de Crônicos ou Medicina Preventiva com monitoramento e histórico de ações.
                    </p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a class="btn btn-light" href="<?= htmlspecialchars($BASE_URL . 'cuidado-continuado/cronicos') ?>">Gestão de Crônicos</a>
                    <a class="btn btn-outline-light" href="<?= htmlspecialchars($BASE_URL . 'cuidado-continuado/medicina-preventiva') ?>">Medicina Preventiva</a>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <?php cc_card('Pré-lista crônicos', (string)$cronicos['prelista'], 'Sugestões aguardando admissão manual.', '#1d6a96'); ?>
            <?php cc_card('Crônicos ativos', (string)$cronicos['ativos'], 'Pacientes já monitorados pelo programa.', '#c43d4b'); ?>
            <?php cc_card('Preventiva ativa', (string)$preventiva['ativos'], 'Pacientes em monitoramento telefônico.', '#198754'); ?>
            <?php cc_card('Elegíveis preventiva', (string)$preventiva['elegiveis'], 'Pacientes prontos para admissão na preventiva.', '#b26a00'); ?>
        </div>

        <div class="row g-4">
            <div class="col-12 col-xl-6">
                <a class="cc-link-card" href="<?= htmlspecialchars($BASE_URL . 'cuidado-continuado/cronicos') ?>">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <div class="text-uppercase small fw-semibold text-muted">Programa 1</div>
                            <h2 class="h4 mt-2">Gestão de Crônicos</h2>
                            <p class="text-muted mb-3">Pré-lista da auditoria, admissão no programa, visitas e ligações registradas na mesma trilha.</p>
                        </div>
                        <i class="bi bi-heart-pulse-fill" style="font-size:2rem;color:#1d6a96;"></i>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-6">
                            <div class="text-muted small">Pendentes</div>
                            <div class="fw-bold fs-5"><?= (int)$cronicos['prelista'] ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Alto risco</div>
                            <div class="fw-bold fs-5"><?= (int)$cronicos['alto_risco'] ?></div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-12 col-xl-6">
                <a class="cc-link-card" href="<?= htmlspecialchars($BASE_URL . 'cuidado-continuado/medicina-preventiva') ?>">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <div class="text-uppercase small fw-semibold text-muted">Programa 2</div>
                            <h2 class="h4 mt-2">Medicina Preventiva</h2>
                            <p class="text-muted mb-3">Monitoramento telefônico com admissão formal, agenda de retorno e histórico de contatos.</p>
                        </div>
                        <i class="bi bi-telephone-forward-fill" style="font-size:2rem;color:#198754;"></i>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-6">
                            <div class="text-muted small">Ativos</div>
                            <div class="fw-bold fs-5"><?= (int)$preventiva['ativos'] ?></div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Pendentes</div>
                            <div class="fw-bold fs-5"><?= (int)$preventiva['pendentes'] ?></div>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-12 col-xl-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h3 class="h5 mb-0">Pré-lista de Crônicos</h3>
                            <a href="<?= htmlspecialchars($BASE_URL . 'cuidado-continuado/cronicos') ?>" class="btn btn-sm btn-outline-secondary">Abrir módulo</a>
                        </div>
                        <?php if (!$prelistaCronicos): ?>
                            <div class="alert alert-light border mb-0">Sem casos pendentes na pré-lista de auditoria.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Paciente</th>
                                            <th>Condição</th>
                                            <th>Origem</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($prelistaCronicos, 0, 5) as $row): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold"><?= htmlspecialchars((string)$row['nome_pac']) ?></div>
                                                    <div class="small text-muted"><?= htmlspecialchars((string)($row['matricula_pac'] ?: 'Sem matrícula')) ?></div>
                                                </td>
                                                <td><?= htmlspecialchars((string)$row['condicao']) ?></td>
                                                <td><?= htmlspecialchars((string)($row['origem_descricao'] ?: $row['origem_tipo'])) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h3 class="h5 mb-0">Elegíveis da Preventiva</h3>
                            <a href="<?= htmlspecialchars($BASE_URL . 'cuidado-continuado/medicina-preventiva') ?>" class="btn btn-sm btn-outline-secondary">Abrir módulo</a>
                        </div>
                        <?php if (!$elegiveis): ?>
                            <div class="alert alert-light border mb-0">Nenhum elegível aguardando admissão na Medicina Preventiva.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Paciente</th>
                                            <th>Condição</th>
                                            <th>Risco</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($elegiveis, 0, 5) as $row): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold"><?= htmlspecialchars((string)$row['nome_pac']) ?></div>
                                                    <div class="small text-muted"><?= htmlspecialchars((string)($row['matricula_pac'] ?: 'Sem matrícula')) ?></div>
                                                </td>
                                                <td><?= htmlspecialchars((string)$row['condicao']) ?></td>
                                                <td><?= htmlspecialchars((string)$row['nivel_risco']) ?></td>
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

        <div class="row g-4 mt-1">
            <div class="col-12 col-xl-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <h3 class="h5 mb-3">Últimas ações em Crônicos</h3>
                        <?php if (!$acoesCronicos): ?>
                            <div class="alert alert-light border mb-0">Sem ações recentes.</div>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($acoesCronicos as $acao): ?>
                                    <li class="list-group-item px-0">
                                        <div class="fw-semibold"><?= htmlspecialchars((string)$acao['nome_pac']) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars(cc_fmt_datetime_dash($acao['realizado_em'] ?? null)) ?> • <?= htmlspecialchars((string)$acao['tipo_acao']) ?></div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <h3 class="h5 mb-3">Últimas ações na Preventiva</h3>
                        <?php if (!$acoesPreventiva): ?>
                            <div class="alert alert-light border mb-0">Sem ações recentes.</div>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($acoesPreventiva as $acao): ?>
                                    <li class="list-group-item px-0">
                                        <div class="fw-semibold"><?= htmlspecialchars((string)$acao['nome_pac']) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars(cc_fmt_datetime_dash($acao['realizado_em'] ?? null)) ?> • <?= htmlspecialchars((string)$acao['tipo_acao']) ?></div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once("templates/footer.php"); ?>
