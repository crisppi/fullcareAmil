<?php
include_once("check_logado.php");

include_once("globals.php");

include_once("models/patologia.php");
include_once("dao/patologiaDao.php");
include_once("templates/header.php");

// Pegar o id do paceinte
$id_patologia = filter_input(INPUT_GET, "id_patologia", FILTER_SANITIZE_NUMBER_INT);

$patologia;

$patologiaDao = new patologiaDAO($conn, $BASE_URL);

//Instanciar o metodo patologia   
$patologia = $patologiaDao->findById($id_patologia);
?>
<script src="js/timeout.js"></script>

<div style="margin:15px" id="main-container">
    <h3 style="margin-top:20px">Dados da Patologia : <?= $patologia->id_patologia ?></h3>
    <div class="card">
        <div class="card-header container-fluid" id="view-contact-container">
            <span class="card-title bold">Patologia:</span>
            <span class="card-title bold"><?= $patologia->patologia_pat ?></span>
            <br>
        </div>
        <div class="card-header container-fluid" id="view-contact-container">
            <span class="card-title bold">Dias de internação:</span>
            <span class="card-title bold"><?= $patologia->dias_pato ?></span>
            <br>
        </div>
        <div class="card-header container-fluid" id="view-contact-container">
            <span class="card-title bold">Descrição:</span>
            <span class="card-title bold"><?= $patologia->descricao ?></span>
            <br>
        </div>

    </div>
    <!-- <div style="margin-left:20px" id="id-confirmacao" class="btn_acoes visible">
        <p>Deseja mesmo deletar este patologia? <?= $nome_pac ?></p>
        <div class="form-group row">
            <div class="form-group col-sm-1">
                <button class="btn btn-success styled btn-int-niveis" onclick=cancelar() type="button" id="cancelar" name="cancelar">Cancelar</button>
            </div>
            <div class="form-group col-sm-2">
                <form display="in-line" id="form_delete" action="process_patologia.php?id_patologia=<?= $id_patologia ?>" method="POST">
                    <input type="hidden" value="deletando">
                    <input type="hidden" name="type" value="delete">
                    <input type="hidden" name="id_patologia" value="<?= $patologia->id_patologia ?>">
                    <button class="btn btn-danger styled btn-int-niveis" value="deletar" type="submit" id="deletar-btn" name="deletar">Deletar</button>
                </form>
            </div>
        </div>
    </div> -->

</div>
<script>
function apareceOpcoes() {
    $('#deletar-btn').val('nao');
    let mudancaStatus = ($('#deletar-btn').val())
    let idAcoes = (document.getElementById('id-confirmacao'));
    idAcoes.style.display = 'block';
}

function deletar() {
    let idAcoes = (document.getElementById('id-confirmacao'));
    idAcoes.style.display = 'none';
    window.location = "<?= $BASE_URL ?>del_patologia.php?id_patologia=<?= $id_patologia ?>";

};

function cancelar() {
    let idAcoes = (document.getElementById('id-confirmacao'));
    idAcoes.style.display = 'none';
    window.location = "<?= $BASE_URL ?>del_patologia.php?id_patologia=<?= $id_patologia ?>";


};
src = "https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js";
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>
<?php
require_once("templates/footer.php");
?>