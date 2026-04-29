<?php
include_once("check_logado.php");
require_once("templates/header.php");;

include_once("models/internacao.php");
include_once("dao/internacaoDao.php");

include_once("models/message.php");

include_once("models/hospital.php");
include_once("dao/hospitalDao.php");

include_once("models/patologia.php");
include_once("dao/patologiaDao.php");

include_once("models/antecedente.php");
include_once("dao/antecedenteDao.php");

include_once("models/paciente.php");
include_once("dao/pacienteDao.php");

include_once("models/uti.php");
include_once("dao/utiDao.php");

include_once("models/gestao.php");
include_once("dao/gestaoDao.php");

include_once("models/prorrogacao.php");
include_once("dao/prorrogacaoDao.php");

include_once("models/negociacao.php");
include_once("dao/negociacaoDao.php");

include_once("models/capeante.php");
include_once("dao/capeanteDao.php");

include_once("models/acomodacao.php");
include_once("dao/acomodacaoDao.php");

include_once("models/hospitalUser.php");
include_once("dao/hospitalUserDao.php");

include_once("models/tuss_ans.php");
include_once("dao/tussAnsDao.php");

include_once("array_dados.php");

$uti = new utiDAO($conn, $BASE_URL);
$utiIdMax = $uti->findMaxUTI();
$findMaxUtiInt = $uti->findMaxUtiInt();

$gestao = new gestaoDAO($conn, $BASE_URL);
$gestaoIdMax = $gestao->findMax();
$findMaxGesInt = $gestao->findMaxGesInt();


$patologiaDao = new patologiaDAO($conn, $BASE_URL);
$patologias = $patologiaDao->findGeral();

$antecedenteDao = new antecedenteDAO($conn, $BASE_URL);
$antecedentes = $antecedenteDao->findGeral();

$capeante = new capeanteDAO($conn, $BASE_URL);
$CapIdMax = $capeante->findMaxCapeante();

$prorrogacao = new prorrogacaoDAO($conn, $BASE_URL);
$prorrogacaoIdMax = $prorrogacao->findMaxPror();
$prorrogacaoGeral = $prorrogacao->findGeral();
$findMaxProInt = $prorrogacao->findMaxProInt();

$negociacao = new negociacaoDAO($conn, $BASE_URL);
$negociacaoLast = new negociacaoDAO($conn, $BASE_URL);
// Inicializando DAOs principais
$internacaoDao = new internacaoDAO($conn, $BASE_URL);
$hospital_geral = new hospitalDAO($conn, $BASE_URL);
$pacienteDao = new pacienteDAO($conn, $BASE_URL);

// Selecionar hospitais e pacientes
$hospitals = $hospital_geral->findGeral($limite, $inicio);
$pacientes = $pacienteDao->findGeral($limite, $inicio);


$findMaxInt = $internacaoDao->findMaxInt();
$a = ($findMaxInt[0]);
$ultimoReg = ($a["ultimoReg"]);

// Selecionar hospitais por usuário
$id_user = $_SESSION['id_usuario'];
if ($_SESSION['nivel'] > 3) {
    $listHopitaisPerfil = $hospital_geral->findGeral();
} else {
    include_once("models/hospitalUser.php");
    include_once("dao/hospitalUserDao.php");
    $hospitalList = new hospitalUserDAO($conn, $BASE_URL);
    $listHopitaisPerfil = $hospitalList->joinHospitalUser($id_user);
}

// Inclusão condicional de arquivos adicionais
if (isset($selectGestao) && $selectGestao === "Sim") {
    include_once("models/gestao.php");
    include_once("dao/gestaoDao.php");
    $gestao = new gestaoDAO($conn, $BASE_URL);
    $gestaoIdMax = $gestao->findMax();
}


include_once("models/uti.php");
include_once("dao/utiDao.php");
$uti = new utiDAO($conn, $BASE_URL);
$utiIdMax = $uti->findMaxUTI();


if (isset($selectNegociacao) && $selectNegociacao === "Sim") {
    include_once("models/negociacao.php");
    include_once("dao/negociacaoDao.php");
    $negociacao = new negociacaoDAO($conn, $BASE_URL);
}

if (isset($selectProrrogacao) && $selectProrrogacao === "Sim") {
    include_once("models/prorrogacao.php");
    include_once("dao/prorrogacaoDao.php");
    $prorrogacao = new prorrogacaoDAO($conn, $BASE_URL);
    $prorrogacaoIdMax = $prorrogacao->findMaxPror();
}

include_once("models/tuss_ans.php");
include_once("dao/tussAnsDao.php");
$tuss = new tussAnsDAO($conn, $BASE_URL);
$tussGeral = $tuss->findAll();

?>

<div id="main-container" style="background-color: #f0f2f4ff;padding: 12px 10px;">
    <!-- FORMULARIO INTERNACAO -->
    <?php include_once('formularios/form_cad_internacao.php'); ?>

    <!-- FORMULARIO DE GESTÃO -->
    <?php if (isset($selectGestao) && $selectGestao === "Sim") : ?>
        <?php include_once('formularios/form_cad_internacao_gestao.php'); ?>
    <?php endif; ?>

    <!-- FORMULARIO DE UTI -->
    <?php if (isset($selectUti) && $selectUti === "Sim") : ?>
        <?php include_once('formularios/form_cad_internacao_uti.php'); ?>
    <?php endif; ?>

    <!-- FORMULARIO DE PRORROGACOES -->
    <?php if (isset($selectProrrogacao) && $selectProrrogacao === "Sim") : ?>
        <?php include_once('formularios/form_cad_internacao_prorrog.php'); ?>
    <?php endif; ?>

    <!-- FORMULARIO DE NEGOCIACOES -->
    <?php if (isset($selectNegociacao) && $selectNegociacao === "Sim") : ?>
        <?php include_once('formularios/form_cad_internacao_negoc.php'); ?>
    <?php endif; ?>

    <!-- FORMULARIO DE TUSS -->
    <?php if (isset($selectTuss) && $selectTuss === "Sim") : ?>
        <?php include_once('formularios/form_cad_internacao_tuss.php'); ?>
    <?php endif; ?>
</div>

<script src="js/timeout.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous">
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<?php require_once("templates/footer.php"); ?>
