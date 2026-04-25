<?php
// DEBUG TEMPORÁRIO (REMOVER APÓS TESTE)
ini_set('log_errors', '1');
error_reporting(E_ALL);


include_once("check_logado.php");

require_once("templates/header.php");
include_once("models/internacao.php");
require_once("dao/internacaoDao.php");
require_once("models/message.php");

include_once("models/hospital.php");
include_once("dao/hospitalDao.php");

include_once("models/patologia.php");
include_once("dao/patologiaDao.php");

include_once("models/visita.php");
include_once("dao/visitaDao.php");

include_once("models/paciente.php");
require_once("dao/pacienteDao.php");

include_once("models/gestao.php");
include_once("dao/gestaoDao.php");

include_once("models/acomodacao.php");
include_once("dao/acomodacaoDao.php");

include_once("models/prorrogacao.php");
include_once("dao/prorrogacaoDao.php");

include_once("models/uti.php");
include_once("dao/utiDao.php");

include_once("models/tuss_ans.php");
include_once("dao/tussAnsDao.php");

include_once("models/tuss.php");
include_once("dao/tussDao.php");

include_once("models/negociacao.php");
include_once("dao/negociacaoDao.php");

include_once("array_dados.php");


include_once("models/antecedente.php");
include_once("dao/antecedenteDao.php");
include_once("models/internacao_antecedente.php");
include_once("dao/internacaoAntecedenteDao.php");

$internacaoDao = new internacaoDAO($conn, $BASE_URL);

$hospital_geral = new hospitalDAO($conn, $BASE_URL);
$hospitals = $hospital_geral->findGeral($limite, $inicio);

$pacienteDao = new pacienteDAO($conn, $BASE_URL);
$pacientes = $pacienteDao->findGeral($limite, $inicio);

$visita = new visitaDAO($conn, $BASE_URL);
$visitas = $visita->findGeral($limite, $inicio);


$patologiaDao = new patologiaDAO($conn, $BASE_URL);
$patologias = $patologiaDao->findGeral();

$gestao = new gestaoDAO($conn, $BASE_URL);
$findMaxVis = $gestao->findMaxVis();

$gestao = new gestaoDAO($conn, $BASE_URL);
$gestaoIdMax = $gestao->findMax();
$findMaxGesInt = $gestao->findMaxGesInt();

$uti = new utiDAO($conn, $BASE_URL);
$utiIdMax = $uti->findMaxUTI();

$prorrogacao = new prorrogacaoDAO($conn, $BASE_URL);
$prorrogacaoIdMax = $prorrogacao->findMaxPror();

$acomodacaoDao = new acomodacaoDAO($conn, $BASE_URL);
$acomodacao = $acomodacaoDao->findGeral();

$tuss = new tussAnsDAO($conn, $BASE_URL);
$tussGeral = $tuss->findAll();

$tussInt = new tussDAO($conn, $BASE_URL);
$negociacaoDao = new negociacaoDAO($conn, $BASE_URL);

$id_internacao = filter_input(INPUT_GET, 'id_internacao', FILTER_VALIDATE_INT);

$visitasAntigas = $visita->findGeralByIntern($id_internacao);

