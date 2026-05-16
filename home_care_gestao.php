<?php
include_once("check_logado.php");
require_once("globals.php");
require_once("db.php");
require_once("templates/header.php");
require_once("dao/homeCareDao.php");

if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$dao = new HomeCareDAO($conn, $BASE_URL);
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$seguradoraId = filter_input(INPUT_GET, 'seguradora_id', FILTER_VALIDATE_INT) ?: null;
$status = trim((string)(filter_input(INPUT_GET, 'status') ?? ''));
$modalidade = trim((string)(filter_input(INPUT_GET, 'modalidade') ?? ''));
$semAtualizacao = filter_input(INPUT_GET, 'sem_atualizacao', FILTER_VALIDATE_INT) ?: null;
$queue = [];
$statusOptions = $dao->getStatusOptions();
$modalidadeOptions = $dao->getModalidadeOptions();
$barreiraOptions = $dao->getBarreiraOptions();
$hospitais = [];
$seguradoras = [];
$pageError = '';

try {
    $queue = $dao->fetchQueue([
        'hospital_id' => $hospitalId,
        'seguradora_id' => $seguradoraId,
        'status' => $status,
        'modalidade' => $modalidade,
        'sem_atualizacao' => $semAtualizacao,
    ]);
    $hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $seguradoras = $conn->query("SELECT id_seguradora, seguradora_seg FROM tb_seguradora ORDER BY seguradora_seg")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $pageError = 'Nao foi possivel carregar a fila de Home Care agora.';
    error_log('[HOME_CARE][GESTAO][ERROR] ' . $e->getMessage());
}

$totais = [
    'casos' => count($queue),
    'elegiveis' => 0,
    'implantacao' => 0,
    'sem_revisao' => 0,
];
foreach ($queue as $row) {
    if (($row['nead_elegivel_hc'] ?? 'n') === 's') {
        $totais['elegiveis']++;
    }
    if (in_array((string)($row['status_hc'] ?? ''), ['elegivel', 'implantacao'], true)) {
        $totais['implantacao']++;
    }
    $ultimaAtualizacao = !empty($row['data_atualizacao_hc']) ? strtotime((string)$row['data_atualizacao_hc']) : false;
    if (!$ultimaAtualizacao || $ultimaAtualizacao < strtotime('-5 days')) {
        $totais['sem_revisao']++;
    }
}

$selectedHospitalLabel = 'Todos os hospitais';
foreach ($hospitais as $hospital) {
    if ($hospitalId == ($hospital['id_hospital'] ?? null)) {
        $selectedHospitalLabel = (string)($hospital['nome_hosp'] ?? $selectedHospitalLabel);
        break;
    }
}

$selectedSeguradoraLabel = 'Todas as seguradoras';
foreach ($seguradoras as $seguradora) {
    if ($seguradoraId == ($seguradora['id_seguradora'] ?? null)) {
        $selectedSeguradoraLabel = (string)($seguradora['seguradora_seg'] ?? $selectedSeguradoraLabel);
        break;
    }
}

$semAtualizacaoLabel = '';
if ($semAtualizacao) {
    $semAtualizacaoLabel = $semAtualizacao . ' dias';
}

$activeFilters = [];
if ($hospitalId) {
    $activeFilters[] = 'Hospital: ' . $selectedHospitalLabel;
}
if ($seguradoraId) {
    $activeFilters[] = 'Seguradora: ' . $selectedSeguradoraLabel;
}
if ($status !== '') {
    $activeFilters[] = 'Status: ' . ($status === '__sem_status__' ? 'Sem status' : ($statusOptions[$status] ?? $status));
}
if ($modalidade !== '') {
    $activeFilters[] = 'Modalidade: ' . ($modalidadeOptions[$modalidade] ?? $modalidade);
}
if ($semAtualizacaoLabel !== '') {
    $activeFilters[] = 'Sem revisão: ' . $semAtualizacaoLabel;
}
?>

