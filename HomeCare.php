<?php
include_once("check_logado.php");
require_once("templates/header.php");
require_once("dao/homeCareDao.php");

if (!isset($conn) || !($conn instanceof PDO)) {
    die("Conexao invalida.");
}

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$seguradoraId = filter_input(INPUT_GET, 'seguradora_id', FILTER_VALIDATE_INT) ?: null;
$status = trim((string)(filter_input(INPUT_GET, 'status') ?? ''));
$modalidade = trim((string)(filter_input(INPUT_GET, 'modalidade') ?? ''));
$semAtualizacao = filter_input(INPUT_GET, 'sem_atualizacao', FILTER_VALIDATE_INT) ?: null;

$dao = new HomeCareDAO($conn, $BASE_URL);
$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);
$seguradoras = $conn->query("SELECT id_seguradora, seguradora_seg FROM tb_seguradora ORDER BY seguradora_seg")
    ->fetchAll(PDO::FETCH_ASSOC);
$statusOptions = $dao->getStatusOptions();
$modalidadeOptions = $dao->getModalidadeOptions();
$queue = $dao->fetchQueue([
    'hospital_id' => $hospitalId,
    'seguradora_id' => $seguradoraId,
    'status' => $status,
    'modalidade' => $modalidade,
    'sem_atualizacao' => $semAtualizacao,
]);

$totalInternações = count($queue);
$totalDiárias = 0;
$maiorPermanencia = 0;
foreach ($queue as $item) {
    $diarias = (int)($item['diarias'] ?? 0);
    $totalDiárias += $diarias;
    if ($diarias > $maiorPermanencia) {
        $maiorPermanencia = $diarias;
    }
}
$mp = $totalInternações > 0 ? round($totalDiárias / $totalInternações, 1) : 0.0;
$totalEventos = $totalInternações;

$hospitalTotals = [];
foreach ($queue as $item) {
    $label = trim((string)($item['nome_hosp'] ?? ''));
    $label = $label !== '' ? $label : 'Sem informacoes';
    $hospitalTotals[$label] = ($hospitalTotals[$label] ?? 0) + 1;
}
arsort($hospitalTotals);
$hospRows = [];
foreach (array_slice($hospitalTotals, 0, 12, true) as $label => $total) {
    $hospRows[] = ['label' => $label, 'total' => $total];
}

$statusTotals = [];
foreach ($queue as $item) {
    $key = trim((string)($item['status_hc'] ?? ''));
    $key = $key !== '' ? $key : '__sem_status__';
    $statusTotals[$key] = ($statusTotals[$key] ?? 0) + 1;
}
arsort($statusTotals);
$tipoRows = [];
foreach (array_slice($statusTotals, 0, 6, true) as $key => $total) {
    $tipoRows[] = [
        'label' => $key === '__sem_status__' ? 'Sem status' : ($statusOptions[$key] ?? $key),
        'total' => $total,
    ];
}

$rowsTable = array_slice($queue, 0, 60);

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
if ($semAtualizacao) {
    $activeFilters[] = 'Sem revisão: ' . $semAtualizacao . ' dias';
}
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260509-filter-icons"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>
<style>
.hc-bi-shell {
    --hcbi-border: rgba(255,255,255,.2);
    --hcbi-soft: rgba(255,255,255,.08);
    --hcbi-text: rgba(255,255,255,.92);
    --hcbi-text-soft: rgba(255,255,255,.76);
    --hcbi-ink: #f4f8ff;
    --hcbi-cyan: #7fe4ff;
    --hcbi-mint: #8df0c7;
    --hcbi-amber: #ffd48a;
    --hcbi-rose: #ff9fc0;
}

.hc-bi-hero {
    display: grid;
    grid-template-columns: minmax(0, 1.7fr) minmax(280px, .95fr);
    gap: 14px;
    margin-bottom: 14px;
}

