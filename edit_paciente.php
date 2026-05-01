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
$enderecosPaciente = $pacienteDao->findEnderecosByPaciente((int) $id_paciente);
$emailsPaciente = $pacienteDao->findEmailsByPaciente((int) $id_paciente);
$telefonesPaciente = $pacienteDao->findTelefonesByPaciente((int) $id_paciente);
$contatosPaciente = $pacienteDao->findContatosByPaciente((int) $id_paciente);
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

if (empty($enderecosPaciente) && !empty($paciente['0']['endereco_pac'])) {
    $enderecosPaciente[] = [
        'tipo_endereco' => 'Principal',
        'cep_endereco' => $paciente['0']['cep_pac'] ?? '',
        'endereco_endereco' => $paciente['0']['endereco_pac'] ?? '',
        'numero_endereco' => $paciente['0']['numero_pac'] ?? '',
        'bairro_endereco' => $paciente['0']['bairro_pac'] ?? '',
        'cidade_endereco' => $paciente['0']['cidade_pac'] ?? '',
        'estado_endereco' => $paciente['0']['estado_pac'] ?? '',
        'complemento_endereco' => $paciente['0']['complemento_pac'] ?? '',
        'principal_endereco' => 1,
    ];
}
if (empty($emailsPaciente)) {
    if (!empty($paciente['0']['email01_pac'])) {
        $emailsPaciente[] = ['tipo_email' => 'Principal', 'email_email' => $paciente['0']['email01_pac'], 'principal_email' => 1];
    }
    if (!empty($paciente['0']['email02_pac'])) {
        $emailsPaciente[] = ['tipo_email' => 'Alternativo', 'email_email' => $paciente['0']['email02_pac'], 'principal_email' => 0];
    }
}
if (empty($telefonesPaciente)) {
    if (!empty($paciente['0']['telefone01_pac'])) {
        $telefonesPaciente[] = ['tipo_telefone' => 'Principal', 'numero_telefone' => $paciente['0']['telefone01_pac'], 'ramal_telefone' => '', 'contato_telefone' => '', 'principal_telefone' => 1];
    }
    if (!empty($paciente['0']['telefone02_pac'])) {
        $telefonesPaciente[] = ['tipo_telefone' => 'Celular', 'numero_telefone' => $paciente['0']['telefone02_pac'], 'ramal_telefone' => '', 'contato_telefone' => '', 'principal_telefone' => 0];
    }
}

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