<style>
.hc-shell {
    --hc-ink: #1f3150;
    --hc-muted: #6c7b91;
    --hc-border: rgba(34, 88, 148, 0.12);
    --hc-panel: rgba(255, 255, 255, 0.8);
    --hc-blue: #2a78c2;
    --hc-blue-2: #58a0eb;
    --hc-gold: #fff2da;
    --hc-mint: #e6f7ec;
    padding: 18px 18px 28px;
    min-height: calc(100vh - 100px);
    background:
        radial-gradient(circle at top left, rgba(88, 160, 235, 0.14), transparent 30%),
        radial-gradient(circle at top right, rgba(42, 120, 194, 0.12), transparent 32%),
        linear-gradient(180deg, #f4f9fd 0%, #eef4fb 100%);
}

.hc-hero {
    display: grid;
    grid-template-columns: minmax(0, 1.7fr) minmax(280px, .9fr);
    gap: 12px;
    margin-bottom: 12px;
}

.hc-hero__main,
.hc-hero__side,
.hc-card,
.hc-kpi {
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}

.hc-hero__main {
    position: relative;
    padding: 20px 22px;
    border-radius: 22px;
    overflow: hidden;
    background: linear-gradient(135deg, rgba(26, 104, 175, 0.96), rgba(88, 160, 235, 0.9)), #236ead;
    color: #fff;
    box-shadow: 0 24px 50px rgba(32, 82, 138, 0.2);
}

.hc-hero__main::after {
    content: "";
    position: absolute;
    inset: auto -60px -80px auto;
    width: 220px;
    height: 220px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255,255,255,.24), rgba(255,255,255,0));
}

.hc-eyebrow {
    display: inline-flex;
    align-items: center;
    padding: 5px 10px;
    border-radius: 999px;
    background: rgba(255,255,255,.15);
    font-size: .66rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.hc-hero h1 {
    margin: 10px 0 6px;
    font-size: 1.72rem;
    line-height: 1.1;
    color: #fff;
}

.hc-hero p {
    margin: 0;
    max-width: 760px;
    color: rgba(255,255,255,.82);
    font-size: .9rem;
}

.hc-hero__metrics {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
    margin-top: 14px;
}

.hc-hero__metric {
    padding: 10px 12px;
    border-radius: 14px;
    background: rgba(255,255,255,.12);
    border: 1px solid rgba(255,255,255,.12);
}

.hc-hero__metric strong {
    display: block;
    font-size: 1.08rem;
    line-height: 1;
}

.hc-hero__metric span {
    display: block;
    margin-top: 4px;
    color: rgba(255,255,255,.74);
    font-size: .72rem;
}

.hc-hero__side {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 10px;
    padding: 16px;
    border-radius: 22px;
    background: var(--hc-panel);
    border: 1px solid rgba(255,255,255,.5);
    box-shadow: 0 18px 38px rgba(39, 78, 120, 0.12);
}

.hc-hero__side h2 {
    margin: 0;
    font-size: .92rem;
    color: var(--hc-ink);
}

.hc-hero__side p {
    color: var(--hc-muted);
    font-size: .78rem;
}

.hc-top-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.hc-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 36px;
    padding: 0 13px;
    border-radius: 12px;
    border: 1px solid var(--hc-border);
    background: rgba(255,255,255,.92);
    color: #29507d;
    font-weight: 700;
    text-decoration: none;
}

.hc-btn--primary {
    background: linear-gradient(135deg, var(--hc-blue), var(--hc-blue-2));
    color: #fff;
    border: none;
    box-shadow: 0 14px 24px rgba(42, 120, 194, 0.2);
}

.hc-kpis {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 12px;
}

.hc-kpi {
    position: relative;
    padding: 12px 14px 14px;
    border-radius: 18px;
    background: var(--hc-panel);
    border: 1px solid rgba(255,255,255,.45);
    box-shadow: 0 16px 30px rgba(39, 78, 120, 0.1);
    overflow: hidden;
}

