<?php
include_once("check_logado.php");

require_once("models/usuario.php");
require_once("dao/usuarioDao.php");
require_once("templates/header.php");
require_once("array_dados.php");
require_once("models/seguradora.php");
require_once("dao/seguradoraDao.php");

$usuarioDao = new UserDAO($conn, $BASE_URL);
$seguradoraDao = new seguradoraDAO($conn, $BASE_URL);
$seguradoras = $seguradoraDao->selectAllSeguradora();

$id_usuario = filter_input(INPUT_GET, "id_usuario", FILTER_VALIDATE_INT);
$usuario = $id_usuario ? $usuarioDao->findById_user($id_usuario) : null;

if (!$usuario) {
    $message->setMessage("Usuário não encontrado!", "error", "list_usuario.php");
}

function usuarioField($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function usuarioCargoNormalizado(?string $cargo): string
{
    $cargo = mb_strtolower(trim((string)$cargo), 'UTF-8');
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $cargo);
    $cargo = $ascii !== false ? $ascii : $cargo;
    return preg_replace('/[^a-z]/', '', $cargo);
}

$cargoAtualNorm = usuarioCargoNormalizado($usuario->cargo_user ?? '');
$isGestorSeguradoraUser = strpos($cargoAtualNorm, 'gestorseguradora') === 0;
?>
<link rel="stylesheet" href="css/form_cad_internacao.css">

