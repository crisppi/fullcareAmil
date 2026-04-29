<?php

require_once("templates/header.php");

include_once("models/internacao.php");
include_once("dao/internacaoDao.php");

include_once("models/patologia.php");
include_once("dao/patologiaDao.php");

include_once("models/paciente.php");
include_once("dao/pacienteDao.php");

include_once("models/hospital.php");
include_once("dao/hospitalDao.php");

include_once("models/gestao.php");
include_once("dao/gestaoDao.php");

include_once("models/pagination.php");

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
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
        error_log('[LIST_GESTAO][SEGURADORA] ' . $e->getMessage());
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

//inicializacao de variaveis

$order = null;
$obLimite = null;
$blocoNovo = null;

$Internacao_geral = new internacaoDAO($conn, $BASE_URL);
$Internacaos = $Internacao_geral->findGeral();

$pacienteDao = new pacienteDAO($conn, $BASE_URL);
$pacientes = $pacienteDao->findGeral($limite, $inicio);

$gestaoDao = new gestaoDAO($conn, $BASE_URL);
$QtdTotalGes = new gestaoDAO($conn, $BASE_URL);
$gestaos = $gestaoDao->findGeral($limite, $inicio);

$senha_int = null;

$hospital_geral = new HospitalDAO($conn, $BASE_URL);
$hospitals = $hospital_geral->findGeral($limite, $inicio);

$patologiaDao = new patologiaDAO($conn, $BASE_URL);
$patologias = $patologiaDao->findGeral();

$internacao = new internacaoDAO($conn, $BASE_URL);

$limite = filter_input(INPUT_GET, 'limite_pag') ? filter_input(INPUT_GET, 'limite_pag') : 10;
$ordenar = filter_input(INPUT_GET, 'ordenar') ? filter_input(INPUT_GET, 'ordenar') : '';

?>
<link rel="stylesheet" href="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/css/listagem_padrao.css', ENT_QUOTES, 'UTF-8') ?>">

