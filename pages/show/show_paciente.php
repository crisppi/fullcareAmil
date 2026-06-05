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
?>
<script src="js/timeout.js"></script>

<div style="margin:15px" id="main-container">
    <h4 style="margin-top:20px">Dados do paciente Registro no:
        <?= $id_paciente ?>
    </h4>
    <?php if (!empty($riskOverview['available'])):
        $riskLevel = strtolower((string)($riskOverview['risk_level'] ?? ''));
        $alertClass = $riskLevel === 'alto' ? '#ffe0e3' : ($riskLevel === 'moderado' ? '#fff5d6' : '#e6fff4');
        $borderColor = $riskLevel === 'alto' ? '#c9184a' : ($riskLevel === 'moderado' ? '#f0a500' : '#0f8f5d');
        $textColor = $riskLevel === 'alto' ? '#5a071d' : ($riskLevel === 'moderado' ? '#6a4900' : '#065238');
$probPct = number_format((float)($riskOverview['probability'] ?? 0) * 100, 1, ',', '.');
$features = $riskOverview['features'] ?? [];
        $faixa = ucfirst($features['faixa_etaria'] ?? '—');
        $idade = (int)($features['idade'] ?? 0);
        $antecedentes = (int)($features['antecedentes'] ?? 0);
        $internPrev = (int)($features['internacoes_previas'] ?? 0);
        $mpPrev = number_format((float)($features['mp_previas'] ?? 0), 1, ',', '.');
        $diasAtual = (int)($features['dias_internado_atual'] ?? 0);
        $mpLimite = (int)($features['mp_limite'] ?? 0);