$condicoes = [
    strlen($id_internacao) ? 'id_internacao = "' . $id_internacao . '"' : NULL,
];
$condicoes = array_filter($condicoes);
// REMOVE POSICOES VAZIAS DO FILTRO
$where = implode(' AND ', $condicoes);
$internacaoList = $internacaoDao->selectAllInternacaoVis($where, $order = null, $limit = null);
$tussIntern = $tussInt->selectAllTUSSByIntern($id_internacao);
$prorrogIntern = $prorrogacao->selectAllInternacaoProrrog($id_internacao);
$tussPorVisita = [];
$tussPorInternacao = [];
$negPorVisita = [];
$negPorInternacao = [];
$gestaoPorVisita = [];
$gestaoPorInternacao = [];
$utiPorVisita = [];
$utiPorInternacao = [];
$prorrogPorVisita = [];
$prorrogPorInternacao = [];
$visitaInterMap = [];
if ($id_internacao) {
    foreach ((array)$visitasAntigas as $row) {
        if (!is_array($row)) continue;
        $visId = isset($row['id_visita']) ? (int)$row['id_visita'] : null;
        $intId = isset($row['fk_internacao_vis']) ? (int)$row['fk_internacao_vis'] : (int)$id_internacao;
        if ($visId) $visitaInterMap[$visId] = $intId;
    }

    $tussRaw = $tussInt->selectRawByInternacao((int) $id_internacao);
    foreach ($tussRaw as $row) {
        $vid = (int) ($row['fk_vis_tuss'] ?? 0);
        $intId = (int) ($row['fk_int_tuss'] ?? 0);
        $entry = [
            'id_tuss'             => (int) ($row['id_tuss'] ?? 0),
            'tuss_solicitado'     => $row['tuss_solicitado'] ?? '',
            'tuss_liberado_sn'    => $row['tuss_liberado_sn'] ?? '',
            'qtd_tuss_solicitado' => $row['qtd_tuss_solicitado'] ?? '',
            'qtd_tuss_liberado'   => $row['qtd_tuss_liberado'] ?? '',
            'data_realizacao_tuss'=> $row['data_realizacao_tuss'] ?? '',
            'fk_usuario_tuss'     => $row['fk_usuario_tuss'] ?? null
        ];
        if ($vid > 0) $tussPorVisita[$vid][] = $entry;
        elseif ($intId > 0) $tussPorInternacao[$intId][] = $entry;
    }

    $negRows = $negociacaoDao->selectByInternacao((int) $id_internacao, null, null, true);
    foreach ($negRows as $row) {
        $vid = (int) ($row['fk_visita_neg'] ?? 0);
        $intId = (int) ($row['fk_id_int'] ?? 0);
        $entry = [
            'id_negociacao'   => (int) ($row['id_negociacao'] ?? 0),
            'tipo_negociacao' => $row['tipo_negociacao'] ?? '',
            'data_inicio_negoc' => $row['data_inicio_neg'] ?? '',
            'data_fim_negoc'    => $row['data_fim_neg'] ?? '',
            'troca_de'        => $row['troca_de'] ?? '',
            'troca_para'      => $row['troca_para'] ?? '',
            'qtd'             => $row['qtd'] ?? '',
            'saving'          => $row['saving'] ?? '',
            'fk_usuario_neg'  => $row['fk_usuario_neg'] ?? null
        ];
        if ($vid > 0) $negPorVisita[$vid][] = $entry;
        elseif ($intId > 0) $negPorInternacao[$intId][] = $entry;
    }

    $gestaoRows = $gestao->selectRawByInternacao((int) $id_internacao);
    foreach ($gestaoRows as $row) {
        $vid = (int) ($row['fk_visita_ges'] ?? 0);
        $intId = (int) ($row['fk_internacao_ges'] ?? 0);
        if ($vid > 0) {
            $gestaoPorVisita[$vid] = $row;
        } elseif ($intId > 0 && !isset($gestaoPorInternacao[$intId])) {
            $gestaoPorInternacao[$intId] = $row;
        }
    }

    $utiRows = $uti->selectRawByInternacao((int) $id_internacao);
    foreach ($utiRows as $row) {
        $vid = (int) ($row['fk_visita_uti'] ?? 0);
        $intId = (int) ($row['fk_internacao_uti'] ?? 0);
        if ($vid > 0) {
            $utiPorVisita[$vid] = $row;
        } elseif ($intId > 0 && !isset($utiPorInternacao[$intId])) {
            $utiPorInternacao[$intId] = $row;
        }
    }

    $prorrogRaw = $prorrogacao->selectRawByInternacao((int) $id_internacao);
    foreach ($prorrogRaw as $row) {
        $vid = (int) ($row['fk_visita_pror'] ?? 0);
        $intId = (int) ($row['fk_internacao_pror'] ?? 0);
        if ($vid > 0) {
            $prorrogPorVisita[$vid][] = $row;
        } elseif ($intId > 0) {
            $prorrogPorInternacao[$intId][] = $row;
        }
    }
}

$editVisitaIdParam = filter_input(INPUT_GET, 'edit_visita', FILTER_VALIDATE_INT);
$editVisitaIdReal = $editVisitaIdParam ?: 0;
if ($editVisitaIdParam && empty($prorrogPorVisita[$editVisitaIdReal])) {
    foreach ((array)$visitasAntigas as $visitaAntiga) {
        if (!is_array($visitaAntiga)) {
            continue;
        }
        if ((int)($visitaAntiga['visita_no_vis'] ?? 0) === $editVisitaIdParam) {
            $editVisitaIdReal = (int)($visitaAntiga['id_visita'] ?? 0);
            break;
        }
    }
}
$prorrogEditRows = ($editVisitaIdReal && !empty($prorrogPorVisita[$editVisitaIdReal]))
    ? $prorrogPorVisita[$editVisitaIdReal]
    : [];
$editAdditionalSelects = [
    'tuss' => ($editVisitaIdReal && !empty($tussPorVisita[$editVisitaIdReal])) ? 's' : '',
    'prorrog' => ($editVisitaIdReal && !empty($prorrogPorVisita[$editVisitaIdReal])) ? 's' : '',
    'gestao' => ($editVisitaIdReal && !empty($gestaoPorVisita[$editVisitaIdReal])) ? 's' : '',
    'uti' => ($editVisitaIdReal && !empty($utiPorVisita[$editVisitaIdReal])) ? 's' : '',
    'negoc' => ($editVisitaIdReal && (!empty($negPorVisita[$editVisitaIdReal]) || !empty($negPorInternacao[(int)$id_internacao]))) ? 's' : '',
];
extract($internacaoList);

$ultimaVis = end($internacaoList);
$ultimaReg = end($internacaoList);

$acomodacoes = $acomodacaoDao->findGeralByHospital($ultimaVis['id_hospital']);
$jsonAcomodacoes = json_encode($acomodacoes);

$antecedenteDao = new antecedenteDAO($conn, $BASE_URL);
$antecedentes = $antecedenteDao->findGeral();
$internAntecedenteDao = new InternacaoAntecedenteDAO($conn, $BASE_URL);
$antecedentesInternacao = ($id_internacao)
    ? $internAntecedenteDao->findByInternacao((int)$id_internacao)
    : [];
$antecedentesInternacao = is_array($antecedentesInternacao) ? $antecedentesInternacao : [];
$antecedentesInternacaoIds = array_map('intval', array_column($antecedentesInternacao, 'intern_antec_ant_int'));

?>

<div id="main-container" style="background-color: #f0f2f4ff;padding: 20px;">
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.8.2/jquery.min.js"></script>
    <!-- FORMULARIO INTERNACAO -->
    <?php include_once('formularios/form_cad_visita.php'); ?>
</div>


<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>