.hc-kpi::before {
    content: "";
    position: absolute;
    inset: 0 0 auto 0;
    height: 4px;
    background: linear-gradient(90deg, rgba(42,120,194,.95), rgba(88,160,235,.35));
}

.hc-kpi small {
    display: block;
    margin-bottom: 6px;
    color: #728198;
    text-transform: uppercase;
    letter-spacing: .09em;
    font-size: .66rem;
    font-weight: 700;
}

.hc-kpi strong {
    display: block;
    font-size: 1.5rem;
    line-height: 1;
    color: var(--hc-ink);
}

.hc-kpi span {
    display: block;
    margin-top: 5px;
    color: var(--hc-muted);
    font-size: .72rem;
}

.hc-grid {
    display: grid;
    grid-template-columns: 320px minmax(0, 1fr);
    gap: 12px;
    align-items: start;
}

.hc-card {
    background: var(--hc-panel);
    border: 1px solid rgba(255,255,255,.5);
    border-radius: 20px;
    box-shadow: 0 18px 34px rgba(39, 78, 120, 0.1);
    overflow: hidden;
}

.hc-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 14px 16px;
    border-bottom: 1px solid rgba(34,88,148,.08);
}

.hc-card__head h2 {
    margin: 0;
    font-size: 1rem;
    color: var(--hc-ink);
}

.hc-card__head p {
    margin: 4px 0 0;
    color: var(--hc-muted);
    font-size: .74rem;
}

.hc-card__body {
    padding: 14px 16px 16px;
}

.hc-filters {
    position: sticky;
    top: 18px;
}

.hc-filter {
    margin-bottom: 10px;
}

.hc-filter label {
    display: block;
    margin-bottom: 4px;
    font-size: .72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .09em;
    color: #6a7890;
}

.hc-filter select,
.hc-filter input {
    width: 100%;
    min-height: 38px;
    border-radius: 12px;
    border: 1px solid #d7e1ef;
    padding: 8px 12px;
    font-size: .84rem;
    color: #27405d;
    background: rgba(255,255,255,.94);
    outline: none;
}

.hc-filter-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 8px;
}

.hc-filter-actions .hc-btn {
    flex: 1 1 120px;
}

.hc-filter-note {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px dashed rgba(34,88,148,.14);
    color: var(--hc-muted);
    font-size: .75rem;
}

.hc-sub {
    color: var(--hc-muted);
    font-size: .72rem;
}

.hc-active-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.hc-active-filter {
    display: inline-flex;
    align-items: center;
    padding: 5px 10px;
    border-radius: 999px;
    background: rgba(42,120,194,.08);
    color: #29507d;
    font-size: .72rem;
    font-weight: 700;
}

.hc-list {
    display: grid;
    gap: 10px;
}

.hc-case {
    padding: 12px 14px;
    border-radius: 16px;
    background: rgba(255,255,255,.86);
    border: 1px solid rgba(42,120,194,.09);
}

.hc-case__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 10px;
}

.hc-case__title h3 {
    margin: 0;
    font-size: .95rem;
    color: var(--hc-ink);
}

.hc-case__title p {
    margin: 3px 0 0;
    color: var(--hc-muted);
    font-size: .76rem;
}

.hc-case__days {
    min-width: 150px;
    padding: 8px 10px;
    border-radius: 14px;
    background: linear-gradient(135deg, rgba(42,120,194,.08), rgba(88,160,235,.14));
    text-align: right;
}

.hc-case__days strong {
    display: block;
    font-size: 1.08rem;
    line-height: 1;
    color: var(--hc-ink);
}

.hc-case__days span {
    display: block;
    margin-top: 3px;
    color: #6481a0;
    font-size: .7rem;
}

.hc-case__meta {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 8px;
}

.hc-meta {
    padding: 9px 10px;
    border-radius: 12px;
    background: rgba(243, 248, 255, 0.9);
}

