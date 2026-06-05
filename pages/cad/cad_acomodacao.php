<?php
include_once("check_logado.php");

require_once("templates/header.php");
require_once("dao/acomodacaoDao.php");
require_once("models/message.php");
include_once("models/hospital.php");
include_once("dao/hospitalDao.php");
include_once("array_dados.php");

$acomodacaoDao = new acomodacaoDAO($conn, $BASE_URL);
$hospital_geral = new hospitalDAO($conn, $BASE_URL);
$hospitals = $hospital_geral->findGeral();

// Receber id do usuário
$id_acomodacao = filter_input(INPUT_GET, "id_acomodacao");

if (empty($id_acomodacao)) {

    if (!empty($userData)) {

        $id = $userData->id_acomodacao;
    } else {

        //$message->setMessage("Usuário não encontrado!", "error", "index.php");
    }
} else {

    $userData = $userDao->findById($id_acomodacao);

    // Se não encontrar usuário
    if (!$userData) {
        $message->setMessage("acomodacao não encontrada!", "error", "index.php");
    }
}

?>

<script src="js/timeout.js"></script>
<script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-maskmoney/3.0.2/jquery.maskMoney.min.js"></script>

<div id="main-container" class="container">
    <div class="row">

        <form action="<?= $BASE_URL ?>process_acomodacao.php" class="borderless" id="add-acomodacao-form" method="POST"
            enctype="multipart/form-data">
            <input type="hidden" name="type" value="create">

            <div class="form-group row">
                <div class="form-group col-sm-6">
                    <label class="control-label" for="fk_hospital">Hospital</label>
                    <select class="form-control" id="fk_hospital" name="fk_hospital">
                        <option value="">Selecione</option>
                        <?php foreach ($hospitals as $hospital): ?>
                        <option value="<?= $hospital["id_hospital"] ?>">
                            <?= $hospital["nome_hosp"] ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group col-sm-6">
                    <label class="control-label" for="acomodacao_aco">Acomodação</label>
                    <select class="form-control" id="acomodacao_aco" name="acomodacao_aco">
                        <option value="">Selecione</option>
                        <?php
                        sort($dados_acomodacao, SORT_ASC);
                        foreach ($dados_acomodacao as $acomd) { ?>
                        <option value="<?= $acomd; ?>">
                            <?= $acomd; ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>
            </div>

            <div class="form-group row">
                <div class="form-group col-sm-6">
                    <label for="valor_aco">Valor Diária</label>
                    <input onkeyup="formatAcomod(event)" type="text" placeholder="R$0,00" class="dinheiro form-control"
                        id="valor_aco" maxlength="12" name="valor_aco">
                </div>

                <div class="form-group col-sm-6">
                    <label for="data_contrato_aco">Data contrato</label>
                    <?php $agora = date('Y-m-d H:i:s'); ?>
                    <input type="date" class="form-control" id="data_contrato_aco" value='' name="data_contrato_aco">
                </div>
            </div>

            <input type="hidden" class="form-control" id="usuario_create_acomodacao"
                value="<?= $_SESSION['email_user'] ?>" name="usuario_create_acomodacao">
            <input type="hidden" class="form-control" id="fk_usuario_acomodacao" value="<?= $_SESSION['id_usuario'] ?>"
                name="fk_usuario_acomodacao">
            <input type="hidden" class="form-control" id="fk_usuario_aco" value="<?= $_SESSION['id_usuario'] ?>"
                name="fk_usuario_aco">
            <?php $agora = date('Y-m-d H:i:s'); ?>
            <input type="hidden" class="form-control" id="data_create_acomodacao" value='<?= $agora; ?>'
                name="data_create_acomodacao">

            <hr>
            <div>
                <button type="submit" class="btn btn-success" style="margin-bottom:10px;">
                    <i style="font-size: 1rem;margin-right:5px;" name="type" value="edite"
                        class="fa-solid fa-check edit-icon"></i>Cadastrar
                </button>
            </div>
        </form>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous">
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>

<?php
require_once("templates/footer.php");
?>