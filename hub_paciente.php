<?php
include_once("check_logado.php");
include_once("templates/header.php");
include_once("models/message.php");

include_once("models/seguradora.php");
include_once("dao/seguradoraDao.php");

include_once("models/estipulante.php");
include_once("dao/estipulanteDao.php");

include_once("models/paciente.php");
include_once("dao/pacienteDao.php");
require_once("app/services/ReadmissionRiskService.php");


include_once("models/internacao.php");      // se existir
include_once("dao/internacaoDao.php");      // o seu DAO
require_once("app/services/AuditorActionService.php");

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}
$normCargoAccess = function ($txt) {
  $txt = mb_strtolower(trim((string)$txt), 'UTF-8');
  $c = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $txt);
  $txt = $c !== false ? $c : $txt;
  return preg_replace('/[^a-z]/', '', $txt);
};
$isGestorSeguradora = (strpos($normCargoAccess($_SESSION['cargo'] ?? ''), 'seguradora') !== false);
$isAuditorPacienteHub = AuditorActionService::canUseOperationalSearch($_SESSION);
if ($isGestorSeguradora && empty($_SESSION['fk_seguradora_user'])) {
  try {
    $uid = (int)($_SESSION['id_usuario'] ?? 0);
    if ($uid > 0) {
      $stmtSeg = $conn->prepare("SELECT fk_seguradora_user FROM tb_user WHERE id_usuario = :id LIMIT 1");
      $stmtSeg->bindValue(':id', $uid, PDO::PARAM_INT);
      $stmtSeg->execute();
      $sid = (int)($stmtSeg->fetchColumn() ?: 0);
      if ($sid > 0) $_SESSION['fk_seguradora_user'] = $sid;
    }
  } catch (Throwable $e) {
    error_log('[HUB_PAC][SEGURADORA] ' . $e->getMessage());
  }
}

$internacaoDao = new internacaoDAO($conn, $BASE_URL);  // ajuste o nome da classe se diferente
// DAOs
$pacienteDao = new pacienteDAO($conn, $BASE_URL);
$seguradoraDao = new seguradoraDAO($conn, $BASE_URL);
$estipulanteDao = new estipulanteDAO($conn, $BASE_URL);

// Debug opcional de timing (?debug_timing=1)
$debugTiming = filter_input(INPUT_GET, 'debug_timing');
$hubT0 = microtime(true);
$hubLog = function (string $label) use ($debugTiming, $hubT0) {
  if ($debugTiming) {
    $elapsed = (int) round((microtime(true) - $hubT0) * 1000);
    error_log("hub_paciente timing: {$label} ({$elapsed} ms)");
  }
};

// GET
$id_paciente = filter_input(INPUT_GET, "id_paciente");
if (!$id_paciente) {
  echo "<div class='container mt-4'><div class='alert alert-danger'>Paciente não informado.</div></div>";
  include_once("templates/footer.php");
  exit;
}
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$rahAfterSave = $_SESSION['rah_after_save'] ?? null;
if ($rahAfterSave && ((int)($rahAfterSave['patient_id'] ?? 0) !== (int)$id_paciente)) {
    $rahAfterSave = null;
}
if ($rahAfterSave) {
    unset($_SESSION['rah_after_save']);
}
$preloadThreshold = 50;
$totalInternacoes = $internacaoDao->countByPaciente((int)$id_paciente);
$hubLog('countByPaciente');
$preloadEnabled = $totalInternacoes <= $preloadThreshold;
$internacoes = $preloadEnabled
  ? $internacaoDao->listByPaciente((int)$id_paciente, $totalInternacoes ?: 1, 0)
  : null; // se não preload, AJAX assume
$hubLog('listByPaciente');

// Dados do paciente
$paciente = $pacienteDao->findById($id_paciente); // seu findById retorna array-like com $paciente['0'][campo]
if (!$paciente || !isset($paciente['0'])) {
  echo "<div class='container mt-4'><div class='alert alert-warning'>Paciente não encontrado.</div></div>";
  include_once("templates/footer.php");
  exit;
}
$p = $paciente['0'];
if ($isGestorSeguradora) {
  $segUserId = (int)($_SESSION['fk_seguradora_user'] ?? 0);
  $segPacId = (int)($p['fk_seguradora_pac'] ?? 0);
  if (!$segUserId || $segUserId !== $segPacId) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Acesso negado para este paciente.</div></div>";
    include_once("templates/footer.php");
    exit;
  }
}

// Helpers de formatação (iguais aos seus)
function formatCpf($cpf)
{
  if (!empty($cpf)) {
    $cpf = preg_replace("/\D/", '', $cpf);
    if (strlen($cpf) == 11) {
      return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }
  }
  return $cpf;
}
function formatCep($cep)
{
  if (!empty($cep)) {
    $cep = preg_replace("/\D/", '', $cep);
    if (strlen($cep) == 8) {
      return substr($cep, 0, 5) . '-' . substr($cep, 5, 3);
    }
  }
  return $cep;
}
function formatPhone($phone)
{
  if (!empty($phone)) {
    $phone = preg_replace("/\D/", '', $phone);
    if (strlen($phone) == 11) {
      return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 5) . '-' . substr($phone, 7, 4);
    } elseif (strlen($phone) == 10) {
      return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 4) . '-' . substr($phone, 6, 4);
    }
  }
  return $phone;
}
function formatDateBr($dateYmd)
{
  if (!$dateYmd || $dateYmd == '0000-00-00')
    return '';
  $dt = DateTime::createFromFormat('Y-m-d', $dateYmd);
  return $dt ? $dt->format('d/m/Y') : $dateYmd;
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

  if ((int)($indicadores['long_stay'] ?? 0) > 0) {
    $score += 6;
  }
  if ((int)($indicadores['eventos_adversos'] ?? 0) > 0) {
    $score += min(12, $indicadores['eventos_adversos'] * 4);
  }

  $score = max(5, min($score, 95));
  $prob = logisticProbFromScore($score);
  $riskLevel = $prob >= 0.7 ? 'alto' : ($prob >= 0.45 ? 'moderado' : 'baixo');
  $recommendations = [];
  if ($riskLevel === 'alto') {
    $recommendations[] = 'Visita presencial prioritária e revisão do plano terapêutico.';
    $recommendations[] = 'Confirmar rede de apoio para continuidade domiciliar.';
  } elseif ($riskLevel === 'moderado') {
    $recommendations[] = 'Programar visita extra ou telemonitoramento.';
  } else {
    $recommendations[] = 'Continuar rotina padrão e monitorar eventos.';
  }

  $expParts = [];
  $expParts[] = "Idade {$idade} anos";
  $expParts[] = "{$anteced} antecedente(s)";
  $expParts[] = "{$totalIntern} internações históricas";
  if ($mediaDias > 0) $expParts[] = "média de permanência {$mediaDias} dias";
  if ((int)($indicadores['eventos_adversos'] ?? 0) > 0) $expParts[] = "eventos adversos registrados";

  return [
    'available' => true,
    'fallback' => true,
    'probability' => $prob,
    'risk_level' => $riskLevel,
    'threshold' => 0.55,
    'internacao_referencia' => null,
    'explanation' => 'Estimativa baseada em histórico consolidado: ' . implode(', ', $expParts) . '.',
    'recommendations' => $recommendations,
    'message' => 'Estimativa gerada pelo histórico do paciente.'
  ];
}

