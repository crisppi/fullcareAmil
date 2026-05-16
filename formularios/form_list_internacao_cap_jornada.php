<?php
ob_start();

// ===== DEV ONLY (remova em produção) =====
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../php-error.log');
error_reporting(E_ALL);

require_once("templates/header.php");

require_once(__DIR__ . "/../dao/internacaoDao.php");
require_once(__DIR__ . "/../dao/capeanteDao.php");
require_once(__DIR__ . "/../models/pagination.php");

// Helper robusto p/ flags 's'
function isYes($value): bool
{
  if ($value === null) return false;
  $v = mb_strtolower(trim((string)$value));
  return in_array($v, ['s', 'sim', '1', 'true', 't', 'y', 'yes', 'on'], true);
}

$internacaoDAO = new internacaoDAO($conn, $BASE_URL);

// ---------------------
// Filtros
// ---------------------
$pesquisa_nome = filter_input(INPUT_GET, 'pesquisa_nome', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';
$pesquisa_pac  = filter_input(INPUT_GET, 'pesquisa_pac',   FILTER_SANITIZE_SPECIAL_CHARS) ?: '';
$limite        = (int)(filter_input(INPUT_GET, 'limite') ?: 10);
$sortField     = filter_input(INPUT_GET, 'sort_field') ?: (filter_input(INPUT_GET, 'ordenar') ?: 'id_internacao');
$sortDir       = strtolower((string)(filter_input(INPUT_GET, 'sort_dir') ?: 'desc'));
$paginaAtual   = (int)(filter_input(INPUT_GET, 'pag') ?: 1);

$ordenarPermitido = [
  'id_internacao' => 'ac.id_internacao',
  'nome_pac'      => 'pa.nome_pac',
  'nome_hosp'     => 'ho.nome_hosp',
  'data_intern_int' => 'ac.data_intern_int',
];
if (!isset($ordenarPermitido[$sortField])) {
  $sortField = 'id_internacao';
}
if (!in_array($sortDir, ['asc', 'desc'], true)) {
  $sortDir = 'desc';
}
$ordenarSql = $ordenarPermitido[$sortField] . ' ' . strtoupper($sortDir) . ', ac.id_internacao ' . strtoupper($sortDir);

// tri-state: '', 's', 'n'
$faturada  = filter_input(INPUT_GET, 'faturada',  FILTER_SANITIZE_SPECIAL_CHARS);
$aberta    = filter_input(INPUT_GET, 'aberta',    FILTER_SANITIZE_SPECIAL_CHARS);
$auditoria = filter_input(INPUT_GET, 'auditoria', FILTER_SANITIZE_SPECIAL_CHARS);
$encerrada = filter_input(INPUT_GET, 'encerrada', FILTER_SANITIZE_SPECIAL_CHARS);

foreach (['faturada', 'aberta', 'auditoria', 'encerrada'] as $k) {
  if (!in_array($$k, ['', 's', 'n', null], true)) $$k = '';
}

// ---------------------
// WHERE
// ---------------------
$condicoes = [];
$whereParams = [];
if ($pesquisa_nome !== '') {
  $condicoes[] = 'ho.nome_hosp LIKE :pesquisa_nome';
  $whereParams[':pesquisa_nome'] = '%' . $pesquisa_nome . '%';
}
if ($pesquisa_pac  !== '') {
  $condicoes[] = 'pa.nome_pac LIKE :pesquisa_pac';
  $whereParams[':pesquisa_pac'] = '%' . $pesquisa_pac . '%';
}

if ($faturada === 's') {
  $condicoes[] = "EXISTS (
    SELECT 1
      FROM tb_capeante caf
     WHERE caf.fk_int_capeante = ac.id_internacao
       AND caf.conta_faturada_cap = 's'
  )";
} elseif ($faturada === 'n') {
  $condicoes[] = "NOT EXISTS (
    SELECT 1
      FROM tb_capeante caf
     WHERE caf.fk_int_capeante = ac.id_internacao
       AND caf.conta_faturada_cap = 's'
  )";
}

if ($aberta === 's') {
  $condicoes[] = "EXISTS (
    SELECT 1
      FROM tb_capeante caa
     WHERE caa.fk_int_capeante = ac.id_internacao
       AND caa.aberto_cap = 's'
  )";
} elseif ($aberta === 'n') {
  $condicoes[] = "NOT EXISTS (
    SELECT 1
      FROM tb_capeante caa
     WHERE caa.fk_int_capeante = ac.id_internacao
       AND caa.aberto_cap = 's'
  )";
}

