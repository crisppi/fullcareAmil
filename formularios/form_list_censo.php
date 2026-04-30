<?php

require_once("templates/header.php");

require_once("models/message.php");

include_once("models/censo.php");
include_once("dao/censoDao.php");

include_once("models/patologia.php");
include_once("dao/patologiaDao.php");

include_once("models/paciente.php");
include_once("dao/pacienteDao.php");

include_once("models/hospital.php");
include_once("dao/hospitalDao.php");

include_once("models/censo.php");
include_once("dao/censoDao.php");

include_once("models/capeante.php");
include_once("dao/capeanteDao.php");

include_once("models/pagination.php");

if (!function_exists('listaCensoGetParam')) {
    function listaCensoGetParam(string $longKey, $default = null)
    {
        static $shortToLong = [
            'hosp' => 'pesquisa_nome',
            'pac'  => 'pesquisa_pac',
            'it'   => 'pesqInternado',
            'pp'   => 'limite',
            'ord'  => 'ordenar',
            'pg'   => 'pag',
            'blc'  => 'bl',
        ];
        static $longToShort = null;
        if ($longToShort === null) $longToShort = array_flip($shortToLong);
        $value = $_GET[$longKey] ?? null;
        if ($value === null && isset($longToShort[$longKey])) {
            $value = $_GET[$longToShort[$longKey]] ?? null;
        }
        if ($value === null || $value === '') return $default;
        return $value;
    }
}

if (!function_exists('listaCensoCompactParams')) {
    function listaCensoCompactParams(array $params): array
    {
        $defaults = ['pesqInternado' => 's', 'limite' => '10'];
        $longToShort = [
            'pesquisa_nome' => 'hosp',
            'pesquisa_pac' => 'pac',
            'pesqInternado' => 'it',
            'limite' => 'pp',
            'ordenar' => 'ord',
            'pag' => 'pg',
            'bl' => 'blc',
        ];
        $clean = [];
        foreach ($params as $k => $v) {
            if ($v === null || $v === '' || $v === false) continue;
            $v = (string)$v;
            if (isset($defaults[$k]) && $defaults[$k] === $v) continue;
            $clean[$k] = $v;
        }
        unset($clean['bl']);
        $compact = [];
        foreach ($clean as $k => $v) {
            $compact[$longToShort[$k] ?? $k] = $v;
        }
        return $compact;
    }
}

$censo_geral = new censoDAO($conn, $BASE_URL);
$censos = $censo_geral->findGeral();

$pacienteDao = new pacienteDAO($conn, $BASE_URL);
$pacientes = $pacienteDao->findGeral($limite, $inicio);

$capeante_geral = new HospitalDAO($conn, $BASE_URL);
$capeantes = $capeante_geral->findGeral($limite, $inicio);

$hospital_geral = new HospitalDAO($conn, $BASE_URL);
$hospitals = $hospital_geral->findGeral($limite, $inicio);

$patologiaDao = new patologiaDAO($conn, $BASE_URL);
$patologias = $patologiaDao->findGeral();

$censoDao = new censoDAO($conn, $BASE_URL);

$censo = new censoDAO($conn, $BASE_URL);
$where = $order = $obLimite = null;
$user = $_SESSION['id_usuario'];

