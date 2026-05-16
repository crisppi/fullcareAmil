<?php
include_once("check_logado.php");
require_once("templates/header.php");

require_once("dao/hospitalDao.php");
require_once("models/seguradora.php");
require_once("dao/seguradoraDao.php");
require_once("models/estipulante.php");
require_once("dao/estipulanteDao.php");
require_once("models/message.php");

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
$seguradoraDefaultId = 1;
foreach ($seguradorasSelect as $seguradoraItem) {
    $nomeSeguradora = mb_strtolower(trim((string)($seguradoraItem['seguradora_seg'] ?? '')), 'UTF-8');
    if ($nomeSeguradora === 'amil') {
        $seguradoraDefaultId = (int)($seguradoraItem['id_seguradora'] ?? 1);
        break;
    }
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
$estipulanteDefaultId = 1;
foreach ($estipulantesSelect as $estipulanteItem) {
    $nomeEstipulante = mb_strtolower(trim((string)($estipulanteItem['nome_est'] ?? '')), 'UTF-8');
    if ($nomeEstipulante === 'sem informações' || $nomeEstipulante === 'sem informacoes') {
        $estipulanteDefaultId = (int)($estipulanteItem['id_estipulante'] ?? 1);
        break;
    }
}

// Receber id do usuário
$id_hospital = filter_input(INPUT_GET, "id_hospital");
?>

<!-- Ícones via pacote local Font Awesome Free -->
<link rel="stylesheet" href="diversos/CoolAdmin-master/vendor/font-awesome-5/css/fontawesome-all.min.css">
<link rel="stylesheet" href="css/style.css?v=<?= @filemtime(__DIR__ . '/css/style.css') ?>">
<link rel="stylesheet" href="css/form_cad_internacao.css?v=<?= @filemtime(__DIR__ . '/css/form_cad_internacao.css') ?>">
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
        min-height: 50px !important;
        margin: 0 0 4px !important;
        padding: 10px 12px !important;
        border-radius: 16px !important;
    }

    #main-container.internacao-page .internacao-page__hero h1 {
        font-size: 1.02rem !important;
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
        padding: 5px 10px;
        text-decoration: none;
        font-weight: 600;
        font-size: .72rem;
        background: #f4ecfb;
    }

    #main-container.internacao-page .hero-back-btn:hover {
        color: #4a1b4e;
        background: #eadcf8;
    }

    #main-container.internacao-page .internacao-card__eyebrow {
        font-weight: 700 !important;
    }

    #main-container.internacao-page .internacao-page__tag,
    #main-container.internacao-page .internacao-card__tag,
    #main-container.internacao-page .entity-step-badge {
        padding: 4px 8px !important;
        font-size: .6rem !important;
    }

    #main-container.internacao-page .internacao-page__content {
        display: block !important;
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
        font-size: .82rem !important;
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
        font-size: .84rem !important;
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
        font-size: .64rem !important;
        line-height: 1.1 !important;
    }

    #multi-step-form .form-control {
        min-height: 36px !important;
        height: 36px !important;
        border-radius: 9px;
        font-size: .72rem !important;
        padding-top: 4px !important;
        padding-bottom: 4px !important;
        padding-left: 10px !important;
        padding-right: 10px !important;
    }

    #multi-step-form select.form-control {
        height: 36px !important;
        min-height: 36px !important;
    }

    #multi-step-form textarea.form-control {
        min-height: 58px !important;
        height: auto !important;
        border-radius: 9px;
    }

    #main-container.internacao-page .entity-actions-bar {
        margin-top: 8px !important;
        padding: 12px 8px !important;
        border: 0 !important;
        border-top: 1px solid rgba(94, 35, 99, 0.12) !important;
        border-radius: 0 !important;
        background: transparent !important;
        box-shadow: none !important;
    }

    #main-container.internacao-page .entity-actions-bar .btn {
        min-height: 36px !important;
        padding: 6px 14px !important;
        border-radius: 10px !important;
        font-size: .72rem !important;
    }