// Campos formatados
$cpf_fmt = formatCpf($p['cpf_pac'] ?? '');
$tel1_fmt = formatPhone($p['telefone01_pac'] ?? '');
$tel2_fmt = formatPhone($p['telefone02_pac'] ?? '');
$cep_fmt = formatCep($p['cep_pac'] ?? '');
$nasc_fmt = formatDateBr($p['data_nasc_pac'] ?? '');

// Matrícula formatada considerando RN: MATRICULA + "RN"(se s) + numero_rn_pac
$mat_base = trim((string) ($p['matricula_pac'] ?? ''));
$mat_rn_flag = ($p['recem_nascido_pac'] ?? '') === 's';
$mat_rn_num = isset($p['numero_rn_pac']) && $p['numero_rn_pac'] !== '' ? (string) $p['numero_rn_pac'] : '';
$mat_full = $mat_base . ($mat_rn_flag ? ('RN' . $mat_rn_num) : '');

// Seguradora/Estipulante (se seu findById já deu join, ótimo — senão traga os nomes por ID aqui)
$seguradora_nome = $p['seguradora_seg'] ?? '';
$estipulante_nome = $p['nome_est'] ?? '';

// Endereço compacto
$endereco_fmt = trim(($p['endereco_pac'] ?? '') . ', ' . ($p['numero_pac'] ?? '')) .
  ' - ' . trim(($p['bairro_pac'] ?? '')) .
  ' - ' . trim(($p['cidade_pac'] ?? '') . '/' . ($p['estado_pac'] ?? '')) .
  ' - CEP ' . $cep_fmt;

// Recen nascido
$recem_nascido_pac = $p['recem_nascido_pac'];
$numero_recem_nascido_pac = $p['numero_rn_pac'];

// Iniciais
$nome_str = trim((string) ($p['nome_pac'] ?? ''));
$ini = '';
if ($nome_str) {
  $parts = preg_split('/\s+/', $nome_str);
  $ini = strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1));
}

