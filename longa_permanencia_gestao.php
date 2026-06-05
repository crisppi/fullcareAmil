<?php
include_once("check_logado.php");
require_once("globals.php");
require_once("db.php");
require_once("templates/header.php");
require_once("dao/longaPermanenciaDao.php");

if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$dao = new LongaPermanenciaDAO($conn, $BASE_URL);
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$seguradoraId = filter_input(INPUT_GET, 'seguradora_id', FILTER_VALIDATE_INT) ?: null;
$status = trim((string)(filter_input(INPUT_GET, 'status') ?? ''));
$escalonamento = trim((string)(filter_input(INPUT_GET, 'escalonamento') ?? ''));
$semAtualizacao = filter_input(INPUT_GET, 'sem_atualizacao', FILTER_VALIDATE_INT) ?: null;
$queue = [];
$statusOptions = $dao->getStatusOptions();
$hospitais = [];
$seguradoras = [];
$pageError = '';

try {
    $queue = $dao->fetchQueue([
        'hospital_id' => $hospitalId,
        'seguradora_id' => $seguradoraId,
        'status' => $status,
        'escalonamento' => $escalonamento,
        'sem_atualizacao' => $semAtualizacao,
    ]);

    $hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $seguradoras = $conn->query("SELECT id_seguradora, seguradora_seg FROM tb_seguradora ORDER BY seguradora_seg")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $pageError = 'Nao foi possivel carregar a fila de longa permanencia agora.';
    error_log('[LONGA_PERMANENCIA][GESTAO][ERROR] ' . $e->getMessage());
}

$totais = [
    'casos' => count($queue),
    'sem_status' => 0,
    'escalonados' => 0,
    'sem_revisao' => 0,
];
foreach ($queue as $row) {
    if (empty($row['status_lp'])) {
        $totais['sem_status']++;
    }
    if (($row['necessita_escalonamento_lp'] ?? 'n') === 's') {
        $totais['escalonados']++;
    }
    $ultimaAtualizacao = !empty($row['data_atualizacao_lp']) ? strtotime((string)$row['data_atualizacao_lp']) : false;
    if (!$ultimaAtualizacao || $ultimaAtualizacao < strtotime('-7 days')) {
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
if ($escalonamento !== '') {
    $activeFilters[] = 'Escalonamento: ' . ($escalonamento === 's' ? 'Necessita escalonamento' : 'Sem escalonamento');
}
if ($semAtualizacaoLabel !== '') {
    $activeFilters[] = 'Sem revisão: ' . $semAtualizacaoLabel;
}
?>

<style>
.lp-shell {
    --lp-ink: #2f2042;
    --lp-muted: #726884;
    --lp-border: rgba(92, 45, 112, 0.12);
    --lp-panel: rgba(255, 255, 255, 0.78);
    --lp-purple: #6b2d73;
    --lp-purple-2: #8d4ca0;
    --lp-rose: #ffe4ea;
    --lp-gold: #fff1d1;
    --lp-mint: #e8f8ef;
    padding: 18px 18px 28px;
    min-height: calc(100vh - 100px);
    background:
        radial-gradient(circle at top left, rgba(166, 115, 190, 0.16), transparent 30%),
        radial-gradient(circle at top right, rgba(127, 177, 233, 0.14), transparent 32%),
        linear-gradient(180deg, #f6f1fb 0%, #eff4f8 100%);
}

.lp-hero {
    display: grid;
    grid-template-columns: minmax(0, 1.7fr) minmax(280px, .9fr);
    gap: 12px;
    margin-bottom: 12px;
}

.lp-hero__main,
.lp-hero__side,
.lp-card,
.lp-kpi {
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
}

.lp-hero__main {
    position: relative;
    padding: 20px 22px;
    border-radius: 22px;
    overflow: hidden;
    background:
        linear-gradient(135deg, rgba(78, 28, 100, 0.96), rgba(127, 88, 168, 0.9)),
        #5d256a;
    color: #fff;
    box-shadow: 0 28px 60px rgba(58, 22, 80, 0.24);
}

.lp-hero__main::after {
    content: "";
    position: absolute;
    inset: auto -60px -80px auto;
    width: 220px;
    height: 220px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255,255,255,.22), rgba(255,255,255,0));
}

.lp-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 5px 10px;
    border-radius: 999px;
    background: rgba(255,255,255,.14);
    font-size: .66rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.lp-hero h1 {
    margin: 10px 0 6px;
    font-size: 1.72rem;
    line-height: 1.1;
    color: #fff;
}

.lp-hero p {
    margin: 0;
    max-width: 760px;
    color: rgba(255,255,255,.82);
    font-size: .9rem;
}

.lp-hero__metrics {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
    margin-top: 14px;
}

.lp-hero__metric {
    padding: 10px 12px;
    border-radius: 14px;
    background: rgba(255,255,255,.12);
    border: 1px solid rgba(255,255,255,.12);
}

.lp-hero__metric strong {
    display: block;
    font-size: 1.08rem;
    line-height: 1;
}

.lp-hero__metric span {
    display: block;
    margin-top: 4px;
    color: rgba(255,255,255,.72);
    font-size: .72rem;
}

.lp-hero__side {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 10px;
    padding: 16px;
    border-radius: 22px;
    background: var(--lp-panel);
    border: 1px solid rgba(255,255,255,.5);
    box-shadow: 0 22px 48px rgba(49, 34, 68, 0.12);
}

.lp-hero__side h2 {
    margin: 0;
    font-size: .92rem;
    color: var(--lp-ink);
}

.lp-hero__side p {
    color: var(--lp-muted);
    font-size: .78rem;
}

.lp-top-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.lp-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 36px;
    padding: 0 13px;
    border-radius: 12px;
    border: 1px solid var(--lp-border);
    background: rgba(255,255,255,.92);
    color: #4e2a62;
    font-weight: 700;
    text-decoration: none;
    transition: transform .16s ease, box-shadow .16s ease, background .16s ease;
}

