<?php

include_once("check_logado.php");
include_once("globals.php");
include_once("models/paciente.php");
include_once("dao/pacienteDao.php");
include_once("models/internacao.php");
include_once("dao/internacaoDao.php");
include_once("models/antecedente.php");
include_once("dao/antecedenteDao.php");
include_once("templates/header.php");

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!function_exists('pacHistoricoEsc')) {
    function pacHistoricoEsc($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('pacHistoricoValue')) {
    function pacHistoricoValue($value): string
    {
        $value = trim((string)$value);
        return $value !== '' ? pacHistoricoEsc($value) : '-';
    }
}

if (!function_exists('pacHistoricoDate')) {
    function pacHistoricoDate($value): string
    {
        $value = trim((string)$value);
        if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return '-';
        }
        $timestamp = strtotime($value);
        return $timestamp ? date('d/m/Y', $timestamp) : pacHistoricoEsc($value);
    }
}

if (!function_exists('pacHistoricoMoney')) {
    function pacHistoricoMoney($value): string
    {
        return 'R$ ' . number_format((float)$value, 2, ',', '.');
    }
}

if (!function_exists('pacHistoricoNormCargo')) {
    function pacHistoricoNormCargo($txt): string
    {
        $txt = mb_strtolower(trim((string)$txt), 'UTF-8');
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $txt);
        $txt = $ascii !== false ? $ascii : $txt;
        return preg_replace('/[^a-z]/', '', $txt);
    }
}

$id_paciente = filter_input(INPUT_GET, "id_paciente", FILTER_VALIDATE_INT);
$pacienteDao = new PacienteDAO($conn, $BASE_URL);
$paciente = $id_paciente ? $pacienteDao->findById($id_paciente) : null;

if (!$paciente || !isset($paciente[0])) {
    echo "<div class='container mt-4'><div class='alert alert-warning'>Paciente não encontrado.</div></div>";
    include_once("templates/footer.php");
    exit;
}

$pacienteRow = $paciente[0];
$isSeguradoraRole = (strpos(pacHistoricoNormCargo($_SESSION['cargo'] ?? ''), 'seguradora') !== false);
$seguradoraUserId = (int)($_SESSION['fk_seguradora_user'] ?? 0);
if ($isSeguradoraRole && $seguradoraUserId <= 0) {
    try {
        $uid = (int)($_SESSION['id_usuario'] ?? 0);
        if ($uid > 0) {
            $stmtSeg = $conn->prepare("SELECT fk_seguradora_user FROM tb_user WHERE id_usuario = :id LIMIT 1");
            $stmtSeg->bindValue(':id', $uid, PDO::PARAM_INT);
            $stmtSeg->execute();
            $seguradoraUserId = (int)($stmtSeg->fetchColumn() ?: 0);
            if ($seguradoraUserId > 0) {
                $_SESSION['fk_seguradora_user'] = $seguradoraUserId;
            }
        }
    } catch (Throwable $e) {
        error_log('[HIST_PAC][SEGURADORA] ' . $e->getMessage());
    }
}

if ($isSeguradoraRole) {
    $segPacId = (int)($pacienteRow['fk_seguradora_pac'] ?? 0);
    if (!$seguradoraUserId || $seguradoraUserId !== $segPacId) {
        echo "<div class='container mt-4'><div class='alert alert-danger'>Acesso negado para este paciente.</div></div>";
        include_once("templates/footer.php");
        exit;
    }
}

$internacaoDao = new internacaoDAO($conn, $BASE_URL);
$internacoes = $internacaoDao->findByPacId((int)$pacienteRow['id_paciente']);

$antecedenteDao = new antecedenteDAO($conn, $BASE_URL);
$antecedentes = $antecedenteDao->findAntByPacId((int)$pacienteRow['id_paciente']);
$antecedentes = array_values(array_filter($antecedentes, static function ($ant): bool {
    return trim((string)($ant['antecedente_ant'] ?? '')) !== '';
}));

$totalCapeante = $internacaoDao->findTotalByPacId((int)$pacienteRow['id_paciente']);
$totalDiarias = $internacaoDao->findTotalDiariasByPacId((int)$pacienteRow['id_paciente']);
$totalDiariasUti = $internacaoDao->findTotalDiariasUtiByPacId((int)$pacienteRow['id_paciente']);

$valorCapeante = (float)($totalCapeante[0]['total_capeante'] ?? 0);
$diarias = (int)($totalDiarias[0]['total_diarias'] ?? 0);
$diariasUti = (int)($totalDiariasUti[0]['total_diarias'] ?? 0);
$totalInternacoes = count($internacoes);
$ultimaInternacao = $internacoes[0]['data_intern_int'] ?? null;
foreach ($internacoes as $internacaoRow) {
    if (!empty($internacaoRow['data_intern_int']) && (!$ultimaInternacao || strtotime($internacaoRow['data_intern_int']) > strtotime((string)$ultimaInternacao))) {
        $ultimaInternacao = $internacaoRow['data_intern_int'];
    }
}