?>
<link rel="stylesheet" href="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/css/listagem_padrao.css', ENT_QUOTES, 'UTF-8') ?>">
<style>
    .listagem-page {
        padding: 4px 4px 14px;
    }

    .listagem-title {
        font-size: .96rem;
        line-height: 1.05;
    }

    .listagem-subtitle {
        font-size: .66rem;
        line-height: 1.25;
        max-width: 42rem;
    }

    .listagem-btn-top {
        min-height: 32px;
        padding: 6px 12px;
        font-size: .7rem;
        gap: 6px;
    }

    .listagem-btn-top i {
        font-size: .72rem;
        margin-right: 0;
    }

    .listagem-panel {
        padding: 8px 8px 6px;
    }

    .filter-inline-row {
        display: flex;
        align-items: stretch;
        flex-wrap: wrap;
        padding: 5px 6px;
        row-gap: 4px;
    }

    .filter-inline-row > [class*="col-"],
    .filter-inline-row > .form-group {
        display: flex;
        align-items: stretch;
        padding: 2px !important;
    }

    .filter-inline-row > :first-child {
        padding-left: 8px !important;
    }

    .filter-inline-row .form-control,
    .filter-inline-row .btn,
    .filter-inline-row .bootstrap-select > .dropdown-toggle {
        min-height: 32px !important;
        height: 32px !important;
        font-size: .72rem;
        line-height: 1.2;
        border-radius: 11px;
        margin-top: 0 !important;
        margin-bottom: 0 !important;
    }

    .filter-inline-row .form-control {
        padding-top: 0;
        padding-bottom: 0;
    }

    .filter-inline-row .form-control::placeholder {
        font-size: .72rem;
        color: #c4c4c4;
    }

    .filter-inline-row select.form-control {
        padding-right: 28px;
    }

    .filter-inline-row .btn-filtro-buscar {
        width: 32px;
        min-width: 32px;
        padding: 0;
        background-color: #5e2363;
        border-color: #5e2363;
    }

    .filter-inline-row .btn-filtro-limpar-icon {
        height: 32px !important;
        min-width: 32px;
        padding: 0;
    }

    .filter-inline-row .filter-actions {
        gap: 8px;
        justify-content: flex-start;
    }

    .filter-inline-row .material-icons {
        font-size: 16px;
        line-height: 1;
        margin: 0;
    }

    #table-content {
        margin-top: -4px;
    }

    #table-content thead th {
        padding: 7px 10px;
        font-size: .54rem;
        letter-spacing: .08em;
    }

    #table-content tbody td,
    #table-content tbody th {
        padding: 6px 10px;
        font-size: .7rem;
        vertical-align: middle;
    }

    #table-content .dropdown-toggle {
        min-width: 32px;
        min-height: 28px;
        padding: 4px 8px;
        font-size: .68rem;
    }

    #table-content .dropdown-menu .btn {
        font-size: .72rem !important;
    }

    #table-content .dropdown-menu .btn i {
        font-size: .78rem !important;
        margin-right: 4px !important;
    }

    .listagem-panel .pagination {
        margin-top: 10px !important;
    }

    .listagem-panel .pagination .page-link,
    .listagem-panel p[style*="text-align:right"] {
        font-size: .72rem;
    }