.lp-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 12px 24px rgba(56, 34, 76, 0.12);
    color: #4e2a62;
}

.lp-btn--primary {
    border: none;
    background: linear-gradient(135deg, var(--lp-purple), var(--lp-purple-2));
    color: #fff;
    box-shadow: 0 16px 28px rgba(107, 45, 115, 0.22);
}

.lp-kpis {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 12px;
}

.lp-kpi {
    position: relative;
    padding: 12px 14px 14px;
    border-radius: 18px;
    background: var(--lp-panel);
    border: 1px solid rgba(255,255,255,.45);
    box-shadow: 0 18px 38px rgba(53, 34, 73, 0.1);
    overflow: hidden;
}

.lp-kpi::before {
    content: "";
    position: absolute;
    inset: 0 0 auto 0;
    height: 4px;
    background: linear-gradient(90deg, rgba(107,45,115,.95), rgba(141,76,160,.35));
}

.lp-kpi small {
    display: block;
    margin-bottom: 6px;
    color: #7f748f;
    text-transform: uppercase;
    letter-spacing: .11em;
    font-size: .66rem;
    font-weight: 700;
}

.lp-kpi strong {
    display: block;
    font-size: 1.5rem;
    line-height: 1;
    color: var(--lp-ink);
}

.lp-kpi span {
    display: block;
    margin-top: 5px;
    color: var(--lp-muted);
    font-size: .72rem;
}

.lp-grid {
    display: grid;
    grid-template-columns: 320px minmax(0, 1fr);
    gap: 12px;
    align-items: start;
}

.lp-card {
    background: var(--lp-panel);
    border: 1px solid rgba(255,255,255,.5);
    border-radius: 20px;
    box-shadow: 0 22px 44px rgba(49, 34, 68, 0.11);
    overflow: hidden;
}

.lp-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    border-bottom: 1px solid rgba(99, 71, 122, 0.08);
}

.lp-card__head h2 {
    margin: 0;
    font-size: 1rem;
    color: var(--lp-ink);
}

.lp-card__head p {
    margin: 4px 0 0;
    color: var(--lp-muted);
    font-size: .74rem;
}

.lp-card__body {
    padding: 14px 16px 16px;
}

.lp-filters {
    position: sticky;
    top: 18px;
}

.lp-filter {
    margin-bottom: 10px;
}

.lp-filter label {
    display: block;
    margin-bottom: 4px;
    font-size: .72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .11em;
    color: #6c617e;
}

.lp-filter select,
.lp-filter input {
    width: 100%;
    min-height: 38px;
    border-radius: 12px;
    border: 1px solid #d9d1e5;
    padding: 8px 12px;
    font-size: .84rem;
    color: #342944;
    background: rgba(255,255,255,.94);
    outline: none;
}

