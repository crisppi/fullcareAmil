<?php
include_once("check_logado.php");

require_once("templates/header.php");

include_once("models/internacao.php");
include_once("dao/internacaoDao.php");

include_once("models/message.php");

include_once("models/hospital.php");
include_once("dao/hospitalDao.php");

include_once("models/tuss.php");
include_once("dao/tussDao.php");

include_once("models/tuss_ans.php");
include_once("dao/tussAnsDao.php");

include_once("models/paciente.php");
include_once("dao/pacienteDao.php");

include_once("models/hospitalUser.php");
include_once("dao/hospitalUserDao.php");

include_once("array_dados.php");

$internacaoDao = new internacaoDAO($conn, $BASE_URL);
$tuss_list = new tussDAO($conn, $BASE_URL);
$limite = isset($limite) ? (int)$limite : null;
$inicio = isset($inicio) ? (int)$inicio : null;

$hospital_geral = new hospitalDAO($conn, $BASE_URL);
$hospitals = $hospital_geral->findGeral($limite, $inicio);

$hospitalList = new hospitalUserDAO($conn, $BASE_URL);
$hospitalUser = new hospitalUserDAO($conn, $BASE_URL);

$pacienteDao = new pacienteDAO($conn, $BASE_URL);
$pacientes = $pacienteDao->findGeral($limite, $inicio);

$where = $order = $obLimite = null;
$query = $hospitalUser->selectAllhospitalUser($where, $order, $obLimite);


$tuss = new tussAnsDAO($conn, $BASE_URL);
$tussGeral = $tuss->findAll();

// SELECIONAR HOSPITAL POR USUARIO
$id_hospitalUser = ($_SESSION['id_usuario']);

$listHopitaisPerfil = $hospitalList->joinHospitalUser($id_hospitalUser);

$id_internacao = filter_input(INPUT_GET, 'id_internacao') ? filter_input(INPUT_GET, 'id_internacao') : 1;
$intern = $internacaoDao->findByIdArray($id_internacao)[0];
$int_paciente = $pacienteDao->findById($intern['fk_paciente_int']);

$int_hospital = $hospital_geral->findById($intern['fk_hospital_int']);

$int_tuss = $tuss_list->findByIdIntern($id_internacao);

?>
<div id="main-container" style="margin:15px;">

    <!-- FORMULARIO INTERNACAO -->
    <?php
    include_once('formularios/form_edit_internacao_tuss2.php'); ?>

</div>
</div>

<script src="js/timeout.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous">
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-maskmoney/3.0.2/jquery.maskMoney.min.js"></script>



<?php
require_once("templates/footer.php");
?>
