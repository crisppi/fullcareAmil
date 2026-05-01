<?php
include_once("check_logado.php");

require_once("models/usuario.php");
require_once("models/estipulante.php");
require_once("dao/usuarioDao.php");
require_once("dao/estipulanteDao.php");
require_once("templates/header.php");

include_once("array_dados.php");

$user = new Estipulante();
$userDao = new UserDAO($conn, $BASE_URL);
$estipulanteDao = new estipulanteDAO($conn, $BASE_URL);

// Receber id do estipulante
$id_estipulante = filter_input(INPUT_GET, "id_estipulante");

$estipulante = $estipulanteDao->findById($id_estipulante);
$enderecosEstipulante = $estipulanteDao->findEnderecosByEstipulante((int) $id_estipulante);
$telefonesEstipulante = $estipulanteDao->findTelefonesByEstipulante((int) $id_estipulante);
$contatosEstipulante = $estipulanteDao->findContatosByEstipulante((int) $id_estipulante);
$estado_selecionado = $estipulante->estado_est;

$cnpj_est = $estipulante->cnpj_est;

// Formatação CNPJ
if (!empty($cnpj_est)) {
    $cnpj_est = preg_replace("/\D/", '', $cnpj_est);
    if (strlen($cnpj_est) === 14) {
        $bloco_1 = substr($cnpj_est, 0, 2);
        $bloco_2 = substr($cnpj_est, 2, 3);
        $bloco_3 = substr($cnpj_est, 5, 3);
        $bloco_4 = substr($cnpj_est, 8, 4);
        $dig_verificador = substr($cnpj_est, -2);
        $cnpj_formatado = $bloco_1 . "." . $bloco_2 . "." . $bloco_3 . "/" . $bloco_4 . "-" . $dig_verificador;
    } else {
        $cnpj_formatado = '';
    }
} else {
    $cnpj_formatado = '';
}

$telefone01_est = $estipulante->telefone01_est;
$telefone02_est = $estipulante->telefone02_est;

if (!empty($telefone01_est)) {
    $telefone01_est = preg_replace("/\D/", '', $telefone01_est);
    if (strlen($telefone01_est) === 10) {
        $bloco_1 = substr($telefone01_est, 0, 2);
        $bloco_2 = substr($telefone01_est, 2, 4);
        $bloco_3 = substr($telefone01_est, 6, 4);
        $telefone01_formatado = "($bloco_1) $bloco_2-$bloco_3";
    } elseif (strlen($telefone01_est) === 11) {
        $bloco_1 = substr($telefone01_est, 0, 2);
        $bloco_2 = substr($telefone01_est, 2, 5);
        $bloco_3 = substr($telefone01_est, 7, 4);
        $telefone01_formatado = "($bloco_1) $bloco_2-$bloco_3";
    } else {
        $telefone01_formatado = '';
    }
} else {
    $telefone01_formatado = '';
}

if (!empty($telefone02_est)) {
    $telefone02_est = preg_replace("/\D/", '', $telefone02_est);
    if (strlen($telefone02_est) === 10) {
        $bloco_1 = substr($telefone02_est, 0, 2);
        $bloco_2 = substr($telefone02_est, 2, 4);
        $bloco_3 = substr($telefone02_est, 6, 4);
        $telefone02_formatado = "($bloco_1) $bloco_2-$bloco_3";
    } elseif (strlen($telefone02_est) === 11) {
        $bloco_1 = substr($telefone02_est, 0, 2);
        $bloco_2 = substr($telefone02_est, 2, 5);
        $bloco_3 = substr($telefone02_est, 7, 4);
        $telefone02_formatado = "($bloco_1) $bloco_2-$bloco_3";
    } else {
        $telefone02_formatado = '';
    }
} else {
    $telefone02_formatado = '';
}

if (empty($enderecosEstipulante) && !empty($estipulante->endereco_est)) {
    $enderecosEstipulante[] = [
        'tipo_endereco' => 'Principal',
        'cep_endereco' => $estipulante->cep_est,
        'endereco_endereco' => $estipulante->endereco_est,
        'numero_endereco' => $estipulante->numero_est,
        'bairro_endereco' => $estipulante->bairro_est,
        'cidade_endereco' => $estipulante->cidade_est,
        'estado_endereco' => $estipulante->estado_est,
        'complemento_endereco' => '',
        'principal_endereco' => 1,
    ];
}