.lp-filter select:focus,
.lp-filter input:focus {
    border-color: rgba(107,45,115,.45);
    box-shadow: 0 0 0 4px rgba(107,45,115,.08);
}

.lp-filter-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 8px;
}

.lp-filter-actions .lp-btn {
    flex: 1 1 120px;
}

.lp-filter-note {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px dashed rgba(99, 71, 122, 0.16);
    color: var(--lp-muted);
    font-size: .75rem;
}

.lp-sub {
    color: var(--lp-muted);
    font-size: .72rem;
}

.lp-active-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.lp-active-filter {
    display: inline-flex;
    align-items: center;
    padding: 5px 10px;
    border-radius: 999px;
    background: rgba(107,45,115,.08);
    color: #5b376d;
    font-size: .72rem;
    font-weight: 700;
}

.lp-list {
    display: grid;
    gap: 10px;
}

.lp-case {
    padding: 12px 14px;
    border-radius: 16px;
    background: rgba(255,255,255,.86);
    border: 1px solid rgba(95, 57, 118, 0.1);
    box-shadow: inset 0 1px 0 rgba(255,255,255,.6);
}

.lp-case__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 10px;
}

.lp-case__title h3 {
    margin: 0;
    font-size: .95rem;
    color: var(--lp-ink);
}

.lp-case__title p {
    margin: 3px 0 0;
    color: var(--lp-muted);
    font-size: .76rem;
}

.lp-case__days {
    min-width: 150px;
    padding: 8px 10px;
    border-radius: 14px;
    background: linear-gradient(135deg, rgba(107,45,115,.08), rgba(187,145,203,.14));
    text-align: right;
}

.lp-case__days strong {
    display: block;
    font-size: 1.08rem;
    line-height: 1;
    color: var(--lp-ink);
}

.lp-case__days span {
    display: block;
    margin-top: 3px;
    color: #705f80;
    font-size: .7rem;
}

.lp-case__meta {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 8px;
}

.lp-meta {
    padding: 9px 10px;
    border-radius: 12px;
    background: rgba(245, 241, 251, 0.88);
}

.lp-meta label {
    display: block;
    margin-bottom: 5px;
    color: #796e8b;
    font-size: .68rem;
    font-weight: 800;
    letter-spacing: .09em;
    text-transform: uppercase;
}

.lp-meta strong,
.lp-meta div {
    color: var(--lp-ink);
    font-size: .82rem;
}

.lp-meta strong {
    font-size: .88rem;
}

.lp-case__foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-top: 10px;
}

.lp-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 9px;
    border-radius: 999px;
    font-size: .68rem;
    font-weight: 800;
}

