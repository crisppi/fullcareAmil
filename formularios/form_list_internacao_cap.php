<?php
ob_start(); // Output Buffering Start

require_once("templates/header.php");
require_once("models/message.php");

include_once("models/internacao.php");
include_once("dao/internacaoDao.php");

include_once("models/patologia.php");
include_once("dao/patologiaDao.php");

include_once("models/paciente.php");
include_once("dao/pacienteDao.php");

include_once("models/capeante.php");
include_once("dao/capeanteDao.php");

include_once("models/hospital.php");
include_once("dao/hospitalDao.php");

include_once("models/usuario.php");
include_once("dao/usuarioDao.php");

include_once("models/pagination.php");

// =====================================================================
// Sessão / Papel / Diretor
// =====================================================================
$cargoSessao = $_SESSION['cargo'] ?? '';
$nivelSessao = $_SESSION['nivel'] ?? null;
$userId = $_SESSION['id_usuario'] ?? null;

$temCargoAmplificado = (stripos((string) $cargoSessao, 'diretor') !== false)
    || (stripos((string) $cargoSessao, 'gestor') !== false);

$isDiretor = $temCargoAmplificado || in_array((string) $nivelSessao, ['4', '5'], true);

// =====================================================================
// Inicialização
// =====================================================================
$data_intern_int = null;
$order = null;
$obLimite = null;
$where = null;
$inicio = $inicio ?? 0;

// =====================================================================
// DAOs
// =====================================================================
$internacao = new internacaoDAO($conn, $BASE_URL);
$capeante_geral = new capeanteDAO($conn, $BASE_URL);
$pacienteDao = new pacienteDAO($conn, $BASE_URL);
$usuarioDao = new userDAO($conn, $BASE_URL);
$hospital_geral = new HospitalDAO($conn, $BASE_URL);
$patologiaDao = new patologiaDAO($conn, $BASE_URL);

// =====================================================================
// GET (filtros)
// =====================================================================
$limite = filter_input(INPUT_GET, 'limite', FILTER_VALIDATE_INT);
if (!$limite || $limite < 1)
    $limite = 10;

$ordenar = filter_input(INPUT_GET, 'ordenar') ?: 'id_capeante_desc'; // campo para ORDER BY

$pesquisa_nome = filter_input(INPUT_GET, 'pesquisa_nome', FILTER_SANITIZE_SPECIAL_CHARS);
$pesquisa_pac = filter_input(INPUT_GET, 'pesquisa_pac', FILTER_SANITIZE_SPECIAL_CHARS);
$pesquisa_matricula = filter_input(INPUT_GET, 'pesquisa_matricula', FILTER_SANITIZE_SPECIAL_CHARS);
$senha_fin = filter_input(INPUT_GET, 'senha_fin') ?: NULL;
$conta_parada = filter_input(INPUT_GET, 'conta_parada') ?: NULL;
$status_conta = filter_input(INPUT_GET, 'status_conta', FILTER_SANITIZE_SPECIAL_CHARS);
$statusOptions = ['todos', 'aberto', 'encerrado', 'auditoria'];
if (!$status_conta || !in_array($status_conta, $statusOptions, true)) {
    $status_conta = 'todos'; // padrão passa a mostrar todas as contas
}
$idcapeante = filter_input(INPUT_GET, 'idcapeante') ?: NULL;
$senha_int = filter_input(INPUT_GET, 'senha_int', FILTER_SANITIZE_SPECIAL_CHARS) ?: NULL;
$lote = filter_input(INPUT_GET, 'lote', FILTER_SANITIZE_SPECIAL_CHARS) ?: NULL;
$data_intern_int = filter_input(INPUT_GET, 'data_intern_int') ?: NULL;
$data_intern_int_max = filter_input(INPUT_GET, 'data_intern_int_max') ?: NULL;
$id_hosp = filter_input(INPUT_GET, 'id_hosp', FILTER_SANITIZE_NUMBER_INT) ?: null;

if (empty($data_intern_int_max))
    $data_intern_int_max = date('Y-m-d');

$encerrado_cap = "";
$em_auditoria_cap = 'n';

// =====================================================================
// Hospitais visíveis (para SELECT) – dedup por id_hospital
// =====================================================================
$hospitalsRaw = $hospital_geral->findGeral();
$hospitalsDedup = [];
foreach ($hospitalsRaw as $h) {
    $hid = $h['id_hospital'] ?? $h['id'] ?? null;
    $hnom = $h['nome_hosp'] ?? $h['nome'] ?? '';
    $fk = $h['fk_usuario_hosp'] ?? null;

    if (!$isDiretor) {
        if ($fk && $userId && (string) $fk !== (string) $userId)
            continue;
    }
    if ($hid && !isset($hospitalsDedup[$hid])) {
        $hospitalsDedup[$hid] = ['id_hospital' => $hid, 'nome_hosp' => $hnom];
    }
}
$hospitals = array_values($hospitalsDedup);