.hc-bi-hero-main {
    padding: 18px 20px;
    border-radius: 20px;
    background: linear-gradient(135deg, rgba(22,78,132,.45), rgba(74,133,197,.28));
    border: 1px solid var(--hcbi-border);
}

.hc-bi-eyebrow {
    display: inline-flex;
    align-items: center;
    padding: 5px 10px;
    border-radius: 999px;
    background: rgba(255,255,255,.12);
    color: rgba(255,255,255,.82);
    font-size: .66rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.hc-bi-hero-main .bi-title {
    margin: 10px 0 6px;
    color: var(--hcbi-ink);
}

.hc-bi-copy {
    margin: 0;
    max-width: 760px;
    color: rgba(255,255,255,.82);
    font-size: .92rem;
}

.hc-bi-mini-kpis {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
    margin-top: 12px;
}

.hc-bi-mini-kpi {
    padding: 10px 12px;
    border-radius: 14px;
    background: rgba(255,255,255,.1);
    border: 1px solid rgba(255,255,255,.1);
}

.hc-bi-mini-kpi:nth-child(1) {
    background: linear-gradient(135deg, rgba(119, 232, 255, .18), rgba(255,255,255,.08));
}

.hc-bi-mini-kpi:nth-child(2) {
    background: linear-gradient(135deg, rgba(255, 212, 138, .18), rgba(255,255,255,.08));
}

.hc-bi-mini-kpi:nth-child(3) {
    background: linear-gradient(135deg, rgba(255, 159, 192, .18), rgba(255,255,255,.08));
}

.hc-bi-mini-kpi strong {
    display: block;
    font-size: 1.05rem;
    line-height: 1;
    color: #fff;
}

.hc-bi-mini-kpi span {
    display: block;
    margin-top: 4px;
    color: rgba(255,255,255,.74);
    font-size: .72rem;
}

.hc-bi-hero-side {
    padding: 16px;
    border-radius: 20px;
    background: linear-gradient(135deg, rgba(255,255,255,.12), rgba(255,255,255,.06));
    border: 1px solid var(--hcbi-border);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 10px;
}

.hc-bi-hero-side h2 {
    margin: 0;
    font-size: .95rem;
    color: #fff;
}

.hc-bi-hero-side p {
    margin: 0;
    color: rgba(255,255,255,.74);
    font-size: .78rem;
}

.hc-bi-filters {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 12px;
    align-items: end;
}

.hc-bi-filters .bi-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.hc-bi-btn-ghost {
    background: rgba(255,255,255,.12);
    border: 1px solid rgba(255,255,255,.16);
}

.hc-bi-topline {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
}

.hc-bi-topline h3 {
    margin: 0;
    color: var(--hcbi-ink);
}

.hc-bi-active-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.hc-bi-filter-pill {
    display: inline-flex;
    align-items: center;
    padding: 5px 10px;
    border-radius: 999px;
    background: rgba(255,255,255,.1);
    color: rgba(255,255,255,.84);
    font-size: .72rem;
    font-weight: 700;
}

.hc-bi-kpis {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
    margin-top: 14px;
}

.hc-bi-kpi {
    min-height: 0;
    padding: 14px 16px;
    border-radius: 18px;
    background: linear-gradient(135deg, rgba(255,255,255,.14), rgba(255,255,255,.07));
    border: 1px solid var(--hcbi-border);
    box-shadow: none;
}

.hc-bi-kpi small {
    display: block;
    margin-bottom: 6px;
    font-size: .66rem;
    letter-spacing: .09em;
    text-transform: uppercase;
    color: rgba(255,255,255,.68);
}

.hc-bi-kpi strong {
    display: block;
    font-size: 1.55rem;
    line-height: 1;
    color: #fff;
}

.hc-bi-kpi span {
    display: block;
    margin-top: 5px;
    color: rgba(255,255,255,.72);
    font-size: .72rem;
}

.hc-bi-kpi:nth-child(1) {
    background: linear-gradient(135deg, rgba(111, 204, 255, .2), rgba(255,255,255,.06));
}

.hc-bi-kpi:nth-child(2) {
    background: linear-gradient(135deg, rgba(141, 240, 199, .18), rgba(255,255,255,.06));
}

.hc-bi-kpi:nth-child(3) {
    background: linear-gradient(135deg, rgba(255, 212, 138, .18), rgba(255,255,255,.06));
}

.hc-bi-kpi:nth-child(4) {
    background: linear-gradient(135deg, rgba(255, 159, 192, .18), rgba(255,255,255,.06));
}

.hc-bi-grid {
    display: grid;
    grid-template-columns: 1.05fr 1.05fr .95fr;
    gap: 12px;
    margin-top: 14px;
}

.hc-bi-card {
    padding: 14px 16px;
    border-radius: 18px;
    background: linear-gradient(135deg, rgba(255,255,255,.11), rgba(255,255,255,.06));
    border: 1px solid var(--hcbi-border);
}

.hc-bi-card h3 {
    margin: 0 0 10px;
    color: var(--hcbi-ink);
    font-size: 1.05rem;
}

.hc-bi-card--hospital {
    background: linear-gradient(135deg, rgba(127, 228, 255, .14), rgba(255,255,255,.06));
}

.hc-bi-card--event {
    background: linear-gradient(135deg, rgba(141, 240, 199, .14), rgba(255,255,255,.06));
}

.hc-bi-list {
    display: grid;
    gap: 8px;
}

.hc-bi-list-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 12px;
    background: rgba(255,255,255,.08);
    color: rgba(255,255,255,.88);
}

