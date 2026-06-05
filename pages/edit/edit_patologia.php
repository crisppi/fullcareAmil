<?php
include_once("check_logado.php");

require_once("models/usuario.php");
require_once("models/patologia.php");
require_once("dao/usuarioDao.php");
require_once("dao/patologiaDao.php");
require_once("templates/header.php");
include_once("dao/cidDao.php");

$user = new patologia();
$userDao = new UserDAO($conn, $BASE_URL);
$cid = new cidDAO($conn, $BASE_URL);
$patologiaDao = new patologiaDAO($conn, $BASE_URL);

// LISTAR CIDS
$cids = $cid->findAll();

// Receber id do usuário
$id_patologia = filter_input(INPUT_GET, "id_patologia");

$patologia = $patologiaDao->findById($id_patologia);

?>
<!-- formulario update -->
<div id="main-container" class="container">
    <div class="row">
        <form action="<?= $BASE_URL ?>process_patologia.php" class="container-fluid fundo_tela_cadastros"
            id="add-movie-form" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="type" value="update">
            <input type="hidden" class="form-control" id="id_patologia" name="id_patologia"
                value="<?= $patologia->id_patologia ?>">

            <div class="form-group-sm col-sm-1">
                <?php $agora = date('Y-m-d H:i:s'); ?>
                <input type="hidden" class="form-control" id="data_update_pat" value='<?= $agora; ?>'
                    name="data_update_pat">
            </div>

            <div class="form-group row">
                <div class="form-group col-sm-6">
                    <label for="patologia_pat">Patologia</label>
                    <input type="text" class="form-control" id="patologia_pat" value="<?= $patologia->patologia_pat ?>"
                        name="patologia_pat" required>
                </div>

                <div class="form-group col-sm-6">
                    <label for="dias_pato">Diárias - DRG</label>
                    <input type="number" class="form-control" id="dias_pato" value="<?= $patologia->dias_pato ?>"
                        name="dias_pato" required>
                </div>
            </div>

            <div class="form-group row">
                <div class="form-group col-sm-6">
                    <label class="control-label" for="cid_pat">CID</label>
                    <select class="form-control selectpicker show-tick" style="background: white !important"
                        data-size="5" id="cid_pat" name="cid_pat" data-live-search="true" required>
                        <option value="<?= $patologia->fk_cid_10_pat ?>">
                            <?= $patologia->cat . " - " . $patologia->descricao ?>
                        </option>
                        <?php foreach ($cids as $cid): ?>
                            <option value="<?= $cid["id_cid"] ?>">
                                <?= $cid['cat'] . " - " . $cid["descricao"] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group col-sm-6">
                    <input type="hidden" class="form-control" id="fk_usuario_pat" value="<?= $_SESSION['id_usuario'] ?>"
                        name="fk_usuario_pat">
                </div>
            </div>

            <hr>
            <button type="submit" class="btn btn-success" style="margin-bottom:10px;">
                <i style="font-size: 1rem;margin-right:5px;" class="fa-solid fa-check edit-icon"></i>Atualizar
            </button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous">
    </script>


<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>

</html>