if ($encerrada === 's') {
  $condicoes[] = "EXISTS (
    SELECT 1
      FROM tb_capeante cae
     WHERE cae.fk_int_capeante = ac.id_internacao
       AND cae.encerrado_cap = 's'
  )";
} elseif ($encerrada === 'n') {
  $condicoes[] = "NOT EXISTS (
    SELECT 1
      FROM tb_capeante cae
     WHERE cae.fk_int_capeante = ac.id_internacao
       AND cae.encerrado_cap = 's'
  )";
}

if ($auditoria === 's') {
  $condicoes[] = "EXISTS (
    SELECT 1
      FROM tb_capeante cau
     WHERE cau.fk_int_capeante = ac.id_internacao
       AND (cau.em_auditoria_cap = 's' OR cau.med_check = 's' OR cau.enfer_check = 's' OR cau.adm_check = 's')
  )";
} elseif ($auditoria === 'n') {
  $condicoes[] = "NOT EXISTS (
    SELECT 1
      FROM tb_capeante cau
     WHERE cau.fk_int_capeante = ac.id_internacao
       AND (cau.em_auditoria_cap = 's' OR cau.med_check = 's' OR cau.enfer_check = 's' OR cau.adm_check = 's')
  )";
}

$where = implode(' AND ', $condicoes);

// ---------------------
// Paginação por SQL (itens únicos) + busca apenas da página
// ---------------------
$baseFromSql = '
  FROM tb_internacao ac
  LEFT JOIN tb_hospital AS ho ON ac.fk_hospital_int = ho.id_hospital
  LEFT JOIN tb_paciente AS pa ON ac.fk_paciente_int = pa.id_paciente
';
$whereSql = $where !== '' ? (' WHERE ' . $where) : '';

$totalDeItensUnicos = 0;
try {
  $sqlCount = 'SELECT COUNT(DISTINCT ac.id_internacao) AS total ' . $baseFromSql . $whereSql;
  $stmtCount = $conn->prepare($sqlCount);
  foreach ($whereParams as $key => $value) {
    $stmtCount->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
  }
  $stmtCount->execute();
  $totalDeItensUnicos = (int)($stmtCount->fetchColumn() ?: 0);
} catch (Throwable $e) {
  error_log('[JORNADA][COUNT] ' . $e->getMessage());
}

$obPagination       = new pagination($totalDeItensUnicos, $paginaAtual, $limite);
$limiteSql          = $obPagination->getLimit();
list($offset, $qtde) = array_map('intval', explode(',', $limiteSql));