$eventos = (int)($features['eventos_adversos'] ?? 0);
$explanation = htmlspecialchars($riskOverview['explanation'] ?? '', ENT_QUOTES, 'UTF-8');
$complexMap = [
    'alto' => ['label' => 'Alta complexidade', 'prioridade' => 'Visita prioritária (<24h)'],
    'moderado' => ['label' => 'Complexidade intermediária', 'prioridade' => 'Visita reforçada / monitorar'],
    'baixo' => ['label' => 'Baixa complexidade', 'prioridade' => 'Seguir rotina padrão']
];
$complexInfo = $complexMap[$riskLevel ?: 'baixo'];
?>
    <div style="border:2px solid <?= $borderColor ?>; background:<?= $alertClass ?>; color:<?= $textColor ?>; border-radius:12px; padding:18px; margin-bottom:18px;">
        <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:16px;">
            <div>
                <div style="text-transform:uppercase; font-size:0.8rem; font-weight:600;">Risco de readmissão</div>
                <div style="font-size:2.4rem; font-weight:700; line-height:1;"><?= $probPct ?>%</div>
                <?php $refIntern = $riskOverview['internacao_referencia'] ?? null; ?>
                <div style="font-size:0.85rem;">
                    Nível <?= strtoupper($riskLevel ?: 'BAIXO') ?> • Internação ref.
                    <?= $refIntern ? '#' . (int)$refIntern : '—' ?>
                </div>
                <div style="margin-top:6px;">
                    <span style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:999px;font-size:.85rem;font-weight:600;background:rgba(255,255,255,.4);color:<?= $textColor ?>;border:1px solid rgba(0,0,0,.06);">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span><?= $complexInfo['label'] ?> · <?= $complexInfo['prioridade'] ?></span>
                    </span>
                </div>
            </div>
            <div style="flex:1; min-width:250px; font-size:0.9rem;">
                <?= $explanation ?>
            </div>
        </div>
        <div style="display:flex; flex-wrap:wrap; gap:20px; margin-top:12px; font-size:0.88rem;">
            <div>
                <strong>Perfil:</strong> <?= $faixa ?><?= $idade ? " ({$idade} anos)" : '' ?>, sexo <?= strtoupper($features['sexo'] ?? 'ND') ?>
            </div>
            <div>
                <strong>Antecedentes:</strong> <?= $antecedentes ?> •
                <strong>Internações prévias:</strong> <?= $internPrev ?> (MP <?= $mpPrev ?> dias)
            </div>
            <div>
                <strong>Permanência atual:</strong> <?= $diasAtual ?> dias<?= $mpLimite ? " (limite {$mpLimite})" : '' ?> •
                <strong>Eventos adversos:</strong> <?= $eventos ?>
            </div>
        </div>
        <?php if (!empty($riskOverview['recommendations']) && is_array($riskOverview['recommendations'])): ?>
        <ul style="margin:12px 0 0 18px; padding-left:0.8rem; font-size:0.88rem;">
            <?php foreach ($riskOverview['recommendations'] as $rec): ?>
            <li><?= htmlspecialchars($rec, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    <?php elseif (!empty($riskOverview['message'])): ?>
    <div style="border:1px dashed #999; border-radius:8px; padding:12px; margin-bottom:18px; color:#555;">
        <?= htmlspecialchars($riskOverview['message'], ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>
    <div class="card">
        <h6 style="margin:10px 0 10px 20px">Dados pessoais</h6>
        <div class="card-header container-fluid" id="view-contact-container">
            <span style="font-size:large; font-weight:600" class="card-title bold">Nome:</span>
            <span style="font-size:large; font-weight:600" class="card-title bold">
                <?= $paciente['0']['nome_pac'] ?>
            </span>
            <span style="margin-left:200px" class="card-title bold">Nome da Mãe:</span>
            <span class="card-title bold">
                <?= $paciente['0']['mae_pac'] ?>
            </span>
            <span style="margin-left:200px" class="card-title bold">CPF:</span>
            <span class="card-title bold">
                <?= $cpf_formatado ?>
            </span>
            <br>
            <span class="card-title bold">Seguradora:</span>
            <span class="card-title bold">
                <?= $paciente['0']['seguradora_seg'] ?>
            </span>
        </div>
        <div class="card-body">
            <h6>Dados cadastrais</h6>
            <span class=" card-text bold">Endereço: </span>
            <span class=" card-text bold">
                <?= $paciente['0']['endereco_pac'] ?>
            </span>
            <span class=" card-text bold">, </span>
            <span class=" card-text bold">
                <?= $paciente['0']['numero_pac'] ?>
            </span>
            <br>
            <span class=" card-text bold">Bairro: </span>
            <span class=" card-text bold">
                <?= $paciente['0']['bairro_pac'] ?>
            </span>
            <span style="margin-left:200px" class="card-text bold">Cidade: </span>
            <span class="card-text bold">
                <?= $paciente['0']['cidade_pac'] ?>
            </span>
            <span style="margin-left:200px" class="card-text bold">Estado: </span>
            <span class="card-text bold">
                <?= $paciente['0']['estado_pac'] ?>
            </span>
        </div>
        <hr>
        <div class="card-body">
            <h6>Contatos</h6>
            <span class=" card-text bold">Email: </span>
            <span class=" card-text bold">
                <?= $paciente['0']['email01_pac'] ?>
            </span>
            <span style="margin-left:200px" class=" card-text bold">Email 02: </span>
            <span class=" card-text bold">
                <?= $paciente['0']['email02_pac'] ?>
            </span>
            <br>
            <span class=" card-text bold">Telefone: </span>
            <span class=" card-text bold">
                <?= $paciente['0']['telefone01_pac'] ?>
            </span>
            <span style="margin-left:200px" class=" card-text bold">Tel 02: </span>
            <span class=" card-text bold">
                <?= $paciente['0']['telefone02_pac'] ?>
            </span>
        </div>
        <hr>
        <div class="card-body">
            <h6>Empresa</h6>
            <span class=" card-text bold">Seguradora: </span>
            <span class=" card-text bold">
                <?= $paciente['0']['seguradora_seg'] ?>
            </span>
            <br>
            <span class=" card-text bold">Estipulante: </span>
            <span class=" card-text bold">
                <?= $paciente['0']['nome_est'] ?>
            </span>
            <hr>
        </div>
        <div style="margin-left:20px" id="id-confirmacao" class="btn_acoes visible">

            <div class="form-group row">
                <div class="form-group col-sm-2">
                    <form display="in-line" id="form_delete"
                        action="process_paciente.php?id_paciente=<?= $id_paciente ?>" method="POST">
                        <input type="hidden" value="deletando">
                        <!-- <input type="hidden" name="type" value="delete"> -->
                        <input type="hidden" name="typeDel" value="delUpdate">
                        <input type="hidden" name="id_paciente" value="<?= $paciente['0']['id_paciente'] ?>">

                        <button class="btn btn-danger" value="deletar" type="submit" id="deletar-btn"
                            name="deletar">Deletar</button>

                    </form>
                    <br>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function apareceOpcoes() {

    $('#deletar-btn').val('nao');
    let mudancaStatus = ($('#deletar-btn').val())
    let idAcoes = (document.getElementById('id-confirmacao'));
    idAcoes.style.display = 'block';
}

function deletar() {
    let idAcoes = (document.getElementById('id-confirmacao'));

    btnDeletar = (document.getElementById('deletar-btn').value);

    idAcoes.style.display = 'none';

    window.location = "<?= $BASE_URL ?>dele_paciente.php?id_paciente=<?= $id_paciente ?>";
};

function cancelar() {
    let idAcoes = (document.getElementById('id-confirmacao'));
    idAcoes.style.display = 'none';
    window.location = "<?= $BASE_URL ?>pacientes?>";

};
src = "https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js";
</script>
<script src="js/apagarModal.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>