.lp-chip--warn { background: var(--lp-gold); color: #946200; }
.lp-chip--critical { background: #ffe2e5; color: #a23247; }
.lp-chip--ok { background: var(--lp-mint); color: #256b43; }
.lp-chip--neutral { background: #eee6f7; color: #65437b; }

.lp-empty {
    padding: 36px 18px;
    text-align: center;
    color: var(--lp-muted);
}

.lp-alert {
    margin-bottom: 16px;
    padding: 15px 16px;
    border-radius: 16px;
    background: #fff0f0;
    border: 1px solid #f2c7c7;
    color: #8a2f2f;
    box-shadow: 0 10px 24px rgba(138,47,47,.08);
}

@media (max-width: 1280px) {
    .lp-hero { grid-template-columns: 1fr; }
    .lp-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .lp-grid { grid-template-columns: 1fr; }
    .lp-filters { position: static; }
}

@media (max-width: 860px) {
    .lp-hero__metrics,
    .lp-case__meta {
        grid-template-columns: 1fr;
    }

    .lp-case__top,
    .lp-case__foot {
        flex-direction: column;
        align-items: stretch;
    }

    .lp-case__days {
        min-width: 0;
        text-align: left;
    }
}

@media (max-width: 680px) {
    .lp-shell { padding: 16px 12px 30px; }
    .lp-hero__main,
    .lp-hero__side,
    .lp-card,
    .lp-kpi,
    .lp-case {
        border-radius: 20px;
    }
    .lp-hero h1 { font-size: 1.45rem; }
    .lp-kpis { grid-template-columns: 1fr; }
}
</style>

<div class="lp-shell">
    <div class="fc-module-header fc-module-header--cuidado">
        <div class="fc-module-header__copy">
            <p class="fc-module-header__kicker">Cuidado Continuado</p>
            <h1 class="fc-module-header__title">Gestão de Longa Permanência</h1>
            <p class="fc-module-header__subtitle">Priorize revisão clínica, barreiras de alta e plano de ação dos casos com maior permanência excedente.</p>
        </div>
        <div class="fc-module-header__actions">
            <span class="badge rounded-pill bg-light text-primary"><?= number_format($totais['casos'], 0, ',', '.') ?> casos</span>
            <span class="badge rounded-pill bg-light text-primary"><?= number_format($totais['sem_revisao'], 0, ',', '.') ?> sem revisão</span>
            <a class="btn btn-light" href="<?= $BASE_URL ?>bi/longa-permanencia">Voltar ao BI</a>
        </div>
    </div>

    <?php if ($pageError !== ''): ?>
        <div class="lp-alert"><?= e($pageError) ?></div>
    <?php endif; ?>

    <div class="lp-kpis">
        <div class="lp-kpi">
            <small>Casos na fila</small>
            <strong><?= number_format($totais['casos'], 0, ',', '.') ?></strong>
            <span>Total após filtros.</span>
        </div>
        <div class="lp-kpi">
            <small>Sem status</small>
            <strong><?= number_format($totais['sem_status'], 0, ',', '.') ?></strong>
            <span>Ainda sem enquadramento.</span>
        </div>
        <div class="lp-kpi">
            <small>Escalonados</small>
            <strong><?= number_format($totais['escalonados'], 0, ',', '.') ?></strong>
            <span>Pedem apoio imediato.</span>
        </div>
        <div class="lp-kpi">
            <small>Sem revisão &gt; 7d</small>
            <strong><?= number_format($totais['sem_revisao'], 0, ',', '.') ?></strong>
            <span>Acompanhamento atrasado.</span>
        </div>
    </div>

    <div class="lp-grid">
        <aside class="lp-card lp-filters">
            <div class="lp-card__head">
                <div>
                    <h2>Filtros</h2>
                    <p>Refine a fila.</p>
                </div>
            </div>
            <div class="lp-card__body">
                <form method="get">
                    <div class="lp-filter">
                        <label>Hospital</label>
                        <select name="hospital_id">
                            <option value="">Todos</option>
                            <?php foreach ($hospitais as $h): ?>
                                <option value="<?= (int)$h['id_hospital'] ?>" <?= $hospitalId == $h['id_hospital'] ? 'selected' : '' ?>><?= e($h['nome_hosp']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="lp-filter">
                        <label>Seguradora</label>
                        <select name="seguradora_id">
                            <option value="">Todas</option>
                            <?php foreach ($seguradoras as $s): ?>
                                <option value="<?= (int)$s['id_seguradora'] ?>" <?= $seguradoraId == $s['id_seguradora'] ? 'selected' : '' ?>><?= e($s['seguradora_seg']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="lp-filter">
                        <label>Status</label>
                        <select name="status">
                            <option value="">Todos</option>
                            <option value="__sem_status__" <?= $status === '__sem_status__' ? 'selected' : '' ?>>Sem status</option>
                            <?php foreach ($statusOptions as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="lp-filter">
                        <label>Escalonamento</label>
                        <select name="escalonamento">
                            <option value="">Todos</option>
                            <option value="s" <?= $escalonamento === 's' ? 'selected' : '' ?>>Necessita escalonamento</option>
                            <option value="n" <?= $escalonamento === 'n' ? 'selected' : '' ?>>Sem escalonamento</option>
                        </select>
                    </div>
                    <div class="lp-filter">
                        <label>Sem revisão há</label>
                        <select name="sem_atualizacao">
                            <option value="">Todos</option>
                            <option value="7" <?= (int)$semAtualizacao === 7 ? 'selected' : '' ?>>7 dias</option>
                            <option value="15" <?= (int)$semAtualizacao === 15 ? 'selected' : '' ?>>15 dias</option>
                            <option value="30" <?= (int)$semAtualizacao === 30 ? 'selected' : '' ?>>30 dias</option>
                        </select>
                    </div>
                    <div class="lp-filter-actions">
                        <button class="lp-btn lp-btn--primary" type="submit">Aplicar</button>
                        <a class="lp-btn" href="<?= $BASE_URL ?>longa_permanencia_gestao.php">Limpar</a>
                    </div>
                    <div class="lp-filter-note">
                        <strong>Visão atual:</strong> <?= e($selectedHospitalLabel) ?><br>
                        <strong>Seguradora:</strong> <?= e($selectedSeguradoraLabel) ?>
                    </div>
                </form>
            </div>
        </aside>

        <section class="lp-card">
            <div class="lp-card__head">
                <div>
                    <h2>Fila de casos</h2>
                    <p><?= number_format(count($queue), 0, ',', '.') ?> caso(s) na visão atual.</p>
                </div>
                <?php if ($activeFilters): ?>
                    <div class="lp-active-filters">
                        <?php foreach ($activeFilters as $filterLabel): ?>
                            <span class="lp-active-filter"><?= e($filterLabel) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="lp-card__body">
                <?php if (!$queue): ?>
                    <div class="lp-empty">Nenhum caso de longa permanência encontrado para os filtros atuais.</div>
                <?php else: ?>
                    <div class="lp-list">
                        <?php foreach ($queue as $row): ?>
                            <?php
                            $excesso = max(0, (int)$row['diarias'] - (int)$row['limiar']);
                            $ultimaAtualizacaoTs = !empty($row['data_atualizacao_lp']) ? strtotime((string)$row['data_atualizacao_lp']) : false;
                            $reviewChip = !$ultimaAtualizacaoTs || $ultimaAtualizacaoTs < strtotime('-7 days')
                                ? 'lp-chip lp-chip--critical'
                                : 'lp-chip lp-chip--ok';
                            $reviewLabel = !$ultimaAtualizacaoTs || $ultimaAtualizacaoTs < strtotime('-7 days')
                                ? 'Revisão atrasada'
                                : 'Atualizado';
                            ?>
                            <article class="lp-case">
                                <div class="lp-case__top">
                                    <div class="lp-case__title">
                                        <h3><?= e($row['nome_pac'] ?? 'Sem nome') ?></h3>
                                        <p>
                                            Seguradora: <?= e($row['seguradora_seg'] ?? 'Sem seguradora') ?>
                                            <?php if (!empty($row['data_intern_int'])): ?>
                                                · Internação em <?= e(date('d/m/Y', strtotime((string)$row['data_intern_int']))) ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="lp-case__days">
                                        <strong><?= number_format((int)$row['diarias'], 0, ',', '.') ?>d</strong>
                                        <span>Limiar <?= number_format((int)$row['limiar'], 0, ',', '.') ?>d · excesso <?= number_format($excesso, 0, ',', '.') ?>d</span>
                                    </div>
                                </div>

                                <div class="lp-case__meta">
                                    <div class="lp-meta">
                                        <label>Hospital</label>
                                        <strong><?= e($row['nome_hosp'] ?? 'Sem hospital') ?></strong>
                                    </div>
                                    <div class="lp-meta">
                                        <label>Status atual</label>
                                        <div class="lp-chip <?= !empty($row['status_lp']) ? 'lp-chip--neutral' : 'lp-chip--warn' ?>">
                                            <?= e($statusOptions[$row['status_lp']] ?? 'Sem status') ?>
                                        </div>
                                        <?php if (!empty($row['motivo_principal_lp'])): ?>
                                            <div class="lp-sub" style="margin-top:8px;"><?= e($row['motivo_principal_lp']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="lp-meta">
                                        <label>Próxima revisão</label>
                                        <div class="<?= $reviewChip ?>"><?= e($reviewLabel) ?></div>
                                        <div class="lp-sub" style="margin-top:8px;">
                                            <?= !empty($row['proxima_revisao_lp']) ? e(date('d/m/Y', strtotime((string)$row['proxima_revisao_lp']))) : 'Sem data definida' ?>
                                        </div>
                                    </div>
                                    <div class="lp-meta">
                                        <label>Responsável</label>
                                        <div><?= e($row['responsavel_lp'] ?? '-') ?></div>
                                        <?php if (($row['necessita_escalonamento_lp'] ?? 'n') === 's'): ?>
                                            <div class="lp-sub" style="margin-top:8px;color:#a23247;font-weight:700;">Necessita escalonamento</div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="lp-case__foot">
                                    <div class="lp-sub">Ação clínica e plano de alta.</div>
                                    <a class="lp-btn lp-btn--primary" href="<?= $BASE_URL ?>longa_permanencia_editar.php?id_internacao=<?= (int)$row['id_internacao'] ?>">Gerir caso</a>
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
