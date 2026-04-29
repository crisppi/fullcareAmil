<?php
ob_start();

require_once("templates/header.php");
require_once("models/message.php");

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(16));
}

include_once("models/internacao.php");
include_once("dao/internacaoDao.php");

include_once("models/patologia.php");
include_once("dao/patologiaDao.php");

include_once("models/paciente.php");
include_once("dao/pacienteDao.php");

include_once("models/hospital.php");
include_once("dao/hospitalDao.php");

include_once("models/alta.php");
include_once("dao/altaDao.php");

include_once("models/pagination.php");

if (!function_exists('listaAltaGetParam')) {
    function listaAltaGetParam(string $longKey, $default = null)
    {
        static $shortToLong = [
            'hosp' => 'pesquisa_nome',
            'pac'  => 'pesquisa_pac',
            'mat'  => 'pesquisa_matricula',
            'it'   => 'pesqInternado',
            'pp'   => 'limite',
            'ord'  => 'ordenar',
            'di'   => 'data_alta',
            'df'   => 'data_alta_max',
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

if (!function_exists('listaAltaCompactParams')) {
    function listaAltaCompactParams(array $params): array
    {
        $defaults = ['pesqInternado' => 's', 'limite' => '10'];
        $longToShort = [
            'pesquisa_nome'      => 'hosp',
            'pesquisa_pac'       => 'pac',
            'pesquisa_matricula' => 'mat',
            'pesqInternado'      => 'it',
            'limite'             => 'pp',
            'ordenar'            => 'ord',
            'data_alta'          => 'di',
            'data_alta_max'      => 'df',
            'pag'                => 'pg',
            'bl'                 => 'blc',
        ];

        $clean = [];
        foreach ($params as $key => $value) {
            if ($value === null || $value === '' || $value === false) continue;
            $value = (string)$value;
            if (isset($defaults[$key]) && $defaults[$key] === $value) continue;
            $clean[$key] = $value;
        }
        if (empty($clean['data_alta'])) unset($clean['data_alta_max']);
        unset($clean['bl']);

        $compact = [];
        foreach ($clean as $key => $value) {
            $compact[$longToShort[$key] ?? $key] = $value;
        }
        return $compact;
    }
}

$somenteListaAltas = isset($somenteListaAltas) ? (bool)$somenteListaAltas : false;

$normCargoAccess = static function ($txt): string {
    $txt = mb_strtolower(trim((string)$txt), 'UTF-8');
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $txt);
    $txt = $ascii !== false ? $ascii : $txt;
    return preg_replace('/[^a-z]/', '', $txt);
};
$isSeguradoraRole = (strpos($normCargoAccess($_SESSION['cargo'] ?? ''), 'seguradora') !== false);
$seguradoraUserId = (int)($_SESSION['fk_seguradora_user'] ?? 0);
if ($isSeguradoraRole && $seguradoraUserId <= 0) {
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
        error_log('[LIST_ALTA][SEGURADORA] ' . $e->getMessage());
    }
}
$seguradoraUserNome = '';
if ($isSeguradoraRole && $seguradoraUserId > 0) {
    try {
        $stmtSegNome = $conn->prepare("SELECT seguradora_seg FROM tb_seguradora WHERE id_seguradora = :id LIMIT 1");
        $stmtSegNome->bindValue(':id', $seguradoraUserId, PDO::PARAM_INT);
        $stmtSegNome->execute();
        $seguradoraUserNome = (string)($stmtSegNome->fetchColumn() ?: '');
    } catch (Throwable $e) {
        $seguradoraUserNome = '';
    }
}

$altaDao    = new altaDAO($conn, $BASE_URL);
$internacao = new internacaoDAO($conn, $BASE_URL);

/* ===================== FILTROS VIA GET ===================== */

$pesquisa_nome   = (string)listaAltaGetParam('pesquisa_nome', '');
$pesquisa_pac    = (string)listaAltaGetParam('pesquisa_pac', '');
$pesquisa_matricula = (string)listaAltaGetParam('pesquisa_matricula', '');
$pesqInternado   = (string)listaAltaGetParam('pesqInternado', 's');
$limite          = (int)listaAltaGetParam('limite', 10);
$ordenar         = (string)listaAltaGetParam('ordenar', '');
$data_alta       = (string)listaAltaGetParam('data_alta', '');
$data_alta_max   = (string)listaAltaGetParam('data_alta_max', '');

if ($data_alta && !$data_alta_max) {
    $data_alta_max = date('Y-m-d');
}

/* ===================== WHERE (MESMA LÓGICA DO EXPORT) ===================== */

$condicoes = [];

// Hospital (ho.nome_hosp)
if (strlen(trim((string)$pesquisa_nome)) > 0) {
    $condicoes[] = 'ho.nome_hosp LIKE "%' . $pesquisa_nome . '%"';
}

// Paciente (pa.nome_pac)
if (strlen(trim((string)$pesquisa_pac)) > 0) {
    $condicoes[] = 'pa.nome_pac LIKE "%' . $pesquisa_pac . '%"';
}
if (strlen(trim((string)$pesquisa_matricula)) > 0) {
    $condicoes[] = 'pa.matricula_pac LIKE "%' . $pesquisa_matricula . '%"';
}

// Data de alta
if (strlen(trim((string)$data_alta)) > 0) {
    $ini = $data_alta;
    $fim = $data_alta_max ?: $data_alta;
    $condicoes[] = 'alta.data_alta_alt BETWEEN "' . $ini . '" AND "' . $fim . '"';
}
if ($isSeguradoraRole) {
    $condicoes[] = $seguradoraUserId > 0 ? ('pa.fk_seguradora_pac = ' . $seguradoraUserId) : '1=0';
}

$condicoes = array_filter($condicoes);
$where     = implode(' AND ', $condicoes);

/* ===================== CONTAGEM + PAGINAÇÃO ===================== */

$order = $ordenar ?: 'id_internacao DESC';
$qtdIntItens1 = $altaDao->findAltaWhere($where, $order, null);
$qtdIntItens  = is_countable($qtdIntItens1) ? count($qtdIntItens1) : 0;

$paginaAtualParam = (int)listaAltaGetParam('pag', 1);
if ($paginaAtualParam < 1) {
    $paginaAtualParam = 1;
}
$obPagination = new pagination($qtdIntItens, $paginaAtualParam, $limite ?? 10);
$obLimite     = $obPagination->getLimit();

$query = $altaDao->findAltaWhere($where, $order, $obLimite ?: null);

if ($qtdIntItens > $limite) {
    $paginas     = $obPagination->getPages();
    $total_pages = count($paginas);

    $paginasAtuais = function ($var) use ($paginaAtualParam) {
        $blocoAtual = (int)listaAltaGetParam('bl', (int)floor(($paginaAtualParam - 1) / 5) * 5);
        return $var['bloco'] == (($blocoAtual) / 5) + 1;
    };

    $block_pages         = array_filter($paginas, $paginasAtuais);
    $first_page_in_block = $block_pages ? reset($block_pages)["pg"] : 1;
    $last_page_in_block  = $block_pages ? end($block_pages)["pg"]   : 1;
    $first_block         = $paginas ? reset($paginas)["bloco"]      : 1;
    $last_block          = $paginas ? end($paginas)["bloco"]        : 1;
    $current_block       = $block_pages ? reset($block_pages)["bloco"] : 1;
} else {
    $total_pages = 1;
    $first_page_in_block = $last_page_in_block = $first_block = $last_block = $current_block = 1;
    $paginas = [];
    $block_pages = [];
}

$paginationParams = [
    'pesquisa_nome' => $pesquisa_nome,
    'pesquisa_pac' => $pesquisa_pac,
    'pesquisa_matricula' => $pesquisa_matricula,
    'pesqInternado' => $pesqInternado,
    'limite' => $limite,
    'ordenar' => $ordenar,
    'data_alta' => $data_alta,
    'data_alta_max' => $data_alta_max
];

$buildListaAltaLink = function($pagina, $bloco) use ($paginationParams, $BASE_URL, $somenteListaAltas) {
    $params = $paginationParams;
    $params = listaAltaCompactParams($params);

    $pathBase = $somenteListaAltas ? 'listas/altas' : 'internacoes/reverter-alta';
    $pagina = max(1, (int)$pagina);
    $path = rtrim($BASE_URL, '/') . '/' . $pathBase . '/pagina/' . $pagina;

    $query = http_build_query($params);
    return $path . ($query ? '?' . $query : '');
};

?>
<link rel="stylesheet" href="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/css/listagem_padrao.css', ENT_QUOTES, 'UTF-8') ?>">
<style>
    /* Chips roxos para seleção de campos (modal export) */
    .export-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 14px;
        border-radius: 999px;
        background-color: #5e2363;
        color: #ffffff;
        font-size: 0.85rem;
        border: none;
        cursor: pointer;
        margin: 4px 6px 4px 0;
        white-space: nowrap;
    }

    .export-pill.inactive {
        background-color: #f1f1f1;
        color: #5e2363;
        border: 1px solid #5e2363;
    }

    .export-pill i {
        font-size: 0.8rem;
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

    .tabela-altas thead th {
        padding: 0.8rem 0.95rem;
        font-size: 0.82rem;
    }

    .tabela-altas tbody td,
    .tabela-altas tbody th {
        padding: 0.78rem 0.95rem;
        font-size: 0.82rem;
        vertical-align: middle;
    }
    .scope-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin: 0 0 8px 16px;
        padding: 6px 12px;
        border-radius: 999px;
        font-size: .82rem;
        font-weight: 700;
        background: #f3edff;
        border: 1px solid #d6c5f7;
        color: #5e2363;
    }

    .alta-list-header .page-title {
        margin: 0;
    }

    .alta-list-header .listagem-hero__copy {
        display: flex;
        align-items: center;
    }

    .alta-list-header .listagem-hero__actions .btn {
        text-decoration: none;
    }