$riskOverview = ['available' => false];
$cargoSessaoPaciente = trim((string)($_SESSION['cargo'] ?? ''));
$cargoNorm = (string)@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $cargoSessaoPaciente);
$cargoNorm = mb_strtolower($cargoNorm !== false ? $cargoNorm : $cargoSessaoPaciente, 'UTF-8');
$cargoNorm = preg_replace('/[^a-z]/', '', $cargoNorm);
$allowedCargos = [
  'medauditor', 'medico', 'medicoauditor', 'medicoauditoria', 'doctor',
  'enfauditor', 'enfermeiroauditor', 'enfermeiro', 'enfermeirora',
  'diretor', 'diretoria', 'administrador', 'admin', 'board'
];
$showClinicalGroups = $cargoNorm !== '' && in_array($cargoNorm, $allowedCargos, true);
$riskInternacaoId = null;
// Tenta limitar tempo das consultas (quando suportado pelo MySQL/MariaDB)
try {
  $conn->exec("SET SESSION MAX_EXECUTION_TIME=5000");
} catch (Throwable $e) {
  // ignora se o servidor não suportar
}
try {
  $stmtLast = $conn->prepare("
      SELECT id_internacao
        FROM tb_internacao
       WHERE fk_paciente_int = :pac
       ORDER BY COALESCE(data_intern_int, '0000-00-00') DESC, id_internacao DESC
       LIMIT 1
    ");
  $stmtLast->bindValue(':pac', (int)$id_paciente, PDO::PARAM_INT);
  $stmtLast->execute();
  $riskInternacaoId = (int)($stmtLast->fetchColumn() ?: 0);
  if ($riskInternacaoId) {
    $riskService = new ReadmissionRiskService($conn);
    $riskOverview = $riskService->scoreInternacao($riskInternacaoId);
    $riskOverview['internacao_referencia'] = $riskInternacaoId;
    $hubLog('scoreInternacao');
  } else {
    $riskOverview = [
      'available' => false,
      'message' => 'Ainda não há internações para estimar o risco.'
    ];
  }
} catch (Throwable $e) {
  $riskOverview = [
    'available' => false,
    'message' => 'Não foi possível calcular o risco de readmissão.'
  ];
}

$indicadoresPaciente = [
  'total_internacoes' => 0,
  'media_permanencia' => 0.0,
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
          CASE 
            WHEN GREATEST(DATEDIFF(COALESCE(al.data_alta_alt, CURRENT_DATE), ac.data_intern_int), 0) >= 20
            THEN 1 ELSE 0
          END
        ) AS longos
      FROM tb_internacao ac
      LEFT JOIN tb_alta al ON al.fk_id_int_alt = ac.id_internacao
      WHERE ac.fk_paciente_int = :pac
    ");
  $stmtResumo->bindValue(':pac', (int)$id_paciente, PDO::PARAM_INT);
  $stmtResumo->execute();
  $hubLog('indicadoresResumo');
  $rowResumo = $stmtResumo->fetch(PDO::FETCH_ASSOC) ?: [];
  $indicadoresPaciente['total_internacoes'] = (int)($rowResumo['total_int'] ?? 0);
  $indicadoresPaciente['media_permanencia'] = round((float)($rowResumo['media_dias'] ?? 0), 1);
  $indicadoresPaciente['ultima_internacao'] = $rowResumo['ultima_data'] ?? null;
  $indicadoresPaciente['long_stay'] = (int)($rowResumo['longos'] ?? 0);

  $stmtEventos = $conn->prepare("
      SELECT COUNT(*) FROM tb_gestao ge
      INNER JOIN tb_internacao ac ON ac.id_internacao = ge.fk_internacao_ges
      WHERE ac.fk_paciente_int = :pac
        AND LOWER(IFNULL(ge.evento_adverso_ges,'')) = 's'
    ");
  $stmtEventos->bindValue(':pac', (int)$id_paciente, PDO::PARAM_INT);
  $stmtEventos->execute();
  $hubLog('indicadoresEventos');
  $indicadoresPaciente['eventos_adversos'] = (int)$stmtEventos->fetchColumn();

  $stmtAnt = $conn->prepare("
      SELECT COUNT(*) FROM tb_intern_antec WHERE fk_id_paciente = :pac
    ");
  $stmtAnt->bindValue(':pac', (int)$id_paciente, PDO::PARAM_INT);
  $stmtAnt->execute();
  $hubLog('indicadoresAntecedentes');
  $indicadoresPaciente['antecedentes'] = (int)$stmtAnt->fetchColumn();
} catch (Throwable $e) {
  // Mantém valores padrão silenciosamente
}

if (!isset($riskOverview) || empty($riskOverview['available'])) {
  $fallback = fallbackRiskFromIndicadores($p, $indicadoresPaciente);
  if ($fallback) {
    $riskOverview = $fallback;
  } else {
    $riskOverview = ['risk_level' => 'baixo', 'probability' => 0, 'threshold' => 0.55, 'available' => false];
  }
}

$riskLevel = strtolower((string)($riskOverview['risk_level'] ?? 'baixo'));
$riskColor = [
  'alto' => ['bg' => '#ffe0e3', 'border' => '#c9184a', 'text' => '#5a071d'],
  'moderado' => ['bg' => '#fff5d6', 'border' => '#f0a500', 'text' => '#6a4900'],
  'baixo' => ['bg' => '#e6fff4', 'border' => '#0f8f5d', 'text' => '#065238']
];
$complexMap = [
  'alto' => ['label' => 'Alta complexidade', 'prioridade' => 'Visita prioritária (<24h)'],
  'moderado' => ['label' => 'Complexidade intermediária', 'prioridade' => 'Reforçar visita / contato'],
  'baixo' => ['label' => 'Baixa complexidade', 'prioridade' => 'Rotina usual']
];

$effectiveLevel = isset($riskColor[$riskLevel]) ? $riskLevel : 'baixo';
$auditorPatientSnapshot = ['counts' => [], 'pending' => [], 'timeline' => []];
if ($isAuditorPacienteHub) {
  $auditorActionService = new AuditorActionService($conn, $BASE_URL);
  $auditorPatientSnapshot = $auditorActionService->patientSnapshot((int)$id_paciente, $_SESSION);
}
?>
<!-- Você já tem Bootstrap do header.php. Aqui só estrutura da página -->

<div class="container-fluid py-2 hub-page">
  <div id="hubPaciente" data-id="<?= $p['id_paciente'] ?>"></div>

  <!-- Cabeçalho do paciente -->
  <div class="card shadow-sm mb-2 hub-patient-card" style="border-radius:14px;">
    <div class="card-body d-flex flex-wrap gap-2 align-items-center justify-content-between">
      <div class="d-flex gap-2 align-items-center">
        <div class="hub-patient-avatar">
          <?= $ini ?: 'PA' ?>
        </div>
        <div>
          <h5 class="mb-1"><?= htmlspecialchars($nome_str ?: '—') ?></h5>
          <div class="d-flex flex-wrap gap-2 text-secondary small">
            <span><i class="bi bi-person-badge me-1"></i>Matrícula:
              <?= htmlspecialchars($mat_full ?: '—') ?></span>
            <span>•</span>
            <span><i class="bi bi-calendar-event me-1"></i>Nasc.:
              <?= htmlspecialchars($nasc_fmt ?: '—') ?></span>
            <span>•</span>
            <span><i
                class="bi bi-person me-1"></i><?= htmlspecialchars(strtoupper($p['sexo_pac'] ?? '')) ?></span>
            <span>•</span>
            <span><i class="bi bi-shield-check me-1"></i>Seg.:
              <?= htmlspecialchars($seguradora_nome ?: '—') ?></span>
            <span>•</span>
            <?php if ($estipulante_nome): ?>
              <span><i class="bi bi-briefcase-fill me-1"></i>Estip.:
                <?= htmlspecialchars($estipulante_nome) ?></span>
            <?php endif; ?>
            <?php if ($recem_nascido_pac !== null): ?>
              <span>•</span>
              <span>
                <i class="bi bi-star-fill me-1"></i>
                RN: <?= $recem_nascido_pac == 's' ? 'Sim' : 'Não' ?>
                <?php if (!empty($numero_recem_nascido_pac)): ?>
                  - Nº <?= htmlspecialchars($numero_recem_nascido_pac) ?>
                <?php endif; ?>
              </span>
            <?php endif; ?>

          </div>
          <?php if (!empty($riskOverview['available'])): ?>
            <?php
        $badgePalette = $riskColor[$effectiveLevel];
        $badgeInfo = $complexMap[$effectiveLevel];
      ?>
            <div class="mt-1">
              <span class="hub-risk-badge" style="background:<?= $badgePalette['bg'] ?>;color:<?= $badgePalette['text'] ?>;border:1px solid <?= $badgePalette['border'] ?>;">
                <i class="bi bi-lightning-fill"></i>
                <?= $badgeInfo['label'] ?> — <?= $badgeInfo['prioridade'] ?>
              </span>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <div class="d-flex flex-column text-end"></div>
  </div>
</div>

<?php
$palette = $riskColor[$effectiveLevel];
$probPct = number_format((float)($riskOverview['probability'] ?? 0) * 100, 1, ',', '.');
$thresholdPct = number_format((float)($riskOverview['threshold'] ?? 0.55) * 100, 0);
$ultimaInternFmt = $indicadoresPaciente['ultima_internacao']
  ? formatDateBr($indicadoresPaciente['ultima_internacao'])
  : '—';
$complexInfo = $complexMap[$effectiveLevel];
?>

<?php if ($showClinicalGroups): ?>
<div class="row g-1 mb-1 hub-clinical-row">
  <div class="col-12 col-lg-7">
    <div class="card shadow-sm h-100 hub-compact-card hub-compact-card--primary" style="border-radius:16px;color:<?= $palette['text'] ?>;">
      <div class="card-body d-flex flex-column h-100 hub-compact-primary-body">
        <div class="row g-1 flex-grow-1 align-items-start">
          <div class="col-12 col-md-6 hub-compact-left">
            <div class="d-flex justify-content-between align-items-start mb-1">
              <small class="text-uppercase fw-semibold" style="letter-spacing:.08em;color:<?= $palette['text'] ?>;">Indicador de readmissão</small>
              <i class="bi bi-graph-up" style="font-size:1.2rem;"></i>
            </div>
            <?php if (!empty($riskOverview['available'])): ?>
              <div class="hub-compact-big"><?= $probPct ?>%</div>
              <div class="small">
                Nível <?= strtoupper($riskLevel ?: 'BAIXO') ?> • limiar <?= $thresholdPct ?>%
              </div>
              <?php $refIntern = $riskOverview['internacao_referencia'] ?? null; ?>
              <div class="small">
                Ref. internação <?= $refIntern ? '#' . (int)$refIntern : '—' ?>
              </div>
            <?php else: ?>
              <div class="small mt-2"><?= htmlspecialchars($riskOverview['message'] ?? 'Sem dados suficientes.', ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
          </div>
          <div class="col-12 col-md-6">
            <?php if (!empty($riskOverview['available'])): ?>
              <div class="small mb-1 text-dark" style="opacity:.85;">
                <?= htmlspecialchars($riskOverview['explanation'] ?? '', ENT_QUOTES, 'UTF-8') ?>
              </div>
              <?php if (!empty($riskOverview['recommendations']) && is_array($riskOverview['recommendations'])): ?>
                <ul class="small mb-0 ps-3 hub-compact-recos">
                  <?php foreach (array_slice($riskOverview['recommendations'], 0, 3) as $rec): ?>
                    <li><?= htmlspecialchars($rec, ENT_QUOTES, 'UTF-8') ?></li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
        <?php if (!empty($riskOverview['available'])): ?>
          <div class="hub-compact-badge-bottom">
            <span class="badge-compact" style="display:inline-flex;align-items:center;gap:6px;border-radius:999px;font-weight:600;background:rgba(255,255,255,.55);color:<?= $palette['text'] ?>;border:1px solid rgba(0,0,0,.05);">
              <i class="bi bi-lightning-fill"></i>
              <span><?= $complexInfo['label'] ?> · <?= $complexInfo['prioridade'] ?></span>
            </span>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-5">
    <div class="card shadow-sm h-100 hub-compact-card hub-compact-card--neutral" style="border-radius:16px;">
      <div class="card-body hub-compact-right">
        <small class="text-uppercase text-muted fw-semibold" style="letter-spacing:.08em;">Indicadores clínicos</small>
        <div class="row mt-1 gy-1 text-secondary fw-semibold">
          <div class="col-sm-6 col-xl-4">
            <div class="small text-muted">Total de internações</div>
            <div class="hub-compact-metric"><?= (int)$indicadoresPaciente['total_internacoes'] ?></div>
          </div>
          <div class="col-sm-6 col-xl-4">
            <div class="small text-muted">Média de permanência</div>
            <div class="hub-compact-metric"><?= number_format($indicadoresPaciente['media_permanencia'], 1, ',', '.') ?> dias</div>
          </div>
          <div class="col-sm-6 col-xl-4">
            <div class="small text-muted">Longa permanência (&ge;20d)</div>
            <div class="hub-compact-metric"><?= (int)$indicadoresPaciente['long_stay'] ?></div>
          </div>
          <div class="col-sm-6 col-xl-4">
            <div class="small text-muted">Eventos adversos</div>
            <div class="hub-compact-metric"><?= (int)$indicadoresPaciente['eventos_adversos'] ?></div>
          </div>
          <div class="col-sm-6 col-xl-4">
            <div class="small text-muted">Antecedentes registrados</div>
            <div class="hub-compact-metric"><?= (int)$indicadoresPaciente['antecedentes'] ?></div>
          </div>
          <div class="col-sm-6 col-xl-4">
            <div class="small text-muted">Última internação</div>
            <div class="hub-compact-metric"><?= htmlspecialchars($ultimaInternFmt) ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($isAuditorPacienteHub): ?>
<?php
  $audHubCounts = $auditorPatientSnapshot['counts'] ?? [];
  $audHubPending = $auditorPatientSnapshot['pending'] ?? [];
  $audHubTimeline = $auditorPatientSnapshot['timeline'] ?? [];
  $fmtTimelineDate = function ($date) {
    if (!$date || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') return '—';
    try {
      return (new DateTime((string)$date))->format('d/m/Y');
    } catch (Throwable $e) {
      return '—';
    }
  };
?>
<div class="row g-1 mb-1 auditor-hub-row">
  <div class="col-12 col-xl-5">
    <div class="card shadow-sm h-100 auditor-hub-card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
          <div>
            <small class="text-uppercase text-muted fw-semibold" style="letter-spacing:.08em;">Pendências do auditor</small>
            <h6 class="mb-0">Próximas ações</h6>
          </div>
          <span class="badge bg-light text-dark border"><?= count($audHubPending) ?> item(ns)</span>
        </div>
        <div class="auditor-hub-kpis">
          <div><small>Internações ativas</small><strong><?= (int)($audHubCounts['internacoes_ativas'] ?? 0) ?></strong></div>
          <div><small>Visitas atrasadas</small><strong><?= (int)($audHubCounts['visitas_atrasadas'] ?? 0) ?></strong></div>
          <div><small>Eventos</small><strong><?= (int)($audHubCounts['eventos_abertos'] ?? 0) ?></strong></div>
          <div><small>Contas</small><strong><?= (int)($audHubCounts['contas_pendentes'] ?? 0) ?></strong></div>
        </div>
        <div class="auditor-hub-pending">
          <?php if (!$audHubPending): ?>
            <div class="text-muted small text-center py-3">Sem pendências operacionais para este paciente.</div>
          <?php else: ?>
            <?php foreach ($audHubPending as $item): ?>
              <a href="<?= htmlspecialchars((string)$item['action_url'], ENT_QUOTES, 'UTF-8') ?>" class="auditor-hub-task">
                <span class="auditor-hub-task__icon <?= htmlspecialchars((string)$item['severity'], ENT_QUOTES, 'UTF-8') ?>">
                  <i class="bi <?= htmlspecialchars((string)$item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                </span>
                <span>
                  <strong><?= htmlspecialchars((string)$item['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                  <small><?= htmlspecialchars((string)$item['detail'], ENT_QUOTES, 'UTF-8') ?></small>
                </span>
                <i class="bi bi-chevron-right"></i>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <div class="col-12 col-xl-7">
    <div class="card shadow-sm h-100 auditor-hub-card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
          <div>
            <small class="text-uppercase text-muted fw-semibold" style="letter-spacing:.08em;">Linha do tempo operacional</small>
            <h6 class="mb-0">Eventos recentes do paciente</h6>
          </div>
        </div>
        <div class="auditor-hub-timeline">
          <?php if (!$audHubTimeline): ?>
            <div class="text-muted small text-center py-3">Sem eventos recentes para exibir.</div>
          <?php else: ?>
            <?php foreach ($audHubTimeline as $event): ?>
              <a href="<?= htmlspecialchars((string)($event['url'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>" class="auditor-hub-event">
                <span class="auditor-hub-event__date"><?= htmlspecialchars($fmtTimelineDate($event['data_ref'] ?? null), ENT_QUOTES, 'UTF-8') ?></span>
                <span class="auditor-hub-event__body">
                  <strong><?= htmlspecialchars((string)($event['titulo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                  <small><?= htmlspecialchars((string)($event['tipo'] ?? ''), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string)($event['detalhe'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small>
                </span>
                <i class="bi bi-arrow-up-right"></i>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

  <!-- Abas -->
  <div class="card shadow-sm hub-history-card" style="border-radius:14px;">
    <div class="card-body">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <ul class="nav nav-pills mb-0" role="tablist">

          <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-internacoes" type="button"
              role="tab">
              <i class="bi bi-hospital me-2"></i>Internações
            </button>
          </li>


          <li class="nav-item">
            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-contas" type="button"
              role="tab">
              <i class="bi bi-receipt me-2"></i>Contas
            </button>
          </li>
        </ul>
        <div class="hub-int-actions d-flex align-items-center gap-2">
          <div class="input-group input-group-sm hub-int-filter">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input id="buscaInternacoes" type="text" class="form-control" placeholder="Filtrar...">
          </div>
          <?php if (!$isGestorSeguradora) { ?>
            <a class="btn btn-sm btn-primary hub-new-int-btn" href="<?= $BASE_URL ?>internacoes/nova/paciente/<?= (int)$p['id_paciente'] ?>">
              <i class="bi bi-plus me-1"></i> Nova Internação
            </a>
          <?php } ?>
        </div>
      </div>

      <div class="tab-content">
        <div class="tab-pane fade" id="tab-overview" role="tabpanel">
          <div class="alert alert-light border text-secondary">
            Carregando...
          </div>
        </div>

        <!-- Internações -->
        <div class="tab-pane fade show active" id="tab-internacoes" role="tabpanel">
          <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-2">
            <h6 class="mb-0">Histórico de internações</h6>
          </div>

          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle" id="tblInternacoes">
              <thead>
                <tr>
                  <th>ID-INT</th>
                  <th>Senha</th> <!-- NOVA COLUNA -->
                  <th>Admissão</th>
                  <th>Alta</th>
                  <th>Unidade</th>
                  <th>Status</th>
                  <th>Visitas</th>
                  <th>Prorrog.</th>
                  <th>Negoc.</th>
                  <th>Ações</th>
                </tr>
              </thead>

              <tbody>
                <!-- preenchido via JS -->
              </tbody>
            </table>
          </div>

          <div class="d-flex justify-content-between align-items-center">
            <small id="int-total"></small>
            <nav>
              <ul class="pagination pagination-sm mb-0" id="int-pager"></ul>
            </nav>
          </div>
        </div>




        <!-- Antecedentes -->
        <div class="tab-pane fade" id="tab-antecedentes" role="tabpanel">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0">Antecedentes e condições</h6>
            <?php if (!$isGestorSeguradora) { ?>
              <button class="btn btn-outline-secondary btn-sm"><i
                  class="bi bi-plus me-2"></i>Novo</button>
            <?php } ?>
          </div>
          <div id="chipsAntecedentes" class="d-flex flex-wrap gap-2">
            <!-- chips com antecedentes (ex.: HAS, DM2, etc.) -->
          </div>
        </div>


        <!-- Autorizações -->
        <div class="tab-pane fade" id="tab-autorizacoes" role="tabpanel">
          <div class="alert alert-light border text-secondary">Sem registros</div>
        </div>

        <!-- Contas -->
        <div class="tab-pane fade" id="tab-contas" role="tabpanel">
          <div class="row g-3 contas-summary">
            <div class="col-12 col-lg-6">
              <div class="card shadow-sm summary-card">
                <div class="card-body py-3 px-3">
                  <h6 class="mb-1 text-uppercase text-muted small">Resumo — Valores</h6>
                  <div id="contasResumoValores" class="text-secondary small fw-semibold" style="line-height:1.4;">
                    <div><strong>Valor apresentado:</strong> —</div>
                    <div><strong>Glosa total:</strong> —</div>
                    <div><strong>Valor final:</strong> —</div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-12 col-lg-6">
              <div class="card shadow-sm summary-card">
                <div class="card-body py-3 px-3">
                  <h6 class="mb-1 text-uppercase text-muted small">Resumo — Indicadores</h6>
                  <div id="contasResumoIndicadores" class="text-secondary small fw-semibold" style="line-height:1.4;">
                    <div><strong>Total de contas:</strong> —</div>
                    <div><strong>Total de internações:</strong> —</div>
                    <div><strong>Custo médio / conta:</strong> —</div>
                    <div><strong>Custo médio / internação:</strong> —</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div><!-- /tab-content -->
    </div>
  </div>
</div>

<!-- Modal genérico para cadastros -->
<div class="modal fade" id="hubModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xxl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Cadastro</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <div id="content-php"></div>
      </div>
    </div>
  </div>
</div>

<!-- Modal de visualização do RAH -->
<div class="modal fade" id="rahPreviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Visualizar RAH</h5>
      </div>
      <div class="modal-body" style="min-height:70vh;">
        <div class="ratio ratio-16x9 border rounded bg-light">
          <iframe id="rahPreviewFrame" title="Pré-visualização do RAH" style="border:0;" allowfullscreen></iframe>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  document.getElementById('rahPreviewModal')?.addEventListener('hidden.bs.modal', function() {
    const frame = document.getElementById('rahPreviewFrame');
    if (frame) frame.src = '';
  });
</script>

</div>

<script>
  (function() {
    try {
      window.addEventListener('load', function() {
      });
      setTimeout(function() {
      }, 3000);
    } catch (e) {}
  })();
</script>

<style>
  :root {
    --brand: #5e2363;
    --brand-700: #4b1c50;
    --brand-800: #431945;
    --brand-100: #f2e8f7;
    --brand-050: #f9f3fc;
  }

  body {
    background: #f5f8fc;
  }

  .hub-page {
    width: 100%;
    max-width: none;
    margin: 0;
    padding: 10px 16px 78px !important;
  }

  .hub-page .row {
    --bs-gutter-x: .45rem;
    --bs-gutter-y: .45rem;
  }

  .hub-page .card {
    border-radius: 10px !important;
  }

  .hub-page .card.shadow-sm {
    box-shadow: 0 4px 12px rgba(23, 40, 70, .07) !important;
  }

  .hub-patient-card {
    position: relative;
    border: 1px solid rgba(94, 35, 99, .12);
    overflow: hidden;
  }

  .hub-patient-card::before {
    content: "";
    display: block;
    width: 6px;
    align-self: stretch;
    background: linear-gradient(180deg, var(--brand), #2f6f9f);
    position: absolute;
    inset: 0 auto 0 0;
  }

  .hub-patient-card .card-body {
    min-height: 0 !important;
    padding: 9px 14px 9px 18px !important;
  }

  .hub-patient-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #ecd5f9;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: #5e2363;
    flex: 0 0 38px;
    font-size: .78rem;
  }

  .hub-patient-card h5 {
    font-size: .9rem;
    line-height: 1.2;
    font-weight: 800;
  }

  .hub-patient-card .small {
    font-size: .66rem !important;
    line-height: 1.18;
  }

  .hub-risk-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 2px 7px;
    border-radius: 999px;
    font-size: .62rem;
    font-weight: 700;
    line-height: 1.1;
  }

  .hub-clinical-row .card,
  .auditor-hub-row .card,
  .hub-history-card {
    min-height: 0 !important;
  }

  .auditor-hub-card {
    border-radius: 10px;
    border: 1px solid rgba(47, 111, 159, .26);
    background: linear-gradient(180deg, #ffffff 0%, #f4faff 100%);
    box-shadow: 0 4px 12px rgba(35, 102, 147, .09) !important;
    overflow: hidden;
  }

  .auditor-hub-card .card-body {
    padding: 7px !important;
  }

  .auditor-hub-card .card-body > .d-flex:first-child {
    margin: -7px -7px 6px;
    padding: 6px 8px;
    background: linear-gradient(90deg, rgba(47, 111, 159, .18), rgba(94, 35, 99, .10));
    color: #24384f;
    border-bottom: 1px solid rgba(47, 111, 159, .20);
    position: relative;
  }

  .auditor-hub-card .card-body > .d-flex:first-child::before {
    content: "";
    position: absolute;
    left: 0;
    top: 7px;
    bottom: 7px;
    width: 3px;
    border-radius: 0 999px 999px 0;
    background: linear-gradient(180deg, #2f6f9f, #5e2363);
  }

  .auditor-hub-card .card-body > .d-flex:first-child small,
  .auditor-hub-card .card-body > .d-flex:first-child h6 {
    color: #24384f !important;
  }

  .auditor-hub-card h6 {
    font-size: .72rem !important;
  }

  .auditor-hub-card .badge {
    font-size: .62rem;
    padding: 2px 6px;
  }

  .auditor-hub-card .card-body > .d-flex:first-child .badge {
    background: rgba(255,255,255,.82) !important;
    border-color: rgba(47, 111, 159, .28) !important;
    color: #24384f !important;
  }

  .auditor-hub-kpis {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 5px;
    margin-bottom: 5px;
  }

  .auditor-hub-kpis > div {
    min-height: 36px;
    border: 1px solid rgba(47, 111, 159, .22);
    background: #ffffff;
    border-radius: 8px;
    padding: 4px 6px;
    box-shadow: none;
  }

  .auditor-hub-kpis small {
    display: block;
    color: #6b7280;
    font-size: .48rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .04em;
    line-height: 1.1;
  }

  .auditor-hub-kpis strong {
    display: block;
    color: #16324f;
    font-size: .82rem;
    line-height: 1.15;
  }

  .auditor-hub-pending,
  .auditor-hub-timeline {
    display: grid;
    gap: 5px;
  }

  .auditor-hub-task,
  .auditor-hub-event {
    color: #1d2940;
    text-decoration: none;
    border: 1px solid rgba(47, 111, 159, .18);
    border-radius: 8px;
    background: #ffffff;
    box-shadow: none;
  }

  .auditor-hub-task:hover,
  .auditor-hub-event:hover {
    color: #16324f;
    background: #edf7ff;
    border-color: rgba(47, 111, 159, .32);
  }

  .auditor-hub-task {
    display: grid;
    grid-template-columns: 24px minmax(0, 1fr) 14px;
    gap: 6px;
    align-items: center;
    padding: 5px 7px;
  }

  .auditor-hub-task__icon {
    width: 24px;
    height: 24px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 7px;
    background: #eff7ff;
    color: #2f6f9f;
  }

  .auditor-hub-task__icon.danger { background: #fff1f2; color: #be123c; }
  .auditor-hub-task__icon.warning { background: #fff7ed; color: #c2410c; }
  .auditor-hub-task__icon.info { background: #eef6ff; color: #2563eb; }
  .auditor-hub-task__icon.primary { background: var(--brand-100); color: var(--brand); }

  .auditor-hub-task strong,
  .auditor-hub-event strong {
    display: block;
    font-size: .64rem;
    line-height: 1.2;
  }

  .auditor-hub-task small,
  .auditor-hub-event small {
    display: block;
    color: #6b7280;
    font-size: .56rem;
    line-height: 1.15;
  }

  .auditor-hub-event {
    display: grid;
    grid-template-columns: 58px minmax(0, 1fr) 14px;
    gap: 6px;
    align-items: center;
    padding: 5px 7px;
  }

  .auditor-hub-event__date {
    color: #1f5f8f;
    font-size: .58rem;
    font-weight: 800;
  }

  .auditor-hub-event__body {
    min-width: 0;
  }

  @media (max-width: 720px) {
    .auditor-hub-kpis {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .auditor-hub-event {
      grid-template-columns: 1fr 18px;
    }
    .auditor-hub-event__date {
      grid-column: 1 / -1;
    }
  }

  #hubModal .modal-dialog {
    max-width: 95vw;
  }

  #hubModal .modal-body {
    min-height: 70vh;
  }

  #tab-contas {
    padding-top: .15rem;
  }

  .contas-summary {
    margin-top: .15rem;
    margin-bottom: .25rem;
    row-gap: .75rem !important;
  }

  .contas-summary .summary-card {
    border: 1px solid #ede1f4;
    border-radius: 16px;
    background: linear-gradient(180deg, #ffffff 0%, #fbf7ff 100%);
    position: relative;
    overflow: hidden;
  }

  .contas-summary .summary-card::before {
    content: "";
    position: absolute;
    top: 12px;
    bottom: 12px;
    left: 8px;
    width: 4px;
    border-radius: 999px;
    background: linear-gradient(180deg, var(--brand), #c997d2);
    opacity: .85;
  }

  .hub-compact-card .card-body {
    padding: 9px 10px 8px;
  }

  .hub-compact-card--primary .card-body {
    padding: 10px 11px 8px;
  }

  .hub-compact-primary-body {
    justify-content: flex-start;
  }

  .hub-compact-left .small {
    line-height: 1.45;
  }

  .hub-compact-left .hub-compact-big {
    margin-bottom: .15rem;
  }

  .hub-compact-card--primary .card-body {
    padding-bottom: 0;
  }

  .hub-compact-card .hub-compact-big {
    font-size: 1rem;
    font-weight: 700;
    line-height: 1;
  }

  .hub-compact-card .hub-compact-metric {
    font-size: .8rem;
    line-height: 1.1;
  }

  .hub-compact-card .small {
    font-size: .68rem;
    line-height: 1.18;
  }

  .hub-compact-card ul {
    margin-bottom: 0;
    padding-left: 1rem;
  }

  .hub-compact-card li {
    margin-bottom: 0;
  }

  .hub-compact-recos {
    line-height: 1.1;
  }

  .hub-compact-card .mb-2 {
    margin-bottom: .35rem !important;
  }

  .hub-compact-card .mt-2 {
    margin-top: .2rem !important;
  }

  .hub-compact-card--neutral {
    background: linear-gradient(180deg, #ffffff 0%, #f6f2fb 100%);
    border: 2px solid #d8c3e9;
  }

  .hub-compact-card--primary {
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.95) 0%, rgba(220, 236, 255, 0.55) 100%);
    border: 2px solid rgba(46, 126, 223, 0.35);
  }

  .hub-compact-card--primary .card-body {
    padding-bottom: 1px;
  }

  .hub-compact-card--primary .mt-2 {
    margin-top: .1rem !important;
  }

  .hub-compact-card .row {
    margin-bottom: 0;
  }

  .hub-compact-card [class*="mb-"] {
    margin-bottom: .25rem !important;
  }

  .hub-compact-card .card-body > :last-child {
    margin-bottom: 0 !important;
  }

  .hub-compact-card .card-body > .row {
    margin-bottom: 0 !important;
  }

  .hub-compact-card .badge-compact {
    padding: 2px 8px;
    font-size: .7rem;
  }

  .hub-compact-badge-wrap {
    margin-bottom: .1rem !important;
    line-height: 1;
  }

  .hub-compact-card--primary .hub-compact-badge-wrap {
    margin-bottom: .15rem !important;
  }

  .hub-compact-badge-bottom {
    margin-top: .35rem;
    display: flex;
    justify-content: center;
    padding-top: 0;
  }

  .hub-compact-right {
    padding-top: 10px;
  }

  .hub-compact-card--primary .hub-compact-recos {
    line-height: 1.6;
  }

  .hub-compact-card--primary .hub-compact-recos li {
    margin-bottom: 2px;
  }

  .contas-summary .summary-card .card-body {
    padding: 1.15rem 1.25rem 1.1rem 1.6rem !important;
  }

  .contas-summary h6 {
    letter-spacing: .04em;
    margin-bottom: .2rem !important;
  }

  #tab-contas .contas-summary + #contasWrap {
    margin-top: .25rem !important;
  }

  .contas-table-wrapper h6 {
    margin-bottom: .25rem;
  }
  #tblContas .contas-group-row td {
    background: rgba(94, 35, 99, .08);
    color: var(--brand-800);
    font-weight: 600;
    border-top: 2px solid rgba(94, 35, 99, .2);
  }
  #tblContas .badge-parcial {
    color: #b45309;
    border-color: #fcd34d;
    background: #fef3c7;
  }
  #tblContas .badge-final {
    color: #166534;
    border-color: #86efac;
    background: #ecfdf5;
  }
  #tblContas .badge-success {
    color: #166534;
    border-color: #4ade80;
    background: #dcfce7;
  }
  #tblContas .badge-danger {
    color: #991b1b;
    border-color: #fecaca;
    background: #fee2e2;
    font-size: .95rem;
    padding: .35rem .7rem;
  }
  #tblContas .badge-open-period {
    margin-left: .35rem;
  }
  #tblContas tr.conta-periodo-aberto td {
    border-left: 3px solid #fcd34d;
  }
  #tblContas tr.conta-periodo-overlap td {
    background: #fff5f5;
  }

  .btn-rah-view {
    border: none;
    color: var(--brand);
    background: rgba(94, 35, 99, .12);
    font-weight: 600;
    transition: all .2s ease;
  }

  .btn-rah-view:hover,
  .btn-rah-view:focus {
    color: #fff;
    background: var(--brand);
    box-shadow: 0 0 0 .2rem rgba(94, 35, 99, .18);
  }

  /* Botões principais */
  .btn-primary {
    background-color: var(--brand) !important;
    border-color: var(--brand) !important;
  }

  .btn-primary:hover {
    background-color: var(--brand-700) !important;
    border-color: var(--brand-700) !important;
  }

  .btn-primary:focus,
  .btn-primary:active {
    background-color: var(--brand-800) !important;
    border-color: var(--brand-800) !important;
    box-shadow: 0 0 0 .2rem rgba(94, 35, 99, .25) !important;
  }

  /* “Outline” no tom da marca (vale para os que você já usa: info/secondary) */
  .btn-outline-primary,
  .btn-outline-info,
  .btn-outline-secondary {
    color: var(--brand) !important;
    border-color: var(--brand) !important;
  }

  .btn-outline-primary:hover,
  .btn-outline-info:hover,
  .btn-outline-secondary:hover {
    color: #fff !important;
    background-color: var(--brand) !important;
    border-color: var(--brand) !important;
  }

  .btn-outline-primary:focus,
  .btn-outline-info:focus,
  .btn-outline-secondary:focus {
    box-shadow: 0 0 0 .2rem rgba(94, 35, 99, .25) !important;
  }
  #tblInternacoes [data-action="ver-int"] {
    color: #78350f;
    background-color: #fef9c3;
    border-color: #facc15;
    font-weight: 600;
  }
  #tblInternacoes [data-action="ver-int"]:hover,
  #tblInternacoes [data-action="ver-int"]:focus {
    color: #fff;
    background-color: #f59e0b;
    border-color: #d97706;
  }
  #tblInternacoes [data-action="editar-int"] {
    color: var(--brand-700);
    border-color: rgba(94, 35, 99, .35);
    background-color: rgba(94, 35, 99, .08);
    font-weight: 600;
  }
  #tblInternacoes [data-action="editar-int"]:hover,
  #tblInternacoes [data-action="editar-int"]:focus {
    color: #fff;
    background-color: var(--brand-700);
    border-color: var(--brand-700);
  }
  #tblInternacoes [data-action="alta-int"] {
    color: #065f46;
    background-color: #d1fae5;
    border-color: #34d399;
    font-weight: 600;
  }
  #tblInternacoes [data-action="alta-int"]:hover,
  #tblInternacoes [data-action="alta-int"]:focus {
    color: #fff;
    background-color: #10b981;
    border-color: #059669;
  }

  .hub-int-actions {
    min-width: min(100%, 420px);
  }

  .hub-int-filter {
    max-width: 240px;
  }

  .hub-new-int-btn {
    background-color: #2563eb !important;
    border-color: #1d4ed8 !important;
    color: #fff !important;
    font-weight: 600;
  }

  .hub-new-int-btn:hover,
  .hub-new-int-btn:focus {
    background-color: #1d4ed8 !important;
    border-color: #1e40af !important;
    color: #fff !important;
  }

  .hub-prorrog-pendente {
    margin-top: 2px;
    font-size: .74rem;
    line-height: 1.15;
    font-weight: 500;
  }

  /* Abas (nav-pills) */
  .nav-pills .nav-link {
    color: var(--brand);
  }

  .nav-pills .nav-link:hover {
    background: var(--brand-050);
  }

  .nav-pills .nav-link.active {
    background-color: var(--brand) !important;
  }

  /* Cabeçalhos de tabela suaves no tema */
  .table thead {
    background: var(--brand-100);
  }

  .table thead th {
    color: var(--brand);
    border-color: #eadcf3 !important;
    font-size: 14px;
  }

  .table td {
    font-size: 13px;
  }

  /* Paginação */
  .pagination .page-link {
    color: var(--brand);
    border-color: #e7ddef;
  }

  .pagination .page-item.active .page-link {
    color: #fff;
    background-color: var(--brand);
    border-color: var(--brand);
  }

  .pagination .page-link:hover {
    background: var(--brand-050);
    border-color: var(--brand);
  }

  /* Inputs foco */
  .form-control:focus {
    border-color: var(--brand) !important;
    box-shadow: 0 0 0 .2rem rgba(94, 35, 99, .15) !important;
  }

  .input-group-text {
    background: var(--brand-100);
    color: var(--brand);
    border-color: #eadcf3 !important;
  }

  /* Modal header no tom do sistema */
  #globalModal .modal-header {
    background: var(--brand);
    color: #fff;
  }

  /* Cards “limpinhos” */
  .card {
    border-radius: 14px;
  }

  .card.shadow-sm {
    box-shadow: 0 8px 24px rgba(0, 0, 0, .06) !important;
  }

  /* Badges da marca (se quiser usar) */
  .badge-brand {
    background: var(--brand);
    color: #fff;
  }

  /* ===== Overview cards com destaque visual ===== */
  .ov-card {
    position: relative;
    border: 0 !important;
    border-radius: 14px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, .06) !important;
    background: #fff;
  }

  /* Faixa lateral de cor */
  .ov-card::before {
    content: "";
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 6px;
    border-top-left-radius: 14px;
    border-bottom-left-radius: 14px;
    background: var(--ov-accent, var(--brand));
    opacity: .9;
  }

  /* Cabeçalho do card com ícone */
  .ov-head {
    display: flex;
    align-items: center;
    gap: .5rem;
    margin-bottom: .5rem;
  }

  .ov-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--ov-accent-100, var(--brand-100));
    color: var(--ov-accent, var(--brand));
    flex: 0 0 36px;
    font-size: 16px;
  }

  /* Título compacto e forte */
  .ov-title {
    margin: 0;
    font-weight: 600;
    color: var(--ov-accent, var(--brand));
  }

  /* Paletas suaves para cada card */
  :root {
    --teal: #0f766e;
    --teal-100: #d1fae5;
    --amber: #b45309;
    --amber-100: #fef3c7;
  }

  /* Variantes */
  .ov-int {
    --ov-accent: var(--brand);
    --ov-accent-100: var(--brand-100);
  }

  .ov-vis {
    --ov-accent: var(--teal);
    --ov-accent-100: var(--teal-100);
  }

  .ov-recent {
    --ov-accent: var(--amber);
    --ov-accent-100: var(--amber-100);
  }

  /* Badge de status com borda suave (fallback para quem não usa bg-subtle do BS 5.3) */
  .badge-soft {
    border: 1px solid currentColor;
    background: rgba(0, 0, 0, .03);
    font-weight: 600;
  }

  .hub-page .card-body .small {
    color: #4b5563;
  }

  /* Layout compacto de operação, alinhado ao detalhe da internação */
  .hub-page {
    padding: 8px 0 76px !important;
    font-size: 13px;
  }

  .hub-page > .card,
  .hub-page > .row {
    margin-left: 0 !important;
    margin-right: 0 !important;
  }

  .hub-patient-card {
    margin-bottom: 6px !important;
    border-color: #e2e8f0 !important;
    border-radius: 8px !important;
  }

  .hub-patient-card .card-body {
    padding: 8px 18px !important;
  }

  .hub-patient-avatar {
    width: 34px !important;
    height: 34px !important;
    flex-basis: 34px !important;
    font-size: .72rem !important;
  }

  .hub-patient-card h5 {
    font-size: .84rem !important;
    margin-bottom: 1px !important;
  }

  .hub-patient-card .small {
    font-size: .61rem !important;
    gap: 6px !important;
  }

  .hub-risk-badge {
    font-size: .56rem !important;
    padding: 2px 6px !important;
    margin-top: 1px;
  }

  .hub-clinical-row {
    margin-bottom: 6px !important;
  }

  .hub-compact-card {
    border-width: 1px !important;
    border-radius: 8px !important;
    box-shadow: 0 2px 7px rgba(15, 23, 42, .05) !important;
  }

  .hub-compact-card .card-body,
  .hub-compact-card--primary .card-body {
    padding: 7px 12px !important;
  }

  .hub-compact-card small.text-uppercase {
    font-size: .6rem !important;
    letter-spacing: .12em !important;
  }

  .hub-compact-card .hub-compact-big {
    font-size: .9rem !important;
  }

  .hub-compact-card .small {
    font-size: .58rem !important;
    line-height: 1.12 !important;
  }

  .hub-compact-card .hub-compact-metric {
    font-size: .66rem !important;
    line-height: 1.05 !important;
  }

  .hub-compact-card .row {
    --bs-gutter-x: .4rem;
    --bs-gutter-y: .15rem;
  }

  .hub-compact-badge-bottom {
    display: none !important;
  }

  .hub-compact-recos {
    line-height: 1.12 !important;
  }

  .auditor-hub-row {
    margin-bottom: 7px !important;
  }

  .auditor-hub-card {
    border-radius: 8px !important;
    background: #fff !important;
    box-shadow: 0 2px 7px rgba(15, 23, 42, .05) !important;
  }

  .auditor-hub-card .card-body {
    padding: 8px 10px !important;
  }

  .auditor-hub-card .card-body > .d-flex:first-child {
    background: transparent !important;
    border-bottom: 0 !important;
    margin: 0 0 5px !important;
    padding: 0 !important;
  }

  .auditor-hub-card .card-body > .d-flex:first-child::before {
    display: none;
  }

  .auditor-hub-card small.text-uppercase {
    font-size: .58rem !important;
    color: #334155 !important;
  }

  .auditor-hub-card h6 {
    font-size: .68rem !important;
    color: #1f2937 !important;
  }

  .auditor-hub-kpis {
    gap: 4px !important;
    margin-bottom: 5px !important;
  }

  .auditor-hub-kpis > div {
    min-height: 31px !important;
    border-radius: 6px !important;
    padding: 3px 6px !important;
  }

  .auditor-hub-kpis small {
    font-size: .43rem !important;
  }

  .auditor-hub-kpis strong {
    font-size: .74rem !important;
  }

  .auditor-hub-task,
  .auditor-hub-event {
    border-radius: 6px !important;
    padding: 4px 7px !important;
  }

  .auditor-hub-task {
    grid-template-columns: 22px minmax(0, 1fr) 12px !important;
  }

  .auditor-hub-task__icon {
    width: 22px !important;
    height: 22px !important;
    border-radius: 6px !important;
    font-size: .68rem !important;
  }

  .auditor-hub-task strong,
  .auditor-hub-event strong {
    font-size: .58rem !important;
  }

  .auditor-hub-task small,
  .auditor-hub-event small {
    font-size: .5rem !important;
  }

  .auditor-hub-event {
    grid-template-columns: 52px minmax(0, 1fr) 12px !important;
  }

  .auditor-hub-event__date {
    font-size: .52rem !important;
  }

  .hub-history-card {
    border-radius: 8px !important;
  }

  .hub-history-card > .card-body {
    padding: 10px 18px !important;
  }

  .hub-page .nav-pills .nav-link {
    padding: 5px 12px !important;
    font-size: .72rem !important;
    line-height: 1.15 !important;
    border-radius: 7px !important;
    font-weight: 700;
  }

  .hub-page .btn-sm,
  .hub-page .input-group-sm > .form-control,
  .hub-page .input-group-sm > .input-group-text {
    padding: 4px 8px !important;
    font-size: .68rem !important;
    min-height: 28px !important;
    line-height: 1.15 !important;
  }

  .hub-int-filter {
    max-width: 210px !important;
  }

  .hub-page h6 {
    font-size: .72rem !important;
  }

  .hub-page .table thead th {
    font-size: .62rem !important;
    padding: 5px 8px !important;
  }

  .hub-page .table td {
    font-size: .62rem !important;
    padding: 5px 8px !important;
  }
