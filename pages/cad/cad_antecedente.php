<?php
//session_start();
include_once("check_logado.php");

require_once("templates/header.php");
require_once("dao/antecedenteDao.php");
require_once("models/message.php");
include_once("dao/cidDao.php");


$message = new Message($BASE_URL);
$cid = new cidDAO($conn, $BASE_URL);

// LISTAR CIDS
$cids = $cid->findAll();

$flassMessage = $message->getMessage();
if (!empty($flassMessage["msg"])) {
    // Limpar a mensagem
    $message->clearMessage();
}
$antecedenteDao = new antecedenteDAO($conn, $BASE_URL);

// Receber id do usuário
$id_antecedente = filter_input(INPUT_GET, "id_antecedente", FILTER_VALIDATE_INT);

?>

<script src="js/timeout.js"></script>
<div id="main-container" class="container">

    <div class="row">

        <form action="<?= $BASE_URL ?>process_antecedente.php" class="borderless" id="add-movie-form" method="POST"
            enctype="multipart/form-data">
            <input type="hidden" name="type" value="create-ant">
            <input type="hidden" class="form-control" id="usuario_create_ant" value="<?= $_SESSION['email_user'] ?>"
                name="usuario_create_ant" placeholder="Digite o usuário">

            <input type="hidden" class="form-control" id="fk_usuario_ant" value="<?= $_SESSION['id_usuario'] ?>"
                name="fk_usuario_ant" placeholder="Digite o usuário">

            <div class="form-group col-sm-1">
                <?php $agora = date('Y-m-d H:i:s'); ?>
                <input type="hidden" class="form-control" id="data_create_ant" value='<?= $agora; ?>'
                    name="data_create_ant" placeholder="">
            </div>
            <div class="form-group row">
                <div class="form-group col-sm-4">
                    <label for="antecedente_ant">Antecedente</label>
                    <input type="text" class="form-control" id="antecedente_ant" name="antecedente_ant"
                        placeholder="Digite o antecedente" autofocus required>
                </div>
                <div class="form-group col-sm-4">
                    <label class="control-label" for="cid_ant">CID</label>
                    <select class="form-control selectpicker show-tick" id="cid_ant" name="cid_ant" data-size="5"
                        data-live-search="true" required>
                        <option value="">...</option>
                        <?php foreach ($cids as $cid): ?>
                            <option value="<?= $cid["id_cid"] ?>">
                                <?= $cid['cat'] . " - " . $cid["descricao"] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <hr>
            <button type="submit" class="btn btn-success" style="margin-bottom:10px"><i
                    style="font-size: 1rem;margin-right:5px;" name="type" value="edite"
                    class="fa-solid fa-check edit-icon"></i>Cadastrar</button>

            <br>
    </div>

    </form>

</div>
<!-- Inclui o CSS do Select2 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<!-- Inclui o JavaScript do Select2 -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous">
    </script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>
<?php
require_once("templates/footer.php");
?>