<div class="internacao-page cadastro-layout" id="main-container">
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
            <p class="internacao-card__eyebrow mb-3">Endereços adicionais</p>
            <div class="inline-manager-card mb-3">
                <div class="row">
                    <div class="form-group col-md-2 mb-2"><label for="end_tipo_inline">Tipo</label><input type="text" class="form-control" id="end_tipo_inline"></div>
                    <div class="form-group col-md-2 mb-2"><label for="end_cep_inline">CEP</label><input type="text" class="form-control" id="end_cep_inline"></div>
                    <div class="form-group col-md-5 mb-2"><label for="end_logradouro_inline">Endereço</label><input type="text" class="form-control" id="end_logradouro_inline"></div>
                    <div class="form-group col-md-1 mb-2"><label for="end_numero_inline">Nº</label><input type="text" class="form-control" id="end_numero_inline"></div>
                    <div class="form-group col-md-1 mb-2"><label for="end_principal_inline">Principal</label><select class="form-control" id="end_principal_inline"><option value="n">Não</option><option value="s">Sim</option></select></div>
                    <div class="form-group col-md-1 mb-2 d-flex align-items-end"><button type="button" id="btnAddEnderecoInline" class="btn btn-primary w-100">+</button></div>
                </div>
                <div class="row">
                    <div class="form-group col-md-3 mb-2"><label for="end_bairro_inline">Bairro</label><input type="text" class="form-control" id="end_bairro_inline"></div>
                    <div class="form-group col-md-3 mb-2"><label for="end_cidade_inline">Cidade</label><input type="text" class="form-control" id="end_cidade_inline"></div>
                    <div class="form-group col-md-2 mb-2"><label for="end_estado_inline">UF</label><input type="text" class="form-control" id="end_estado_inline"></div>
                    <div class="form-group col-md-4 mb-2"><label for="end_complemento_inline">Complemento</label><input type="text" class="form-control" id="end_complemento_inline"></div>
                </div>
                <div class="table-responsive mt-2"><table class="table table-sm table-striped mb-0"><thead><tr><th>Tipo</th><th>Endereço</th><th>Cidade/UF</th><th>P</th><th>Ação</th></tr></thead><tbody id="enderecosTableBody"><tr id="enderecosTableEmpty" style="display: <?= empty($enderecosPaciente) ? '' : 'none' ?>;"><td colspan="5" class="text-muted text-center">Nenhum endereço adicional.</td></tr>
                    <?php foreach ($enderecosPaciente as $end): ?>
                        <?php $ep = ((int)($end['principal_endereco'] ?? 0) === 1) ? 's' : 'n'; ?>
                        <tr><td><?= htmlspecialchars((string)($end['tipo_endereco'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string)($end['endereco_endereco'] ?? ''), ENT_QUOTES, 'UTF-8') ?><?= !empty($end['numero_endereco']) ? ', ' . htmlspecialchars((string)$end['numero_endereco'], ENT_QUOTES, 'UTF-8') : '' ?></td><td><?= htmlspecialchars((string)($end['cidade_endereco'] ?? '-'), ENT_QUOTES, 'UTF-8') ?><?= !empty($end['estado_endereco']) ? '/' . htmlspecialchars((string)$end['estado_endereco'], ENT_QUOTES, 'UTF-8') : '' ?></td><td><?= $ep === 's' ? 'Sim' : 'Não' ?></td><td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-inline">Remover</button></td><td style="display:none;"><input type="hidden" name="end_tipo[]" value="<?= htmlspecialchars((string)($end['tipo_endereco'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="end_cep[]" value="<?= htmlspecialchars((string)($end['cep_endereco'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="end_logradouro[]" value="<?= htmlspecialchars((string)($end['endereco_endereco'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="end_numero[]" value="<?= htmlspecialchars((string)($end['numero_endereco'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="end_bairro[]" value="<?= htmlspecialchars((string)($end['bairro_endereco'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="end_cidade[]" value="<?= htmlspecialchars((string)($end['cidade_endereco'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="end_estado[]" value="<?= htmlspecialchars((string)($end['estado_endereco'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="end_complemento[]" value="<?= htmlspecialchars((string)($end['complemento_endereco'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="end_principal[]" value="<?= $ep ?>"></td></tr>
                    <?php endforeach; ?>
                </tbody></table></div>
                <div id="enderecosHiddenContainer"></div>
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
            <p class="internacao-card__eyebrow mb-3">Emails adicionais</p>
            <div class="inline-manager-card mb-3">
                <div class="row">
                    <div class="form-group col-md-3 mb-2"><label for="email_tipo_inline">Tipo</label><input type="text" class="form-control" id="email_tipo_inline"></div>
                    <div class="form-group col-md-6 mb-2"><label for="email_email_inline">Email</label><input type="email" class="form-control" id="email_email_inline"></div>
                    <div class="form-group col-md-2 mb-2"><label for="email_principal_inline">Principal</label><select class="form-control" id="email_principal_inline"><option value="n">Não</option><option value="s">Sim</option></select></div>
                    <div class="form-group col-md-1 mb-2 d-flex align-items-end"><button type="button" id="btnAddEmailInline" class="btn btn-primary w-100">+</button></div>
                </div>
                <div class="table-responsive mt-2"><table class="table table-sm table-striped mb-0"><thead><tr><th>Tipo</th><th>Email</th><th>P</th><th>Ação</th></tr></thead><tbody id="emailsTableBody"><tr id="emailsTableEmpty" style="display: <?= empty($emailsPaciente) ? '' : 'none' ?>;"><td colspan="4" class="text-muted text-center">Nenhum email adicional.</td></tr>
                    <?php foreach ($emailsPaciente as $emailItem): ?>
                        <?php $emP = ((int)($emailItem['principal_email'] ?? 0) === 1) ? 's' : 'n'; ?>
                        <tr><td><?= htmlspecialchars((string)($emailItem['tipo_email'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string)($emailItem['email_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td><td><?= $emP === 's' ? 'Sim' : 'Não' ?></td><td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-inline">Remover</button></td><td style="display:none;"><input type="hidden" name="email_tipo[]" value="<?= htmlspecialchars((string)($emailItem['tipo_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="email_email[]" value="<?= htmlspecialchars((string)($emailItem['email_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="email_principal[]" value="<?= $emP ?>"></td></tr>
                    <?php endforeach; ?>
                </tbody></table></div>
                <div id="emailsHiddenContainer"></div>
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
            <p class="internacao-card__eyebrow mb-3">Telefones adicionais</p>
            <div class="inline-manager-card mb-3">
                <div class="row">
                    <div class="form-group col-md-2 mb-2"><label for="tel_tipo_inline">Tipo</label><input type="text" class="form-control" id="tel_tipo_inline"></div>
                    <div class="form-group col-md-3 mb-2"><label for="tel_numero_inline">Telefone</label><input type="text" class="form-control" id="tel_numero_inline"></div>
                    <div class="form-group col-md-2 mb-2"><label for="tel_ramal_inline">Ramal</label><input type="text" class="form-control" id="tel_ramal_inline"></div>
                    <div class="form-group col-md-3 mb-2"><label for="tel_contato_inline">Contato</label><input type="text" class="form-control" id="tel_contato_inline"></div>
                    <div class="form-group col-md-1 mb-2"><label for="tel_principal_inline">Principal</label><select class="form-control" id="tel_principal_inline"><option value="n">Não</option><option value="s">Sim</option></select></div>
                    <div class="form-group col-md-1 mb-2 d-flex align-items-end"><button type="button" id="btnAddTelefoneInline" class="btn btn-primary w-100">+</button></div>
                </div>
                <div class="table-responsive mt-2"><table class="table table-sm table-striped mb-0"><thead><tr><th>Tipo</th><th>Número</th><th>Ramal</th><th>Contato</th><th>P</th><th>Ação</th></tr></thead><tbody id="telefonesTableBody"><tr id="telefonesTableEmpty" style="display: <?= empty($telefonesPaciente) ? '' : 'none' ?>;"><td colspan="6" class="text-muted text-center">Nenhum telefone adicional.</td></tr>
                    <?php foreach ($telefonesPaciente as $tel): ?>
                        <?php $tp = ((int)($tel['principal_telefone'] ?? 0) === 1) ? 's' : 'n'; $tf = formatPhone((string)($tel['numero_telefone'] ?? '')); ?>
                        <tr><td><?= htmlspecialchars((string)($tel['tipo_telefone'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($tf ?: '-', ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string)($tel['ramal_telefone'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string)($tel['contato_telefone'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td><td><?= $tp === 's' ? 'Sim' : 'Não' ?></td><td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-inline">Remover</button></td><td style="display:none;"><input type="hidden" name="tel_tipo[]" value="<?= htmlspecialchars((string)($tel['tipo_telefone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="tel_numero[]" value="<?= htmlspecialchars($tf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="tel_ramal[]" value="<?= htmlspecialchars((string)($tel['ramal_telefone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="tel_contato[]" value="<?= htmlspecialchars((string)($tel['contato_telefone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="tel_principal[]" value="<?= $tp ?>"></td></tr>
                    <?php endforeach; ?>
                </tbody></table></div>
                <div id="telefonesHiddenContainer"></div>
            </div>
            <p class="internacao-card__eyebrow mb-3">Contatos adicionais</p>
            <div class="inline-manager-card mb-3">
                <div class="row">
                    <div class="form-group col-md-2 mb-2"><label for="cont_nome_inline">Nome</label><input type="text" class="form-control" id="cont_nome_inline"></div>
                    <div class="form-group col-md-2 mb-2"><label for="cont_parentesco_inline">Parentesco</label><input type="text" class="form-control" id="cont_parentesco_inline"></div>
                    <div class="form-group col-md-2 mb-2"><label for="cont_email_inline">Email</label><input type="email" class="form-control" id="cont_email_inline"></div>
                    <div class="form-group col-md-2 mb-2"><label for="cont_telefone_inline">Telefone</label><input type="text" class="form-control" id="cont_telefone_inline"></div>
                    <div class="form-group col-md-2 mb-2"><label for="cont_observacao_inline">Observação</label><input type="text" class="form-control" id="cont_observacao_inline"></div>
                    <div class="form-group col-md-1 mb-2"><label for="cont_principal_inline">Principal</label><select class="form-control" id="cont_principal_inline"><option value="n">Não</option><option value="s">Sim</option></select></div>
                    <div class="form-group col-md-1 mb-2 d-flex align-items-end"><button type="button" id="btnAddContatoInline" class="btn btn-primary w-100">+</button></div>
                </div>
                <div class="table-responsive mt-2"><table class="table table-sm table-striped mb-0"><thead><tr><th>Nome</th><th>Parentesco</th><th>Email</th><th>Telefone</th><th>P</th><th>Ação</th></tr></thead><tbody id="contatosTableBody"><tr id="contatosTableEmpty" style="display: <?= empty($contatosPaciente) ? '' : 'none' ?>;"><td colspan="6" class="text-muted text-center">Nenhum contato adicional.</td></tr>
                    <?php foreach ($contatosPaciente as $ct): ?>
                        <?php $cp = ((int)($ct['principal_contato'] ?? 0) === 1) ? 's' : 'n'; $ctTel = formatPhone((string)($ct['telefone_contato'] ?? '')); ?>
                        <tr><td><?= htmlspecialchars((string)($ct['nome_contato'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string)($ct['parentesco_contato'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string)($ct['email_contato'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($ctTel ?: '-', ENT_QUOTES, 'UTF-8') ?></td><td><?= $cp === 's' ? 'Sim' : 'Não' ?></td><td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-inline">Remover</button></td><td style="display:none;"><input type="hidden" name="cont_nome[]" value="<?= htmlspecialchars((string)($ct['nome_contato'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="cont_parentesco[]" value="<?= htmlspecialchars((string)($ct['parentesco_contato'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="cont_email[]" value="<?= htmlspecialchars((string)($ct['email_contato'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="cont_telefone[]" value="<?= htmlspecialchars($ctTel, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="cont_observacao[]" value="<?= htmlspecialchars((string)($ct['observacao_contato'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="cont_principal[]" value="<?= $cp ?>"></td></tr>
                    <?php endforeach; ?>
                </tbody></table></div>
                <div id="contatosHiddenContainer"></div>
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

    (function () {
        function onlyDigits(v) { return String(v || '').replace(/\D+/g, ''); }
        function formatPhone(v) {
            const d = onlyDigits(v);
            if (!d) return '';
            if (d.length > 10) return d.replace(/^(\d{2})(\d{5})(\d{0,4}).*$/, '($1) $2-$3').trim();
            return d.replace(/^(\d{2})(\d{4})(\d{0,4}).*$/, '($1) $2-$3').trim();
        }
        function h(name, value) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value || '';
            return input;
        }
        function updateEmpty(bodyId, emptyId) {
            const body = document.getElementById(bodyId);
            const empty = document.getElementById(emptyId);
            if (!body || !empty) return;
            const rows = Array.from(body.querySelectorAll('tr')).filter(row => row.id !== emptyId);
            empty.style.display = rows.length ? 'none' : '';
        }
        function bindExistingRemovers(bodyId, emptyId) {
            const body = document.getElementById(bodyId);
            if (!body) return;
            body.querySelectorAll('.btn-remove-inline').forEach(btn => {
                btn.addEventListener('click', function () {
                    const tr = btn.closest('tr');
                    if (tr) tr.remove();
                    updateEmpty(bodyId, emptyId);
                });
            });
            updateEmpty(bodyId, emptyId);
        }
        function bindInline(cfg) {
            const add = document.getElementById(cfg.add);
            const body = document.getElementById(cfg.body);
            const empty = document.getElementById(cfg.empty);
            const wrap = document.getElementById(cfg.wrap);
            if (!add || !body || !empty || !wrap) return;
            add.addEventListener('click', function () {
                const item = cfg.read();
                if (!item) return;
                empty.style.display = 'none';
                const tr = document.createElement('tr');
                tr.innerHTML = cfg.row(item);
                const holder = document.createElement('div');
                cfg.hidden(item).forEach(field => holder.appendChild(h(field.name, field.value)));
                wrap.appendChild(holder);
                tr.querySelector('.btn-remove-inline').addEventListener('click', function () {
                    tr.remove();
                    holder.remove();
                    updateEmpty(cfg.body, cfg.empty);
                });
                body.appendChild(tr);
                cfg.clear();
                updateEmpty(cfg.body, cfg.empty);
            });
        }
        bindExistingRemovers('enderecosTableBody', 'enderecosTableEmpty');
        bindExistingRemovers('emailsTableBody', 'emailsTableEmpty');
        bindExistingRemovers('telefonesTableBody', 'telefonesTableEmpty');
        bindExistingRemovers('contatosTableBody', 'contatosTableEmpty');
        bindInline({
            add: 'btnAddEnderecoInline', body: 'enderecosTableBody', empty: 'enderecosTableEmpty', wrap: 'enderecosHiddenContainer',
            read: () => {
                const item = {
                    tipo: (document.getElementById('end_tipo_inline').value || '').trim(),
                    cep: (document.getElementById('end_cep_inline').value || '').trim(),
                    logradouro: (document.getElementById('end_logradouro_inline').value || '').trim(),
                    numero: (document.getElementById('end_numero_inline').value || '').trim(),
                    bairro: (document.getElementById('end_bairro_inline').value || '').trim(),
                    cidade: (document.getElementById('end_cidade_inline').value || '').trim(),
                    estado: (document.getElementById('end_estado_inline').value || '').trim(),
                    complemento: (document.getElementById('end_complemento_inline').value || '').trim(),
                    principal: document.getElementById('end_principal_inline').value || 'n'
                };
                return item.logradouro ? item : null;
            },
            row: item => `<td>${item.tipo || '-'}</td><td>${item.logradouro}${item.numero ? ', ' + item.numero : ''}</td><td>${item.cidade || '-'}${item.estado ? '/' + item.estado : ''}</td><td>${item.principal === 's' ? 'Sim' : 'Não'}</td><td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-inline">Remover</button></td>`,
            hidden: item => [{name:'end_tipo[]',value:item.tipo},{name:'end_cep[]',value:item.cep},{name:'end_logradouro[]',value:item.logradouro},{name:'end_numero[]',value:item.numero},{name:'end_bairro[]',value:item.bairro},{name:'end_cidade[]',value:item.cidade},{name:'end_estado[]',value:item.estado},{name:'end_complemento[]',value:item.complemento},{name:'end_principal[]',value:item.principal}],
            clear: () => { ['end_tipo_inline','end_cep_inline','end_logradouro_inline','end_numero_inline','end_bairro_inline','end_cidade_inline','end_estado_inline','end_complemento_inline'].forEach(id => document.getElementById(id).value = ''); document.getElementById('end_principal_inline').value = 'n'; }
        });
        bindInline({
            add: 'btnAddEmailInline', body: 'emailsTableBody', empty: 'emailsTableEmpty', wrap: 'emailsHiddenContainer',
            read: () => {
                const item = {
                    tipo: (document.getElementById('email_tipo_inline').value || '').trim(),
                    email: (document.getElementById('email_email_inline').value || '').trim(),
                    principal: document.getElementById('email_principal_inline').value || 'n'
                };
                return item.email ? item : null;
            },
            row: item => `<td>${item.tipo || '-'}</td><td>${item.email}</td><td>${item.principal === 's' ? 'Sim' : 'Não'}</td><td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-inline">Remover</button></td>`,
            hidden: item => [{name:'email_tipo[]',value:item.tipo},{name:'email_email[]',value:item.email},{name:'email_principal[]',value:item.principal}],
            clear: () => { ['email_tipo_inline','email_email_inline'].forEach(id => document.getElementById(id).value = ''); document.getElementById('email_principal_inline').value = 'n'; }
        });
        bindInline({
            add: 'btnAddTelefoneInline', body: 'telefonesTableBody', empty: 'telefonesTableEmpty', wrap: 'telefonesHiddenContainer',
            read: () => {
                const item = {
                    tipo: (document.getElementById('tel_tipo_inline').value || '').trim(),
                    numero: formatPhone(document.getElementById('tel_numero_inline').value || ''),
                    ramal: (document.getElementById('tel_ramal_inline').value || '').trim(),
                    contato: (document.getElementById('tel_contato_inline').value || '').trim(),
                    principal: document.getElementById('tel_principal_inline').value || 'n'
                };
                return item.numero ? item : null;
            },
            row: item => `<td>${item.tipo || '-'}</td><td>${item.numero}</td><td>${item.ramal || '-'}</td><td>${item.contato || '-'}</td><td>${item.principal === 's' ? 'Sim' : 'Não'}</td><td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-inline">Remover</button></td>`,
            hidden: item => [{name:'tel_tipo[]',value:item.tipo},{name:'tel_numero[]',value:item.numero},{name:'tel_ramal[]',value:item.ramal},{name:'tel_contato[]',value:item.contato},{name:'tel_principal[]',value:item.principal}],
            clear: () => { ['tel_tipo_inline','tel_numero_inline','tel_ramal_inline','tel_contato_inline'].forEach(id => document.getElementById(id).value = ''); document.getElementById('tel_principal_inline').value = 'n'; }
        });
        bindInline({
            add: 'btnAddContatoInline', body: 'contatosTableBody', empty: 'contatosTableEmpty', wrap: 'contatosHiddenContainer',
            read: () => {
                const item = {
                    nome: (document.getElementById('cont_nome_inline').value || '').trim(),
                    parentesco: (document.getElementById('cont_parentesco_inline').value || '').trim(),
                    email: (document.getElementById('cont_email_inline').value || '').trim(),
                    telefone: formatPhone(document.getElementById('cont_telefone_inline').value || ''),
                    observacao: (document.getElementById('cont_observacao_inline').value || '').trim(),
                    principal: document.getElementById('cont_principal_inline').value || 'n'
                };
                return item.nome ? item : null;
            },
            row: item => `<td>${item.nome}</td><td>${item.parentesco || '-'}</td><td>${item.email || '-'}</td><td>${item.telefone || '-'}</td><td>${item.principal === 's' ? 'Sim' : 'Não'}</td><td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-inline">Remover</button></td>`,
            hidden: item => [{name:'cont_nome[]',value:item.nome},{name:'cont_parentesco[]',value:item.parentesco},{name:'cont_email[]',value:item.email},{name:'cont_telefone[]',value:item.telefone},{name:'cont_observacao[]',value:item.observacao},{name:'cont_principal[]',value:item.principal}],
            clear: () => { ['cont_nome_inline','cont_parentesco_inline','cont_email_inline','cont_telefone_inline','cont_observacao_inline'].forEach(id => document.getElementById(id).value = ''); document.getElementById('cont_principal_inline').value = 'n'; }
        });
    })();
</script>

<?php include_once("templates/footer.php"); ?>