// IDs da página atual
$idsDaPagina = [];
if ($totalDeItensUnicos > 0) {
  try {
    $sqlIds = 'SELECT DISTINCT ac.id_internacao ' . $baseFromSql . $whereSql .
      ' ORDER BY ' . $ordenarSql . ' LIMIT ' . $offset . ',' . $qtde;
    $stmtIds = $conn->prepare($sqlIds);
    foreach ($whereParams as $key => $value) {
      $stmtIds->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmtIds->execute();
    $idsDaPagina = array_map(
      'intval',
      array_column($stmtIds->fetchAll(PDO::FETCH_ASSOC), 'id_internacao')
    );
  } catch (Throwable $e) {
    error_log('[JORNADA][IDS] ' . $e->getMessage());
  }
}

// Busca apenas as linhas necessárias (com todos os capeantes dessas internações da página)
$linhasFiltradas = [];
if (!empty($idsDaPagina)) {
  $idPlaceholders = [];
  foreach (array_values($idsDaPagina) as $idx => $idPagina) {
    $ph = ':id_pagina_' . $idx;
    $idPlaceholders[] = $ph;
    $whereParams[$ph] = (int)$idPagina;
  }
  $sqlPagina = "
    SELECT
      ac.id_internacao,
      ac.data_intern_int,
      ho.nome_hosp,
      pa.nome_pac,
      ca.id_capeante,
      ca.senha_finalizada,
      ca.adm_check,
      ca.med_check,
      ca.enfer_check,
      ca.aberto_cap,
      ca.em_auditoria_cap,
      ca.encerrado_cap,
      ca.conta_faturada_cap
    FROM tb_internacao ac
    LEFT JOIN tb_hospital ho ON ac.fk_hospital_int = ho.id_hospital
    LEFT JOIN tb_paciente pa ON ac.fk_paciente_int = pa.id_paciente
    LEFT JOIN tb_capeante ca ON ac.id_internacao = ca.fk_int_capeante
    WHERE ac.id_internacao IN (" . implode(',', $idPlaceholders) . ")
    ORDER BY " . $ordenarSql;
  try {
    $stmtPagina = $conn->prepare($sqlPagina);
    foreach ($whereParams as $key => $value) {
      if (str_starts_with((string)$key, ':id_pagina_')) {
        $stmtPagina->bindValue($key, (int)$value, PDO::PARAM_INT);
      }
    }
    $stmtPagina->execute();
    $linhasFiltradas = $stmtPagina->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) {
    error_log('[JORNADA][PAGE_ROWS] ' . $e->getMessage());
    $linhasFiltradas = [];
  }
}

// agrega por id_internacao
$linhasUnicas         = [];
$internacoesAgregadas = [];

foreach ($linhasFiltradas as $linha) {
  $id = (int)($linha['id_internacao'] ?? 0);
  if (!$id) {
    continue;
  }

  if (!isset($linhasUnicas[$id])) {
    $linhasUnicas[$id] = $linha;
  }
  if (!isset($internacoesAgregadas[$id])) {
    $internacoesAgregadas[$id] = [
      'auditoria'   => false,
      'finalizada'  => false,
      'faturada'    => false,
      'aberta'      => false,
      'encerrada'   => false,
      'id_capeante' => 0,
    ];
  }

  if (
    isYes($linha['em_auditoria_cap'] ?? '') ||
    isYes($linha['med_check'] ?? '') ||
    isYes($linha['enfer_check'] ?? '') ||
    isYes($linha['adm_check'] ?? '')
  ) {
    $internacoesAgregadas[$id]['auditoria'] = true;
  }
  if (isYes($linha['senha_finalizada'] ?? '')) {
    $internacoesAgregadas[$id]['finalizada'] = true;
  }
  if (isYes($linha['conta_faturada_cap'] ?? '')) {
    $internacoesAgregadas[$id]['faturada'] = true;
  }
  if (isYes($linha['aberto_cap'] ?? '')) {
    $internacoesAgregadas[$id]['aberta'] = true;
  }
  if (isYes($linha['encerrado_cap'] ?? '')) {
    $internacoesAgregadas[$id]['encerrada'] = true;
  }

  $idCap = (int)($linha['id_capeante'] ?? 0);
  if ($idCap > $internacoesAgregadas[$id]['id_capeante']) {
    $internacoesAgregadas[$id]['id_capeante'] = $idCap;
  }
}

// mantém exatamente a ordem dos IDs paginados
$query = [];
foreach ($idsDaPagina as $idPagina) {
  if (isset($linhasUnicas[$idPagina])) {
    $query[] = $linhasUnicas[$idPagina];
  }
}

// preparar blocos de páginas
$paginas       = $obPagination->getPages(); // array com ['pg'=>N,'bloco'=>B, ...]
$blocoAtual    = isset($_GET['bl']) ? (int)$_GET['bl'] : 0;
$paginasBloco  = [];
$primeiraPag   = 0;
$ultimaPag     = 0;
$primeiroBloco = 0;
$ultimoBloco   = 0;
$blocoCorrente = 0;

if (!empty($paginas)) {
  $blocoCorrente = (int)floor($blocoAtual / 5) + 1;
  foreach ($paginas as $p) {
    if (($p['bloco'] ?? 0) == $blocoCorrente) {
      // marca current por comparação com página atual
      $p['current'] = ((int)($p['pg'] ?? 0) === $paginaAtual);
      $paginasBloco[] = $p;
    }
  }
  if (!empty($paginasBloco)) {
    $primeiraPag = (int)reset($paginasBloco)['pg'];
    $ultimaPag   = (int)end($paginasBloco)['pg'];
  }
  $primeiroBloco = (int)reset($paginas)['bloco'];
  $ultimoBloco   = (int)end($paginas)['bloco'];
}

// URL base
$self      = basename($_SERVER['PHP_SELF']);
$actionUrl = $self;
$urlParams = http_build_query([
  'pesquisa_nome' => $pesquisa_nome,
  'pesquisa_pac'  => $pesquisa_pac,
  'limite'        => $limite,
  'sort_field'    => $sortField,
  'sort_dir'      => $sortDir,
  'faturada'      => $faturada,
  'aberta'        => $aberta,
  'auditoria'     => $auditoria,
  'encerrada'     => $encerrada,
]);
$urlBase = $self . '?' . $urlParams;

$buildSortUrl = function (string $field, string $dir) use (
  $self,
  $pesquisa_nome,
  $pesquisa_pac,
  $limite,
  $faturada,
  $aberta,
  $auditoria,
  $encerrada
) {
  $params = [
    'pesquisa_nome' => $pesquisa_nome,
    'pesquisa_pac'  => $pesquisa_pac,
    'limite'        => $limite,
    'sort_field'    => $field,
    'sort_dir'      => $dir,
    'faturada'      => $faturada,
    'aberta'        => $aberta,
    'auditoria'     => $auditoria,
    'encerrada'     => $encerrada,
    'pag'           => 1,
    'bl'            => 0,
  ];
  $params = array_filter($params, function ($value) {
    return $value !== null && $value !== '';
  });
  return $self . '?' . http_build_query($params);
};
?>

<style>
/* === TIMELINE === */
.timeline-container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 10px 0;
    min-width: 400px
}