</style>
<!-- FORMULARIO DE PESQUISAS -->
<div class="container-fluid form_container listagem-page" id="main-container">
    <div class="listagem-hero">
        <div class="listagem-hero__copy">
            <div class="listagem-kicker">Censo</div>
            <h1 class="listagem-title">Listagem de censo</h1>
        </div>
        <div class="listagem-hero__actions">
            <a class="btn listagem-btn-top listagem-btn-top--blue" href="censo/novo"><i class="fa-solid fa-plus"></i>Novo lançamento</a>
            <a onclick="sendIdListToPHP()" id="submitInter" class="btn listagem-btn-top listagem-btn-top--purple"><i class="fa-solid fa-check"></i>Internar selecionados</a>
        </div>
    </div>
    <div class="complete-table listagem-panel">
        <div id="navbarToggleExternalContent" class="table-filters">
            <div class="row">
                <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
                <form action="" id="select-censo-form" method="GET">
                    <?php $pesquisa_nome = (string)listaCensoGetParam('pesquisa_nome', '');
                    $pesqInternado = (string)listaCensoGetParam('pesqInternado', 's');
                    $limite = (int)listaCensoGetParam('limite', 10);
                    $pesquisa_pac = (string)listaCensoGetParam('pesquisa_pac', '');
                    $ordenar = (string)listaCensoGetParam('ordenar', '');
                    ?>
                    <div class="row filter-inline-row">
                        <div class="form-group col-sm-2" style="padding:2px !important;padding-left:16px !important;">
                            <input class="form-control form-control-sm" type="text"
                                name="pesquisa_nome"
                                placeholder="Selecione o Hospital" value="<?= $pesquisa_nome ?>">
                        </div>
                        <div class="form-group col-sm-2" style="padding:2px !important">
                            <input class="form-control form-control-sm" type="text"
                                name="pesquisa_pac"
                                placeholder="Selecione o Paciente" value="<?= $pesquisa_pac ?>">
                        </div>

                        <div class="form-group col-sm-2" style="padding:2px !important">
                            <select class="form-control form-control-sm"
                                id="pesqInternado" name="pesqInternado">
                                <option value="">Busca por Internados</option>
                                <option value="s" <?= $pesqInternado == 's' ? 'selected' : null ?>>Sim</option>
                                <option value="n" <?= $pesqInternado == 'n' ? 'selected' : null ?>>Não</option>
                            </select>
                        </div>
                        <div class="col-sm-1" style="padding:2px !important">
                            <select class="form-control form-control-sm" id="limite"
                                name="limite">
                                <option value="">Reg por página</option>
                                <option value="5" <?= $limite == '5' ? 'selected' : null ?>>Reg por pág = 5
                                </option>
                                <option value="10" <?= $limite == '10' ? 'selected' : null ?>>Reg por pág = 10
                                </option>
                                <option value="20" <?= $limite == '20' ? 'selected' : null ?>>Reg por pág = 20
                                </option>
                                <option value="50" <?= $limite == '50' ? 'selected' : null ?>>Reg por pág = 50
                                </option>
                            </select>
                        </div>
                        <div class="form-group col-sm-2" style="padding:2px !important">
                            <select class="form-control form-control-sm"
                                id="ordenar" name="ordenar">
                                <option value="">Classificar por</option>
                                <option value="id_censo" <?= $ordenar == 'id_censo' ? 'selected' : null ?>>Internação
                                </option>
                                <option value="nome_pac" <?= $ordenar == 'nome_pac' ? 'selected' : null ?>>Paciente
                                </option>
                                <option value="nome_hosp" <?= $ordenar == 'nome_hosp' ? 'selected' : null ?>>Hospital
                                </option>
                                <option value="data_intern_int"
                                    <?= $ordenar == 'data_intern_int' ? 'selected' : null ?>>
                                    Data
                                    Internação</option>
                            </select>
                        </div>
                        <div class="form-group col-sm-1 d-flex align-items-stretch filter-actions" style="padding:2px !important">
                            <button type="submit" class="btn btn-primary btn-filtro-buscar btn-filtro-limpar-icon"><span
                                    class="material-icons">
                                    search
                                </span></button>
                            <a href="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/censo/lista', ENT_QUOTES, 'UTF-8') ?>"
                                class="btn btn-light btn-sm btn-filtro-limpar btn-filtro-limpar-icon"
                                title="Limpar filtros" aria-label="Limpar filtros">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <!-- BASE DAS PESQUISAS -->
        <?php

        // validacao de lista de hospital por usuario (o nivel sera o filtro)
        if ($_SESSION['nivel'] == 3) {
            $auditor = ($_SESSION['id_usuario']);
        } else {

            $auditor = null;
        };

        //Instanciando a classe
        $QtdTotalInt = new censoDAO($conn, $BASE_URL);
        // METODO DE BUSCA DE PAGINACAO 
        $pesquisa_nome = (string)listaCensoGetParam('pesquisa_nome', '');
        $limite = (int)listaCensoGetParam('limite', 10);
        $pesquisa_pac = (string)listaCensoGetParam('pesquisa_pac', '');
        $ordenar = (string)listaCensoGetParam('ordenar', 1);
        $censo_internado = '1';

        // $buscaAtivo = in_array($buscaAtivo, ['s', 'n']) ?: "";
        $condicoes = [
            strlen($pesquisa_nome) ? 'ho.nome_hosp LIKE "%' . $pesquisa_nome . '%"' : NULL,
            strlen($pesquisa_pac) ? 'nome_pac LIKE "%' . $pesquisa_pac . '%"' : NULL,
            "internado IS NULL",
        ];
        $condicoes = array_filter($condicoes);
        // REMOVE POSICOES VAZIAS DO FILTRO
        $where = implode(' AND ', $condicoes);

        // QUANTIDADE censo
        $qtdIntItens1 = $censoDao->selectAllCensoList($where);

        $qtdIntItens = count($qtdIntItens1); // total de registros
        $totalcasos = ceil($qtdIntItens / $limite);

        // PAGINACAO
        $order = $ordenar;

        $paginaAtualParam = (int)listaCensoGetParam('pag', 1);
        if ($paginaAtualParam < 1) {
            $paginaAtualParam = 1;
        }
        $obPagination = new pagination($qtdIntItens, $paginaAtualParam, $limite ?? 10);

        $obLimite = $obPagination->getLimit();

        // PREENCHIMENTO DO FORMULARIO COM QUERY
        $query = $censoDao->selectAllCensoList($where, $order, $obLimite);


        // PAGINACAO
        if ($qtdIntItens > $limite) {
            $paginacao = '';
            $paginas = $obPagination->getPages();
            $pagina = 1;
            $total_pages = count($paginas);

            // FUNCAO PARA CONTROLE DO NUMERO DE PAGINAS, UTILIZANDO A QUANTIDADE DE PAGINAS CALCULADAS NA VARIAVEL PAGINAS PELE METODO getPages

            function paginasAtuais($var)
            {
                $blocoAtual = (int)listaCensoGetParam('bl', (int)floor(($paginaAtualParam - 1) / 5) * 5);
                return $var['bloco'] == (($blocoAtual) / 5) + 1;
            }
            $block_pages = array_filter($paginas, "paginasAtuais"); // REFERENCIA FUNCAO CRIADA ACIMA
            $first_page_in_block = reset($block_pages)["pg"];
            $last_page_in_block = end($block_pages)["pg"];
            $first_block = reset($paginas)["bloco"];
            $last_block = end($paginas)["bloco"];
            $current_block = reset($block_pages)["bloco"];
        }

        $paginationBaseParams = [
            'pesquisa_nome' => $pesquisa_nome,
            'pesquisa_pac' => $pesquisa_pac,
            'pesqInternado' => $pesqInternado,
            'limite' => $limite,
            'ordenar' => $ordenar,
        ];
        if (!function_exists('buildCensoPaginationUrl')) {
            function buildCensoPaginationUrl(array $baseParams, array $override = []): string
            {
                $params = array_merge($baseParams, $override);
                $compact = listaCensoCompactParams($params);
                $query = http_build_query($compact);
                global $BASE_URL;
                $baseUrl = rtrim($BASE_URL, '/') . '/censo/lista';
                return $query ? $baseUrl . '?' . $query : $baseUrl;
            }
        }
        ?>

        <!-- TABELA DE REGISTROS -->
        <div>
            <div id="table-content" class="listagem-table-wrap">
                <table class="table table-sm table-striped  table-hover table-condensed censo-list-table">
                    <thead>
                        <tr>
                            <th scope="col">Id-Int</th>
                            <th scope="col">Hospital</th>
                            <th scope="col">Paciente</th>
                            <th scope="col">Status</th>
                            <th scope="col">Acomodação</th>
                            <th scope="col">Modo Admissão</th>
                            <th scope="col">Médico</th>
                            <th scope="col">Senha</th>
                            <th scope="col">Ações</th>
                            <?php if (count($query) > 0): ?>
                            <th scope="col"></th>
                            <?php endif ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($query as $intern):
                            extract($query);
                        ?>
                        <tr>
                            <td scope="row" class="col-id">
                                <?= $intern["id_censo"] ?>
                            </td>

                            <td scope="row" class="nome-coluna-table">
                                <?= $intern["nome_hosp"] ?>
                            </td>
                            <td scope="row">
                                <?= $intern["nome_pac"] ?>
                            </td>
                            <td scope="row">
                                <?php
                                    if ($intern['id_internacao'] > 0) {
                                        echo "Internado";
                                    } else {

                                        echo "Não Internado";
                                    }
                                    ?>
                            </td>
                            <td scope="row">
                                <?= $intern["acomodacao_censo"] ?>
                            </td>
                            <td scope="row">
                                <?= $intern["tipo_admissao_censo"] ?>
                            </td>

                            <td scope="row">
                                <?= $intern["titular_censo"] ?>
                            </td>
                            <td scope="row">
                                <?= $intern["senha_censo"] ?>
                            </td>
                            <td class="action">
                                <div class="dropdown">
                                    <button class="btn btn-default dropdown-toggle" id="navbarScrollingDropdown"
                                        role="button" data-bs-toggle="dropdown" style="color:#5e2363"
                                        aria-expanded="false">
                                        <i class="bi bi-stack"></i>
                                    </button>
                                    <ul class="dropdown-menu" aria-labelledby="navbarScrollingDropdown">
                                        <li>
                                            <button class="btn btn-default"
                                                onclick="openModal('<?= $BASE_URL ?>show_censo_adm.php?id_censo=<?= $intern['id_censo'] ?>')"
                                                data-bs-toggle="modal" data-bs-target="#myModal"><i class="fas fa-eye"
                                                    style="color: rgb(27,156, 55);"></i>Ver</button>
                                        </li>
                                        <li>
                                            <form class="d-inline-block delete-form" action="process_censo_int.php"
                                                method="get">
                                                <input type="hidden" name="type" value="create">
                                                <input type="hidden" name="id_censo" value="<?= $intern["id_censo"] ?>">
                                                <button class="btn btn-default"><i
                                                        style="color: rgb(67, 125, 525);"
                                                        class="bi bi-door-open"></i>Internar</button>
                                            </form>
                                        </li>
                                        <li>
                                            <form class="d-inline-block delete-form" action="del_censo.php"
                                                method="post">
                                                <input type="hidden" name="type" value="delete">
                                                <input type="hidden" name="id_censo" value="<?= $intern["id_censo"] ?>">
                                                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                                <button class="btn btn-default"><i
                                                        style="color: red;"
                                                        class="bi bi-x-circle-fill"></i>Deletar</button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                            <?php if (count($query) > 0): ?>
                            <td class="action">
                                <div class="form-check">
                                    <input onclick="addList(<?= $intern['id_censo'] ?>)" class="form-check-input"
                                        type="checkbox" id="flexCheckDefault">
                                </div>
                            </td>
                            <?php endif ?>
                        </tr>
                        <?php endforeach; ?>
                        <?php if ($qtdIntItens == 0): ?>
                        <tr>
                            <td colspan="15" scope="row" class="col-id">
                                Não foram encontrados registros
                            </td>
                        </tr>
                        <?php endif ?>
                    </tbody>
                </table>
                <div class="modal fade" id="myModal">
                    <div class="modal-dialog  modal-dialog-centered modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h4 class="page-title" style="color:white">Censo</h4>
                                <p class="page-description" style="color:white; margin-top:5px">Informações sobre o
                                    censo</p>
                            </div>
                            <div class="modal-body">
                                <div id="content-php"></div>
                            </div>

                        </div>
                    </div>
                </div>
                <div style="text-align:right">
                    <input type="hidden" id="qtd" value="<?php echo $qtdIntItens ?>">
                </div>
                <div style="display: flex;margin-top:20px">

                    <div class="pagination" style="margin: 0 auto;">
                        <?php if ($total_pages ?? 1 > 1): ?>
                        <ul class="pagination">
                            <?php
                                $blocoAtual = (int)listaCensoGetParam('bl', (int)floor(($paginaAtualParam - 1) / 5) * 5);
                                $paginaAtual = $paginaAtualParam;
                                ?>
                            <?php if ($current_block > $first_block): ?>
                            <li class="page-item">
                                <a class="page-link" id="blocoNovo" href="<?= htmlspecialchars(buildCensoPaginationUrl($paginationBaseParams, ['pag' => 1]), ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="fa-solid fa-angles-left"></i></a>
                            </li>
                            <?php endif; ?>
                            <?php if ($current_block <= $last_block && $last_block > 1 && $current_block != 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= htmlspecialchars(buildCensoPaginationUrl($paginationBaseParams, ['pag' => max(1, $paginaAtual - 1)]), ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="fa-solid fa-angle-left"></i></a>
                            </li>
                            <?php endif; ?>

                            <?php for ($i = $first_page_in_block; $i <= $last_page_in_block; $i++): ?>
                            <li class="page-item <?php print $paginaAtualParam == $i ? "active" : "" ?>">
                                <a class="page-link" href="<?= htmlspecialchars(buildCensoPaginationUrl($paginationBaseParams, ['pag' => $i]), ENT_QUOTES, 'UTF-8') ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>

                            <?php if ($current_block < $last_block): ?>
                            <li class="page-item">
                                <a class="page-link" id="blocoNovo" href="<?= htmlspecialchars(buildCensoPaginationUrl($paginationBaseParams, ['pag' => min($total_pages, $paginaAtual + 1)]), ENT_QUOTES, 'UTF-8') ?>"><i
                                        class="fa-solid fa-angle-right"></i></a>
                            </li>
                            <?php endif; ?>
                            <?php if ($current_block < $last_block): ?>
                            <li class="page-item">
                                <a class="page-link" id="blocoNovo" href="<?= htmlspecialchars(buildCensoPaginationUrl($paginationBaseParams, ['pag' => count($paginas)]), ENT_QUOTES, 'UTF-8') ?>"><i
                                        class="fa-solid fa-angles-right"></i></a>
                            </li>
                            <?php endif; ?>
                        </ul>
                        <?php endif; ?>
                    </div>

                    <div class="table-counter">
                        <p
                            style="font-size:1em; font-weight:600; font-family:var(--bs-font-sans-serif); text-align:right">
                            <?php echo "Total: " . $qtdIntItens ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
var censoAliasLongToShort = {
    pesquisa_nome: 'hosp',
    pesquisa_pac: 'pac',
    pesqInternado: 'it',
    limite: 'pp',
    ordenar: 'ord',
    pag: 'pg',
    bl: 'blc'
};
var censoAliasShortToLong = {};
Object.keys(censoAliasLongToShort).forEach(function(k) {
    censoAliasShortToLong[censoAliasLongToShort[k]] = k;
});
function compactCensoQuery(input) {
    var source = new URLSearchParams(typeof input === 'string' ? input : (input || ''));
    var normalized = {};
    source.forEach(function(v, k) {
        normalized[censoAliasShortToLong[k] || k] = v;
    });
    delete normalized.bl;
    var defaults = { pesqInternado: 's', limite: '10' };
    var out = new URLSearchParams();
    Object.keys(normalized).forEach(function(longKey) {
        var value = String(normalized[longKey] || '').trim();
        if (!value) return;
        if (defaults[longKey] !== undefined && defaults[longKey] === value) return;
        out.set(censoAliasLongToShort[longKey] || longKey, value);
    });
    return out.toString();
}

function renderCensoTable(responseHtml) {
    var temp = document.createElement('div');
    temp.innerHTML = responseHtml;
    var tableContent = temp.querySelector('#table-content');
    if (!tableContent) return false;
    $('#table-content').html(tableContent.innerHTML);
    return true;
}

function loadCensoList(url, dataPayload) {
    var requestUrl = url || ($('#select-censo-form').attr('action') || window.location.pathname);
    $.ajax({
        url: requestUrl,
        type: 'GET',
        data: dataPayload || null,
        success: function(response) {
            if (!renderCensoTable(response)) return;
            if (dataPayload) {
                var qs = typeof dataPayload === 'string' ? dataPayload : $.param(dataPayload);
                var compactQs = compactCensoQuery(qs);
                var targetUrl = requestUrl + (compactQs ? (requestUrl.indexOf('?') === -1 ? '?' : '&') + compactQs : '');
                window.history.replaceState({}, '', targetUrl);
            } else if (url) {
                try {
                    var parsed = new URL(url, window.location.origin);
                    var compactFromUrl = compactCensoQuery(parsed.search);
                    window.history.replaceState({}, '', parsed.pathname + (compactFromUrl ? '?' + compactFromUrl : ''));
                } catch (e) {
                    window.history.replaceState({}, '', url);
                }
            }
        },
        error: function() {
            $('#responseMessage').html('Ocorreu um erro ao atualizar a listagem.');
        }
    });
}

$(document).ready(function() {
    $('#select-censo-form').on('submit', function(e) {
        e.preventDefault();
        loadCensoList($(this).attr('action') || window.location.pathname, $(this).serialize());
    });
});

$(document).on('click', '#table-content .pagination a.page-link', function(e) {
    var href = $(this).attr('href');
    if (!href || href === '#') return;
    e.preventDefault();
    loadCensoList(href, null);
});
</script>


<style>
.modal-backdrop {
    display: none;

}

.modal {
    background: rgba(0, 0, 0, 0.5);

}

.modal-header {
    color: white;
    background: #35bae1;
}

/* Center the checkbox both vertically and horizontally */
.form-check {
    display: flex;
    justify-content: center;
    /* Center horizontally */
    align-items: center;
    /* Center vertically */
    height: 100%;
    /* Ensure the container takes full height of the cell */
}

/* Increase the size of the checkbox */
.form-check-input[type=checkbox] {
    width: 17px;
    /* Set your desired width */
    height: 17px;
    /* Set your desired height */
}
</style>
<script src="./js/input-estilo.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous">
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>
<script>
src = "https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js";
</script>
<script src="./scripts/cadastro/general.js"></script>
<script src="./js/ajaxNav.js"></script>
