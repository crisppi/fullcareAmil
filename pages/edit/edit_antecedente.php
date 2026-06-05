<?php
include_once("check_logado.php");

require_once("models/usuario.php");
require_once("models/antecedente.php");
require_once("dao/usuarioDao.php");
require_once("dao/antecedenteDao.php");
require_once("templates/header.php");
include_once("dao/cidDao.php");

$user = new antecedente();
$userDao = new UserDAO($conn, $BASE_URL);
$antecedenteDao = new antecedenteDAO($conn, $BASE_URL);

// Receber id do usuário
$id_antecedente = filter_input(INPUT_GET, "id_antecedente");

$antecedente = $antecedenteDao->findById($id_antecedente);
$cid = new cidDAO($conn, $BASE_URL);
$cids = $cid->findAll();
?>

<div id="main-container" class="container">
    <div class="row">
        <form action="<?= $BASE_URL ?>process_antecedente.php" class="borderless" id="add-movie-form" method="POST"
            enctype="multipart/form-data">
            <input type="hidden" name="type" value="update-ant">
            <input type="hidden" class="form-control" id="id_antecedente" name="id_antecedente"
                value="<?= $antecedente->id_antecedente ?>">

            <input type="hidden" class="form-control" id="usuario_update_ant" value="<?= $_SESSION['email_user'] ?>"
                name="usuario_update_ant">
            <input type="hidden" class="form-control" id="fk_usuario_ant" value="<?= $_SESSION['id_usuario'] ?>"
                name="fk_usuario_ant">

            <div class="form-group-sm col-sm-1">
                <?php $agora = date('Y-m-d H:i:s'); ?>
                <input type="hidden" class="form-control" id="data_update_ant" value='<?= $agora; ?>'
                    name="data_update_ant">
            </div>

            <div class="form-group row">
                <div class="form-group col-sm-4">
                    <label for="antecedente_ant">Antecedente</label>
                    <input type="text" class="form-control" id="antecedente_ant"
                        value="<?= $antecedente->antecedente_ant ?>" name="antecedente_ant" required>
                </div>

                <div class="form-group col-sm-4">
                    <label class="control-label" for="cid_ant">CID</label>
                    <select class="form-control selectpicker show-tick" id="cid_ant" name="cid_ant" data-size="5"
                        data-live-search="true" required>
                        <option value="<?= $antecedente->fk_cid_10_ant ?>">
                            <?= $antecedente->cat . " - " . $antecedente->descricao ?>
                        </option>
                        <?php foreach ($cids as $cid): ?>
                            <option value="<?= $cid["id_cid"] ?>">
                                <?= $cid['cat'] . " - " . $cid["descricao"] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <hr>
            <button type="submit" class="btn btn-success" style="margin-bottom:10px;">
                <i style="font-size: 1rem; margin-right:5px;" class="fa-solid fa-check edit-icon"></i>Atualizar
            </button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4"
    crossorigin="anonymous"></script>


<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>