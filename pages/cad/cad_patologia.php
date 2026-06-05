<?php
include_once("check_logado.php");

require_once("templates/header.php");
require_once("dao/patologiaDao.php");
require_once("models/message.php");
include_once("dao/cidDao.php");

$patologiaDao = new patologiaDAO($conn, $BASE_URL);
$cid = new cidDAO($conn, $BASE_URL);

// LISTAR CIDS
$cids = $cid->findAll();

// Receber id do usuário
$id_patologia = filter_input(INPUT_GET, "id_patologia");

?>
<div id="main-container" class="container">
    <div class="row">
        <form action="<?= $BASE_URL ?>process_patologia.php" class="container-fluid fundo_tela_cadastros"
            id="add-movie-form" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="type" value="create">
            <input type="hidden" class="form-control" id="usuario_create_pat" value="<?= $_SESSION['email_user'] ?>"
                name="usuario_create_pat" placeholder="Digite o usuário">

            <div class="form-group-sm col-sm-1">
                <?php $agora = date('Y-m-d H:i:s'); ?>
                <input type="hidden" class="form-control" id="data_create_pat" value='<?= $agora; ?>'
                    name="data_create_pat" placeholder="">
            </div>

            <div class="form-group row">
                <div class="form-group col-sm-6">
                    <label for="patologia_pat">Patologia</label>
                    <input type="text" class="form-control" id="patologia_pat" name="patologia_pat"
                        placeholder="Digite a patologia" autofocus required>
                </div>

                <div class="form-group col-sm-6">
                    <label for="dias_pato">Diárias - DRG</label>
                    <input type="number" class="form-control" id="dias_pato" name="dias_pato"
                        placeholder="Digite o número de diárias" required>
                </div>
            </div>

            <div class="form-group row">
                <div class="form-group col-sm-6">
                    <label class="control-label" for="cid_pat">CID</label>
                    <select class="form-control selectpicker show-tick"  data-size="5" id="cid_pat" name="cid_pat" data-live-search="true"
                        required>
                        <option value="">Selecione o CID</option>
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
                <i style="font-size: 1rem;margin-right:5px;" name="type" value="edite"
                    class="fa-solid fa-check edit-icon"></i>Cadastrar
            </button>
           
        </form>
    </div>
</div>


<script>
    function mascara(i) {

        var v = i.value;

        if (isNaN(v[v.length - 1])) { // impede entrar outro caractere que não seja número
            i.value = v.substring(0, v.length - 1);
            return;
        }

        i.setAttribute("maxlength", "14");
        if (v.length == 3 || v.length == 7) i.value += ".";
        if (v.length == 11) i.value += "-";

    }
</script>
<script>
    function mascara(i, t) {

        var v = i.value;

        if (isNaN(v[v.length - 1])) {
            i.value = v.substring(0, v.length - 1);
            return;
        }

        if (t == "data") {
            i.setAttribute("maxlength", "10");
            if (v.length == 2 || v.length == 5) i.value += "/";
        }

        if (t == "cpf") {
            i.setAttribute("maxlength", "14");
            if (v.length == 3 || v.length == 7) i.value += ".";
            if (v.length == 11) i.value += "-";
        }

        if (t == "cnpj") {
            i.setAttribute("maxlength", "18");
            if (v.length == 2 || v.length == 6) i.value += ".";
            if (v.length == 10) i.value += "/";
            if (v.length == 15) i.value += "-";
        }

        if (t == "cep") {
            i.setAttribute("maxlength", "9");
            if (v.length == 5) i.value += "-";
        }

        if (t == "tel") {
            if (v[0] == 12) {

                i.setAttribute("maxlength", "10");
                if (v.length == 5) i.value += "-";
                if (v.length == 0) i.value += "(";

            } else {
                i.setAttribute("maxlength", "9");
                if (v.length == 4) i.value += "-";
            }
        }
    }

    function mascaraTelefone(event) {
        let tecla = event.key;
        let telefone = event.target.value.replace(/\D+/g, "");

        if (/^[0-9]$/i.test(tecla)) {
            telefone = telefone + tecla;
            let tamanho = telefone.length;

            if (tamanho >= 12) {
                return false;
            }

            if (tamanho > 10) {
                telefone = telefone.replace(/^(\d\d)(\d{5})(\d{4}).*/, "($1) $2-$3");
            } else if (tamanho > 5) {
                telefone = telefone.replace(/^(\d\d)(\d{4})(\d{0,4}).*/, "($1) $2-$3");
            } else if (tamanho > 2) {
                telefone = telefone.replace(/^(\d\d)(\d{0,5})/, "($1) $2");
            } else {
                telefone = telefone.replace(/^(\d*)/, "($1");
            }

            event.target.value = telefone;
        }
 
        if (!["Backspace", "Delete"].includes(tecla)) {
            return false;
        }
    }
</script>

<?php
require_once("templates/footer.php");
?>
<!-- Inclui o CSS do Select2 -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<!-- Inclui o JavaScript do Select2 -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous">
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>