$matriculaPaciente = trim((string)($pacienteRow['matricula_pac'] ?? ''));
if (($pacienteRow['recem_nascido_pac'] ?? '') === 's' && trim((string)($pacienteRow['numero_rn_pac'] ?? '')) !== '') {
    $matriculaPaciente .= ' RN' . trim((string)$pacienteRow['numero_rn_pac']);
}
?>
<script src="js/timeout.js"></script>
<link rel="stylesheet" href="css/form_cad_internacao.css?v=<?= @filemtime(dirname(__DIR__, 2) . '/css/form_cad_internacao.css') ?>">

<style>
.pac-historico-page {
    padding: 0 16px 96px;
}

.pac-historico-page .internacao-page__hero {
    margin-bottom: 14px;
}

.pac-historico-grid {
    display: grid;
    grid-template-columns: minmax(240px, 310px) minmax(0, 1fr);
    gap: 16px;
    align-items: start;
}

.pac-historico-summary,
.pac-historico-card {
    background: #fff;
    border: 1px solid rgba(47, 111, 159, 0.12);
    border-radius: 14px;
    box-shadow: 0 12px 30px rgba(47, 60, 85, 0.08);
}

.pac-historico-summary {
    padding: 18px;
}

.pac-historico-avatar {
    width: 96px;
    height: 96px;
    border-radius: 24px;
    display: grid;
    place-items: center;
    margin-bottom: 14px;
    background: #eef6fb;
    color: #2f6f9f;
    font-size: 2.6rem;
    border: 4px solid #eef6fb;
    box-shadow: 0 10px 24px rgba(47, 111, 159, 0.16);
}

.pac-historico-name {
    margin: 0 0 4px;
    color: #1f2937;
    font-size: 1.22rem;
    font-weight: 800;
}

.pac-historico-subtitle {
    margin: 0;
    color: #667085;
    font-size: 0.9rem;
}

.pac-historico-meta {
    display: grid;
    gap: 9px;
    margin-top: 18px;
    padding-top: 16px;
    border-top: 1px solid #edf2f7;
}

.pac-historico-meta span {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    color: #667085;
    font-size: 0.84rem;
}

.pac-historico-meta strong {
    color: #334155;
    font-weight: 800;
}

.pac-historico-kpis {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}

.pac-historico-kpi {
    min-height: 92px;
    padding: 14px;
    border: 1px solid #e5edf4;
    border-radius: 12px;
    background: #f8fbfd;
}

.pac-historico-kpi label {
    display: block;
    margin-bottom: 8px;
    color: #64748b;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.pac-historico-kpi div {
    color: #1f2937;
    font-size: 1.25rem;
    font-weight: 800;
}

.pac-historico-stack {
    display: grid;
    gap: 14px;
}

.pac-historico-card {
    padding: 16px;
}

.pac-historico-card h3 {
    margin: 0;
    color: #24384f;
    font-size: 1rem;
    font-weight: 800;
}

.pac-historico-card-subtitle {
    margin: 3px 0 0;
    color: #64748b;
    font-size: 0.84rem;
}

.pac-historico-table-wrap {
    overflow-x: auto;
    margin-top: 14px;
    border: 1px solid #e5edf4;
    border-radius: 12px;
}

.pac-historico-table {
    margin: 0;
    min-width: 760px;
}

.pac-historico-table thead th {
    background: #2f6f9f;
    color: #fff;
    border: 0;
    font-size: 0.78rem;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.pac-historico-table tbody td {
    vertical-align: middle;
    color: #334155;
    font-size: 0.9rem;
}

.pac-historico-empty {
    padding: 18px;
    text-align: center;
    color: #64748b;
    background: #f8fbfd;
    font-weight: 700;
}

.pac-historico-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}

.pac-historico-actions .btn {
    border-radius: 9px;
    line-height: 1;
}

.pac-historico-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 14px;
}

.pac-historico-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 10px;
    border-radius: 999px;
    background: #f4f0fa;
    color: #5e2363;
    font-size: 0.84rem;
    font-weight: 800;
}

@media (max-width: 1100px) {
    .pac-historico-grid,
    .pac-historico-kpis {
        grid-template-columns: 1fr;
    }
}
</style>

