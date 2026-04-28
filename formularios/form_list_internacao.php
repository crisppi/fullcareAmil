<?php
$start = microtime(true); // Marca o início da execução da página
ob_start(); // Output Buffering Start

require_once("templates/header.php");
require_once("models/message.php");

include_once("models/internacao.php");
include_once("dao/internacaoDao.php");

include_once("models/patologia.php");
include_once("dao/patologiaDao.php");

include_once("models/paciente.php");
include_once("dao/pacienteDao.php");

include_once("models/gestao.php");
include_once("dao/gestaoDao.php");

include_once("models/visita.php");
include_once("dao/visitaDao.php");

include_once("models/hospital.php");
include_once("dao/hospitalDao.php");
include_once("dao/hospitalUserDao.php");

include_once("models/pagination.php");

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!function_exists('internacaoGetParam')) {
    function internacaoGetParam(string $longKey, $default = null)
    {
        static $shortToLong = [
            'hosp' => 'pesquisa_nome',
            'pac'  => 'pesquisa_pac',
            'seg'  => 'pesquisa_seguradora',
            'mat'  => 'pesquisa_matricula',
            'sn'   => 'senha_int',
            'pp'   => 'limite_pag',
            'di'   => 'data_intern_int',
            'df'   => 'data_intern_int_max',
            'it'   => 'pesqInternado',
            'ss'   => 'sem_senha',
            'sf'   => 'sort_field',
            'sd'   => 'sort_dir',
            'pg'   => 'pag',
            'blc'  => 'bl',
        ];
        static $longToShort = null;
        if ($longToShort === null) {
            $longToShort = array_flip($shortToLong);
        }

        $value = $_GET[$longKey] ?? null;
        if ($value === null && isset($longToShort[$longKey])) {
            $value = $_GET[$longToShort[$longKey]] ?? null;
        }

        if ($value === null || $value === '') {
            return $default;
        }
        return $value;
    }
}

if (!function_exists('internacaoCompactQueryParams')) {
    function internacaoCompactQueryParams(array $params): array
    {
        $defaults = [
            'pesqInternado' => 's',
            'sem_senha'     => '0',
            'sort_dir'      => 'desc',
            'limite_pag'    => '10',
        ];
        $longToShort = [
            'pesquisa_nome'       => 'hosp',
            'pesquisa_pac'        => 'pac',
            'pesquisa_seguradora' => 'seg',
            'pesquisa_matricula'  => 'mat',
            'senha_int'           => 'sn',
            'limite_pag'          => 'pp',
            'data_intern_int'     => 'di',
            'data_intern_int_max' => 'df',
            'pesqInternado'       => 'it',
            'sem_senha'           => 'ss',
            'sort_field'          => 'sf',
            'sort_dir'            => 'sd',
            'pag'                 => 'pg',
            'bl'                  => 'blc',
        ];

        $clean = [];
        foreach ($params as $key => $value) {
            if ($value === null || $value === '' || $value === false) {
                continue;
            }
            $value = (string)$value;
            if (isset($defaults[$key]) && $defaults[$key] === $value) {
                continue;
            }
            $clean[$key] = $value;
        }

        if (empty($clean['data_intern_int'])) {
            unset($clean['data_intern_int_max']);
        }
        if (empty($clean['sort_field'])) {
            unset($clean['sort_dir']);
        }

        $compact = [];
        foreach ($clean as $key => $value) {
            $compact[$longToShort[$key] ?? $key] = $value;
        }

        return $compact;
    }
}
$normCargoAccess = function ($txt) {
    $txt = mb_strtolower(trim((string)$txt), 'UTF-8');
    $c = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $txt);
    $txt = $c !== false ? $c : $txt;
    return preg_replace('/[^a-z]/', '', $txt);
};
$isGestorSeguradora = (strpos($normCargoAccess($_SESSION['cargo'] ?? ''), 'seguradora') !== false);
$seguradoraUserId = isset($_SESSION['fk_seguradora_user']) ? (int)$_SESSION['fk_seguradora_user'] : 0;
if ($isGestorSeguradora && $seguradoraUserId <= 0) {
    try {
        $uid = (int)($_SESSION['id_usuario'] ?? 0);
        if ($uid > 0) {
            $stmtSeg = $conn->prepare("SELECT fk_seguradora_user FROM tb_user WHERE id_usuario = :id LIMIT 1");
            $stmtSeg->bindValue(':id', $uid, PDO::PARAM_INT);
            $stmtSeg->execute();
            $seguradoraUserId = (int)($stmtSeg->fetchColumn() ?: 0);
            if ($seguradoraUserId > 0) {
                $_SESSION['fk_seguradora_user'] = $seguradoraUserId;
            }
        }
    } catch (Throwable $e) {
        error_log('[LIST_INT][SEGURADORA] ' . $e->getMessage());
    }
}
$seguradoraUserNome = '';
if ($isGestorSeguradora && $seguradoraUserId > 0) {
    try {
        $stmtSegNome = $conn->prepare("SELECT seguradora_seg FROM tb_seguradora WHERE id_seguradora = :id LIMIT 1");
        $stmtSegNome->bindValue(':id', $seguradoraUserId, PDO::PARAM_INT);
        $stmtSegNome->execute();
        $seguradoraUserNome = (string)($stmtSegNome->fetchColumn() ?: '');
    } catch (Throwable $e) {
        $seguradoraUserNome = '';
    }
}

// inicializacao de variaveis
$data_intern_int      = null;
$order                = null;
$obLimite             = null;
$blocoNovo            = null;
$senha_int            = null;
$where                = null;

$Internacao_geral = new internacaoDAO($conn, $BASE_URL);
$Internacaos      = $Internacao_geral->findGeral();

$pacienteDao = new pacienteDAO($conn, $BASE_URL);
$gestaoDao   = new gestaoDAO($conn, $BASE_URL);

$limite  = internacaoGetParam('limite_pag', 10);
$ordenar = internacaoGetParam('ordenar', 1);
$sortField = trim((string)internacaoGetParam('sort_field', ''));
$sortDir   = strtolower((string)internacaoGetParam('sort_dir', 'desc'));
$sortDir   = $sortDir === 'asc' ? 'asc' : 'desc';
$onlySemSenhaParam = (string)internacaoGetParam('sem_senha', '');
$onlySemSenha = in_array($onlySemSenhaParam, ['1', 1, 'true', 'on'], true);

$hospital_geral     = new HospitalDAO($conn, $BASE_URL);
$patologiaDao       = new patologiaDAO($conn, $BASE_URL);
$visitaDao          = new visitaDAO($conn, $BASE_URL);
$internacao         = new internacaoDAO($conn, $BASE_URL);
$hospitalUserDao    = new hospitalUserDAO($conn, $BASE_URL);

$hospitalOptions = [];
try {
    $nivelSessao     = (int) ($_SESSION['nivel'] ?? 0);
    $usuarioSessaoId = (int) ($_SESSION['id_usuario'] ?? 0);
    $rawHospitais    = [];

    if ($nivelSessao >= 4) {
        $rawHospitais = $hospital_geral->findGeral();
    } elseif ($hospitalUserDao && $usuarioSessaoId) {
        $rawHospitais = $hospitalUserDao->listarPorUsuario($usuarioSessaoId);
        if (!is_array($rawHospitais) || !count($rawHospitais)) {
            $rawHospitais = $hospital_geral->findGeral();
        }
    } else {
        $rawHospitais = $hospital_geral->findGeral();
    }

    if (is_array($rawHospitais)) {
        foreach ($rawHospitais as $hospitalRow) {
            $nome = trim((string) ($hospitalRow['nome_hosp'] ?? ''));
            if ($nome && !isset($hospitalOptions[$nome])) {
                $hospitalOptions[$nome] = $nome;
            }
        }
        if ($hospitalOptions) {
            ksort($hospitalOptions, SORT_NATURAL | SORT_FLAG_CASE);
        }
    }
} catch (Throwable $th) {
    $hospitalOptions = [];
}

$seguradoraOptions = [];
try {
    $stmtSegOptions = $conn->query("SELECT seguradora_seg FROM tb_seguradora WHERE seguradora_seg IS NOT NULL AND seguradora_seg <> '' ORDER BY seguradora_seg");
    $rawSeguradoras = $stmtSegOptions->fetchAll(PDO::FETCH_COLUMN) ?: [];
    foreach ($rawSeguradoras as $nomeSeg) {
        $nome = trim((string)$nomeSeg);
        if ($nome !== '' && !isset($seguradoraOptions[$nome])) {
            $seguradoraOptions[$nome] = $nome;
        }
    }
} catch (Throwable $th) {
    $seguradoraOptions = [];
}
?>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">

<style>
.internacao-list-page {
    padding: 8px 6px 20px;
}

.internacao-list-hero {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 14px;
}

.internacao-list-hero__copy {
    min-width: 0;
}

