<?php

include_once("check_logado.php");

require_once("models/usuario.php");
require_once("models/seguradora.php");
require_once("dao/usuarioDao.php");
require_once("dao/seguradoraDao.php");
require_once("templates/header.php");
require_once("array_dados.php");

$user = new seguradora();
$userDao = new UserDAO($conn, $BASE_URL);
$seguradoraDao = new seguradoraDAO($conn, $BASE_URL);

// Receber id do usuário
$id_seguradora = filter_input(INPUT_GET, "id_seguradora");

$seguradora = $seguradoraDao->findById($id_seguradora);
$enderecosSeguradora = $seguradoraDao->findEnderecosBySeguradora((int) $id_seguradora);
$telefonesSeguradora = $seguradoraDao->findTelefonesBySeguradora((int) $id_seguradora);
$contatosSeguradora = $seguradoraDao->findContatosBySeguradora((int) $id_seguradora);
$estado_selecionado = $seguradora->estado_seg;

$cep_formatado = formatarCEP($seguradora->cep_seg);
$cnpj_formatado = formatarCNPJ($seguradora->cnpj_seg);
$telefone01_formatado = formatarTelefone($seguradora->telefone01_seg);
$telefone02_formatado = formatarTelefone($seguradora->telefone02_seg);
$valor_alto_custo_seg = str_replace(',', '.', $seguradora->valor_alto_custo_seg);
$valor_alto_custo_formatado = number_format(floatval($valor_alto_custo_seg), 2, ',', '.');

function formatarCEP($cep)
{
    if (!empty($cep)) {
        $cep = preg_replace("/\D/", '', $cep);
        if (strlen($cep) === 8) {
            return substr($cep, 0, 5) . '-' . substr($cep, 5, 3);
        }
    }
    return '';
}

function formatarCNPJ($cnpj)
{
    if (!empty($cnpj)) {
        $cnpj = preg_replace("/\D/", '', $cnpj);
        if (strlen($cnpj) === 14) {
            return substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.' . substr($cnpj, 5, 3) . '/' . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12, 2);
        }
    }
    return '';
}

function formatarTelefone($telefone)
{
    if (!empty($telefone)) {
        $telefone = preg_replace("/\D/", '', $telefone);
        if (strlen($telefone) === 10) {
            return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 4) . '-' . substr($telefone, 6, 4);
        } elseif (strlen($telefone) === 11) {
            return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7, 4);
        }
    }
    return '';
}

