<?php
include_once("check_logado.php");

include_once("globals.php");

include_once("models/paciente.php");
include_once("dao/pacienteDao.php");
require_once("app/services/ReadmissionRiskService.php");
include_once("templates/header.php");

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$normCargoAccess = static function ($txt): string {
    $txt = mb_strtolower(trim((string)$txt), 'UTF-8');
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $txt);
    $txt = $ascii !== false ? $ascii : $txt;
    return preg_replace('/[^a-z]/', '', $txt);
};
$isSeguradoraRole = (strpos($normCargoAccess($_SESSION['cargo'] ?? ''), 'seguradora') !== false);
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
        error_log('[SHOW_PAC][SEGURADORA] ' . $e->getMessage());
    }
}

function calcularIdadeAnos(?string $dataNasc): int
{
    if (!$dataNasc || $dataNasc === '0000-00-00') return 0;
    try {
        $dn = new DateTime($dataNasc);
        $hoje = new DateTime();
        return (int)$dn->diff($hoje)->y;
    } catch (Throwable $e) {
        return 0;
    }
}

function logisticProbFromScore(float $score): float
{
    $x = ($score - 50) / 10;
    return round(1 / (1 + exp(-$x)), 3);
}

function coletarIndicadoresPaciente(PDO $conn, int $pacienteId): array
{
    $indicadores = [
        'total_internacoes' => 0,
        'media_permanencia' => 0,
        'ultima_internacao' => null,
        'eventos_adversos' => 0,
        'antecedentes' => 0,
        'long_stay' => 0
    ];
    try {
        $stmtResumo = $conn->prepare("
            SELECT
                COUNT(*) AS total_int,
                AVG(GREATEST(DATEDIFF(COALESCE(al.data_alta_alt, CURRENT_DATE), ac.data_intern_int), 0)) AS media_dias,
                MAX(ac.data_intern_int) AS ultima_data,
                SUM(
                    CASE WHEN GREATEST(DATEDIFF(COALESCE(al.data_alta_alt, CURRENT_DATE), ac.data_intern_int), 0) >= 20
                         THEN 1 ELSE 0 END
                ) AS longos
            FROM tb_internacao ac
            LEFT JOIN tb_alta al ON al.fk_id_int_alt = ac.id_internacao
            WHERE ac.fk_paciente_int = :pac
        ");
        $stmtResumo->bindValue(':pac', $pacienteId, PDO::PARAM_INT);
        $stmtResumo->execute();
        $rowResumo = $stmtResumo->fetch(PDO::FETCH_ASSOC) ?: [];
        $indicadores['total_internacoes'] = (int)($rowResumo['total_int'] ?? 0);
        $indicadores['media_permanencia'] = round((float)($rowResumo['media_dias'] ?? 0), 1);
        $indicadores['ultima_internacao'] = $rowResumo['ultima_data'] ?? null;
        $indicadores['long_stay'] = (int)($rowResumo['longos'] ?? 0);

        $stmtEventos = $conn->prepare("
            SELECT COUNT(*)
              FROM tb_gestao ge
              INNER JOIN tb_internacao ac ON ac.id_internacao = ge.fk_internacao_ges
             WHERE ac.fk_paciente_int = :pac
               AND LOWER(IFNULL(ge.evento_adverso_ges,'')) = 's'
        ");
        $stmtEventos->bindValue(':pac', $pacienteId, PDO::PARAM_INT);
        $stmtEventos->execute();
        $indicadores['eventos_adversos'] = (int)$stmtEventos->fetchColumn();

        $stmtAnt = $conn->prepare("SELECT COUNT(*) FROM tb_intern_antec WHERE fk_id_paciente = :pac");
        $stmtAnt->bindValue(':pac', $pacienteId, PDO::PARAM_INT);
        $stmtAnt->execute();
        $indicadores['antecedentes'] = (int)$stmtAnt->fetchColumn();
    } catch (Throwable $e) {
        // mantém padrão
    }
    return $indicadores;
}

function fallbackRiskFromIndicadores(array $pacienteRow, array $indicadores): ?array
{
    $totalIntern = (int)($indicadores['total_internacoes'] ?? 0);
    if ($totalIntern <= 0) return null;

    $idade = calcularIdadeAnos($pacienteRow['data_nasc_pac'] ?? null);
    $score = 12;
    if ($idade >= 65) $score += 18;
    elseif ($idade >= 40) $score += 12;
    elseif ($idade >= 18) $score += 8;
    else $score += 6;

    $anteced = (int)($indicadores['antecedentes'] ?? 0);
    $score += min(18, $anteced * 4);

    $internPrev = max(0, $totalIntern - 1);
    $score += min(20, $internPrev * 6);

    $mediaDias = (float)($indicadores['media_permanencia'] ?? 0);
    if ($mediaDias >= 15) $score += 12;
    elseif ($mediaDias >= 8) $score += 8;

    if ((int)($indicadores['long_stay'] ?? 0) > 0) $score += 6;
    if ((int)($indicadores['eventos_adversos'] ?? 0) > 0) {
        $score += min(12, $indicadores['eventos_adversos'] * 4);
    }

    $score = max(5, min($score, 95));
    $prob = logisticProbFromScore($score);
    $riskLevel = $prob >= 0.7 ? 'alto' : ($prob >= 0.45 ? 'moderado' : 'baixo');

    $recommendations = [];
    if ($riskLevel === 'alto') {
        $recommendations[] = 'Visita presencial prioritária e revisão do plano terapêutico.';
    } elseif ($riskLevel === 'moderado') {
        $recommendations[] = 'Planejar visita extra ou telemonitoramento.';
    } else {
        $recommendations[] = 'Rotina usual e monitoramento de eventos.';
    }

    $expParts = [];
    $expParts[] = "Idade {$idade} anos";
    $expParts[] = "{$anteced} antecedente(s)";
    $expParts[] = "{$totalIntern} internações registradas";
    if ($mediaDias > 0) $expParts[] = "média {$mediaDias} dias";
    if ((int)($indicadores['eventos_adversos'] ?? 0) > 0) $expParts[] = "eventos adversos recentes";

    return [
        'available' => true,
        'fallback' => true,
        'probability' => $prob,
        'risk_level' => $riskLevel,
        'threshold' => 0.55,
        'internacao_referencia' => null,
        'explanation' => 'Estimativa baseada no histórico: ' . implode(', ', $expParts) . '.',
        'recommendations' => $recommendations,
        'message' => 'Estimativa gerada pelo histórico consolidado.'
    ];
}

// Pegar o id do paceinte
$id_paciente = filter_input(INPUT_GET, "id_paciente", FILTER_SANITIZE_NUMBER_INT);
$paciente;
$pacienteDao = new PacienteDAO($conn, $BASE_URL);

//Instanciar o metodo paciente   
$paciente = $pacienteDao->findById($id_paciente);
if (!$paciente || !isset($paciente[0])) {
    echo "<div class='container mt-4'><div class='alert alert-warning'>Paciente não encontrado.</div></div>";
    include_once("templates/footer.php");
    exit;
}
if ($isSeguradoraRole) {
    $segPacId = (int)($paciente[0]['fk_seguradora_pac'] ?? 0);
    if (!$seguradoraUserId || $seguradoraUserId !== $segPacId) {
        echo "<div class='container mt-4'><div class='alert alert-danger'>Acesso negado para este paciente.</div></div>";
        include_once("templates/footer.php");
        exit;
    }
}
extract($paciente);
$telefone01_format = $telefone02_format = $cnpj_format = null;

if (strlen($paciente['0']['telefone01_pac']) > 0) {

    if (strlen($paciente['0']['telefone01_pac']) == 10) {
        $telefone01_format = '(' .
            substr($paciente['0']['telefone01_pac'], 0, 2) . ') ' .
            substr($paciente['0']['telefone01_pac'], 2, 4) . '-' .
            substr($paciente['0']['telefone01_pac'], 6, 9);
    } else {
        $telefone01_format = '(' .
            substr($paciente['0']['telefone01_pac'], 0, 2) . ') ' .
            substr($paciente['0']['telefone01_pac'], 2, 5) . '-' .
            substr($paciente['0']['telefone01_pac'], 7, 9);
    }
} else {
    $telefone01_format = null;
};
if (strlen($paciente['0']['telefone02_pac']) > 0) {

    if (strlen($paciente['0']['telefone02_pac']) == 10) {
        $telefone02_format = '(' .
            substr($paciente['0']['telefone02_pac'], 0, 2) . ') ' .
            substr($paciente['0']['telefone02_pac'], 2, 4) . '-' .
            substr($paciente['0']['telefone02_pac'], 6, 9);
    } else {
        $telefone02_format = '(' .
            substr($paciente['0']['telefone02_pac'], 0, 2) . ') ' .
            substr($paciente['0']['telefone02_pac'], 2, 5) . '-' .
            substr($paciente['0']['telefone02_pac'], 7, 9);
    }
} else {
    $telefone02_format = null;
};

$paciente['0']['telefone01_pac'] = $telefone01_format;
$paciente['0']['telefone02_pac'] = $telefone02_format;

$cpf_pac = $paciente['0']['cpf_pac'];
$bloco_1 = substr($cpf_pac, 0, 3);
$bloco_2 = substr($cpf_pac, 3, 3);
$bloco_3 = substr($cpf_pac, 6, 3);
$dig_verificador = substr($cpf_pac, -2);
$cpf_formatado = $bloco_1 . "." . $bloco_2 . "." . $bloco_3 . "-" . $dig_verificador;

$riskOverview = ['available' => false];
$lastInternacaoId = null;
try {
    $stmtUltimaInt = $conn->prepare("
        SELECT id_internacao
          FROM tb_internacao
         WHERE fk_paciente_int = :paciente
         ORDER BY COALESCE(data_intern_int, '0000-00-00') DESC, id_internacao DESC
         LIMIT 1
    ");
    $stmtUltimaInt->bindValue(':paciente', (int)$id_paciente, PDO::PARAM_INT);
    $stmtUltimaInt->execute();
    $lastInternacaoId = (int)($stmtUltimaInt->fetchColumn() ?: 0);
    if ($lastInternacaoId) {
        $riskService = new ReadmissionRiskService($conn);
        $riskOverview = $riskService->scoreInternacao($lastInternacaoId);
        $riskOverview['internacao_referencia'] = $lastInternacaoId;
    } else {
        $riskOverview = [
            'available' => false,
            'message'   => 'Ainda não há internações para estimar o risco.'
        ];
    }
} catch (Throwable $e) {
    $riskOverview = [
        'available' => false,
        'message'   => 'Não foi possível calcular o risco de readmissão.'
    ];
}

$indicadoresPaciente = coletarIndicadoresPaciente($conn, (int)$id_paciente);
if (empty($riskOverview['available'])) {
    $fallbackRisk = fallbackRiskFromIndicadores($paciente['0'] ?? [], $indicadoresPaciente);
    if ($fallbackRisk) {
        $riskOverview = $fallbackRisk;
    }
}

if (!function_exists('pacienteShowEsc')) {
    function pacienteShowEsc($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('pacienteShowValue')) {
    function pacienteShowValue($value): string
    {
        $value = trim((string)$value);
        return $value !== '' ? pacienteShowEsc($value) : '-';
    }
}

if (!function_exists('pacienteShowPhone')) {
    function pacienteShowPhone($value): string
    {
        $digits = preg_replace('/\D+/', '', (string)$value);
        if ($digits === '') {
            return '-';
        }
        if (strlen($digits) === 10) {
            return '(' . substr($digits, 0, 2) . ') ' . substr($digits, 2, 4) . '-' . substr($digits, 6);
        }
        if (strlen($digits) === 11) {
            return '(' . substr($digits, 0, 2) . ') ' . substr($digits, 2, 5) . '-' . substr($digits, 7);
        }
        return pacienteShowEsc((string)$value);
    }
}

if (!function_exists('pacienteShowCpf')) {
    function pacienteShowCpf($value): string
    {
        $digits = preg_replace('/\D+/', '', (string)$value);
        if ($digits === '') {
            return '-';
        }
        if (strlen($digits) === 11) {
            return substr($digits, 0, 3) . '.' .
                substr($digits, 3, 3) . '.' .
                substr($digits, 6, 3) . '-' .
                substr($digits, 9, 2);
        }
        return pacienteShowEsc((string)$value);
    }
}

if (!function_exists('pacienteShowDate')) {
    function pacienteShowDate($value): string
    {
        $value = trim((string)$value);
        if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return '-';
        }
        $timestamp = strtotime($value);
        return $timestamp ? date('d/m/Y', $timestamp) : pacienteShowEsc($value);
    }
}

$pacienteRow = $paciente[0] ?? [];
$ativoPaciente = strtolower(trim((string)($pacienteRow['ativo_pac'] ?? '')));
$deletadoPaciente = strtolower(trim((string)($pacienteRow['deletado_pac'] ?? '')));
$statusAtivo = $deletadoPaciente !== 's' && $ativoPaciente !== 'n';
$statusLabel = $statusAtivo ? 'Ativo' : 'Inativo';
$statusClass = $statusAtivo ? 'is-active' : 'is-inactive';
$sexoValor = strtolower((string)($pacienteRow['sexo_pac'] ?? ''));
$sexoLabel = $sexoValor === 'f' ? 'Feminino' : ($sexoValor === 'm' ? 'Masculino' : pacienteShowValue($pacienteRow['sexo_pac'] ?? ''));
$idadePaciente = calcularIdadeAnos($pacienteRow['data_nasc_pac'] ?? null);
$matriculaPaciente = trim((string)($pacienteRow['matricula_pac'] ?? ''));
if (($pacienteRow['recem_nascido_pac'] ?? '') === 's' && trim((string)($pacienteRow['numero_rn_pac'] ?? '')) !== '') {
    $matriculaPaciente .= ' RN' . trim((string)$pacienteRow['numero_rn_pac']);
}
$enderecoPaciente = trim(implode(' ', array_filter([
    trim((string)($pacienteRow['endereco_pac'] ?? '')),
    trim((string)($pacienteRow['numero_pac'] ?? '')) !== '' ? ', ' . trim((string)$pacienteRow['numero_pac']) : '',
])));
?>
<script src="js/timeout.js"></script>
<link rel="stylesheet" href="css/form_cad_internacao.css?v=<?= @filemtime(dirname(__DIR__, 2) . '/css/form_cad_internacao.css') ?>">

<style>
.paciente-show-page {
    padding: 0 16px 96px;
}

.paciente-show-page .internacao-page__hero {
    margin-bottom: 14px;
}

.paciente-profile-card {
    display: grid;
    grid-template-columns: minmax(220px, 300px) minmax(0, 1fr);
    gap: 16px;
    align-items: start;
}

.paciente-profile-summary,
.paciente-info-card,
.paciente-ai-card,
.paciente-risk-card,
.paciente-danger-card {
    background: #fff;
    border: 1px solid rgba(47, 111, 159, 0.12);
    border-radius: 14px;
    box-shadow: 0 12px 30px rgba(47, 60, 85, 0.08);
}

.paciente-profile-summary {
    padding: 18px;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    min-height: 100%;
}

.paciente-avatar {
    width: 112px;
    height: 112px;
    border-radius: 28px;
    display: grid;
    place-items: center;
    background: #eef6fb;
    color: #2f6f9f;
    font-size: 3rem;
    border: 4px solid #eef6fb;
    box-shadow: 0 10px 24px rgba(47, 111, 159, 0.16);
}

.paciente-name {
    margin: 14px 0 4px;
    color: #1f2937;
    font-size: 1.25rem;
    font-weight: 800;
}

.paciente-meta-line {
    margin: 0;
    color: #667085;
    font-size: 0.92rem;
}

.paciente-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 14px;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 800;
}

.paciente-status::before {
    content: "";
    width: 8px;
    height: 8px;
    border-radius: 999px;
    background: currentColor;
}

.paciente-status.is-active {
    background: #eaf8f0;
    color: #16834d;
}

.paciente-status.is-inactive {
    background: #fff1f2;
    color: #be123c;
}

.paciente-summary-meta {
    width: 100%;
    display: grid;
    gap: 8px;
    margin-top: 18px;
    padding-top: 16px;
    border-top: 1px solid #edf2f7;
    text-align: left;
}

.paciente-summary-meta span {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    color: #667085;
    font-size: 0.82rem;
}

.paciente-summary-meta strong {
    color: #334155;
    font-weight: 800;
}

.paciente-info-stack {
    display: grid;
    gap: 14px;
}

.paciente-info-card,
.paciente-ai-card,
.paciente-risk-card {
    padding: 16px;
}

.paciente-info-card h3,
.paciente-ai-card h3,
.paciente-risk-card h3,
.paciente-danger-card h3 {
    margin: 0;
    color: #24384f;
    font-size: 1rem;
    font-weight: 800;
}

.paciente-card-subtitle {
    margin: 3px 0 0;
    color: #64748b;
    font-size: 0.84rem;
}

.paciente-field-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    margin-top: 14px;
}

.paciente-field {
    min-height: 74px;
    padding: 11px 12px;
    border: 1px solid #e5edf4;
    border-radius: 10px;
    background: #f8fbfd;
}

.paciente-field label {
    display: block;
    margin: 0 0 5px;
    padding: 0;
    color: #64748b;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.paciente-field div {
    color: #1f2937;
    font-size: 0.94rem;
    font-weight: 600;
    word-break: break-word;
}

.paciente-risk-card {
    border-width: 2px;
}

.paciente-risk-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}