</style>

<div class="container-fluid form_container listagem-page" id="main-container" style="margin-top:-25px;">

    <div class="listagem-hero alta-list-header">
        <div class="listagem-hero__copy">
            <h4 class="page-title">Alta Hospitalar</h4>
        </div>

        <div class="listagem-hero__actions">
            <a href="#" id="btnExportExcelAlta" class="btn listagem-btn-top listagem-btn-top--green">
                <i class="fa-solid fa-file-excel me-1"></i> Exportar Excel
            </a>
        </div>
    </div>

    <div class="complete-table listagem-panel">
        <?php if ($isSeguradoraRole): ?>
            <div class="scope-badge">
                Escopo: Seguradora <?= htmlspecialchars($seguradoraUserNome !== '' ? $seguradoraUserNome : ('#' . $seguradoraUserId), ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        <div id="navbarToggleExternalContent" class="table-filters">
            <div>
                <form action="" id="select-internacao-form" method="GET">
                    <div class="row filter-inline-row">
                        <div class="col-sm-2" style="padding:2px !important;padding-left:16px !important;">
                            <input class="form-control form-control-sm" style="margin-top:7px;" type="text"
                                name="pesquisa_nome" placeholder="Selecione o Hospital"
                                value="<?= htmlspecialchars((string)$pesquisa_nome) ?>">
                        </div>
                        <div class="col-sm-2" style="padding:2px !important">
                            <input class="form-control form-control-sm" style="margin-top:7px;" type="text"
                                name="pesquisa_pac" placeholder="Selecione o Paciente"
                                value="<?= htmlspecialchars((string)$pesquisa_pac) ?>">
                        </div>
                        <div class="col-sm-2" style="padding:2px !important">
                            <input class="form-control form-control-sm" style="margin-top:7px;" type="text"
                                name="pesquisa_matricula" placeholder="Matrícula"
                                value="<?= htmlspecialchars((string)$pesquisa_matricula) ?>">
                        </div>

                        <div class="col-sm-1" style="padding:2px !important">
                            <select class="form-control mb-3 form-control-sm" style="margin-top:7px;" id="limite"
                                name="limite">
                                <option value="">Reg por página</option>
                                <option value="5" <?= $limite == 5  ? 'selected' : null ?>>Reg por pág = 5</option>
                                <option value="10" <?= $limite == 10 ? 'selected' : null ?>>Reg por pág = 10</option>
                                <option value="20" <?= $limite == 20 ? 'selected' : null ?>>Reg por pág = 20</option>
                                <option value="50" <?= $limite == 50 ? 'selected' : null ?>>Reg por pág = 50</option>
                            </select>
                        </div>

                        <div class="col-sm-2" style="padding:2px !important">
                            <select class="form-control mb-3 form-control-sm" style="margin-top:7px;" id="ordenar"
                                name="ordenar">
                                <option value="">Classificar por</option>
                                <option value="id_internacao" <?= $ordenar == 'id_internacao' ? 'selected' : null ?>>
                                    Internação
                                </option>
                                <option value="nome_pac" <?= $ordenar == 'nome_pac' ? 'selected' : null ?>>
                                    Paciente
                                </option>
                                <option value="nome_hosp" <?= $ordenar == 'nome_hosp' ? 'selected' : null ?>>
                                    Hospital
                                </option>
                                <option value="data_alta_alt" <?= $ordenar == 'data_alta_alt' ? 'selected' : null ?>>
                                    Data Alta
                                </option>
                            </select>
                        </div>

                        <div class="col-sm-1" style="padding:2px !important">
                            <input class="form-control form-control-sm" type="date" style="margin-top:7px;"
                                name="data_alta" placeholder="Data Alta Min"
                                value="<?= htmlspecialchars((string)$data_alta) ?>">
                        </div>

                        <div class="col-sm-1" style="padding:2px !important">
                            <input class="form-control form-control-sm" type="date" style="margin-top:7px;"
                                name="data_alta_max" placeholder="Data Alta Max"
                                value="<?= htmlspecialchars((string)$data_alta_max) ?>">
                        </div>

                        <div class="col-sm-1 d-flex align-items-start gap-2" style="padding:2px !important">
                            <button type="submit" class="btn btn-primary btn-filtro-buscar btn-filtro-limpar-icon"
                                style="background-color:#5e2363;width:42px;height:32px;margin-top:7px;border-color:#5e2363">
                                <span class="material-icons" style="margin-left:-3px;margin-top:-2px;">search</span>
                            </button>
                            <a class="btn btn-light btn-sm btn-filtro-limpar btn-filtro-limpar-icon" style="margin-top:7px;"
                                href="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/' . ($somenteListaAltas ? 'listas/altas' : 'internacoes/reverter-alta'), ENT_QUOTES, 'UTF-8') ?>">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- BASE DAS PESQUISAS -->
        <div>
            <div id="table-content" class="listagem-table-wrap">

                <table class="table table-sm table-striped table-hover table-condensed tabela-altas">
                    <thead>
                        <tr>
                            <th scope="col" width="3%">Id-Int</th>
                            <th scope="col" width="3%">UTI</th>
                            <th scope="col" width="14%">Hospital</th>
                            <th scope="col" width="14%">Paciente</th>
                            <th scope="col" width="7%">Tipo Alta</th>
                            <th scope="col" width="8%">Data Alta</th>
                            <th scope="col" width="6%">Ações</th>
                            <?php if (!$somenteListaAltas): ?>
                            <th scope="col" width="4%">Remover</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($query as $intern): ?>
                            <tr style="font-size:15px">
                                <td scope="row" class="col-id">
                                    <?= htmlspecialchars((string)$intern["fk_id_int_alt"]) ?>
                                </td>
                                <td scope="row" class="col-id">
                                    <?= !empty($intern["id_uti"]) ? 'Sim' : 'Não' ?>
                                </td>
                                <td scope="row">
                                    <?= htmlspecialchars((string)$intern["nome_hosp"]) ?>
                                </td>
                                <td scope="row">
                                    <?= htmlspecialchars((string)$intern["nome_pac"]) ?>
                                </td>
                                <td scope="row">
                                    <?= htmlspecialchars((string)$intern["tipo_alta_alt"]) ?>
                                </td>
                                <td scope="row">
                                    <?= htmlspecialchars(date('d/m/Y', strtotime((string)$intern["data_alta_alt"]))) ?>
                                </td>
                                <td scope="row">
                                    <a class="btn btn-sm btn-outline-primary"
                                       href="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/internacoes/visualizar/' . (int)$intern["fk_id_int_alt"], ENT_QUOTES, 'UTF-8') ?>"
                                       title="Visualizar internação">
                                        <i class="fa-solid fa-eye me-1"></i> Ver
                                    </a>
                                </td>
                                <?php if (!$somenteListaAltas): ?>
                                <td>
                                    <input type="checkbox" class="ckAlta" value="<?= (int)$intern['id_alta'] ?>">
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>

                        <?php if ($qtdIntItens == 0): ?>
                            <tr>
                                <td colspan="<?= !$somenteListaAltas ? 8 : 7 ?>" scope="row" class="col-id" style="font-size:15px">
                                    Sem registros para os filtros aplicados.<?= $isSeguradoraRole ? ' Você está visualizando somente dados da sua seguradora.' : '' ?>
                                </td>
                            </tr>
                        <?php endif ?>
                    </tbody>
                </table>

                <div style="text-align:right">
                    <input type="hidden" id="qtd" value="<?= (int)$qtdIntItens ?>">
                </div>

                <div style="display: flex;margin-top:20px">
                    <div class="pagination" style="margin: 0 auto;">

                        <?php if (($total_pages ?? 1) > 1): ?>
                            <ul class="pagination">
                                <?php
                                $blocoAtual  = (int)listaAltaGetParam('bl', (int)floor(($paginaAtualParam - 1) / 5) * 5);
                                $paginaAtual = $paginaAtualParam;
                                ?>
                                <?php if ($current_block > $first_block): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?= $buildListaAltaLink(1, 0) ?>">
                                            <i class="fa-solid fa-angles-left"></i></a>
                                    </li>
                                <?php endif; ?>
                                <?php if ($current_block <= $last_block && $last_block > 1 && $current_block != 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?= $buildListaAltaLink($paginaAtual - 1, $blocoAtual - 5) ?>">
                                            <i class="fa-solid fa-angle-left"></i> </a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = $first_page_in_block; $i <= $last_page_in_block; $i++): ?>
                                    <li class="page-item <?= $paginaAtualParam == $i ? "active" : "" ?>">
                                        <a class="page-link" href="<?= $buildListaAltaLink($i, $blocoAtual) ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($current_block < $last_block): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?= $buildListaAltaLink($paginaAtual + 1, $blocoAtual + 5) ?>">
                                            <i class="fa-solid fa-angle-right"></i></a>
                                    </li>
                                <?php endif; ?>
                                <?php if ($current_block < $last_block): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?= $buildListaAltaLink(count($paginas), ($last_block - 1) * 5) ?>">
                                            <i class="fa-solid fa-angles-right"></i></a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <?php if (!$somenteListaAltas): ?>
                    <div class="col-sm-3">
                        <button id="btnRemoveAltas" class="btn btn-outline-danger">
                            <i class="fa-solid fa-trash-can me-1"></i> Remover alta(s) selecionada(s)
                        </button>
                    </div>
                    <?php endif; ?>

                    <div class="table-counter">
                        <p style="margin-bottom:25px;font-size:1em;font-weight:600;
                                  font-family:var(--bs-font-sans-serif);text-align:right">
                            <?= "Total: " . (int)$qtdIntItens ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!$somenteListaAltas): ?>
