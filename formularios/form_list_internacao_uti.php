<?php

require_once("templates/header.php");

// require_once("models/message.php");

include_once("models/internacao.php");
include_once("dao/internacaoDao.php");

include_once("models/patologia.php");
include_once("dao/patologiaDao.php");

include_once("models/paciente.php");
include_once("dao/pacienteDao.php");

include_once("models/hospital.php");
include_once("dao/hospitalDao.php");

include_once("models/uti.php");
include_once("dao/utiDao.php");

include_once("models/pagination.php");

if (!function_exists('listaUtiGetParam')) {
    function listaUtiGetParam(string $longKey, $default = null)
    {
        static $shortToLong = [
            'hosp' => 'pesquisa_nome',
            'pac'  => 'pesquisa_pac',
            'mat'  => 'pesquisa_matricula',
            'it'   => 'pesqInternado',
            'pp'   => 'limite_pag',
            'di'   => 'data_intern_int',
            'sf'   => 'sort_field',
            'sd'   => 'sort_dir',
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

if (!function_exists('listaUtiCompactParams')) {
    function listaUtiCompactParams(array $params): array
    {
        $defaults = ['pesqInternado' => 's', 'limite_pag' => '10', 'sort_dir' => 'desc'];
        $longToShort = [
            'pesquisa_nome' => 'hosp',
            'pesquisa_pac' => 'pac',
            'pesquisa_matricula' => 'mat',
            'pesqInternado' => 'it',
            'limite_pag' => 'pp',
            'data_intern_int' => 'di',
            'sort_field' => 'sf',
            'sort_dir' => 'sd',
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
        if (empty($clean['sort_field'])) unset($clean['sort_dir']);
        unset($clean['bl']);
        $compact = [];
        foreach ($clean as $k => $v) {
            $compact[$longToShort[$k] ?? $k] = $v;
        }
        return $compact;
    }
}

$where = null;
$internacao_geral = new internacaoDAO($conn, $BASE_URL);
$internacaos = $internacao_geral->findGeral($where, $limite, $inicio);

$pacienteDao = new pacienteDAO($conn, $BASE_URL);
$pacientes = $pacienteDao->findGeral($limite, $inicio);

$hospital_geral = new hospitalDAO($conn, $BASE_URL);
$hospitals = $hospital_geral->findGeral($limite, $inicio);

$patologiaDao = new patologiaDAO($conn, $BASE_URL);
$patologias = $patologiaDao->findGeral();

$internacao = new internacaoDAO($conn, $BASE_URL);
$order = null;
$obLimite = null;
$uti = new utiDAO($conn, $BASE_URL);
$sortField = trim((string)listaUtiGetParam('sort_field', ''));
$sortDir = strtolower((string)listaUtiGetParam('sort_dir', 'desc'));

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

    .listagem-panel {
        padding: 8px 8px 6px;
    }

    .listagem-panel .uti-filter-row {
        flex-wrap: nowrap !important;
    }

    .listagem-panel .uti-filter-row > [class*="col-"] {
        max-width: none;
        min-width: 0;
    }

    .listagem-panel .uti-filter-hospital {
        flex: 1.35 1 0;
    }

    .listagem-panel .uti-filter-paciente {
        flex: 1.4 1 0;
    }

    .listagem-panel .uti-filter-matricula {
        flex: 0.95 1 0;
    }

    .listagem-panel .uti-filter-internado {
        flex: 0.95 1 0;
    }

    .listagem-panel .uti-filter-limit {
        flex: 0 0 156px;
        max-width: 156px;
    }

    .listagem-panel .uti-filter-actions {
        flex: 0 0 74px;
        max-width: 74px;
        display: flex;
        align-items: stretch;
        gap: 6px;
        white-space: nowrap;
    }

    .listagem-panel .uti-filter-actions .btn {
        flex: 0 0 32px;
    }

    .th-sortable {
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    .th-sortable .sort-icons a {
        text-decoration: none;
        font-size: 0.72rem;
        color: #ffffff;
        margin-left: 1px;
        opacity: 0.9;
        font-weight: 700;
    }

    .th-sortable .sort-icons a.active {
        color: #ffd966;
        opacity: 1;
        font-weight: 700;
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
<div class="container-fluid form_container listagem-page" id='main-container'>
    <div class="listagem-hero">
        <div class="listagem-hero__copy">
            <div class="listagem-kicker">UTI</div>
            <h1 class="listagem-title">Internações em UTI</h1>
        </div>
    </div>
    <div class="complete-table listagem-panel">
        <div id="navbarToggleExternalContent" class="table-filters">
            <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
            <div>
                <form action="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/internacoes/uti', ENT_QUOTES, 'UTF-8') ?>" id="select-internacao-form" method="GET" style="width:100%;">
                    <?php $pesquisa_nome = (string)listaUtiGetParam('pesquisa_nome', '');
                    $pesqInternado = (string)listaUtiGetParam('pesqInternado', 's');
                    $limite_pag = (int)listaUtiGetParam('limite_pag', 10);
                    $pesquisa_pac = (string)listaUtiGetParam('pesquisa_pac', '');
                    $pesquisa_matricula = (string)listaUtiGetParam('pesquisa_matricula', '');
                    $ordenar = (string)listaUtiGetParam('ordenar', '');
                    ?>
                    <div class="row filter-inline-row uti-filter-row">
                        <div class="col-sm-3 uti-filter-hospital" style="padding:2px !important;padding-left:16px !important;">
                            <input class="form-control form-control-sm" type="text" name="pesquisa_nome"
                                placeholder="Hospital" autofocus
                                value="<?= $pesquisa_nome ?>">
                        </div>
                        <div class="col-sm-3 uti-filter-paciente" style="padding:2px !important">
                            <input class="form-control form-control-sm" type="text" name="pesquisa_pac"
                                placeholder="Paciente"
                                value="<?= $pesquisa_pac ?>">
                        </div>
                        <div class="col-sm-2 uti-filter-matricula" style="padding:2px !important">
                            <input class="form-control form-control-sm" type="text" name="pesquisa_matricula"
                                placeholder="Matrícula"
                                value="<?= htmlspecialchars((string)$pesquisa_matricula) ?>">
                        </div>

                        <div class="col-sm-2 uti-filter-internado" style="padding:2px !important">
                            <select class="form-control sm-3 form-control-sm" id="pesqInternado" name="pesqInternado">
                                <option value="">Busca por Internados</option>
                                <option value="s" <?= $pesqInternado == 's' ? 'selected' : null ?>>Sim</option>
                                <option value="n" <?= $pesqInternado == 'n' ? 'selected' : null ?>>Não</option>
                            </select>
                        </div>
                        <div class="col-sm-1 uti-filter-limit" style="padding:2px !important">
                            <select class="form-control mb-3 form-control-sm" id="limite"
                                name="limite_pag">
                                <option value="">Reg por página</option>
                                <option value="5" <?= $limite_pag == '5' ? 'selected' : null ?>>Reg por pág = 5
                                </option>
                                <option value="10" <?= $limite_pag == '10' ? 'selected' : null ?>>Reg por pág = 10
                                </option>
                                <option value="20" <?= $limite_pag == '20' ? 'selected' : null ?>>Reg por pág = 20
                                </option>
                                <option value="50" <?= $limite_pag == '50' ? 'selected' : null ?>>Reg por pág = 50
                                </option>
                            </select>
                        </div>
                        <div class="col-sm-1 uti-filter-actions" style="padding:2px !important">
                            <button type="submit" class="btn btn-primary btn-filtro-buscar btn-filtro-limpar-icon"><span
                                    class="material-icons">
                                    search
                                </span></button>
                            <a href="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/internacoes/uti', ENT_QUOTES, 'UTF-8') ?>"
                                class="btn btn-light btn-sm btn-filtro-limpar btn-filtro-limpar-icon"
                                title="Limpar filtros" aria-label="Limpar filtros">
                                <i class="bi bi-trash3"></i>
                            </a>
                        </div>
                    </div>
                    <input type="hidden" name="sort_field" value="<?= htmlspecialchars((string)$sortField) ?>">
                    <input type="hidden" name="sort_dir" value="<?= htmlspecialchars((string)$sortDir) ?>">
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
        $QtdTotalIntUTI = new utiDAO($conn, $BASE_URL);
        // METODO DE BUSCA DE PAGINACAO
        $pesquisa_nome = (string)listaUtiGetParam('pesquisa_nome', '');
        $pesqInternado = (string)listaUtiGetParam('pesqInternado', '');
        $limite_pag = (int)listaUtiGetParam('limite_pag', 10);
        $pesquisa_pac = (string)listaUtiGetParam('pesquisa_pac', '');
        $pesquisa_matricula = (string)listaUtiGetParam('pesquisa_matricula', '');
        $data_intern_int = listaUtiGetParam('data_intern_int') ?: null;
        $ordenar = (string)listaUtiGetParam('ordenar', '');
        $uti_internacao = 's';
        $uti_internado = 's';
        // $buscaAtivo = in_array($buscaAtivo, ['s', 'n']) ?: "";
        $condicoes = [
            strlen($pesquisa_nome) ? 'nome_hosp LIKE "%' . $pesquisa_nome . '%"' : null,
            strlen($pesquisa_pac) ? 'nome_pac LIKE "%' . $pesquisa_pac . '%"' : null,
            strlen($pesquisa_matricula) ? 'pa.matricula_pac LIKE "%' . $pesquisa_matricula . '%"' : null,
            strlen($pesqInternado) ? 'internado_int = "' . $pesqInternado . '"' : NULL,
            strlen($uti_internacao) ? 'uti.internacao_uti = "s"' : "s",
            strlen($uti_internado) ? 'uti.internado_uti = "s"' : "s",
            strlen($data_intern_int) ? 'data_intern_int = "' . $data_intern_int . '"' : NULL,
            strlen($auditor) ? 'hos.fk_usuario_hosp = "' . $auditor . '"' : NULL,

        ];
        $condicoes = array_filter($condicoes);
        // REMOVE POSICOES VAZIAS DO FILTRO
        $where = implode(' AND ', $condicoes);
        $sortableColumns = [
            'id_internacao'      => 'ac.id_internacao',
            'nome_hosp'          => 'ho.nome_hosp',
            'nome_pac'           => 'pa.nome_pac',
            'data_intern_int'    => 'ac.data_intern_int',
            'data_internacao_uti'=> 'uti.data_internacao_uti'
        ];
        if ($sortField && isset($sortableColumns[$sortField])) {
            $order = $sortableColumns[$sortField] . ' ' . strtoupper($sortDir);
        } else {
            $order = 'ac.id_internacao DESC';
        }
        // QUANTIDADE InternacaoS
        $qtdIntItens1 = $uti->selectAllUTI($where, $order, $obLimite);
        // print_r($qtdIntItens1);
        $qtdIntItens = count($qtdIntItens1);
        // PAGINACAO
        $qtdIntItens = count($qtdIntItens1);
        $totalcasos = ceil($qtdIntItens / $limite_pag);

        $paginaAtualParam = (int)listaUtiGetParam('pag', 1);
        if ($paginaAtualParam < 1) {
            $paginaAtualParam = 1;
        }
        $obPagination = new pagination($qtdIntItens, $paginaAtualParam, $limite_pag);

        $obLimite = $obPagination->getLimit();

        // PREENCHIMENTO DO FORMULARIO COM QUERY
        $query = $uti->selectAllUTI($where, $order, $obLimite);

        // PAGINACAO
        if ($qtdIntItens > $limite_pag) {
            $paginacao = '';
            $paginas = $obPagination->getPages();
            $pagina = 1;
            $total_pages = count($paginas);

            // CONTROLE DO BLOCO ATUAL DA PAGINACAO
            $blocoAtual = (int)listaUtiGetParam('bl', (int)floor(($paginaAtualParam - 1) / 5) * 5);
            $block_pages = array_filter($paginas, function ($var) use ($blocoAtual) {
                return $var['bloco'] == (($blocoAtual) / 5) + 1;
            });
            $first_page_in_block = reset($block_pages)["pg"];
            $last_page_in_block = end($block_pages)["pg"];
            $first_block = reset($paginas)["bloco"];
            $last_block = end($paginas)["bloco"];
            $current_block = reset($block_pages)["bloco"];
        }
        $paginationBaseParams = [
            'pesquisa_nome'      => $pesquisa_nome,
            'pesquisa_pac'       => $pesquisa_pac,
            'pesquisa_matricula' => $pesquisa_matricula,
            'pesqInternado'      => $pesqInternado,
            'limite_pag'         => $limite_pag,
            'data_intern_int'    => $data_intern_int,
            'sort_field'         => $sortField,
            'sort_dir'           => $sortDir,
        ];

        if (!function_exists('buildInternacaoUtiPaginationUrl')) {
            function buildInternacaoUtiPaginationUrl(array $baseParams, array $override = []): string
            {
                $params = array_merge($baseParams, $override);
                $compact = listaUtiCompactParams($params);
                $query = http_build_query($compact);
                global $BASE_URL;
                $baseUrl = rtrim($BASE_URL, '/') . '/internacoes/uti';
                return $query ? $baseUrl . '?' . $query : $baseUrl;
            }
        }
        ?>

        <div style="margin-top:10px;" id='container'>
            <div id="table-content" class="listagem-table-wrap">

                <!-- <h6 class="page-title">Relatório de internações - UTI</h6> -->
                <table class="table table-sm table-striped table-hover table-condensed uti-list-table">
                    <thead>
                        <tr>
                            <?php
                            $sortableHeaders = [
                                'id_internacao'   => ['label' => 'Id-Int',   'style' => 'width:4%'],
                                'nome_hosp'       => ['label' => 'Hospital', 'style' => 'width:15%'],
                                'nome_pac'        => ['label' => 'Paciente', 'style' => 'width:15%'],
                                'data_intern_int' => ['label' => 'Data internação', 'style' => 'width:8%'],
                                'data_internacao_uti' => ['label' => 'Data internação UTI', 'style' => 'width:8%'],
                            ];
                            foreach ($sortableHeaders as $key => $meta):
                                $ascActive = ($sortField === $key && $sortDir === 'asc');
                                $descActive = ($sortField === $key && $sortDir === 'desc');
                                $ascUrl = buildInternacaoUtiPaginationUrl($paginationBaseParams, ['sort_field' => $key, 'sort_dir' => 'asc', 'pag' => 1]);
                                $descUrl = buildInternacaoUtiPaginationUrl($paginationBaseParams, ['sort_field' => $key, 'sort_dir' => 'desc', 'pag' => 1]);
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
                            <th scope="col" width="4%">Internado</th>
                            <th scope="col" width="4%">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($query as $intern):
                            extract($query);
                        ?>
                        <tr>
                            <td scope="row" class="col-id">
                                <?= $intern["id_internacao"] ?>
                            </td>
                            <td scope="row" class="nome-coluna-table">
                                <?= $intern["nome_hosp"] ?>
                            </td>
                            <td scope="row">
                                <?= $intern["nome_pac"] ?>
                            </td>
                            <td scope="row">
                                <?= date('d/m/Y', strtotime($intern["data_intern_int"])) ?>
                            </td>
                            <td scope="row">
                                <?= !empty($intern["data_internacao_uti"]) ? date('d/m/Y', strtotime($intern["data_internacao_uti"])) : "--" ?>
                            </td>
                            <td scope="row" class="nome-coluna-table">
                                <?= $intern["internado_int"] == "s" ? "Sim" : "Não" ?>
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
                                                onclick="edit('<?= rtrim($BASE_URL, '/') ?>/internacoes/visualizar/<?= (int)$intern['id_internacao'] ?>')"><i
                                                    class="fas fa-eye"
                                                    style="color: rgb(27,156, 55);"></i>Ver</button>
                                        </li>
                                        <li>
                                            <form class="d-inline-block delete-form" action="edit_alta_uti.php"
                                                method="get">
                                                <input type="hidden" name="type" value="update">
                                                <!-- <input type="hidden" name="alta" value="alta"> -->
                                                <input type="hidden" name="id_internacao"
                                                    value="<?= $intern["id_internacao"] ?>">
                                                <button class="btn btn-default"><i
                                                        style="color: rgb(67, 125, 525);"
                                                        class="bi bi-door-open"></i>Alta</button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if ($qtdIntItens == 0): ?>
                        <tr>
                            <td colspan="8" scope="row" class="col-id">
                                Não foram encontrados registros
                            </td>
                        </tr>

                        <?php endif ?>
                    </tbody>
                </table>
                <!-- paginacao que aparece abaixo da tabela -->
                <div style="display: flex;margin-top:20px">

                    <!-- Modal para abrir tela de cadastro -->
                    <div class="modal fade" id="myModal">
                        <div class="modal-dialog  modal-dialog-centered modal-xl">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h4 class="page-title" style="color:white;">Cadastrar Internação</h4>
                                    <p class="page-description" style="color:white; margin-top:5px">Adicione
                                        informações
                                        sobre a internação</p>
                                </div>
                                <div class="modal-body">
                                    <div id="content-php"></div>
                                </div>

                            </div>
                        </div>
                    </div>
                    <!-- Modal para abrir tela de cadastro -->

                    <div class="pagination" style="margin: 0 auto;">
                        <?php if ($total_pages ?? 1 > 1): ?>
                        <ul class="pagination">
                            <?php
                                $blocoAtual = (int)listaUtiGetParam('bl', (int)floor(($paginaAtualParam - 1) / 5) * 5);
                                $paginaAtual = $paginaAtualParam;
                                ?>
                            <?php if ($current_block > $first_block): ?>
                            <li class="page-item">
                                <a class="page-link" id="blocoNovo" href="#"
                                    onclick="loadUtiContent('<?= htmlspecialchars(buildInternacaoUtiPaginationUrl($paginationBaseParams, ['pag' => 1]), ENT_QUOTES, 'UTF-8') ?>')">
                                    <i class="fa-solid fa-angles-left"></i></a>
                            </li>
                            <?php endif; ?>
                            <?php if ($current_block <= $last_block && $last_block > 1 && $current_block != 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="#"
                                    onclick="loadUtiContent('<?= htmlspecialchars(buildInternacaoUtiPaginationUrl($paginationBaseParams, ['pag' => max(1, $paginaAtual - 1)]), ENT_QUOTES, 'UTF-8') ?>')">
                                    <i class="fa-solid fa-angle-left"></i> </a>
                            </li>
                            <?php endif; ?>

                            <?php for ($i = $first_page_in_block; $i <= $last_page_in_block; $i++): ?>
                            <li class="page-item <?php print $paginaAtualParam == $i ? "active" : "" ?>">

                                <a class="page-link" href="#"
                                    onclick="loadUtiContent('<?= htmlspecialchars(buildInternacaoUtiPaginationUrl($paginationBaseParams, ['pag' => $i]), ENT_QUOTES, 'UTF-8') ?>')">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>

                            <?php if ($current_block < $last_block): ?>
                            <li class="page-item">
                                <a class="page-link" id="blocoNovo" href="#"
                                    onclick="loadUtiContent('<?= htmlspecialchars(buildInternacaoUtiPaginationUrl($paginationBaseParams, ['pag' => min($total_pages, $paginaAtual + 1)]), ENT_QUOTES, 'UTF-8') ?>')"><i
                                        class="fa-solid fa-angle-right"></i></a>
                            </li>
                            <?php endif; ?>
                            <?php if ($current_block < $last_block): ?>
                            <li class="page-item">
                                <a class="page-link" id="blocoNovo" href="#"
                                    onclick="loadUtiContent('<?= htmlspecialchars(buildInternacaoUtiPaginationUrl($paginationBaseParams, ['pag' => count($paginas)]), ENT_QUOTES, 'UTF-8') ?>')"><i
                                        class="fa-solid fa-angles-right"></i></a>
                            </li>
                            <?php endif; ?>
                        </ul>
                        <?php endif; ?>
                    </div>

                    <div class="table-counter">
                        <p
                            style="margin-bottom:25px;font-size:1em; font-weight:600; font-family:var(--bs-font-sans-serif); text-align:right">
                            <?php echo "Total: " . $qtdIntItens ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<script>
var utiAliasLongToShort = {
    pesquisa_nome: 'hosp',
    pesquisa_pac: 'pac',
    pesquisa_matricula: 'mat',
    pesqInternado: 'it',
    limite_pag: 'pp',
    data_intern_int: 'di',
    sort_field: 'sf',
    sort_dir: 'sd',
    pag: 'pg',
    bl: 'blc'
};
var utiAliasShortToLong = {};
Object.keys(utiAliasLongToShort).forEach(function(k) {
    utiAliasShortToLong[utiAliasLongToShort[k]] = k;
});
function compactUtiQuery(input) {
    var source = new URLSearchParams(typeof input === 'string' ? input : (input || ''));
    var normalized = {};
    source.forEach(function(v, k) {
        normalized[utiAliasShortToLong[k] || k] = v;
    });
    delete normalized.bl;
    if (!normalized.sort_field) {
        delete normalized.sort_dir;
    }
    var defaults = { pesqInternado: 's', limite_pag: '10', sort_dir: 'desc' };
    var out = new URLSearchParams();
    Object.keys(normalized).forEach(function(longKey) {
        var value = String(normalized[longKey] || '').trim();
        if (!value) return;
        if (defaults[longKey] !== undefined && defaults[longKey] === value) return;
        out.set(utiAliasLongToShort[longKey] || longKey, value);
    });
    return out.toString();
}

function renderUtiTableContent(responseHtml) {
    var tempElement = document.createElement('div');
    tempElement.innerHTML = responseHtml;
    var tableContent = tempElement.querySelector('#table-content');
    if (!tableContent) {
        return false;
    }
    $('#table-content').html(tableContent.innerHTML);
    return true;
}

// ajax para navegacao
function loadUtiContent(url, dataPayload) {
    var requestUrl = url || ($('#select-internacao-form').attr('action') || 'internacoes/uti');
    $.ajax({
        url: requestUrl,
        type: 'GET',
        data: dataPayload || null,
        dataType: 'html',
        success: function(data) {
            var updated = renderUtiTableContent(data);
            if (!updated) {
                return;
            }

            if (dataPayload) {
                var qs = typeof dataPayload === 'string' ? dataPayload : $.param(dataPayload);
                var compactQs = compactUtiQuery(qs);
                var targetUrl = requestUrl + (compactQs ? (requestUrl.indexOf('?') === -1 ? '?' : '&') + compactQs : '');
                window.history.replaceState({}, '', targetUrl);
            } else if (url) {
                try {
                    var parsed = new URL(requestUrl, window.location.origin);
                    var compactFromUrl = compactUtiQuery(parsed.search);
                    window.history.replaceState({}, '', parsed.pathname + (compactFromUrl ? '?' + compactFromUrl : ''));
                } catch (e) {
                    window.history.replaceState({}, '', requestUrl);
                }
            }
        },
        error: function() {
            $('#responseMessage').html('Ocorreu um erro ao atualizar a listagem.');
        }
    });
}

// ajax para submit do formulario de pesquisa
$(document).ready(function() {
    $('#select-internacao-form').on('submit', function(e) {
        e.preventDefault();
        loadUtiContent($(this).attr('action') || 'internacoes/uti', $(this).serialize());
    });
});

$(document).on('click', '#table-content .pagination a.page-link, #table-content .sort-icons a', function(e) {
    var href = $(this).attr('href');
    if (!href || href === '#') {
        return;
    }
    e.preventDefault();
    loadUtiContent(href);
});
</script>


<script src="./js/input-estilo.js"></script>

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
<script src="./js/ajaxNav.js"></script>