.internacao-list-kicker {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
    color: #7b5a9a;
    font-size: .76rem;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.internacao-list-kicker::before {
    content: "";
    width: 18px;
    height: 2px;
    border-radius: 999px;
    background: currentColor;
}

.internacao-list-title {
    margin: 0;
    color: #2d203d;
    font-size: 2rem;
    font-weight: 800;
    letter-spacing: -.03em;
}

.internacao-list-subtitle {
    margin: 6px 0 0;
    color: #7b7b8d;
    font-size: .95rem;
}

.internacao-list-hero__actions {
    display: flex;
    align-items: center;
    align-self: flex-end;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: flex-end;
    padding-bottom: 4px;
}

.btn-list-top {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 44px;
    border: none;
    border-radius: 14px;
    padding: 0 18px;
    font-weight: 700;
    box-shadow: 0 12px 22px rgba(46, 27, 78, 0.12);
    line-height: 1;
    text-align: center;
    white-space: nowrap;
}

.btn-list-top.btn-export {
    background: linear-gradient(135deg, #3d915b 0%, #58ab74 100%);
}

.btn-list-top.btn-new {
    background: linear-gradient(135deg, #35bae1 0%, #5dc8ea 100%);
}

.complete-table {
    border-radius: 22px;
    border: 1px solid rgba(94, 35, 99, 0.10);
    background: linear-gradient(180deg, rgba(255,255,255,0.98) 0%, rgba(248,244,253,0.96) 100%);
    box-shadow: 0 16px 34px rgba(45, 18, 70, 0.08);
    padding: 14px 14px 10px;
    overflow: hidden;
}

.table-filters {
    padding: 0;
}

/* Chips roxos para seleção de campos (modal export) */
/* Pills lilás maiores, com ícones brancos */
.export-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 6px 20px;
    /* mais “gordinho” */
    border-radius: 999px;
    background-color: #5e2363;
    /* roxo cheio */
    color: #ffffff;
    /* texto branco */
    font-size: 0.95rem;
    /* fonte um pouco maior */
    font-weight: 600;
    border: none;
    cursor: pointer;
    margin: 6px 8px 6px 0;
    white-space: nowrap;
}

/* Estado desativado (contorno) */
.export-pill.inactive {
    background-color: #ffffff;
    color: #5e2363;
    border: 1px solid #5e2363;
}

/* Ícones sempre brancos nas pills ativas */
.export-pill i {
    color: #ffffff;
    /* ícones brancos */
    font-size: 1rem;
    /* maior que antes */
}

/* Ícones roxos quando a pill está desativada */
.export-pill.inactive i {
    color: #5e2363;
}

.th-sortable {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.th-sortable .sort-icons a {
    text-decoration: none;
    font-size: 0.85rem;
    color: #ffffff;
    margin-left: 2px;
    opacity: 0.7;
}

.th-sortable .sort-icons a.active {
    color: #ffd966;
    opacity: 1;
    font-weight: bold;
}


.export-pill-toolbar {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 8px;
    margin-bottom: 4px;
}

.export-pill-toolbar button {
    font-size: 0.8rem;
    padding: 2px 10px;
    border-radius: 999px;
}

.modal-backdrop {
    display: none;
}

.modal {
    background: rgba(0, 0, 0, 0.5);
}

.modal-header.modal-header-blue {
    color: white;
    background: #35bae1;
}

.filter-intel-wrapper {
    border: 1px solid #e7dcf1;
    border-radius: 20px;
    padding: 16px 18px;
    margin-bottom: 14px;
    background:
        radial-gradient(circle at top right, rgba(83, 196, 226, 0.10), transparent 26%),
        linear-gradient(180deg, #fefcff 0%, #f8f3fd 100%);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.75);
}

.filter-intel-wrapper h6 {
    font-weight: 800;
    color: #5e2363;
    margin-bottom: 8px;
    font-size: 1rem;
}

.filter-intel-wrapper small {
    color: #7a6b84;
    display: block;
}

.filter-intel-grid {
    display: flex;
    flex-wrap: nowrap;
    gap: 12px;
    align-items: center;
}

.filter-intel-grid .smart-search-group {
    flex: 1;
    min-width: 220px;
}

.filter-intel-grid label {
    font-size: .82rem;
    font-weight: 700;
    color: #7a6b84;
}

.filter-intel-grid .input-group {
    display: flex;
    gap: 6px;
}

.filter-intel-grid .input-group .form-control,
.filter-intel-grid .input-group .btn {
    min-height: 42px;
    border-radius: 12px !important;
}

.filter-intel-grid .input-group .btn {
    padding-inline: 16px;
    font-weight: 700;
}

.smart-search-feedback {
    display: none;
    margin-top: 6px;
    border-radius: 8px;
    padding: 6px 10px;
    font-size: .78rem;
    font-weight: 600;
}

.smart-search-feedback.is-error {
    display: block;
    color: #9c1d3d;
    background: #ffeef3;
    border: 1px solid #f3bccb;
}

.scope-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 12px;
    padding: 8px 14px;
    border-radius: 999px;
    font-size: 0.82rem;
    font-weight: 700;
    background: #f3edff;
    border: 1px solid #d6c5f7;
    color: #5e2363;
}

.filter-intel-grid input[type="text"] {
    flex: 1;
}

.filter-memory-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-left: auto;
}

@media (max-width: 991.98px) {
    .filter-intel-grid {
        flex-wrap: wrap;
        align-items: flex-end;
    }
    .filter-memory-actions {
        margin-left: 0;
    }
}

.filter-memory-actions button {
    border-radius: 999px;
    font-size: .82rem;
    font-weight: 600;
    border: 1px solid #bfa3d1;
    background: #fff;
    color: #5e2363;
    padding: 6px 14px;
    transition: all .15s ease;
}

.filter-memory-actions button:hover {
    background: #5e2363;
    color: #fff;
}

.filter-inline-row {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 8px;
    padding: 12px 14px;
    border-radius: 18px;
    background: #fff;
    border: 1px solid #ece4f4;
    box-shadow: inset 0 1px 0 rgba(255,255,255,.9);
}

.filter-inline-row > .filter-inline-field {
    flex: 1 1 0;
    min-width: 80px;
}

.filter-inline-row > .filter-inline-field:last-child {
    flex: 0 0 auto;
}

.filter-inline--wide {
    min-width: 140px;
}

.filter-inline--date {
    min-width: 120px;
}

.filter-inline--short {
    min-width: 100px;
}

.filter-inline--icon {
    min-width: 48px;
    flex: 0 0 auto !important;
}

.filter-inline-actions {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.filter-inline-row .form-control,
.filter-inline-row .form-control-sm,
.filter-inline-row .btn {
    min-height: 42px;
    border-radius: 12px;
}

.filter-inline-row .form-control,
.filter-inline-row .form-control-sm {
    border-color: #ddd6e7;
}

.btn-filtro-buscar {
    box-shadow: 0 10px 18px rgba(94, 35, 99, 0.18);
}

@media (max-width: 1199.98px) {
    .filter-inline-row {
        flex-wrap: wrap;
    }
}

.filter-favorites {
    margin-top: 10px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.filter-favorite-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border-radius: 999px;
    padding: 4px 12px;
    font-size: .78rem;
    font-weight: 600;
    border: 1px solid #ffcad9;
    color: #a03a5e;
    background: #fff5f8;
    cursor: pointer;
}

.filter-favorite-chip .remove {
    font-size: .75rem;
    color: #c24360;
    cursor: pointer;
}

.filter-favorite-chip .remove:hover {
    color: #8a1433;
}

.filter-empty-hint {
    font-size: .78rem;
    color: #a690b3;
}

#table-content {
    margin-top: 12px !important;
    border-radius: 18px;
    overflow: hidden;
    border: 1px solid #ebe3f3;
    background: #fff;
}

#table-content .table {
    margin-bottom: 0;
}

#table-content thead th {
    padding-top: 14px;
    padding-bottom: 14px;
    background: linear-gradient(90deg, #5e2363 0%, #69407f 100%);
    border-bottom: none;
    color: #fff;
    font-size: .76rem;
    font-weight: 800;
    letter-spacing: .04em;
    text-transform: uppercase;
    vertical-align: middle;
}

#table-content tbody td {
    padding-top: 13px;
    padding-bottom: 13px;
    vertical-align: middle;
    border-top: 1px solid #f1ebf7;
}

#table-content tbody tr:nth-child(even) {
    background: #fbf8fe;
}

#table-content tbody tr:hover {
    background: #f3ecfb !important;
}

.fc-list-action .dropdown-toggle {
    border: 1px solid #dccceb;
    border-radius: 12px;
    min-width: 42px;
    min-height: 38px;
    background: #fff;
}

@media (max-width: 991.98px) {
    .internacao-list-hero {
        flex-direction: column;
    }

    .internacao-list-hero__actions {
        width: 100%;
        justify-content: flex-start;
        align-self: auto;
        padding-bottom: 0;
    }
}
</style>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- SHIM do selectpicker: impede o erro mesmo se alguém chamar .selectpicker() -->
<script>
if (typeof jQuery !== 'undefined') {
    (function($) {
        if (!$.fn.selectpicker) {
            $.fn.selectpicker = function() {
                // não faz nada, só evita erro
                return this;
            };
        }
    })(jQuery);
}
</script>

<!-- <script src="js/ajaxNav.js"></script> -->

