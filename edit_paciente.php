<?php
include_once("check_logado.php");
include_once("templates/header.php");
include_once("models/message.php");

include_once("models/seguradora.php");
include_once("dao/seguradoraDao.php");

include_once("models/estipulante.php");
include_once("dao/estipulanteDao.php");

include_once("models/paciente.php");
include_once("dao/pacienteDao.php");

$seguradoraDao = new seguradoraDAO($conn, $BASE_URL);
$seguradoras = $seguradoraDao->findAll();
// Evita nomes duplicados no select (mantem o registro mais recente: ORDER BY id DESC)
$seguradorasSelect = [];
$seguradorasSeen = [];
foreach ($seguradoras as $seguradoraItem) {
    $nomeKey = strtolower(trim((string) ($seguradoraItem['seguradora_seg'] ?? '')));
    if ($nomeKey === '' || isset($seguradorasSeen[$nomeKey])) {
        continue;
    }
    $seguradorasSeen[$nomeKey] = true;
    $seguradorasSelect[] = $seguradoraItem;
}

$estipulanteDao = new estipulanteDAO($conn, $BASE_URL);
$estipulantes = $estipulanteDao->findAll();
// Evita nomes duplicados no select (mantem o registro mais recente: ORDER BY id DESC)
$estipulantesSelect = [];
$estipulantesSeen = [];
foreach ($estipulantes as $estipulanteItem) {
    $nomeKey = strtolower(trim((string) ($estipulanteItem['nome_est'] ?? '')));
    if ($nomeKey === '' || isset($estipulantesSeen[$nomeKey])) {
        continue;
    }
    $estipulantesSeen[$nomeKey] = true;
    $estipulantesSelect[] = $estipulanteItem;
}

$user = new Paciente();
$pacienteDao = new pacienteDAO($conn, $BASE_URL);

// Receber id do usuário
$id_paciente = filter_input(INPUT_GET, "id_paciente");
$paciente = $pacienteDao->findById($id_paciente);
extract($paciente);

// Função para formatar CPF
function formatCpf($cpf)
{
    if (!empty($cpf)) {
        $cpf = preg_replace("/\D/", '', $cpf); // Remove caracteres não numéricos
        if (strlen($cpf) == 11) {
            return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
        }
    }
    return $cpf;
}

// Função para formatar CEP
function formatCep($cep)
{
    if (!empty($cep)) {
        $cep = preg_replace("/\D/", '', $cep); // Remove caracteres não numéricos
        if (strlen($cep) == 8) {
            return substr($cep, 0, 5) . '-' . substr($cep, 5, 3);
        }
    }
    return $cep;
}

// Função para formatar telefone
function formatPhone($phone)
{
    if (!empty($phone)) {
        $phone = preg_replace("/\D/", '', $phone); // Remove caracteres não numéricos
        if (strlen($phone) == 11) {
            // Formato para celular (11 dígitos)
            return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 5) . '-' . substr($phone, 7, 4);
        } elseif (strlen($phone) == 10) {
            // Formato para telefone fixo (10 dígitos)
            return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 4) . '-' . substr($phone, 6, 4);
        }
    }
    return $phone;
}


// Recebendo e formatando as variáveis
$cep_pac = !empty($paciente['0']['cep_pac']) ? formatCep($paciente['0']['cep_pac']) : '';
$cpf_pac = !empty($paciente['0']['cpf_pac']) ? formatCpf($paciente['0']['cpf_pac']) : '';
$telefone01_pac = !empty($paciente['0']['telefone01_pac']) ? formatPhone($paciente['0']['telefone01_pac']) : '';
$telefone02_pac = !empty($paciente['0']['telefone02_pac']) ? formatPhone($paciente['0']['telefone02_pac']) : '';

?>