?>
<script src="css/ocultar.css"></script>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/form_cad_internacao.css">
<style>
    #main-container.internacao-page {
        margin: 2px 0 0 !important;
        padding-inline: 2px !important;
        padding-top: 0 !important;
        width: auto !important;
        max-width: 100% !important;
        overflow-x: hidden;
    }

    #main-container.internacao-page .internacao-page__hero {
        min-height: 58px !important;
        margin: 0 0 5px !important;
        padding: 14px 14px !important;
        border-radius: 18px !important;
    }

    #main-container.internacao-page .internacao-page__hero h1 {
        font-size: 1.2rem !important;
        line-height: 1.1 !important;
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
        padding: 6px 12px;
        text-decoration: none;
        font-weight: 600;
        font-size: .78rem;
        background: #f4ecfb;
    }

    #main-container.internacao-page .hero-back-btn:hover {
        color: #4a1b4e;
        background: #eadcf8;
    }

    #main-container.internacao-page .internacao-card__eyebrow {
        font-weight: 700 !important;
    }

    #main-container.internacao-page .internacao-page__content {
        display: block !important;
    }

    #main-container.internacao-page .internacao-page__tag,
    #main-container.internacao-page .internacao-card__tag,
    #main-container.internacao-page .entity-step-badge {
        padding: 4px 8px !important;
        font-size: .6rem !important;
    }

    #main-container.internacao-page .internacao-card {
        background: transparent !important;
        border: 0 !important;
        box-shadow: none !important;
        border-radius: 0 !important;
    }

    #main-container.internacao-page .internacao-card__header {
        padding: 8px 8px 2px !important;
        border-bottom: 0 !important;
    }

    #main-container.internacao-page .internacao-card__title {
        font-size: .9rem !important;
        line-height: 1.1 !important;
    }

    #main-container.internacao-page .internacao-card__body {
        padding: 4px 8px 10px !important;
        gap: 5px !important;
        background: transparent !important;
    }

    #main-container.internacao-page .entity-step-card {
        padding: 7px 8px 8px !important;
        border-radius: 0 !important;
        border: 0 !important;
        border-top: 1px solid rgba(94, 35, 99, 0.12) !important;
        background: transparent !important;
        box-shadow: none !important;
    }

    #main-container.internacao-page .entity-step-card::before {
        display: none !important;
    }

    #main-container.internacao-page .entity-step-card + .entity-step-card {
        margin-top: 3px !important;
    }

    #main-container.internacao-page .entity-step-header {
        align-items: center !important;
        margin-bottom: 7px !important;
    }

    #main-container.internacao-page .entity-step-kicker {
        margin-bottom: 1px !important;
        font-size: .52rem !important;
    }

    #main-container.internacao-page .entity-step-title {
        font-size: .92rem !important;
        line-height: 1.1 !important;
    }

    #main-container.internacao-page .entity-step-desc {
        display: none !important;
    }

    #main-container.internacao-page .entity-form .form-group,
    #main-container.internacao-page .entity-form [class*="col-md-"].form-group {
        margin-bottom: 8px !important;
    }

    #main-container.internacao-page .entity-form .form-group label {
        margin-bottom: 3px !important;
        font-size: .7rem !important;
        line-height: 1.1 !important;
    }

    #multi-step-form .form-control {
        min-height: 40px !important;
        height: 40px !important;
        border-radius: 9px;
        font-size: .78rem !important;
        padding-top: 5px !important;
        padding-bottom: 5px !important;
    }

    #multi-step-form select.form-control {
        height: 40px !important;
        min-height: 40px !important;
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

    .inline-manager-card {
        background: #f7f5fb;
        border: 1px solid #e8def1;
        border-radius: 10px;
        padding: 9px;
    }
</style>