.paciente-risk-score {
    font-size: 2.2rem;
    font-weight: 800;
    line-height: 1;
}

.paciente-risk-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 8px;
    padding: 6px 10px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.6);
    font-size: 0.82rem;
    font-weight: 800;
}

.paciente-risk-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 14px 20px;
    margin-top: 12px;
    font-size: 0.88rem;
}

.paciente-risk-card ul {
    margin: 12px 0 0 18px;
    padding-left: 0.8rem;
    font-size: 0.88rem;
}

.paciente-ai-card {
    display: grid;
    gap: 14px;
}

.paciente-ai-header {
    display: flex;
    justify-content: space-between;
    gap: 14px;
    align-items: flex-start;
    flex-wrap: wrap;
}

.paciente-ai-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 999px;
    background: #eef6fb;
    color: #2f6f9f;
    font-size: 0.76rem;
    font-weight: 800;
}

.paciente-ai-messages {
    min-height: 210px;
    max-height: 380px;
    overflow-y: auto;
    display: grid;
    gap: 10px;
    padding: 12px;
    border: 1px solid #e5edf4;
    border-radius: 12px;
    background: #f8fbfd;
}

.paciente-ai-message {
    width: min(88%, 760px);
    padding: 11px 13px;
    border-radius: 12px;
    white-space: pre-wrap;
    color: #263445;
    font-size: 0.92rem;
    line-height: 1.48;
}