.timeline-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    text-align: center;
    width: 80px
}

.timeline-dot {
    width: 20px;
    height: 20px;
    background: #e0e0e0;
    border-radius: 50%;
    border: 3px solid #fff;
    box-shadow: 0 0 0 2px #e0e0e0;
    transition: background-color .3s, box-shadow .3s;
    z-index: 2
}

.timeline-label {
    margin-top: 8px;
    font-size: 12px;
    color: #a0a0a0;
    font-weight: 500
}

.timeline-connector {
    flex-grow: 1;
    height: 4px;
    background: #e0e0e0;
    margin: 0 -2px;
    transition: background-color .3s
}

.timeline-step.step-1-completed .timeline-dot {
    background: #35bae1;
    box-shadow: 0 0 0 2px #35bae1
}

.timeline-step.step-1-completed .timeline-label {
    color: #35bae1;
    font-weight: 600
}

.timeline-step.step-1-completed+.timeline-connector {
    background-color: #35bae1
}

.timeline-step.step-2-completed .timeline-dot {
    background: #5e2363;
    box-shadow: 0 0 0 2px #5e2363
}

.timeline-step.step-2-completed .timeline-label {
    color: #5e2363;
    font-weight: 600
}

.timeline-step.step-2-completed+.timeline-connector {
    background-color: #5e2363
}

.timeline-step.step-3-completed .timeline-dot {
    background: #4CAF50;
    box-shadow: 0 0 0 2px #4CAF50
}

.timeline-step.step-3-completed .timeline-label {
    color: #4CAF50;
    font-weight: 600
}

.connector-green {
    background: #4CAF50
}

.connector-red {
    background: #dc3545
}

.timeline-step.step-4-completed .timeline-dot {
    background: #dc3545;
    box-shadow: 0 0 0 2px #dc3545
}

.timeline-step.step-4-completed .timeline-label {
    color: #dc3545;
    font-weight: 600
}

/* TH “Ações para faturar” com caixa separada */
th.th-acoes {
    white-space: nowrap;
    vertical-align: middle;
}

.acoes-head {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .5rem;
}

.acoes-head .label {
    font-weight: 600;
    color: #444;
}