<!-- Ícones via pacote local Font Awesome Free -->
<link rel="stylesheet" href="diversos/CoolAdmin-master/vendor/font-awesome-5/css/fontawesome-all.min.css">
<script src="css/ocultar.css"></script>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/form_cad_internacao.css">
<style>
    #main-container.internacao-page {
        margin: 2px 0 0 !important;
        padding-inline: 5px !important;
        padding-top: 0 !important;
        width: auto !important;
        max-width: 100% !important;
        overflow-x: hidden;
    }

    #main-container.internacao-page .internacao-page__hero {
        margin: 0 0 6px !important;
    }

    #main-container.internacao-page .hero-actions {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    #main-container.internacao-page .hero-back-btn {
        border-radius: 999px;
        border: 1px solid #d9c3f4;
        color: #5e2363;
        padding: 7px 14px;
        text-decoration: none;
        font-weight: 600;
        font-size: .85rem;
        background: #f4ecfb;
    }

    #main-container.internacao-page .hero-back-btn:hover {
        color: #4a1b4e;
        background: #eadcf8;
    }

    #main-container.internacao-page .internacao-card__eyebrow {
        font-weight: 700 !important;
    }

    #multi-step-form .form-control {
        min-height: 42px;
        border-radius: 8px;
    }

    #multi-step-form select.form-control {
        height: 42px;
    }

    .confirm-delete-modal .modal-content {
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #cfd4dc;
    }

    .confirm-delete-modal .modal-header {
        background: #8b95a5;
        color: #fff !important;
        border-bottom: 0;
        padding: 10px 14px;
    }

    .confirm-delete-modal .modal-title {
        font-size: 1rem;
        font-weight: 700;
        margin: 0;
        color: #ffffff !important;
    }

    .confirm-delete-modal .close {
        color: #fff;
        opacity: .9;
        text-shadow: none;
    }

    .confirm-delete-modal .close:hover {
        color: #fff;
        opacity: 1;
    }

    .confirm-delete-modal .modal-body {
        padding: 14px;
    }

    .confirm-delete-modal .modal-footer {
        border-top: 1px solid #e8ebf0;
    }
</style>

