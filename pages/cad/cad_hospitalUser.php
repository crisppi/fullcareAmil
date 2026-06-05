<?php
include_once("check_logado.php");
include_once("globals.php");

require_once("models/hospitalUser.php");
require_once("dao/hospitalUserDao.php");

require_once("models/message.php");

include_once("models/internacao.php");
include_once("dao/internacaoDao.php");

include_once("models/hospital.php");
include_once("dao/hospitalDao.php");

include_once("models/patologia.php");
include_once("dao/patologiaDao.php");

include_once("models/usuario.php");

// >>> ATENÇÃO AO CASE DO ARQUIVO <<<
require_once("dao/usuarioDao.php"); // use este nome exato no servidor

include_once("models/uti.php");
include_once("dao/utiDao.php");

include_once("models/gestao.php");
include_once("dao/gestaoDao.php");

include_once("models/prorrogacao.php");
include_once("dao/prorrogacaoDao.php");

include_once("models/negociacao.php");
include_once("dao/negociacaoDao.php");

include_once("array_dados.php");

/* Valores padrão para paginação/lista */
$limite = isset($limite) ? (int)$limite : 200;
$inicio = isset($inicio) ? (int)$inicio : 0;

/* Instâncias */
$internacaoDao   = new internacaoDAO($conn, $BASE_URL);
$hospital_geral  = new hospitalDAO($conn, $BASE_URL);
$hospitals       = $hospital_geral->findGeral($limite, $inicio);

$usuarioDao      = new userDAO($conn, $BASE_URL);
$usuarios        = $usuarioDao->findGeral($limite, $inicio);

$patologiaDao    = new patologiaDAO($conn, $BASE_URL);
$patologias      = $patologiaDao->findGeral();

$gestao          = new gestaoDAO($conn, $BASE_URL);
$gestaoIdMax     = $gestao->findMax();
$findMaxGesInt   = $gestao->findMaxGesInt();

$uti             = new utiDAO($conn, $BASE_URL);
$utiIdMax        = $uti->findMaxUTI();
$findMaxUtiInt   = $uti->findMaxUtiInt();

$prorrogacao     = new prorrogacaoDAO($conn, $BASE_URL);
$prorrogacaoIdMax = $prorrogacao->findMaxPror();
$findMaxProInt   = $prorrogacao->findMaxProInt();

$negociacao      = new negociacaoDAO($conn, $BASE_URL);
$negociacaoLast  = new negociacaoDAO($conn, $BASE_URL);

$hospitalUserDao = new hospitalUserDAO($conn, $BASE_URL);

// Receber id do usuário (se houver)
$id_hospitalUser = filter_input(INPUT_GET, "id_hospitalUser", FILTER_VALIDATE_INT);

?>
<div id="main-container" class="container form_container" style="margin-top:16px;">
    <div class="row">
        <form class="formulario-borderless" action="<?= htmlspecialchars($BASE_URL) ?>process_hospitalUser.php"
            id="add-movie-form" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="type" value="create">

            <div class="form-group col-sm-3">
                <label class="control-label col-sm-3" for="fk_hospital_user">Hospital</label>
                <select class="form-control selectpicker show-tick"
                    id="fk_hospital_user"
                    name="fk_hospital_user"
                    required
                    data-live-search="true"
                    data-size="8"
                    data-width="100%"
                    title="Selecione o Hospital">
                    <option value="">Selecione o Hospital</option>
                    <?php if (!empty($hospitals)): ?>
                    <?php foreach ($hospitals as $hospital): ?>
                    <option value="<?= (int)$hospital['id_hospital'] ?>">
                        <?= htmlspecialchars($hospital['nome_hosp']) ?>
                    </option>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group col-sm-3">
                <label class="control-label" for="fk_usuario_hosp">Usuário</label>
                <select class="form-control selectpicker show-tick"
                    id="fk_usuario_hosp"
                    name="fk_usuario_hosp"
                    required
                    data-live-search="true"
                    data-size="8"
                    data-width="100%"
                    title="Selecione o usuário">
                    <option value="">Selecione o usuário</option>
                    <?php if (!empty($usuarios)): ?>
                    <?php foreach ($usuarios as $usuario): ?>
                    <option value="<?= (int)$usuario['id_usuario'] ?>">
                        <?= htmlspecialchars($usuario['usuario_user']) ?>
                    </option>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <br>
            <button type="submit" class="btn btn-primary">
                <i style="font-size:1rem;margin-right:5px;" class="fa-solid fa-check edit-icon"></i>
                Cadastrar
            </button>
            <br>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (!window.jQuery || !jQuery.fn || !jQuery.fn.selectpicker) return;
    ['#fk_hospital_user', '#fk_usuario_hosp'].forEach(function(sel) {
        var $el = jQuery(sel);
        if (!$el.length) return;
        if (!$el.data('selectpicker')) {
            $el.selectpicker();
        }
        $el.selectpicker('refresh');
    });
});

// (mantenha apenas UMA definição por função)
function mascara(i, t) {
    var v = i.value;
    if (isNaN(v[v.length - 1])) {
        i.value = v.substring(0, v.length - 1);
        return;
    }
    if (t === "data") {
        i.setAttribute("maxlength", "10");
        if (v.length === 2 || v.length === 5) i.value += "/";
    }
    if (t === "cpf") {
        i.setAttribute("maxlength", "14");
        if (v.length === 3 || v.length === 7) i.value += ".";
        if (v.length === 11) i.value += "-";
    }
    if (t === "cnpj") {
        i.setAttribute("maxlength", "18");
        if (v.length === 2 || v.length === 6) i.value += ".";
        if (v.length === 10) i.value += "/";
        if (v.length === 15) i.value += "-";
    }
    if (t === "cep") {
        i.setAttribute("maxlength", "9");
        if (v.length === 5) i.value += "-";
    }
}

function mascaraTelefone(event) {
    let tecla = event.key;
    let telefone = event.target.value.replace(/\D+/g, "");
    if (/^[0-9]$/.test(tecla)) {
        telefone = telefone + tecla;
        let tamanho = telefone.length;
        if (tamanho >= 12) return false;
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
    if (!["Backspace", "Delete"].includes(tecla)) return false;
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous">
</script>