.hc-bi-card--hospital .hc-bi-list-item {
    background: linear-gradient(135deg, rgba(127, 228, 255, .14), rgba(255,255,255,.05));
}

.hc-bi-card--event .hc-bi-list-item {
    background: linear-gradient(135deg, rgba(141, 240, 199, .14), rgba(255,255,255,.05));
}

.hc-bi-list-item span:last-child {
    min-width: 28px;
    text-align: right;
    font-weight: 700;
    color: #fff;
}

.hc-bi-focus {
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-height: 100%;
    padding: 18px;
    border-radius: 18px;
    background: linear-gradient(135deg, rgba(255, 212, 138, .16), rgba(255, 159, 192, .12), rgba(255,255,255,.08));
    border: 1px solid var(--hcbi-border);
    text-align: center;
    position: relative;
    overflow: hidden;
}

.hc-bi-focus::after {
    content: "";
    position: absolute;
    inset: auto -40px -50px auto;
    width: 150px;
    height: 150px;
    border-radius: 999px;
    background: radial-gradient(circle, rgba(255,255,255,.22), rgba(255,255,255,0) 70%);
}

.hc-bi-focus small {
    display: block;
    margin-bottom: 8px;
    color: rgba(255,255,255,.7);
    text-transform: uppercase;
    letter-spacing: .09em;
    font-size: .68rem;
}

.hc-bi-focus strong {
    display: block;
    font-size: 2rem;
    line-height: 1;
    color: #fff;
    position: relative;
    z-index: 1;
}

.hc-bi-focus span {
    margin-top: 8px;
    color: rgba(255,255,255,.74);
    font-size: .76rem;
    position: relative;
    z-index: 1;
}

.hc-bi-table-wrap {
    margin-top: 14px;
}

.hc-bi-table-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 10px;
}

.hc-bi-table-head h3,
.hc-bi-table-head p {
    margin: 0;
}

.hc-bi-table-head h3 {
    color: var(--hcbi-ink);
}

.hc-bi-table-head p {
    color: rgba(255,255,255,.68) !important;
}