<div class="internacao-page" id="main-container">
    <div class="internacao-page__hero">
        <div><h1>Editar paciente</h1></div>
        <div class="hero-actions">
            <a class="hero-back-btn" href="<?= htmlspecialchars($BASE_URL . 'pacientes', ENT_QUOTES, 'UTF-8') ?>">Voltar para lista</a>
            <span class="internacao-page__tag">Campos obrigatórios em destaque</span>
        </div>
    </div>
    <div class="internacao-page__content">

    <form action="<?= $BASE_URL ?>process_paciente.php" id="multi-step-form" method="POST" enctype="multipart/form-data"
        class="needs-validation visible entity-form">
        <div class="internacao-card internacao-card--general">
            <div class="internacao-card__header">
                <div>
                    <p class="internacao-card__eyebrow">Etapa 1</p>
                    <h2 class="internacao-card__title">Dados do paciente</h2>
                </div>
                <span class="internacao-card__tag internacao-card__tag--critical">Edição cadastral</span>
            </div>
            <div class="internacao-card__body">

        <input type="hidden" name="type" value="update">
        <input type="hidden" name="id_paciente" value="<?= $paciente['0']['id_paciente'] ?>">

        <!-- Step 1: Informações Pessoais -->
        <div id="step-1" class="step entity-step-card">
            <div class="entity-step-header">
                <div class="entity-step-copy">
                    <div class="entity-step-kicker">Passo 1</div>
                    <h3 class="entity-step-title">Identificação</h3>
                    <p class="entity-step-desc">Atualize os dados principais do paciente, incluindo vínculo com seguradora e estipulante.</p>
                </div>
                <span class="entity-step-badge">Dados essenciais</span>
            </div>
            <div class="row">
                <div class="form-group col-md-4 mb-3">
                    <label for="cpf_pac">CPF</label>
                    <input class="form-control" type="text" oninput="mascara(this, 'cpf')" value="<?= $cpf_pac ?>"
                        id="cpf_pac" name="cpf_pac" placeholder="000.000.000-00">
                    <div class="invalid-feedback">Por favor, insira um CPF válido.</div>
                </div>
                <div class="form-group col-md-8 mb-3">
                    <label for="nome_pac">Nome</label>
                    <input type="text" class="form-control" id="nome_pac" name="nome_pac"
                        value="<?= $paciente['0']['nome_pac'] ?>">
                    <div class="invalid-feedback">Por favor, insira o nome.</div>
                </div>
            </div>
            <div class="row">
                <div class="form-group col-md-4 mb-3">
                    <label for="recem_nascido_pac">Recém-nascido?</label>
                    <select class="form-control" id="recem_nascido_pac" name="recem_nascido_pac"
                        onchange="handleRecemNascidoChange()">
                        <option value="">Selecione</option>
                        <option value="s" <?= ($paciente['0']['recem_nascido_pac'] ?? '') === 's' ? 'selected' : '' ?>>Sim
                        </option>
                        <option value="n" <?= ($paciente['0']['recem_nascido_pac'] ?? '') === 'n' ? 'selected' : '' ?>>Não
                        </option>
                    </select>
                </div>


                <?php
                $isRN = ($paciente['0']['recem_nascido_pac'] ?? '') === 's';
                $maeTit = $paciente['0']['mae_titular_pac'] ?? '';
                // valor do número RN vindo do BD (tabela: numero_rn_pac)
                $numeroRN = isset($paciente['0']['numero_rn_pac']) ? (string) $paciente['0']['numero_rn_pac'] : '';
                // mostrar o campo somente quando for RN
                $showNumeroRN = $isRN;
                ?>
                <div class="form-group col-md-2 mb-3" id="numero_recem_nascido_group"
                    style="display: <?= $showNumeroRN ? 'block' : 'none' ?>;">
                    <label for="numero_recem_nascido_pac">Número RN</label>
                    <input type="number" class="form-control" id="numero_recem_nascido_pac"
                        onkeyup="validarMatriculaExistente()" name="numero_recem_nascido_pac"
                        value="<?= htmlspecialchars($numeroRN) ?>" <?= $showNumeroRN ? '' : 'disabled' ?>
                        <?= $showNumeroRN ? 'required' : '' ?> min="0" step="1" placeholder="Ex: 1, 2...">
                </div>
                <div class="form-group col-md-4 mb-3" id="mae_titular_group"
                    style="display: <?= $isRN ? 'block' : 'none' ?>;">
                    <label for="mae_titular_pac">Mãe Titular?</label>
                    <select class="form-control" id="mae_titular_pac" name="mae_titular_pac"
                        onchange="handleMaeTitularChange()">
                        <option value="">Selecione</option>
                        <option value="s" <?= $maeTit === 's' ? 'selected' : '' ?>>Sim</option>
                        <option value="n" <?= $maeTit === 'n' ? 'selected' : '' ?>>Não</option>
                    </select>
                </div>


                <?php
                $showMatTit = $isRN && ($maeTit === 'n');
                $matTitVal = $paciente['0']['matricula_titular_pac'] ?? '';
                ?>
                <div class="form-group col-md-2 mb-3" id="matricula_titular_group"
                    style="display: <?= $showMatTit ? 'block' : 'none' ?>;">
                    <label for="matricula_titular_pac">Matrícula da Titular</label>
                    <input type="text" class="form-control" id="matricula_titular_pac" name="matricula_titular_pac"
                        value="<?= htmlspecialchars($matTitVal) ?>" <?= $showMatTit ? '' : 'disabled' ?> <?= $showMatTit ? '' : '' ?>>
                </div>

            </div>
            <div class="row">
                <div class="form-group col-md-4 mb-3">
                    <label for="data_nasc_pac">Nascimento</label>
                    <input type="date" class="form-control" id="data_nasc_pac" name="data_nasc_pac"
                        value="<?= $paciente['0']['data_nasc_pac'] ?>">
                    <div class="invalid-feedback">Por favor, insira a data de nascimento.</div>
                </div>
                <div class="form-group col-md-4 mb-2">
                    <label for="matricula_pac">Matrícula</label>
                    <input type="text" class="form-control" id="matricula_pac" name="matricula_pac"
                        onkeyup="validarMatriculaExistente()" value="<?= $paciente['0']['matricula_pac'] ?>">
                    <div class="invalid-feedback">
                        Por favor, insira a matrícula.
                    </div>
                    <div class="invalid-feedback" id="validar_matricula" style="display: none;">
                        Matrícula já cadastrada.
                    </div>
                </div>
                <div class="form-group col-md-4 mb-2">
                    <label for="sexo_pac">Sexo</label>
                    <select class="form-control" name="sexo_pac" id="sexo_pac" required>
                        <option value="" disabled <?= empty($paciente['0']['sexo_pac']) ? 'selected' : '' ?>>Selecione...
                        </option>
                        <option value="m" <?= $paciente['0']['sexo_pac'] == 'm' ? 'selected' : '' ?>>Masculino</option>
                        <option value="f" <?= $paciente['0']['sexo_pac'] == 'f' ? 'selected' : '' ?>>Feminino</option>
                    </select>
                    <div class="invalid-feedback">Por favor, selecione o sexo.</div>
                </div>

                <!-- <div class="form-group col-md-4 mb-3">
                    <label for="num_atendimento_pac">Número Atendimento</label>
                    <input type="text" class="form-control" value="<?= $paciente['0']['num_atendimento_pac'] ?>"
                        id="num_atendimento_pac" name="num_atendimento_pac">
                </div> -->
                <!-- <div class="form-group col-md-8 mb-3">
                    <label for="nome_social_pac">Nome Social</label>
                    <input type="text" class="form-control" id="nome_social_pac" name="nome_social_pac"
                        value="<?= $paciente['0']['nome_social_pac'] ?>">
                </div> -->

            </div>



            <div class="row">
                <div class="form-group col-md-6 mb-3">
                    <label for="fk_seguradora_pac">Seguradora</label>
                    <select class="form-control" id="fk_seguradora_pac" name="fk_seguradora_pac">
                        <option value="<?= $paciente['0']['fk_seguradora_pac'] ?>" selected>
                            <?= $paciente['0']['seguradora_seg'] ?>
                        </option>
                        <?php foreach ($seguradorasSelect as $seguradora): ?>
                            <?php if ((string) $seguradora['id_seguradora'] === (string) $paciente['0']['fk_seguradora_pac']) continue; ?>
                            <option value="<?= $seguradora['id_seguradora'] ?>"><?= $seguradora['seguradora_seg'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-6 mb-3">
                    <label for="fk_seguradora_pac">Estipulante</label>
                    <select class="form-control" id="fk_estipulante_pac" name="fk_estipulante_pac">
                        <option value="<?= $paciente['0']['fk_estipulante_pac'] ?>" selected>
                            <?= $paciente['0']['nome_est'] ?>
                        </option>
                        <?php foreach ($estipulantesSelect as $estipulanteItem): ?>
                            <?php if ((string) $estipulanteItem['id_estipulante'] === (string) $paciente['0']['fk_estipulante_pac']) continue; ?>
                            <option value="<?= $estipulanteItem['id_estipulante'] ?>"><?= $estipulanteItem['nome_est'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>
            <div class="form-group mb-3">
                <label for="obs_pac">Observações</label>
                <textarea rows="5" class="form-control" id="obs_pac"
                    name="obs_pac"><?= $paciente['0']['obs_pac'] ?></textarea>
            </div>
            <hr>
        </div>

        <!-- Step 2: Informações de Endereço -->
        <div id="step-2" class="step entity-step-card">
            <div class="entity-step-header">
                <div class="entity-step-copy">
                    <div class="entity-step-kicker">Passo 2</div>
                    <h3 class="entity-step-title">Endereço</h3>
                    <p class="entity-step-desc">Revisite CEP, endereço e complemento mantendo o padrão da ficha cadastral principal.</p>
                </div>
                <span class="entity-step-badge">Localização</span>
            </div>
            <div class="row">
                <div class="form-group col-md-3 mb-3">
                    <label for="cep_pac">CEP</label>
                    <input type="text" oninput="mascara(this, 'cep')" onkeyup="consultarCEP(this, 'pac')"
                        value="<?= $cep_pac ?>" class="form-control" id="cep_pac" name="cep_pac"
                        placeholder="00000-000">
                    <div class="invalid-feedback">Por favor, insira o CEP.</div>
                </div>
                <div class="form-group col-md-9 mb-3">
                    <label for="endereco_pac">Endereço</label>
                    <input readonly type="text" class="form-control" value="<?= $paciente['0']['endereco_pac'] ?>"
                        id="endereco_pac" name="endereco_pac">
                </div>
            </div>

            <div class="row">
                <div class="form-group col-md-6 mb-3">
                    <label for="bairro_pac">Bairro</label>
                    <input readonly type="text" class="form-control" value="<?= $paciente['0']['bairro_pac'] ?>"
                        id="bairro_pac" name="bairro_pac">
                </div>
                <div class="form-group col-md-6 mb-3">
                    <label for="cidade_pac">Cidade</label>
                    <input readonly type="text" class="form-control" id="cidade_pac"
                        value="<?= $paciente['0']['cidade_pac'] ?>" name="cidade_pac">
                </div>
            </div>

            <div class="row">
                <div class="form-group col-md-6 mb-3">
                    <label for="estado_pac">Estado</label>
                    <select readonly class="form-control" id="estado_pac" name="estado_pac">
                        <option value="<?= $paciente['0']['estado_pac'] ?>"><?= $paciente['0']['estado_pac'] ?>
                        </option>
                        <?php foreach ($estado_sel as $estado): ?>
                            <option value="<?= $estado ?>"><?= $estado ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-6 mb-3">
                    <label for="numero_pac">Número</label>
                    <input type="text" class="form-control" id="numero_pac" value="<?= $paciente['0']['numero_pac'] ?>"
                        name="numero_pac">
                </div>
            </div>

            <div class="form-group mb-3">
                <label for="complemento_pac">Complemento</label>
                <input type="text" class="form-control" id="complemento_pac" name="complemento_pac"
                    value="<?= $paciente['0']['complemento_pac'] ?>">
            </div>
            <hr>
        </div>

        <!-- Step 3: Informações de Contato -->
        <div id="step-3" class="step entity-step-card">
            <div class="entity-step-header">
                <div class="entity-step-copy">
                    <div class="entity-step-kicker">Passo 3</div>
                    <h3 class="entity-step-title">Contato</h3>
                    <p class="entity-step-desc">Finalize com emails, telefones e ações de manutenção do registro.</p>
                </div>
                <span class="entity-step-badge">Fechamento</span>
            </div>
            <div class="row">
                <div class="form-group col-md-6 mb-3">
                    <label for="email01_pac">Email Principal</label>
                    <input type="email" class="form-control" id="email01_pac" name="email01_pac"
                        value="<?= $paciente['0']['email01_pac'] ?>" placeholder="exemplo@dominio.com">
                    <div class="invalid-feedback">Por favor, insira um email válido.</div>
                </div>
                <div class="form-group col-md-6 mb-3">
                    <label for="email02_pac">Email Alternativo</label>
                    <input type="email" class="form-control" id="email02_pac" name="email02_pac"
                        value="<?= $paciente['0']['email02_pac'] ?>" placeholder="exemplo@dominio.com">
                </div>
            </div>

            <div class="row">
                <div class="form-group col-md-6 mb-3">
                    <label for="telefone01_pac">Telefone</label>
                    <input type="text" onkeydown="return mascaraTelefone(event)" class="form-control"
                        id="telefone01_pac" value="<?= $telefone01_pac ?>" name="telefone01_pac"
                        placeholder="(00) 0000-0000">
                </div>
                <div class="form-group col-md-6 mb-3">
                    <label for="telefone02_pac">Celular</label>
                    <input type="text" onkeydown="return mascaraTelefone(event)" class="form-control"
                        id="telefone02_pac" value="<?= $telefone02_pac ?>" name="telefone02_pac"
                        placeholder="(00) 00000-0000">
                    <div class="invalid-feedback">Por favor, insira um número de celular válido.</div>
                </div>
            </div>




            <div class="entity-actions-bar">
                <div class="entity-actions-copy">Confirme os dados antes de atualizar. A exclusão continua disponível nesta mesma etapa.</div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-check"></i> Atualizar
                </button>
                <button type="button" class="btn btn-danger" onclick="showConfirmDelete()">
                    Deletar <i class="fas fa-times"></i>
                </button>
                </div>
            </div>
        </div>

        <div class="modal fade confirm-delete-modal" id="modalConfirmDelete" tabindex="-1" aria-hidden="true" style="display:none;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirmar exclusão</h5>
                        <button type="button" class="close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Fechar" onclick="hideConfirmDelete()">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        Este registro será deletado. Deseja continuar?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-dismiss="modal" onclick="hideConfirmDelete()">Não</button>
                        <button type="button" class="btn btn-danger" onclick="confirmAction()">Sim, deletar</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        function showConfirmDelete() {
            const modalEl = document.getElementById("modalConfirmDelete");
            if (!modalEl) return;
            modalEl.style.display = "block";
            modalEl.classList.add("show");
            modalEl.removeAttribute("aria-hidden");
            modalEl.setAttribute("aria-modal", "true");
            modalEl.setAttribute("role", "dialog");
            document.body.classList.add("modal-open");
            if (!document.getElementById("confirm-delete-backdrop")) {
                const backdrop = document.createElement("div");
                backdrop.id = "confirm-delete-backdrop";
                backdrop.className = "modal-backdrop fade show";
                backdrop.onclick = hideConfirmDelete;
                document.body.appendChild(backdrop);
            }
            }

            function hideConfirmDelete() {
                const modalEl = document.getElementById("modalConfirmDelete");
                if (!modalEl) return;
                modalEl.classList.remove("show");
                modalEl.style.display = "none";
                modalEl.setAttribute("aria-hidden", "true");
                modalEl.removeAttribute("aria-modal");
                document.body.classList.remove("modal-open");
                const backdrop = document.getElementById("confirm-delete-backdrop");
                if (backdrop) backdrop.remove();
            }

            // Função para confirmar a exclusão
            function confirmAction() {
                hideConfirmDelete();
                // Inicia o processo de exclusão
                const form = document.getElementById("multi-step-form");
                form.action = "<?= $BASE_URL ?>process_paciente.php";

                // Adiciona campos ocultos para o processo de deletar
                const inputType = document.createElement("input");
                inputType.type = "hidden";
                inputType.name = "type";
                inputType.value = "delUpdate";
                form.appendChild(inputType);

                const inputDeleted = document.createElement("input");
                inputDeleted.type = "hidden";
                inputDeleted.name = "deletado_pac";
                inputDeleted.value = "s";
                form.appendChild(inputDeleted);

                // Envia o formulário
                form.submit();
            }
        </script>

            </div>
        </div>
    </form>
    </div>
</div>

<script>
    function mascara(i, t) {
        var v = i.value;
        if (isNaN(v[v.length - 1])) {
            i.value = v.substring(0, v.length - 1);
            return;
        }
        if (t == "cpf") {
            i.setAttribute("maxlength", "14");
            if (v.length == 3 || v.length == 7) i.value += ".";
            if (v.length == 11) i.value += "-";
        }
        if (t == "cep") {
            i.setAttribute("maxlength", "9");
            if (v.length == 5) i.value += "-";
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

<?php include_once("templates/footer.php"); ?>
