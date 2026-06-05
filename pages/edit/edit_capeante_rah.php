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

include_once("dao/CapValoresDao.php");
include_once("dao/CapValoresDiarDao.php");

include_once("dao/CapValoresAPDao.php");
include_once("dao/CapValoresUTIDao.php");
include_once("dao/CapValoresCCDao.php");
include_once("dao/CapValoresOutDao.php");

include_once("array_dados.php");

$internacaoDao = new internacaoDAO($conn, $BASE_URL);
$internacao   = $internacaoDao->findGeral($limite, $inicio);

$hospital_geral = new hospitalDAO($conn, $BASE_URL);
$hospitals      = $hospital_geral->findGeral($limite, $inicio);

$pacienteDao = new pacienteDAO($conn, $BASE_URL);
$pacientes   = $pacienteDao->findGeral($limite, $inicio);

$patologiaDao = new patologiaDAO($conn, $BASE_URL);
$patologias   = $patologiaDao->findGeral();

$gestao = new gestaoDAO($conn, $BASE_URL);
$gestaoIdMax    = $gestao->findMax();
$findMaxGesInt  = $gestao->findMaxGesInt();

$uti = new utiDAO($conn, $BASE_URL);
$utiIdMax    = $uti->findMaxUTI();
$findMaxUtiInt = $uti->findMaxUtiInt();

$prorrogacao = new prorrogacaoDAO($conn, $BASE_URL);
$prorrogacaoIdMax = $prorrogacao->findMaxPror();
$findMaxProInt    = $prorrogacao->findMaxProInt();

$negociacao = new negociacaoDAO($conn, $BASE_URL);
$negociacaoLast = new negociacaoDAO($conn, $BASE_URL);

$capeanteDao = new capeanteDAO($conn, $BASE_URL);
$capValoresDao = new CapValoresDAO($conn);

$capValoresApDao   = new CapValoresAPDAO($conn);
$capValoresUtiDao  = new CapValoresUTIDAO($conn);
$capValoresCcDao   = new CapValoresCCDAO($conn);
$capValoresDiarDao = new CapValoresDiarDAO($conn);
$capValoresOutDao  = new CapValoresOutDAO($conn);

$id_capeante = filter_input(INPUT_GET, "id_capeante", FILTER_VALIDATE_INT);
$id_valor    = filter_input(INPUT_GET, "id_valor", FILTER_VALIDATE_INT);

$capValorHeader = null;
if ($id_valor) {
    $capValorHeader = $capValoresDao->findById($id_valor);
    if ($capValorHeader && !$id_capeante && isset($capValorHeader['fk_capeante'])) {
        $id_capeante = (int)$capValorHeader['fk_capeante'];
    }
}
if (!$capValorHeader && $id_capeante) {
    $capValorHeader = $capValoresDao->findByCapeante($id_capeante);
    if ($capValorHeader && !$id_valor && isset($capValorHeader['id_valor'])) {
        $id_valor = (int)$capValorHeader['id_valor'];
    }
}

$internacao = [];
if ($capValorHeader) {
    $internacao[] = $capValorHeader;
} elseif ($id_capeante) {
    $internacao = $internacaoDao->selectAllInternacaoCapList($id_capeante);
}

$capeanteRow = $id_capeante ? $capeanteDao->findById($id_capeante) : null;
$apRow   = $id_capeante ? $capValoresApDao->findByCapeante($id_capeante) : null;
$utiRow  = $id_capeante ? $capValoresUtiDao->findByCapeante($id_capeante) : null;
$ccRow   = $id_capeante ? $capValoresCcDao->findByCapeante($id_capeante) : null;
$diarRow = $id_capeante ? $capValoresDiarDao->findByCapeante($id_capeante) : null;
$outRow  = $id_capeante ? $capValoresOutDao->findByCapeante($id_capeante) : null;

$toArray = function ($obj) {
    if (!$obj) return [];
    if (is_array($obj)) return $obj;
    return get_object_vars($obj);
};

$rahEditData = [
    'capeante' => $toArray($capeanteRow),
    'ap'       => $toArray($apRow),
    'uti'      => $toArray($utiRow),
    'cc'       => $toArray($ccRow),
    'diar'     => $toArray($diarRow),
    'outros'   => $toArray($outRow),
    'header'   => $capValorHeader ?: [],
];

// força modo update no form base
$type = 'update';

?>
<div id="main-container" style="background:#f5f6f8; margin:1px">

    <!-- FORMULÁRIO DE EDIÇÃO -->
    <?php include_once('formularios/form_edit_capeante_rah.php'); ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous">
    </script>

</div>
</div>
<?php
require_once("templates/footer.php");
?>
