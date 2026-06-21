<?php
include_once("check_logado.php");
require_once("globals.php");
require_once("templates/header.php");

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

$nomeSessao = $_SESSION['usuario_user'] ?? '';
$emailSessao = $_SESSION['email_user'] ?? '';
$cargoSessao = $_SESSION['cargo'] ?? '';
$empresaSessao = '';
if ($emailSessao !== '' && strpos(strtolower($emailSessao), '@conex.') !== false) {
    $empresaSessao = 'Conex';
}
$dataHoje = date('Y-m-d');

$norm = function ($txt) {
    $txt = mb_strtolower(trim((string)$txt), 'UTF-8');
    $c = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $txt);
    $txt = $c !== false ? $c : $txt;
    return preg_replace('/[^a-z]/', '', $txt);
};
$isDiretoria = in_array($norm($_SESSION['cargo'] ?? ''), ['diretoria', 'diretor', 'administrador', 'admin', 'board'], true)
    || in_array($norm($_SESSION['nivel'] ?? ''), ['diretoria', 'diretor', 'administrador', 'admin', 'board'], true)
    || ((int)($_SESSION['nivel'] ?? 0) === -1);
?>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/form_cad_internacao.css?v=<?= @filemtime(__DIR__ . '/css/form_cad_internacao.css') ?>">
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

    #main-container.internacao-page .internacao-page__content {
        padding-bottom: 10px;
    }

    #customizacao-form .internacao-card {
        margin-bottom: 8px !important;
        border-color: rgba(47, 111, 159, .20) !important;
        background: linear-gradient(180deg, #ffffff 0%, #f7fbff 100%) !important;
        box-shadow: 0 8px 18px rgba(35, 102, 147, .08) !important;
    }

    #customizacao-form .internacao-card__header {
        padding: 8px 10px 7px !important;
        border-bottom-color: rgba(47, 111, 159, .14) !important;
        background: linear-gradient(90deg, #f2f8fd 0%, #fbfdff 100%) !important;
    }

    #customizacao-form .internacao-card__body {
        padding: 10px !important;
    }

    #customizacao-form .row {
        --bs-gutter-x: .75rem;
        --bs-gutter-y: .55rem;
    }

    #main-container.internacao-page .hero-actions {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
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

    #customizacao-form .form-control,
    #customizacao-form .form-select {
        min-height: 34px;
        border-radius: 8px;
        border: 1px solid #cbd9e8;
        background-color: #fff;
        color: #25364b;
        box-shadow: inset 0 1px 2px rgba(35, 102, 147, .05), 0 1px 0 rgba(255, 255, 255, .75);
        font-size: .82rem;
        font-weight: 500;
        padding-top: 6px;
        padding-bottom: 6px;
    }

    #customizacao-form .form-control:focus,
    #customizacao-form .form-select:focus {
        border-color: #4b8fc0;
        box-shadow: 0 0 0 .15rem rgba(47, 111, 159, .16);
        background-color: #fff;
    }

    #customizacao-form textarea.form-control {
        min-height: 86px;
        border-radius: 8px;
    }

    #customizacao-form .form-label {
        margin-bottom: 4px;
        color: #4c5f76;
        font-size: .74rem;
        font-weight: 800;
    }

    .customizacao-pill {
        border: 1px solid #cfddeb;
        border-radius: 10px;
        padding: 7px 10px;
        display: flex;
        align-items: center;
        gap: 8px;
        background: #ffffff;
        min-height: 34px;
        color: #30445d;
        box-shadow: 0 2px 7px rgba(35, 102, 147, .06);
    }

    .customizacao-pill input {
        margin-top: 0;
        flex: 0 0 auto;
        border-color: #b9c8d8;
    }

    .customizacao-pill:has(input:checked) {
        border-color: #4b8fc0;
        background: #eaf6ff;
        color: #1f5f8f;
    }

    .customizacao-pill .form-check-label {
        font-size: .8rem;
        font-weight: 700;
    }

    .customizacao-subtitle {
        font-size: 0.8rem;
        color: #4c5f76;
    }