.paciente-ai-message.is-assistant {
    justify-self: start;
    background: #fff;
    border: 1px solid #dbe8f2;
}

.paciente-ai-message.is-user {
    justify-self: end;
    background: #2f6f9f;
    color: #fff;
}

.paciente-ai-suggestions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.paciente-ai-suggestion {
    border: 1px solid #d7e4ee;
    border-radius: 999px;
    background: #fff;
    color: #35556f;
    font-size: 0.82rem;
    font-weight: 700;
    padding: 7px 11px;
}

.paciente-ai-form {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 10px;
    align-items: end;
}

.paciente-ai-form textarea {
    min-height: 52px;
    max-height: 140px;
    resize: vertical;
    border: 1px solid #d7e4ee;
    border-radius: 10px;
    padding: 11px 12px;
    font-size: 0.92rem;
}

.paciente-ai-form button {
    min-height: 52px;
    border-radius: 10px;
    padding: 0 16px;
    font-weight: 800;
}

.paciente-danger-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-top: 14px;
    padding: 16px;
    border-color: rgba(190, 18, 60, 0.18);
    background: linear-gradient(135deg, #fff 0%, #fff7f7 100%);
}

.paciente-danger-card p {
    margin: 4px 0 0;
    color: #667085;
    font-size: 0.88rem;
}