<main id="main-container" class="internacao-page cadastro-layout pac-historico-page">
    <div class="internacao-page__hero">
        <div class="internacao-page__hero-main">
            <h1>Histórico do paciente</h1>
        </div>
        <div class="hero-actions">
            <a href="<?= $BASE_URL ?>pacientes" class="hero-back-btn">Voltar para lista</a>
            <a href="<?= $BASE_URL ?>pacientes/ver/<?= (int)$id_paciente ?>" class="hero-back-btn">Ver paciente</a>
            <a href="<?= $BASE_URL ?>internacoes/nova?id_paciente=<?= (int)$id_paciente ?>" class="hero-back-btn">Lançar internação</a>
            <span class="internacao-page__tag">Registro #<?= (int)$id_paciente ?></span>
        </div>
    </div>

    <div class="pac-historico-grid">
        <aside class="pac-historico-summary">
            <div class="pac-historico-avatar" aria-hidden="true">
                <i class="bi bi-clock-history"></i>
            </div>
            <h2 class="pac-historico-name"><?= pacHistoricoValue($pacienteRow['nome_pac'] ?? '') ?></h2>
            <p class="pac-historico-subtitle"><?= pacHistoricoValue($pacienteRow['seguradora_seg'] ?? '') ?></p>

            <div class="pac-historico-meta">
                <span><strong>Matrícula</strong><?= pacHistoricoValue($matriculaPaciente) ?></span>
                <span><strong>Estipulante</strong><?= pacHistoricoValue($pacienteRow['nome_est'] ?? '') ?></span>
                <span><strong>Cadastro</strong><?= pacHistoricoDate($pacienteRow['data_create_pac'] ?? '') ?></span>
                <span><strong>Última internação</strong><?= pacHistoricoDate($ultimaInternacao) ?></span>
            </div>
        </aside>

        <section class="pac-historico-stack">
            <div class="pac-historico-kpis">
                <div class="pac-historico-kpi">
                    <label>Internações</label>
                    <div><?= (int)$totalInternacoes ?></div>
                </div>
                <div class="pac-historico-kpi">
                    <label>Total de diárias</label>
                    <div><?= (int)$diarias ?></div>
                </div>
                <div class="pac-historico-kpi">
                    <label>Diárias UTI</label>
                    <div><?= (int)$diariasUti ?></div>
                </div>
                <div class="pac-historico-kpi">
                    <label>Custo total</label>
                    <div><?= pacHistoricoMoney($valorCapeante) ?></div>
                </div>
            </div>

            <div class="pac-historico-card">
                <h3>Histórico de internações</h3>
                <p class="pac-historico-card-subtitle">Registros assistenciais vinculados ao paciente.</p>
                <div class="pac-historico-table-wrap">
                    <table class="table table-sm table-striped table-hover table-condensed pac-historico-table">
                        <thead>
                            <tr>
                                <th scope="col" width="8%">ID</th>
                                <th scope="col">Hospital</th>
                                <th scope="col" width="14%">Data int.</th>
                                <th scope="col" width="18%">Antecedente</th>
                                <th scope="col" width="12%">Status</th>
                                <th scope="col" width="12%">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($internacoes as $intern): ?>
                            <tr>
                                <td class="col-id"><?= (int)($intern["id_internacao"] ?? 0) ?></td>
                                <td style="font-weight:700;"><?= pacHistoricoValue($intern["nome_hosp"] ?? '') ?></td>
                                <td><?= pacHistoricoDate($intern["data_intern_int"] ?? '') ?></td>
                                <td><?= pacHistoricoValue($intern["antecedente_ant"] ?? '') ?></td>
                                <td><?= strtolower((string)($intern["internado_int"] ?? '')) === 's' ? 'Internado' : 'Encerrada' ?></td>
                                <td>
                                    <div class="pac-historico-actions">
                                        <a class="btn btn-sm btn-outline-primary"
                                            href="<?= $BASE_URL ?>internacoes/visualizar/<?= (int)($intern["id_internacao"] ?? 0) ?>"
                                            title="Ver internação" aria-label="Ver internação">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (count($internacoes) === 0): ?>
                            <tr>
                                <td colspan="6" class="pac-historico-empty">Não foram encontrados registros</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="pac-historico-card">
                <h3>Antecedentes</h3>
                <p class="pac-historico-card-subtitle">Antecedentes registrados nas internações do paciente.</p>
                <?php if (count($antecedentes) > 0): ?>
                <div class="pac-historico-tags">
                    <?php foreach ($antecedentes as $ant): ?>
                        <?php if (trim((string)($ant["antecedente_ant"] ?? '')) !== ''): ?>
                        <span class="pac-historico-tag">
                            <i class="bi bi-clipboard2-pulse"></i>
                            <?= pacHistoricoValue($ant["antecedente_ant"] ?? '') ?>
                        </span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="pac-historico-empty" style="margin-top:14px;border-radius:12px;">Não foram encontrados registros</div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>
<?php include_once("templates/footer.php"); ?>