.hc-meta label {
    display: block;
    margin-bottom: 5px;
    color: #70839c;
    font-size: .68rem;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.hc-meta strong,
.hc-meta div {
    color: var(--hc-ink);
    font-size: .82rem;
}

.hc-meta strong {
    font-size: .88rem;
}

.hc-case__foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-top: 10px;
}

.hc-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 9px;
    border-radius: 999px;
    font-size: .68rem;
    font-weight: 800;
}

.hc-chip--warn { background: var(--hc-gold); color: #946200; }
.hc-chip--critical { background: #ffe1e1; color: #a82b2b; }
.hc-chip--ok { background: var(--hc-mint); color: #2b7a46; }
.hc-chip--neutral { background: #eef5ff; color: #315b8d; }

.hc-empty {
    padding: 28px 16px;
    text-align: center;
    color: #7d8ba0;
}

.hc-alert {
    margin-bottom: 14px;
    padding: 14px 16px;
    border-radius: 14px;
    background: #fff0f0;
    border: 1px solid #f2c7c7;
    color: #8a2f2f;
}

@media (max-width: 1280px) {
    .hc-hero { grid-template-columns: 1fr; }
    .hc-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .hc-grid { grid-template-columns: 1fr; }
    .hc-filters { position: static; }
}

@media (max-width: 980px) {
    .hc-case__meta {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 680px) {
    .hc-shell { padding: 16px 12px 28px; }
    .hc-hero__metrics,
    .hc-case__meta {
        grid-template-columns: 1fr;
    }
    .hc-case__top,
    .hc-case__foot {
        flex-direction: column;
        align-items: stretch;
    }
    .hc-case__days {
        min-width: 0;
        text-align: left;
    }
    .hc-hero h1 { font-size: 1.45rem; }
    .hc-kpis { grid-template-columns: 1fr; }
}
</style>

<div class="hc-shell">
    <div class="hc-hero">
        <section class="hc-hero__main">
            <div class="hc-eyebrow">Cuidado domiciliar</div>
            <h1>Gestão de Home Care</h1>
            <p>Priorize elegibilidade NEAD, implantação e transição segura do cuidado domiciliar com foco nos casos em atraso.</p>
            <div class="hc-hero__metrics">
                <div class="hc-hero__metric">
                    <strong><?= number_format($totais['casos'], 0, ',', '.') ?></strong>
                    <span>casos monitorados</span>
                </div>
                <div class="hc-hero__metric">
                    <strong><?= number_format($totais['elegiveis'], 0, ',', '.') ?></strong>
                    <span>elegíveis NEAD</span>
                </div>
                <div class="hc-hero__metric">
                    <strong><?= number_format($totais['sem_revisao'], 0, ',', '.') ?></strong>
                    <span>sem revisão recente</span>
                </div>
            </div>
        </section>
        <aside class="hc-hero__side">
            <div>
                <h2>Leitura rápida da fila</h2>
                <p>Foque nos casos sem status definido e nos pacientes com implantação sem revisão há mais de 5 dias.</p>
            </div>
            <div class="hc-top-actions">
                <a class="hc-btn" href="<?= $BASE_URL ?>bi/home-care">Voltar ao BI</a>
            </div>
        </aside>
    </div>

    <?php if ($pageError !== ''): ?>
        <div class="hc-alert"><?= e($pageError) ?></div>
    <?php endif; ?>

    <div class="hc-kpis">
        <div class="hc-kpi"><small>Casos na fila</small><strong><?= number_format($totais['casos'], 0, ',', '.') ?></strong><span>Total após filtros.</span></div>
        <div class="hc-kpi"><small>Elegíveis NEAD</small><strong><?= number_format($totais['elegiveis'], 0, ',', '.') ?></strong><span>Prontos para decisão.</span></div>
        <div class="hc-kpi"><small>Em implantação</small><strong><?= number_format($totais['implantacao'], 0, ',', '.') ?></strong><span>Transição em andamento.</span></div>
        <div class="hc-kpi"><small>Sem revisão &gt; 5d</small><strong><?= number_format($totais['sem_revisao'], 0, ',', '.') ?></strong><span>Acompanhamento atrasado.</span></div>
    </div>

    <div class="hc-grid">
        <aside class="hc-card hc-filters">
            <div class="hc-card__head">
                <div>
                    <h2>Filtros</h2>
                    <p>Refine a fila.</p>
                </div>
            </div>
            <div class="hc-card__body">
                <form method="get">
                    <div class="hc-filter">
                        <label>Hospital</label>
                        <select name="hospital_id">
                            <option value="">Todos</option>
                            <?php foreach ($hospitais as $h): ?>
                                <option value="<?= (int)$h['id_hospital'] ?>" <?= $hospitalId == $h['id_hospital'] ? 'selected' : '' ?>><?= e($h['nome_hosp']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="hc-filter">
                        <label>Seguradora</label>
                        <select name="seguradora_id">
                            <option value="">Todas</option>
                            <?php foreach ($seguradoras as $s): ?>
                                <option value="<?= (int)$s['id_seguradora'] ?>" <?= $seguradoraId == $s['id_seguradora'] ? 'selected' : '' ?>><?= e($s['seguradora_seg']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="hc-filter">
                        <label>Status</label>
                        <select name="status">
                            <option value="">Todos</option>
                            <option value="__sem_status__" <?= $status === '__sem_status__' ? 'selected' : '' ?>>Sem status</option>
                            <?php foreach ($statusOptions as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="hc-filter">
                        <label>Modalidade</label>
                        <select name="modalidade">
                            <option value="">Todas</option>
                            <?php foreach ($modalidadeOptions as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $modalidade === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="hc-filter">
                        <label>Sem revisão há</label>
                        <select name="sem_atualizacao">
                            <option value="">Todos</option>
                            <option value="3" <?= (int)$semAtualizacao === 3 ? 'selected' : '' ?>>3 dias</option>
                            <option value="5" <?= (int)$semAtualizacao === 5 ? 'selected' : '' ?>>5 dias</option>
                            <option value="7" <?= (int)$semAtualizacao === 7 ? 'selected' : '' ?>>7 dias</option>
                        </select>
                    </div>
                    <div class="hc-filter-actions">
                        <button class="hc-btn hc-btn--primary" type="submit">Aplicar</button>
                        <a class="hc-btn" href="<?= $BASE_URL ?>home_care_gestao.php">Limpar</a>
                    </div>
                    <div class="hc-filter-note">
                        <strong>Visão atual:</strong> <?= e($selectedHospitalLabel) ?><br>
                        <strong>Seguradora:</strong> <?= e($selectedSeguradoraLabel) ?>
                    </div>
                </form>
            </div>
        </aside>

        <section class="hc-card">
            <div class="hc-card__head">
                <div>
                    <h2>Fila de casos</h2>
                    <p><?= number_format(count($queue), 0, ',', '.') ?> caso(s) na visão atual.</p>
                </div>
                <?php if ($activeFilters): ?>
                    <div class="hc-active-filters">
                        <?php foreach ($activeFilters as $filterLabel): ?>
                            <span class="hc-active-filter"><?= e($filterLabel) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="hc-card__body">
                <?php if (!$queue): ?>
                    <div class="hc-empty">Nenhum caso de Home Care encontrado para os filtros atuais.</div>
                <?php else: ?>
                    <div class="hc-list">
                        <?php foreach ($queue as $row): ?>
                            <?php
                            $ultimaAtualizacaoTs = !empty($row['data_atualizacao_hc']) ? strtotime((string)$row['data_atualizacao_hc']) : false;
                            $reviewChip = !$ultimaAtualizacaoTs || $ultimaAtualizacaoTs < strtotime('-5 days') ? 'hc-chip hc-chip--critical' : 'hc-chip hc-chip--ok';
                            $reviewLabel = !$ultimaAtualizacaoTs || $ultimaAtualizacaoTs < strtotime('-5 days') ? 'Revisão atrasada' : 'Atualizado';
                            $statusChip = !empty($row['status_hc']) ? 'hc-chip hc-chip--neutral' : 'hc-chip hc-chip--warn';
                            ?>
                            <article class="hc-case">
                                <div class="hc-case__top">
                                    <div class="hc-case__title">
                                        <h3><?= e($row['nome_pac'] ?? 'Sem nome') ?></h3>
                                        <p>
                                            Seguradora: <?= e($row['seguradora_seg'] ?? 'Sem seguradora') ?>
                                            <?php if (!empty($row['data_intern_int'])): ?>
                                                · Internação em <?= e(date('d/m/Y', strtotime((string)$row['data_intern_int']))) ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="hc-case__days">
                                        <strong><?= number_format((int)($row['diarias'] ?? 0), 0, ',', '.') ?>d</strong>
                                        <span>Dias internado</span>
                                    </div>
                                </div>

                                <div class="hc-case__meta">
                                    <div class="hc-meta">
                                        <label>Hospital</label>
                                        <strong><?= e($row['nome_hosp'] ?? 'Sem hospital') ?></strong>
                                        <?php if (($row['sinalizado_hc'] ?? 'n') === 's'): ?>
                                            <div class="hc-sub" style="margin-top:6px;color:#2a78c2;font-weight:700;">Sinalizado em gestão</div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="hc-meta">
                                        <label>NEAD</label>
                                        <div class="hc-chip <?= ($row['nead_elegivel_hc'] ?? 'n') === 's' ? 'hc-chip--ok' : 'hc-chip--warn' ?>">
                                            <?= ($row['nead_elegivel_hc'] ?? 'n') === 's' ? 'Elegível' : 'Pendente / não elegível' ?>
                                        </div>
                                        <div class="hc-sub" style="margin-top:6px;">Score: <?= number_format((int)($row['nead_pontuacao_hc'] ?? 0), 0, ',', '.') ?></div>
                                    </div>
                                    <div class="hc-meta">
                                        <label>Status</label>
                                        <div class="<?= $statusChip ?>"><?= e($statusOptions[$row['status_hc']] ?? 'Sem status') ?></div>
                                        <div class="hc-sub" style="margin-top:6px;"><?= e($row['modalidade_aprovada_hc'] ?? $modalidadeOptions[$row['modalidade_sugerida_hc']] ?? 'Sem modalidade') ?></div>
                                    </div>
                                    <div class="hc-meta">
                                        <label>Implantação</label>
                                        <div class="<?= $reviewChip ?>"><?= e($reviewLabel) ?></div>
                                        <div class="hc-sub" style="margin-top:6px;">
                                            <?= !empty($row['previsao_implantacao_hc']) ? 'Prev. ' . e(date('d/m/Y', strtotime((string)$row['previsao_implantacao_hc']))) : 'Sem previsão' ?>
                                        </div>
                                    </div>
                                    <div class="hc-meta">
                                        <label>Barreira</label>
                                        <div><?= e($barreiraOptions[$row['barreira_principal_hc']] ?? ($row['barreira_principal_hc'] ?? '-')) ?></div>
                                        <?php if (!empty($row['fornecedor_hc'])): ?>
                                            <div class="hc-sub" style="margin-top:6px;">Fornecedor: <?= e($row['fornecedor_hc']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="hc-case__foot">
                                    <div class="hc-sub">Decisão assistencial, barreiras e implantação.</div>
                                    <a class="hc-btn hc-btn--primary" href="<?= $BASE_URL ?>home_care_avaliacao.php?id_internacao=<?= (int)$row['id_internacao'] ?>">Avaliar caso</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
