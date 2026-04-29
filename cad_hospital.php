<?php
include_once("check_logado.php");

require_once("templates/header.php");
require_once("dao/hospitalDao.php");
require_once("models/message.php");

$hospitalDao = new HospitalDAO($conn, $BASE_URL);

// Receber id do usuário
$id_hospital = filter_input(INPUT_GET, "id_hospital");

?>
<?php include_once("array_dados.php");
?>
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

    #acomodacao-inline-card {
        background: #f7f5fb;
        border: 1px solid #e8def1;
        border-radius: 14px;
        padding: 14px;
    }

    #acomodacoesTable th,
    #acomodacoesTable td {
        vertical-align: middle;
    }

    .inline-manager-card {
        background: #f7f5fb;
        border: 1px solid #e8def1;
        border-radius: 14px;
        padding: 14px;
    }
</style>

<div class="internacao-page" id="main-container">
    <div class="internacao-page__hero">
        <div>
            <h1>Cadastrar hospital</h1>
        </div>
        <div class="hero-actions">
            <a class="hero-back-btn js-friendly-back"
                data-default-return="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/hospitais', ENT_QUOTES, 'UTF-8') ?>"
                href="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/hospitais', ENT_QUOTES, 'UTF-8') ?>">
                Voltar para lista
            </a>
            <span class="internacao-page__tag">Campos obrigatórios em destaque</span>
        </div>
    </div>
    <div class="internacao-page__content">
        <form action="<?= $BASE_URL ?>process_hospital.php" id="multi-step-form" method="POST" enctype="multipart/form-data"
            class="needs-validation visible entity-form" novalidate>
            <div class="internacao-card internacao-card--general">
                <div class="internacao-card__header">
                    <div>
                        <p class="internacao-card__eyebrow">Etapa 1</p>
                        <h2 class="internacao-card__title">Dados do hospital</h2>
                    </div>
                    <span class="internacao-card__tag internacao-card__tag--critical">Cadastro institucional</span>
                </div>
                <div class="internacao-card__body">
        <input type="hidden" name="type" value="create">
        <input type="hidden" name="deletado_hosp" value="n">

        <!-- Step 1: Informações Básicas -->
        <div id="step-1" class="step entity-step-card">
            <div class="entity-step-header">
                <div class="entity-step-copy">
                    <div class="entity-step-kicker">Passo 1</div>
                    <h3 class="entity-step-title">Identificação do hospital</h3>
                    <p class="entity-step-desc">Defina o registro principal da instituição antes de configurar endereço, contatos e acomodações.</p>
                </div>
                <span class="entity-step-badge">Dados base</span>
            </div>
            <div class="row">
                <div class="form-group col-md-6 mb-3">
                    <label for="cnpj_hosp">CNPJ</label>
                    <input type="text" oninput="mascara(this, 'cnpj')" class="form-control" id="cnpj_hosp"
                        name="cnpj_hosp" placeholder="Ex: 00.000.000/0000-00">
                    <div class="invalid-feedback">Por favor, insira um CNPJ válido.</div>
                </div>
                <div class="form-group col-md-6 mb-3">
                    <label for="nome_hosp"><span style="color:red;">*</span> Nome do Hospital</label>
                    <input type="text" class="form-control" id="nome_hosp" name="nome_hosp" required
                        placeholder="Digite o nome do hospital">
                    <div class="invalid-feedback">Por favor, insira o nome do hospital.</div>
                </div>
            </div>
            <hr>
        </div>

        <!-- Step 2: Endereço e Localização -->
        <div id="step-2" class="step entity-step-card">
            <div class="entity-step-header">
                <div class="entity-step-copy">
                    <div class="entity-step-kicker">Passo 2</div>
                    <h3 class="entity-step-title">Endereços</h3>
                    <p class="entity-step-desc">Cadastre o endereço principal e, se necessário, mantenha filiais ou endereços de cobrança no mesmo fluxo.</p>
                </div>
                <span class="entity-step-badge">Localização</span>
            </div>
            <div class="row">
                <div class="form-group col-md-4 mb-3">
                    <label for="cep_hosp">CEP</label>
                    <input type="text" onkeyup="consultarCEP(this, 'hosp')" class="form-control" id="cep_hosp"
                        name="cep_hosp" placeholder="00000-000">
                    <div class="invalid-feedback">Por favor, insira o CEP.</div>
                </div>
                <div class="form-group col-md-8 mb-3">
                    <label for="endereco_hosp">Endereço</label>
                    <input readonly type="text" class="form-control" id="endereco_hosp" name="endereco_hosp"
                        placeholder="Rua, Av, etc.">
                    <div class="invalid-feedback">Por favor, insira o endereço.</div>
                </div>
            </div>

            <div class="row">
                <div class="form-group col-md-6 mb-3">
                    <label for="bairro_hosp">Bairro</label>
                    <input readonly type="text" class="form-control" id="bairro_hosp" name="bairro_hosp"
                        placeholder="Bairro">
                </div>
                <div class="form-group col-md-6 mb-3">
                    <label for="cidade_hosp">Cidade</label>
                    <input readonly type="text" class="form-control" id="cidade_hosp" name="cidade_hosp"
                        placeholder="Cidade">
                </div>
            </div>

            <div class="row">
                <div class="form-group col-md-6 mb-3">
                    <label for="estado_hosp">Estado</label>
                    <input readonly class="form-control" id="estado_hosp" name="estado_hosp">
                </div>
                <div class="form-group col-md-6 mb-3">
                    <label for="numero_hosp">Número</label>
                    <input type="text" class="form-control" id="numero_hosp" name="numero_hosp"
                        placeholder="Número do endereço">
                </div>
            </div>

            <p class="internacao-card__eyebrow mb-3">Endereços adicionais</p>
            <div class="inline-manager-card mb-3">
                <div class="row">
                    <div class="form-group col-md-2 mb-2">
                        <label for="end_tipo_inline">Tipo</label>
                        <input type="text" class="form-control" id="end_tipo_inline" placeholder="Filial / Cobrança">
                    </div>
                    <div class="form-group col-md-2 mb-2">
                        <label for="end_cep_inline">CEP</label>
                        <input type="text" class="form-control" id="end_cep_inline" placeholder="00000-000">
                    </div>
                    <div class="form-group col-md-4 mb-2">
                        <label for="end_logradouro_inline">Endereço</label>
                        <input type="text" class="form-control" id="end_logradouro_inline" placeholder="Rua, Av, etc.">
                    </div>
                    <div class="form-group col-md-1 mb-2">
                        <label for="end_numero_inline">Nº</label>
                        <input type="text" class="form-control" id="end_numero_inline" placeholder="123">
                    </div>
                    <div class="form-group col-md-2 mb-2">
                        <label for="end_bairro_inline">Bairro</label>
                        <input type="text" class="form-control" id="end_bairro_inline" placeholder="Bairro">
                    </div>
                    <div class="form-group col-md-1 mb-2 d-flex align-items-end">
                        <button type="button" id="btnAddEnderecoInline" class="btn btn-primary w-100">+</button>
                    </div>
                </div>
                <div class="row">
                    <div class="form-group col-md-3 mb-2">
                        <label for="end_cidade_inline">Cidade</label>
                        <input type="text" class="form-control" id="end_cidade_inline" placeholder="Cidade">
                    </div>
                    <div class="form-group col-md-2 mb-2">
                        <label for="end_estado_inline">UF</label>
                        <input type="text" class="form-control" id="end_estado_inline" placeholder="UF">
                    </div>
                    <div class="form-group col-md-5 mb-2">
                        <label for="end_complemento_inline">Complemento</label>
                        <input type="text" class="form-control" id="end_complemento_inline" placeholder="Complemento">
                    </div>
                    <div class="form-group col-md-2 mb-2">
                        <label for="end_principal_inline">Principal</label>
                        <select class="form-control" id="end_principal_inline">
                            <option value="n">Não</option>
                            <option value="s">Sim</option>
                        </select>
                    </div>
                </div>
                <div class="table-responsive mt-2">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Endereço</th>
                                <th>Cidade/UF</th>
                                <th>Principal</th>
                                <th style="width: 90px;">Ação</th>
                            </tr>
                        </thead>
                        <tbody id="enderecosTableBody">
                            <tr id="enderecosTableEmpty">
                                <td colspan="5" class="text-muted text-center">Nenhum endereço adicional.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div id="enderecosHiddenContainer"></div>
            </div>

            <hr>
        </div>

        <!-- Step 3: Contato -->
        <div id="step-3" class="step entity-step-card">
            <div class="entity-step-header">
                <div class="entity-step-copy">
                    <div class="entity-step-kicker">Passo 3</div>
                    <h3 class="entity-step-title">Contato operacional</h3>
                    <p class="entity-step-desc">Organize emails, telefones e contatos-chave para facilitar auditoria, faturamento e rotina assistencial.</p>
                </div>
                <span class="entity-step-badge">Comunicação</span>
            </div>
            <div class="row">
                <div class="form-group col-md-3 mb-3">
                    <label for="email01_hosp">Email Principal</label>
                    <input type="email" class="form-control" id="email01_hosp" name="email01_hosp"
                        placeholder="exemplo@dominio.com">
                    <div class="invalid-feedback">Por favor, insira um email válido.</div>
                </div>
                <div class="form-group col-md-3 mb-3">
                    <label for="email02_hosp">Email Alternativo</label>
                    <input type="email" class="form-control" id="email02_hosp" name="email02_hosp"
                        placeholder="exemplo@dominio.com">
                </div>
                <div class="form-group col-md-2 mb-3">
                    <label for="telefone01_hosp">Telefone Principal</label>
                    <input type="text" onkeydown="return mascaraTelefone(event)" class="form-control"
                        id="telefone01_hosp" name="telefone01_hosp" placeholder="(00) 0000-0000">
                </div>
                <div class="form-group col-md-2 mb-3">
                    <label for="telefone02_hosp">Telefone Alternativo</label>
                    <input type="text" onkeydown="return mascaraTelefone(event)" class="form-control"
                        id="telefone02_hosp" name="telefone02_hosp" placeholder="(00) 0000-0000">
                </div>
                <div class="form-group col-md-2 mb-3">
                    <label for="ativo_hosp">Ativo</label>
                    <select class="form-control" name="ativo_hosp">
                        <option value="s">Sim</option>
                        <option value="n">Não</option>
                    </select>
                </div>
            </div>

            <p class="internacao-card__eyebrow mb-3">Telefones adicionais</p>
            <div class="inline-manager-card mb-3">
                <div class="row">
                    <div class="form-group col-md-2 mb-2">
                        <label for="tel_tipo_inline">Tipo</label>
                        <input type="text" class="form-control" id="tel_tipo_inline" placeholder="Plantão / Financeiro">
                    </div>
                    <div class="form-group col-md-3 mb-2">
                        <label for="tel_numero_inline">Telefone</label>
                        <input type="text" class="form-control" id="tel_numero_inline" placeholder="(00) 00000-0000">
                    </div>
                    <div class="form-group col-md-2 mb-2">
                        <label for="tel_ramal_inline">Ramal</label>
                        <input type="text" class="form-control" id="tel_ramal_inline" placeholder="Ramal">
                    </div>
                    <div class="form-group col-md-3 mb-2">
                        <label for="tel_contato_inline">Contato</label>
                        <input type="text" class="form-control" id="tel_contato_inline" placeholder="Nome do contato">
                    </div>
                    <div class="form-group col-md-1 mb-2">
                        <label for="tel_principal_inline">Principal</label>
                        <select class="form-control" id="tel_principal_inline">
                            <option value="n">Não</option>
                            <option value="s">Sim</option>
                        </select>
                    </div>
                    <div class="form-group col-md-1 mb-2 d-flex align-items-end">
                        <button type="button" id="btnAddTelefoneInline" class="btn btn-primary w-100">+</button>
                    </div>
                </div>
                <div class="table-responsive mt-2">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Número</th>
                                <th>Ramal</th>
                                <th>Contato</th>
                                <th>Principal</th>
                                <th style="width: 90px;">Ação</th>
                            </tr>
                        </thead>
                        <tbody id="telefonesTableBody">
                            <tr id="telefonesTableEmpty">
                                <td colspan="6" class="text-muted text-center">Nenhum telefone adicional.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div id="telefonesHiddenContainer"></div>
            </div>

            <p class="internacao-card__eyebrow mb-3">Contatos do hospital</p>
            <div class="inline-manager-card mb-3">
                <div class="row">
                    <div class="form-group col-md-2 mb-2">
                        <label for="cont_nome_inline">Nome</label>
                        <input type="text" class="form-control" id="cont_nome_inline" placeholder="Nome do contato">
                    </div>
                    <div class="form-group col-md-2 mb-2">
                        <label for="cont_cargo_inline">Cargo</label>
                        <input type="text" class="form-control" id="cont_cargo_inline" placeholder="Cargo">
                    </div>
                    <div class="form-group col-md-2 mb-2">
                        <label for="cont_setor_inline">Setor</label>
                        <input type="text" class="form-control" id="cont_setor_inline" placeholder="Setor">
                    </div>
                    <div class="form-group col-md-2 mb-2">
                        <label for="cont_email_inline">Email</label>
                        <input type="email" class="form-control" id="cont_email_inline" placeholder="email@dominio.com">
                    </div>
                    <div class="form-group col-md-2 mb-2">
                        <label for="cont_telefone_inline">Telefone</label>
                        <input type="text" class="form-control" id="cont_telefone_inline" placeholder="(00) 00000-0000">
                    </div>
                    <div class="form-group col-md-1 mb-2">
                        <label for="cont_principal_inline">Principal</label>
                        <select class="form-control" id="cont_principal_inline">
                            <option value="n">Não</option>
                            <option value="s">Sim</option>
                        </select>
                    </div>
                    <div class="form-group col-md-1 mb-2 d-flex align-items-end">
                        <button type="button" id="btnAddContatoInline" class="btn btn-primary w-100">+</button>
                    </div>
                </div>
                <div class="table-responsive mt-2">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Cargo/Setor</th>
                                <th>Email</th>
                                <th>Telefone</th>
                                <th>Principal</th>
                                <th style="width: 90px;">Ação</th>
                            </tr>
                        </thead>
                        <tbody id="contatosTableBody">
                            <tr id="contatosTableEmpty">
                                <td colspan="6" class="text-muted text-center">Nenhum contato adicional.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div id="contatosHiddenContainer"></div>
            </div>
            <hr>
        </div>

        <!-- Step 4: Coordenadas e Responsáveis -->
        <div id="step-4" class="step entity-step-card">
            <div class="entity-step-header">
                <div class="entity-step-copy">
                    <div class="entity-step-kicker">Passo 4</div>
                    <h3 class="entity-step-title">Dados complementares</h3>
                    <p class="entity-step-desc">Finalize com responsáveis, coordenadas e acomodações negociadas da instituição.</p>
                </div>
                <span class="entity-step-badge">Estrutura</span>
            </div>
            <div class="row">
                <div class="form-group col-md-4 mb-3">
                    <label for="coordenador_medico_hosp">Coordenador Médico</label>
                    <input type="text" class="form-control" id="coordenador_medico_hosp" name="coordenador_medico_hosp"
                        placeholder="Nome do coordenador médico">
                </div>
                <div class="form-group col-md-4 mb-3">
                    <label for="diretor_hosp">Diretor</label>
                    <input type="text" class="form-control" id="diretor_hosp" name="diretor_hosp"
                        placeholder="Nome do diretor">
                </div>
                <div class="form-group col-md-4 mb-3">
                    <label for="coordenador_fat_hosp">Coordenador de Faturamento</label>
                    <input type="text" class="form-control" id="coordenador_fat_hosp" name="coordenador_fat_hosp"
                        placeholder="Nome do coordenador de faturamento">
                </div>
            </div>

            <div class="row">
                <div class="form-group col-md-6 mb-3">
                    <label for="numero_hosp">Latitude</label>
                    <input type="text" class="form-control" id="latitude_hosp" name="latitude_hosp"
                        placeholder="Ex: -23.5505">
                </div>
                <div class="form-group col-md-6 mb-3">
                    <label for="longitude_hosp">Longitude</label>
                    <input type="text" class="form-control" id="longitude_hosp" name="longitude_hosp"
                        placeholder="Ex: -46.6333">
                </div>
            </div>

            <p class="internacao-card__eyebrow mb-3">Acomodações do hospital</p>
            <div id="acomodacao-inline-card" class="mb-3">
                <div class="row">
                    <div class="form-group col-md-4 mb-2">
                        <label for="acomodacao_nome_inline">Acomodação</label>
                        <select class="form-control" id="acomodacao_nome_inline">
                            <option value="">Selecione</option>
                            <?php
                            sort($dados_acomodacao, SORT_ASC);
                            foreach ($dados_acomodacao as $acomd): ?>
                            <option value="<?= htmlspecialchars($acomd, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($acomd, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-4 mb-2">
                        <label for="acomodacao_valor_inline">Valor diária</label>
                        <input type="text" class="form-control" id="acomodacao_valor_inline" placeholder="R$ 0,00">
                    </div>
                    <div class="form-group col-md-3 mb-2">
                        <label for="acomodacao_data_inline">Data contrato</label>
                        <input type="date" class="form-control" id="acomodacao_data_inline">
                    </div>
                    <div class="form-group col-md-1 mb-2 d-flex align-items-end">
                        <button type="button" id="btnAddAcomodacaoInline" class="btn btn-primary w-100">+</button>
                    </div>
                </div>

                <div class="table-responsive mt-3">
                    <table class="table table-sm table-striped mb-0" id="acomodacoesTable">
                        <thead>
                            <tr>
                                <th>Acomodação</th>
                                <th>Valor diária</th>
                                <th>Data contrato</th>
                                <th style="width: 80px;">Ação</th>
                            </tr>
                        </thead>
                        <tbody id="acomodacoesTableBody">
                            <tr id="acomodacoesTableEmpty">
                                <td colspan="4" class="text-muted text-center">Nenhuma acomodação adicionada.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div id="acomodacoesHiddenContainer"></div>
            </div>

            <div class="entity-actions-bar">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check"></i> Cadastrar
                </button>
            </div>
        </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var backLink = document.querySelector('.js-friendly-back');
        if (!backLink) return;
        var fallbackUrl = backLink.getAttribute('data-default-return') || backLink.href;
        var returnUrl = null;
        try {
            returnUrl = sessionStorage.getItem('return_flow_url');
        } catch (e) {
            returnUrl = null;
        }
        if (returnUrl && returnUrl !== window.location.href) {
            backLink.href = returnUrl;
            backLink.textContent = 'Voltar ao fluxo anterior';
        } else {
            backLink.href = fallbackUrl;
            backLink.textContent = 'Voltar para lista';
        }
    });

    (function () {
        const nomeEl = document.getElementById('acomodacao_nome_inline');
        const valorEl = document.getElementById('acomodacao_valor_inline');
        const dataEl = document.getElementById('acomodacao_data_inline');
        const addBtn = document.getElementById('btnAddAcomodacaoInline');
        const tbody = document.getElementById('acomodacoesTableBody');
        const hiddenContainer = document.getElementById('acomodacoesHiddenContainer');
        const emptyRow = document.getElementById('acomodacoesTableEmpty');

        if (!nomeEl || !valorEl || !dataEl || !addBtn || !tbody || !hiddenContainer || !emptyRow) {
            return;
        }

        let index = 0;

        function createHidden(name, value) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value || '';
            return input;
        }

        function onlyDigits(value) {
            return String(value || '').replace(/\D+/g, '');
        }

        function formatCurrencyBR(value) {
            const digits = onlyDigits(value);
            if (!digits) return '';
            const cents = Number(digits) / 100;
            return 'R$ ' + cents.toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function formatDateBR(value) {
            const raw = String(value || '').trim();
            if (!raw) return '';
            const m = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
            if (!m) return raw;
            return `${m[3]}/${m[2]}/${m[1]}`;
        }

        function addRow(nome, valor, data) {
            if (emptyRow) emptyRow.style.display = 'none';
            const dataView = formatDateBR(data);

            const row = document.createElement('tr');
            row.dataset.index = String(index);
            row.innerHTML = `
                <td>${nome}</td>
                <td>${valor || '-'}</td>
                <td>${dataView || '-'}</td>
                <td><button type="button" class="btn btn-sm btn-outline-danger">Remover</button></td>
            `;

            const wrap = document.createElement('div');
            wrap.dataset.index = String(index);
            wrap.appendChild(createHidden('acomodacao_nome[]', nome));
            wrap.appendChild(createHidden('acomodacao_valor[]', valor));
            wrap.appendChild(createHidden('acomodacao_data[]', data));
            hiddenContainer.appendChild(wrap);

            row.querySelector('button').addEventListener('click', function () {
                row.remove();
                wrap.remove();
                if (!tbody.querySelector('tr')) {
                    emptyRow.style.display = '';
                    tbody.appendChild(emptyRow);
                }
            });

            tbody.appendChild(row);
            index += 1;
        }

        valorEl.addEventListener('input', function () {
            const formatted = formatCurrencyBR(valorEl.value);
            valorEl.value = formatted;
        });

        addBtn.addEventListener('click', function () {
            const nome = (nomeEl.value || '').trim();
            const valor = formatCurrencyBR(valorEl.value);
            const data = (dataEl.value || '').trim();

            if (!nome) {
                alert('Selecione a acomodação.');
                nomeEl.focus();
                return;
            }

            addRow(nome, valor, data);
            nomeEl.value = '';
            valorEl.value = '';
            dataEl.value = '';
            nomeEl.focus();
        });
    })();

    (function () {
        function onlyDigits(value) {
            return String(value || '').replace(/\D+/g, '');
        }

        function formatPhoneBR(value) {
            const digits = onlyDigits(value);
            if (!digits) return '';
            if (digits.length > 10) {
                return digits.replace(/^(\d{2})(\d{5})(\d{0,4}).*$/, '($1) $2-$3').trim();
            }
            return digits.replace(/^(\d{2})(\d{4})(\d{0,4}).*$/, '($1) $2-$3').trim();
        }

        function createHidden(name, value) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value || '';
            return input;
        }

        function bindManager(config) {
            const addBtn = document.getElementById(config.addButtonId);
            const tbody = document.getElementById(config.tableBodyId);
            const emptyRow = document.getElementById(config.emptyRowId);
            const hiddenContainer = document.getElementById(config.hiddenContainerId);
            if (!addBtn || !tbody || !emptyRow || !hiddenContainer) return;

            let idx = 0;
            function addItem(item) {
                if (emptyRow.parentNode) emptyRow.remove();
                const row = document.createElement('tr');
                row.dataset.idx = String(idx);
                row.innerHTML = config.rowTemplate(item);
                const wrap = document.createElement('div');
                wrap.dataset.idx = String(idx);
                config.hiddenFields(item).forEach(({ name, value }) => wrap.appendChild(createHidden(name, value)));
                hiddenContainer.appendChild(wrap);
                const removeBtn = row.querySelector('.btn-remove-inline');
                if (removeBtn) {
                    removeBtn.addEventListener('click', function () {
                        row.remove();
                        wrap.remove();
                        if (!tbody.querySelector('tr')) {
                            tbody.appendChild(emptyRow);
                        }
                    });
                }
                tbody.appendChild(row);
                idx += 1;
            }

            addBtn.addEventListener('click', function () {
                const item = config.readItem();
                if (!item) return;
                addItem(item);
                config.clearInputs();
            });
        }

        bindManager({
            addButtonId: 'btnAddEnderecoInline',
            tableBodyId: 'enderecosTableBody',
            emptyRowId: 'enderecosTableEmpty',
            hiddenContainerId: 'enderecosHiddenContainer',
            readItem: function () {
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
                if (!item.logradouro) return null;
                return item;
            },
            rowTemplate: function (item) {
                return `<td>${item.tipo || '-'}</td>
                        <td>${item.logradouro}${item.numero ? ', ' + item.numero : ''}</td>
                        <td>${item.cidade || '-'}${item.estado ? '/' + item.estado : ''}</td>
                        <td>${item.principal === 's' ? 'Sim' : 'Não'}</td>
                        <td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-inline">Remover</button></td>`;
            },
            hiddenFields: function (item) {
                return [
                    { name: 'end_tipo[]', value: item.tipo },
                    { name: 'end_cep[]', value: item.cep },
                    { name: 'end_logradouro[]', value: item.logradouro },
                    { name: 'end_numero[]', value: item.numero },
                    { name: 'end_bairro[]', value: item.bairro },
                    { name: 'end_cidade[]', value: item.cidade },
                    { name: 'end_estado[]', value: item.estado },
                    { name: 'end_complemento[]', value: item.complemento },
                    { name: 'end_principal[]', value: item.principal },
                ];
            },
            clearInputs: function () {
                ['end_tipo_inline', 'end_cep_inline', 'end_logradouro_inline', 'end_numero_inline', 'end_bairro_inline', 'end_cidade_inline', 'end_estado_inline', 'end_complemento_inline'].forEach(function (id) {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });
                document.getElementById('end_principal_inline').value = 'n';
            }
        });

        bindManager({
            addButtonId: 'btnAddTelefoneInline',
            tableBodyId: 'telefonesTableBody',
            emptyRowId: 'telefonesTableEmpty',
            hiddenContainerId: 'telefonesHiddenContainer',
            readItem: function () {
                const item = {
                    tipo: (document.getElementById('tel_tipo_inline').value || '').trim(),
                    numero: formatPhoneBR(document.getElementById('tel_numero_inline').value || ''),
                    ramal: (document.getElementById('tel_ramal_inline').value || '').trim(),
                    contato: (document.getElementById('tel_contato_inline').value || '').trim(),
                    principal: document.getElementById('tel_principal_inline').value || 'n'
                };
                if (!item.numero) return null;
                return item;
            },
            rowTemplate: function (item) {
                return `<td>${item.tipo || '-'}</td>
                        <td>${item.numero}</td>
                        <td>${item.ramal || '-'}</td>
                        <td>${item.contato || '-'}</td>
                        <td>${item.principal === 's' ? 'Sim' : 'Não'}</td>
                        <td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-inline">Remover</button></td>`;
            },
            hiddenFields: function (item) {
                return [
                    { name: 'tel_tipo[]', value: item.tipo },
                    { name: 'tel_numero[]', value: item.numero },
                    { name: 'tel_ramal[]', value: item.ramal },
                    { name: 'tel_contato[]', value: item.contato },
                    { name: 'tel_principal[]', value: item.principal },
                ];
            },
            clearInputs: function () {
                ['tel_tipo_inline', 'tel_numero_inline', 'tel_ramal_inline', 'tel_contato_inline'].forEach(function (id) {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });
                document.getElementById('tel_principal_inline').value = 'n';
            }
        });

        bindManager({
            addButtonId: 'btnAddContatoInline',
            tableBodyId: 'contatosTableBody',
            emptyRowId: 'contatosTableEmpty',
            hiddenContainerId: 'contatosHiddenContainer',
            readItem: function () {
                const item = {
                    nome: (document.getElementById('cont_nome_inline').value || '').trim(),
                    cargo: (document.getElementById('cont_cargo_inline').value || '').trim(),
                    setor: (document.getElementById('cont_setor_inline').value || '').trim(),
                    email: (document.getElementById('cont_email_inline').value || '').trim(),
                    telefone: formatPhoneBR(document.getElementById('cont_telefone_inline').value || ''),
                    principal: document.getElementById('cont_principal_inline').value || 'n'
                };
                if (!item.nome) return null;
                return item;
            },
            rowTemplate: function (item) {
                return `<td>${item.nome}</td>
                        <td>${item.cargo || '-'}${item.setor ? ' / ' + item.setor : ''}</td>
                        <td>${item.email || '-'}</td>
                        <td>${item.telefone || '-'}</td>
                        <td>${item.principal === 's' ? 'Sim' : 'Não'}</td>
                        <td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-inline">Remover</button></td>`;
            },
            hiddenFields: function (item) {
                return [
                    { name: 'cont_nome[]', value: item.nome },
                    { name: 'cont_cargo[]', value: item.cargo },
                    { name: 'cont_setor[]', value: item.setor },
                    { name: 'cont_email[]', value: item.email },
                    { name: 'cont_telefone[]', value: item.telefone },
                    { name: 'cont_principal[]', value: item.principal },
                ];
            },
            clearInputs: function () {
                ['cont_nome_inline', 'cont_cargo_inline', 'cont_setor_inline', 'cont_email_inline', 'cont_telefone_inline'].forEach(function (id) {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });
                document.getElementById('cont_principal_inline').value = 'n';
            }
        });
    })();

    // validacao de tamanho do arquivo de imagem
    const imagem = document.querySelector("#logo_hosp")
    // console.log(imagem);

    if (imagem) {
        imagem.addEventListener("change", function (e) {
            if (!imagem.files || !imagem.files[0]) return;
            if (imagem.files[0].size > (1024 * 1024 * 2)) {

                // Apresentar a mensagem de erro
                // alert("Tamanho máximo permitido do arquivo é 2mb.");
                var notifImagem = document.querySelector("#notifImagem");
                if (notifImagem) notifImagem.style.display = "block";

                // Limpar o campo arquivo
                imagem.value = '';
                //(imagem ? imagem.value = '' : null)
            }
        })
    }

    function novoArquivo() {
        var notifImagem = document.querySelector("#notifImagem");
        if (notifImagem) notifImagem.style.display = "none";

    }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js">
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<?php
require_once("templates/footer.php");
?>