</style>

<div class="internacao-page cadastro-layout" id="main-container">
    <div class="internacao-page__hero">
        <div>
            <h1>Cadastrar paciente</h1>
        </div>
        <div class="hero-actions">
            <a class="hero-back-btn js-friendly-back"
                data-default-return="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/pacientes', ENT_QUOTES, 'UTF-8') ?>"
                href="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/pacientes', ENT_QUOTES, 'UTF-8') ?>">
                Voltar para lista
            </a>
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
                    <span class="internacao-card__tag internacao-card__tag--critical">Cadastro base</span>
                </div>
                <div class="internacao-card__body">

                    <input type="hidden" name="type" value="create">
                    <input type="hidden" name="deletado_pac" value="n">
                    <input type="hidden" name="confirmar_homonimo_pac" id="confirmar_homonimo_pac" value="0">

        <!-- Step 1: Personal Information -->
        <div id="step-1" class="step entity-step-card">
            <div class="entity-step-header">
                <div class="entity-step-copy">
                    <div class="entity-step-kicker">Passo 1</div>
                    <h3 class="entity-step-title">Identificação</h3>
                    <p class="entity-step-desc">Preencha os dados principais do beneficiário antes de seguir para endereço e contato.</p>
                </div>
                <span class="entity-step-badge">Dados essenciais</span>
            </div>
            <div class="row">
                <div class="form-group col-md-2 mb-3">
                    <label for="cpf_pac">CPF</label>
                    <input class="form-control" type="text" oninput="mascara(this, 'cpf')" id="cpf_pac" name="cpf_pac"
                        placeholder="000.000.000-00">
                    <div class="invalid-feedback">
                        Por favor, insira um CPF válido.
                    </div>
                    <div class="invalid-feedback" id="validar_cpf" style="display: none;">
                        CPF já cadastrado.
                    </div>
                </div>
                <div class="form-group col-md-6 mb-3">
                    <label for="nome_pac">Nome</label>
                    <input type="text" class="form-control" id="nome_pac" name="nome_pac" required>
                    <div class="invalid-feedback">
                        Por favor, insira o nome.
                    </div>
                </div>
                <div class="form-group col-md-2 mb-3">
                    <label for="recem_nascido_pac">Recém-nascido?</label>
                    <select class="form-control" id="recem_nascido_pac" name="recem_nascido_pac"
                        onchange="handleRecemNascidoChange()">
                        <option value="">Selecione</option>
                        <option value="s">Sim</option>
                        <option value="n" selected>Não</option>
                    </select>
                </div>
                <div class="form-group col-md-2 mb-3">
                    <label for="data_nasc_pac">Nascimento</label>
                    <input type="date" class="form-control" id="data_nasc_pac" name="data_nasc_pac">
                    <div class="invalid-feedback">
                        Por favor, insira a data de nascimento.
                    </div>
                </div>
            </div>
            <div class="row">
                <!-- <div class="invalid-feedback" id="validar_matricula_rn" style="display: none;">
                    Matrícula já cadastrada para RN.
                </div> -->

                <!-- Número RN -->
                <div class="form-group col-md-2 mb-3" id="numero_recem_nascido_group" style="display:none;">
                    <label for="numero_recem_nascido_pac">Número RN</label>
                    <input type="text" class="form-control" id="numero_recem_nascido_pac"
                        onkeyup="validarMatriculaExistente()" name="numero_recem_nascido_pac" placeholder="Ex: 1, 2..."
                        disabled>
                    <div class="invalid-feedback">Informe apenas números.</div>
                </div>

                <!-- Select: Mãe Titular -->
                <div class="form-group col-md-2 mb-3" id="mae_titular_group" style="display: none;">
                    <label for="mae_titular_pac">Mãe Titular?</label>
                    <select class="form-control" id="mae_titular_pac" name="mae_titular_pac"
                        onchange="handleMaeTitularChange()">
                        <option value="s" selected>Sim</option>
                        <option value="n">Não</option>
                    </select>
                </div>




                <!-- Input: Matrícula da Titular -->
                <div class="form-group col-md-4 mb-3" id="matricula_titular_group" style="display: none;">
                    <label for="matricula_titular_pac">Matrícula do Titular</label>
                    <input type="text" class="form-control" id="matricula_titular_pac" name="matricula_titular_pac">
                </div>
            </div>
            <div class="row">
                <div class="form-group col-md-3 mb-2">
                    <label for="matricula_pac">Matrícula</label>
                    <input type="text" class="form-control" onkeyup="validarMatriculaExistente()" id="matricula_pac"
                        name="matricula_pac" required>
                    <div class="invalid-feedback">
                        Por favor, insira a matrícula.
                    </div>
                    <div class="invalid-feedback" id="validar_matricula" style="display: none;">
                        Matrícula já cadastrada.
                    </div>

                </div>
                <div class="form-group col-md-3 mb-2">
                    <label for="sexo_pac">Sexo</label>
                    <select class="form-control" name="sexo_pac" id="sexo_pac">
                        <option value="" selected disabled>Selecione...</option>
                        <option value="f">Feminino</option>
                        <option value="m">Masculino</option>
                    </select>
                    <div class="invalid-feedback">Por favor, selecione o sexo.</div>
                </div>
                <div class="form-group col-md-3 mb-3">
                    <label for="fk_seguradora_pac">Seguradora</label>
                    <select class="form-control" id="fk_seguradora_pac" name="fk_seguradora_pac">
                        <option value="1" <?= $seguradoraDefaultId === 1 ? 'selected' : '' ?>>Selecione</option>
                        <?php foreach ($seguradorasSelect as $seguradora): ?>
                        <option value="<?= $seguradora["id_seguradora"] ?>" <?= ((int)$seguradora["id_seguradora"] === $seguradoraDefaultId) ? 'selected' : '' ?>><?= $seguradora['seguradora_seg'] ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-3 mb-3">
                    <label for="fk_estipulante_pac">Estipulante</label>
                    <select class="form-control" id="fk_estipulante_pac" name="fk_estipulante_pac">
                        <option value="1" <?= $estipulanteDefaultId === 1 ? 'selected' : '' ?>>Selecione</option>
                        <?php foreach ($estipulantesSelect as $estipulante): ?>
                        <option value="<?= $estipulante["id_estipulante"] ?>" <?= ((int)$estipulante["id_estipulante"] === $estipulanteDefaultId) ? 'selected' : '' ?>><?= $estipulante['nome_est'] ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <hr>
        </div>

        <!-- Step 2: Address Information -->
        <div id="step-2" class="step entity-step-card">
            <div class="entity-step-header">
                <div class="entity-step-copy">
                    <div class="entity-step-kicker">Passo 2</div>
                    <h3 class="entity-step-title">Endereço</h3>
                    <p class="entity-step-desc">Use o CEP para completar os campos automaticamente e ajuste apenas o que for necessário.</p>
                </div>
                <span class="entity-step-badge">Localização</span>
            </div>
            <div class="row">
                <div class="form-group col-md-3 mb-3">
                    <label for="cep_pac">CEP</label>
                    <input type="text" oninput="mascara(this, 'cep')" onkeyup="consultarCEP(this, 'pac')"
                        class="form-control" id="cep_pac" name="cep_pac" placeholder="00000-000">
                    <div class="invalid-feedback">
                        Por favor, insira o CEP.
                    </div>
                </div>
                <div class="form-group col-md-9 mb-3">
                    <label for="endereco_pac">Endereço</label>
                    <input readonly type="text" class="form-control" id="endereco_pac" name="endereco_pac"
                        placeholder="...">
                </div>
            </div>

            <div class="row">
                <div class="form-group col-md-6 mb-3">
                    <label for="bairro_pac">Bairro</label>
                    <input readonly type="text" class="form-control" id="bairro_pac" name="bairro_pac"
                        placeholder="...">
                </div>
                <div class="form-group col-md-6 mb-3">
                    <label for="cidade_pac">Cidade</label>
                    <input readonly type="text" class="form-control" id="cidade_pac" name="cidade_pac"
                        placeholder="...">
                </div>
            </div>

            <div class="row">
                <div class="form-group col-md-6 mb-3">
                    <label for="estado_pac">Estado</label>
                    <select readonly class="form-control" id="estado_pac" name="estado_pac">
                        <option value="">...</option>
                        <?php foreach ($estado_sel as $estado): ?>
                        <option value="<?= $estado ?>"><?= $estado ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-6 mb-3">
                    <label for="numero_pac">Número</label>
                    <input type="text" class="form-control" id="numero_pac" name="numero_pac">
                </div>
            </div>

            <div class="form-group mb-3">
                <label for="complemento_pac">Complemento</label>
                <input type="text" class="form-control" id="complemento_pac" name="complemento_pac">
            </div>
            <p class="internacao-card__eyebrow mb-3">Endereços adicionais</p>
            <div class="inline-manager-card mb-3">
                <div class="row">
                    <div class="form-group col-md-2 mb-2"><label for="end_tipo_inline">Tipo</label><input type="text" class="form-control" id="end_tipo_inline" placeholder="Cobrança"></div>
                    <div class="form-group col-md-2 mb-2"><label for="end_cep_inline">CEP</label><input type="text" class="form-control" id="end_cep_inline" placeholder="00000-000"></div>
                    <div class="form-group col-md-5 mb-2"><label for="end_logradouro_inline">Endereço</label><input type="text" class="form-control" id="end_logradouro_inline" placeholder="Rua, Av, etc."></div>
                    <div class="form-group col-md-1 mb-2"><label for="end_numero_inline">Nº</label><input type="text" class="form-control" id="end_numero_inline"></div>
                    <div class="form-group col-md-1 mb-2"><label for="end_principal_inline">Principal</label><select class="form-control" id="end_principal_inline"><option value="n">Não</option><option value="s">Sim</option></select></div>
                    <div class="form-group col-md-1 mb-2 d-flex align-items-end"><button type="button" id="btnAddEnderecoInline" class="btn btn-primary inline-add-btn" aria-label="Adicionar endereço">+</button></div>
                </div>
                <div class="row">
                    <div class="form-group col-md-3 mb-2"><label for="end_bairro_inline">Bairro</label><input type="text" class="form-control" id="end_bairro_inline"></div>
                    <div class="form-group col-md-3 mb-2"><label for="end_cidade_inline">Cidade</label><input type="text" class="form-control" id="end_cidade_inline"></div>
                    <div class="form-group col-md-2 mb-2"><label for="end_estado_inline">UF</label><input type="text" class="form-control" id="end_estado_inline"></div>
                    <div class="form-group col-md-4 mb-2"><label for="end_complemento_inline">Complemento</label><input type="text" class="form-control" id="end_complemento_inline"></div>
                </div>
                <div class="table-responsive mt-2"><table class="table table-sm table-striped mb-0"><thead><tr><th>Tipo</th><th>Endereço</th><th>Cidade/UF</th><th>P</th><th>Ação</th></tr></thead><tbody id="enderecosTableBody"><tr id="enderecosTableEmpty"><td colspan="5" class="text-muted text-center">Nenhum endereço adicional.</td></tr></tbody></table></div>
                <div id="enderecosHiddenContainer"></div>
            </div>
            <hr>
        </div>

        <!-- Step 3: Contact & Other Information -->
        <div id="step-3" class="step entity-step-card">
            <div class="entity-step-header">
                <div class="entity-step-copy">
                    <div class="entity-step-kicker">Passo 3</div>
                    <h3 class="entity-step-title">Contato e observações</h3>
                    <p class="entity-step-desc">Finalize com os canais de contato e registre qualquer contexto importante para o time assistencial.</p>
                </div>
                <span class="entity-step-badge">Fechamento</span>
            </div>
            <div class="row">
                <div class="form-group col-md-6 mb-3">
                    <label for="email01_pac">Email Principal</label>
                    <input type="email" class="form-control" id="email01_pac" name="email01_pac"
                        placeholder="exemplo@dominio.com">
                    <div class="invalid-feedback">
                        Por favor, insira um email válido.
                    </div>
                </div>
                <div class="form-group col-md-6 mb-3">
                    <label for="email02_pac">Email Alternativo</label>
                    <input type="email" class="form-control" id="email02_pac" name="email02_pac"
                        placeholder="exemplo@dominio.com">
                </div>
            </div>
            <p class="internacao-card__eyebrow mb-3">Emails adicionais</p>
            <div class="inline-manager-card mb-3">
                <div class="row">
                    <div class="form-group col-md-3 mb-2"><label for="email_tipo_inline">Tipo</label><input type="text" class="form-control" id="email_tipo_inline" placeholder="Responsável"></div>
                    <div class="form-group col-md-6 mb-2"><label for="email_email_inline">Email</label><input type="email" class="form-control" id="email_email_inline" placeholder="exemplo@dominio.com"></div>
                    <div class="form-group col-md-2 mb-2"><label for="email_principal_inline">Principal</label><select class="form-control" id="email_principal_inline"><option value="n">Não</option><option value="s">Sim</option></select></div>
                    <div class="form-group col-md-1 mb-2 d-flex align-items-end"><button type="button" id="btnAddEmailInline" class="btn btn-primary inline-add-btn" aria-label="Adicionar email">+</button></div>
                </div>
                <div class="table-responsive mt-2"><table class="table table-sm table-striped mb-0"><thead><tr><th>Tipo</th><th>Email</th><th>P</th><th>Ação</th></tr></thead><tbody id="emailsTableBody"><tr id="emailsTableEmpty"><td colspan="4" class="text-muted text-center">Nenhum email adicional.</td></tr></tbody></table></div>
                <div id="emailsHiddenContainer"></div>
            </div>

            <div class="row">
                <div class="form-group col-md-6 mb-3">
                    <label for="telefone01_pac">Telefone</label>
                    <input type="text" onkeydown="return mascaraTelefone(event)" class="form-control"
                        id="telefone01_pac" name="telefone01_pac" placeholder="(00) 0000-0000">
                </div>
                <div class="form-group col-md-6 mb-3">
                    <label for="telefone02_pac">Celular</label>
                    <input type="text" onkeydown="return mascaraTelefone(event)" class="form-control"
                        id="telefone02_pac" name="telefone02_pac" placeholder="(00) 00000-0000">
                    <div class="invalid-feedback">
                        Por favor, insira um número de celular válido.
                    </div>
                </div>
            </div>
            <p class="internacao-card__eyebrow mb-3">Telefones adicionais</p>
            <div class="inline-manager-card mb-3">
                <div class="row">
                    <div class="form-group col-md-2 mb-2"><label for="tel_tipo_inline">Tipo</label><input type="text" class="form-control" id="tel_tipo_inline" placeholder="Celular"></div>
                    <div class="form-group col-md-3 mb-2"><label for="tel_numero_inline">Telefone</label><input type="text" class="form-control" id="tel_numero_inline" placeholder="(00) 00000-0000"></div>
                    <div class="form-group col-md-2 mb-2"><label for="tel_ramal_inline">Ramal</label><input type="text" class="form-control" id="tel_ramal_inline"></div>
                    <div class="form-group col-md-3 mb-2"><label for="tel_contato_inline">Contato</label><input type="text" class="form-control" id="tel_contato_inline"></div>
                    <div class="form-group col-md-1 mb-2"><label for="tel_principal_inline">Principal</label><select class="form-control" id="tel_principal_inline"><option value="n">Não</option><option value="s">Sim</option></select></div>
                    <div class="form-group col-md-1 mb-2 d-flex align-items-end"><button type="button" id="btnAddTelefoneInline" class="btn btn-primary inline-add-btn" aria-label="Adicionar telefone">+</button></div>
                </div>
                <div class="table-responsive mt-2"><table class="table table-sm table-striped mb-0"><thead><tr><th>Tipo</th><th>Número</th><th>Ramal</th><th>Contato</th><th>P</th><th>Ação</th></tr></thead><tbody id="telefonesTableBody"><tr id="telefonesTableEmpty"><td colspan="6" class="text-muted text-center">Nenhum telefone adicional.</td></tr></tbody></table></div>
                <div id="telefonesHiddenContainer"></div>
            </div>
            <p class="internacao-card__eyebrow mb-3">Contatos adicionais</p>
            <div class="inline-manager-card mb-3">
                <div class="row">
                    <div class="form-group col-md-2 mb-2"><label for="cont_nome_inline">Nome</label><input type="text" class="form-control" id="cont_nome_inline"></div>
                    <div class="form-group col-md-2 mb-2"><label for="cont_parentesco_inline">Parentesco</label><input type="text" class="form-control" id="cont_parentesco_inline" placeholder="Familiar"></div>
                    <div class="form-group col-md-2 mb-2"><label for="cont_email_inline">Email</label><input type="email" class="form-control" id="cont_email_inline"></div>
                    <div class="form-group col-md-2 mb-2"><label for="cont_telefone_inline">Telefone</label><input type="text" class="form-control" id="cont_telefone_inline"></div>
                    <div class="form-group col-md-2 mb-2"><label for="cont_observacao_inline">Observação</label><input type="text" class="form-control" id="cont_observacao_inline"></div>
                    <div class="form-group col-md-1 mb-2"><label for="cont_principal_inline">Principal</label><select class="form-control" id="cont_principal_inline"><option value="n">Não</option><option value="s">Sim</option></select></div>
                    <div class="form-group col-md-1 mb-2 d-flex align-items-end"><button type="button" id="btnAddContatoInline" class="btn btn-primary inline-add-btn" aria-label="Adicionar contato">+</button></div>
                </div>
                <div class="table-responsive mt-2"><table class="table table-sm table-striped mb-0"><thead><tr><th>Nome</th><th>Parentesco</th><th>Email</th><th>Telefone</th><th>P</th><th>Ação</th></tr></thead><tbody id="contatosTableBody"><tr id="contatosTableEmpty"><td colspan="6" class="text-muted text-center">Nenhum contato adicional.</td></tr></tbody></table></div>
                <div id="contatosHiddenContainer"></div>
            </div>
            <div class="form-group mb-3">
                <label for="obs_pac">Observações</label>
                <textarea rows="5" class="form-control" id="obs_pac" name="obs_pac"></textarea>
            </div>
            <div class="entity-actions-bar">
                <button type="submit" class="btn btn-primary" id="finalizar_etapa1" name="finalizar_etapa1">
                    <i class="fas fa-check"></i> Cadastrar
                </button>
            </div>
                </div>
            </div>
        </form>
    </div>

    <div class="modal fade" id="modalNomeDuplicadoPaciente" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-warning">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>
                    Possível paciente já cadastrado
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">
                    Encontramos paciente(s) com nome igual ou muito parecido. Confirme se é homônimo antes de continuar.
                </p>
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Paciente</th>
                                <th>Matrícula</th>
                                <th>CPF</th>
                                <th>Nascimento</th>
                                <th>Seguradora</th>
                            </tr>
                        </thead>
                        <tbody id="dupPacienteBody">
                            <tr>
                                <td colspan="6" class="text-muted text-center">Sem dados.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <small class="text-muted d-block mt-2">
                    Se for a mesma pessoa, cancele e use o cadastro existente.
                </small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnConfirmarHomonimo">
                    É outro paciente, continuar
                </button>
            </div>
        </div>
    </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var backLink = document.querySelector('.js-friendly-back');
    if (!backLink) return;
    var fallbackUrl = backLink.getAttribute('data-default-return') || backLink.href;
    backLink.href = fallbackUrl;
    backLink.textContent = 'Voltar para lista';
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>

<?php
require_once("templates/footer.php");
?>