.th-sortable {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.th-sortable .sort-icons a {
    text-decoration: none;
    font-size: 0.85rem;
    color: #6c757d;
    margin-left: 2px;
    opacity: 0.9;
    font-weight: 700;
}

.th-sortable .sort-icons a.active {
    color: #5e2363;
    opacity: 1;
    font-weight: bold;
}
</style>
<link rel="stylesheet" href="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/css/listagem_padrao.css', ENT_QUOTES, 'UTF-8') ?>">

<div class="container-fluid form_container" style="margin-top:8px;">
    <h4 class="page-title m-0 mb-3" style="color:#3A3A3A;">Capeantes - Jornada da Conta</h4>

    <form action="<?= htmlspecialchars($actionUrl) ?>" id="filtros-form" method="GET" class="listagem-panel">
        <div class="row legacy-filter-row align-items-end">
            <div class="col-sm-3">
                <label class="form-label mb-0 small text-muted">Hospital</label>
                <input class="form-control form-control-sm" type="text" name="pesquisa_nome"
                    placeholder="Nome do Hospital" value="<?= htmlspecialchars($pesquisa_nome) ?>">
            </div>
            <div class="col-sm-3">
                <label class="form-label mb-0 small text-muted">Paciente</label>
                <input class="form-control form-control-sm" type="text" name="pesquisa_pac"
                    placeholder="Nome do Paciente" value="<?= htmlspecialchars($pesquisa_pac) ?>">
            </div>
            <div class="col-sm-2">
                <label class="form-label mb-0 small text-muted">Registros</label>
                <select class="form-control form-control-sm" name="limite">
                    <option value="10" <?= $limite == 10 ? 'selected' : '' ?>>10 por pág.</option>
                    <option value="20" <?= $limite == 20 ? 'selected' : '' ?>>20 por pág.</option>
                    <option value="50" <?= $limite == 50 ? 'selected' : '' ?>>50 por pág.</option>
                </select>
            </div>
            <div class="col-sm-2">
                <label class="form-label mb-0 small text-muted">Ordenar</label>
                <select class="form-control form-control-sm" name="sort_field">
                    <option value="id_internacao" <?= $sortField === 'id_internacao' ? 'selected' : '' ?>>Internação
                    </option>
                    <option value="nome_pac" <?= $sortField === 'nome_pac' ? 'selected' : '' ?>>Paciente</option>
                    <option value="nome_hosp" <?= $sortField === 'nome_hosp' ? 'selected' : '' ?>>Hospital</option>
                    <option value="data_intern_int" <?= $sortField === 'data_intern_int' ? 'selected' : '' ?>>Data
                    </option>
                </select>
                <input type="hidden" name="sort_dir" value="<?= htmlspecialchars($sortDir, ENT_QUOTES, 'UTF-8') ?>">
            </div>
        </div>

        <div class="row legacy-filter-row align-items-end mt-1">
            <div class="col-sm-2">
                <label class="form-label mb-0 small text-muted">Abertas</label>
                <select class="form-control form-control-sm" name="aberta">
                    <option value="" <?= $aberta === '' ? 'selected' : '' ?>>Todas</option>
                    <option value="s" <?= $aberta === 's' ? 'selected' : '' ?>>Somente abertas</option>
                    <option value="n" <?= $aberta === 'n' ? 'selected' : '' ?>>Somente não abertas</option>
                </select>
            </div>
            <div class="col-sm-2">
                <label class="form-label mb-0 small text-muted">Em Auditoria</label>
                <select class="form-control form-control-sm" name="auditoria">
                    <option value="" <?= $auditoria === '' ? 'selected' : '' ?>>Todas</option>
                    <option value="s" <?= $auditoria === 's' ? 'selected' : '' ?>>Somente em auditoria</option>
                    <option value="n" <?= $auditoria === 'n' ? 'selected' : '' ?>>Somente fora de auditoria</option>
                </select>
            </div>
            <div class="col-sm-2">
                <label class="form-label mb-0 small text-muted">Encerradas</label>
                <select class="form-control form-control-sm" name="encerrada">
                    <option value="" <?= $encerrada === '' ? 'selected' : '' ?>>Todas</option>
                    <option value="s" <?= $encerrada === 's' ? 'selected' : '' ?>>Somente encerradas</option>
                    <option value="n" <?= $encerrada === 'n' ? 'selected' : '' ?>>Somente não encerradas</option>
                </select>
            </div>
            <div class="col-sm-2">
                <label class="form-label mb-0 small text-muted">Faturadas</label>
                <select class="form-control form-control-sm" name="faturada">
                    <option value="" <?= $faturada === '' ? 'selected' : '' ?>>Todas</option>
                    <option value="s" <?= $faturada === 's' ? 'selected' : '' ?>>Somente faturadas</option>
                    <option value="n" <?= $faturada === 'n' ? 'selected' : '' ?>>Somente não faturadas</option>
                </select>
            </div>
            <div class="col-sm-1 filter-actions d-flex align-items-end gap-2">
                <button type="submit"
                    class="btn btn-primary btn-sm btn-filtro-buscar d-inline-flex align-items-center justify-content-center btn-filtro-limpar-icon">
                    <span class="material-icons">search</span>
                </button>
                <a href="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/list_internacao_cap_jornada.php', ENT_QUOTES, 'UTF-8') ?>"
                    class="btn btn-light btn-sm btn-filtro-limpar btn-filtro-limpar-icon"
                    title="Limpar filtros" aria-label="Limpar filtros">
                    <i class="bi bi-trash3"></i>
                </a>
            </div>
        </div>
    </form>

    <div id="table-container" class="mt-3">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th>
                            <div class="th-sortable">
                                <span>Reg</span>
                                <span class="sort-icons">
                                    <a class="ajax-link <?= ($sortField === 'id_internacao' && $sortDir === 'asc') ? 'active' : '' ?>"
                                        href="<?= htmlspecialchars($buildSortUrl('id_internacao', 'asc'), ENT_QUOTES, 'UTF-8') ?>"
                                        title="Ordenar crescente">↑</a>
                                    <a class="ajax-link <?= ($sortField === 'id_internacao' && $sortDir === 'desc') ? 'active' : '' ?>"
                                        href="<?= htmlspecialchars($buildSortUrl('id_internacao', 'desc'), ENT_QUOTES, 'UTF-8') ?>"
                                        title="Ordenar decrescente">↓</a>
                                </span>
                            </div>
                        </th>
                        <th>Conta</th>
                        <th>
                            <div class="th-sortable">
                                <span>Hospital</span>
                                <span class="sort-icons">
                                    <a class="ajax-link <?= ($sortField === 'nome_hosp' && $sortDir === 'asc') ? 'active' : '' ?>"
                                        href="<?= htmlspecialchars($buildSortUrl('nome_hosp', 'asc'), ENT_QUOTES, 'UTF-8') ?>"
                                        title="Ordenar crescente">↑</a>
                                    <a class="ajax-link <?= ($sortField === 'nome_hosp' && $sortDir === 'desc') ? 'active' : '' ?>"
                                        href="<?= htmlspecialchars($buildSortUrl('nome_hosp', 'desc'), ENT_QUOTES, 'UTF-8') ?>"
                                        title="Ordenar decrescente">↓</a>
                                </span>
                            </div>
                        </th>
                        <th>
                            <div class="th-sortable">
                                <span>Paciente</span>
                                <span class="sort-icons">
                                    <a class="ajax-link <?= ($sortField === 'nome_pac' && $sortDir === 'asc') ? 'active' : '' ?>"
                                        href="<?= htmlspecialchars($buildSortUrl('nome_pac', 'asc'), ENT_QUOTES, 'UTF-8') ?>"
                                        title="Ordenar crescente">↑</a>
                                    <a class="ajax-link <?= ($sortField === 'nome_pac' && $sortDir === 'desc') ? 'active' : '' ?>"
                                        href="<?= htmlspecialchars($buildSortUrl('nome_pac', 'desc'), ENT_QUOTES, 'UTF-8') ?>"
                                        title="Ordenar decrescente">↓</a>
                                </span>
                            </div>
                        </th>
                        <th>
                            <div class="th-sortable">
                                <span>Data Inter.</span>
                                <span class="sort-icons">
                                    <a class="ajax-link <?= ($sortField === 'data_intern_int' && $sortDir === 'asc') ? 'active' : '' ?>"
                                        href="<?= htmlspecialchars($buildSortUrl('data_intern_int', 'asc'), ENT_QUOTES, 'UTF-8') ?>"
                                        title="Ordenar crescente">↑</a>
                                    <a class="ajax-link <?= ($sortField === 'data_intern_int' && $sortDir === 'desc') ? 'active' : '' ?>"
                                        href="<?= htmlspecialchars($buildSortUrl('data_intern_int', 'desc'), ENT_QUOTES, 'UTF-8') ?>"
                                        title="Ordenar decrescente">↓</a>
                                </span>
                            </div>
                        </th>
                        <th style="width:40%">Jornada da Conta</th>
                        <th class="text-center th-acoes">
                            <div class="acoes-head">
                                <span class="label">Ações para faturar</span>
                                <div class="form-check m-0" title="Selecionar todos da página">
                                    <input class="form-check-input" type="checkbox" id="chkTodos">
                                    <label class="form-check-label small ms-1" for="chkTodos">Selecionar todos</label>
                                </div>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($query)) : ?>
                    <?php foreach ($query as $intern) : ?>
                    <?php
              $idInt = (int)($intern['id_internacao'] ?? 0);
              $agg   = $internacoesAgregadas[$idInt] ?? ['auditoria' => false, 'finalizada' => false, 'faturada' => false, 'aberta' => false, 'encerrada' => false, 'id_capeante' => 0];

              // === ALTERAÇÃO ÚNICA: marcar cada etapa apenas pela flag real do BD ===
              $step1_aberta     = (bool)($agg['aberta'] ?? false);      // ca.aberto_cap = 's'
              $step2_auditoria  = (bool)($agg['auditoria'] ?? false);   // em_auditoria_cap || med_check || enfer_check || adm_check
              $step3_finalizada = (bool)($agg['finalizada'] ?? false);  // senha_finalizada = 's'
              $step4_faturada   = (bool)($agg['faturada'] ?? false);    // conta_faturada_cap = 's'

              $idCapeante = (int)($agg['id_capeante'] ?? 0);
              ?>
                    <tr>
                        <td><b><?= $idInt ?></b></td>
                        <td><?= $idCapeante ?></td>
                        <td><?= htmlspecialchars($intern["nome_hosp"] ?? '') ?></td>
                        <td><?= htmlspecialchars($intern["nome_pac"] ?? '') ?></td>
                        <td><?= !empty($intern["data_intern_int"]) ? date('d/m/Y', strtotime($intern["data_intern_int"])) : '' ?>
                        </td>
                        <td>
                            <div class="timeline-container">
                                <div class="timeline-step <?= $step1_aberta ? 'step-1-completed' : '' ?>">
                                    <div class="timeline-dot"></div>
                                    <div class="timeline-label">Aberta</div>
                                </div>
                                <div class="timeline-connector"></div>
                                <div class="timeline-step <?= $step2_auditoria ? 'step-2-completed' : '' ?>">
                                    <div class="timeline-dot"></div>
                                    <div class="timeline-label">Em Auditoria</div>
                                </div>
                                <div class="timeline-connector"></div>
                                <div class="timeline-step <?= $step3_finalizada ? 'step-3-completed' : '' ?>">
                                    <div class="timeline-dot"></div>
                                    <div class="timeline-label">Finalizada</div>
                                </div>
                                <div
                                    class="timeline-connector <?= $step4_faturada ? 'connector-red' : ($step3_finalizada ? 'connector-green' : '') ?>">
                                </div>
                                <div class="timeline-step <?= $step4_faturada ? 'step-4-completed' : '' ?>">
                                    <div class="timeline-dot"></div>
                                    <div class="timeline-label">Faturada</div>
                                </div>
                            </div>
                        </td>
                        <td class="text-center">
                            <?php if (!$step4_faturada): ?>
                            <input type="checkbox" class="form-check-input chk-faturar-linha" value="<?= $idCapeante ?>"
                                title="Selecionar para faturar">
                            <?php else: ?>
                            <span class="badge bg-success">Faturada</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center p-4">Nenhum registro encontrado com os filtros aplicados.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- LINHA FINAL: paginação CENTRAL e botão à DIREITA -->
        <div class="d-flex justify-content-center align-items-center mt-3 position-relative">

            <?php if ($totalDeItensUnicos > $limite && !empty($paginasBloco)) : ?>
            <nav aria-label="Navegação das páginas">
                <ul class="pagination m-0">
                    <?php if ($blocoCorrente > $primeiroBloco): ?>
                    <?php
              $pagAnterior   = max(1, $primeiraPag - 1);
              $blocoAnterior = max(0, $blocoAtual - 5);
              ?>
                    <li class="page-item"><a class="page-link ajax-link" href="<?= $urlBase ?>&pag=1&bl=0"
                            aria-label="Primeira">&laquo;</a></li>
                    <li class="page-item"><a class="page-link ajax-link"
                            href="<?= $urlBase ?>&pag=<?= $pagAnterior ?>&bl=<?= $blocoAnterior ?>"
                            aria-label="Anterior">&lsaquo;</a></li>
                    <?php endif; ?>

                    <?php foreach ($paginasBloco as $p): ?>
                    <?php
              $pg = (int)($p['pg'] ?? 0);
              $isCurrent = (bool)($p['current'] ?? ($pg === $paginaAtual));
              ?>
                    <li class="page-item <?= $isCurrent ? 'active' : '' ?>"
                        <?= $isCurrent ? 'aria-current="page"' : '' ?>>
                        <a class="page-link ajax-link"
                            href="<?= $urlBase ?>&pag=<?= $pg ?>&bl=<?= $blocoAtual ?>"><?= $pg ?></a>
                    </li>
                    <?php endforeach; ?>

                    <?php if ($blocoCorrente < $ultimoBloco): ?>
                    <?php
              $pagProxima   = $ultimaPag + 1;
              $blocoProximo = $blocoAtual + 5;
              $blocoUltima  = ($ultimoBloco - 1) * 5;
              ?>
                    <li class="page-item"><a class="page-link ajax-link"
                            href="<?= $urlBase ?>&pag=<?= $pagProxima ?>&bl=<?= $blocoProximo ?>"
                            aria-label="Próxima">&rsaquo;</a></li>
                    <li class="page-item"><a class="page-link ajax-link"
                            href="<?= $urlBase ?>&pag=<?= count($paginas) ?>&bl=<?= $blocoUltima ?>"
                            aria-label="Última">&raquo;</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>

            <div class="position-absolute end-0">
                <button type="button" id="btnFaturarSelecionados" class="btn btn-danger">
                    Faturar Selecionados <span class="badge bg-light text-dark ms-2" id="badgeSel">0</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JS (jQuery + Bootstrap bundle já vêm do header; se não, inclua) -->