<div id="main-container" class="internacao-page cadastro-layout">
    <div class="internacao-page__hero">
        <div class="internacao-page__hero-main">
            <h1>Atualizar usuário</h1>
        </div>
        <div class="hero-actions">
            <a href="<?= $BASE_URL ?>list_usuario.php" class="hero-back-btn">Voltar para lista</a>
            <span class="internacao-page__tag">Cadastro e acessos</span>
        </div>
    </div>

    <div class="internacao-page__content">
        <form action="<?= $BASE_URL ?>process_usuario.php" id="add-movie-form" method="POST"
            enctype="multipart/form-data" class="needs-validation visible entity-form">
            <input type="hidden" name="type" value="update">
            <input type="hidden" name="id_usuario" value="<?= (int)$usuario->id_usuario ?>">
            <input type="hidden" name="login_user" value="<?= usuarioField($usuario->login_user ?? '') ?>">
            <input type="hidden" name="usuario_create_user" value="<?= usuarioField($usuario->usuario_create_user ?? '') ?>">
            <input type="hidden" name="data_create_user" value="<?= usuarioField($usuario->data_create_user ?? '') ?>">
            <input type="hidden" name="senha_default_user" value="<?= usuarioField($usuario->senha_default_user ?? '') ?>">
            <input type="hidden" name="fk_usuario_user" value="<?= usuarioField($_SESSION['id_usuario'] ?? '') ?>">

            <div class="internacao-card internacao-card--general">
                <div class="internacao-card__header">
                    <div>
                        <div class="internacao-card__eyebrow">Cadastros</div>
                        <h2 class="internacao-card__title">Dados do usuário</h2>
                    </div>
                    <span class="internacao-card__tag">Registro #<?= (int)$usuario->id_usuario ?></span>
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
                                    name="cpf_user" value="<?= usuarioField($usuario->cpf_user) ?>">
                            </div>
                            <div class="form-group col-sm-4">
                                <label for="usuario_user">Nome do Usuário</label>
                                <input type="text" class="form-control" id="usuario_user" name="usuario_user"
                                    value="<?= usuarioField($usuario->usuario_user) ?>" autofocus required>
                            </div>
                            <div class="form-group col-sm-2">
                                <label class="control-label" for="sexo_user">Sexo</label>
                                <select class="form-control" id="sexo_user" name="sexo_user">
                                    <option value="f" <?= ($usuario->sexo_user === 'f') ? 'selected' : '' ?>>Feminino</option>
                                    <option value="m" <?= ($usuario->sexo_user === 'm') ? 'selected' : '' ?>>Masculino</option>
                                </select>
                            </div>
                            <div class="form-group col-sm-2">
                                <label for="idade_user">Idade</label>
                                <input type="text" class="form-control" id="idade_user" name="idade_user"
                                    value="<?= usuarioField($usuario->idade_user) ?>">
                            </div>
                            <div class="form-group col-sm-2">
                                <label class="control-label" for="data_admissao_user">Admissão</label>
                                <input class="form-control" type="date" id="data_admissao_user" name="data_admissao_user"
                                    value="<?= usuarioField($usuario->data_admissao_user) ?>">
                            </div>
                            <div class="form-group col-sm-2">
                                <label class="control-label" for="ativo_user">Ativo</label>
                                <select class="form-control" id="ativo_user" name="ativo_user">
                                    <option value="s" <?= ($usuario->ativo_user === 's') ? 'selected' : '' ?>>Sim</option>
                                    <option value="n" <?= ($usuario->ativo_user === 'n') ? 'selected' : '' ?>>Não</option>
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
                                <input type="text" class="form-control" id="endereco_user" name="endereco_user"
                                    value="<?= usuarioField($usuario->endereco_user) ?>" placeholder="...">
                            </div>
                            <div class="form-group col-sm-3">
                                <label for="bairro_user">Bairro</label>
                                <input type="text" class="form-control" id="bairro_user" name="bairro_user"
                                    value="<?= usuarioField($usuario->bairro_user) ?>" placeholder="...">
                            </div>
                            <div class="form-group col-sm-3">
                                <label for="cidade_user">Cidade</label>
                                <input type="text" class="form-control" id="cidade_user" name="cidade_user"
                                    value="<?= usuarioField($usuario->cidade_user) ?>" placeholder="...">
                            </div>
                            <div class="form-group col-sm-2">
                                <label for="estado_user">Estado</label>
                                <select class="form-control" id="estado_user" name="estado_user">
                                    <option value="">...</option>
                                    <?php foreach ($estado_sel as $estado): ?>
                                        <option value="<?= usuarioField($estado) ?>" <?= ((string)$usuario->estado_user === (string)$estado) ? 'selected' : '' ?>>
                                            <?= usuarioField($estado) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group col-sm-2">
                                <label for="numero_user">Número</label>
                                <input type="text" class="form-control" id="numero_user" name="numero_user"
                                    value="<?= usuarioField($usuario->numero_user) ?>">
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
                                <input type="email" class="form-control" id="email_user" name="email_user"
                                    value="<?= usuarioField($usuario->email_user) ?>">
                            </div>
                            <div class="form-group col-sm-3">
                                <label for="email02_user">E-mail Alternativo</label>
                                <input type="email" class="form-control" id="email02_user" name="email02_user"
                                    value="<?= usuarioField($usuario->email02_user) ?>">
                            </div>
                            <div class="form-group col-sm-2">
                                <label for="telefone01_user">Telefone Residencial</label>
                                <input type="text" onkeydown="return mascaraTelefone(event)" maxlength="11" class="form-control"
                                    id="telefone01_user" name="telefone01_user" value="<?= usuarioField($usuario->telefone01_user) ?>">
                            </div>
                            <div class="form-group col-sm-2">
                                <label for="telefone02_user">Celular</label>
                                <input type="text" onkeydown="return mascaraTelefone(event)" maxlength="11" class="form-control"
                                    id="telefone02_user" name="telefone02_user" value="<?= usuarioField($usuario->telefone02_user) ?>">
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
                                    $cargos = $cargo_user;
                                    sort($cargos, SORT_ASC);
                                    foreach ($cargos as $cargoOption): ?>
                                        <option value="<?= usuarioField($cargoOption) ?>" <?= ((string)$usuario->cargo_user === (string)$cargoOption) ? 'selected' : '' ?>>
                                            <?= usuarioField($cargoOption) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group col-sm-2" id="seguradora-wrap" style="<?= $isGestorSeguradoraUser ? '' : 'display:none;' ?>">
                                <label class="control-label" for="fk_seguradora_user">Seguradora</label>
                                <select class="form-control" id="fk_seguradora_user" name="fk_seguradora_user">
                                    <option value="">Selecione</option>
                                    <?php foreach ($seguradoras as $seg): ?>
                                        <option value="<?= (int)$seg['id_seguradora'] ?>" <?= ((int)($usuario->fk_seguradora_user ?? 0) === (int)$seg['id_seguradora']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($seg['seguradora_seg'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group col-sm-2">
                                <label class="control-label" for="nivel_user">Nível</label>
                                <select class="form-control" id="nivel_user" name="nivel_user">
                                    <option value="1" <?= ((string)$usuario->nivel_user === '1') ? 'selected' : '' ?>>Nível 01</option>
                                    <option value="2" <?= ((string)$usuario->nivel_user === '2') ? 'selected' : '' ?>>Nível 02</option>
                                    <option value="3" <?= ((string)$usuario->nivel_user === '3') ? 'selected' : '' ?>>Nível 03</option>
                                    <option value="4" <?= ((string)$usuario->nivel_user === '4') ? 'selected' : '' ?>>Nível 04</option>
                                    <option value="5" <?= ((string)$usuario->nivel_user === '5') ? 'selected' : '' ?>>Nível 05</option>
                                    <option value="-1" <?= ((string)$usuario->nivel_user === '-1') ? 'selected' : '' ?>>Hospital</option>
                                </select>
                            </div>
                            <div class="form-group col-sm-2">
                                <label class="control-label" for="depto_user">Departamento</label>
                                <select class="form-control" id="depto_user" name="depto_user">
                                    <option value="">Selecione</option>
                                    <?php foreach ($depto_sel as $depto): ?>
                                        <option value="<?= usuarioField($depto) ?>" <?= ((string)$usuario->depto_user === (string)$depto) ? 'selected' : '' ?>>
                                            <?= usuarioField($depto) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group col-sm-2">
                                <label class="control-label" for="vinculo_user">Vínculo</label>
                                <select class="form-control" id="vinculo_user" name="vinculo_user">
                                    <option value="">Selecione</option>
                                    <?php foreach ($vinculo_sel as $vinculo): ?>
                                        <option value="<?= usuarioField($vinculo) ?>" <?= ((string)$usuario->vinculo_user === (string)$vinculo) ? 'selected' : '' ?>>
                                            <?= usuarioField($vinculo) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group col-sm-2">
                                <label class="control-label" for="data_demissao_user">Demissão</label>
                                <input class="form-control" type="date" id="data_demissao_user" name="data_demissao_user"
                                    value="<?= usuarioField($usuario->data_demissao_user) ?>">
                            </div>
                            <div class="form-group col-sm-2">
                                <label for="reg_profissional_user">Reg.Profissional</label>
                                <input type="text" class="form-control" id="reg_profissional_user" name="reg_profissional_user"
                                    value="<?= usuarioField($usuario->reg_profissional_user) ?>">
                            </div>
                            <div class="form-group col-sm-2">
                                <label for="tipo_reg_user">Tipo Reg</label>
                                <select class="form-control" id="tipo_reg_user" name="tipo_reg_user">
                                    <option value="">Tipo</option>
                                    <?php foreach ($tipo_reg as $reg): ?>
                                        <option value="<?= usuarioField($reg) ?>" <?= ((string)$usuario->tipo_reg_user === (string)$reg) ? 'selected' : '' ?>>
                                            <?= usuarioField($reg) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group row">
                            <div class="form-group col-sm-2">
                                <label for="senha_user">Senha</label>
                                <input type="password" class="form-control" id="senha_user" name="senha_user"
                                    placeholder="Deixe em branco para manter" minlength="8"
                                    pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}"
                                    title="Mínimo 8 caracteres, com letra maiúscula, minúscula, número e caractere especial.">
                                <small class="form-text text-muted">Ao preencher, use no mínimo 8 caracteres com maiúscula, minúscula, número e especial.</small>
                            </div>
                            <div class="form-group col-sm-10">
                                <label for="obs_user">Observações</label>
                                <textarea rows="2" onclick="aumentarTextObs()" class="form-control" id="obs_user"
                                    name="obs_user"><?= htmlspecialchars((string)$usuario->obs_user, ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>
                            <div class="form-group-sm col-sm-3">
                                <label for="foto_usuario">Foto</label>
                                <input type="file" onclick="novoArquivo()" name="foto_usuario" id="foto_usuario"
                                    accept="image/png, image/jpeg">
                                <div class="notif-input oculto" id="notifImagem">Tamanho do arquivo inválido!</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="entity-actions-bar">
                <button type="submit" class="btn btn-primary">Atualizar</button>
            </div>
        </form>
    </div>
</div>

<script>
function aumentarTextObs() {
    const textObs = document.querySelector("#obs_user");
    if (!textObs) return;
    textObs.rows = textObs.rows === 2 ? 20 : 2;
}

function mascara(i, t) {
    var v = i.value;
    if (isNaN(v[v.length - 1])) {
        i.value = v.substring(0, v.length - 1);
        return;
    }
    if (t === "cpf") {
        i.setAttribute("maxlength", "14");
        if (v.length === 3 || v.length === 7) i.value += ".";
        if (v.length === 11) i.value += "-";
    }
    if (t === "cep") {
        i.setAttribute("maxlength", "9");
        if (v.length === 5) i.value += "-";
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

<?php
include_once("templates/footer.php");
?>