if (empty($enderecosSeguradora) && !empty($seguradora->endereco_seg)) {
    $enderecosSeguradora[] = [
        'tipo_endereco' => 'Principal',
        'cep_endereco' => $seguradora->cep_seg,
        'endereco_endereco' => $seguradora->endereco_seg,
        'numero_endereco' => $seguradora->numero_seg,
        'bairro_endereco' => $seguradora->bairro_seg,
        'cidade_endereco' => $seguradora->cidade_seg,
        'estado_endereco' => $seguradora->estado_seg,
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

    .inline-manager-card {
        background: #f7f5fb;
        border: 1px solid #e8def1;
        border-radius: 14px;
        padding: 14px;
    }
</style>

<div id="main-container" class="internacao-page">
    <div class="internacao-page__hero">
        <div><h1>Editar seguradora</h1></div>
        <div class="hero-actions">
            <a class="hero-back-btn" href="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/seguradoras', ENT_QUOTES, 'UTF-8') ?>">Voltar para lista</a>
            <span class="internacao-page__tag">Campos obrigatórios em destaque</span>
        </div>
    </div>
    <div class="internacao-page__content">
        <form class="container-fluid fundo_tela_cadastros visible entity-form" action="<?= $BASE_URL ?>process_seguradora.php"
            id="multi-step-form" method="POST" enctype="multipart/form-data">
            <div class="internacao-card internacao-card--general">
                <div class="internacao-card__header">
                    <div>
                        <p class="internacao-card__eyebrow">Etapa 1</p>
                        <h2 class="internacao-card__title">Dados da seguradora</h2>
                    </div>
                    <span class="internacao-card__tag internacao-card__tag--critical">Edição contratual</span>
                </div>
                <div class="internacao-card__body">
            <input type="hidden" name="type" value="update">
            <input type="hidden" class="form-control" id="id_seguradora" name="id_seguradora"
                value="<?= $seguradora->id_seguradora ?>">
            <input type="hidden" name="deletado_seg" value="n">
            <!-- Step 1: Informações da Seguradora -->
            <div id="step-1" class="step entity-step-card">
                <div class="entity-step-header">
                    <div class="entity-step-copy">
                        <div class="entity-step-kicker">Passo 1</div>
                        <h3 class="entity-step-title">Identificação da seguradora</h3>
                        <p class="entity-step-desc">Ajuste os dados de referência da operadora antes de revisar endereço, contatos e parâmetros.</p>
                    </div>
                    <span class="entity-step-badge">Dados base</span>
                </div>
                <div class="row">
                    <div class="form-group col-md-6">
                        <label for="seguradora_seg"><span style="color:red;">*</span> Seguradora</label>
                        <input type="text" class="form-control" id="seguradora_seg" name="seguradora_seg"
                            value="<?= $seguradora->seguradora_seg ?>" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="cnpj_seg">CNPJ</label>
                        <input type="text" class="form-control" id="cnpj_seg" name="cnpj_seg"
                            oninput="mascara(this, 'cnpj')" value="<?= $cnpj_formatado ?>"
                            placeholder="00.000.000/0000-00">
                    </div>
                </div>
                <hr>
            </div>

            <!-- Step 2: Endereço -->
            <div id="step-2" class="step entity-step-card">
                <div class="entity-step-header">
                    <div class="entity-step-copy">
                        <div class="entity-step-kicker">Passo 2</div>
                        <h3 class="entity-step-title">Endereços</h3>
                        <p class="entity-step-desc">Reorganize o endereço principal e os complementares com a mesma hierarquia usada no cadastro.</p>
                    </div>
                    <span class="entity-step-badge">Localização</span>
                </div>
                <div class="row">
                    <div class="form-group col-md-6">
                        <label for="cep_seg">CEP</label>
                        <input type="text" class="form-control" id="cep_pac" name="cep_seg"
                            onkeyup="consultarCEP(this, 'seg')" value="<?= $cep_formatado ?>" placeholder="00000-000">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="endereco_seg">Endereço</label>
                        <input readonly type="text" class="form-control" id="endereco_seg" name="endereco_seg"
                            value="<?= $seguradora->endereco_seg ?>" placeholder="Rua, Avenida, etc.">
                    </div>
                </div>
                <div class="row">
                    <div class="form-group col-md-6">
                        <label for="bairro_seg">Bairro</label>
                        <input readonly type="text" class="form-control" id="bairro_seg" name="bairro_seg"
                            value="<?= $seguradora->bairro_seg ?>" placeholder="Digite o bairro">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="cidade_seg">Cidade</label>
                        <input readonly type="text" class="form-control" id="cidade_seg" name="cidade_seg"
                            value="<?= $seguradora->cidade_seg ?>" placeholder="Digite a cidade">
                    </div>
                </div>
                <div class="row">
                    <div class="form-group col-md-6">
                        <label for="estado_seg">Estado</label>
                        <select readonly class="form-control" id="estado_seg" name="estado_seg" required>
                            <option value="">Selecione o estado</option>
                            <?php foreach ($estado_sel as $estado): ?>
                            <option value="<?= $estado ?>" <?= $estado_selecionado == $estado ? 'selected' : '' ?>>
                                <?= $estado ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="numero_seg">Número</label>
                        <input type="number" class="form-control" id="numero_seg" name="numero_seg"
                            value="<?= $seguradora->numero_seg ?>" placeholder="Número do endereço">
                    </div>
                </div>
                <p class="internacao-card__eyebrow mb-3">Endereços adicionais</p>
                <div class="inline-manager-card mb-3">
                    <div class="row">
                        <div class="form-group col-md-2 mb-2"><label for="end_tipo_inline">Tipo</label><input type="text" class="form-control" id="end_tipo_inline"></div>
                        <div class="form-group col-md-2 mb-2"><label for="end_cep_inline">CEP</label><input type="text" class="form-control" id="end_cep_inline"></div>
                        <div class="form-group col-md-6 mb-2"><label for="end_logradouro_inline">Endereço</label><input type="text" class="form-control" id="end_logradouro_inline"></div>
                        <div class="form-group col-md-1 mb-2"><label for="end_numero_inline">Nº</label><input type="text" class="form-control" id="end_numero_inline"></div>
                        <div class="form-group col-md-1 mb-2 d-flex align-items-end"><button type="button" id="btnAddEnderecoInline" class="btn btn-primary w-100">+</button></div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-3 mb-2"><label for="end_bairro_inline">Bairro</label><input type="text" class="form-control" id="end_bairro_inline"></div>
                        <div class="form-group col-md-3 mb-2"><label for="end_cidade_inline">Cidade</label><input type="text" class="form-control" id="end_cidade_inline"></div>
                        <div class="form-group col-md-2 mb-2"><label for="end_estado_inline">UF</label><input type="text" class="form-control" id="end_estado_inline"></div>
                        <div class="form-group col-md-2 mb-2"><label for="end_complemento_inline">Complemento</label><input type="text" class="form-control" id="end_complemento_inline"></div>
                        <div class="form-group col-md-2 mb-2"><label for="end_principal_inline">Principal</label><select class="form-control" id="end_principal_inline"><option value="n">Não</option><option value="s">Sim</option></select></div>
                    </div>
                    <div class="table-responsive mt-2"><table class="table table-sm table-striped mb-0"><thead><tr><th>Tipo</th><th>Endereço</th><th>Cidade/UF</th><th>P</th><th>Ação</th></tr></thead><tbody id="enderecosTableBody"><tr id="enderecosTableEmpty" style="display: <?= empty($enderecosSeguradora) ? '' : 'none' ?>;"><td colspan="5" class="text-muted text-center">Nenhum endereço adicional.</td></tr>
                        <?php foreach ($enderecosSeguradora as $end): ?>
                            <?php $p = ((int)($end['principal_endereco'] ?? 0) === 1) ? 's' : 'n'; ?>
                            <tr><td><?= htmlspecialchars((string)($end['tipo_endereco'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string)($end['endereco_endereco'] ?? '') . (!empty($end['numero_endereco']) ? ', ' . $end['numero_endereco'] : ''), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string)($end['cidade_endereco'] ?? '-') . (!empty($end['estado_endereco']) ? '/' . $end['estado_endereco'] : ''), ENT_QUOTES, 'UTF-8') ?></td><td><?= $p === 's' ? 'Sim' : 'Não' ?></td><td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-inline">Remover</button></td><td style="display:none;"><input type="hidden" name="end_tipo[]" value="<?= htmlspecialchars((string)($end['tipo_endereco'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="end_cep[]" value="<?= htmlspecialchars((string)($end['cep_endereco'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="end_logradouro[]" value="<?= htmlspecialchars((string)($end['endereco_endereco'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="end_numero[]" value="<?= htmlspecialchars((string)($end['numero_endereco'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="end_bairro[]" value="<?= htmlspecialchars((string)($end['bairro_endereco'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="end_cidade[]" value="<?= htmlspecialchars((string)($end['cidade_endereco'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="end_estado[]" value="<?= htmlspecialchars((string)($end['estado_endereco'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="end_complemento[]" value="<?= htmlspecialchars((string)($end['complemento_endereco'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="end_principal[]" value="<?= $p ?>"></td></tr>
                        <?php endforeach; ?>
                    </tbody></table></div>
                    <div id="enderecosHiddenContainer"></div>
                </div>
                <hr>
            </div>

            <!-- Step 3: Contato e Informações Complementares -->
            <div id="step-3" class="step entity-step-card">
                <div class="entity-step-header">
                    <div class="entity-step-copy">
                        <div class="entity-step-kicker">Passo 3</div>
                        <h3 class="entity-step-title">Contato e operação</h3>
                        <p class="entity-step-desc">Atualize responsáveis, contatos adicionais, regras operacionais e anexos da seguradora.</p>
                    </div>
                    <span class="entity-step-badge">Fechamento</span>
                </div>
                <div class="row">
                    <div class="form-group col-md-6">
                        <label for="email01_seg">Email Principal</label>
                        <input type="email" class="form-control" id="email01_seg" name="email01_seg"
                            value="<?= $seguradora->email01_seg ?>" placeholder="exemplo@dominio.com">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="email02_seg">Email Alternativo</label>
                        <input type="email" class="form-control" id="email02_seg" name="email02_seg"
                            value="<?= $seguradora->email02_seg ?>" placeholder="exemplo@dominio.com">
                    </div>
                </div>
                <div class="row">
                    <div class="form-group col-md-6">
                        <label for="telefone01_seg">Telefone</label>
                        <input type="text" class="form-control" id="telefone01_seg" name="telefone01_seg"
                            onkeydown="return mascaraTelefone(event)" value="<?= $telefone01_formatado ?>"
                            placeholder="(00) 0000-0000">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="telefone02_seg">Telefone Alternativo</label>
                        <input type="text" class="form-control" id="telefone02_seg" name="telefone02_seg"
                            onkeydown="return mascaraTelefone(event)" value="<?= $telefone02_formatado ?>"
                            placeholder="(00) 0000-0000">
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
                    <div class="table-responsive mt-2"><table class="table table-sm table-striped mb-0"><thead><tr><th>Tipo</th><th>Número</th><th>Ramal</th><th>Contato</th><th>P</th><th>Ação</th></tr></thead><tbody id="telefonesTableBody"><tr id="telefonesTableEmpty" style="display: <?= empty($telefonesSeguradora) ? '' : 'none' ?>;"><td colspan="6" class="text-muted text-center">Nenhum telefone adicional.</td></tr>
                        <?php foreach ($telefonesSeguradora as $tel): ?>
                            <?php $tp = ((int)($tel['principal_telefone'] ?? 0) === 1) ? 's' : 'n'; $nd = preg_replace('/\D+/', '', (string)($tel['numero_telefone'] ?? '')); $nf = $nd; if (strlen($nd)===11) { $nf = '(' . substr($nd,0,2) . ') ' . substr($nd,2,5) . '-' . substr($nd,7,4);} elseif (strlen($nd)===10) { $nf = '(' . substr($nd,0,2) . ') ' . substr($nd,2,4) . '-' . substr($nd,6,4);} ?>
                            <tr><td><?= htmlspecialchars((string)($tel['tipo_telefone'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($nf ?: '-', ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string)($tel['ramal_telefone'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string)($tel['contato_telefone'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td><td><?= $tp === 's' ? 'Sim' : 'Não' ?></td><td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-inline">Remover</button></td><td style="display:none;"><input type="hidden" name="tel_tipo[]" value="<?= htmlspecialchars((string)($tel['tipo_telefone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="tel_numero[]" value="<?= htmlspecialchars($nf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="tel_ramal[]" value="<?= htmlspecialchars((string)($tel['ramal_telefone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="tel_contato[]" value="<?= htmlspecialchars((string)($tel['contato_telefone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="tel_principal[]" value="<?= $tp ?>"></td></tr>
                        <?php endforeach; ?>
                    </tbody></table></div>
                    <div id="telefonesHiddenContainer"></div>
                </div>
                <div class="row">
                    <div class="form-group col-md-6">
                        <label for="ativo_seg">Ativo</label>
                        <select class="form-control" id="ativo_seg" name="ativo_seg">
                            <option value="s" <?= $seguradora->ativo_seg == 's' ? 'selected' : '' ?>>Sim</option>
                            <option value="n" <?= $seguradora->ativo_seg == 'n' ? 'selected' : '' ?>>Não</option>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="coord_rh_seg">Coordenador RH</label>
                        <input type="text" class="form-control" id="coord_rh_seg" name="coord_rh_seg"
                            value="<?= $seguradora->coord_rh_seg ?>" placeholder="Nome do Coordenador RH">
                    </div>
                </div>
                <div class="row">
                    <div class="form-group col-md-6">
                        <label for="coordenador_seg">Coordenador</label>
                        <input type="text" class="form-control" id="coordenador_seg" name="coordenador_seg"
                            value="<?= $seguradora->coordenador_seg ?>" placeholder="Nome do Coordenador">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="contato_seg">Contato Seguradora</label>
                        <input type="text" class="form-control" id="contato_seg" name="contato_seg"
                            value="<?= $seguradora->contato_seg ?>" placeholder="Nome do contato na seguradora">
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
                        <div class="form-group col-md-1 mb-2 d-flex align-items-end"><button type="button" id="btnAddContatoInline" class="btn btn-primary w-100">+</button></div>
                    </div>
                    <div class="table-responsive mt-2"><table class="table table-sm table-striped mb-0"><thead><tr><th>Nome</th><th>Cargo/Setor</th><th>Email</th><th>Telefone</th><th>P</th><th>Ação</th></tr></thead><tbody id="contatosTableBody"><tr id="contatosTableEmpty" style="display: <?= empty($contatosSeguradora) ? '' : 'none' ?>;"><td colspan="6" class="text-muted text-center">Nenhum contato adicional.</td></tr>
                        <?php foreach ($contatosSeguradora as $ct): ?>
                            <?php $cp = ((int)($ct['principal_contato'] ?? 0) === 1) ? 's' : 'n'; $nd = preg_replace('/\D+/', '', (string)($ct['telefone_contato'] ?? '')); $nf = $nd; if (strlen($nd)===11) { $nf = '(' . substr($nd,0,2) . ') ' . substr($nd,2,5) . '-' . substr($nd,7,4);} elseif (strlen($nd)===10) { $nf = '(' . substr($nd,0,2) . ') ' . substr($nd,2,4) . '-' . substr($nd,6,4);} ?>
                            <tr><td><?= htmlspecialchars((string)($ct['nome_contato'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars(((string)($ct['cargo_contato'] ?? '-')) . (!empty($ct['setor_contato']) ? ' / ' . $ct['setor_contato'] : ''), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string)($ct['email_contato'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($nf ?: '-', ENT_QUOTES, 'UTF-8') ?></td><td><?= $cp === 's' ? 'Sim' : 'Não' ?></td><td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-inline">Remover</button></td><td style="display:none;"><input type="hidden" name="cont_nome[]" value="<?= htmlspecialchars((string)($ct['nome_contato'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="cont_cargo[]" value="<?= htmlspecialchars((string)($ct['cargo_contato'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="cont_setor[]" value="<?= htmlspecialchars((string)($ct['setor_contato'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="cont_email[]" value="<?= htmlspecialchars((string)($ct['email_contato'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="cont_telefone[]" value="<?= htmlspecialchars($nf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="cont_principal[]" value="<?= $cp ?>"></td></tr>
                        <?php endforeach; ?>
                    </tbody></table></div>
                    <div id="contatosHiddenContainer"></div>
                </div>
                <div class="row">
                    <div class="form-group col-md-6">
                        <label for="dias_visita_seg">Dias Visita Clínica</label>
                        <input type="text" class="form-control" id="dias_visita_seg" name="dias_visita_seg"
                            value="<?= $seguradora->dias_visita_seg ?>"
                            placeholder="Digite os dias de visita à clínica">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="dias_visita_uti_seg">Dias Visita UTI</label>
                        <input type="text" class="form-control" id="dias_visita_uti_seg" name="dias_visita_uti_seg"
                            value="<?= $seguradora->dias_visita_uti_seg ?>"
                            placeholder="Digite os dias de visita à UTI">
                    </div>
                </div>
                <div class="row">
                    <div class="form-group col-md-6">
                        <label for="valor_alto_custo_seg">Valor Alto Custo</label>
                        <input type="text" class="form-control" id="valor_alto_custo_seg" name="valor_alto_custo_seg"
                            value="<?= $valor_alto_custo_formatado ?>" placeholder="Valor alto custo">
                    </div>
                    <div class="form-group col-md-6">
                        <label for="longa_permanencia_seg">Longa Permanência</label>
                        <input type="text" class="form-control" id="longa_permanencia_seg" name="longa_permanencia_seg"
                            value="<?= $seguradora->longa_permanencia_seg ?>" placeholder="Longa permanência">
                    </div>
                </div>

                <!-- Logo Upload -->
                <div class="row">
                    <div class="form-group col-md-6">
                        <label for="logo_seg">Logo</label>
                        <input type="file" class="form-control" name="logo_seg" id="logo_seg"
                            accept="image/png, image/jpeg">
                        <div class="notif-input oculto" id="notifImagem">Tamanho do arquivo inválido!</div>
                    </div>
                    <?php if (!empty($seguradora->logo_seg)): ?>
                    <div class="form-group col-md-6">
                        <label>Logo Atual</label>
                        <img src="uploads/<?= $seguradora->logo_seg; ?>" height="80" width="80">
                    </div>
                    <?php endif; ?>
                </div>
                <div class="entity-actions-bar">
                    <div class="entity-actions-copy">Revise contatos, logo e parâmetros operacionais antes de salvar ou excluir este registro.</div>
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
                form.action = "<?= $BASE_URL ?>process_seguradora.php";

                // Adiciona campos ocultos para o processo de deletar
                const inputType = document.createElement("input");
                inputType.type = "hidden";
                inputType.name = "type";
                inputType.value = "delUpdate";
                form.appendChild(inputType);

                const inputDeleted = document.createElement("input");
                inputDeleted.type = "hidden";
                inputDeleted.name = "deletado_seg";
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
</script>

<script>
const imagem = document.querySelector("#logo_seg");

if (imagem) {
    imagem.addEventListener("change", function(e) {
        if (!imagem.files || !imagem.files[0]) return;
        if (imagem.files[0].size > (1024 * 1024 * 2)) {
            var notifImagem = document.querySelector("#notifImagem");
            if (notifImagem) notifImagem.style.display = "block";
            imagem.value = '';
        }
    });
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
        add.addEventListener('click', function () {
            const item = cfg.read(); if (!item) return;
            if (empty.parentNode) empty.remove();
            const tr = document.createElement('tr'); tr.innerHTML = cfg.row(item);
            const holder = document.createElement('div'); cfg.hidden(item).forEach(f => holder.appendChild(h(f.name, f.value))); wrap.appendChild(holder);
            tr.querySelector('.btn-remove-inline').addEventListener('click', function () { tr.remove(); holder.remove(); if (!body.querySelector('tr')) body.appendChild(empty); });
            body.appendChild(tr); cfg.clear();
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
<?php require_once("templates/footer.php"); ?>