<!-- FORMULARIO DE PESQUISAS -->
<div class="container-fluid form_container listagem-page" id='main-container' style="margin-top:4px;">
    <script src="./js/ajaxNav.js"></script>
    <h4 class="page-title" style="font-size:.96rem;margin-bottom:6px;">Gestão Assistencial</h4>
    <hr>
    <style>
    .listagem-page {
        padding: 4px 4px 14px;
    }
    .complete-table {
        padding: 8px 8px 6px;
        border-radius: 16px;
        border: 1px solid #eee8f6;
        background: #fff;
        box-shadow: 0 10px 28px -22px rgba(89, 46, 131, .28);
    }
    .gestao-filter-bar {
        display: flex;
        gap: 6px;
        flex-wrap: nowrap;
        overflow-x: hidden;
        padding-bottom: 6px;
        width: 100%;
    }
    .gestao-filter-bar .filter-item {
        flex: 1 1 0;
        min-width: 0;
    }
    .gestao-filter-bar .filter-item.wide {
        flex: 1.15 1 0;
    }
    .gestao-filter-bar .filter-item.compact {
        flex: .7 1 0;
        min-width: 96px;
    }
    .gestao-filter-bar .filter-item.search-btn {
        flex: 0 0 84px;
        min-width: 84px;
    }
    .gestao-filter-bar .filter-item.clear-btn {
        flex: 0 0 122px;
        min-width: 122px;
    }
    .gestao-filter-bar .form-control,
    .gestao-filter-bar .btn {
        height: 32px !important;
        min-height: 32px;
        margin-top: 0 !important;
        font-size: .72rem;
        line-height: 1.2;
        border-radius: 11px;
    }
    .gestao-filter-bar .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
        padding: 0 10px;
    }
    .gestao-filter-bar .form-control::placeholder {
        font-size: .72rem;
        color: #c4c4c4;
    }
    .gestao-filter-bar .material-icons {
        font-size: 16px !important;
        line-height: 1;
        vertical-align: middle;
    }
    .scope-badge {
        margin: 0 0 6px 0;
        padding: 4px 10px;
        font-size: .68rem;
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
    #table-content .dropdown-toggle,
    #table-content .btn-default {
        font-size: .68rem !important;
    }
    #table-content .dropdown-menu .btn i {
        font-size: .78rem !important;
        margin-right: 4px !important;
    }
    @media (max-width: 1199px) {
        .gestao-filter-bar {
            flex-wrap: wrap;
            overflow-x: visible;
        }
        .gestao-filter-bar .filter-item,
        .gestao-filter-bar .filter-item.wide,
        .gestao-filter-bar .filter-item.compact,
        .gestao-filter-bar .filter-item.search-btn,
        .gestao-filter-bar .filter-item.clear-btn {
            flex: 1 1 220px;
            min-width: 180px;
        }
    }
    .scope-badge { display: inline-flex; align-items: center; gap: 8px; border-radius: 999px; font-weight: 700; background: #f3edff; border: 1px solid #d6c5f7; color: #5e2363; }
    </style>
    <div class="complete-table">
        <?php if ($isSeguradoraRole): ?>
            <div class="scope-badge">
                Escopo: Seguradora <?= htmlspecialchars($seguradoraUserNome !== '' ? $seguradoraUserNome : ('#' . $seguradoraUserId), ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        <div id="navbarToggleExternalContent" class="table-filters">

            <form action="" id="select-internacao-form" method="GET">
                <?php $pesquisa_nome = filter_input(INPUT_GET, 'pesquisa_nome', FILTER_SANITIZE_SPECIAL_CHARS);
                $pesqGestao = filter_input(INPUT_GET, 'pesqGestao');
                $pesqInternado = filter_input(INPUT_GET, 'pesqInternado', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';
                $limite_pag = filter_input(INPUT_GET, 'limite_pag') ?? 10;
                $pesquisa_pac = filter_input(INPUT_GET, 'pesquisa_pac', FILTER_SANITIZE_SPECIAL_CHARS);
                $pesquisa_matricula = filter_input(INPUT_GET, 'pesquisa_matricula', FILTER_SANITIZE_SPECIAL_CHARS);
                $data_intern_int = filter_input(INPUT_GET, 'data_intern_int') ?: null;
                $data_intern_int_max = filter_input(INPUT_GET, 'data_intern_int_max') ?: null;
                ?>

                <div class="gestao-filter-bar">
                    <div class="filter-item wide">
                        <select class="form-control mb-2 form-control-sm" id="pesqGestao" name="pesqGestao">
                            <option value="">Selecione a Gestão</option>
                            <option value="home_care" <?= $pesqGestao == 'home_care' ? 'selected' : null ?>>Home care
                            </option>
                            <option value="desospitalizacao" <?= $pesqGestao == 'desospitalizacao' ? 'selected' : null ?>>
                                Desospitalização</option>
                            <option value="opme" <?= $pesqGestao == 'opme' ? 'selected' : null ?>>Opme</option>
                            <option value="alto" <?= $pesqGestao == 'alto' ? 'selected' : null ?>>Alto custo</option>
                        </select>
                    </div>
                    <div class="filter-item wide">
                        <input class="form-control form-control-sm"
                            type="text" name="pesquisa_nome" placeholder="Selecione o Hospital"
                            value="<?= $pesquisa_nome ?>">
                    </div>
                    <div class="filter-item wide">
                        <input class="form-control form-control-sm"
                            type="text" name="pesquisa_pac" placeholder="Selecione o Paciente"
                            value="<?= $pesquisa_pac ?>">
                    </div>
                    <div class="filter-item wide">
                        <input class="form-control form-control-sm"
                            type="text" name="pesquisa_matricula" placeholder="Matrícula"
                            value="<?= htmlspecialchars((string)$pesquisa_matricula) ?>">
                    </div>
                    <div class="filter-item compact">
                        <input class="form-control form-control-sm"
                            type="text" name="senha_int" placeholder="Senha" value="<?= $senha_int ?>">
                    </div>
                    <div class="filter-item compact">
                        <select class="form-control form-control-sm placeholder" id="pesqInternado"
                            name="pesqInternado">
                            <option value="">Internados</option>
                            <option value="s" <?= $pesqInternado == 's' ? 'selected' : null ?>>Sim</option>
                            <option value="n" <?= $pesqInternado == 'n' ? 'selected' : null ?>>Não</option>
                        </select>
                    </div>
                    <div class="filter-item compact">
                        <select class="form-control mb-3 form-control-sm" id="limite"
                            name="limite">
                            <option value="">Registros</option>
                            <option value="5" <?= $limite == '5' ? 'selected' : null ?>>5</option>
                            <option value="10" <?= $limite == '10' ? 'selected' : null ?>>10</option>
                            <option value="20" <?= $limite == '20' ? 'selected' : null ?>>20</option>
                            <option value="50" <?= $limite == '50' ? 'selected' : null ?>>50</option>
                        </select>
                    </div>
                    <div class="filter-item compact">
                        <input class="form-control form-control-sm" type="date" name="data_intern_int"
                            placeholder="Data Internação Min" value="<?= $data_intern_int ?>">
                    </div>
                    <div class="filter-item compact">
                        <input class="form-control form-control-sm" type="date" name="data_intern_int_max"
                            placeholder="Data Internação Max" value="<?= $data_intern_int_max ?>">
                    </div>
                    <div class="filter-item search-btn">
                        <button type="submit" class="btn btn-primary w-100"
                            style="background-color:#5e2363;border-color:#5e2363">
                            <span class="material-icons">search</span>
                        </button>
                    </div>
                    <div class="filter-item clear-btn">
                        <a href="<?= htmlspecialchars($BASE_URL . 'list_gestao.php', ENT_QUOTES, 'UTF-8') ?>"
                            class="btn btn-outline-secondary w-100 btn-filtro-limpar">Limpar filtros</a>
                    </div>
                </div>
            </form>

            <!-- BASE DAS PESQUISAS -->

            <?php
            // SELECAO DA ENTRADA DO INPUT DE PESQUISA GESTAO
            $pesqGestao = filter_input(INPUT_GET, 'pesqGestao');
            // validacao de lista de hospital por usuario (o nivel sera o filtro)
            if ($_SESSION['nivel'] == 3) {
                $auditor = ($_SESSION['id_usuario']);
            } else {
                $auditor = null;
            };

            $test = isset($_GET);
            if ($test); {
                if ($pesqGestao == 'home_care') {
                    $gestaoHome = "s";
                } else {
                    $gestaoHome = null;
                }
            };
            if ($test); {
                if ($pesqGestao == 'desospitalizacao') {
                    $gestaoDesop = "s";
                } else {
                    $gestaoDesop = null;
                }
            };
            if ($test); {
                if ($pesqGestao == 'opme') {
                    $gestaoOPME = "s";
                } else {
                    $gestaoOPME = null;
                }
            };
            if ($test); {
                if ($pesqGestao == 'alto') {
                    $gestaoAlto = "s";
                } else {
                    $gestaoAlto = null;
                }
            };

            $QtdTotalInt = new internacaoDAO($conn, $BASE_URL);

            // METODO DE BUSCA DE PAGINACAO
            // validacao de lista de hospital por usuario (o nivel sera o filtro)
            if ($_SESSION['nivel'] == 3) {
                $auditor = ($_SESSION['id_usuario']);
            } else {
                $auditor = null;
            };
            $pesquisa_nome = filter_input(INPUT_GET, 'pesquisa_nome');
            $senha_int = filter_input(INPUT_GET, 'senha_int');
            $pesqInternado = filter_input(INPUT_GET, 'pesqInternado');
            $limite_pag = filter_input(INPUT_GET, 'limite_pag') ? filter_input(INPUT_GET, 'limite_pag') : 10;
            $pesquisa_pac = filter_input(INPUT_GET, 'pesquisa_pac');
            $pesquisa_matricula = filter_input(INPUT_GET, 'pesquisa_matricula');
            $ordenar = filter_input(INPUT_GET, 'ordenar') ? filter_input(INPUT_GET, 'ordenar') : '';
            $data_intern_int = filter_input(INPUT_GET, 'data_intern_int');
            $data_intern_int_max = filter_input(INPUT_GET, 'data_intern_int_max');
            if (empty($data_intern_int_max)) {
                $data_intern_int_max = date('Y-m-d'); // Formato de data compatível com SQL
            }

            $whereParams = [];
            $condicoes = [];
            if (strlen((string)$pesquisa_nome)) {
                $condicoes[] = 'ho.nome_hosp LIKE :pesquisa_nome';
                $whereParams[':pesquisa_nome'] = '%' . (string)$pesquisa_nome . '%';
            }
            if (strlen((string)$pesquisa_pac)) {
                $condicoes[] = 'pa.nome_pac LIKE :pesquisa_pac';
                $whereParams[':pesquisa_pac'] = '%' . (string)$pesquisa_pac . '%';
            }
            if (strlen((string)$pesquisa_matricula)) {
                $condicoes[] = 'pa.matricula_pac LIKE :pesquisa_matricula';
                $whereParams[':pesquisa_matricula'] = '%' . (string)$pesquisa_matricula . '%';
            }
            if (strlen((string)$senha_int)) {
                $condicoes[] = 'senha_int LIKE :senha_int';
                $whereParams[':senha_int'] = '%' . (string)$senha_int . '%';
            }
            if (strlen((string)$pesqInternado)) {
                $condicoes[] = 'internado_int = :pesq_internado';
                $whereParams[':pesq_internado'] = (string)$pesqInternado;
            }
            if (strlen((string)$gestaoAlto)) {
                $condicoes[] = 'alto_custo_ges = :gestao_alto';
                $whereParams[':gestao_alto'] = (string)$gestaoAlto;
            }
            if (strlen((string)$gestaoOPME)) {
                $condicoes[] = 'opme_ges = :gestao_opme';
                $whereParams[':gestao_opme'] = (string)$gestaoOPME;
            }
            if (strlen((string)$gestaoDesop)) {
                $condicoes[] = 'desospitalizacao_ges = :gestao_desop';
                $whereParams[':gestao_desop'] = (string)$gestaoDesop;
            }
            if (strlen((string)$gestaoHome)) {
                $condicoes[] = 'home_care_ges = :gestao_home';
                $whereParams[':gestao_home'] = (string)$gestaoHome;
            }
            if (strlen((string)$auditor)) {
                $condicoes[] = 'hos.fk_usuario_hosp = :auditor';
                $whereParams[':auditor'] = (int)$auditor;
            }
            if (strlen((string)$data_intern_int)) {
                $condicoes[] = 'data_intern_int BETWEEN :data_int_ini AND :data_int_fim';
                $whereParams[':data_int_ini'] = (string)$data_intern_int;
                $whereParams[':data_int_fim'] = (string)$data_intern_int_max;
            }
            if ($isSeguradoraRole) {
                if ($seguradoraUserId > 0) {
                    $condicoes[] = 'pa.fk_seguradora_pac = :seguradora_id';
                    $whereParams[':seguradora_id'] = (int)$seguradoraUserId;
                } else {
                    $condicoes[] = '1=0';
                }
            }

            $condicoes = array_filter($condicoes);
            // REMOVE POSICOES VAZIAS DO FILTRO
            $where = implode(' AND ', $condicoes);
            $order = 'id_internacao DESC';
            $gestaoListBaseUrl = rtrim((string)$BASE_URL, '/') . '/gestao';

            $qtdGesItens1 = $QtdTotalGes->selectAllGestaoLis($where, $order, $obLimite, $whereParams);

            $qtdIntItens = count($qtdGesItens1); // total de registros
            $totalcasos = ceil($qtdIntItens / $limite);

            $qtdLinksPagina = ($totalcasos / 5) + 1;

            // PAGINACAO
            $obPagination = new pagination($qtdIntItens, $_GET['pag'] ?? 1, $limite ?? 10);
            $obLimite = $obPagination->getLimit();
            $paginacao = '';
            $paginas = $obPagination->getPages();
            $query = $gestaoDao->selectAllGestaoLis($where, $order, $obLimite, $whereParams);
            // PAGINACAO
            if ($qtdIntItens > $limite) {
                $paginacao = '';
                $paginas = $obPagination->getPages();
                $pagina = 1;
                $total_pages = count($paginas);

                // FUNCAO PARA CONTROLE DO NUMERO DE PAGINAS, UTILIZANDO A QUANTIDADE DE PAGINAS CALCULADAS NA VARIAVEL PAGINAS PELE METODO getPages

                function paginasAtuais($var)
                {
                    $blocoAtual = isset($_GET['bl']) ? $_GET['bl'] : 0;
                    return $var['bloco'] == (($blocoAtual) / 5) + 1;
                }
                $block_pages = array_filter($paginas, "paginasAtuais"); // REFERENCIA FUNCAO CRIADA ACIMA
                $first_page_in_block = reset($block_pages)["pg"];
                $last_page_in_block = end($block_pages)["pg"];
                $first_block = reset($paginas)["bloco"];
                $last_block = end($paginas)["bloco"];
                $current_block = reset($block_pages)["bloco"];
            }

            ?>
            <div>
                <div id="table-content">
                    <!-- <?php include_once("check_nivel.php");
                            ?> -->
                    <table class="table table-sm table-striped  table-hover table-condensed">
                        <thead>
                            <tr>
                                <th scope="col">Id-Int</th>
                                <th scope="col">Internado</th>
                                <th scope="col">Hospital</th>
                                <th scope="col">Paciente</th>
                                <th scope="col">Senha</th>
                                <th scope="col">Data internação</th>
                                <th scope="col">Home care</th>
                                <th scope="col">Desospitalização</th>
                                <th scope="col">OPME</th>
                                <th scope="col">Alto Custo</th>
                                <th scope="col">Evento Adverso</th>
                                <th scope="col">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $isYesFlag = static function ($value): bool {
                                $v = mb_strtolower(trim((string)$value), 'UTF-8');
                                return in_array($v, ['s', 'sim', '1', 'true', 'y', 'yes'], true);
                            };
                            foreach ($query as $intern):
                                extract($query);
                            ?>
                            <tr>
                                <td scope="row" class="col-id">
                                    <?= $intern["id_internacao"] ?>
                                </td>
                                <td scope="row" class="nome-coluna-table">
                                    <?php if ($intern["internado_int"] == "s") {
                                            echo "Sim";
                                        } else {
                                            echo "Não";
                                        }; ?>
                                </td>
                                <td scope="row" class="nome-coluna-table">
                                    <?= $intern["nome_hosp"] ?>
                                </td>
                                <td scope="row">
                                    <?= $intern["nome_pac"] ?>
                                </td>
                                <td scope="row">
                                    <?= $intern["senha_int"] ?>
                                </td>
                                <td scope="row">
                                    <?= date('d/m/Y', strtotime($intern["data_intern_int"])) ?>
                                </td>
                                <td scope="row">
                                    <?php if ($isYesFlag($intern["home_care_ges"] ?? null)) { ?>
                                    <a
                                        href="<?= $BASE_URL ?>show_gestao.php?id_gestao=<?= (int)$intern["id_gestao"] ?>"><i
                                            style="color:red; font-size:1.4em" class="bi bi-house-door">
                                        </i></a>
                                    <?php } else {
                                            echo "--";
                                        }; ?>
                                </td>
                                <td scope="row">
                                    <?php if ($isYesFlag($intern["desospitalizacao_ges"] ?? null)) { ?>
                                    <a
                                        href="<?= $BASE_URL ?>show_gestao.php?id_gestao=<?= (int)$intern["id_gestao"] ?>"><i
                                            style="color:orange; font-size:1.5em" class="bi bi-house-up">
                                        </i></a>
                                    <?php } else {
                                            echo "--";
                                        }; ?>
                                </td>
                                <td scope="row">
                                    <?php if ($isYesFlag($intern["opme_ges"] ?? null)) { ?>
                                    <a
                                        href="<?= $BASE_URL ?>show_gestao.php?id_gestao=<?= (int)$intern["id_gestao"] ?>"><i
                                            style="color:gray; font-size:1.4em" class="fas fa-procedures">
                                        </i></a>
                                    <?php } else {
                                            echo "--";
                                        }; ?>
                                </td>
                                <td scope="row">
                                    <?php if ($isYesFlag($intern["alto_custo_ges"] ?? null)) { ?>
                                    <a
                                        href="<?= $BASE_URL ?>show_gestao.php?id_gestao=<?= (int)$intern["id_gestao"] ?>"><i
                                            style="color:green; font-size:1.4em" class="fas fa-dollar-sign">
                                        </i></a>
                                    <?php } else {
                                            echo "--";
                                        }; ?>
                                </td>
                                <td scope="row">
                                    <?php if ($isYesFlag($intern["evento_adverso_ges"] ?? null)) { ?>
                                    <a
                                        href="<?= $BASE_URL ?>show_gestao.php?id_gestao=<?= (int)$intern["id_gestao"] ?>"><i
                                            style="color:blue; font-size:1.4em" class="bi bi-shield-exclamation">
                                        </i></a>
                                    <?php
                                        } else {
                                            echo "--";
                                        }; ?>
                                </td>

                                <td class="action">
                                    <div class="dropdown">
                                        <button class="btn btn-default dropdown-toggle" id="navbarScrollingDropdown"
                                            role="button" data-bs-toggle="dropdown" style="color:#5e2363"
                                            aria-expanded="false">
                                            <i class="bi bi-stack"></i>
                                        </button>
                                        <ul class="dropdown-menu" aria-labelledby="navbarScrollingDropdown">
                                            <button class="btn btn-default"
                                                onclick="edit('<?= $BASE_URL ?>show_gestao.php?id_gestao=<?= $intern['id_gestao'] ?>')"><i
                                                    class="fas fa-eye"
                                                    style="color: rgb(27,156, 55);"></i>
                                                Ver</button>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if ($qtdIntItens == 0): ?>
                            <tr>
                                <td colspan="12" scope="row" class="col-id">
                                    Sem registros para os filtros aplicados.<?= $isSeguradoraRole ? ' Você está visualizando somente dados da sua seguradora.' : '' ?>
                                </td>
                            </tr>

                            <?php endif ?>
                        </tbody>
                    </table>
                    <!-- salvar variavel qtdIntItens no PHP para passar para JS -->
                    <div style="text-align:right">
                        <input type="hidden" id="qtd" value="<?php echo $qtdIntItens ?>">
                    </div>


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
                                    $blocoAtual = isset($_GET['bl']) ? $_GET['bl'] : 0;
                                    $paginaAtual = isset($_GET['pag']) ? $_GET['pag'] : 1;
                                    ?>
                                <?php if ($current_block > $first_block): ?>
                                <li class="page-item">
                                    <a class="page-link ajax-link" id="blocoNovo"
                                        href="<?= htmlspecialchars($gestaoListBaseUrl . '?pesqGestao=' . urlencode((string)$pesqGestao) . '&pesquisa_nome=' . urlencode((string)$pesquisa_nome) . '&data_intern_int=' . urlencode((string)$data_intern_int) . '&senha_int=' . urlencode((string)$senha_int) . '&pesquisa_pac=' . urlencode((string)$pesquisa_pac) . '&limite_pag=' . urlencode((string)$limite_pag) . '&pag=1&bl=0', ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="fa-solid fa-angles-left"></i></a>
                                </li>
                                <?php endif; ?>
                                <?php if ($current_block <= $last_block && $last_block > 1 && $current_block != 1): ?>
                                <li class="page-item">
                                    <a class="page-link ajax-link"
                                        href="<?= htmlspecialchars($gestaoListBaseUrl . '?pesqGestao=' . urlencode((string)$pesqGestao) . '&pesquisa_nome=' . urlencode((string)$pesquisa_nome) . '&pesquisa_pac=' . urlencode((string)$pesquisa_pac) . '&data_intern_int=' . urlencode((string)$data_intern_int) . '&senha_int=' . urlencode((string)$senha_int) . '&limite_pag=' . urlencode((string)$limite_pag) . '&pag=' . urlencode((string)($paginaAtual - 1)) . '&bl=' . urlencode((string)($blocoAtual - 5)), ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="fa-solid fa-angle-left"></i> </a>
                                </li>
                                <?php endif; ?>

                                <?php for ($i = $first_page_in_block; $i <= $last_page_in_block; $i++): ?>
                                <li class="page-item <?php print ($_GET['pag'] ?? 1) == $i ? "active" : "" ?>">

                                    <a class="page-link ajax-link"
                                        href="<?= htmlspecialchars($gestaoListBaseUrl . '?pesqGestao=' . urlencode((string)$pesqGestao) . '&pesquisa_nome=' . urlencode((string)$pesquisa_nome) . '&data_intern_int=' . urlencode((string)$data_intern_int) . '&senha_int=' . urlencode((string)$senha_int) . '&pesquisa_pac=' . urlencode((string)$pesquisa_pac) . '&limite_pag=' . urlencode((string)$limite_pag) . '&pag=' . urlencode((string)$i) . '&bl=' . urlencode((string)$blocoAtual), ENT_QUOTES, 'UTF-8') ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>

                                <?php if ($current_block < $last_block): ?>
                                <li class="page-item">
                                    <a class="page-link ajax-link" id="blocoNovo"
                                        href="<?= htmlspecialchars($gestaoListBaseUrl . '?pesqGestao=' . urlencode((string)$pesqGestao) . '&pesquisa_nome=' . urlencode((string)$pesquisa_nome) . '&data_intern_int=' . urlencode((string)$data_intern_int) . '&senha_int=' . urlencode((string)$senha_int) . '&pesquisa_pac=' . urlencode((string)$pesquisa_pac) . '&limite_pag=' . urlencode((string)$limite_pag) . '&pag=' . urlencode((string)$i) . '&bl=' . urlencode((string)($blocoAtual + 5)), ENT_QUOTES, 'UTF-8') ?>"><i
                                            class="fa-solid fa-angle-right"></i></a>
                                </li>
                                <?php endif; ?>
                                <?php if ($current_block < $last_block): ?>
                                <li class="page-item">
                                    <a class="page-link ajax-link" id="blocoNovo"
                                        href="<?= htmlspecialchars($gestaoListBaseUrl . '?pesqGestao=' . urlencode((string)$pesqGestao) . '&pesquisa_nome=' . urlencode((string)$pesquisa_nome) . '&data_intern_int=' . urlencode((string)$data_intern_int) . '&senha_int=' . urlencode((string)$senha_int) . '&pesquisa_pac=' . urlencode((string)$pesquisa_pac) . '&limite_pag=' . urlencode((string)$limite_pag) . '&pag=' . urlencode((string)count($paginas)) . '&bl=' . urlencode((string)(($last_block - 1) * 5)), ENT_QUOTES, 'UTF-8') ?>"><i
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
    // ajax para submit do formulario de pesquisa
    $(document).ready(function() {
        $('#select-internacao-form').submit(function(e) {
            e.preventDefault(); // Impede o comportamento padrão de enviar o formulário

            var formData = $(this).serialize(); // Serializa os dados do formulário

            $.ajax({
                url: $(this).attr('action'), // URL do formulário
                type: $(this).attr('method'), // Método do formulário (POST)
                data: formData, // Dados serializados do formulário
                success: function(response) {
                    // Crie um elemento temporário para armazenar a resposta HTML
                    var tempElement = document.createElement('div');
                    tempElement.innerHTML = response;

                    // Encontre o elemento com o ID "table-content" dentro do elemento temporário
                    var tableContent = tempElement.querySelector('#table-content');
                    if (tableContent) {
                        $('#table-content').html(tableContent.innerHTML);
                    } else {
                        $('#table-content').html(response);
                    }
                    if (typeof window.applyHeaderSortOnListPages === 'function') {
                        window.applyHeaderSortOnListPages();
                    }
                },
                error: function() {
                    $('#responseMessage').html('Ocorreu um erro ao enviar o formulário.');
                }
            });
        });
    });

    $(document).on('click', '#table-content a.ajax-link', function(e) {
        e.preventDefault();
        var url = $(this).attr('href');
        if (url) loadContent(url);
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

    @media (min-width: 992px) {
        #table-content td.action .dropdown:hover>.dropdown-menu {
            display: block;
            margin-top: 0;
        }
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