<div class="modal fade" id="modalReverterAlta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:1rem;">
            <div class="modal-header">
                <h5 class="modal-title">Reverter alta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                Confirmar a reversão de <strong><span id="qtdAltasSel">0</span></strong> alta(s)?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btnConfirmReverter" class="btn btn-danger">Confirmar reversão</button>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Modal: Mensagem (info/erro) -->
<div class="modal fade" id="modalMsg" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:1rem;">
            <div class="modal-header">
                <h5 class="modal-title" id="modalMsgTitle">Aviso</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body" id="modalMsgBody">...</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Selecionar campos do Excel -->
<div class="modal fade" id="modalExportAltaCampos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius:1rem;">
            <div class="modal-header">
                <h5 class="modal-title">Campos a exibir/exportar para o Excel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <div class="modal-body">

                <!-- Barra Selecionar todos / Limpar -->
                <div class="export-pill-toolbar">
                    <button type="button" class="btn btn-light btn-sm" id="btnExportSelectAllAlta">
                        ✓ Selecionar todos
                    </button>
                    <button type="button" class="btn btn-light btn-sm" id="btnExportClearAlta">
                        ✕ Limpar
                    </button>
                </div>

                <!-- Chips -->
                <div class="mb-2">
                    <!-- ID Internação -->
                    <button type="button" class="export-pill" data-target="#cbColIdInt">
                        # ID da internação
                    </button>
                    <input type="checkbox" class="d-none" id="cbColIdInt" name="colsAlta[]" value="id_int" checked>

                    <!-- Hospital -->
                    <button type="button" class="export-pill" data-target="#cbColHosp">
                        <i class="fa-solid fa-hospital"></i> Hospital
                    </button>
                    <input type="checkbox" class="d-none" id="cbColHosp" name="colsAlta[]" value="hosp" checked>

                    <!-- Paciente -->
                    <button type="button" class="export-pill" data-target="#cbColPac">
                        <i class="fa-solid fa-user"></i> Nome do paciente
                    </button>
                    <input type="checkbox" class="d-none" id="cbColPac" name="colsAlta[]" value="pac" checked>

                    <!-- Tipo Alta -->
                    <button type="button" class="export-pill" data-target="#cbColTipoAlta">
                        <i class="fa-regular fa-square-check"></i> Tipo alta
                    </button>
                    <input type="checkbox" class="d-none" id="cbColTipoAlta" name="colsAlta[]" value="tipo_alta"
                        checked>

                    <!-- Data Alta -->
                    <button type="button" class="export-pill" data-target="#cbColDataAlta">
                        <i class="fa-regular fa-calendar"></i> Data alta
                    </button>
                    <input type="checkbox" class="d-none" id="cbColDataAlta" name="colsAlta[]" value="data_alta"
                        checked>

                    <!-- UTI -->
                    <button type="button" class="export-pill" data-target="#cbColUti">
                        UTI
                    </button>
                    <input type="checkbox" class="d-none" id="cbColUti" name="colsAlta[]" value="uti" checked>

                    <!-- Senha -->
                    <button type="button" class="export-pill inactive" data-target="#cbColSenha">
                        Senha
                    </button>
                    <input type="checkbox" class="d-none" id="cbColSenha" name="colsAlta[]" value="senha">

                    <!-- Matrícula -->
                    <button type="button" class="export-pill inactive" data-target="#cbColMatricula">
                        Matrícula
                    </button>
                    <input type="checkbox" class="d-none" id="cbColMatricula" name="colsAlta[]" value="matricula">

                    <!-- Evolução (relatório) -->
                    <button type="button" class="export-pill inactive" data-target="#cbColEvolucao">
                        Relatório / Evolução
                    </button>
                    <input type="checkbox" class="d-none" id="cbColEvolucao" name="colsAlta[]" value="evolucao">

                    <!-- Ações -->
                    <button type="button" class="export-pill inactive" data-target="#cbColAcoes">
                        Ações
                    </button>
                    <input type="checkbox" class="d-none" id="cbColAcoes" name="colsAlta[]" value="acoes">

                    <!-- Programação -->
                    <button type="button" class="export-pill inactive" data-target="#cbColProgramacao">
                        Programação
                    </button>
                    <input type="checkbox" class="d-none" id="cbColProgramacao" name="colsAlta[]" value="programacao">

                    <!-- Especialidade -->
                    <button type="button" class="export-pill inactive" data-target="#cbColEspecialidade">
                        Especialidade
                    </button>
                    <input type="checkbox" class="d-none" id="cbColEspecialidade" name="colsAlta[]"
                        value="especialidade">
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btnConfirmExportAlta" class="btn btn-success">
                    Exportar XLSX (Excel)
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js"></script>

