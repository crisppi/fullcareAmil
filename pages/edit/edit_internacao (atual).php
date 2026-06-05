<?php
include_once("check_logado.php");

require_once("templates/header.php");

include_once("models/internacao.php");
include_once("dao/internacaoDao.php");

include_once("models/message.php");

include_once("models/hospital.php");
include_once("dao/hospitalDao.php");

include_once("models/patologia.php");
include_once("dao/patologiaDao.php");

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

include_once("models/hospitalUser.php");
include_once("dao/hospitalUserDao.php");

include_once("models/tuss_ans.php");
include_once("dao/tussAnsDao.php");

include_once("models/tuss.php");
include_once("dao/tussDao.php");

include_once("models/detalhes.php");
include_once("dao/detalhesDao.php");

include_once("array_dados.php");

$internacaoDao = new internacaoDAO($conn, $BASE_URL);

$hospital_geral = new hospitalDAO($conn, $BASE_URL);
$hospitals = $hospital_geral->findGeral($limite, $inicio);

$hospitalList = new hospitalUserDAO($conn, $BASE_URL);
$hospitalUser = new hospitalUserDAO($conn, $BASE_URL);

$pacienteDao = new pacienteDAO($conn, $BASE_URL);
$pacientes = $pacienteDao->findGeral($limite, $inicio);

$patologiaDao = new patologiaDAO($conn, $BASE_URL);
$patologias = $patologiaDao->findGeral();

$gestao = new gestaoDAO($conn, $BASE_URL);
$gestaoIdMax = $gestao->findMax();
$findMaxGesInt = $gestao->findMaxGesInt();

$uti = new utiDAO($conn, $BASE_URL);
$utiIdMax = $uti->findMaxUTI();
$findMaxUtiInt = $uti->findMaxUtiInt();

$capeante = new capeanteDAO($conn, $BASE_URL);
$CapIdMax = $capeante->findMaxCapeante();

$prorrogacao = new prorrogacaoDAO($conn, $BASE_URL);
$prorrogacaoIdMax = $prorrogacao->findMaxPror();
$prorrogacaoGeral = $prorrogacao->findGeral();
$findMaxProInt = $prorrogacao->findMaxProInt();

$negociacao = new negociacaoDAO($conn, $BASE_URL);
$negociacaoLast = new negociacaoDAO($conn, $BASE_URL);

$detalhesDao = new detalhesDao($conn, $BASE_URL);

$where = $order = $obLimite = null;
$query = $hospitalUser->selectAllhospitalUser($where, $order, $obLimite);

// SELECIONAR HOSPITAL POR USUARIO
$id_hospitalUser = ($_SESSION['id_usuario']);

$listHopitaisPerfil = $hospitalList->joinHospitalUser($id_hospitalUser);

$tuss = new tussAnsDAO($conn, $BASE_URL);

$tuss_int = new tussDAO($conn, $BASE_URL);

$id_internacao = filter_input(INPUT_GET, 'id_internacao') ? filter_input(INPUT_GET, 'id_internacao') : 1;

$intern = $internacaoDao->findByIdArray($id_internacao)[0];
$int_paciente = $pacienteDao->findById($intern['fk_paciente_int']);
$int_patologia = $patologiaDao->findById($intern['fk_patologia_int']);
$int_antecedente = $patologiaDao->findById($intern['fk_patologia2']);
$int_detalhes = $detalhesDao->findById($intern['id_internacao']);
$ctl_detalhes = $detalhesDao->findById($intern['id_internacao']);

if (empty($int_detalhes)) {
    $detalhes_new = new Detalhes();
    $int_detalhes = $detalhes_new;
}

$int_hospital = $hospital_geral->findById($intern['fk_hospital_int']);

$int_tuss = $tuss_int->findByIdIntern($intern['id_internacao']);

$int_gestao = $gestao->findByIdInt($intern['id_internacao']);

// print_r($int_tuss);

$tussGeral = $tuss->findAll();

?>
<div id="main-container" style="margin:15px;background-color: #dee2e6;">

    <!-- FORMULARIO INTERNACAO -->
    <?php include_once('formularios/form_edit_internacao.php'); ?>

    <!-- FORMULARIO DE GESTÃO -->
    <?php include_once('formularios/form_cad_internacao_gestao.php'); ?>

    <!-- FORMULARIO DE UTI -->
    <?php include_once('formularios/form_cad_internacao_uti.php'); ?>

    <!-- FORMULARIO DE PRORROGACOES -->
    <?php include_once('formularios/form_cad_internacao_prorrog.php'); ?>

    <!-- <FORMULARO DE NEGOCIACOES -->
    <?php include_once('formularios/form_cad_internacao_negoc.php'); ?>


</div>
</div>

<script src="js/timeout.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous">
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>


<?php
require_once("templates/footer.php");
?>