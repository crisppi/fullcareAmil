<?php
include_once("check_logado.php");

require_once("templates/header.php");
require_once("models/usuario.php");
require_once("models/internacao.php");
require_once("models/uti.php");
require_once("dao/usuarioDao.php");
require_once("dao/internacaoDao.php");
require_once("dao/utiDao.php");
include("array_dados.php");

$internacao = new internacao();
$userDao = new UserDAO($conn, $BASE_URL);
$internacaoDao = new internacaoDAO($conn, $BASE_URL);
$utiDao = new utiDAO($conn, $BASE_URL);

// Receber id do usuário
$id_internacao = (int)filter_input(INPUT_GET, "id_internacao", FILTER_SANITIZE_NUMBER_INT);
$internacao = $internacaoDao->findById($id_internacao);

$internacao_geral = new internacaoDAO($conn, $BASE_URL);
$internacao = $internacaoDao->selectAllInternacao('ac.id_internacao = :id_internacao', null, null, [
    ':id_internacao' => $id_internacao
]);
extract($internacao);
// print_r($internacao);
// exit;

$internadosUTI = $utiDao->findUTIInternacao($id_internacao);
$id_uti = $internadosUTI['0']['id_uti']; ?>

<!-- formulario alta uti -->
<div id="main-container" style="margin:15px">
    <h4 class="page-title">Alta da UTI</h4>

    <form action="<?= $BASE_URL ?>process_alta_uti.php" id="add-movie-form" method="POST" enctype="multipart/form-data">

        <!-- DADOS DO INPUT COM ENTRADA DE DADOS PADRAO -->
        <input type="hidden" name="type" value="update">
        <div class="form-group col-sm-1">
            <input type="hidden" class="form-control" id="id_internacao" name="id_internacao"
                value="<?= $internacao['0']['id_internacao'] ?>">
        </div>
        <div class="form-group col-sm-1">
            <input type="hidden" class="form-control" id="id_uti" name="id_uti" value="<?= $id_uti ?>">
        </div>
        <div class="row">
            <div class="form-group col-sm-3">
                <label class="control-label">Hospital</label>
                <input type="text" class="form-control" value="<?= $internacao['0']['nome_hosp'] ?>" readonly>
            </div>
            <div class="form-group col-sm-3">
                <label class="control-label">Paciente</label>
                <input type="text" class="form-control" value="<?= $internacao['0']['nome_pac'] ?>" readonly>
            </div>

            <div class="form-group col-sm-2">
                <label class="control-label" for="data_alta_int">Data internacao</label>
                <input type="date" class="form-control" value='<?= $internadosUTI['0']['data_intern_int'] ?>'
                    id="data_intern_int" name="data_intern_int" readonly placeholder="" required>
            </div>
        </div>
        <hr>
        <div class="row">
            <p style="margin-left:20px" class="page-description">Adicione informações sobre a alta da UTI</p>

            <div class="form-group col-sm-2">
                <label class="control-label" for="data_alta_uti">Data alta UTI</label>
                <input type="date" class="form-control" value='<?php echo date('Y-m-d') ?>' id="data_alta_uti"
                    name="data_alta_uti" autofocus require>
                <div class="notif-input oculto" id="notif-input2">
                    Data inválida !
                </div>
            </div>
            <div class="form-group col-sm-2">
                <input type="hidden" class="form-control" value="n" id="internado_uti_int" name="internado_uti_int"
                    placeholder="">
            </div>
            <div class="form-group col-sm-2">
                <input type="hidden" name="data_create_int" class="form-control" value='<?php echo date('Y-m-d') ?>'
                    placeholder="">
            </div>
            <div class="form-group col-sm-2">
                <input type="hidden" class="form-control" id="internado_uti" name="internado_uti" value='n'
                    placeholder="">
            </div>
            <div class="form-group col-sm-3">
                <input type="hidden" value="<?= $_SESSION['email_user']; ?>" class="form-control"
                    id="usuario_create_int" name="usuario_create_int" placeholder="Digite o usuário">
            </div>
        </div>
        <br>
        <button style="margin:10px" type="submit" class="btn-sm btn-primary">Alta UTI</button>
        <br>
    </form>
    <?php include_once("diversos/backbtn_internacao_uti.php"); ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous">
</script>


<?php
include_once("templates/footer.php");
?>
<script src="js/scriptDataAltaUTI.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>

</html>
