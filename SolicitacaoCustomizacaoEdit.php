<?php
include_once("check_logado.php");
require_once("globals.php");
require_once("templates/header.php");
require_once("dao/solicitacaoCustomizacaoDao.php");

$norm = function ($txt) {
    $txt = mb_strtolower(trim((string)$txt), 'UTF-8');
    $c = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $txt);
    $txt = $c !== false ? $c : $txt;
    return preg_replace('/[^a-z]/', '', $txt);
};
$isDiretoria = in_array($norm($_SESSION['cargo'] ?? ''), ['diretoria', 'diretor', 'administrador', 'admin', 'board'], true)
    || in_array($norm($_SESSION['nivel'] ?? ''), ['diretoria', 'diretor', 'administrador', 'admin', 'board'], true)
    || ((int)($_SESSION['nivel'] ?? 0) === -1);

if (!$isDiretoria) {
    http_response_code(403);
    die('Acesso negado. Requer cargo/nível: Diretoria.');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$dao = new SolicitacaoCustomizacaoDAO($conn, $BASE_URL);
$solicitacao = $id ? $dao->findById($id) : null;
if (!$solicitacao) {
    http_response_code(404);
    die('Solicitação não encontrada.');
}

$emailSessao = strtolower(trim((string)($_SESSION['email_user'] ?? '')));
$isConex = $emailSessao !== '' && strpos($emailSessao, '@conex.') !== false;
$canEditSolicitacao = $isConex;
$canEditResposta = !$isConex;

$modules = ['Internação', 'Paciente', 'Hospital', 'Auditoria', 'Financeiro', 'Relatórios', 'Outro'];
$tipos = [
    'Novo recurso',
    'Alteração de recurso existente',
    'Correção de erro',
    'Integração com outro sistema',
    'Layout/Visual',
    'Relatório/Exportação',
];
$impactos = ['Baixo', 'Médio', 'Alto'];
$prioridades = ['Urgente', 'Alta', 'Média', 'Baixa'];
$statusList = ['Aberto', 'Em análise', 'Resolvido', 'Cancelado'];

$modulosSelecionados = $dao->findModulos($id);
$tiposSelecionados = $dao->findTipos($id);
$anexos = $dao->findAnexos($id);

$resolvidoEmValue = $solicitacao->resolvido_em ? date('Y-m-d\TH:i', strtotime($solicitacao->resolvido_em)) : '';
$hasOrcamento = trim((string)$solicitacao->precificacao) !== '';
?>

<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/form_cad_internacao.css?v=<?= @filemtime(__DIR__ . '/css/form_cad_internacao.css') ?>">
<style>
    #main-container.customizacao-edit-page {
        margin: 2px 0 0 !important;
        padding-inline: 8px !important;
        padding-top: 0 !important;
        width: auto !important;
        max-width: 100% !important;
        overflow-x: hidden;
    }

    #main-container.customizacao-edit-page .internacao-page__hero {
        margin: 0 0 10px !important;
    }

    .customizacao-edit-page .hero-actions {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
    }

    .customizacao-edit-page .hero-back-btn {
        border-radius: 999px;
        border: 1px solid #d9c3f4;
        color: #5e2363;
        padding: 7px 14px;
        text-decoration: none;
        font-weight: 600;
        font-size: .85rem;
        background: #f4ecfb;
    }

    .customizacao-edit-page .hero-back-btn:hover {
        color: #4a1b4e;
        background: #eadcf8;
    }

    .customizacao-edit-page .internacao-page__content {
        display: grid;
        gap: 14px;
    }

    .customizacao-edit-page .edit-form {
        display: grid;
        gap: 14px;
    }

    .customizacao-edit-page .card {
        border: 1px solid #e6dff0;
        border-radius: 18px;
        box-shadow: 0 16px 35px rgba(39, 14, 58, 0.08);
        overflow: hidden;
    }

    .customizacao-edit-page .card-header {
        padding: 14px 18px;
    }

    .customizacao-edit-page .card-header strong {
        font-size: 0.95rem;
        letter-spacing: 0.01em;
    }

    .customizacao-edit-page .card-body {
        padding: 18px;
    }

    .customizacao-edit-page .conex-section {
        background: linear-gradient(180deg, #f7f8fb 0%, #f1f4f8 100%);
        border-color: #d9e0e8;
    }

    .customizacao-edit-page .conex-section .card-header {
        background: #4f555d;
        border-bottom: 0;
    }

    .customizacao-edit-page .conex-section .card-header strong {
        color: #fff;
    }

    .customizacao-edit-page .conex-aprovacao {
        background: linear-gradient(180deg, #eef1f5 0%, #e7ebf1 100%);
    }

    .customizacao-edit-page .conex-aprovacao .card-header {
        background: #3f454d;
    }

    .customizacao-edit-page .fullcare-resposta {
        border-color: #edd9cf;
        background: linear-gradient(180deg, #fff9f5 0%, #fff2e8 100%);
    }

    .customizacao-edit-page .fullcare-resposta .card-header {
        background: #5e2363;
        border-bottom: 0;
    }

    .customizacao-edit-page .fullcare-resposta .card-header strong {
        color: #fff;
    }

    .customizacao-edit-page .form-label {
        font-weight: 600;
        color: #49354d;
        margin-bottom: 6px;
    }

    .customizacao-edit-page .form-control,
    .customizacao-edit-page .form-select {
        min-height: 44px;
        border-radius: 12px;
        border-color: #d9dce3;
    }

    .customizacao-edit-page textarea.form-control {
        min-height: 110px;
    }

    .customizacao-edit-page .form-check {
        display: flex;
        align-items: center;
        gap: 10px;
        min-height: 44px;
        margin-bottom: 0;
    }

    .customizacao-edit-page .form-check-input {
        margin: 0;
        flex: 0 0 auto;
    }

    .customizacao-edit-page .form-check-label {
        margin: 0;
        font-weight: 500;
        color: #2f3541;
    }

    .customizacao-edit-page .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding-top: 4px;
    }

    @media (max-width: 991.98px) {
        .customizacao-edit-page .card-body {
            padding: 16px;
        }
    }
</style>

<div class="internacao-page customizacao-edit-page" id="main-container">
    <div class="internacao-page__hero">
        <div>
            <h1>Editar Solicitação #<?= (int)$solicitacao->id_solicitacao ?></h1>
            <p>Atualize a solicitação e finalize quando estiver resolvida.</p>
        </div>
        <div class="hero-actions">
            <a class="hero-back-btn" href="<?= $BASE_URL ?>SolicitacaoCustomizacaoList.php">Voltar à lista</a>
        </div>
    </div>

    <div class="internacao-page__content">
    <form action="<?= $BASE_URL ?>process_solicitacao_customizacao.php" method="POST" enctype="multipart/form-data" class="needs-validation edit-form">
        <input type="hidden" name="type" value="update">
        <input type="hidden" name="id_solicitacao" value="<?= (int)$solicitacao->id_solicitacao ?>">
        <input type="hidden" name="fk_usuario_solicitante" value="<?= (int)$solicitacao->fk_usuario_solicitante ?>">
        <input type="hidden" name="resolvido_por" value="<?= (int)$solicitacao->resolvido_por ?>">

        <div class="card mb-4 conex-section conex-aprovacao">
            <div class="card-header">
                <strong>1. Identificação do solicitante</strong>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="nome">Nome</label>
                        <input type="text" class="form-control" id="nome" name="nome" value="<?= htmlspecialchars($solicitacao->nome) ?>" <?= $canEditSolicitacao ? '' : 'readonly' ?> required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="empresa">Empresa</label>
                        <input type="text" class="form-control" id="empresa" name="empresa" value="<?= htmlspecialchars($solicitacao->empresa) ?>" <?= $canEditSolicitacao ? '' : 'readonly' ?>>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="cargo">Cargo</label>
                        <input type="text" class="form-control" id="cargo" name="cargo" value="<?= htmlspecialchars($solicitacao->cargo) ?>" <?= $canEditSolicitacao ? '' : 'readonly' ?>>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="email">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($solicitacao->email) ?>" <?= $canEditSolicitacao ? '' : 'readonly' ?>>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="telefone">Telefone</label>
                        <input type="text" class="form-control" id="telefone" name="telefone" value="<?= htmlspecialchars($solicitacao->telefone) ?>" <?= $canEditSolicitacao ? '' : 'readonly' ?>>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="data_solicitacao">Data da solicitação</label>
                        <input type="date" class="form-control" id="data_solicitacao" name="data_solicitacao" value="<?= htmlspecialchars($solicitacao->data_solicitacao) ?>" <?= $canEditSolicitacao ? '' : 'readonly' ?>>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4 conex-section">
            <div class="card-header">
                <strong>2. Módulo a ser customizado</strong>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach ($modules as $module) { ?>
                        <?php $checked = in_array($module, $modulosSelecionados, true) ? 'checked' : ''; ?>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="modulos[]" value="<?= htmlspecialchars($module) ?>" id="modulo_<?= md5($module) ?>" <?= $checked ?> <?= $canEditSolicitacao ? '' : 'disabled' ?>>
                                <label class="form-check-label" for="modulo_<?= md5($module) ?>"><?= htmlspecialchars($module) ?></label>
                            </div>
                            <?php if (!$canEditSolicitacao && $checked) { ?>
                                <input type="hidden" name="modulos[]" value="<?= htmlspecialchars($module) ?>">
                            <?php } ?>
                        </div>
                    <?php } ?>
                    <div class="col-md-6">
                        <label class="form-label" for="modulo_outro">Outro (especificar)</label>
                        <input type="text" class="form-control" id="modulo_outro" name="modulo_outro" value="<?= htmlspecialchars($solicitacao->modulo_outro) ?>" <?= $canEditSolicitacao ? '' : 'readonly' ?>>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4 conex-section">
            <div class="card-header">
                <strong>3. Tipo de solicitação</strong>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach ($tipos as $tipo) { ?>
                        <?php $checked = in_array($tipo, $tiposSelecionados, true) ? 'checked' : ''; ?>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="tipos[]" value="<?= htmlspecialchars($tipo) ?>" id="tipo_<?= md5($tipo) ?>" <?= $checked ?> <?= $canEditSolicitacao ? '' : 'disabled' ?>>
                                <label class="form-check-label" for="tipo_<?= md5($tipo) ?>"><?= htmlspecialchars($tipo) ?></label>
                            </div>
                            <?php if (!$canEditSolicitacao && $checked) { ?>
                                <input type="hidden" name="tipos[]" value="<?= htmlspecialchars($tipo) ?>">
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>

        <div class="card mb-4 conex-section">
            <div class="card-header">
                <strong>4. Detalhamento</strong>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="descricao">Descrição objetiva da necessidade</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="3" <?= $canEditSolicitacao ? '' : 'readonly' ?>><?= htmlspecialchars($solicitacao->descricao) ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="problema_atual">Como funciona hoje (problema atual)</label>
                        <textarea class="form-control" id="problema_atual" name="problema_atual" rows="3" <?= $canEditSolicitacao ? '' : 'readonly' ?>><?= htmlspecialchars($solicitacao->problema_atual) ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="resultado_esperado">Como deve funcionar (resultado esperado)</label>
                        <textarea class="form-control" id="resultado_esperado" name="resultado_esperado" rows="3" <?= $canEditSolicitacao ? '' : 'readonly' ?>><?= htmlspecialchars($solicitacao->resultado_esperado) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4 conex-section">
            <div class="card-header">
                <strong>5. Impacto e prioridade</strong>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="impacto_nivel">Impacto se não for feito</label>
                        <select class="form-select" id="impacto_nivel" name="impacto_nivel" <?= $canEditSolicitacao ? '' : 'disabled' ?>>
                            <option value="">Selecione</option>
                            <?php foreach ($impactos as $impacto) { ?>
                                <option value="<?= htmlspecialchars($impacto) ?>" <?= $solicitacao->impacto_nivel === $impacto ? 'selected' : '' ?>><?= htmlspecialchars($impacto) ?></option>
                            <?php } ?>
                        </select>
                        <?php if (!$canEditSolicitacao) { ?>
                            <input type="hidden" name="impacto_nivel" value="<?= htmlspecialchars($solicitacao->impacto_nivel) ?>">
                        <?php } ?>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="descricao_impacto">Descrição do impacto</label>
                        <input type="text" class="form-control" id="descricao_impacto" name="descricao_impacto" value="<?= htmlspecialchars($solicitacao->descricao_impacto) ?>" <?= $canEditSolicitacao ? '' : 'readonly' ?>>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="prioridade">Prioridade</label>
                        <select class="form-select" id="prioridade" name="prioridade" <?= $canEditSolicitacao ? '' : 'disabled' ?>>
                            <option value="">Selecione</option>
                            <?php foreach ($prioridades as $prioridade) { ?>
                                <option value="<?= htmlspecialchars($prioridade) ?>" <?= $solicitacao->prioridade === $prioridade ? 'selected' : '' ?>><?= htmlspecialchars($prioridade) ?></option>
                            <?php } ?>
                        </select>
                        <?php if (!$canEditSolicitacao) { ?>
                            <input type="hidden" name="prioridade" value="<?= htmlspecialchars($solicitacao->prioridade) ?>">
                        <?php } ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="prazo_desejado">Prazo desejado</label>
                        <input type="date" class="form-control" id="prazo_desejado" name="prazo_desejado" value="<?= htmlspecialchars($solicitacao->prazo_desejado) ?>" <?= $canEditSolicitacao ? '' : 'readonly' ?>>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4 conex-section">
            <div class="card-header">
                <strong>6. Aprovação</strong>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="responsavel">Nome do responsável</label>
                        <input type="text" class="form-control" id="responsavel" name="responsavel" value="<?= htmlspecialchars($solicitacao->responsavel) ?>" <?= $canEditSolicitacao ? '' : 'readonly' ?>>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="assinatura">Assinatura</label>
                        <input type="text" class="form-control" id="assinatura" name="assinatura" value="<?= htmlspecialchars($solicitacao->assinatura) ?>" <?= $canEditSolicitacao ? '' : 'readonly' ?>>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="data_aprovacao">Data da aprovação</label>
                        <input type="date" class="form-control" id="data_aprovacao" name="data_aprovacao" value="<?= htmlspecialchars($solicitacao->data_aprovacao) ?>" <?= $canEditSolicitacao ? '' : 'readonly' ?>>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4 conex-section">
            <div class="card-header">
                <strong>7. Anexos</strong>
            </div>
            <div class="card-body">
                <p class="text-muted mb-2">Formatos aceitos: JPG, PNG, PDF, DOC/DOCX.</p>
                <input type="file" class="form-control" name="anexos[]" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" <?= $canEditSolicitacao ? '' : 'disabled' ?>>

                <?php if ($anexos) { ?>
                    <div class="mt-3">
                        <h6>Anexos existentes</h6>
                        <?php foreach ($anexos as $anexo) { ?>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <a href="<?= $BASE_URL . htmlspecialchars($anexo['caminho_arquivo']) ?>" target="_blank">
                                    <?= htmlspecialchars($anexo['nome_original']) ?>
                                </a>
                                <?php if ($canEditSolicitacao) { ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="remover_anexo[]" value="<?= (int)$anexo['id_anexo'] ?>" id="remover_<?= (int)$anexo['id_anexo'] ?>">
                                        <label class="form-check-label" for="remover_<?= (int)$anexo['id_anexo'] ?>">Remover</label>
                                    </div>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        </div>

        <div class="card mb-4 conex-section">
            <div class="card-header">
                <strong>8. Aprovação Conex (orçamento)</strong>
            </div>
            <div class="card-body">
                <?php if (!$hasOrcamento) { ?>
                    <div class="alert alert-secondary mb-3">
                        Aguardando orçamento da FullCare para liberar a aprovação.
                    </div>
                <?php } ?>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="aprovacao_conex">Aprovado</label>
                        <select class="form-select" id="aprovacao_conex" name="aprovacao_conex" <?= $canEditSolicitacao && $hasOrcamento ? '' : 'disabled' ?>>
                            <option value="">Selecione</option>
                            <option value="Sim" <?= $solicitacao->aprovacao_conex === 'Sim' ? 'selected' : '' ?>>Sim</option>
                            <option value="Não" <?= $solicitacao->aprovacao_conex === 'Não' ? 'selected' : '' ?>>Não</option>
                        </select>
                        <?php if (!$canEditSolicitacao || !$hasOrcamento) { ?>
                            <input type="hidden" name="aprovacao_conex" value="<?= htmlspecialchars($solicitacao->aprovacao_conex) ?>">
                        <?php } ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="data_aprovacao_conex">Data</label>
                        <input type="date" class="form-control" id="data_aprovacao_conex" name="data_aprovacao_conex" value="<?= htmlspecialchars($solicitacao->data_aprovacao_conex) ?>" <?= $canEditSolicitacao && $hasOrcamento ? '' : 'readonly' ?>>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="responsavel_aprovacao_conex">Responsável</label>
                        <input type="text" class="form-control" id="responsavel_aprovacao_conex" name="responsavel_aprovacao_conex" value="<?= htmlspecialchars($solicitacao->responsavel_aprovacao_conex) ?>" <?= $canEditSolicitacao && $hasOrcamento ? '' : 'readonly' ?>>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4 fullcare-resposta">
            <div class="card-header">
                <strong>9. Resposta FullCare</strong>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="prazo_resposta">Prazo estimado</label>
                        <input type="text" class="form-control" id="prazo_resposta" name="prazo_resposta" value="<?= htmlspecialchars($solicitacao->prazo_resposta) ?>" <?= $canEditResposta ? '' : 'readonly' ?>>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="precificacao">Precificação / estimativa de custo</label>
                        <input type="text" class="form-control" id="precificacao" name="precificacao" value="<?= htmlspecialchars($solicitacao->precificacao) ?>" <?= $canEditResposta ? '' : 'readonly' ?>>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="observacoes_resposta">Observações / ajustes propostos</label>
                        <textarea class="form-control" id="observacoes_resposta" name="observacoes_resposta" rows="3" <?= $canEditResposta ? '' : 'readonly' ?>><?= htmlspecialchars($solicitacao->observacoes_resposta) ?></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="aprovacao_resposta">Aprovação final</label>
                        <input type="text" class="form-control" id="aprovacao_resposta" name="aprovacao_resposta" value="<?= htmlspecialchars($solicitacao->aprovacao_resposta) ?>" <?= $canEditResposta ? '' : 'readonly' ?>>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="data_resposta">Data da resposta</label>
                        <input type="date" class="form-control" id="data_resposta" name="data_resposta" value="<?= htmlspecialchars($solicitacao->data_resposta) ?>" <?= $canEditResposta ? '' : 'readonly' ?>>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status" <?= $canEditResposta ? '' : 'disabled' ?>>
                            <?php foreach ($statusList as $statusItem) { ?>
                                <option value="<?= htmlspecialchars($statusItem) ?>" <?= $solicitacao->status === $statusItem ? 'selected' : '' ?>><?= htmlspecialchars($statusItem) ?></option>
                            <?php } ?>
                        </select>
                        <?php if (!$canEditResposta) { ?>
                            <input type="hidden" name="status" value="<?= htmlspecialchars($solicitacao->status) ?>">
                        <?php } ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="resolvido_em">Data/Hora de resolução</label>
                        <input type="datetime-local" class="form-control" id="resolvido_em" name="resolvido_em" value="<?= htmlspecialchars($resolvidoEmValue) ?>" <?= $canEditResposta ? '' : 'readonly' ?>>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="versao_sistema">Versão do sistema</label>
                        <input type="text" class="form-control" id="versao_sistema" name="versao_sistema" value="<?= htmlspecialchars($solicitacao->versao_sistema) ?>" <?= $canEditResposta ? '' : 'readonly' ?>>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-success">Salvar alterações</button>
        </div>
    </form>
    </div>
</div>

<?php include_once("templates/footer.php"); ?>