</style>

<script>
  window.BASE_URL = '<?= rtrim($BASE_URL, '/') . '/' ?>'; // ex: http://localhost/full17.2/
  window.PACIENTE_ID = <?= (int) $id_paciente ?>;
</script>
<script src="<?= $BASE_URL ?>js/hub_paciente.js?v=<?= filemtime('js/hub_paciente.js') ?>"></script>

<script>
  // Diz qual campo é a senha e injeta os dados pro JS
  window.HUB_SENHA_FIELD = 'senha_int';
  window.PRELOADED_INT = null;
  window.PRELOAD_ENABLED = false;
  window.PRELOAD_THRESHOLD = <?= (int)$preloadThreshold ?>;

  // (mantém o que você já tinha)
  window.BASE_URL = '<?= rtrim($BASE_URL, '/') . '/' ?>';
  window.PACIENTE_ID = <?= (int) $id_paciente ?>;
</script>

<?php if ($rahAfterSave && !empty($rahAfterSave['accounts_url'])): ?>
<div class="modal fade" id="rahContinueModal" tabindex="-1" aria-labelledby="rahContinueTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header">
                <h5 class="modal-title" id="rahContinueTitle">Continuar lançando?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p>Deseja seguir lançando contas ou visitas para este paciente?</p>
            </div>
            <div class="modal-footer">
                <button type="button" id="rahModalClose" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    Permanecer no hub
                </button>
                <button type="button" id="rahModalAccounts" class="btn btn-primary">
                    Ir para contas
                </button>
                <?php if (!empty($rahAfterSave['visits_url'])): ?>
                    <button type="button" id="rahModalVisits" class="btn btn-outline-primary">
                        Ir para visitas
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var modalEl = document.getElementById('rahContinueModal');
        if (!modalEl) return;
        if (!(window.bootstrap && typeof window.bootstrap.Modal === 'function')) {
            console.warn('[hub_paciente] bootstrap.Modal indisponível; pulando modal');
            return;
        }
        var modal = new bootstrap.Modal(modalEl, {backdrop: 'static', keyboard: false});
        modal.show();
        document.getElementById('rahModalAccounts').addEventListener('click', function () {
            window.location.href = <?= json_encode($rahAfterSave['accounts_url'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        });
        document.getElementById('rahModalClose').addEventListener('click', function () {
            modal.hide();
        });
        <?php if (!empty($rahAfterSave['visits_url'])): ?>
        document.getElementById('rahModalVisits').addEventListener('click', function () {
            window.location.href = <?= json_encode($rahAfterSave['visits_url'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        });
        <?php endif; ?>
    });
</script>
<?php endif; ?>
<?php include_once("templates/footer.php"); ?>