@media (max-width: 1300px) {
    .hc-bi-filters {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    .hc-bi-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 980px) {
    .hc-bi-hero,
    .hc-bi-kpis {
        grid-template-columns: 1fr;
    }
    .hc-bi-mini-kpis {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 720px) {
    .hc-bi-filters {
        grid-template-columns: 1fr;
    }
    .hc-bi-topline,
    .hc-bi-table-head {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<div class="bi-wrapper bi-theme hc-bi-shell">
    <div class="hc-bi-hero">
        <section class="hc-bi-hero-main">
            <div class="hc-bi-eyebrow">Cuidado domiciliar</div>
            <h1 class="bi-title">Dashboard Home Care</h1>
            <p class="hc-bi-copy">Acompanhe volume, permanência, elegibilidade e concentração da fila com acesso direto para a tela operacional de gestão.</p>
            <div class="hc-bi-mini-kpis">
                <div class="hc-bi-mini-kpi">
                    <strong><?= $totalEventos ?></strong>
                    <span>eventos de Home Care</span>
                </div>
                <div class="hc-bi-mini-kpi">
                    <strong><?= number_format($mp, 1, ',', '.') ?></strong>
                    <span>média de permanência</span>
                </div>
                <div class="hc-bi-mini-kpi">
                    <strong><?= $maiorPermanencia ?></strong>
                    <span>maior permanência</span>
                </div>
            </div>
        </section>
        <aside class="hc-bi-hero-side">
            <div>
                <h2>Fluxo operacional</h2>
                <p>Use o dashboard para leitura executiva e avance para a tela operacional quando precisar tratar elegibilidade, barreiras e implantação por caso.</p>
            </div>
            <div class="bi-header-actions">
                <a class="bi-btn bi-btn-secondary" href="<?= $BASE_URL ?>home_care_gestao.php">Gestão Home Care</a>
                <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegacao">
                    <i class="bi bi-grid-3x3-gap"></i>
                </a>
            </div>
        </aside>
    </div>

    <form class="bi-panel bi-filters hc-bi-filters" method="get">
        <div class="bi-filter">
            <label>Hospitais</label>
            <select name="hospital_id">
                <option value="">Todos</option>
                <?php foreach ($hospitais as $h): ?>
                    <option value="<?= (int)$h['id_hospital'] ?>" <?= $hospitalId == $h['id_hospital'] ? 'selected' : '' ?>>
                        <?= e($h['nome_hosp']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Seguradora</label>
            <select name="seguradora_id">
                <option value="">Todos</option>
                <?php foreach ($seguradoras as $seg): ?>
                    <option value="<?= (int)$seg['id_seguradora'] ?>" <?= $seguradoraId == $seg['id_seguradora'] ? 'selected' : '' ?>>
                        <?= e($seg['seguradora_seg']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Status</label>
            <select name="status">
                <option value="">Todos</option>
                <option value="__sem_status__" <?= $status === '__sem_status__' ? 'selected' : '' ?>>Sem status</option>
                <?php foreach ($statusOptions as $statusValue => $statusLabel): ?>
                    <option value="<?= e($statusValue) ?>" <?= $status === $statusValue ? 'selected' : '' ?>>
                        <?= e($statusLabel) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Modalidade</label>
            <select name="modalidade">
                <option value="">Todos</option>
                <?php foreach ($modalidadeOptions as $modalidadeValue => $modalidadeLabel): ?>
                    <option value="<?= e($modalidadeValue) ?>" <?= $modalidade === $modalidadeValue ? 'selected' : '' ?>>
                        <?= e($modalidadeLabel) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Sem revisão há</label>
            <select name="sem_atualizacao">
                <option value="">Todos</option>
                <option value="5" <?= $semAtualizacao === 5 ? 'selected' : '' ?>>5 dias</option>
                <option value="7" <?= $semAtualizacao === 7 ? 'selected' : '' ?>>7 dias</option>
                <option value="10" <?= $semAtualizacao === 10 ? 'selected' : '' ?>>10 dias</option>
                <option value="15" <?= $semAtualizacao === 15 ? 'selected' : '' ?>>15 dias</option>
            </select>
        </div>
        <div class="bi-actions">
            <button class="bi-btn" type="submit">Aplicar</button>
            <a class="bi-btn hc-bi-btn-ghost" href="<?= $BASE_URL ?>bi/home-care">Limpar</a>
        </div>
    </form>

    <div class="bi-panel" style="margin-top:14px;">
        <div class="hc-bi-topline">
            <h3>Visão executiva</h3>
            <?php if ($activeFilters): ?>
                <div class="hc-bi-active-filters">
                    <?php foreach ($activeFilters as $filterLabel): ?>
                        <span class="hc-bi-filter-pill"><?= e($filterLabel) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="hc-bi-kpis">
            <div class="hc-bi-kpi"><small>Casos na fila</small><strong><?= $totalInternações ?></strong><span>Total de casos na mesma base da gestão.</span></div>
            <div class="hc-bi-kpi"><small>Diárias</small><strong><?= $totalDiárias ?></strong><span>Soma de permanência acumulada.</span></div>
            <div class="hc-bi-kpi"><small>MP</small><strong><?= number_format($mp, 1, ',', '.') ?></strong><span>Média de permanência do período.</span></div>
            <div class="hc-bi-kpi"><small>Maior permanência</small><strong><?= $maiorPermanencia ?></strong><span>Caso mais extenso da base atual.</span></div>
        </div>
    </div>

    <div class="hc-bi-grid">
        <div class="hc-bi-card hc-bi-card--hospital">
            <h3>Hospitais</h3>
            <div class="hc-bi-list">
                <?php if (!$hospRows): ?>
                    <div class="hc-bi-list-item"><span>Sem informacoes</span><span>0</span></div>
                <?php endif; ?>
                <?php foreach ($hospRows as $row): ?>
                    <div class="hc-bi-list-item">
                        <span><?= e($row['label'] ?? 'Sem informacoes') ?></span>
                        <span><?= (int)($row['total'] ?? 0) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="hc-bi-card hc-bi-card--event">
            <h3>Status da fila</h3>
            <div class="hc-bi-list">
                <?php if (!$tipoRows): ?>
                    <div class="hc-bi-list-item"><span>Sem informacoes</span><span>0</span></div>
                <?php endif; ?>
                <?php foreach ($tipoRows as $row): ?>
                    <div class="hc-bi-list-item">
                        <span><?= e($row['label'] ?? '-') ?></span>
                        <span><?= (int)($row['total'] ?? 0) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="hc-bi-focus">
            <small>No. de casos</small>
            <strong><?= $totalEventos ?></strong>
            <span>Quantidade de casos ativos na fila operacional.</span>
        </div>
    </div>

    <div class="bi-panel hc-bi-table-wrap">
        <div class="hc-bi-table-head">
            <div>
                <h3>Fila resumida de Home Care</h3>
                <p><?= count($rowsTable) ?> registro(s) exibidos.</p>
            </div>
            <a class="bi-btn bi-btn-secondary" href="<?= $BASE_URL ?>home_care_gestao.php">Abrir tela operacional</a>
        </div>
        <table class="bi-table">
            <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Hospital</th>
                    <th>Status</th>
                    <th>Modalidade</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rowsTable): ?>
                    <tr>
                        <td colspan="4">Sem informacoes para o filtro selecionado.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($rowsTable as $row): ?>
                    <tr>
                        <td><?= e($row['nome_pac'] ?? '-') ?></td>
                        <td><?= e($row['nome_hosp'] ?? '-') ?></td>
                        <td><?= e(!empty($row['status_hc']) ? ($statusOptions[$row['status_hc']] ?? $row['status_hc']) : 'Sem status') ?></td>
                        <td><?= e(!empty($row['modalidade_aprovada_hc']) ? ($modalidadeOptions[$row['modalidade_aprovada_hc']] ?? $row['modalidade_aprovada_hc']) : (!empty($row['modalidade_sugerida_hc']) ? ($modalidadeOptions[$row['modalidade_sugerida_hc']] ?? $row['modalidade_sugerida_hc']) : '-')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