// auto-seleciona hospital único do profissional
if (!$isDiretor && empty($id_hosp) && count($hospitals) === 1) {
    $id_hosp = (string) $hospitals[0]['id_hospital'];
}

// =====================================================================
// WHERE (filtro principal) – prefixos corretos
// =====================================================================
$condicoes = [];
$whereParams = [];

if (strlen((string)$id_hosp)) {
    $condicoes[] = 'ho.id_hospital = :id_hosp';
    $whereParams[':id_hosp'] = (int)$id_hosp;
}
if (strlen($pesquisa_nome)) {
    $condicoes[] = 'ho.nome_hosp LIKE :pesquisa_nome';
    $whereParams[':pesquisa_nome'] = '%' . $pesquisa_nome . '%';
}
if (strlen($pesquisa_pac)) {
    $condicoes[] = 'pa.nome_pac LIKE :pesquisa_pac';
    $whereParams[':pesquisa_pac'] = '%' . $pesquisa_pac . '%';
}
if (strlen($pesquisa_matricula)) {
    $condicoes[] = 'pa.matricula_pac LIKE :pesquisa_matricula';
    $whereParams[':pesquisa_matricula'] = '%' . $pesquisa_matricula . '%';
}
if (strlen($lote)) {
    $condicoes[] = 'ca.lote_cap = :lote';
    $whereParams[':lote'] = $lote;
}
if (strlen($idcapeante)) {
    $condicoes[] = 'ca.id_capeante LIKE :idcapeante';
    $whereParams[':idcapeante'] = '%' . $idcapeante . '%';
}
if (strlen($senha_fin)) {
    $condicoes[] = 'ca.senha_finalizada = :senha_fin';
    $whereParams[':senha_fin'] = $senha_fin;
}
if ($conta_parada === 's' || $conta_parada === 'n') {
    $condicoes[] = 'ca.conta_parada_cap = :conta_parada';
    $whereParams[':conta_parada'] = $conta_parada;
}
if (strlen($senha_int)) {
    $condicoes[] = 'ac.senha_int LIKE :senha_int';
    $whereParams[':senha_int'] = '%' . $senha_int . '%';
}
if (strlen((string)$data_intern_int)) {
    $condicoes[] = 'ac.data_intern_int BETWEEN :data_intern_int AND :data_intern_int_max';
    $whereParams[':data_intern_int'] = (string)$data_intern_int;
    $whereParams[':data_intern_int_max'] = (string)$data_intern_int_max;
}
if (!$isDiretor && strlen((string)$userId)) {
    $condicoes[] = 'hos.fk_usuario_hosp = :user_id';
    $whereParams[':user_id'] = (int)$userId;
}

switch ($status_conta) {
    case 'encerrado':
        $condicoes[] = 'ca.encerrado_cap = "s"';
        break;
    case 'auditoria':
        $condicoes[] = 'ca.em_auditoria_cap = "s"';
        break;
    case 'aberto':
        $condicoes[] = '(ca.encerrado_cap IS NULL OR ca.encerrado_cap = "" OR ca.encerrado_cap = "n")';
        break;
    case 'todos':
    default:
        // sem condição adicional
        break;
}

$where = implode(' AND ', $condicoes);

// =====================================================================
// TOTAL exato de CAPEANTES (contas) com os MESMOS filtros
// =====================================================================
$sqlTotal = "
    SELECT COUNT(DISTINCT ca.id_capeante) AS total
    FROM tb_internacao ac
    LEFT JOIN tb_hospital AS ho   ON ac.fk_hospital_int   = ho.id_hospital
    LEFT JOIN tb_hospitalUser hos ON hos.fk_hospital_user = ho.id_hospital
    LEFT JOIN tb_user AS se       ON se.id_usuario        = hos.fk_usuario_hosp
    LEFT JOIN tb_uti  AS ut       ON ac.id_internacao     = ut.fk_internacao_uti
    LEFT JOIN tb_paciente AS pa   ON ac.fk_paciente_int   = pa.id_paciente 
    LEFT JOIN tb_capeante AS ca   ON ac.id_internacao     = ca.fk_int_capeante
    " . (strlen($where) ? 'WHERE ' . $where : '') . "