<script>
    const SOMENTE_LISTA_ALTAS = <?= $somenteListaAltas ? 'true' : 'false' ?>;
    (function($) {
        var altaAliasLongToShort = {
            pesquisa_nome: 'hosp',
            pesquisa_pac: 'pac',
            pesquisa_matricula: 'mat',
            pesqInternado: 'it',
            limite: 'pp',
            ordenar: 'ord',
            data_alta: 'di',
            data_alta_max: 'df',
            pag: 'pg',
            bl: 'blc'
        };
        var altaAliasShortToLong = {};
        Object.keys(altaAliasLongToShort).forEach(function(k) {
            altaAliasShortToLong[altaAliasLongToShort[k]] = k;
        });
        var altaDefaults = {
            pesqInternado: 's',
            limite: '10'
        };

        function compactAltaQuery(input) {
            var source = new URLSearchParams(typeof input === 'string' ? input : (input || ''));
            var normalized = {};
            source.forEach(function(v, k) {
                normalized[altaAliasShortToLong[k] || k] = v;
            });
            if (!normalized.data_alta) {
                delete normalized.data_alta_max;
            }
            delete normalized.bl;

            var out = new URLSearchParams();
            Object.keys(normalized).forEach(function(longKey) {
                var value = String(normalized[longKey] || '').trim();
                if (!value) return;
                if (altaDefaults[longKey] !== undefined && altaDefaults[longKey] === value) return;
                out.set(altaAliasLongToShort[longKey] || longKey, value);
            });
            return out.toString();
        }

        function showMsg(title, body) {
            $('#modalMsgTitle').text(title || 'Aviso');
            $('#modalMsgBody').html(body || '');
            new bootstrap.Modal(document.getElementById('modalMsg')).show();
        }

        function renderAltaTable(responseHtml) {
            var temp = document.createElement('div');
            temp.innerHTML = responseHtml;
            var tableContent = temp.querySelector('#table-content');
            if (!tableContent) {
                return false;
            }
            $('#table-content').html(tableContent.innerHTML);
            return true;
        }

        function loadAltaList(url, dataPayload) {
            var requestUrl = url || window.location.pathname;
            $.ajax({
                url: requestUrl,
                type: 'GET',
                data: dataPayload || null,
                success: function(response) {
                    if (!renderAltaTable(response)) {
                        return;
                    }

                    if (dataPayload) {
                        var qs = typeof dataPayload === 'string' ? dataPayload : $.param(dataPayload);
                        var compactQs = compactAltaQuery(qs);
                        var targetUrl = requestUrl + (compactQs ? (requestUrl.indexOf('?') === -1 ? '?' : '&') + compactQs : '');
                        window.history.replaceState({}, '', targetUrl);
                    } else if (url) {
                        try {
                            var parsed = new URL(url, window.location.origin);
                            var compactFromUrl = compactAltaQuery(parsed.search);
                            var compactUrl = parsed.pathname + (compactFromUrl ? '?' + compactFromUrl : '');
                            window.history.replaceState({}, '', compactUrl);
                        } catch (e) {
                            window.history.replaceState({}, '', url);
                        }
                    }
                },
                error: function() {
                    showMsg('Erro', 'Ocorreu um erro ao atualizar a listagem.');
                }
            });
        }

        // Abre modal de campos ao clicar em Exportar Excel
        $(document).on('click', '#btnExportExcelAlta', function(e) {
            e.preventDefault();
            e.stopPropagation();
            new bootstrap.Modal(document.getElementById('modalExportAltaCampos')).show();
        });

        // Clique em um chip: alterna estado + checkbox oculto
        $(document).on('click', '.export-pill', function() {
            var $pill = $(this);
            var target = $pill.data('target');
            var $cb = $(target);

            var ativo = !$pill.hasClass('inactive');
            if (ativo) {
                $pill.addClass('inactive');
                $cb.prop('checked', false);
            } else {
                $pill.removeClass('inactive');
                $cb.prop('checked', true);
            }
        });

        // Selecionar todos
        $(document).on('click', '#btnExportSelectAllAlta', function() {
            $('.export-pill').removeClass('inactive');
            $('input[name="colsAlta[]"]').prop('checked', true);
        });

        // Limpar
        $(document).on('click', '#btnExportClearAlta', function() {
            $('.export-pill').addClass('inactive');
            $('input[name="colsAlta[]"]').prop('checked', false);
        });

        // Confirmar exportação
        $(document).on('click', '#btnConfirmExportAlta', function(e) {
            e.preventDefault();

            var cols = [];
            $('input[name="colsAlta[]"]:checked').each(function() {
                cols.push($(this).val());
            });

            if (!cols.length) {
                showMsg('Seleção necessária', 'Selecione pelo menos um campo para exportar.');
                return;
            }

            var query = $('#select-internacao-form').serialize();
            var colsParam = 'cols=' + encodeURIComponent(cols.join(','));

            if (query) {
                query += '&' + colsParam;
            } else {
                query = colsParam;
            }

            var url = '<?= $BASE_URL ?>exportar_excel_list_alta.php';
            if (query) {
                url += '?' + query;
            }

            var modalEl = document.getElementById('modalExportAltaCampos');
            var modalObj = bootstrap.Modal.getInstance(modalEl);
            if (modalObj) modalObj.hide();

            window.open(url, '_blank');
        });

        // Submit filtros (AJAX)
        $(document)
            .off('submit.alta', '#select-internacao-form')
            .on('submit.alta', '#select-internacao-form', function(e) {
                e.preventDefault();
                var $form = $(this);
                loadAltaList($form.attr('action') || window.location.pathname, $form.serialize());
            });

        $(document)
            .off('click.alta', '#table-content .pagination a.page-link, #table-content .sort-icons a')
            .on('click.alta', '#table-content .pagination a.page-link, #table-content .sort-icons a', function(e) {
                var href = $(this).attr('href');
                if (!href || href === '#') {
                    return;
                }
                e.preventDefault();
                loadAltaList(href, null);
            });

        if (!SOMENTE_LISTA_ALTAS) {
            let idsSelecionados = [];

            $(document)
                .off('click.alta', '#btnRemoveAltas')
                .on('click.alta', '#btnRemoveAltas', function(e) {
                    e.preventDefault();
                    idsSelecionados = $('.ckAlta:checked').map(function() {
                        return $(this).val();
                    }).get();
                    if (!idsSelecionados.length) {
                        showMsg('Seleção necessária', 'Selecione pelo menos uma alta para reverter.');
                        return;
                    }
                    $('#qtdAltasSel').text(idsSelecionados.length);
                    new bootstrap.Modal(document.getElementById('modalReverterAlta')).show();
                });

            $(document)
                .off('click.alta', '#btnConfirmReverter')
                .on('click.alta', '#btnConfirmReverter', function() {
                    var $btn = $(this);
                    $btn.prop('disabled', true);

                    $.ajax({
                        url: 'alta_reverter.php',
                        type: 'POST',
                        data: {
                            ids: idsSelecionados
                        },
                        success: function(resp) {
                            const j = (typeof resp === 'string') ? JSON.parse(resp) : resp;
                            if (j && j.ok) {
                                bootstrap.Modal.getInstance(document.getElementById('modalReverterAlta'))
                                    .hide();
                                location.reload();
                            } else {
                                showMsg('Falha', (j && j.msg) ? j.msg : 'Falha ao reverter.');
                            }
                        },
                        error: function() {
                            showMsg('Erro de comunicação', 'Não foi possível contatar o servidor.');
                        },
                        complete: function() {
                            $btn.prop('disabled', false);
                        }
                    });
                });
        }

    })(jQuery);
</script>

<script src="./js/input-estilo.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Iehds+3Ulb9Bn9Plx0x4" crossorigin="anonymous">
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>
<script src="./scripts/cadastro/general.js"></script>
<script src="./js/ajaxNav.js"></script>