<!-- Formulário de Edição -->
<div id="main-container" class="internacao-page cadastro-layout">
    <div class="internacao-page__hero">
        <div><h1>Editar estipulante</h1></div>
        <div class="hero-actions">
            <a class="hero-back-btn" href="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/estipulantes', ENT_QUOTES, 'UTF-8') ?>">Voltar para lista</a>
            <span class="internacao-page__tag">Campos obrigatórios em destaque</span>
        </div>
    </div>
    <div class="internacao-page__content">

    <form action="<?= $BASE_URL ?>process_estipulante.php" id="multi-step-form" method="POST"
        enctype="multipart/form-data" class="needs-validation visible entity-form" novalidate>
        <div class="internacao-card internacao-card--general">
            <div class="internacao-card__header">
                <div>
                    <p class="internacao-card__eyebrow">Etapa 1</p>
                    <h2 class="internacao-card__title">Dados do estipulante</h2>
                </div>
                <span class="internacao-card__tag internacao-card__tag--critical">Edição comercial</span>
            </div>
            <div class="internacao-card__body">

        <input type="hidden" name="type" value="update">
        <input type="hidden" name="id_estipulante" value="<?= $estipulante->id_estipulante ?>">

        <!-- Step 1: Informações Básicas -->
        <div id="step-1" class="step entity-step-card">
            <div class="entity-step-header">
                <div class="entity-step-copy">
                    <div class="entity-step-kicker">Passo 1</div>
                    <h3 class="entity-step-title">Identificação do estipulante</h3>
                    <p class="entity-step-desc">Atualize os dados principais do estipulante mantendo a leitura consistente com o cadastro novo.</p>
                </div>
                <span class="entity-step-badge">Dados base</span>
            </div>
            <div class="row">
                <div class="form-group col-md-6 mb-3">
                    <label for="cnpj_est">CNPJ</label>
                    <input type="text" oninput="mascara(this, 'cnpj')" class="form-control" id="cnpj_est"
                        name="cnpj_est" value="<?= $cnpj_formatado ?>" placeholder="00.000.000/0000-00">
                </div>
                <div class="form-group col-md-6 mb-3">
                    <label for="nome_est"><span style="color:red;">*</span> Estipulante</label>
                    <input type="text" class="form-control" id="nome_est" name="nome_est"
                        value="<?= $estipulante->nome_est ?>" placeholder="Nome do estipulante" required>
                </div>
            </div>
            <hr>
        </div>

        <!-- Step 2: Endereço -->
        <div id="step-2" class="step entity-step-card">
            <div class="entity-step-header">
                <div class="entity-step-copy">
                    <div class="entity-step-kicker">Passo 2</div>
                    <h3 class="entity-step-title">Endereço</h3>
                    <p class="entity-step-desc">Revise o endereço principal e os complementares no mesmo padrão visual das demais entidades.</p>
                </div>
                <span class="entity-step-badge">Localização</span>
            </div>
            <div class="row">
                <div class="form-group col-md-6 mb-3">
                    <label for="cep_est">CEP</label>
                    <input type="text" oninput="mascara(this, 'cep')" onkeyup="consultarCEP(this, 'est')"
                        class="form-control" id="cep_est" name="cep_est" value="<?= $estipulante->cep_est ?>"
                        placeholder="00000-000">
                </div>
                <div class="form-group col-md-6 mb-3">
                    <label for="endereco_est">Endereço</label>
                    <input type="text" class="form-control" id="endereco_est" name="endereco_est"
                        value="<?= $estipulante->endereco_est ?>" placeholder="Rua, avenida...">
                </div>
            </div>
            <div class="row">
                <div class="form-group col-md-6 mb-3">
                    <label for="bairro_est">Bairro</label>
                    <input readonly type="text" class="form-control" id="bairro_est" name="bairro_est"
                        value="<?= $estipulante->bairro_est ?>" placeholder="Bairro">
                </div>
                <div class="form-group col-md-6 mb-3">
                    <label for="cidade_est">Cidade</label>
                    <input readonly type="text" class="form-control" id="cidade_est" name="cidade_est"
                        value="<?= $estipulante->cidade_est ?>" placeholder="Cidade">
                </div>
            </div>
            <div class="row">
                <div class="form-group col-md-6 mb-3">
                    <label for="estado_est">Estado</label>
                    <input readonly value="<?= $estipulante->estado_est ?>" class="form-control" id="estado_est" name="estado_est">
                </div>
                <div class="form-group col-md-6 mb-3">
                    <label for="numero_est">Número</label>
                    <input type="text" class="form-control" id="numero_est" name="numero_est"
                        value="<?= $estipulante->numero_est ?>" placeholder="Número">
                </div>
            </div>
            <p class="internacao-card__eyebrow mb-3">Endereços adicionais</p>
            <div class="inline-manager-card mb-3">
                <div class="row">
                    <div class="form-group col-md-2 mb-2"><label for="end_tipo_inline">Tipo</label><input type="text" class="form-control" id="end_tipo_inline"></div>
                    <div class="form-group col-md-2 mb-2"><label for="end_cep_inline">CEP</label><input type="text" class="form-control" id="end_cep_inline"></div>
                    <div class="form-group col-md-6 mb-2"><label for="end_logradouro_inline">Endereço</label><input type="text" class="form-control" id="end_logradouro_inline"></div>
                    <div class="form-group col-md-1 mb-2"><label for="end_numero_inline">Nº</label><input type="text" class="form-control" id="end_numero_inline"></div>
                    <div class="form-group col-md-1 mb-2 d-flex align-items-end"><button type="button" id="btnAddEnderecoInline" class="btn btn-primary inline-add-btn" aria-label="Adicionar endereço">+</button></div>
                </div>
                <div class="row">
                    <div class="form-group col-md-3 mb-2"><label for="end_bairro_inline">Bairro</label><input type="text" class="form-control" id="end_bairro_inline"></div>
                    <div class="form-group col-md-3 mb-2"><label for="end_cidade_inline">Cidade</label><input type="text" class="form-control" id="end_cidade_inline"></div>
                    <div class="form-group col-md-2 mb-2"><label for="end_estado_inline">UF</label><input type="text" class="form-control" id="end_estado_inline"></div>
                    <div class="form-group col-md-2 mb-2"><label for="end_complemento_inline">Complemento</label><input type="text" class="form-control" id="end_complemento_inline"></div>
                    <div class="form-group col-md-2 mb-2"><label for="end_principal_inline">Principal</label><select class="form-control" id="end_principal_inline"><option value="n">Não</option><option value="s">Sim</option></select></div>
                </div>
                <div class="table-responsive mt-2"><table class="table table-sm table-striped mb-0"><thead><tr><th>Tipo</th><th>Endereço</th><th>Cidade/UF</th><th>P</th><th>Ação</th></tr></thead><tbody id="enderecosTableBody"><tr id="enderecosTableEmpty" style="display: <?= empty($enderecosEstipulante) ? '' : 'none' ?>;"><td colspan="5" class="text-muted text-center">Nenhum endereço adicional.</td></tr>
                    <?php foreach ($enderecosEstipulante as $end): ?>
                        <?php $p = ((int)($end['principal_endereco'] ?? 0) === 1) ? 's' : 'n'; ?>
                        <tr><td><?= htmlspecialchars((string)($end['tipo_endereco'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string)($end['endereco_endereco'] ?? '') . (!empty($end['numero_endereco']) ? ', ' . $end['numero_endereco'] : ''), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string)($end['cidade_endereco'] ?? '-') . (!empty($end['estado_endereco']) ? '/' . $end['estado_endereco'] : ''), ENT_QUOTES, 'UTF-8') ?></td><td><?= $p === 's' ? 'Sim' : 'Não' ?></td><td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-inline">Remover</button></td><td style="display:none;"><input type="hidden" name="end_tipo[]" value="<?= htmlspecialchars((string)($end['tipo_endereco'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="end_cep[]" value="<?= htmlspecialchars((string)($end['cep_endereco'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="end_logradouro[]" value="<?= htmlspecialchars((string)($end['endereco_endereco'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="end_numero[]" value="<?= htmlspecialchars((string)($end['numero_endereco'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="end_bairro[]" value="<?= htmlspecialchars((string)($end['bairro_endereco'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="end_cidade[]" value="<?= htmlspecialchars((string)($end['cidade_endereco'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="end_estado[]" value="<?= htmlspecialchars((string)($end['estado_endereco'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="end_complemento[]" value="<?= htmlspecialchars((string)($end['complemento_endereco'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="end_principal[]" value="<?= $p ?>"></td></tr>
                    <?php endforeach; ?>
                </tbody></table></div>
                <div id="enderecosHiddenContainer"></div>
            </div>
            <hr>
        </div>

        <!-- Step 3: Contato e Finalização -->
        <div id="step-3" class="step entity-step-card">
            <div class="entity-step-header">
                <div class="entity-step-copy">
                    <div class="entity-step-kicker">Passo 3</div>
                    <h3 class="entity-step-title">Contato e anexos</h3>
                    <p class="entity-step-desc">Consolide os contatos, responsáveis e arquivos antes de concluir a atualização.</p>
                </div>
                <span class="entity-step-badge">Fechamento</span>
            </div>
            <div class="row">
                <div class="form-group col-md-6 mb-3">
                    <label for="email01_est">Email Principal</label>
                    <input type="email" class="form-control" id="email01_est" name="email01_est"
                        value="<?= $estipulante->email01_est ?>" placeholder="exemplo@dominio.com">
                </div>
                <div class="form-group col-md-6 mb-3">
                    <label for="email02_est">Email Alternativo</label>
                    <input type="email" class="form-control" id="email02_est" name="email02_est"
                        value="<?= $estipulante->email02_est ?>" placeholder="exemplo@dominio.com">
                </div>
            </div>
            <div class="row">
                <div class="form-group col-md-6 mb-3">
                    <label for="telefone01_est">Telefone</label>
                    <input type="text" onkeydown="return mascaraTelefone(event)" class="form-control"
                        id="telefone01_est" name="telefone01_est" value="<?= $telefone01_formatado ?>"
                        placeholder="(00) 0000-0000">
                </div>
                <div class="form-group col-md-6 mb-3">
                    <label for="telefone02_est">Celular</label>
                    <input type="text" onkeydown="return mascaraTelefone(event)" class="form-control"
                        id="telefone02_est" name="telefone02_est" value="<?= $telefone02_formatado ?>"
                        placeholder="(00) 00000-0000">
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
                    <div class="form-group col-md-1 mb-2 d-flex align-items-end"><button type="button" id="btnAddTelefoneInline" class="btn btn-primary inline-add-btn" aria-label="Adicionar telefone">+</button></div>
                </div>
                <div class="table-responsive mt-2"><table class="table table-sm table-striped mb-0"><thead><tr><th>Tipo</th><th>Número</th><th>Ramal</th><th>Contato</th><th>P</th><th>Ação</th></tr></thead><tbody id="telefonesTableBody"><tr id="telefonesTableEmpty" style="display: <?= empty($telefonesEstipulante) ? '' : 'none' ?>;"><td colspan="6" class="text-muted text-center">Nenhum telefone adicional.</td></tr>
                    <?php foreach ($telefonesEstipulante as $tel): ?>
                        <?php $tp = ((int)($tel['principal_telefone'] ?? 0) === 1) ? 's' : 'n'; $nd = preg_replace('/\D+/', '', (string)($tel['numero_telefone'] ?? '')); $nf = $nd; if (strlen($nd)===11) { $nf = '(' . substr($nd,0,2) . ') ' . substr($nd,2,5) . '-' . substr($nd,7,4);} elseif (strlen($nd)===10) { $nf = '(' . substr($nd,0,2) . ') ' . substr($nd,2,4) . '-' . substr($nd,6,4);} ?>
                        <tr><td><?= htmlspecialchars((string)($tel['tipo_telefone'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($nf ?: '-', ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string)($tel['ramal_telefone'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string)($tel['contato_telefone'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td><td><?= $tp === 's' ? 'Sim' : 'Não' ?></td><td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-inline">Remover</button></td><td style="display:none;"><input type="hidden" name="tel_tipo[]" value="<?= htmlspecialchars((string)($tel['tipo_telefone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="tel_numero[]" value="<?= htmlspecialchars($nf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="tel_ramal[]" value="<?= htmlspecialchars((string)($tel['ramal_telefone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="tel_contato[]" value="<?= htmlspecialchars((string)($tel['contato_telefone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="tel_principal[]" value="<?= $tp ?>"></td></tr>
                    <?php endforeach; ?>
                </tbody></table></div>
                <div id="telefonesHiddenContainer"></div>
            </div>

            <div class="row">
                <div class="form-group col-md-6 mb-3">
                    <label for="nome_contato_est">Nome do Contato</label>
                    <input type="text" class="form-control" id="nome_contato_est" name="nome_contato_est"
                        value="<?= $estipulante->nome_contato_est ?>" placeholder="Nome do contato">
                </div>
                <div class="form-group col-md-6 mb-3">
                    <label for="nome_responsavel_est">Nome do Responsável</label>
                    <input type="text" class="form-control" id="nome_responsavel_est" name="nome_responsavel_est"
                        value="<?= $estipulante->nome_responsavel_est ?>" placeholder="Nome do responsável">
                </div>
            </div>
            <p class="internacao-card__eyebrow mb-3">Contatos adicionais</p>
            <div class="inline-manager-card mb-3">
                <div class="row">
                    <div class="form-group col-md-2 mb-2"><label for="cont_nome_inline">Nome</label><input type="text" class="form-control" id="cont_nome_inline"></div>
                    <div class="form-group col-md-2 mb-2"><label for="cont_cargo_inline">Cargo</label><input type="text" class="form-control" id="cont_cargo_inline"></div>
                    <div class="form-group col-md-2 mb-2"><label for="cont_setor_inline">Setor</label><input type="text" class="form-control" id="cont_setor_inline"></div>
                    <div class="form-group col-md-2 mb-2"><label for="cont_email_inline">Email</label><input type="email" class="form-control" id="cont_email_inline"></div>
                    <div class="form-group col-md-2 mb-2"><label for="cont_telefone_inline">Telefone</label><input type="text" class="form-control" id="cont_telefone_inline"></div>
                    <div class="form-group col-md-1 mb-2"><label for="cont_principal_inline">Principal</label><select class="form-control" id="cont_principal_inline"><option value="n">Não</option><option value="s">Sim</option></select></div>
                    <div class="form-group col-md-1 mb-2 d-flex align-items-end"><button type="button" id="btnAddContatoInline" class="btn btn-primary inline-add-btn" aria-label="Adicionar contato">+</button></div>
                </div>
                <div class="table-responsive mt-2"><table class="table table-sm table-striped mb-0"><thead><tr><th>Nome</th><th>Cargo/Setor</th><th>Email</th><th>Telefone</th><th>P</th><th>Ação</th></tr></thead><tbody id="contatosTableBody"><tr id="contatosTableEmpty" style="display: <?= empty($contatosEstipulante) ? '' : 'none' ?>;"><td colspan="6" class="text-muted text-center">Nenhum contato adicional.</td></tr>
                    <?php foreach ($contatosEstipulante as $ct): ?>
                        <?php $cp = ((int)($ct['principal_contato'] ?? 0) === 1) ? 's' : 'n'; $nd = preg_replace('/\D+/', '', (string)($ct['telefone_contato'] ?? '')); $nf = $nd; if (strlen($nd)===11) { $nf = '(' . substr($nd,0,2) . ') ' . substr($nd,2,5) . '-' . substr($nd,7,4);} elseif (strlen($nd)===10) { $nf = '(' . substr($nd,0,2) . ') ' . substr($nd,2,4) . '-' . substr($nd,6,4);} ?>
                        <tr><td><?= htmlspecialchars((string)($ct['nome_contato'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars(((string)($ct['cargo_contato'] ?? '-')) . (!empty($ct['setor_contato']) ? ' / ' . $ct['setor_contato'] : ''), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string)($ct['email_contato'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($nf ?: '-', ENT_QUOTES, 'UTF-8') ?></td><td><?= $cp === 's' ? 'Sim' : 'Não' ?></td><td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-inline">Remover</button></td><td style="display:none;"><input type="hidden" name="cont_nome[]" value="<?= htmlspecialchars((string)($ct['nome_contato'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="cont_cargo[]" value="<?= htmlspecialchars((string)($ct['cargo_contato'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="cont_setor[]" value="<?= htmlspecialchars((string)($ct['setor_contato'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="cont_email[]" value="<?= htmlspecialchars((string)($ct['email_contato'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="cont_telefone[]" value="<?= htmlspecialchars($nf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="cont_principal[]" value="<?= $cp ?>"></td></tr>
                    <?php endforeach; ?>
                </tbody></table></div>
                <div id="contatosHiddenContainer"></div>
            </div>
            <div class="row">
                <div class="form-group col-md-6">
                    <label for="logo_est">Logo</label>
                    <input type="file" class="form-control" name="logo_est" id="logo_est"
                        accept="image/png, image/jpeg">
                    <div class="notif-input oculto" id="notifImagem">Tamanho do arquivo inválido!</div>
                </div>
            </div>
            <div class="entity-actions-bar">
                <div class="entity-actions-copy">Revise a rede de contatos antes de salvar. A exclusão permanece disponível nesta etapa final.</div>
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
            form.action = "<?= $BASE_URL ?>process_estipulante.php";

            // Adiciona campos ocultos para o processo de deletar
            const inputType = document.createElement("input");
            inputType.type = "hidden";
            inputType.name = "type";
            inputType.value = "delUpdate";
            form.appendChild(inputType);

            const inputDeleted = document.createElement("input");
            inputDeleted.type = "hidden";
            inputDeleted.name = "deletado_est";
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

const imagem = document.querySelector("#logo_est");
if (imagem) {
    imagem.addEventListener("change", function(e) {
        if (!imagem.files || !imagem.files[0]) return;
        if (imagem.files[0].size > (1024 * 1024 * 2)) {
            var notifImagem = document.querySelector("#notifImagem");
            if (notifImagem) notifImagem.style.display = "block";
            imagem.value = '';
        }
    })
}

function novoArquivo() {
    var notifImagem = document.querySelector("#notifImagem");
    if (notifImagem) notifImagem.style.display = "none";
}

(function () {
    function onlyDigits(v) { return String(v || '').replace(/\D+/g, ''); }
    function formatPhone(v) {
        const d = onlyDigits(v);
        if (!d) return '';
        if (d.length > 10) return d.replace(/^(\d{2})(\d{5})(\d{0,4}).*$/, '($1) $2-$3').trim();
        return d.replace(/^(\d{2})(\d{4})(\d{0,4}).*$/, '($1) $2-$3').trim();
    }
    function h(name, value) { const i = document.createElement('input'); i.type = 'hidden'; i.name = name; i.value = value || ''; return i; }
    function bindExistingRemovers(bodyId, emptyId) {
        const body = document.getElementById(bodyId), empty = document.getElementById(emptyId);
        if (!body || !empty) return;
        body.querySelectorAll('.btn-remove-inline').forEach(btn => btn.addEventListener('click', function () {
            const row = btn.closest('tr'); if (!row) return; row.remove(); if (!body.querySelector('tr')) body.appendChild(empty);
        }));
    }
    function bind(cfg) {
        const add = document.getElementById(cfg.add), body = document.getElementById(cfg.body), empty = document.getElementById(cfg.empty), wrap = document.getElementById(cfg.wrap);
        if (!add || !body || !empty || !wrap) return;
        let idx = 0;
        add.addEventListener('click', function () {
            const item = cfg.read(); if (!item) return;
            if (empty.parentNode) empty.remove();
            const tr = document.createElement('tr'); tr.innerHTML = cfg.row(item);
            const holder = document.createElement('div');
            cfg.hidden(item).forEach(f => holder.appendChild(h(f.name, f.value)));
            wrap.appendChild(holder);
            tr.querySelector('.btn-remove-inline').addEventListener('click', function () { tr.remove(); holder.remove(); if (!body.querySelector('tr')) body.appendChild(empty); });
            body.appendChild(tr); cfg.clear(); idx++;
        });
    }
    bindExistingRemovers('enderecosTableBody', 'enderecosTableEmpty');
    bindExistingRemovers('telefonesTableBody', 'telefonesTableEmpty');
    bindExistingRemovers('contatosTableBody', 'contatosTableEmpty');
    bind({ add:'btnAddEnderecoInline', body:'enderecosTableBody', empty:'enderecosTableEmpty', wrap:'enderecosHiddenContainer',
        read:()=>{const it={tipo:(document.getElementById('end_tipo_inline').value||'').trim(),cep:(document.getElementById('end_cep_inline').value||'').trim(),logradouro:(document.getElementById('end_logradouro_inline').value||'').trim(),numero:(document.getElementById('end_numero_inline').value||'').trim(),bairro:(document.getElementById('end_bairro_inline').value||'').trim(),cidade:(document.getElementById('end_cidade_inline').value||'').trim(),estado:(document.getElementById('end_estado_inline').value||'').trim(),complemento:(document.getElementById('end_complemento_inline').value||'').trim(),principal:document.getElementById('end_principal_inline').value||'n'};return it.logradouro?it:null;},
        row:it=>`<td>${it.tipo||'-'}</td><td>${it.logradouro}${it.numero?', '+it.numero:''}</td><td>${it.cidade||'-'}${it.estado?'/'+it.estado:''}</td><td>${it.principal==='s'?'Sim':'Não'}</td><td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-inline">Remover</button></td>`,
        hidden:it=>[{name:'end_tipo[]',value:it.tipo},{name:'end_cep[]',value:it.cep},{name:'end_logradouro[]',value:it.logradouro},{name:'end_numero[]',value:it.numero},{name:'end_bairro[]',value:it.bairro},{name:'end_cidade[]',value:it.cidade},{name:'end_estado[]',value:it.estado},{name:'end_complemento[]',value:it.complemento},{name:'end_principal[]',value:it.principal}],
        clear:()=>{['end_tipo_inline','end_cep_inline','end_logradouro_inline','end_numero_inline','end_bairro_inline','end_cidade_inline','end_estado_inline','end_complemento_inline'].forEach(id=>document.getElementById(id).value=''); document.getElementById('end_principal_inline').value='n';}
    });
    bind({ add:'btnAddTelefoneInline', body:'telefonesTableBody', empty:'telefonesTableEmpty', wrap:'telefonesHiddenContainer',
        read:()=>{const it={tipo:(document.getElementById('tel_tipo_inline').value||'').trim(),numero:formatPhone(document.getElementById('tel_numero_inline').value||''),ramal:(document.getElementById('tel_ramal_inline').value||'').trim(),contato:(document.getElementById('tel_contato_inline').value||'').trim(),principal:document.getElementById('tel_principal_inline').value||'n'};return it.numero?it:null;},
        row:it=>`<td>${it.tipo||'-'}</td><td>${it.numero}</td><td>${it.ramal||'-'}</td><td>${it.contato||'-'}</td><td>${it.principal==='s'?'Sim':'Não'}</td><td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-inline">Remover</button></td>`,
        hidden:it=>[{name:'tel_tipo[]',value:it.tipo},{name:'tel_numero[]',value:it.numero},{name:'tel_ramal[]',value:it.ramal},{name:'tel_contato[]',value:it.contato},{name:'tel_principal[]',value:it.principal}],
        clear:()=>{['tel_tipo_inline','tel_numero_inline','tel_ramal_inline','tel_contato_inline'].forEach(id=>document.getElementById(id).value=''); document.getElementById('tel_principal_inline').value='n';}
    });
    bind({ add:'btnAddContatoInline', body:'contatosTableBody', empty:'contatosTableEmpty', wrap:'contatosHiddenContainer',
        read:()=>{const it={nome:(document.getElementById('cont_nome_inline').value||'').trim(),cargo:(document.getElementById('cont_cargo_inline').value||'').trim(),setor:(document.getElementById('cont_setor_inline').value||'').trim(),email:(document.getElementById('cont_email_inline').value||'').trim(),telefone:formatPhone(document.getElementById('cont_telefone_inline').value||''),principal:document.getElementById('cont_principal_inline').value||'n'};return it.nome?it:null;},
        row:it=>`<td>${it.nome}</td><td>${it.cargo||'-'}${it.setor?' / '+it.setor:''}</td><td>${it.email||'-'}</td><td>${it.telefone||'-'}</td><td>${it.principal==='s'?'Sim':'Não'}</td><td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-inline">Remover</button></td>`,
        hidden:it=>[{name:'cont_nome[]',value:it.nome},{name:'cont_cargo[]',value:it.cargo},{name:'cont_setor[]',value:it.setor},{name:'cont_email[]',value:it.email},{name:'cont_telefone[]',value:it.telefone},{name:'cont_principal[]',value:it.principal}],
        clear:()=>{['cont_nome_inline','cont_cargo_inline','cont_setor_inline','cont_email_inline','cont_telefone_inline'].forEach(id=>document.getElementById(id).value=''); document.getElementById('cont_principal_inline').value='n';}
    });
})();
</script>
<?php
include_once("templates/footer.php");
?>