.paciente-actions {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.paciente-actions .btn {
    border-radius: 10px;
    font-weight: 700;
    padding: 9px 14px;
}

@media (max-width: 980px) {
    .paciente-profile-card,
    .paciente-field-grid {
        grid-template-columns: 1fr;
    }

    .paciente-danger-card {
        align-items: flex-start;
        flex-direction: column;
    }

    .paciente-ai-form {
        grid-template-columns: 1fr;
    }
}
</style>

<main id="main-container" class="internacao-page cadastro-layout paciente-show-page">
    <div class="internacao-page__hero">
        <div class="internacao-page__hero-main">
            <h1>Dados do paciente</h1>
        </div>
        <div class="hero-actions">
            <a href="<?= $BASE_URL ?>pacientes" class="hero-back-btn">Voltar para lista</a>
            <a href="<?= $BASE_URL ?>pacientes/editar/<?= (int)$id_paciente ?>" class="hero-back-btn">Editar paciente</a>
            <a href="<?= $BASE_URL ?>pacientes/historico/<?= (int)$id_paciente ?>" class="hero-back-btn">Histórico</a>
            <a href="<?= $BASE_URL ?>pacientes/hub/<?= (int)$id_paciente ?>" class="hero-back-btn">Hub Paciente</a>
            <a href="<?= $BASE_URL ?>internacoes/nova?id_paciente=<?= (int)$id_paciente ?>" class="hero-back-btn">Nova internação</a>
            <span class="internacao-page__tag">Registro #<?= (int)$id_paciente ?></span>
        </div>
    </div>

    <div class="paciente-profile-card">
        <aside class="paciente-profile-summary">
            <div class="paciente-avatar" aria-hidden="true">
                <i class="bi bi-person-vcard"></i>
            </div>
            <h2 class="paciente-name"><?= pacienteShowValue($pacienteRow['nome_pac'] ?? '') ?></h2>
            <p class="paciente-meta-line"><?= pacienteShowValue($pacienteRow['nome_social_pac'] ?? '') ?></p>
            <span class="paciente-status <?= $statusClass ?>"><?= $statusLabel ?></span>

            <div class="paciente-summary-meta">
                <span><strong>CPF</strong><?= pacienteShowCpf($pacienteRow['cpf_pac'] ?? '') ?></span>
                <span><strong>Matrícula</strong><?= pacienteShowValue($matriculaPaciente) ?></span>
                <span><strong>Idade</strong><?= $idadePaciente ? (int)$idadePaciente . ' anos' : '-' ?></span>
                <span><strong>Seguradora</strong><?= pacienteShowValue($pacienteRow['seguradora_seg'] ?? '') ?></span>
            </div>
        </aside>

        <section class="paciente-info-stack">
            <?php if (!empty($riskOverview['available'])):
                $riskLevel = strtolower((string)($riskOverview['risk_level'] ?? ''));
                $alertClass = $riskLevel === 'alto' ? '#ffe0e3' : ($riskLevel === 'moderado' ? '#fff5d6' : '#e6fff4');
                $borderColor = $riskLevel === 'alto' ? '#c9184a' : ($riskLevel === 'moderado' ? '#f0a500' : '#0f8f5d');
                $textColor = $riskLevel === 'alto' ? '#5a071d' : ($riskLevel === 'moderado' ? '#6a4900' : '#065238');
                $probPct = number_format((float)($riskOverview['probability'] ?? 0) * 100, 1, ',', '.');
                $features = $riskOverview['features'] ?? [];
                $faixa = ucfirst((string)($features['faixa_etaria'] ?? '-'));
                $idade = (int)($features['idade'] ?? $idadePaciente);
                $antecedentes = (int)($features['antecedentes'] ?? ($indicadoresPaciente['antecedentes'] ?? 0));
                $internPrev = (int)($features['internacoes_previas'] ?? max(0, (int)($indicadoresPaciente['total_internacoes'] ?? 0) - 1));
                $mpPrev = number_format((float)($features['mp_previas'] ?? ($indicadoresPaciente['media_permanencia'] ?? 0)), 1, ',', '.');
                $diasAtual = (int)($features['dias_internado_atual'] ?? 0);
                $mpLimite = (int)($features['mp_limite'] ?? 0);
                $eventos = (int)($features['eventos_adversos'] ?? ($indicadoresPaciente['eventos_adversos'] ?? 0));
                $complexMap = [
                    'alto' => ['label' => 'Alta complexidade', 'prioridade' => 'Visita prioritária (<24h)'],
                    'moderado' => ['label' => 'Complexidade intermediária', 'prioridade' => 'Visita reforçada / monitorar'],
                    'baixo' => ['label' => 'Baixa complexidade', 'prioridade' => 'Seguir rotina padrão']
                ];
                $complexInfo = $complexMap[$riskLevel ?: 'baixo'] ?? $complexMap['baixo'];
                $refIntern = $riskOverview['internacao_referencia'] ?? null;
            ?>
            <div class="paciente-risk-card" style="border-color:<?= $borderColor ?>; background:<?= $alertClass ?>; color:<?= $textColor ?>;">
                <div class="paciente-risk-header">
                    <div>
                        <h3 style="color:<?= $textColor ?>;">Risco de readmissão</h3>
                        <div class="paciente-risk-score"><?= $probPct ?>%</div>
                        <div style="font-size:0.85rem;">
                            Nível <?= strtoupper($riskLevel ?: 'BAIXO') ?> · Internação ref.
                            <?= $refIntern ? '#' . (int)$refIntern : '-' ?>
                        </div>
                        <span class="paciente-risk-pill">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <?= pacienteShowEsc($complexInfo['label']) ?> · <?= pacienteShowEsc($complexInfo['prioridade']) ?>
                        </span>
                    </div>
                    <div style="flex:1; min-width:250px; font-size:0.9rem;">
                        <?= pacienteShowEsc($riskOverview['explanation'] ?? '') ?>
                    </div>
                </div>
                <div class="paciente-risk-meta">
                    <div><strong>Perfil:</strong> <?= pacienteShowEsc($faixa) ?><?= $idade ? " ({$idade} anos)" : '' ?>, sexo <?= pacienteShowEsc(strtoupper((string)($features['sexo'] ?? ($pacienteRow['sexo_pac'] ?? 'ND')))) ?></div>
                    <div><strong>Antecedentes:</strong> <?= $antecedentes ?> · <strong>Internações prévias:</strong> <?= $internPrev ?> (MP <?= $mpPrev ?> dias)</div>
                    <div><strong>Permanência atual:</strong> <?= $diasAtual ?> dias<?= $mpLimite ? " (limite {$mpLimite})" : '' ?> · <strong>Eventos adversos:</strong> <?= $eventos ?></div>
                </div>
                <?php if (!empty($riskOverview['recommendations']) && is_array($riskOverview['recommendations'])): ?>
                <ul>
                    <?php foreach ($riskOverview['recommendations'] as $rec): ?>
                    <li><?= pacienteShowEsc($rec) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
            <?php elseif (!empty($riskOverview['message'])): ?>
            <div class="paciente-risk-card">
                <h3>Risco de readmissão</h3>
                <p class="paciente-card-subtitle"><?= pacienteShowEsc($riskOverview['message']) ?></p>
            </div>
            <?php endif; ?>

            <div class="paciente-ai-card" id="paciente-ai-chat" data-paciente-id="<?= (int)$id_paciente ?>">
                <div class="paciente-ai-header">
                    <div>
                        <h3>Chat IA do paciente</h3>
                        <p class="paciente-card-subtitle">Perguntas limitadas aos dados deste paciente, internações e antecedentes.</p>
                    </div>
                    <span class="paciente-ai-badge" id="paciente-ai-source">
                        <i class="bi bi-stars"></i>
                        Contexto do paciente
                    </span>
                </div>
                <div class="paciente-ai-messages" id="paciente-ai-messages" aria-live="polite">
                    <div class="paciente-ai-message is-assistant">Posso responder sobre este paciente usando cadastro, histórico de internações, visitas, UTI, eventos e antecedentes registrados.</div>
                </div>
                <div class="paciente-ai-suggestions">
                    <button type="button" class="paciente-ai-suggestion" data-question="Faça um resumo objetivo deste paciente.">Resumo do paciente</button>
                    <button type="button" class="paciente-ai-suggestion" data-question="Quais internações este paciente teve e quais pontos merecem atenção?">Internações</button>
                    <button type="button" class="paciente-ai-suggestion" data-question="Quais antecedentes estão registrados para este paciente?">Antecedentes</button>
                    <button type="button" class="paciente-ai-suggestion" data-question="Há sinais operacionais de longa permanência, UTI, evento adverso ou ausência de visita recente?">Alertas operacionais</button>
                </div>
                <form class="paciente-ai-form" id="paciente-ai-form">
                    <textarea id="paciente-ai-question" placeholder="Pergunte algo sobre este paciente..." aria-label="Pergunta para IA do paciente"></textarea>
                    <button type="submit" class="btn btn-primary" id="paciente-ai-send">
                        <i class="bi bi-send"></i>
                        Enviar
                    </button>
                </form>
            </div>

            <div class="paciente-info-card">
                <h3>Identificação</h3>
                <p class="paciente-card-subtitle">Dados principais do cadastro do paciente.</p>
                <div class="paciente-field-grid">
                    <div class="paciente-field">
                        <label>Nome completo</label>
                        <div><?= pacienteShowValue($pacienteRow['nome_pac'] ?? '') ?></div>
                    </div>
                    <div class="paciente-field">
                        <label>Nome social</label>
                        <div><?= pacienteShowValue($pacienteRow['nome_social_pac'] ?? '') ?></div>
                    </div>
                    <div class="paciente-field">
                        <label>Nome da mãe</label>
                        <div><?= pacienteShowValue($pacienteRow['mae_pac'] ?? '') ?></div>
                    </div>
                    <div class="paciente-field">
                        <label>CPF</label>
                        <div><?= pacienteShowCpf($pacienteRow['cpf_pac'] ?? '') ?></div>
                    </div>
                    <div class="paciente-field">
                        <label>Sexo</label>
                        <div><?= pacienteShowValue($sexoLabel) ?></div>
                    </div>
                    <div class="paciente-field">
                        <label>Nascimento</label>
                        <div><?= pacienteShowDate($pacienteRow['data_nasc_pac'] ?? '') ?></div>
                    </div>
                    <div class="paciente-field">
                        <label>Matrícula</label>
                        <div><?= pacienteShowValue($matriculaPaciente) ?></div>
                    </div>
                    <div class="paciente-field">
                        <label>Nº atendimento</label>
                        <div><?= pacienteShowValue($pacienteRow['num_atendimento_pac'] ?? '') ?></div>
                    </div>
                    <div class="paciente-field">
                        <label>Cadastrado em</label>
                        <div><?= pacienteShowDate($pacienteRow['data_create_pac'] ?? '') ?></div>
                    </div>
                </div>
            </div>

            <div class="paciente-info-card">
                <h3>Convênio</h3>
                <p class="paciente-card-subtitle">Vínculo operacional do paciente.</p>
                <div class="paciente-field-grid">
                    <div class="paciente-field">
                        <label>Seguradora</label>
                        <div><?= pacienteShowValue($pacienteRow['seguradora_seg'] ?? '') ?></div>
                    </div>
                    <div class="paciente-field">
                        <label>Estipulante</label>
                        <div><?= pacienteShowValue($pacienteRow['nome_est'] ?? '') ?></div>
                    </div>
                    <div class="paciente-field">
                        <label>RN</label>
                        <div><?= ($pacienteRow['recem_nascido_pac'] ?? '') === 's' ? 'Sim' : 'Não' ?></div>
                    </div>
                    <div class="paciente-field">
                        <label>Mãe titular</label>
                        <div><?= pacienteShowValue($pacienteRow['mae_titular_pac'] ?? '') ?></div>
                    </div>
                    <div class="paciente-field">
                        <label>Matrícula titular</label>
                        <div><?= pacienteShowValue($pacienteRow['matricula_titular_pac'] ?? '') ?></div>
                    </div>
                    <div class="paciente-field">
                        <label>Número RN</label>
                        <div><?= pacienteShowValue($pacienteRow['numero_rn_pac'] ?? '') ?></div>
                    </div>
                </div>
            </div>

            <div class="paciente-info-card">
                <h3>Contato</h3>
                <p class="paciente-card-subtitle">Canais de comunicação registrados.</p>
                <div class="paciente-field-grid">
                    <div class="paciente-field">
                        <label>E-mail principal</label>
                        <div><?= pacienteShowValue($pacienteRow['email01_pac'] ?? '') ?></div>
                    </div>
                    <div class="paciente-field">
                        <label>E-mail secundário</label>
                        <div><?= pacienteShowValue($pacienteRow['email02_pac'] ?? '') ?></div>
                    </div>
                    <div class="paciente-field">
                        <label>Telefone principal</label>
                        <div><?= pacienteShowPhone($pacienteRow['telefone01_pac'] ?? '') ?></div>
                    </div>
                    <div class="paciente-field">
                        <label>Telefone secundário</label>
                        <div><?= pacienteShowPhone($pacienteRow['telefone02_pac'] ?? '') ?></div>
                    </div>
                </div>
            </div>

            <div class="paciente-info-card">
                <h3>Endereço</h3>
                <p class="paciente-card-subtitle">Localização principal do cadastro.</p>
                <div class="paciente-field-grid">
                    <div class="paciente-field">
                        <label>Endereço</label>
                        <div><?= pacienteShowValue($enderecoPaciente) ?></div>
                    </div>
                    <div class="paciente-field">
                        <label>Complemento</label>
                        <div><?= pacienteShowValue($pacienteRow['complemento_pac'] ?? '') ?></div>
                    </div>
                    <div class="paciente-field">
                        <label>Bairro</label>
                        <div><?= pacienteShowValue($pacienteRow['bairro_pac'] ?? '') ?></div>
                    </div>
                    <div class="paciente-field">
                        <label>Cidade</label>
                        <div><?= pacienteShowValue($pacienteRow['cidade_pac'] ?? '') ?></div>
                    </div>
                    <div class="paciente-field">
                        <label>Estado</label>
                        <div><?= pacienteShowValue($pacienteRow['estado_pac'] ?? '') ?></div>
                    </div>
                    <div class="paciente-field">
                        <label>CEP</label>
                        <div><?= pacienteShowValue($pacienteRow['cep_pac'] ?? '') ?></div>
                    </div>
                </div>
            </div>

            <?php if (trim((string)($pacienteRow['obs_pac'] ?? '')) !== ''): ?>
            <div class="paciente-info-card">
                <h3>Observações</h3>
                <div class="paciente-field-grid">
                    <div class="paciente-field" style="grid-column: 1 / -1;">
                        <label>Nota interna</label>
                        <div><?= nl2br(pacienteShowEsc($pacienteRow['obs_pac'] ?? '')) ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="paciente-danger-card">
                <div>
                    <h3>Inativar paciente</h3>
                    <p>Use esta ação apenas quando o cadastro não deve aparecer como ativo nas listas operacionais.</p>
                </div>
                <div class="paciente-actions">
                    <a href="<?= $BASE_URL ?>pacientes" class="btn btn-outline-secondary">Cancelar</a>
                    <button class="btn btn-danger" type="submit" form="delete-paciente-form" id="deletar-btn" name="deletar">Inativar</button>
                </div>
                <form id="delete-paciente-form" action="process_paciente.php?id_paciente=<?= (int)$id_paciente ?>" method="POST" style="display:none;">
                    <input type="hidden" name="type" value="delUpdate">
                    <input type="hidden" name="typeDel" value="delUpdate">
                    <input type="hidden" name="id_paciente" value="<?= (int)$id_paciente ?>">
                </form>
            </div>
        </section>
    </div>
</main>

<script>
(function () {
    const chat = document.getElementById('paciente-ai-chat');
    if (!chat) return;

    const endpoint = <?= json_encode($BASE_URL . 'ajax/paciente_chat.php', JSON_UNESCAPED_SLASHES) ?>;
    const pacienteId = Number(chat.dataset.pacienteId || 0);
    const form = document.getElementById('paciente-ai-form');
    const textarea = document.getElementById('paciente-ai-question');
    const messages = document.getElementById('paciente-ai-messages');
    const sendBtn = document.getElementById('paciente-ai-send');
    const source = document.getElementById('paciente-ai-source');

    function appendMessage(kind, text) {
        const el = document.createElement('div');
        el.className = 'paciente-ai-message ' + (kind === 'user' ? 'is-user' : 'is-assistant');
        el.textContent = text;
        messages.appendChild(el);
        messages.scrollTop = messages.scrollHeight;
        return el;
    }

    async function ask(question) {
        const cleanQuestion = (question || '').trim();
        if (!cleanQuestion) {
            textarea.focus();
            return;
        }

        appendMessage('user', cleanQuestion);
        textarea.value = '';
        const loading = appendMessage('assistant', 'Consultando os registros deste paciente...');
        sendBtn.disabled = true;
        sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Enviando';

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                credentials: 'same-origin',
                body: JSON.stringify({
                    id_paciente: pacienteId,
                    question: cleanQuestion
                })
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.error || 'Não foi possível responder agora.');
            }
            loading.textContent = data.answer || 'Sem resposta disponível.';
            if (data.summary && data.summary.source) {
                source.innerHTML = '<i class="bi bi-stars"></i>' + (data.summary.source === 'openai' ? 'IA ativa' : 'Leitura local');
            }
        } catch (error) {
            loading.textContent = error.message || 'Não foi possível responder agora.';
        } finally {
            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i class="bi bi-send"></i> Enviar';
            textarea.focus();
        }
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        ask(textarea.value);
    });

    chat.querySelectorAll('[data-question]').forEach(function (button) {
        button.addEventListener('click', function () {
            ask(button.dataset.question || '');
        });
    });
})();
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>
<?php include_once("templates/footer.php"); ?>
