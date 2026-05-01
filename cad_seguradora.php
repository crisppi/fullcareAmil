<?php
include_once("check_logado.php");
require_once("templates/header.php");
require_once("dao/seguradoraDao.php");
require_once("models/message.php");
include_once("array_dados.php");

$seguradoraDao = new seguradoraDAO($conn, $BASE_URL);
// Receber id da seguradora
$id_seguradora = filter_input(INPUT_GET, "id_seguradora");
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

    .inline-manager-card {
        background: #f7f5fb;
        border: 1px solid #e8def1;
        border-radius: 14px;
        padding: 14px;
    }
</style>

<div id="main-container" class="internacao-page">
    <div class="internacao-page__hero">
        <div>
            <h1>Cadastrar seguradora</h1>
        </div>
        <div class="hero-actions">
            <a class="hero-back-btn js-friendly-back"
                data-default-return="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/seguradoras', ENT_QUOTES, 'UTF-8') ?>"
                href="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/seguradoras', ENT_QUOTES, 'UTF-8') ?>">
                Voltar para lista
            </a>
            <span class="internacao-page__tag">Campos obrigatórios em destaque</span>
        </div>
    </div>
    <div class="internacao-page__content">
        <form action="<?= $BASE_URL ?>process_seguradora.php" method="POST" enctype="multipart/form-data" id="multi-step-form" class="visible entity-form">

            <div class="internacao-card internacao-card--general">
                <div class="internacao-card__header">
                    <div>
                        <p class="internacao-card__eyebrow">Etapa 1</p>
                        <h2 class="internacao-card__title">Dados da seguradora</h2>
                    </div>
                    <span class="internacao-card__tag internacao-card__tag--critical">Cadastro contratual</span>
                </div>
                <div class="internacao-card__body">
                    <input type="hidden" name="type" value="create">
                    <input type="hidden" name="deletado_seg" value="n">

                    <div id="step-1" class="step entity-step-card">
                        <div class="entity-step-header">
                            <div class="entity-step-copy">
                                <div class="entity-step-kicker">Passo 1</div>
                                <h3 class="entity-step-title">Identificação da seguradora</h3>
                                <p class="entity-step-desc">Defina o nome comercial e o CNPJ que serão usados como referência no restante do sistema.</p>
                            </div>
                            <span class="entity-step-badge">Dados base</span>
                        </div>
                        <div class="row">
                            <div class="form-group col-md-6 mb-3">
                                <label for="seguradora_seg"><span style="color:red;">*</span> Seguradora</label>
                                <input type="text" class="form-control" id="seguradora_seg" name="seguradora_seg" required autofocus
                                    placeholder="Digite o nome da seguradora">
                            </div>
                            <div class="form-group col-md-6 mb-3">
                                <label for="cnpj_seg">CNPJ</label>
                                <input type="text" class="form-control" id="cnpj_seg" name="cnpj_seg"
                                    oninput="mascara(this, 'cnpj')" placeholder="00.000.000/0000-00">
                            </div>
                        </div>
                        <hr>
                    </div>

                    <div id="step-2" class="step entity-step-card">
                        <div class="entity-step-header">
                            <div class="entity-step-copy">
                                <div class="entity-step-kicker">Passo 2</div>
                                <h3 class="entity-step-title">Endereços</h3>
                                <p class="entity-step-desc">Cadastre o endereço principal e mantenha pontos alternativos para cobrança, operação ou atendimento.</p>
                            </div>
                            <span class="entity-step-badge">Localização</span>
                        </div>
                        <div class="row">
                            <div class="form-group col-md-6 mb-3">
                                <label for="cep_seg">CEP</label>
                                <input type="text" class="form-control" id="cep_seg" name="cep_seg"
                                    onkeyup="consultarCEP(this, 'seg')" placeholder="00000-000">
                            </div>
                            <div class="form-group col-md-6 mb-3">
                                <label for="endereco_seg">Endereço</label>
                                <input readonly type="text" class="form-control" id="endereco_seg" name="endereco_seg"
                                    placeholder="Rua, Avenida, etc.">
                            </div>
                        </div>
                        <div class="row">
                            <div class="form-group col-md-6 mb-3">
                                <label for="bairro_seg">Bairro</label>
                                <input readonly type="text" class="form-control" id="bairro_seg" name="bairro_seg"
                                    placeholder="Digite o bairro">
                            </div>
                            <div class="form-group col-md-6 mb-3">
                                <label for="cidade_seg">Cidade</label>
                                <input readonly type="text" class="form-control" id="cidade_seg" name="cidade_seg"
                                    placeholder="Digite a cidade">
                            </div>
                        </div>
                        <div class="row">
                            <div class="form-group col-md-6 mb-3">
                                <label for="estado_seg">Estado</label>
                                <select readonly class="form-control" id="estado_seg" name="estado_seg">
                                    <option value="">Selecione o estado</option>
                                    <?php foreach ($estado_sel as $estado): ?>
                                        <option value="<?= $estado ?>"><?= $estado ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group col-md-6 mb-3">
                                <label for="numero_seg">Número</label>
                                <input type="number" class="form-control" id="numero_seg" name="numero_seg"
                                    placeholder="Número do endereço">
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
                            <div class="table-responsive mt-2"><table class="table table-sm table-striped mb-0"><thead><tr><th>Tipo</th><th>Endereço</th><th>Cidade/UF</th><th>P</th><th>Ação</th></tr></thead><tbody id="enderecosTableBody"><tr id="enderecosTableEmpty"><td colspan="5" class="text-muted text-center">Nenhum endereço adicional.</td></tr></tbody></table></div>
                            <div id="enderecosHiddenContainer"></div>
                        </div>
                        <hr>
                    </div>

                    <div id="step-3" class="step entity-step-card">
                        <div class="entity-step-header">
                            <div class="entity-step-copy">
                                <div class="entity-step-kicker">Passo 3</div>
                                <h3 class="entity-step-title">Contato e operação</h3>
                                <p class="entity-step-desc">Concentre contatos, responsáveis, visitas e anexos operacionais em uma única área mais legível.</p>
                            </div>
                            <span class="entity-step-badge">Fechamento</span>
                        </div>
                        <div class="row">
                            <div class="form-group col-md-3 mb-3">
                                <label for="email01_seg">Email Principal</label>
                                <input type="email" class="form-control" id="email01_seg" name="email01_seg"
                                    placeholder="exemplo@dominio.com">
                            </div>
                            <div class="form-group col-md-3 mb-3">
                                <label for="email02_seg">Email Alternativo</label>
                                <input type="email" class="form-control" id="email02_seg" name="email02_seg"
                                    placeholder="exemplo@dominio.com">
                            </div>
                            <div class="form-group col-md-2 mb-3">
                                <label for="telefone01_seg">Telefone</label>
                                <input type="text" class="form-control" id="telefone01_seg" name="telefone01_seg"
                                    onkeydown="return mascaraTelefone(event)" placeholder="(00) 0000-0000">
                            </div>
                            <div class="form-group col-md-2 mb-3">
                                <label for="telefone02_seg">Telefone Alternativo</label>
                                <input type="text" class="form-control" id="telefone02_seg" name="telefone02_seg"
                                    onkeydown="return mascaraTelefone(event)" placeholder="(00) 0000-0000">
                            </div>
                            <div class="form-group col-md-2 mb-3">
                                <label for="ativo_seg">Ativo</label>
                                <select class="form-control" id="ativo_seg" name="ativo_seg">
                                    <option value="s">Sim</option>
                                    <option value="n">Não</option>
                                </select>
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
                            <div class="table-responsive mt-2"><table class="table table-sm table-striped mb-0"><thead><tr><th>Tipo</th><th>Número</th><th>Ramal</th><th>Contato</th><th>P</th><th>Ação</th></tr></thead><tbody id="telefonesTableBody"><tr id="telefonesTableEmpty"><td colspan="6" class="text-muted text-center">Nenhum telefone adicional.</td></tr></tbody></table></div>
                            <div id="telefonesHiddenContainer"></div>
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
                            <div class="table-responsive mt-2"><table class="table table-sm table-striped mb-0"><thead><tr><th>Nome</th><th>Cargo/Setor</th><th>Email</th><th>Telefone</th><th>P</th><th>Ação</th></tr></thead><tbody id="contatosTableBody"><tr id="contatosTableEmpty"><td colspan="6" class="text-muted text-center">Nenhum contato adicional.</td></tr></tbody></table></div>
                            <div id="contatosHiddenContainer"></div>
                        </div>
                        <div class="row">
                            <div class="form-group col-md-3 mb-3">
                                <label for="coord_rh_seg">Coordenador RH</label>
                                <input type="text" class="form-control" id="coord_rh_seg" name="coord_rh_seg"
                                    placeholder="Nome do Coordenador RH">
                            </div>
                            <div class="form-group col-md-3 mb-3">
                                <label for="coordenador_seg">Coordenador</label>
                                <input type="text" class="form-control" id="coordenador_seg" name="coordenador_seg"
                                    placeholder="Nome do Coordenador">
                            </div>
                            <div class="form-group col-md-3 mb-3">
                                <label for="contato_seg">Contato Seguradora</label>
                                <input type="text" class="form-control" id="contato_seg" name="contato_seg"
                                    placeholder="Nome do contato na seguradora">
                            </div>
                            <div class="form-group col-md-3 mb-3">
                                <label for="logo_seg">Logo</label>
                                <input type="file" class="form-control" onclick="novoArquivo()" name="logo_seg"
                                    id="logo_seg" accept="image/png, image/jpeg">
                                <div class="notif-input oculto" id="notifImagem">Tamanho do arquivo inválido!</div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="form-group col-md-3 mb-3">
                                <label for="dias_visita_seg">Dias Visita Clínica</label>
                                <input type="text" class="form-control" id="dias_visita_seg" name="dias_visita_seg"
                                    placeholder="Digite os dias de visita à clínica">
                            </div>
                            <div class="form-group col-md-3 mb-3">
                                <label for="dias_visita_uti_seg">Dias Visita UTI</label>
                                <input type="text" class="form-control" id="dias_visita_uti_seg" name="dias_visita_uti_seg"
                                    placeholder="Digite os dias de visita à UTI">
                            </div>
                            <div class="form-group col-md-3 mb-3">
                                <label for="valor_alto_custo_seg">Valor Alto Custo</label>
                                <input type="text" class="form-control" id="valor_alto_custo_seg"
                                    name="valor_alto_custo_seg" placeholder="Valor alto custo">
                            </div>
                            <div class="form-group col-md-3 mb-3">
                                <label for="longa_permanencia_seg">Longa Permanência</label>
                                <input type="text" class="form-control" id="longa_permanencia_seg"
                                    name="longa_permanencia_seg" placeholder="Longa permanência">
                            </div>
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

    const imagem = document.querySelector("#logo_seg");

    if (imagem) {
        imagem.addEventListener("change", function () {
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
require_once("templates/footer.php");
?>