<script>
(function() {
    const $wrap = $('#table-container');
    let loadingTimer = null;

    function setLoading(active) {
        if (active) {
            if (loadingTimer) {
                clearTimeout(loadingTimer);
            }
            // Evita flicker: mostra apenas se demorar mais que 180ms
            loadingTimer = setTimeout(function() {
                if (!$wrap.find('.ajax-loading-indicator').length) {
                    $wrap.prepend(
                        '<div class="ajax-loading-indicator alert alert-light py-2 px-3 mb-2 small">Carregando...</div>'
                    );
                }
            }, 180);
            return;
        }
        if (loadingTimer) {
            clearTimeout(loadingTimer);
            loadingTimer = null;
        }
        $wrap.find('.ajax-loading-indicator').remove();
    }

    function loadContent(url) {
        setLoading(true);
        $.ajax({
            url: url,
            type: 'GET',
            success: function(response) {
                const $resp = $('<div>').html(response);
                const $chunk = $resp.find('#table-container');
                if ($chunk.length) {
                    $wrap.html($chunk.html());
                } else {
                    $wrap.html(
                        '<div class="p-3 border rounded text-danger"><b>Erro:</b> #table-container não encontrado na resposta.</div>'
                    );
                }
                history.pushState(null, '', url);
            },
            error: function(xhr) {
                $wrap.html(
                    '<div class="text-center text-danger p-4"><b>Erro na requisição AJAX.</b> Status: ' +
                    xhr.status + ' ' + xhr.statusText + '</div>');
            },
            complete: function() {
                setLoading(false);
            }
        });
    }

    // Paginação por AJAX
    $(document).on('click', 'a.ajax-link', function(e) {
        e.preventDefault();
        loadContent($(this).attr('href'));
    });

    // Submissão dos filtros por AJAX
    $(document).on('submit', '#filtros-form', function(e) {
        e.preventDefault();
        const url = $(this).attr('action') + '?' + $(this).serialize();
        loadContent(url);
    });

    // Navegação browser
    window.addEventListener('popstate', function() {
        loadContent(location.href);
    });

    // Seleção de checkboxes
    function atualizarBadge() {
        $('#badgeSel').text($('.chk-faturar-linha:checked').length);
    }
    $(document).on('change', '#chkTodos', function() {
        const chk = this.checked;
        $('.chk-faturar-linha').each(function() {
            this.checked = chk;
        });
        atualizarBadge();
    });
    $(document).on('change', '.chk-faturar-linha', atualizarBadge);

    // Faturar selecionados
    $(document).on('click', '#btnFaturarSelecionados', function() {
        const ids = $('.chk-faturar-linha:checked').map(function() {
            return $(this).val();
        }).get();
        if (!ids.length) {
            alert('Selecione pelo menos uma conta.');
            return;
        }
        if (!confirm(`Confirmar o faturamento de ${ids.length} conta(s)?`)) return;

        $.ajax({
            url: 'processa_faturamento.php',
            method: 'POST',
            data: {
                ids: ids
            },
            dataType: 'json',
            success: function(resp) {
                if (resp && resp.success) {
                    loadContent(location.href);
                } else {
                    alert((resp && resp.message) ? resp.message : 'Falha ao faturar.');
                }
            },
            error: function() {
                alert('Erro de comunicação ao tentar faturar.');
            }
        });
    });
})();
</script>
