<?php
include_once("check_logado.php");

require_once("templates/header.php");
require_once("dao/usuarioDao.php");
require_once("models/message.php");
include_once("array_dados.php");
include_once("models/seguradora.php");
include_once("dao/seguradoraDao.php");

$usuarioDao = new userDAO($conn, $BASE_URL);
$seguradoraDao = new seguradoraDAO($conn, $BASE_URL);
$seguradoras = $seguradoraDao->selectAllSeguradora();

// Receber id do usuário
$id_usuario = filter_input(INPUT_GET, "id_usuario");

if (empty($id_usuario)) {

    if (!empty($userData)) {

        $id = $userData->id_usuario;
    } else {
    }
} else {

    $userData = $userDao->findById($id_usuario);

    // Se não encontrar usuário
    if (!$userData) {
        $message->setMessage("Usuário não encontrado!", "error", "index.php");
    }
}
?>
<link rel="stylesheet" href="css/form_cad_internacao.css">
<div id="main-container" class="internacao-page cadastro-layout">
    <div class="internacao-page__hero">
        <div class="internacao-page__hero-main">
            <h1>Cadastrar usuário</h1>
        </div>
        <div class="hero-actions">
            <a href="<?= $BASE_URL ?>list_usuario.php" class="hero-back-btn">Voltar para lista</a>
            <span class="internacao-page__tag">Campos obrigatórios em destaque</span>
        </div>
    </div>
    <div class="internacao-page__content">
        <form action="<?= $BASE_URL ?>process_usuario.php" id="add-movie-form" method="POST"
            enctype="multipart/form-data" class="needs-validation visible entity-form">
            <input type="hidden" name="type" value="create">
            <div class="internacao-card internacao-card--general">
                <div class="internacao-card__header">
                    <div>
                        <div class="internacao-card__eyebrow">Cadastros</div>
                        <h2 class="internacao-card__title">Dados do usuário</h2>
                    </div>
                    <span class="internacao-card__tag">Cadastro base</span>
                </div>
                <div class="internacao-card__body">
                    <div class="entity-step-card">
                        <div class="entity-step-header">
                            <div class="entity-step-copy">
                                <span class="entity-step-kicker">Passo 1</span>
                                <h3 class="entity-step-title">Identificação do usuário</h3>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="form-group col-sm-2">
                                <label for="cpf_user">CPF</label>
                                <input type="text" oninput="mascara(this, 'cpf')" class="form-control" id="cpf_user"
                                    name="cpf_user">
                            </div>
                            <div class="form-group col-sm-3">
                                <label for="usuario_user">Nome do Usuário</label>
                                <input type="text" class="form-control" id="usuario_user" name="usuario_user" autofocus required>
                            </div>
                            <div class="form-group col-sm-1">
                                <label class="control-label" for="sexo_user">Sexo</label>
                                <select class="form-control" name="sexo_user">
                                    <option value="">Selecione</option>
                                    <option value="f">Feminino</option>
                                    <option value="m">Masculino</option>
                                </select>
                            </div>
                            <div class="form-group col-sm-2">
                                <label for="usuario_user">Idade</label>
                                <input type="text" class="form-control" id="idade_user" name="idade_user">
                            </div>
                            <div class="form-group col-sm-2">
                                <label class="control-label" for="data_admissao_user">Admissão</label>
                                <input class="form-control" type="date" id="data_admissao_user" name="data_admissao_user">
                            </div>
                            <div class="form-group col-sm-2">
                                <label class="control-label" for="ativo_user">Ativo</label>
                                <select class="form-control" id="ativo_user" name="ativo_user">
                                    <option selected value="s">Sim</option>
                                    <option value="n">Não</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="entity-step-card">
                        <div class="entity-step-header">
                            <div class="entity-step-copy">
                                <span class="entity-step-kicker">Passo 2</span>
                                <h3 class="entity-step-title">Endereço</h3>
                            </div>
                        </div>
            <div class="form-group row">
                <div class="form-group col-sm-3">
                    <label for="cep_user">CEP</label>
                    <input type="text" onkeyup="consultarCEP(this, 'user')" class="form-control" id="cep_user"
                        name="cep_user" placeholder="Digite o CEP">
                </div>
                <div class="form-group col-sm-3">
                    <label for="endereco_user">Endereço</label>
                    <input type="text" readonly class="form-control" id="endereco_user" name="endereco_user"
                        placeholder="...">
                </div>
                <div class="form-group col-sm-3">
                    <label for="bairro_user">Bairro</label>
                    <input type="text" readonly class="form-control" id="bairro_user" name="bairro_user"
                        placeholder="...">
                </div>
                <div class="form-group col-sm-3">
                    <label for="cidade_user">Cidade</label>
                    <input type="text" readonly class="form-control" id="cidade_user" name="cidade_user"
                        placeholder="...">
                </div>
                <div class="form-group col-sm-2">
                    <label for="estado_user">Estado</label>
                    <select class="form-control" id="estado_user" name="estado_user">
                        <option value="">...</option>
                        <?php foreach ($estado_sel as $estado): ?>
                        <option value="<?= $estado ?>">
                            <?= $estado ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-sm-2">
                    <label for="numero_user">Número</label>
                    <input type="text" class="form-control" id="numero_user" name="numero_user">
                </div>
                <div class="form-group col-sm-2">
                    <label for="numero_user">Complemento</label>
                    <input type="text" class="form-control" id="numero_user" name="numero_user">
                </div>
            </div>
                    </div>
                    <div class="entity-step-card">
                        <div class="entity-step-header">
                            <div class="entity-step-copy">
                                <span class="entity-step-kicker">Passo 3</span>
                                <h3 class="entity-step-title">Contato</h3>
                            </div>
                        </div>
            <div class="form-group row">

                <div class="form-group col-sm-3">
                    <label for="email_user">E-mail Principal</label>
                    <input type="email" class="form-control" id="email_user" required name="email_user">
                </div>
                <div class="form-group col-sm-3">
                    <label for="email02_user">E-mail Alternativo</label>
                    <input type="email" class="form-control" id="email02_user" name="email02_user">
                </div>
                <div class="form-group col-sm-2">
                    <label for="telefone01_user">Telefone Residencial</label>
                    <input type="text" onkeydown="return mascaraTelefone(event)" maxlength="11" class="form-control"
                        id="telefone01_user" name="telefone01_user">
                </div>
                <div class="form-group col-sm-2">
                    <label for="telefone02_user">Celular</label>
                    <input type="text" onkeydown="return mascaraTelefone(event)" maxlength="11" class="form-control"
                        id="telefone02_user" name="telefone02_user">
                </div>
            </div>
                    </div>
                    <div class="entity-step-card">
                        <div class="entity-step-header">
                            <div class="entity-step-copy">
                                <span class="entity-step-kicker">Passo 4</span>
                                <h3 class="entity-step-title">Acesso e perfil</h3>
                            </div>
                        </div>
            <div class="form-group row">
                <div class="form-group col-sm-2">
                    <label class="control-label" for="cargo_user">Cargo</label>
                    <select class="form-control" id="cargo_user" name="cargo_user">
                        <option value="">Selecione</option>
                        <?php
                        sort($cargo_user, SORT_ASC);
                        foreach ($cargo_user as $cargo) { ?>
                        <option value="<?= $cargo; ?>">
                            <?= $cargo; ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-group col-sm-2" id="seguradora-wrap" style="display:none;">
                    <label class="control-label" for="fk_seguradora_user">Seguradora</label>
                    <select class="form-control" id="fk_seguradora_user" name="fk_seguradora_user">
                        <option value="">Selecione</option>
                        <?php foreach ($seguradoras as $seg): ?>
                        <option value="<?= (int) $seg['id_seguradora'] ?>">
                            <?= htmlspecialchars($seg['seguradora_seg'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-sm-2 ">
                    <label class="control-label" for="nivel_user">Nível</label>
                    <select class="form-control" name="nivel_user">
                        <option value="">Nível</option>
                        <option value="1">Nível 01</option>
                        <option value="2">Nível 02</option>
                        <option value="3">Nível 03</option>
                        <option value="4">Nível 04</option>
                        <option value="5">Nível 05</option>
                        <option value="-1">Hospital</option>
                    </select>
                </div>
                <div class="form-group col-sm-2 ">
                    <label class="control-label" for="depto_user">Departamento</label>
                    <select class="form-control" name="depto_user">
                        <option value="">Selecione</option>
                        <?php foreach ($depto_sel as $depto): ?>
                        <option value="<?= $depto ?>">
                            <?= $depto ?>
                        </option>
                        <?php endforeach; ?>
                        </option>
                    </select>
                </div>
                <div class="form-group col-sm-2 ">
                    <label class="control-label" for="vinculo_user">Vínculo</label>
                    <select class="form-control" name="vinculo_user">
                        <option value="">Selecione</option>
                        <?php foreach ($vinculo_sel as $vinculo): ?>
                        <option value="<?= $vinculo ?>">
                            <?= $vinculo ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>


                <div class="form-group col-sm-2">
                    <label for="reg_profissional_user">Reg.Profissional</label>
                    <input type="text" class="form-control" id="reg_profissional_user" name="reg_profissional_user">
                </div>
                <div class="form-group col-sm-1">
                    <label for="tipo_profissional_user">Tipo Reg</label>
                    <select class="form-control" name="tipo_profissional_user">
                        <option value="">Tipo</option>

                        <?php foreach ($tipo_reg as $reg): ?>
                        <option value="<?= $reg ?>">
                            <?= $reg ?>
                        </option>
                        <?php endforeach; ?>
                        </option>
                    </select>
                </div>

            </div>
            <div class="form-group row">
                <div class="form-group col-sm-2">
                    <label for="senha_user">Senha</label>
                    <input type="password" class="form-control" id="senha_user" name="senha_user"
                        placeholder="Digite a senha" minlength="8"
                        pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}"
                        title="Mínimo 8 caracteres, com letra maiúscula, minúscula, número e caractere especial.">
                    <small class="form-text text-muted">Mínimo 8 caracteres, com maiúscula, minúscula, número e especial.</small>
                </div>
                <div class="form-group col-sm-2">
                    <input type="hidden" class="form-control" value="s" id="senha_default_user"
                        name="senha_default_user">
                </div>
                <div class="form-group col-sm-2">
                    <input type="hidden" class="form-control" value="s" id="login_user"
                        value="<?= $_SESSION['email_user'] ?>" name="login_user">
                </div>

                <div class="form-group col-sm-4">
                    <?php $agora = date('Y-m-d'); ?>
                    <input class="visible" type="hidden" class="form-control" value='<?= $agora; ?>'
                        id="data_create_pac" name="data_create_pac" placeholder="">
                </div>
                <div class="form-group col-sm-4">
                    <input type="hidden" class="form-control" id="usuario_create_pac"
                        value="<?= $_SESSION['email_user'] ?>" name="usuario_create_pac">
                </div>
                <div class="form-group col-sm-4">
                    <input type="hidden" class="form-control" id="fk_usuario_user"
                        value="<?= $_SESSION['id_usuario'] ?>" name="fk_usuario_user">
                </div>
                <div class="form-group col-sm-12">
                    <label for="obs_user">Observações</label>
                    <textarea type="textarea" rows="2" onclick="aumentarTextObs()" class="form-control" id="obs_user"
                        name="obs_user" placeholder="Digite as observações do usuário"></textarea>
                </div>
                <div class="form-group-sm row">
                    <div class="form-group-sm col-sm-2">
                        <label for="foto_usuario">Foto</label>
                        <input type="file" onclick="novoArquivo()" name="foto_usuario" id="foto_usuario"
                            accept="image/png, image/jpeg">
                        <div class="notif-input oculto" id="notifImagem">
                            Tamanho do arquivo inválido!
                        </div>
                    </div>
                </div>
            </div>
                    </div>
                </div>
            </div>
            <div class="entity-actions-bar">
                <button type="submit" class="btn btn-primary">Cadastrar</button>
            </div>
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
function toggleSeguradoraField() {
    var cargoSel = document.getElementById('cargo_user');
    var wrap = document.getElementById('seguradora-wrap');
    var segSel = document.getElementById('fk_seguradora_user');
    if (!cargoSel || !wrap || !segSel) return;
    var cargoNorm = cargoSel.value.toString().trim().toLowerCase().replace(/[^a-z]/g, '');
    var isGestor = cargoNorm.indexOf('gestorseguradora') === 0;
    wrap.style.display = isGestor ? '' : 'none';
    segSel.required = isGestor;
    if (!isGestor) segSel.value = '';
}
document.addEventListener('DOMContentLoaded', function () {
    var cargoSel = document.getElementById('cargo_user');
    if (cargoSel) {
        cargoSel.addEventListener('change', toggleSeguradoraField);
    }
    toggleSeguradoraField();
});
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
var text_obs = document.querySelector("#obs_user");

function aumentarTextObs() {
    if (text_obs.rows == "2") {
        text_obs.rows = "20"
    } else {
        text_obs.rows = "2"
    }
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous">
</script>


<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>
<?php
require_once("templates/footer.php");
?>
