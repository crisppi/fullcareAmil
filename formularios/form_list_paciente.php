<body>
    <script src="https://code.jquery.com/jquery-3.6.3.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js"></script>
    <script src="./scripts/cadastro/general.js"></script>
    <?php
    include_once("globals.php");
    include_once("models/paciente.php");
    include_once("models/message.php");
    include_once("dao/pacienteDao.php");
    include_once("templates/header.php");
    include_once("array_dados.php");

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $normCargoAccess = function ($txt)
    {
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
            error_log('[LIST_PAC][SEGURADORA] ' . $e->getMessage());
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

    if (!function_exists('paciente_escape')) {
        function paciente_escape($valor)
        {
            return htmlentities((string)$valor, ENT_QUOTES, 'UTF-8');
        }
    }

    $autocompletePacientes = [];
    if (isset($conn) && $conn instanceof PDO) {
        try {
            $stmt = $conn->query("
                SELECT nome_pac, matricula_pac, recem_nascido_pac, IFNULL(numero_rn_pac, '') AS numero_rn_pac
                FROM tb_paciente
                WHERE deletado_pac <> 's'
                ORDER BY nome_pac ASC
                LIMIT 200
            ");
            $autocompletePacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $autocompletePacientes = [];
        }
    }

    //Instanciando a classe 
    $paciente = new PacienteDAO($conn, $BASE_URL);
    $QtdTotalpac = new PacienteDAO($conn, $BASE_URL);

    // METODO DE BUSCA DE PAGINACAO
    $busca = trim((string) filter_input(INPUT_GET, 'pesquisa_nome', FILTER_SANITIZE_SPECIAL_CHARS));
    $buscaSeguradora = filter_input(INPUT_GET, 'pesquisa_seguradora', FILTER_SANITIZE_SPECIAL_CHARS);
    if ($isGestorSeguradora) {
        $buscaSeguradora = $seguradoraUserNome !== '' ? $seguradoraUserNome : $buscaSeguradora;
    }

    $pesquisa_nome = filter_input(INPUT_GET, 'pesquisa_nome', FILTER_SANITIZE_SPECIAL_CHARS);
    $buscaAtivo = filter_input(INPUT_GET, 'ativo_pac', FILTER_SANITIZE_SPECIAL_CHARS);
    $limite = filter_input(INPUT_GET, 'limite') ? filter_input(INPUT_GET, 'limite') : 10;
    $ordenar = filter_input(INPUT_GET, 'ordenar') ? filter_input(INPUT_GET, 'ordenar') : 'id_paciente_desc';
    $buscaAtivo = in_array($buscaAtivo, ['s', 'n']) ?: "";
    $pacienteInicio = ' 1 ';
    $buscaMatriculaForcada = $busca;
    if ($busca !== '' && preg_match('/^(.*?)\\s*-\\s*([0-9]+(?:\\s*RN\\s*\\d+)?)$/i', $busca, $matches)) {
        $busca = trim($matches[1]);
        $buscaMatriculaForcada = trim(str_replace(' ', '', strtoupper($matches[2])));
    }

    $nameFilter = $busca !== '' ? 'nome_pac LIKE "%' . $busca . '%"' : '';
    $matriculaFilter = $buscaMatriculaForcada !== '' ? 'CONCAT(
              matricula_pac,
              CASE WHEN recem_nascido_pac = "s" THEN "RN" ELSE "" END,
              IFNULL(numero_rn_pac, "")
          ) LIKE "%' . $buscaMatriculaForcada . '%"' : '';

    $nameMatClause = implode(' OR ', array_filter([$nameFilter, $matriculaFilter]));

    $condicoes = [
        $nameMatClause ? '(' . $nameMatClause . ')' : null,
        strlen($buscaSeguradora) ? 'se.seguradora_seg LIKE "%' . $buscaSeguradora . '%"' : null,
        strlen($buscaAtivo) ? 'ativo_pac = "' . $buscaAtivo . '"' : null,
        strlen($pacienteInicio) ? 'id_paciente > ' . $pacienteInicio . ' ' : null,
        $isGestorSeguradora
            ? ($seguradoraUserId > 0 ? 'pa.fk_seguradora_pac = ' . $seguradoraUserId : '1=0')
            : null,
    ];



    $condicoes = array_filter($condicoes);
    // print_r($condicoes);
    $orderMap = [
        'id_paciente' => 'pa.id_paciente',
        'id_paciente_desc' => 'pa.id_paciente DESC',
        'nome_pac' => 'pa.nome_pac',
        'nome_pac_desc' => 'pa.nome_pac DESC',
        'matricula_pac' => 'pa.matricula_pac',
        'matricula_pac_desc' => 'pa.matricula_pac DESC',
        'cpf_pac' => 'pa.cpf_pac',
        'cpf_pac_desc' => 'pa.cpf_pac DESC',
        'seguradora_seg' => 'se.seguradora_seg',
        'seguradora_seg_desc' => 'se.seguradora_seg DESC',
        'cidade_pac' => 'pa.cidade_pac',
        'cidade_pac_desc' => 'pa.cidade_pac DESC',
    ];
    $order = $orderMap[$ordenar] ?? 'pa.id_paciente DESC';

    // REMOVE POSICOES VAZIAS DO FILTRO
        $where = implode(' AND ', $condicoes);
    $qtdpacItens1 = $QtdTotalpac->selectAllpaciente($where, $order, $obLimite ?? null);
    $qtdIntItens = count($qtdpacItens1); // total de registros
    // PAGINACAO
    $obPagination = new pagination($qtdIntItens, $_GET['pag'] ?? 1, $limite ?? 10);
    $obLimite = $obPagination->getLimit();

    // PREENCHIMENTO DO FORMULARIO COM QUERY
    $query = $paciente->selectAllpaciente($where, $order, $obLimite);

    $totalcasos = ceil($qtdIntItens / 5);

    $pacientePaginationBaseParams = [
        'pesquisa_nome'     => $pesquisa_nome,
        'pesquisa_seguradora'=> $buscaSeguradora,
        'ativo_pac'         => $buscaAtivo,
        'limite'            => $limite,
        'ordenar'           => $ordenar,
    ];

    if (!function_exists('buildPacientePaginationUrl')) {
        function buildPacientePaginationUrl(array $baseParams, array $override = []): string
        {
            $params = array_merge($baseParams, $override);
            $params = array_filter($params, function ($value) {
                return $value !== null && $value !== '';
            });

            $query = http_build_query($params);
            global $BASE_URL;
            $baseUrl = rtrim($BASE_URL, '/') . '/pacientes';

        return $query ? $baseUrl . '?' . $query : $baseUrl;
        }
    }

    $pacSortFieldCurrent = preg_replace('/_desc$/', '', (string)$ordenar);
    $pacSortDirCurrent = (substr((string)$ordenar, -5) === '_desc') ? 'desc' : 'asc';
    $buildPacienteSortUrl = function (string $field) use ($pacSortFieldCurrent, $pacSortDirCurrent, $pacientePaginationBaseParams) {
        $isCurrentField = ($pacSortFieldCurrent === $field);
        $nextDir = ($isCurrentField && $pacSortDirCurrent === 'asc') ? 'desc' : 'asc';
        $nextOrder = ($nextDir === 'desc') ? ($field . '_desc') : $field;
        return buildPacientePaginationUrl($pacientePaginationBaseParams, [
            'ordenar' => $nextOrder,
            'pag' => 1,
            'bl' => 0,
        ]);
    };
    $pacSortIcon = function (string $field) use ($pacSortFieldCurrent, $pacSortDirCurrent): string {
        if ($pacSortFieldCurrent !== $field) {
            return '↕';
        }
        return $pacSortDirCurrent === 'asc' ? '↑' : '↓';
    };

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

    <link rel="stylesheet" href="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/css/listagem_padrao.css', ENT_QUOTES, 'UTF-8') ?>">

    <div class="container-fluid form_container listagem-page" id="main-container" style="margin-top:18px;">
        <div class="listagem-hero">
            <div class="listagem-hero__copy">
                <div class="listagem-kicker">Cadastros</div>
                <h1 class="listagem-title">Pacientes</h1>
                <p class="listagem-subtitle">Pesquise por nome, matrícula e seguradora com um topo mais claro e consistente.</p>
            </div>
            <div class="listagem-hero__actions">
                <a href="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/pacientes/novo', ENT_QUOTES, 'UTF-8') ?>"
                    class="btn listagem-btn-top listagem-btn-top--blue">
                    <i class="fa-solid fa-plus" style='font-size: 1rem;margin-right:5px;'></i>Novo Paciente
                </a>
            </div>
        </div>

        <div class="complete-table">
            <?php if ($isGestorSeguradora): ?>
                <div style="display:inline-flex;align-items:center;gap:8px;margin:0 0 8px 16px;padding:6px 12px;border-radius:999px;font-size:.82rem;font-weight:700;background:#f3edff;border:1px solid #d6c5f7;color:#5e2363;">
                    Escopo: Seguradora <?= paciente_escape($seguradoraUserNome !== '' ? $seguradoraUserNome : ('#' . $seguradoraUserId)) ?>
                </div>
            <?php endif; ?>
            <div id="navbarToggleExternalContent" class="table-filters">
                <form id="form_pesquisa" method="GET">
                    <div class="row">
                        <div class="form-group col-sm-3" style="padding:2px !important;padding-left:16px !important;">
                            <input class="form-control form-control-sm" style="margin-top:7px" type="text"
                                value="<?= paciente_escape($busca) ?>" name="pesquisa_nome" id="pesquisa_nome"
                                placeholder="Pesquisa por nome ou matrícula" list="pacienteSuggestions">
                            <datalist id="pacienteSuggestions">
                                <?php foreach ($autocompletePacientes as $entry):
                                    $matriculaLabel = trim($entry['matricula_pac'] ?? '');
                                    if ($entry['recem_nascido_pac'] === 's' && $entry['numero_rn_pac'] !== '') {
                                        $matriculaLabel .= ' RN' . $entry['numero_rn_pac'];
                                    }
                                    $label = trim($entry['nome_pac'] . ($matriculaLabel ? ' - ' . $matriculaLabel : ''));
                                ?>
                                    <option value="<?= paciente_escape($label) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="form-group col-sm-2" style="padding:2px !important;">
                            <?php if ($isGestorSeguradora): ?>
                                <input type="hidden" name="pesquisa_seguradora" id="pesquisa_seguradora"
                                    value="<?= paciente_escape($seguradoraUserNome !== '' ? $seguradoraUserNome : $buscaSeguradora) ?>">
                                <input class="form-control form-control-sm" style="margin-top:7px;background:#f3edff;color:#6b5b8b" type="text"
                                    value="<?= paciente_escape($seguradoraUserNome !== '' ? $seguradoraUserNome : '-') ?>" readonly>
                            <?php else: ?>
                                <input class="form-control form-control-sm" style="margin-top:7px" type="text"
                                    value="<?= paciente_escape((string)$buscaSeguradora) ?>" name="pesquisa_seguradora" id="pesquisa_seguradora"
                                    placeholder="Pesquisa por seguradora">
                            <?php endif; ?>
                        </div>
                        <div class="col-sm-1" style="padding:2px !important">
                            <select class="form-control mb-3 form-control-sm" style="margin-top:7px;" id="limite"
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
                                style="margin-top:7px;font-size:.8em; color:#878787" id="ordenar" name="ordenar">
                                <option value="">Classificar por</option>
                                <option value="id_paciente_desc" <?= $ordenar == 'id_paciente_desc' ? 'selected' : null ?>>Id
                                    Paciente
                                </option>
                                <option value="nome_pac" <?= $ordenar == 'nome_pac' ? 'selected' : null ?>>Nome Paciente
                                </option>
                                <option value="matricula_pac" <?= $ordenar == 'matricula_pac' ? 'selected' : null ?>>Matrícula</option>
                                <option value="cpf_pac" <?= $ordenar == 'cpf_pac' ? 'selected' : null ?>>CPF</option>
                                <option value="seguradora_seg" <?= $ordenar == 'seguradora_seg' ? 'selected' : null ?>>Seguradora</option>
                                <option value="cidade_pac" <?= $ordenar == 'cidade_pac' ? 'selected' : null ?>>Cidade</option>
                            </select>
                        </div>

                        <div class="form-group col-sm-1" style="padding:2px !important" style="margin:0px 0px 20px 0px">
                            <button type="submit" class="btn btn-primary btn-filtro-buscar btn-filtro-limpar-icon"
                                style="background-color:#5e2363;width:42px;height:32px;margin-top:7px;border-color:#5e2363"><span
                                    class="material-icons" style="margin-left:-3px;margin-top:-2px;">
                                    search
                                </span></button>
                        </div>
                        <div class="form-group col-sm-2" style="padding:2px !important">
                            <a href="<?= htmlspecialchars($BASE_URL . 'pacientes', ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-secondary btn-sm btn-filtro-limpar" style="margin-top:7px;">
                                Limpar filtros
                            </a>
                        </div>


                    </div>
                </form>
            </div>
            <div>
                <div id="table-content">
                    <table class="table table-sm table-striped table-hover table-condensed">
                        <thead>
                            <tr>
                                <th scope="col" data-sort="false">
                                    <a class="rah-sort-link" href="<?= htmlspecialchars($buildPacienteSortUrl('id_paciente'), ENT_QUOTES, 'UTF-8') ?>"
                                        onclick="return paginatePacientes('<?= htmlspecialchars($buildPacienteSortUrl('id_paciente'), ENT_QUOTES, 'UTF-8') ?>');">
                                        <span>Id</span><span class="rah-sort-icon"><?= $pacSortIcon('id_paciente') ?></span>
                                    </a>
                                </th>
                                <th scope="col" data-sort="false">
                                    <a class="rah-sort-link" href="<?= htmlspecialchars($buildPacienteSortUrl('nome_pac'), ENT_QUOTES, 'UTF-8') ?>"
                                        onclick="return paginatePacientes('<?= htmlspecialchars($buildPacienteSortUrl('nome_pac'), ENT_QUOTES, 'UTF-8') ?>');">
                                        <span>Paciente</span><span class="rah-sort-icon"><?= $pacSortIcon('nome_pac') ?></span>
                                    </a>
                                </th>
                                <th scope="col" data-sort="false">
                                    <a class="rah-sort-link" href="<?= htmlspecialchars($buildPacienteSortUrl('matricula_pac'), ENT_QUOTES, 'UTF-8') ?>"
                                        onclick="return paginatePacientes('<?= htmlspecialchars($buildPacienteSortUrl('matricula_pac'), ENT_QUOTES, 'UTF-8') ?>');">
                                        <span>Matrícula</span><span class="rah-sort-icon"><?= $pacSortIcon('matricula_pac') ?></span>
                                    </a>
                                </th>
                                <th scope="col" data-sort="false">
                                    <a class="rah-sort-link" href="<?= htmlspecialchars($buildPacienteSortUrl('cpf_pac'), ENT_QUOTES, 'UTF-8') ?>"
                                        onclick="return paginatePacientes('<?= htmlspecialchars($buildPacienteSortUrl('cpf_pac'), ENT_QUOTES, 'UTF-8') ?>');">
                                        <span>CPF</span><span class="rah-sort-icon"><?= $pacSortIcon('cpf_pac') ?></span>
                                    </a>
                                </th>
                                <th scope="col" data-sort="false">
                                    <a class="rah-sort-link" href="<?= htmlspecialchars($buildPacienteSortUrl('seguradora_seg'), ENT_QUOTES, 'UTF-8') ?>"
                                        onclick="return paginatePacientes('<?= htmlspecialchars($buildPacienteSortUrl('seguradora_seg'), ENT_QUOTES, 'UTF-8') ?>');">
                                        <span>Seguradora</span><span class="rah-sort-icon"><?= $pacSortIcon('seguradora_seg') ?></span>
                                    </a>
                                </th>
                                <th scope="col" data-sort="false">
                                    <a class="rah-sort-link" href="<?= htmlspecialchars($buildPacienteSortUrl('cidade_pac'), ENT_QUOTES, 'UTF-8') ?>"
                                        onclick="return paginatePacientes('<?= htmlspecialchars($buildPacienteSortUrl('cidade_pac'), ENT_QUOTES, 'UTF-8') ?>');">
                                        <span>Cidade</span><span class="rah-sort-icon"><?= $pacSortIcon('cidade_pac') ?></span>
                                    </a>
                                </th>
                                <th scope="col" width="8%" data-sort="false">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($query)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">Sem registros para os filtros aplicados.</td>
                            </tr>
                            <?php endif; ?>
                            <?php

                            foreach ($query as $paciente):
                                extract($paciente);
                            ?>
                            <?php

                                if (strlen($cpf_pac) > 0) {
                                    $cpf_format = substr($cpf_pac, 0, 3) . '.' .
                                        substr($cpf_pac, 3, 3) . '.' .
                                        substr($cpf_pac, 6, 3) . '-' .
                                        substr($cpf_pac, 9, 2);
                                } else {
                                    $cpf_format = null;
                                }

                                ?>

                            <?php if ($id_paciente >= 1) { ?>
                            <tr style="font-size:15px">
                                <td scope="row" class="col-id">
                                    <?= $id_paciente ?>
                                </td>
                                <td scope="row" class="nome-coluna-table">
                                    <?= $nome_pac ?>
                                </td>
                                <td scope="row" class="nome-coluna-table">
                                    <?= $matricula_pac ?>
                                </td>
                                <td scope="row" class="nome-coluna-table">
                                    <?= $cpf_format ?>
                                </td>
                                <td scope="row" class="nome-coluna-table">
                                    <?= isset($seguradora_seg) && $seguradora_seg !== '' ? $seguradora_seg : '-' ?>
                                </td>

                                <td scope="row" class="nome-coluna-table">
                                    <?= $cidade_pac ?>
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
                                                <a class="dropdown-item" style="font-size: .9rem; font-weight: 400 !important; text-transform: none !important;"
                                                    href="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/pacientes/editar/' . (int) $id_paciente, ENT_QUOTES, 'UTF-8') ?>">
                                                    <i class="bi bi-pencil-square" style="font-size:1rem;margin-right:8px;color:#3b82f6;"></i>Editar
                                                </a>
                                            </li>
                                            <li>
                                                <button class="dropdown-item" style="font-size: .9rem; font-weight: 400 !important; text-transform: none !important;"
                                                    onclick="openModal('<?= $BASE_URL ?>show_paciente_historico.php?id_paciente=<?= $id_paciente ?>')"
                                                    data-bs-toggle="modal" data-bs-target="#myModal"><i
                                                        class="bi bi-clock-history" style="font-size:1rem;margin-right:8px;color:#6366f1;"></i>Histórico</button>
                                            </li>
                                            <li>
                                                <a href="<?= $BASE_URL ?>hub_paciente/paciente<?= $id_paciente ?>"
                                                    class="dropdown-item" style="font-size: .9rem; font-weight: 400 !important; text-transform: none !important;">
                                                    <i class="bi bi-journal-medical"
                                                        style="font-size:1rem;margin-right:8px;color:#0ea5e9;"></i>
                                                    Hub Paciente
                                                </a>
                                            </li>

                                            <li>
                                                <a href="<?= $BASE_URL ?>internacoes/nova?id_paciente=<?= (int)$id_paciente ?>"
                                                    class="dropdown-item" style="font-size:.9rem; font-weight: 400 !important; text-transform: none !important;">
                                                    <i class="bi bi-clipboard2-pulse"
                                                        style="font-size:1rem;margin-right:8px;color:#14b8a6;"></i>
                                                    Internação
                                                </a>

                                            </li>
                                        </ul>
                                    </div>
                                </td>
                                <?php }; ?>
                            </tr>
                            <?php endforeach; ?>
                            <?php if ($qtdIntItens == 0): ?>
                            <tr>
                                <td colspan="7" scope="row" class="col-id" style='font-size:15px'>
                                    Não foram encontrados registros
                                </td>
                            </tr>

                            <?php endif ?>
                        </tbody>
                    </table>
                    <hr>
                    <div style="text-align:right">
                        <input type="hidden" id="qtd" value="<?php echo $qtdIntItens ?>">
                    </div>
                    <div style="display: flex;margin-top:20px">
                        <div class="modal fade" id="myModal">
                            <div class="modal-dialog  modal-lg modal-dialog-centered modal-xl">
                                <div class="modal-content">
                                    <div style="padding-left:20px;padding-top:20px;">
                                        <h4>Paciente</h4>
                                        <p class="page-description">Informações
                                            do paciente</p>
                                    </div>
                                    <div class="modal-body">
                                        <div id="content-php"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pagination" style="margin: 0 auto;">
                            <?php if ($total_pages ?? 1 > 1): ?>
                            <ul class="pagination">
                                <?php
                                    $blocoAtual = isset($_GET['bl']) ? $_GET['bl'] : 0;
                                    $paginaAtual = isset($_GET['pag']) ? $_GET['pag'] : 1;
                                    ?>
                                <?php if ($current_block > $first_block): ?>
                                <?php
                                        $firstPageUrl = buildPacientePaginationUrl($pacientePaginationBaseParams, [
                                            'pag' => 1,
                                            'bl'  => 0
                                        ]);
                                        ?>
                                <li class="page-item">
                                    <a class="page-link" id="blocoNovo" href="<?= htmlspecialchars($firstPageUrl) ?>"
                                        onclick="return paginatePacientes('<?= htmlspecialchars($firstPageUrl, ENT_QUOTES) ?>');">
                                        <i class="fa-solid fa-angles-left"></i>
                                    </a>
                                </li>
                                <?php endif; ?>
                                <?php if ($current_block <= $last_block && $last_block > 1 && $current_block != 1): ?>
                                <?php
                                        $prevPageUrl = buildPacientePaginationUrl($pacientePaginationBaseParams, [
                                            'pag' => max(1, $paginaAtual - 1),
                                            'bl'  => max(0, $blocoAtual - 5)
                                        ]);
                                        ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?= htmlspecialchars($prevPageUrl) ?>"
                                        onclick="return paginatePacientes('<?= htmlspecialchars($prevPageUrl, ENT_QUOTES) ?>');">
                                        <i class="fa-solid fa-angle-left"></i>
                                    </a>
                                </li>
                                <?php endif; ?>

                                <?php for ($i = $first_page_in_block; $i <= $last_page_in_block; $i++): ?>
                                <?php
                                        $pageUrl = buildPacientePaginationUrl($pacientePaginationBaseParams, [
                                            'pag' => $i,
                                            'bl'  => $blocoAtual
                                        ]);
                                        ?>
                                <li class="page-item <?php print ($_GET['pag'] ?? 1) == $i ? "active" : "" ?>">

                                    <a class="page-link" href="<?= htmlspecialchars($pageUrl) ?>"
                                        onclick="return paginatePacientes('<?= htmlspecialchars($pageUrl, ENT_QUOTES) ?>');">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>

                                <?php if ($current_block < $last_block): ?>
                                <?php
                                        $nextPageUrl = buildPacientePaginationUrl($pacientePaginationBaseParams, [
                                            'pag' => min($total_pages, $paginaAtual + 1),
                                            'bl'  => $blocoAtual + 5
                                        ]);
                                        ?>
                                <li class="page-item">
                                    <a class="page-link" id="blocoNovo" href="<?= htmlspecialchars($nextPageUrl) ?>"
                                        onclick="return paginatePacientes('<?= htmlspecialchars($nextPageUrl, ENT_QUOTES) ?>');"><i
                                            class="fa-solid fa-angle-right"></i></a>
                                </li>
                                <?php endif; ?>
                                <?php if ($current_block < $last_block): ?>
                                <?php
                                        $lastPageUrl = buildPacientePaginationUrl($pacientePaginationBaseParams, [
                                            'pag' => count($paginas),
                                            'bl'  => ($last_block - 1) * 5
                                        ]);
                                        ?>
                                <li class="page-item">
                                    <a class="page-link" id="blocoNovo" href="<?= htmlspecialchars($lastPageUrl) ?>"
                                        onclick="return paginatePacientes('<?= htmlspecialchars($lastPageUrl, ENT_QUOTES) ?>');"><i
                                            class="fa-solid fa-angles-right"></i></a>
                                </li>
                                <?php endif; ?>
                            </ul>
                            <?php endif; ?>
                        </div>

                        <div>
                            <p
                                style="margin-bottom:25px; font-size:1em; font-weight:600; font-family:var(--bs-font-sans-serif); text-align:right">
                                <?php echo "Total: " . $qtdIntItens ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</body>


<script>
// ajax para submit do formulario de pesquisa
$(document).ready(function() {
    $('#form_pesquisa').submit(function(e) {
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



            },


            error: function() {
                $('#responseMessage').html('Ocorreu um erro ao enviar o formulário.');
            }
        });
    });
});

$(document).ready(function() {
    var initialPacienteUrl = '<?= htmlspecialchars(buildPacientePaginationUrl(
        $pacientePaginationBaseParams,
        [
            'pag' => $_GET['pag'] ?? 1,
            'bl'  => $_GET['bl'] ?? 0
        ]
    ), ENT_QUOTES) ?>';
    if (typeof loadContent === 'function') {
        loadContent(initialPacienteUrl);
    }
});
</script>

<script>
if (typeof window.paginatePacientes !== 'function') {
    window.paginatePacientes = function(url) {
        if (typeof loadContent === 'function') {
            loadContent(url);
            return false;
        }
        window.location.href = url;
        return false;
    };
}
</script>

<style>
.rah-sort-link {
    color: inherit;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.rah-sort-link:hover,
.rah-sort-link:focus {
    color: inherit;
    text-decoration: none;
    opacity: .9;
}

.rah-sort-icon {
    font-size: .85em;
    opacity: .95;
}

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

<script src="./js/ajaxNav.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>
<script>
src = "https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js";
</script>