<!-- FORMULARIO DE PESQUISAS -->
<div class="container-fluid internacao-list-page" id='main-container'>

    <div class="internacao-list-hero">
        <div class="internacao-list-hero__copy">
            <div class="internacao-list-kicker">Internações</div>
            <h1 class="internacao-list-title"><?= $onlySemSenha ? 'Internações com senha pendente' : 'Listagem de internações' ?></h1>
            <p class="internacao-list-subtitle">Filtre por hospital, paciente, seguradora, datas e atalhos de memória sem perder contexto da operação.</p>
        </div>

        <?php
        // valores default para montagem de URL / filtros
        $busca               = $busca               ?? '';
        $busca_user          = $busca_user          ?? '';
        $ordenar             = $ordenar             ?? 1;
        $limite              = $limite              ?? 10;
        $senha_int           = $senha_int           ?? '';
        $data_intern_int     = $data_intern_int     ?? '';
        $data_intern_int_max = $data_intern_int_max ?? '';
        $pesquisa_seguradora = $pesquisa_seguradora ?? '';
        ?>

        <div class="internacao-list-hero__actions">
            <!-- Botão de Exportar para Excel (abre modal) -->
            <a href="#" id="btn-exportar-excel" class="btn btn-success btn-list-top btn-export">
                Exportar para Excel
            </a>

            <!-- Botão de Nova Internação -->
            <a class="btn btn-success btn-list-top btn-new" href="<?= $BASE_URL ?>internacoes/nova">
                <i class="fas fa-plus" style="font-size:1rem;margin-right:5px;"></i>
                Nova Internação
            </a>
        </div>
    </div>

    <div class="complete-table">
        <?php if ($isGestorSeguradora): ?>
            <div class="scope-badge">
                Escopo: Seguradora <?= htmlspecialchars($seguradoraUserNome !== '' ? $seguradoraUserNome : ('#' . $seguradoraUserId), ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        <div id="navbarToggleExternalContent" class="table-filters">
            <form action="" id="select-internacao-form" method="GET">
                <?php
                $pesquisa_nome       = internacaoGetParam('pesquisa_nome', '');
                $pesqInternado       = internacaoGetParam('pesqInternado', 's');
                $limite              = internacaoGetParam('limite_pag', 10);
                $pesquisa_pac        = internacaoGetParam('pesquisa_pac', '');
                $pesquisa_seguradora = internacaoGetParam('pesquisa_seguradora', '');
                $pesquisa_matricula  = internacaoGetParam('pesquisa_matricula', '');
                $ordenar             = internacaoGetParam('ordenar', '');
                $data_intern_int     = internacaoGetParam('data_intern_int') ?: null;
                $data_intern_int_max = internacaoGetParam('data_intern_int_max') ?: null;
                $senha_int           = internacaoGetParam('senha_int') ?: null;
                if ($isGestorSeguradora) {
                    $pesquisa_seguradora = $seguradoraUserNome !== '' ? $seguradoraUserNome : $pesquisa_seguradora;
                }
                ?>
                <div class="filter-intel-wrapper">
                    <h6>Memória de filtros e busca inteligente</h6>
                    <div class="filter-intel-grid">
                        <div class="smart-search-group">
                            <label for="smartSearchPhrase">Busca em linguagem natural</label>
                            <div class="input-group">
                                <input type="text" id="smartSearchPhrase" class="form-control form-control-sm"
                                    placeholder='Ex.: "contas Einstein outubro 2023" ou "paciente Ana maio"'>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnApplySmartSearch">
                                    Aplicar frase
                                </button>
                            </div>
                            <div id="smartSearchFeedback" class="smart-search-feedback" role="alert" aria-live="polite"></div>
                            <small>Tente combinar hospital, paciente, seguradora, mês/ano ou senha em uma frase única.</small>
                        </div>
                        <div class="filter-memory-actions">
                            <button type="button" id="btnApplyLastFilter">Aplicar último filtro</button>
                            <button type="button" id="btnSaveFavFilter">Salvar como favorito</button>
                            <button type="button" id="btnClearFilters" class="btn-filtro-limpar">Limpar filtros</button>
                        </div>
                    </div>
                    <div class="filter-favorites" id="filterFavorites"></div>
                    <div class="filter-empty-hint" id="filterFavoritesHint">Nenhum favorito salvo ainda.</div>
                </div>
                <div class="form-group row filter-inline-row" style="margin-bottom:14px;">
                    <div class="form-group col-sm-2 filter-inline-field filter-inline--wide" style="padding:2px;padding-left:16px !important;">
                        <input class="form-control form-control-sm" type="text" style="color:#878787;margin-top:0;"
                            name="pesquisa_nome" list="internacaoHospitaisList" placeholder="Selecione o Hospital"
                            value="<?= htmlspecialchars((string)$pesquisa_nome) ?>">
                        <datalist id="internacaoHospitaisList">
                            <?php foreach ($hospitalOptions as $nomeHosp): ?>
                                <option value="<?= htmlspecialchars($nomeHosp) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="form-group col-sm-2 filter-inline-field filter-inline--wide" style="padding:2px;">
                        <input class="form-control form-control-sm" type="text" style="color:#878787;margin-top:0;"
                            name="pesquisa_pac" placeholder="Selecione o Paciente"
                            value="<?= htmlspecialchars((string)$pesquisa_pac) ?>">
                    </div>

                    <div class="form-group col-sm-2 filter-inline-field filter-inline--wide" style="padding:2px;">
                        <?php if ($isGestorSeguradora): ?>
                            <input type="hidden" name="pesquisa_seguradora" value="<?= htmlspecialchars((string)($seguradoraUserNome !== '' ? $seguradoraUserNome : ($pesquisa_seguradora ?? '')), ENT_QUOTES, 'UTF-8') ?>">
                            <input class="form-control form-control-sm" type="text" style="color:#6b5b8b;margin-top:0;background:#f3edff;"
                                value="<?= htmlspecialchars((string)($seguradoraUserNome !== '' ? $seguradoraUserNome : '-'), ENT_QUOTES, 'UTF-8') ?>" readonly>
                        <?php else: ?>
                            <input class="form-control form-control-sm" type="text" style="color:#878787;margin-top:0;"
                                name="pesquisa_seguradora" placeholder="Seguradora"
                                value="<?= htmlspecialchars((string)($pesquisa_seguradora ?? '')) ?>">
                        <?php endif; ?>
                    </div>

                    <div class="form-group col-sm-2 filter-inline-field" style="padding:2px;">
                        <input class="form-control form-control-sm" type="text" style="color:#878787;margin-top:0;"
                            name="pesquisa_matricula" placeholder="Matrícula"
                            value="<?= htmlspecialchars((string)($pesquisa_matricula ?? '')) ?>">
                    </div>

                    <div class="form-group col-sm-1 filter-inline-field filter-inline--short" style="padding:2px;">
                        <input class="form-control form-control-sm" type="text" style="color:#878787;margin-top:0;"
                            name="senha_int" placeholder="Senha" value="<?= htmlspecialchars((string)$senha_int) ?>">
                    </div>

                    <div class="col-sm-1 filter-inline-field filter-inline--short" style="padding:2px !important">
                        <select class="form-control form-control-sm" style="color:#878787;margin-top:0;"
                            id="limite" name="limite_pag">
                            <option value="">Reg por pag</option>
                            <option value="5" <?= $limite == '5'  ? 'selected' : null ?>>Reg por pág = 5</option>
                            <option value="10" <?= $limite == '10' ? 'selected' : null ?>>Reg por pág = 10</option>
                            <option value="20" <?= $limite == '20' ? 'selected' : null ?>>Reg por pág = 20</option>
                            <option value="50" <?= $limite == '50' ? 'selected' : null ?>>Reg por pág = 50</option>
                        </select>
                    </div>

                    <div class="form-group col-sm-1 filter-inline-field filter-inline--date" style="padding:2px;">
                        <input class="form-control form-control-sm" type="date" style="color:#878787;margin-top:0;"
                            name="data_intern_int" placeholder="Data Internação Min"
                            value="<?= htmlspecialchars((string)$data_intern_int) ?>">
                    </div>

                    <div class="form-group col-sm-1 filter-inline-field filter-inline--date" style="padding:2px;">
                        <input class="form-control form-control-sm" type="date" style="color:#878787;margin-top:0;"
                            name="data_intern_int_max" placeholder="Data Internação Max"
                            value="<?= htmlspecialchars((string)$data_intern_int_max) ?>">
                    </div>

                    <div class="form-group col-sm-1 filter-inline-field filter-inline--icon" style="padding:2px;">
                        <div class="filter-inline-actions">
                            <button type="submit" class="btn btn-primary btn-filtro-buscar btn-filtro-limpar-icon"
                                style="background-color:#5e2363;width:42px;height:32px;border-color:#5e2363;margin-top:0;">
                                <i class="bi bi-search"></i>
                            </button>
                            <a href="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/internacoes/lista', ENT_QUOTES, 'UTF-8') ?>"
                                id="btnClearFiltersIcon"
                                class="btn btn-light btn-sm btn-filtro-limpar btn-filtro-limpar-icon"
                                style="margin-top:0;" title="Limpar filtros" aria-label="Limpar filtros">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="pesqInternado" value="<?= htmlspecialchars((string)$pesqInternado) ?>">
                <input type="hidden" name="sem_senha" value="<?= $onlySemSenha ? '1' : '0' ?>">
                <input type="hidden" name="sort_field" value="<?= htmlspecialchars((string)$sortField) ?>">
                <input type="hidden" name="sort_dir" value="<?= htmlspecialchars((string)$sortDir) ?>">
            </form>
        </div>

        <?php
        // validacao de lista de hospital por usuario (o nivel sera o filtro)
        if ($_SESSION['nivel'] == 3) {
            $auditor = ($_SESSION['id_usuario']);
        } else {
            $auditor = null;
        }

        $QtdTotalInt = new internacaoDAO($conn, $BASE_URL);

        // METODO DE BUSCA DE PAGINACAO 
        $pesquisa_nome       = (string)internacaoGetParam('pesquisa_nome', '');
        $pesqInternado       = (string)internacaoGetParam('pesqInternado', 's');
        $limite              = (int)internacaoGetParam('limite_pag', 10);
        $pesquisa_pac        = (string)internacaoGetParam('pesquisa_pac', '');
        $pesquisa_seguradora = (string)internacaoGetParam('pesquisa_seguradora', '');
        $pesquisa_matricula  = (string)internacaoGetParam('pesquisa_matricula', '');
        $senha_int           = (string)internacaoGetParam('senha_int', '');
        $data_intern_int     = internacaoGetParam('data_intern_int');
        $data_intern_int_max = internacaoGetParam('data_intern_int_max');

        if (empty($data_intern_int_max)) {
            $data_intern_int_max = date('Y-m-d');
        }

        $ordenar = internacaoGetParam('ordenar', 1);
        if ($isGestorSeguradora) {
            $pesquisa_seguradora = $seguradoraUserNome !== '' ? $seguradoraUserNome : $pesquisa_seguradora;
        }

        $condicoes = [];
        $whereParams = [];

        if (strlen($pesquisa_nome)) {
            $condicoes[] = 'ho.nome_hosp LIKE :pesquisa_nome';
            $whereParams[':pesquisa_nome'] = '%' . $pesquisa_nome . '%';
        }
        if (strlen($pesquisa_pac)) {
            $condicoes[] = 'pa.nome_pac LIKE :pesquisa_pac';
            $whereParams[':pesquisa_pac'] = '%' . $pesquisa_pac . '%';
        }
        if (strlen($pesquisa_seguradora)) {
            $condicoes[] = 's.seguradora_seg LIKE :pesquisa_seguradora';
            $whereParams[':pesquisa_seguradora'] = '%' . $pesquisa_seguradora . '%';
        }
        if (strlen($pesquisa_matricula)) {
            $condicoes[] = 'pa.matricula_pac LIKE :pesquisa_matricula';
            $whereParams[':pesquisa_matricula'] = '%' . $pesquisa_matricula . '%';
        }
        if (strlen($pesqInternado)) {
            $condicoes[] = 'internado_int = :pesq_internado';
            $whereParams[':pesq_internado'] = $pesqInternado;
        }
        if (strlen((string)$data_intern_int)) {
            $condicoes[] = 'data_intern_int BETWEEN :data_intern_int AND :data_intern_int_max';
            $whereParams[':data_intern_int'] = (string)$data_intern_int;
            $whereParams[':data_intern_int_max'] = (string)$data_intern_int_max;
        }
        if (strlen($senha_int)) {
            $condicoes[] = 'ac.senha_int LIKE :senha_int';
            $whereParams[':senha_int'] = '%' . $senha_int . '%';
        }
        if (strlen((string)$auditor)) {
            $condicoes[] = 'hos.fk_usuario_hosp = :auditor_id';
            $whereParams[':auditor_id'] = (int)$auditor;
        }
        if ($onlySemSenha) {
            $condicoes[] = '(ac.senha_int IS NULL OR TRIM(ac.senha_int) = "")';
        }
        if ($isGestorSeguradora) {
            if ($seguradoraUserId > 0) {
                $condicoes[] = 'pa.fk_seguradora_pac = :seguradora_user_id';
                $whereParams[':seguradora_user_id'] = (int)$seguradoraUserId;
            } else {
                $condicoes[] = '1=0';
            }
        }

        $where     = implode(' AND ', $condicoes);

        $sortableColumns = [
            'id_internacao'   => 'ac.id_internacao',
            'nome_hosp'       => 'ho.nome_hosp',
            'nome_pac'        => 'pa.nome_pac',
            'seguradora_seg'  => 's.seguradora_seg',
            'data_intern_int' => 'ac.data_intern_int'
        ];
        if ($sortField && isset($sortableColumns[$sortField])) {
            $order = $sortableColumns[$sortField] . ' ' . strtoupper($sortDir);
        } else {
            $order = 'ac.id_internacao DESC';
        }

        $qtdIntItens = 0;
        try {
            $whereCount = strlen($where) ? ('WHERE ' . $where) : '';
            $sqlCount = "
                SELECT COUNT(DISTINCT ac.id_internacao)
                  FROM tb_internacao ac
             LEFT JOIN tb_hospital AS ho ON ac.fk_hospital_int = ho.id_hospital
             LEFT JOIN tb_hospitalUser AS hos ON hos.fk_hospital_user = ho.id_hospital
             LEFT JOIN tb_paciente AS pa ON ac.fk_paciente_int = pa.id_paciente
             LEFT JOIN tb_seguradora AS s ON pa.fk_seguradora_pac = s.id_seguradora
                {$whereCount}
            ";
            $stmtCount = $conn->prepare($sqlCount);
            foreach ($whereParams as $key => $value) {
                $stmtCount->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmtCount->execute();
            $qtdIntItens = (int)$stmtCount->fetchColumn();
        } catch (Throwable $th) {
            $qtdIntItens = 0;
            error_log('[LIST_INT][COUNT] ' . $th->getMessage());
        }
        $totalcasos = $limite > 0 ? ceil($qtdIntItens / $limite) : 0;

        $paginaAtualParam = (int)internacaoGetParam('pag', 1);
        if ($paginaAtualParam < 1) {
            $paginaAtualParam = 1;
        }
        $blocoAtualParam = internacaoGetParam('bl', null);
        $blocoAtualParam = ($blocoAtualParam === null || $blocoAtualParam === '')
            ? ((int)floor(($paginaAtualParam - 1) / 5) * 5)
            : (int)$blocoAtualParam;
        if ($blocoAtualParam < 0) {
            $blocoAtualParam = 0;
        }

        $obPagination = new pagination($qtdIntItens, $paginaAtualParam, $limite ?? 10);
        $obLimite     = $obPagination->getLimit();

        $query = $internacao->selectAllInternacaoList($where, $order, $obLimite, $whereParams);

        $internIds = [];
        foreach ($query as $internRow) {
            $internIds[] = (int)($internRow['id_internacao'] ?? 0);
        }
        $internIds = array_values(array_unique(array_filter($internIds)));

        $visitaResumoPorInternacao = [];
        $gestaoPorInternacao = [];
        if ($internIds) {
            try {
                $placeholders = implode(',', array_fill(0, count($internIds), '?'));

                $sqlVisitasResumo = "
                    SELECT
                        fk_internacao_vis AS id_internacao,
                        COUNT(*) AS total_visitas,
                        MAX(data_visita_vis) AS ultima_visita,
                        MAX(CASE WHEN LOWER(COALESCE(visita_med_vis, '')) = 's' THEN data_visita_vis END) AS ultima_med,
                        MAX(CASE WHEN LOWER(COALESCE(visita_enf_vis, '')) = 's' THEN data_visita_vis END) AS ultima_enf
                    FROM tb_visita
                    WHERE fk_internacao_vis IN ({$placeholders})
                      AND (retificado IS NULL OR retificado = 0)
                    GROUP BY fk_internacao_vis
                ";
                $stmtVis = $conn->prepare($sqlVisitasResumo);
                foreach ($internIds as $idx => $idIntern) {
                    $stmtVis->bindValue($idx + 1, $idIntern, PDO::PARAM_INT);
                }
                $stmtVis->execute();
                while ($rowVis = $stmtVis->fetch(PDO::FETCH_ASSOC)) {
                    $visitaResumoPorInternacao[(int)$rowVis['id_internacao']] = $rowVis;
                }

                $sqlGestao = "
                    SELECT DISTINCT fk_internacao_ges AS id_internacao
                      FROM tb_gestao
                     WHERE fk_internacao_ges IN ({$placeholders})
                ";
                $stmtGes = $conn->prepare($sqlGestao);
                foreach ($internIds as $idx => $idIntern) {
                    $stmtGes->bindValue($idx + 1, $idIntern, PDO::PARAM_INT);
                }
                $stmtGes->execute();
                while ($rowGes = $stmtGes->fetch(PDO::FETCH_ASSOC)) {
                    $gestaoPorInternacao[(int)$rowGes['id_internacao']] = true;
                }
            } catch (Throwable $th) {
                error_log('[LIST_INT][BATCH] ' . $th->getMessage());
            }
        }

        $verificarVisitas = null;

        if ($qtdIntItens > $limite) {
            $paginacao   = '';
            $paginas     = $obPagination->getPages();
            $pagina      = 1;
            $total_pages = count($paginas);

            $blocoCorrente       = (int)floor($blocoAtualParam / 5) + 1;
            $block_pages         = array_filter($paginas, function ($var) use ($blocoCorrente) {
                return (int)($var['bloco'] ?? 0) === $blocoCorrente;
            });
            $first_page_in_block = reset($block_pages)["pg"];
            $last_page_in_block  = end($block_pages)["pg"];
            $first_block         = reset($paginas)["bloco"];
            $last_block          = end($paginas)["bloco"];
            $current_block       = reset($block_pages)["bloco"];
        }

        $paginationBaseParams = [
            'pesquisa_nome'       => $pesquisa_nome,
            'pesquisa_pac'        => $pesquisa_pac,
            'pesquisa_seguradora' => $pesquisa_seguradora,
            'pesquisa_matricula'  => $pesquisa_matricula,
            'senha_int'           => $senha_int,
            'data_intern_int'     => $data_intern_int,
            'data_intern_int_max' => $data_intern_int_max,
            'pesqInternado'       => $pesqInternado,
            'limite_pag'          => $limite,
            'sort_field'          => $sortField,
            'sort_dir'            => $sortDir,
            'sem_senha'           => $onlySemSenha ? '1' : null,
        ];

        if (!function_exists('buildInternacaoPaginationUrl')) {
            function buildInternacaoPaginationUrl(array $baseParams, array $override = []): string
            {
                $params = array_merge($baseParams, $override);
                $compactParams = internacaoCompactQueryParams($params);
                $query = http_build_query($compactParams);
                global $BASE_URL;
                $baseUrl = rtrim($BASE_URL, '/') . '/internacoes/lista';

                return $query ? $baseUrl . '?' . $query : $baseUrl;
            }
        }
        ?>

        <!-- TABELA DE REGISTROS -->
        <div style="margin-top:3px;" id="container">
            <div id="table-content">
                <table class="table table-sm table-striped table-hover table-condensed">
                    <thead>
                        <tr>
                            <?php
                            $sortableHeaders = [
                                'id_internacao'   => ['label' => 'Id-Int',   'style' => 'min-width: 50px;'],
                                'nome_hosp'       => ['label' => 'Hospital', 'style' => 'min-width: 150px;'],
                                'nome_pac'        => ['label' => 'Paciente', 'style' => 'min-width: 150px;'],
                                'seguradora_seg'  => ['label' => 'Seguradora', 'style' => 'min-width: 150px;'],
                                'data_intern_int' => ['label' => 'Data Int', 'style' => 'min-width: 100px;'],
                            ];
                            foreach ($sortableHeaders as $key => $meta):
                                $ascActive = ($sortField === $key && $sortDir === 'asc');
                                $descActive = ($sortField === $key && $sortDir === 'desc');
                                $ascUrl = buildInternacaoPaginationUrl($paginationBaseParams, ['sort_field' => $key, 'sort_dir' => 'asc', 'pag' => 1]);
                                $descUrl = buildInternacaoPaginationUrl($paginationBaseParams, ['sort_field' => $key, 'sort_dir' => 'desc', 'pag' => 1]);
                            ?>
                            <th scope="col" style="<?= $meta['style'] ?>" class="text-center">
                                <div class="th-sortable justify-content-center">
                                    <span><?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="sort-icons">
                                        <a href="<?= htmlspecialchars($ascUrl, ENT_QUOTES, 'UTF-8') ?>"
                                            class="<?= $ascActive ? 'active' : '' ?>" title="Ordenar crescente">↑</a>
                                        <a href="<?= htmlspecialchars($descUrl, ENT_QUOTES, 'UTF-8') ?>"
                                            class="<?= $descActive ? 'active' : '' ?>" title="Ordenar decrescente">↓</a>
                                    </span>
                                </div>
                            </th>
                            <?php endforeach; ?>
                            <th scope="col" style="min-width: 80px;">Senha</th>
                            <th scope="col" style="min-width: 80px;">Dias Int</th>
                            <th scope="col" style="min-width: 80px;">Últ Visita</th>
                            <th scope="col" style="min-width: 80px;">Visita Med</th>
                            <th scope="col" style="min-width: 80px;">Visita Enf</th>
                            <th scope="col" style="min-width: 80px;">Nº Visita</th>
                            <th scope="col" style="min-width: 80px;">Gestão</th>
                            <th scope="col" style="min-width: 80px;">UTI</th>
                            <th scope="col" style="min-width: 80px;">Ações</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php
                        $hoje  = date('Y-m-d');
                        $atual = new DateTime($hoje);
                        foreach ($query as $intern):
                            $internId = (int)$intern["id_internacao"];
                            $resumoVisita = $visitaResumoPorInternacao[$internId] ?? null;

                            $datainternacao = date("Y-m-d", strtotime($intern['data_intern_int']));
                            $dataIntern     = new DateTime($datainternacao);

                            $diasIntern     = $dataIntern->diff($atual);
                            $countVisitas   = (int)($resumoVisita['total_visitas'] ?? 0);
                        ?>
                        <tr style="font-size:13px">
                            <td scope="row" class="col-id">
                                <?= $intern["id_internacao"] ?>
                            </td>

                            <td scope="row" style="font-weight:bolder;">
                                <?= htmlspecialchars($intern["nome_hosp"], ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td scope="row">
                                <?= htmlspecialchars($intern["nome_pac"], ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td scope="row">
                                <?= htmlspecialchars($intern["seguradora_seg"] ?? '--', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td scope="row">
                                <?= date('d/m/Y', strtotime($intern["data_intern_int"])) ?>
                            </td>
                            <td scope="row" style="font-weight:bolder;">
                                <?= $intern["senha_int"] ?>
                            </td>
                            <td scope="row">
                                <?= $diasIntern->days ?>
                            </td>
                            <td scope="row">
                                <?php
                                    if (!empty($resumoVisita['ultima_visita'])) {
                                        echo date('d/m/Y', strtotime((string)$resumoVisita['ultima_visita']));
                                    }
                                    ?>
                            </td>

                            <!-- Visita Médica -->
                            <td scope="row">
                                <?php
                                    $ultimaMed = $resumoVisita['ultima_med'] ?? null;

                                    if ($ultimaMed) {
                                        $dias = (new DateTime($ultimaMed))->diff($atual)->days;
                                        if ($dias <= 7) {
                                            $cor   = 'green';
                                            $icone = '<i class="fas fa-check-circle" style="color: green; margin-right: 5px;"></i>';
                                        } elseif ($dias > 7 && $dias <= 10) {
                                            $cor   = 'orange';
                                            $icone = '<i class="fas fa-exclamation-circle" style="color: orange; margin-right: 5px;"></i>';
                                        } else {
                                            $cor   = 'red';
                                            $icone = '<i class="fas fa-times-circle" style="color: red; margin-right: 5px;"></i>';
                                        }
                                        echo "$icone<span style='color: $cor; font-weight: bold;'>{$dias} dias</span>";
                                    } else {
                                        echo "<span style='color: gray;'>--</span>";
                                    }
                                    ?>
                            </td>

                            <!-- Visita Enfermagem -->
                            <td scope="row">
                                <?php
                                    $ultimaEnf = $resumoVisita['ultima_enf'] ?? null;

                                    if ($ultimaEnf) {
                                        $diasEnf = (new DateTime($ultimaEnf))->diff($atual)->days;
                                        if ($diasEnf <= 7) {
                                            $cor   = 'green';
                                            $icone = '<i class="fas fa-check-circle" style="color: green; margin-right: 5px;"></i>';
                                        } elseif ($diasEnf > 7 && $diasEnf <= 10) {
                                            $cor   = 'orange';
                                            $icone = '<i class="fas fa-exclamation-circle" style="color: orange; margin-right: 5px;"></i>';
                                        } else {
                                            $cor   = 'red';
                                            $icone = '<i class="fas fa-times-circle" style="color: red; margin-right: 5px;"></i>';
                                        }
                                        echo "$icone<span style='color: $cor; font-weight: bold;'>{$diasEnf} dias</span>";
                                    } else {
                                        echo "<span style='color: gray;'>--</span>";
                                    }
                                    ?>
                            </td>

                            <td scope="row">
                                <?= $countVisitas ?>
                            </td>

                            <td scope="row">
                                <?php
                                    if (!empty($gestaoPorInternacao[$internId])) {
                                        echo '<a href=""><i style="color:green; font-size:1.8em" class="bi bi-card-checklist fw-bold"></i></a>';
                                    } else {
                                        echo "--";
                                    }
                                    ?>
                            </td>

                            <td scope="row">
                                <?php
                                    if ($intern['internado_uti'] == 's') {
                                        echo '<a href=""><i class="bi bi-clipboard-heart" style="color: blue; font-size: 1.8em; margin-right: 8px;"></i></a>';
                                    } else {
                                        echo "--";
                                    }
                                    ?>
                            </td>

                            <td class="fc-list-action">
                                <div class="dropdown">
                                    <button class="btn btn-default dropdown-toggle" id="acoesInternacaoDropdown<?= (int)$intern['id_internacao'] ?>"
                                        role="button" data-bs-toggle="dropdown" style="color:#5e2363"
                                        aria-expanded="false">
                                        <i class="bi bi-stack"></i>
                                    </button>
                                    <ul class="dropdown-menu" aria-labelledby="acoesInternacaoDropdown<?= (int)$intern['id_internacao'] ?>">
                                        <?php if ($pesqInternado == "s" and $intern['censo_int'] <> "s") { ?>
                                        <li>
                                            <button class="btn btn-default"
                                                onclick="edit('<?= rtrim($BASE_URL, '/') ?>/internacoes/visualizar/<?= (int)$intern['id_internacao'] ?>')"
                                                style="font-size: 1rem;">
                                                <i class="fas fa-eye"
                                                    style="font-size: 1rem;margin-right:5px; color: rgb(27,156, 55);"></i>
                                                Visualização
                                            </button>
                                        </li>
                                        <?php } ?>

                                        <?php if ($pesqInternado == "s" and $intern['censo_int'] == "s" and $intern['primeira_vis_int'] == 'n') { ?>
                                        <li>
                                            <button class="btn btn-default"
                                                onclick="edit('<?= $BASE_URL ?>edit_internacao.php?id_internacao=<?= $intern['id_internacao'] ?>')"
                                                style="font-size: .9rem;">
                                                <i class="bi bi-pencil-square"
                                                    style="font-size: 1rem;margin-right:5px; color: rgb(27,156, 55);"></i>
                                                Rel. Inicial
                                            </button>
                                        </li>
                                        <?php } ?>

                                        <?php if (!$isGestorSeguradora) { ?>
                                            <li>
                                                <button type="button" class="btn btn-default" style="font-size: .9rem;"
                                                    onclick="window.location.href='<?= $BASE_URL ?>cad_visita.php?id_internacao=<?= $intern['id_internacao'] ?>'">
                                                    <i class="bi bi-file-text"
                                                        style="font-size: 1rem; margin-right:5px; color: rgba(128, 27, 156, 1);"></i>
                                                    Visita
                                                </button>
                                            </li>
                                        <?php } ?>

                                        <?php if ($pesqInternado == "s" && !$isGestorSeguradora) { ?>
                                            <li>
                                                <button class="btn btn-default"
                                                    onclick="edit('<?= $BASE_URL ?>edit_alta.php?type=alta&id_internacao=<?= $intern['id_internacao'] ?>')"
                                                    style="font-size: .9rem;">
                                                    <i class="bi bi-door-open"
                                                        style="font-size: 1rem;margin-right:5px; color: rgba(27, 64, 156, 1);"></i>
                                                    Alta
                                                </button>
                                            </li>
                                        <?php } ?>

                                        <li>
                                            <!-- <button class="btn btn-default"
                                                onclick="edit('<?= $BASE_URL ?>edit_internacao_EA.php?id_internacao=<?= $intern['id_internacao'] ?>')"
                                                style="font-size: .9rem;">
                                                <i class="bi bi-pencil-square"
                                                    style="font-size: 1rem;margin-right:5px; color: rgba(27, 27, 156, 1);"></i>
                                                Ev Adverso
                                            </button> -->
                                        </li>

                                        <li>
                                            <!-- <button class="btn btn-default"
                                                onclick="edit('<?= $BASE_URL ?>edit_internacao_TUSS.php?id_internacao=<?= $intern['id_internacao'] ?>')"
                                                style="font-size: .9rem;">
                                                <i class="bi bi-pencil-square"
                                                    style="font-size: 1rem;margin-right:5px; color: rgba(156, 27, 85, 1);"></i>
                                                TUSS
                                            </button> -->
                                        </li>

                                        <?php if (!$isGestorSeguradora) { ?>
                                            <li>
                                                <button type="button" class="btn btn-default" style="font-size: .9rem;"
                                                    onclick="window.location.href='<?= $BASE_URL ?>edit_internacao.php?id_internacao=<?= $intern['id_internacao'] ?>'">
                                                    <i class="bi bi-pencil-square"
                                                        style="font-size: 1rem; margin-right: 5px; color: rgba(113, 27, 156, 1);"></i>
                                                    Editar
                                                </button>
                                            </li>

                                            <li>
                                                <button class="btn btn-default"
                                                    onclick="callProcessPdf(<?= $intern['id_internacao'] ?>)"
                                                    style="font-size: .9rem;">
                                                    <i class="bi bi-file-earmark-pdf"
                                                        style="font-size: 1rem; margin-right:5px; color: #ff7043;"></i>
                                                    PDF - Internação
                                                </button>
                                            </li>
                                        <?php } ?>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <?php if ($qtdIntItens == 0): ?>
                        <tr>
                            <td colspan="14" scope="row" class="col-id" style="font-size:15px">
                                Sem registros para os filtros aplicados.<?= $isGestorSeguradora ? ' Você está visualizando somente dados da sua seguradora.' : '' ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div style="text-align:right">
                    <input type="hidden" id="qtd" value="<?= (int)$qtdIntItens ?>">
                </div>

                <div style="display: flex;margin-top:20px;">

                    <!-- Modal para abrir tela de cadastro -->
                    <div class="modal fade" id="myModal">
                        <div class="modal-dialog  modal-dialog-centered modal-xl">
                            <div class="modal-content">
                                <div class="modal-header modal-header-blue">
                                    <h4 class="page-title" style="color:white;">Cadastrar Internação</h4>
                                    <p class="page-description" style="color:white; margin-top:5px">
                                        Adicione informações sobre a internação
                                    </p>
                                </div>
                                <div class="modal-body">
                                    <div id="content-php"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PAGINAÇÃO -->
                    <div class="pagination" style="margin: 0 auto;">
                        <?php if ($total_pages ?? 1 > 1): ?>
                        <ul class="pagination">
                            <?php
                                $blocoAtual  = $blocoAtualParam;
                                $paginaAtual = $paginaAtualParam;
                                ?>
                            <?php if ($current_block > $first_block): ?>
                            <?php
                                    $firstPageUrl = buildInternacaoPaginationUrl($paginationBaseParams, [
                                        'pag' => 1
                                    ]);
                                    ?>
                            <li class="page-item">
                                <a class="page-link" id="blocoNovo" href="<?= htmlspecialchars($firstPageUrl) ?>">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                            </li>
                            <?php endif; ?>

                            <?php if ($current_block <= $last_block && $last_block > 1 && $current_block != 1): ?>
                            <?php
                                    $prevPage  = max(1, $paginaAtual - 1);
                                    $prevUrl   = buildInternacaoPaginationUrl($paginationBaseParams, [
                                        'pag' => $prevPage
                                    ]);
                                    ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= htmlspecialchars($prevUrl) ?>">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            </li>
                            <?php endif; ?>

                            <?php for ($i = $first_page_in_block; $i <= $last_page_in_block; $i++): ?>
                            <?php
                                    $pageUrl = buildInternacaoPaginationUrl($paginationBaseParams, [
                                        'pag' => $i
                                    ]);
                                    ?>
                            <li class="page-item <?= $paginaAtualParam == $i ? "active" : "" ?>">
                                <a class="page-link" href="<?= htmlspecialchars($pageUrl) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                            <?php endfor; ?>

                            <?php if ($current_block < $last_block): ?>
                            <?php
                                    $nextPage  = min($total_pages, $paginaAtual + 1);
                                    $nextUrl   = buildInternacaoPaginationUrl($paginationBaseParams, [
                                        'pag' => $nextPage
                                    ]);
                                    ?>
                            <li class="page-item">
                                <a class="page-link" id="blocoNovo" href="<?= htmlspecialchars($nextUrl) ?>">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                            </li>
                            <?php endif; ?>

                            <?php if ($current_block < $last_block): ?>
                            <?php
                                    $lastUrl = buildInternacaoPaginationUrl($paginationBaseParams, [
                                        'pag' => $total_pages
                                    ]);
                                    ?>
                            <li class="page-item">
                                <a class="page-link" id="blocoNovo" href="<?= htmlspecialchars($lastUrl) ?>">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                        <?php endif; ?>
                    </div>

                    <div class="table-counter">
                        <p style="margin-bottom:25px;font-size:1em; font-weight:600;
                                  font-family:var(--bs-font-sans-serif); text-align:right">
                            <?= "Total: " . (int)$qtdIntItens ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Selecionar campos do Excel (Internação) -->
<!-- Modal: Campos a exibir/exportar para o Excel (Internação) -->
<div class="modal fade" id="modalExportInternCampos" tabindex="-1" aria-labelledby="modalExportInternCamposLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">

            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" id="modalExportInternCamposLabel">
                    Campos a exibir/exportar para o Excel
                </h5>

                <div class="d-flex align-items-center gap-2 me-3">
                    <!-- Selecionar todos -->
                    <button type="button" class="btn btn-sm rounded-pill" id="btnInternSelectAll"
                        style="background-color:#f5f1ff;border:none;color:#555;">
                        ✓ Selecionar todos
                    </button>
                    <!-- Limpar -->
                    <button type="button" class="btn btn-sm rounded-pill" id="btnInternClear"
                        style="background-color:#f5f1ff;border:none;color:#555;">
                        ✕ Limpar
                    </button>
                </div>

                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <div class="modal-body">

                <form id="formCamposExcelIntern">
                    <!-- Pills – use a mesma classe de pill do modal Alta se já existir -->
                    <div class="d-flex flex-wrap gap-2">
                        <!-- ID Internação -->
                        <input class="btn-check" type="checkbox" name="colsIntern[]" value="id_int" id="campo_id_int"
                            autocomplete="off" checked>
                        <label class="btn btn-sm rounded-pill export-pill" for="campo_id_int">
                            # ID da internação
                        </label>

                        <!-- Hospital -->
                        <input class="btn-check" type="checkbox" name="colsIntern[]" value="hosp" id="campo_hosp"
                            autocomplete="off" checked>
                        <label class="btn btn-sm rounded-pill export-pill" for="campo_hosp">
                            🏥 Hospital
                        </label>

                        <!-- Nome do paciente -->
                        <input class="btn-check" type="checkbox" name="colsIntern[]" value="pac" id="campo_pac"
                            autocomplete="off" checked>
                        <label class="btn btn-sm rounded-pill export-pill" for="campo_pac">
                            👤 Nome do paciente
                        </label>

                        <!-- Data Internação -->
                        <input class="btn-check" type="checkbox" name="colsIntern[]" value="data_intern"
                            id="campo_data_intern" autocomplete="off" checked>
                        <label class="btn btn-sm rounded-pill export-pill" for="campo_data_intern">
                            📅 Data da internação
                        </label>

                        <!-- Hora Internação -->
                        <input class="btn-check" type="checkbox" name="colsIntern[]" value="hora_intern"
                            id="campo_hora_intern" autocomplete="off">
                        <label class="btn btn-sm rounded-pill export-pill" for="campo_hora_intern">
                            ⏰ Hora da internação
                        </label>

                        <!-- UTI -->
                        <input class="btn-check" type="checkbox" name="colsIntern[]" value="uti" id="campo_uti"
                            autocomplete="off">
                        <label class="btn btn-sm rounded-pill export-pill" for="campo_uti">
                            UTI
                        </label>

                        <!-- Acomodação -->
                        <input class="btn-check" type="checkbox" name="colsIntern[]" value="acomodacao"
                            id="campo_acomodacao" autocomplete="off">
                        <label class="btn btn-sm rounded-pill export-pill" for="campo_acomodacao">
                            Acomodação
                        </label>

                        <!-- Senha -->
                        <input class="btn-check" type="checkbox" name="colsIntern[]" value="senha" id="campo_senha"
                            autocomplete="off">
                        <label class="btn btn-sm rounded-pill export-pill" for="campo_senha">
                            Senha
                        </label>

                        <!-- Matrícula -->
                        <input class="btn-check" type="checkbox" name="colsIntern[]" value="matricula"
                            id="campo_matricula" autocomplete="off">
                        <label class="btn btn-sm rounded-pill export-pill" for="campo_matricula">
                            Matrícula
                        </label>

                        <!-- Tipo Admissão -->
                        <input class="btn-check" type="checkbox" name="colsIntern[]" value="tipo_adm"
                            id="campo_tipo_adm" autocomplete="off">
                        <label class="btn btn-sm rounded-pill export-pill" for="campo_tipo_adm">
                            Tipo admissão
                        </label>

                        <!-- Modo Internação -->
                        <input class="btn-check" type="checkbox" name="colsIntern[]" value="modo" id="campo_modo"
                            autocomplete="off">
                        <label class="btn btn-sm rounded-pill export-pill" for="campo_modo">
                            Modo internação
                        </label>

                        <!-- Internado -->
                        <input class="btn-check" type="checkbox" name="colsIntern[]" value="internado"
                            id="campo_internado" autocomplete="off">
                        <label class="btn btn-sm rounded-pill export-pill" for="campo_internado">
                            Internado
                        </label>

                        <!-- Especialidade -->
                        <input class="btn-check" type="checkbox" name="colsIntern[]" value="especialidade"
                            id="campo_especialidade" autocomplete="off">
                        <label class="btn btn-sm rounded-pill export-pill" for="campo_especialidade">
                            Especialidade
                        </label>

                        <!-- Patologia -->
                        <input class="btn-check" type="checkbox" name="colsIntern[]" value="patologia"
                            id="campo_patologia" autocomplete="off">
                        <label class="btn btn-sm rounded-pill export-pill" for="campo_patologia">
                            Patologia
                        </label>

                        <!-- Relatório / Evolução -->
                        <input class="btn-check" type="checkbox" name="colsIntern[]" value="relatorio"
                            id="campo_relatorio" autocomplete="off" checked>
                        <label class="btn btn-sm rounded-pill export-pill" for="campo_relatorio">
                            Relatório / Evolução
                        </label>

                        <!-- Última visita médica (quadro clínico) -->
                        <input class="btn-check" type="checkbox" name="colsIntern[]" value="ultima_visita_medico"
                            id="campo_ultima_visita_medico" autocomplete="off" checked>
                        <label class="btn btn-sm rounded-pill export-pill" for="campo_ultima_visita_medico">
                            Última visita médica (quadro clínico)
                        </label>

                        <!-- Ações -->
                        <input class="btn-check" type="checkbox" name="colsIntern[]" value="acoes" id="campo_acoes"
                            autocomplete="off">
                        <label class="btn btn-sm rounded-pill export-pill" for="campo_acoes">
                            Ações
                        </label>

                        <!-- Programação -->
                        <input class="btn-check" type="checkbox" name="colsIntern[]" value="programacao"
                            id="campo_programacao" autocomplete="off">
                        <label class="btn btn-sm rounded-pill export-pill" for="campo_programacao">
                            Programação
                        </label>

                        <!-- Médico Titular -->
                        <input class="btn-check" type="checkbox" name="colsIntern[]" value="medico_titular"
                            id="campo_medico_titular" autocomplete="off">
                        <label class="btn btn-sm rounded-pill export-pill" for="campo_medico_titular">
                            Médico titular
                        </label>

                        <!-- Nome do profissional -->
                        <input class="btn-check" type="checkbox" name="colsIntern[]" value="profissional"
                            id="campo_profissional" autocomplete="off" checked>
                        <label class="btn btn-sm rounded-pill export-pill" for="campo_profissional">
                            Nome do profissional
                        </label>

                        <!-- Cargo do profissional -->
                        <input class="btn-check" type="checkbox" name="colsIntern[]" value="profissional_cargo"
                            id="campo_profissional_cargo" autocomplete="off" checked>
                        <label class="btn btn-sm rounded-pill export-pill" for="campo_profissional_cargo">
                            Cargo do profissional
                        </label>

                        <!-- Registro do profissional -->
                        <input class="btn-check" type="checkbox" name="colsIntern[]" value="profissional_registro"
                            id="campo_profissional_registro" autocomplete="off" checked>
                        <label class="btn btn-sm rounded-pill export-pill" for="campo_profissional_registro">
                            Registro profissional
                        </label>

                    </div>

                </form>
            </div>

            <div class="modal-footer border-0 d-flex justify-content-between">
                <button type="button" class="btn btn-link text-muted" data-bs-dismiss="modal">
                    Cancelar
                </button>

                <button type="button" class="btn btn-success rounded-pill" id="btnConfirmExportIntern">
                    Exportar XLSX (Excel)
                </button>
            </div>

        </div>
    </div>
</div>



<script type="text/javascript">
function callProcessPdf(id_internacao) {
    window.location.href = 'process_pdf_intern.php?id=' + encodeURIComponent(id_internacao);
}
</script>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js"></script>

<script>
// ajax para submit do formulario de pesquisa + modal de exportação
$(document).ready(function() {
    // campo "ordenar" removido (classificação agora nos headers)
    var urlAliasLongToShort = {
        pesquisa_nome: 'hosp',
        pesquisa_pac: 'pac',
        pesquisa_seguradora: 'seg',
        pesquisa_matricula: 'mat',
        senha_int: 'sn',
        limite_pag: 'pp',
        data_intern_int: 'di',
        data_intern_int_max: 'df',
        pesqInternado: 'it',
        sem_senha: 'ss',
        sort_field: 'sf',
        sort_dir: 'sd',
        pag: 'pg',
        bl: 'blc'
    };
    var urlAliasShortToLong = {};
    Object.keys(urlAliasLongToShort).forEach(function(longKey) {
        urlAliasShortToLong[urlAliasLongToShort[longKey]] = longKey;
    });

    var compactDefaults = {
        pesqInternado: 's',
        sem_senha: '0',
        sort_dir: 'desc',
        limite_pag: '10'
    };

    function compactInternacaoQueryString(input) {
        var sourceParams = new URLSearchParams(typeof input === 'string' ? input : (input || ''));
        var normalized = {};

        sourceParams.forEach(function(value, key) {
            var longKey = urlAliasShortToLong[key] || key;
            normalized[longKey] = value;
        });

        if (!normalized.data_intern_int) {
            delete normalized.data_intern_int_max;
        }
        if (!normalized.sort_field) {
            delete normalized.sort_dir;
        }

        var compact = new URLSearchParams();
        Object.keys(normalized).forEach(function(longKey) {
            var value = normalized[longKey];
            if (value === null || value === undefined) return;
            value = String(value).trim();
            if (!value) return;
            if (compactDefaults[longKey] !== undefined && value === compactDefaults[longKey]) return;

            var shortKey = urlAliasLongToShort[longKey] || longKey;
            compact.set(shortKey, value);
        });

        return compact.toString();
    }

    function syncFilterFormFromUrl(url) {
        var form = document.getElementById('select-internacao-form');
        if (!form || !url) return;
        try {
            var parsed = new URL(url, window.location.origin);
            var params = parsed.searchParams;
            Array.from(form.elements || []).forEach(function(el) {
                if (!el || !el.name) return;
                var shortName = urlAliasLongToShort[el.name] || null;
                var hasLong = params.has(el.name);
                var hasShort = shortName ? params.has(shortName) : false;
                if (!hasLong && !hasShort) return;
                var rawValue = hasLong ? params.get(el.name) : params.get(shortName);
                if (el.type === 'checkbox') {
                    el.checked = ['1', 'true', 'on'].includes((rawValue || '').toLowerCase());
                } else {
                    el.value = rawValue;
                }
            });
        } catch (err) {
            // Se URL inválida, mantém estado atual do formulário.
        }
    }

    function renderTableContentFromResponse(responseHtml) {
        var tempElement = document.createElement('div');
        tempElement.innerHTML = responseHtml;
        var tableContent = tempElement.querySelector('#table-content');
        if (tableContent) {
            $('#table-content').html(tableContent.innerHTML);
            return true;
        }
        return false;
    }

    function loadInternacaoList(url, dataPayload) {
        var requestUrl = url || ($('#select-internacao-form').attr('action') || 'internacoes/lista');
        var requestData = dataPayload || null;

        $.ajax({
            url: requestUrl,
            type: 'GET',
            data: requestData,
            success: function(response) {
                var updated = renderTableContentFromResponse(response);
                if (!updated) return;

                if (requestData) {
                    var qs = requestData;
                    if (typeof qs !== 'string') {
                        qs = $.param(requestData);
                    }
                    var compactQs = compactInternacaoQueryString(qs);
                    var targetUrl = requestUrl + (compactQs ? (requestUrl.indexOf('?') === -1 ? '?' : '&') + compactQs : '');
                    window.history.replaceState({}, '', targetUrl);
                    syncFilterFormFromUrl(targetUrl);
                } else {
                    var compactUrl = requestUrl;
                    try {
                        var reqUrlObj = new URL(requestUrl, window.location.origin);
                        var compactFromUrl = compactInternacaoQueryString(reqUrlObj.search);
                        compactUrl = reqUrlObj.pathname + (compactFromUrl ? '?' + compactFromUrl : '');
                    } catch (err) {}
                    window.history.replaceState({}, '', compactUrl);
                    syncFilterFormFromUrl(compactUrl);
                }
            },
            error: function() {
                $('#responseMessage').html('Ocorreu um erro ao atualizar a listagem.');
            }
        });
    }

    // ============================
    // 1) SUBMIT AJAX – FILTRO
    // ============================
    $('#select-internacao-form').on('submit', function(e) {
        e.preventDefault();
        var formData = $(this).serialize();
        loadInternacaoList($(this).attr('action') || 'internacoes/lista', formData);
    });

    // =================================================
    // 1.1) PAGINAÇÃO E ORDENAÇÃO DO HEADER VIA AJAX
    // =================================================
    $(document).on('click', '#table-content .pagination a.page-link, #table-content .sort-icons a', function(e) {
        var href = $(this).attr('href');
        if (!href || href === '#') return;
        e.preventDefault();
        loadInternacaoList(href, null);
    });

    // ==========================================
    // 2) ABRIR MODAL DE CAMPOS DO EXCEL
    // ==========================================
    $('#btn-exportar-excel').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        new bootstrap.Modal(document.getElementById('modalExportInternCampos')).show();
    });

    // ==========================================
    // 3) PILLS (chips lilás) <-> checkboxes
    // ==========================================

    // Deixa as pills com visual coerente com o estado dos checkboxes
    function syncPillsFromCheckboxes() {
        $('#formCamposExcelIntern input[name="colsIntern[]"]').each(function() {
            var id = $(this).attr('id'); // ex: campo_id_int
            var $label = $('label[for="' + id + '"]'); // pill correspondente

            if ($(this).is(':checked')) {
                $label.removeClass('inactive');
            } else {
                $label.addClass('inactive');
            }
        });
    }

    // Chamada inicial ao abrir a página
    syncPillsFromCheckboxes();

    // Clique em uma pill -> alterna checkbox correspondente
    $(document).on('click', '.export-pill', function(e) {
        e.preventDefault();

        var $pill = $(this);
        var forId = $pill.attr('for'); // exemplo: "campo_id_int"
        var $cb = $('#' + forId);

        var novoStatus = !$cb.prop('checked');
        $cb.prop('checked', novoStatus);

        if (novoStatus) {
            $pill.removeClass('inactive');
        } else {
            $pill.addClass('inactive');
        }
    });

    // Botão "Selecionar todos"
    $('#btnInternSelectAll').on('click', function(e) {
        e.preventDefault();
        $('#formCamposExcelIntern input[name="colsIntern[]"]').prop('checked', true);
        syncPillsFromCheckboxes();
    });

    // Botão "Limpar"
    $('#btnInternClear').on('click', function(e) {
        e.preventDefault();
        $('#formCamposExcelIntern input[name="colsIntern[]"]').prop('checked', false);
        syncPillsFromCheckboxes();
    });

    // ==========================================
    // 4) CONFIRMAR EXPORTAÇÃO EXCEL
    // ==========================================
    $('#btnConfirmExportIntern').on('click', function(e) {
        e.preventDefault();

        // 1) Campos marcados no modal
        var campos = [];
        $('input[name="colsIntern[]"]:checked').each(function() {
            campos.push($(this).val());
        });

        if (!campos.length) {
            alert('Selecione pelo menos um campo para exportar.');
            return;
        }

        // 2) Filtros da listagem
        var queryParts = [];
        var baseQuery = $('#select-internacao-form').serialize();
        if (baseQuery) {
            queryParts.push(baseQuery);
        }

        // 3) Param "campos" em CSV
        queryParts.push('campos=' + encodeURIComponent(campos.join(',')));

        // 4) Filtro adicional de profissional

        var query = queryParts.join('&');

        // 4) URL final
        var urlExcel = '<?= $BASE_URL ?>exportar_excel_list_intern.php';
        if (query) {
            urlExcel += '?' + query;
        }

        // 5) Fecha modal
        var modalEl = document.getElementById('modalExportInternCampos');
        var modalObj = bootstrap.Modal.getInstance(modalEl);
        if (modalObj) modalObj.hide();

        // 6) Abre Excel
        window.open(urlExcel, '_blank');
    });

});
</script>

<script>
if (typeof window.paginateInternacao !== 'function') {
    window.paginateInternacao = function(url) {
        if (typeof loadContent === 'function') {
            loadContent(url);
            return false;
        }
        window.location.href = url;
        return false;
    };
}
</script>

<script>
(function() {
    const storageKeys = {
        last: 'fullconex:listInternacao:lastFilter',
        fav: 'fullconex:listInternacao:favorites'
    };
    const form = document.getElementById('select-internacao-form');
    if (!form) return;

    const btnApplyLast = document.getElementById('btnApplyLastFilter');
    const btnSaveFav = document.getElementById('btnSaveFavFilter');
    const btnClear = document.getElementById('btnClearFilters');
    const btnClearIcon = document.getElementById('btnClearFiltersIcon');
    const favoritesWrap = document.getElementById('filterFavorites');
    const favoritesHint = document.getElementById('filterFavoritesHint');
    const smartInput = document.getElementById('smartSearchPhrase');
    const btnSmart = document.getElementById('btnApplySmartSearch');
    const smartFeedback = document.getElementById('smartSearchFeedback');
    const isSeguradoraRole = <?= $isGestorSeguradora ? 'true' : 'false' ?>;
    const seguradoraNomeEscopo = <?= json_encode((string)$seguradoraUserNome, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const smartSeguradoraOptions = <?= json_encode(array_values($seguradoraOptions), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    const fieldNames = [
        'pesquisa_nome',
        'pesquisa_pac',
        'pesquisa_seguradora',
        'pesquisa_matricula',
        'senha_int',
        'limite_pag',
        'data_intern_int',
        'data_intern_int_max',
        'pesqInternado',
        'sem_senha',
        'sort_field',
        'sort_dir'
    ];

    const hiddenDefaults = {
        pesqInternado: form.elements.namedItem('pesqInternado')?.value || 's',
        sem_senha: form.elements.namedItem('sem_senha')?.value || '0',
        sort_field: '',
        sort_dir: form.elements.namedItem('sort_dir')?.value || 'desc'
    };

    const storageAvailable = (() => {
        try {
            const test = '__fc_filter__';
            localStorage.setItem(test, '1');
            localStorage.removeItem(test);
            return true;
        } catch (err) {
            return false;
        }
    })();

    function readFormValues() {
        const values = {};
        fieldNames.forEach((name) => {
            const field = form.elements.namedItem(name);
            if (!field) return;
            if (field.type === 'checkbox') {
                values[name] = field.checked ? '1' : '0';
            } else {
                values[name] = field.value ?? '';
            }
        });
        return values;
    }

    function fillFormValues(values) {
        if (!values) return;
        fieldNames.forEach((name) => {
            if (!(name in values)) return;
            const field = form.elements.namedItem(name);
            if (!field) return;
            if (field.type === 'checkbox') {
                field.checked = values[name] === '1';
            } else {
                field.value = values[name];
            }
        });
    }

    function submitFiltersWithoutRefresh() {
        form.dispatchEvent(new Event('submit', {
            bubbles: true,
            cancelable: true
        }));
    }

    function persistLastFilter(values) {
        if (!storageAvailable) return;
        localStorage.setItem(storageKeys.last, JSON.stringify(values));
    }

    function getLastFilter() {
        if (!storageAvailable) return null;
        const data = localStorage.getItem(storageKeys.last);
        if (!data) return null;
        try {
            return JSON.parse(data);
        } catch (err) {
            return null;
        }
    }

    function getFavorites() {
        if (!storageAvailable) return [];
        const data = localStorage.getItem(storageKeys.fav);
        if (!data) return [];
        try {
            const parsed = JSON.parse(data);
            return Array.isArray(parsed) ? parsed : [];
        } catch (err) {
            return [];
        }
    }

    function saveFavorites(list) {
        if (!storageAvailable) return;
        localStorage.setItem(storageKeys.fav, JSON.stringify(list));
    }

    function renderFavorites() {
        const favorites = getFavorites();
        if (favoritesWrap) favoritesWrap.innerHTML = '';
        if (!favorites.length) {
            if (favoritesHint) favoritesHint.style.display = 'block';
            return;
        }
        if (favoritesHint) favoritesHint.style.display = 'none';
        favorites.forEach((fav, index) => {
            const chip = document.createElement('div');
            chip.className = 'filter-favorite-chip';
            chip.dataset.index = String(index);
            chip.innerHTML = `
                <span class="label">${fav.label}</span>
                <span class="remove" title="Remover favorito">&times;</span>
            `;
            chip.addEventListener('click', (event) => {
                if (event.target.classList.contains('remove')) return;
                applyFavorite(index);
            });
            chip.querySelector('.remove').addEventListener('click', (event) => {
                event.stopPropagation();
                removeFavorite(index);
            });
            favoritesWrap && favoritesWrap.appendChild(chip);
        });
    }

    function applyFavorite(index) {
        const favorites = getFavorites();
        const fav = favorites[index];
        if (!fav) return;
        fillFormValues(fav.values);
        submitFiltersWithoutRefresh();
    }

    function removeFavorite(index) {
        const favorites = getFavorites();
        favorites.splice(index, 1);
        saveFavorites(favorites);
        renderFavorites();
    }

    function handleSaveFavorite() {
        const current = readFormValues();
        const labelDefault = current.pesquisa_nome || current.pesquisa_pac || current.pesquisa_seguradora || current.pesquisa_matricula ||
            'Novo favorito';
        const label = prompt('Nome do favorito:', labelDefault);
        if (!label) return;
        const favorites = getFavorites();
        favorites.unshift({
            label: label.trim(),
            savedAt: new Date().toISOString(),
            values: current
        });
        if (favorites.length > 5) {
            favorites.length = 5;
        }
        saveFavorites(favorites);
        renderFavorites();
    }

    function handleApplyLast() {
        const last = getLastFilter();
        if (!last) {
            showSmartError('Nenhum filtro anterior encontrado.');
            return;
        }
        fillFormValues(last);
        if (isSeguradoraRole && seguradoraNomeEscopo) {
            const segField = form.elements.namedItem('pesquisa_seguradora');
            if (segField) segField.value = seguradoraNomeEscopo;
        }
        submitFiltersWithoutRefresh();
    }

    function handleClearFilters() {
        ['pesquisa_nome', 'pesquisa_pac', 'pesquisa_seguradora', 'pesquisa_matricula', 'senha_int', 'data_intern_int',
            'data_intern_int_max'
        ].forEach((name) => {
            const field = form.elements.namedItem(name);
            if (field) field.value = '';
        });
        if (smartInput) smartInput.value = '';
        clearSmartError();
        ['limite_pag'].forEach((name) => {
            const field = form.elements.namedItem(name);
            if (field && field.tagName === 'SELECT') {
                field.selectedIndex = 0;
            }
        });
        Object.keys(hiddenDefaults).forEach((name) => {
            const field = form.elements.namedItem(name);
            if (field) field.value = hiddenDefaults[name];
        });
        if (isSeguradoraRole && seguradoraNomeEscopo) {
            const segField = form.elements.namedItem('pesquisa_seguradora');
            if (segField) segField.value = seguradoraNomeEscopo;
        }
        if (storageAvailable) {
            localStorage.removeItem(storageKeys.last);
        }
        submitFiltersWithoutRefresh();
    }

    function normalizeSmartTerm(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, ' ')
            .trim();
    }

    function escapeRegex(value) {
        return String(value || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function findKnownSeguradora(phrase) {
        const normalizedPhrase = ` ${normalizeSmartTerm(phrase)} `;
        if (!normalizedPhrase.trim()) return null;

        const ordered = smartSeguradoraOptions
            .map((label) => ({
                label,
                normalized: normalizeSmartTerm(label)
            }))
            .filter((item) => item.normalized.length >= 3)
            .sort((a, b) => b.normalized.length - a.normalized.length);

        for (const item of ordered) {
            if (normalizedPhrase.includes(` ${item.normalized} `)) {
                return item.label;
            }
        }
        return null;
    }

    function removeKnownTermFromPhrase(phrase, term) {
        const termWords = normalizeSmartTerm(term).split(' ').filter(Boolean);
        if (!termWords.length) return phrase;

        let result = String(phrase || '');
        termWords.forEach((word) => {
            if (word.length < 3) return;
            result = result.replace(new RegExp(`\\b${escapeRegex(word)}\\b`, 'ig'), ' ');
        });
        return result.replace(/\s+/g, ' ').trim();
    }

    function parseSmartPhrase(phrase) {
        if (!phrase) return null;
        const cleaned = phrase.trim();
        if (!cleaned) return null;
        const months = {
            janeiro: '01',
            fevereiro: '02',
            marco: '03',
            março: '03',
            abril: '04',
            maio: '05',
            junho: '06',
            julho: '07',
            agosto: '08',
            setembro: '09',
            outubro: '10',
            novembro: '11',
            dezembro: '12'
        };
        const result = {};
        const lower = cleaned.toLowerCase();
        const knownSeguradora = !isSeguradoraRole ? findKnownSeguradora(cleaned) : null;
        const cleanedForTextFields = knownSeguradora ? removeKnownTermFromPhrase(cleaned, knownSeguradora) : cleaned;

        let monthInfo = null;
        Object.keys(months).some((name) => {
            const regex = new RegExp(name, 'i');
            const match = cleanedForTextFields.match(regex);
            if (match) {
                const yearMatch = cleanedForTextFields.match(/20\d{2}/);
                const year = yearMatch ? parseInt(yearMatch[0], 10) : new Date().getFullYear();
                const monthNum = parseInt(months[name], 10);
                const start = `${year}-${String(monthNum).padStart(2, '0')}-01`;
                const endDay = new Date(year, monthNum, 0).getDate();
                const end =
                    `${year}-${String(monthNum).padStart(2, '0')}-${String(endDay).padStart(2, '0')}`;
                result.data_intern_int = start;
                result.data_intern_int_max = end;
                monthInfo = {
                    index: match.index,
                    length: match[0].length
                };
                return true;
            }
            return false;
        });

        const hospRegex =
            /(?:contas|hospital|hosp)\s+([^0-9]+?)(?=(?:janeiro|fevereiro|mar[cç]o|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro|paciente|\d{4}|$))/i;
        const hospMatch = cleanedForTextFields.match(hospRegex);
        if (hospMatch) {
            result.pesquisa_nome = hospMatch[1].trim();
        } else if (monthInfo && monthInfo.index > 0) {
            const possible = cleanedForTextFields.slice(0, monthInfo.index).replace(/^(contas|hospital|hosp)\s+/i, '').trim();
            if (possible) result.pesquisa_nome = possible;
        }

        const pacRegex =
            /paciente\s+([^0-9]+?)(?=(?:contas|hospital|hosp|janeiro|fevereiro|mar[cç]o|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro|\d{4}|$))/i;
        const pacMatch = cleanedForTextFields.match(pacRegex);
        if (pacMatch) {
            result.pesquisa_pac = pacMatch[1].trim();
        }

        const segRegex =
            /(?:seguradora|operadora|convenio|conv[êe]nio)\s+([^0-9]+?)(?=(?:paciente|contas|hospital|hosp|janeiro|fevereiro|mar[cç]o|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro|senha|matr[íi]cula|\d{4}|$))/i;
        const segMatch = cleaned.match(segRegex);
        if (segMatch) {
            result.pesquisa_seguradora = segMatch[1].trim();
        } else if (knownSeguradora) {
            result.pesquisa_seguradora = knownSeguradora;
        }

        const senhaMatch = cleaned.match(/senha\s+([\w-]+)/i);
        if (senhaMatch) {
            result.senha_int = senhaMatch[1];
        }

        const matriculaMatch = cleaned.match(/matr[íi]cula\s+([\w.-]+)/i);
        if (matriculaMatch) {
            result.pesquisa_matricula = matriculaMatch[1];
        }

        if (knownSeguradora && !result.pesquisa_pac && !result.pesquisa_nome && !result.senha_int && !result.pesquisa_matricula) {
            const remainingTerm = cleanedForTextFields.replace(/\b(paciente|seguradora|operadora|convenio|conv[êe]nio)\b/ig, '').trim();
            if (remainingTerm.length >= 3) {
                result.pesquisa_pac = remainingTerm;
            }
        }

        // Fallback: texto simples sem chave vira seguradora (ex.: "Bradesco")
        if (Object.keys(result).length === 0) {
            const plainTerm = cleaned.replace(/\s+/g, ' ').trim();
            if (plainTerm.length >= 3) {
                result.pesquisa_seguradora = plainTerm;
            }
        }

        if (isSeguradoraRole && seguradoraNomeEscopo) {
            result.pesquisa_seguradora = seguradoraNomeEscopo;
        }

        if (Object.keys(result).length === 0) {
            return null;
        }
        return result;
    }

    function showSmartError(message) {
        if (!smartFeedback) return;
        smartFeedback.textContent = message;
        smartFeedback.classList.add('is-error');
    }

    function clearSmartError() {
        if (!smartFeedback) return;
        smartFeedback.textContent = '';
        smartFeedback.classList.remove('is-error');
    }

    function handleSmartSearch() {
        const phrase = smartInput.value;
        const parsed = parseSmartPhrase(phrase);
        if (!parsed) {
            showSmartError('Não foi possível interpretar esta frase. Tente informar hospital, paciente, seguradora ou mês.');
            return;
        }
        if (isSeguradoraRole && seguradoraNomeEscopo && /\b(seguradora|operadora|conv[êe]nio|convenio)\b/i.test(phrase || '')) {
            showSmartError('No seu perfil, a seguradora é fixa em ' + seguradoraNomeEscopo + '.');
        } else {
            clearSmartError();
        }
        fillFormValues(parsed);
        submitFiltersWithoutRefresh();
    }

    if (storageAvailable) {
        renderFavorites();
    } else {
        if (favoritesHint) {
            favoritesHint.textContent = 'Memória de filtros não disponível neste navegador.';
            favoritesHint.style.display = 'block';
        }
    }

    form.addEventListener('submit', () => {
        const values = readFormValues();
        persistLastFilter(values);
    });

    if (btnSaveFav) btnSaveFav.addEventListener('click', handleSaveFavorite);
    if (btnApplyLast) btnApplyLast.addEventListener('click', handleApplyLast);
    if (btnClear) btnClear.addEventListener('click', handleClearFilters);
    if (btnClearIcon) btnClearIcon.addEventListener('click', (event) => {
        event.preventDefault();
        handleClearFilters();
    });
    if (btnSmart) btnSmart.addEventListener('click', handleSmartSearch);
    if (smartInput) {
        smartInput.addEventListener('input', clearSmartError);
        smartInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                handleSmartSearch();
            }
        });
    }
})();
</script>

<script src="./js/input-estilo.js"></script>
<script src="./js/scriptDataAltaHospitalar.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous">
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>
<script src="./js/ajaxNav.js"></script>

<?php
require_once("templates/footer.php");
?>