</style>

<div class="internacao-page" id="main-container">
    <div class="internacao-page__hero">
        <div>
            <h1>Solicitação de customização</h1>
            <p>Organize o pedido com detalhes claros para agilizar análise, priorização e desenvolvimento.</p>
        </div>
        <div class="hero-actions">
            <?php if ($isDiretoria) { ?>
                <a class="hero-back-btn" href="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/SolicitacaoCustomizacaoList.php', ENT_QUOTES, 'UTF-8') ?>">Ver listagem</a>
            <?php } ?>
            <span class="internacao-page__tag">Campos obrigatórios em destaque</span>
        </div>
    </div>
    <div class="internacao-page__content">
    <form action="<?= $BASE_URL ?>process_solicitacao_customizacao.php" method="POST" enctype="multipart/form-data" class="needs-validation" id="customizacao-form">
        <input type="hidden" name="type" value="create">
        <div class="internacao-card internacao-card--fields mb-3">
            <div class="internacao-card__header">
                <div>
                    <p class="internacao-card__eyebrow">Solicitação</p>
                    <h2 class="internacao-card__title">1. Identificação do solicitante</h2>
                </div>
            </div>
            <div class="internacao-card__body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="nome">Nome</label>
                        <input type="text" class="form-control" id="nome" name="nome" value="<?= htmlspecialchars($nomeSessao) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="empresa">Empresa</label>
                        <input type="text" class="form-control" id="empresa" name="empresa" value="<?= htmlspecialchars($empresaSessao) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="cargo">Cargo</label>
                        <input type="text" class="form-control" id="cargo" name="cargo" value="<?= htmlspecialchars($cargoSessao) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="email">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($emailSessao) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="telefone">Telefone</label>
                        <input type="text" class="form-control" id="telefone" name="telefone">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="data_solicitacao">Data da solicitação</label>
                        <input type="date" class="form-control" id="data_solicitacao" name="data_solicitacao" value="<?= $dataHoje ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="internacao-card internacao-card--fields mb-3">
            <div class="internacao-card__header">
                <div>
                    <p class="internacao-card__eyebrow">Escopo</p>
                    <h2 class="internacao-card__title">2. Módulo a ser customizado</h2>
                </div>
            </div>
            <div class="internacao-card__body">
                <div class="row g-3">
                    <?php foreach ($modules as $module) { ?>
                        <div class="col-md-3">
                            <div class="customizacao-pill">
                                <input class="form-check-input" type="checkbox" name="modulos[]" value="<?= htmlspecialchars($module) ?>" id="modulo_<?= md5($module) ?>">
                                <label class="form-check-label" for="modulo_<?= md5($module) ?>"><?= htmlspecialchars($module) ?></label>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="col-md-12">
                        <label class="form-label customizacao-subtitle" for="modulo_outro">Se marcou Outro, descreva</label>
                        <input type="text" class="form-control" id="modulo_outro" name="modulo_outro">
                    </div>
                </div>
            </div>
        </div>

        <div class="internacao-card internacao-card--fields mb-3">
            <div class="internacao-card__header">
                <div>
                    <p class="internacao-card__eyebrow">Classificação</p>
                    <h2 class="internacao-card__title">3. Tipo de solicitação</h2>
                </div>
            </div>
            <div class="internacao-card__body">
                <div class="row g-3">
                    <?php foreach ($tipos as $tipo) { ?>
                        <div class="col-md-4">
                            <div class="customizacao-pill">
                                <input class="form-check-input" type="checkbox" name="tipos[]" value="<?= htmlspecialchars($tipo) ?>" id="tipo_<?= md5($tipo) ?>">
                                <label class="form-check-label" for="tipo_<?= md5($tipo) ?>"><?= htmlspecialchars($tipo) ?></label>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>

        <div class="internacao-card internacao-card--notes mb-3">
            <div class="internacao-card__header">
                <div>
                    <p class="internacao-card__eyebrow">Detalhamento</p>
                    <h2 class="internacao-card__title">4. Detalhamento</h2>
                </div>
            </div>
            <div class="internacao-card__body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="descricao">Descrição objetiva da necessidade</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="3"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="problema_atual">Como funciona hoje (problema atual)</label>
                        <textarea class="form-control" id="problema_atual" name="problema_atual" rows="3"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="resultado_esperado">Como deve funcionar (resultado esperado)</label>
                        <textarea class="form-control" id="resultado_esperado" name="resultado_esperado" rows="3"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="internacao-card internacao-card--fields mb-3">
            <div class="internacao-card__header">
                <div>
                    <p class="internacao-card__eyebrow">Criticidade</p>
                    <h2 class="internacao-card__title">5. Impacto e prioridade</h2>
                </div>
            </div>
            <div class="internacao-card__body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="impacto_nivel">Impacto se não for feito</label>
                        <select class="form-select" id="impacto_nivel" name="impacto_nivel">
                            <option value="">Selecione</option>
                            <?php foreach ($impactos as $impacto) { ?>
                                <option value="<?= htmlspecialchars($impacto) ?>"><?= htmlspecialchars($impacto) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="descricao_impacto">Descrição do impacto</label>
                        <input type="text" class="form-control" id="descricao_impacto" name="descricao_impacto">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="prioridade">Prioridade</label>
                        <select class="form-select" id="prioridade" name="prioridade">
                            <option value="">Selecione</option>
                            <?php foreach ($prioridades as $prioridade) { ?>
                                <option value="<?= htmlspecialchars($prioridade) ?>"><?= htmlspecialchars($prioridade) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="prazo_desejado">Prazo desejado</label>
                        <input type="date" class="form-control" id="prazo_desejado" name="prazo_desejado">
                    </div>
                </div>
            </div>
        </div>

        <div class="internacao-card internacao-card--fields mb-3">
            <div class="internacao-card__header">
                <div>
                    <p class="internacao-card__eyebrow">Aprovação</p>
                    <h2 class="internacao-card__title">6. Aprovação</h2>
                </div>
            </div>
            <div class="internacao-card__body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="responsavel">Nome do responsável</label>
                        <input type="text" class="form-control" id="responsavel" name="responsavel">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="assinatura">Assinatura</label>
                        <input type="text" class="form-control" id="assinatura" name="assinatura">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="data_aprovacao">Data da aprovação</label>
                        <input type="date" class="form-control" id="data_aprovacao" name="data_aprovacao">
                    </div>
                </div>
            </div>
        </div>

        <div class="internacao-card internacao-card--fields mb-3">
            <div class="internacao-card__header">
                <div>
                    <p class="internacao-card__eyebrow">Arquivos</p>
                    <h2 class="internacao-card__title">7. Anexos</h2>
                </div>
            </div>
            <div class="internacao-card__body">
                <p class="text-muted mb-2">Formatos aceitos: JPG, PNG, PDF, DOC/DOCX.</p>
                <input type="file" class="form-control" name="anexos[]" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
            </div>
        </div>

        <div class="internacao-card internacao-card--fields mb-3">
            <div class="internacao-card__body">
        <div class="alert alert-info d-flex justify-content-between align-items-center mb-0">
            <div>
                <strong>Precisa de apoio?</strong> Fale com o time FullCare Amil no chat interno.
            </div>
            <a class="btn btn-sm btn-primary" href="<?= $BASE_URL ?>show_chat.php">Abrir chat FullCare Amil</a>
        </div>
            </div>
        </div>

        <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-success">Enviar solicitação</button>
        </div>
    </form>
    </div>
</div>

<?php include_once("templates/footer.php"); ?>