";
$stmtTotal = $conn->prepare($sqlTotal);
foreach ($whereParams as $key => $value) {
    $stmtTotal->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmtTotal->execute();
$rowTotal = $stmtTotal->fetch(PDO::FETCH_ASSOC);
$qtdIntItens = (int) ($rowTotal['total'] ?? 0);

// =====================================================================
// Paginação
// =====================================================================
$totalcasos = max(1, (int) ceil($qtdIntItens / max(1, (int) $limite)));
$paginaAtual = (int) ($_GET['pag'] ?? 1);

// Monta ORDER BY seguro (só permite colunas conhecidas)
$mapOrder = [
    'id_internacao' => 'ac.id_internacao',
    'id_capeante_desc' => 'ca.id_capeante DESC',
    'id_capeante' => 'ca.id_capeante',
    'senha_int' => 'ac.senha_int',
    'nome_pac' => 'pa.nome_pac',
    'nome_hosp' => 'ho.nome_hosp',
    'data_intern_int' => 'ac.data_intern_int',
];
$orderBy = $mapOrder[$ordenar] ?? 'ca.id_capeante DESC';

// Calcula LIMIT (ex.: "0,10")
$offset = max(0, ($paginaAtual - 1) * $limite);
$limitSql = $offset . ',' . (int) $limite;

// =====================================================================
// Lista (1 linha por conta): SELECT DISTINCT com mesmos filtros
// =====================================================================
$sqlList = "
    SELECT DISTINCT
        ca.id_capeante,
        ac.id_internacao,
        ho.id_hospital,
        ho.nome_hosp,
        pa.id_paciente,
        pa.nome_pac,
        ac.senha_int,
        ac.data_intern_int,
        ca.adm_check,
        ca.med_check,
        ca.enfer_check,
        ca.parcial_num,
        ca.senha_finalizada,
        ca.conta_parada_cap,
        ca.parada_motivo_cap,
        ca.lote_cap,
        ca.encerrado_cap,
        ca.em_auditoria_cap,
        CASE
            WHEN EXISTS (
                SELECT 1
                FROM tb_gestao ge
                WHERE ge.fk_internacao_ges = ac.id_internacao
                  AND LOWER(COALESCE(ge.evento_adverso_ges, '')) = 's'
            ) THEN 1
            ELSE 0
        END AS alerta_evento_adverso_cap
    FROM tb_internacao ac
    LEFT JOIN tb_capeante    ca  ON ac.id_internacao     = ca.fk_int_capeante
    LEFT JOIN tb_hospital    ho  ON ac.fk_hospital_int   = ho.id_hospital
    LEFT JOIN tb_hospitalUser hos ON hos.fk_hospital_user = ho.id_hospital
    LEFT JOIN tb_user        se  ON se.id_usuario        = hos.fk_usuario_hosp
    LEFT JOIN tb_uti         ut  ON ac.id_internacao     = ut.fk_internacao_uti
    LEFT JOIN tb_paciente    pa  ON ac.fk_paciente_int   = pa.id_paciente
    " . (strlen($where) ? 'WHERE ' . $where : '') . "
    ORDER BY $orderBy
    LIMIT $limitSql
";
$stmtList = $conn->prepare($sqlList);
foreach ($whereParams as $key => $value) {
    $stmtList->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmtList->execute();
$query = $stmtList->fetchAll(PDO::FETCH_ASSOC);

// =====================================================================
// URL base de paginação (corrigido 'ordenar' e data_intern_int)
// =====================================================================
$url = 'list_internacao_cap.php?'
    . 'id_hosp=' . urlencode((string) $id_hosp)
    . '&pesquisa_nome=' . urlencode((string) $pesquisa_nome)
    . '&pesquisa_pac=' . urlencode((string) $pesquisa_pac)
    . '&pesquisa_matricula=' . urlencode((string) $pesquisa_matricula)
    . '&senha_fin=' . urlencode((string) $senha_fin)
    . '&conta_parada=' . urlencode((string) $conta_parada)
    . '&status_conta=' . urlencode((string) $status_conta)
    . '&encerrado_cap=' . urlencode((string) $encerrado_cap)
    . '&lote=' . urlencode((string) $lote)
    . '&senha_int=' . urlencode((string) $senha_int)
    . '&data_intern_int=' . urlencode((string) $data_intern_int)
    . '&data_intern_int_max=' . urlencode((string) $data_intern_int_max)
    . '&limite=' . (int) $limite
    . '&ordenar=' . urlencode((string) $ordenar);

// =====================================================================
// Blocos e páginas (para navegação)
// =====================================================================
$havePages = $qtdIntItens > $limite;
if ($havePages) {
    $obPagination = new pagination((int) $qtdIntItens, $paginaAtual, (int) $limite);
    $paginas = $obPagination->getPages();
    $total_pages = count($paginas);

    function paginasAtuais($var)
    {
        $blocoAtual = isset($_GET['bl']) ? $_GET['bl'] : 0;
        return $var['bloco'] == (($blocoAtual) / 5) + 1;
    }
    $block_pages = array_filter($paginas, "paginasAtuais");
    $first_page_in_block = reset($block_pages)["pg"] ?? 1;
    $last_page_in_block = end($block_pages)["pg"] ?? 1;
    $first_block = reset($paginas)["bloco"] ?? 1;
    $last_block = end($paginas)["bloco"] ?? 1;
    $current_block = reset($block_pages)["bloco"] ?? 1;
}
?>
<link rel="stylesheet" href="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/css/listagem_padrao.css', ENT_QUOTES, 'UTF-8') ?>">
<!-- FORMULARIO DE PESQUISAS -->
<div class="container-fluid form_container listagem-page" id='main-container'>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"
        integrity="sha256-2Pmvv0kuTBOenSvLm6bvfBSSHrUJ+3A7x6P5Ebd07/g=" crossorigin="anonymous"></script>
    <div class="listagem-hero">
        <div class="listagem-hero__copy">
            <div class="listagem-kicker">Capeantes</div>
            <h1 class="listagem-title">Listagem de capeantes</h1>
            <p class="listagem-subtitle">Controle contas abertas, auditoria, paradas e encerramentos com uma leitura mais limpa da operação.</p>
        </div>
    </div>
    <div class="complete-table listagem-panel">
        <div id="navbarToggleExternalContent" class="table-filters">
            <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
            <form id="select-internacao-form" method="GET" action="list_internacao_cap.php">

                <div class="form-group row filter-inline-row">
                    <!-- SELECT de Hospital (sem duplicidade) -->
                    <div class="form-group col-sm-3" style="padding:2px !important;padding-left:16px !important;">
                        <select class="form-control form-control-sm"
                            style="margin-top:7px; font-size:.8em; color:#878787" name="id_hosp" id="id_hosp">
                            <option value=""><?= $isDiretor ? 'Todos os Hospitais' : 'Selecione o Hospital' ?></option>
                            <?php foreach ($hospitals as $h): ?>
                                <option value="<?= (int) $h['id_hospital'] ?>"
                                    <?= ((string) $id_hosp === (string) $h['id_hospital']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) $h['nome_hosp']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group col-sm-2" style="padding:2px !important">
                        <input class="form-control form-control-sm"
                            style="margin-top:7px; font-size:.8em; color:#878787" type="text" name="pesquisa_pac"
                            placeholder="Paciente" value="<?= htmlspecialchars((string) $pesquisa_pac) ?>">
                    </div>

                    <div class="form-group col-sm-2" style="padding:2px !important">
                        <input class="form-control form-control-sm"
                            style="margin-top:7px; font-size:.8em; color:#878787" type="text" name="pesquisa_matricula"
                            placeholder="Matrícula" value="<?= htmlspecialchars((string) $pesquisa_matricula) ?>">
                    </div>

                    <div class="form-group col-sm-2" style="padding:2px !important">
                        <input class="form-control form-control-sm"
                            style="margin-top:7px; font-size:.8em; color:#878787" type="text" name="senha_int"
                            placeholder="Senha" value="<?= htmlspecialchars((string) $senha_int) ?>">
                    </div>

                    <div class="form-group col-sm-1" style="padding:2px !important">
                        <input class="form-control form-control-sm"
                            style="margin-top:7px; font-size:.8em; color:#878787" type="text" name="lote"
                            placeholder="Lote" value="<?= htmlspecialchars((string) $lote) ?>">
                    </div>

                    <div class="form-group col-sm-1" style="padding:2px !important">
                        <input class="form-control form-control-sm"
                            style="margin-top:7px; font-size:.8em; color:#878787" type="text" name="idcapeante"
                            placeholder="Capeante" value="<?= htmlspecialchars((string) $idcapeante) ?>">
                    </div>

                    <div class="col-sm-1" style="padding:2px !important">
                        <select class="form-control mb-3 form-control-sm"
                            style="margin-top:7px;font-size:.8em; color:#878787" id="limite" name="limite">
                            <option value="">Reg/pág</option>
                            <option value="5" <?= (int) $limite === 5 ? 'selected' : null ?>>5</option>
                            <option value="10" <?= (int) $limite === 10 ? 'selected' : null ?>>10</option>
                            <option value="20" <?= (int) $limite === 20 ? 'selected' : null ?>>20</option>
                            <option value="50" <?= (int) $limite === 50 ? 'selected' : null ?>>50</option>
                        </select>
                    </div>


                </div>

                <div class="form-group row filter-inline-row" style="margin-top:10px; margin-bottom:14px;">
                    <div class="form-group col-sm-2" style="padding-left:16px !important;">
                        <select class="form-control form-control-sm"
                            style="margin-top:7px;font-size:.8em; color:#878787" id="ordenar" name="ordenar">
                            <option value="">Classificar por</option>
                            <option value="id_capeante_desc" <?= $ordenar == 'id_capeante_desc' ? 'selected' : '' ?>>
                                No.capeante (desc)</option>
                            <option value="id_internacao" <?= $ordenar == 'id_internacao' ? 'selected' : '' ?>>
                                Internação</option>
                            <option value="id_capeante" <?= $ordenar == 'id_capeante' ? 'selected' : '' ?>>
                                No.capeante (asc)</option>
                            <option value="senha_int" <?= $ordenar == 'senha_int' ? 'selected' : '' ?>>Senha
                            </option>
                            <option value="nome_pac" <?= $ordenar == 'nome_pac' ? 'selected' : '' ?>>Paciente
                            </option>
                            <option value="nome_hosp" <?= $ordenar == 'nome_hosp' ? 'selected' : '' ?>>Hospital
                            </option>
                            <option value="data_intern_int" <?= $ordenar == 'data_intern_int' ? 'selected' : '' ?>>Data
                                Internação</option>
                        </select>
                    </div>

                    <div class="form-group col-sm-2" style="padding:2px !important">
                        <select class="form-control form-control-sm"
                            style="margin-top:7px;font-size:.8em; color:#878787" id="senha_fin" name="senha_fin">
                            <option value="">Senha finalizada</option>
                            <option value="s" <?= $senha_fin === 's' ? 'selected' : '' ?>>Sim</option>
                            <option value="n" <?= $senha_fin === 'n' ? 'selected' : '' ?>>Não</option>
                        </select>
                    </div>
                    <div class="form-group col-sm-2" style="padding:2px !important">
                        <select class="form-control form-control-sm"
                            style="margin-top:7px;font-size:.8em;color:#878787" id="conta_parada" name="conta_parada">
                            <option value="" <?= ($conta_parada === null || $conta_parada === '') ? 'selected' : '' ?>>Conta parada (todas)</option>
                            <option value="s" <?= $conta_parada === 's' ? 'selected' : '' ?>>Paradas</option>
                            <option value="n" <?= $conta_parada === 'n' ? 'selected' : '' ?>>Ativas</option>
                        </select>
                    </div>
                    <div class="form-group col-sm-2" style="padding:2px !important">
                        <select class="form-control form-control-sm"
                            style="margin-top:7px;font-size:.8em;color:#878787" id="status_conta" name="status_conta">
                            <option value="todos" <?= $status_conta === 'todos' ? 'selected' : '' ?>>Status — Encerrado (todos)
                            </option>
                            <option value="aberto" <?= $status_conta === 'aberto' ? 'selected' : '' ?>>Apenas abertos</option>
                            <option value="auditoria" <?= $status_conta === 'auditoria' ? 'selected' : '' ?>>Em auditoria
                            </option>
                            <option value="encerrado" <?= $status_conta === 'encerrado' ? 'selected' : '' ?>>Somente encerrados
                            </option>
                        </select>
                    </div>
                    <div class="form-group col-sm-2" style="padding:2px !important">
                        <input class="form-control form-control-sm" type="date"
                            style="margin-top:7px;font-size:.8em; color:#878787" name="data_intern_int"
                            placeholder="Data Internação Min" value="<?= htmlspecialchars((string) $data_intern_int) ?>">
                    </div>
                    <div class="form-group col-sm-2" style="padding:2px !important">
                        <input class="form-control form-control-sm" type="date"
                            style="margin-top:7px;font-size:.8em; color:#878787" name="data_intern_int_max"
                            placeholder="Data Internação Max"
                            value="<?= htmlspecialchars((string) $data_intern_int_max) ?>">
                    </div>
                    <div class="form-group col-sm-1" style="padding:2px !important">
                        <button type="submit" class="btn btn-primary"
                            style="background-color:#5e2363;width:42px;height:32px;margin-top:7px;border-color:#5e2363">
                            <span class="material-icons" style="margin-left:-3px;margin-top:-2px;">search</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div>
            <div id="table-content" class="listagem-table-wrap" style="margin-top:10px">
                <table class="table table-sm table-striped  table-hover table-condensed">
                    <thead>
                        <tr>
                            <th scope="col" style="width:4%">Reg Int</th>
                            <th scope="col" style="width:5%">Conta No.</th>
                            <th scope="col" style="width:12%">Hospital</th>
                            <th scope="col" style="width:16%">Paciente</th>
                            <th scope="col" style="width:10%">Senha</th>
                            <th scope="col" style="width:8%">Data internação</th>
                            <th scope="col" style="width:4%;">Med</th>
                            <th scope="col" style="width:4%;">Enf</th>
                            <th scope="col" style="width:4%;">Adm</th>
                            <th scope="col" style="width:4%;">Parcial</th>
                            <th scope="col" style="width:3%;">Final</th>
                            <th scope="col" style="width:3%;">EA</th>
                            <th scope="col" style="width:3%;">Aberto</th>
                            <th scope="col" style="width:6%;">Cap Encer</th>
                            <th scope="col" style="width:6%;">Em Audit</th>
                            <th scope="col" style="width:13%">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($query as $intern): ?>
                            <tr style="font-size:13px">
                                <td scope="row" class="col-id"><b><?= $intern["id_internacao"]; ?></b></td>
                                <td scope="row" class="col-id"><b><?= $intern["id_capeante"]; ?></b></td>
                                <td scope="row" class="nome-coluna-table"><b><?= $intern["nome_hosp"] ?></b></td>
                                <td scope="row"><?= $intern["nome_pac"] ?></td>
                                <td scope="row"><?= $intern["senha_int"] ?></td>
                                <td scope="row"><?= date('d/m/Y', strtotime($intern["data_intern_int"])) ?></td>

                                <td scope="row">
                                    <?php if (($intern["med_check"] ?? 'n') === "s") { ?>
                                        <a class="legenda-medico"><span class="bi bi-check-circle"
                                                style="font-size:1.1rem;font-weight:1000;color:rgb(0,78,86);"></span></a>
                                    <?php } ?>
                                </td>
                                <td scope="row">
                                    <?php if (($intern["enfer_check"] ?? 'n') === "s") { ?>
                                        <a class="legenda-enfermagem" style="font-weight:bold"><span class="bi bi-check-circle"
                                                style="font-size:1.1rem;font-weight:bold;color:rgb(234,128,55);"></span></a>
                                    <?php } ?>
                                </td>
                                <td scope="row">
                                    <?php if (($intern["adm_check"] ?? 'n') === "s") { ?>
                                        <a class="legenda-administrativo"><span class="bi bi-check-circle"
                                                style="font-size:1.1rem;font-weight:1000;color:rgb(25,78,255);"></span></a>
                                    <?php } ?>
                                </td>
                                <td scope="row"><?= $intern["parcial_num"]; ?></td>
                                <td scope="row">
                                    <?php if (($intern["senha_finalizada"] ?? 'n') === "s") { ?>
                                        <a class="legenda-finalizada"><span class="bi bi-briefcase"
                                                style="font-size:1.1rem;font-weight:800;color:rgb(255,25,55);"></span></a>
                                    <?php } ?>
                                </td>
                                <td scope="row">
                                    <?php if ((int)($intern["alerta_evento_adverso_cap"] ?? 0) === 1) { ?>
                                        <a title="Conta com evento adverso">
                                            <span class="bi bi-exclamation-triangle-fill"
                                                style="font-size:1.1rem;font-weight:800;color:#c62828;"></span>
                                        </a>
                                    <?php } ?>
                                </td>
                                <td scope="row">
                                    <?php if (($intern["aberto_cap"] ?? 'n') === "s") { ?>
                                        <a class="legenda-aberto"><span class="bi bi-book"
                                                style="font-size:1.1rem;color:blue;font-weight:800"></span></a>
                                    <?php } ?>
                                </td>
                                <td scope="row">
                                    <?php if (($intern["encerrado_cap"] ?? 'n') === "s") { ?>
                                        <a class="legenda-aberto"><span class="bi bi-briefcase"
                                                style="font-size:1.1rem;color:green;font-weight:800;"></span></a>
                                    <?php } ?>
                                </td>
                                <td scope="row">
                                    <?php if (($intern["em_auditoria_cap"] ?? 'n') === "s") { ?>
                                        <a class="legenda-em-auditoria"><span class="bi bi-pencil-square"
                                                style="font-size:1.1rem;font-weight:800;color:orange;"></span></a>
                                    <?php } ?>
                                </td>

                                <?php
                                    $capeanteEncerrado = strtolower((string)($intern['encerrado_cap'] ?? 'n')) === 's';
                                    $pdfPreviewUrl = $BASE_URL . 'export_capeante_rah_pdf.php?id_capeante=' . $intern['id_capeante'] . '&download=0';
                                    $pdfDownloadUrl = $BASE_URL . 'export_capeante_rah_pdf.php?id_capeante=' . $intern['id_capeante'] . '&download=1';
                                    $rahEditUrl = $BASE_URL . 'cad_capeante_rah.php?id_capeante=' . $intern['id_capeante'];
                                ?>
                                <td class="action text-center">
                                    <div class="d-flex flex-column align-items-center gap-2">
                                        <div class="d-flex flex-wrap align-items-center gap-3 justify-content-center">
                                            <?php if (($intern['encerrado_cap'] ?? 'n') !== "s"): ?>
                                                <?php if (($intern['em_auditoria_cap'] ?? 'n') === "s"): ?>
                                                    <a class="legenda-em-auditoria"
                                                        href="<?= $BASE_URL ?>cad_capeante_rah.php?id_capeante=<?= $intern['id_capeante'] ?>">
                                                        <i class="bi bi-file-text" style="color:#db5a0f;font-size:1.1em;margin:0 5px"></i>
                                                        <span style="color:#db5a0f;">Analisar</span>
                                                    </a>
                                                <?php else: ?>
                                                    <a class="legenda-iniciar"
                                                        href="<?= $BASE_URL ?>cad_capeante_rah.php?id_capeante=<?= $intern['id_capeante'] ?>">
                                                        <i class="bi bi-file-text"
                                                            style="color:rgb(25,78,255);font-size:1.1em;font-weight:bold;margin:0 5px"></i>
                                                        <span>Iniciar</span>
                                                    </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="legenda-encerrado" style="cursor:default;">
                                                    <i class="bi"
                                                        style="color:black;text-decoration:none;font-size:1.1em;font-weight:bold;margin:0 5px">
                                                        Encerrado</i>
                                                </span>
                                            <?php endif; ?>

                                            <a class="legenda-parcial"
                                                href="<?= $BASE_URL ?>cad_capeante_rah.php?id_internacao=<?= $intern["id_internacao"] ?>&type=create">
                                                <i class="legenda-parcial bi bi-file-text"
                                                    style="color:green;text-decoration:none;font-size:10px;font-weight:bold;margin:0 5px">
                                                    Criar Parcial</i>
                                            </a>
                                        </div>

                                        <div class="dropdown">
                                            <?php $dropdownId = 'contasDropdown' . $intern['id_capeante'] . '_' . $intern['id_internacao']; ?>
                                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button"
                                                id="<?= htmlspecialchars($dropdownId, ENT_QUOTES, 'UTF-8') ?>"
                                                data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="bi bi-stack me-1"></i>
                                                Contas
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="<?= htmlspecialchars($dropdownId, ENT_QUOTES, 'UTF-8') ?>">
                                                <li>
                                                    <a class="dropdown-item fw-normal" href="<?= htmlspecialchars($rahEditUrl, ENT_QUOTES, 'UTF-8') ?>"
                                                        onclick="edit('<?= htmlspecialchars($rahEditUrl, ENT_QUOTES, 'UTF-8') ?>'); return false;">
                                                        <i class="bi bi-pencil-square me-1" style="color:#5e2363;"></i>
                                                        <span style="color:#5e2363; font-weight:400;">Editar RAH</span>
                                                    </a>
                                                </li>
                                                <?php if ($capeanteEncerrado): ?>
                                                <li>
                                                    <a class="dropdown-item fw-normal" target="_blank" rel="noopener"
                                                        href="<?= htmlspecialchars($pdfPreviewUrl, ENT_QUOTES, 'UTF-8') ?>"
                                                        style="color:#0d6efd !important; font-weight:400 !important;">
                                                        <i class="bi bi-eye me-1" style="color:#0d6efd;"></i>
                                                        <span style="color:#0d6efd !important; font-weight:400 !important;">Ver RAH</span>
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item fw-normal" target="_blank" rel="noopener"
                                                        href="<?= htmlspecialchars($pdfDownloadUrl, ENT_QUOTES, 'UTF-8') ?>"
                                                        style="color:#198754 !important; font-weight:400 !important;">
                                                        <i class="bi bi-printer-fill me-1" style="color:#198754;"></i>
                                                        <span style="color:#198754 !important; font-weight:400 !important;">Imprimir PDF</span>
                                                    </a>
                                                </li>
                                                <?php else: ?>
                                                <li>
                                                    <span class="dropdown-item disabled text-muted fw-normal"
                                                        style="color:#6c757d !important; font-weight:400 !important;">
                                                        <i class="bi bi-eye me-1" style="color:#6c757d;"></i>
                                                        <span style="color:#6c757d !important; font-weight:400 !important;">Ver RAH</span>
                                                    </span>
                                                </li>
                                                <li>
                                                    <span class="dropdown-item disabled text-muted fw-normal"
                                                        style="color:#6c757d !important; font-weight:400 !important;">
                                                        <i class="bi bi-printer-fill me-1" style="color:#6c757d;"></i>
                                                        <span style="color:#6c757d !important; font-weight:400 !important;">Imprimir PDF</span>
                                                    </span>
                                                </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if ($qtdIntItens == 0): ?>
                            <tr>
                                <td colspan="16" scope="row" class="col-id" style='font-size:15px'>
                                    Não foram encontrados registros
                                </td>
                            </tr>
                        <?php endif ?>
                    </tbody>
                </table>

                <div style="display:flex;margin:10px 25px 25px 25px;align-items:center;gap:16px;">
                    <div class="pagination" style="margin:10px auto;">
                        <?php if (!empty($havePages) && $havePages): ?>
                            <ul class="pagination">
                                <?php
                                $blocoAtual = isset($_GET['bl']) ? (int) $_GET['bl'] : 0;
                                $paginaAtual = isset($_GET['pag']) ? (int) $_GET['pag'] : 1;
                                ?>
                                <?php if ($current_block > $first_block): ?>
                                    <li class="page-item">
                                        <a class="page-link" id="blocoNovo" href="<?= $url ?>&pag=1&bl=0">
                                            <i class="fa-solid fa-angles-left"></i></a>
                                    </li>
                                <?php endif; ?>

                                <?php if ($current_block <= $last_block && $last_block > 1 && $current_block != 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?= $url ?>&pag=<?= max(1, $paginaAtual - 1) ?>&bl=<?= max(0, $blocoAtual - 5) ?>">
                                            <i class="fa-solid fa-angle-left"></i></a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = $first_page_in_block; $i <= $last_page_in_block; $i++): ?>
                                    <li class="page-item <?= (($_GET['pag'] ?? 1) == $i) ? "active" : "" ?>">
                                        <a class="page-link" href="<?= $url ?>&pag=<?= $i ?>&bl=<?= (int) $blocoAtual ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($current_block < $last_block): ?>
                                    <li class="page-item">
                                        <a class="page-link" id="blocoNovo" href="<?= $url ?>&pag=<?= $paginaAtual + 1 ?>&bl=<?= $blocoAtual + 5 ?>">
                                            <i class="fa-solid fa-angle-right"></i></a>
                                    </li>
                                <?php endif; ?>

                                <?php if ($current_block < $last_block): ?>
                                    <li class="page-item">
                                        <a class="page-link" id="blocoNovo" href="<?= $url ?>&pag=<?= $total_pages ?>&bl=<?= ($last_block - 1) * 5 ?>">
                                            <i class="fa-solid fa-angles-right"></i></a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <div class="table-counter">
                        <p
                            style="font-size:1em;font-weight:600;font-family:var(--bs-font-sans-serif);text-align:right;margin:0;">
                            <?php echo "Total: " . (int) $qtdIntItens ?>
                        </p>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<script>
    // AJAX para submit do formulário de pesquisa
    $(document).ready(function () {
        $('#select-internacao-form').submit(function (e) {
            e.preventDefault();
            var formData = $(this).serialize();
            $.ajax({
                url: $(this).attr('action'),
                type: $(this).attr('method') || 'GET',
                data: formData,
                success: function (response) {
                    var tempElement = document.createElement('div');
                    tempElement.innerHTML = response;
                    var tableContent = tempElement.querySelector('#table-content');
                    $('#table-content').html(tableContent);
                },
                error: function () {
                    alert('Ocorreu um erro ao enviar o formulário.');
                }
            });
        });
    });

    // Carregamento inicial (corrigido: data_intern_int e ordenar)
    $(document).ready(function () {
        loadContent(
            'list_internacao_cap.php?id_hosp=<?= urlencode((string) $id_hosp) ?>' +
            '&pesquisa_pac=<?= urlencode((string) $pesquisa_pac) ?>' +
            '&pesquisa_matricula=<?= urlencode((string) $pesquisa_matricula) ?>' +
            '&data_intern_int=<?= urlencode((string) $data_intern_int) ?>' +
            '&pag=1&bl=0&limite=<?= (int) $limite ?>' +
            '&ordenar=<?= htmlspecialchars((string) $ordenar) ?>'
        );
    });
</script>

<script>
    $(document).ready(function () {
        if ($('#encerrado_cap').length) $('#encerrado_cap').val('n');
    });
</script>

<script>
    function openCapeantePdf(idCapeante) {
        if (!idCapeante) return false;
        var url = '<?= $BASE_URL ?>export_capeante_pdf.php?id_capeante=' + encodeURIComponent(idCapeante) + '&download=0';
        window.open(url, '_blank', 'noopener');
        return false;
    }
</script>

<script src="./js/input-estilo.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-gtEjrD/SeCtmISkJkNUaaKMoLD0//ElJ19smozuHV6z3Ulb9Bn9Plx0x4" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.2/html2pdf.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js"></script>
<script src="./js/ajaxNav.js"></script>
<script src="./scripts/cadastro/general.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-maskmoney/3.0.2/jquery.maskMoney.min.js"></script>
<!-- jQuery (necessário para bootstrap-select) -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>

<!-- Bootstrap 5 (bundle com Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

<!-- bootstrap-select (versão compatível com BS5) -->
<link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/css/bootstrap-select.min.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/js/bootstrap-select.min.js"></script>
