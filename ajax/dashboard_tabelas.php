<?php
define('SKIP_HEADER', true);
chdir(__DIR__ . '/..');
require_once(__DIR__ . '/../globals.php');
require_once(__DIR__ . '/_auth_scope.php');
include_once(__DIR__ . '/../check_logado.php');
include_once(__DIR__ . '/../models/internacao.php');
include_once(__DIR__ . '/../dao/internacaoDao.php');
include_once(__DIR__ . '/../dao/indicadoresDao.php');

header('Content-Type: text/html; charset=utf-8');

try {
ajax_require_active_session();
$ctx = ajax_user_context($conn);
$scopeMode = ajax_scope_mode($ctx);

$hospital_selecionado = (int)(filter_input(INPUT_POST, 'hospital_id', FILTER_SANITIZE_NUMBER_INT)
    ?: filter_input(INPUT_GET, 'hospital_id', FILTER_SANITIZE_NUMBER_INT));
$id_usuario_sessao    = (int)($ctx['user_id'] ?? 0);
$seguradoraUserId     = (int)($ctx['seguradora_id'] ?? 0);

if ($hospital_selecionado > 0 && !ajax_assert_hospital_access($conn, $ctx, $hospital_selecionado)) {
    $hospital_selecionado = -1;
}

$condicoes_vis = [
    $hospital_selecionado ? "ac.fk_hospital_int = {$hospital_selecionado}" : null,
    "ac.internado_int = 's'",
    "(vi.id_visita = (SELECT MAX(vi2.id_visita) FROM tb_visita vi2 WHERE vi2.fk_internacao_vis = ac.id_internacao) OR vi.id_visita IS NULL)",
    $scopeMode === 'seguradora'
        ? ($seguradoraUserId > 0 ? "pa.fk_seguradora_pac = {$seguradoraUserId}" : '1=0')
        : null,
    $scopeMode === 'hospital' && $id_usuario_sessao > 0
        ? "ac.fk_hospital_int IN (SELECT hu.fk_hospital_user FROM tb_hospitalUser hu WHERE hu.fk_usuario_hosp = {$id_usuario_sessao})"
        : null,
];
$condicoes_hospital = [
    "DATEDIFF(CURRENT_DATE(), i.data_intern_int) > COALESCE(s.longa_permanencia_seg, 0)",
    $hospital_selecionado ? "i.fk_hospital_int = {$hospital_selecionado}" : null,
    "i.internado_int = 's'",
    $scopeMode === 'hospital' && $id_usuario_sessao > 0
        ? "i.fk_hospital_int IN (SELECT hu.fk_hospital_user FROM tb_hospitalUser hu WHERE hu.fk_usuario_hosp = {$id_usuario_sessao})"
        : null,
    $scopeMode === 'seguradora'
        ? ($seguradoraUserId > 0 ? "p.fk_seguradora_pac = {$seguradoraUserId}" : '1=0')
        : null
];

$where_vis      = implode(' AND ', array_filter($condicoes_vis));
$where_hospital = implode(' AND ', array_filter($condicoes_hospital));

$Internacao_geral = new internacaoDAO($conn, $BASE_URL);
$indicadores      = new indicadoresDAO($conn, $BASE_URL);

$dados_internacoes_visitas = $Internacao_geral->selectInternVisLastWhere($where_vis);

$ultimaVisitaPorInternacao = [];
foreach ((array)$dados_internacoes_visitas as $vis) {
    $id = (int)($vis['id_internacao'] ?? $vis['fk_internacao_vis'] ?? 0);
    $dataVisita = $vis['data_visita_vis'] ?? null;
    if ($id <= 0 || empty($dataVisita)) {
        continue;
    }
    $ts = strtotime($dataVisita);
    if ($ts === false) {
        continue;
    }
    if (!isset($ultimaVisitaPorInternacao[$id]) || $ts > $ultimaVisitaPorInternacao[$id]['ts']) {
        $ultimaVisitaPorInternacao[$id] = [
            'data' => $dataVisita,
            'ts' => $ts,
        ];
    }
}

function filterVisitasAtrasadas($value)
{
    $hoje  = new DateTime('today');
    $toDate = function ($s) {
        if (empty($s)) return null;
        $dt = DateTime::createFromFormat('Y-m-d', $s);
        if ($dt instanceof DateTime) return $dt;
        $ts = strtotime($s);
        if ($ts === false) return null;
        $dt = new DateTime();
        $dt->setTimestamp($ts);
        return $dt;
    };
    $dtVisita = $toDate($value['data_visita_vis'] ?? null);
    $dtIntern = $toDate($value['data_visita_int'] ?? null);
    $limiteDias = (int)($value['dias_visita_seg'] ?? 0);
    if ($limiteDias <= 0) {
        $limiteDias = 10;
    }

    if ($dtVisita instanceof DateTime) {
        $dias = ($dtVisita > $hoje) ? 0 : $dtVisita->diff($hoje)->days;
        return $dias > $limiteDias;
    }
    if ($dtIntern instanceof DateTime) {
        $dias = ($dtIntern > $hoje) ? 0 : $dtIntern->diff($hoje)->days;
        return $dias > $limiteDias;
    }
    return false;
}

function diasDesdeData($data)
{
    if (empty($data)) {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $data);
    if (!($dt instanceof DateTime)) {
        $ts = strtotime($data);
        if ($ts === false) {
            return null;
        }
        $dt = new DateTime();
        $dt->setTimestamp($ts);
    }
    $hoje = new DateTime('today');
    if ($dt > $hoje) {
        return 0;
    }
    return $dt->diff($hoje)->days;
}

$dados_visitas_atraso = [];
foreach ((array)$dados_internacoes_visitas as $v) {
    $diasUlt = diasDesdeData($v['data_visita_vis'] ?? ($v['data_visita_int'] ?? null));
    if ($diasUlt === null) continue;
    $limite = (int)($v['dias_visita_seg'] ?? 0);
    if ($limite <= 0) $limite = 10;
    $atraso = $diasUlt - $limite;
    if ($atraso <= 0) continue;
    $v['_dias_atraso'] = $atraso;
    $dados_visitas_atraso[] = $v;
}
usort($dados_visitas_atraso, function ($a, $b) {
    return ($b['_dias_atraso'] ?? 0) <=> ($a['_dias_atraso'] ?? 0);
});
$dados_visitas_atraso_list = array_slice($dados_visitas_atraso, 0, 50);

$longa_perm = $indicadores->getLongaPermanencia($where_hospital);
$longa_perm_list = $longa_perm;
if (!empty($longa_perm_list)) {
    usort($longa_perm_list, function ($a, $b) {
        $da = diasDesdeData($a['data_intern_int'] ?? null) ?? 0;
        $db = diasDesdeData($b['data_intern_int'] ?? null) ?? 0;
        return $db <=> $da; // mais dias internado primeiro
    });
$longa_perm_list = array_slice($longa_perm_list, 0, 50);
} else {
    $longa_perm_list = [];
}

} catch (Throwable $e) {
    error_log('[DASH_TABELAS] ' . $e->getMessage());
    echo '<div id="dash-visitas-atraso-content"><div style="padding:10px">Erro ao carregar.</div></div>';
    echo '<div id="dash-longa-perm-content"><div style="padding:10px">Erro ao carregar.</div></div>';
    exit;
}
?>

<style>
    #dash-visitas-atraso-content .table,
    #dash-longa-perm-content .table {
        margin-top: 6px !important;
        font-size: 0.68rem !important;
        table-layout: fixed;
    }

    #dash-visitas-atraso-content .table thead th,
    #dash-longa-perm-content .table thead th {
        padding: 6px 8px !important;
        font-size: 0.52rem !important;
        letter-spacing: 0.04em;
        font-weight: 500 !important;
    }

    #dash-visitas-atraso-content .table tbody td,
    #dash-longa-perm-content .table tbody td {
        padding: 6px 8px !important;
        font-size: 0.68rem !important;
        font-weight: 400 !important;
        vertical-align: middle;
    }

    #dash-visitas-atraso-content .table tbody tr,
    #dash-longa-perm-content .table tbody tr,
    #dash-visitas-atraso-content .table tbody tr td,
    #dash-longa-perm-content .table tbody tr td {
        font-weight: 400 !important;
    }

    #dash-visitas-atraso-content .table tbody td a,
    #dash-longa-perm-content .table tbody td a,
    #dash-visitas-atraso-content .table tbody td span,
    #dash-longa-perm-content .table tbody td span {
        font-size: 0.68rem !important;
        font-weight: 400 !important;
    }

    #dash-visitas-atraso-content .table tbody td.fw-semibold,
    #dash-visitas-atraso-content .table tbody td.fw-bold,
    #dash-longa-perm-content .table tbody td.fw-semibold,
    #dash-longa-perm-content .table tbody td.fw-bold,
    #dash-visitas-atraso-content .table tbody td strong,
    #dash-longa-perm-content .table tbody td strong {
        font-weight: 400 !important;
    }

    #dash-visitas-atraso-content .sort-icons a,
    #dash-longa-perm-content .sort-icons a {
        font-size: 0.54rem !important;
    }

    #dash-visitas-atraso-content .table th:nth-child(1),
    #dash-longa-perm-content .table th:nth-child(1),
    #dash-visitas-atraso-content .table td:nth-child(1),
    #dash-longa-perm-content .table td:nth-child(1) {
        width: 5%;
    }

    #dash-visitas-atraso-content .table th:nth-child(2),
    #dash-longa-perm-content .table th:nth-child(2),
    #dash-visitas-atraso-content .table td:nth-child(2),
    #dash-longa-perm-content .table td:nth-child(2) {
        width: 15%;
    }

    #dash-visitas-atraso-content .table th:nth-child(3),
    #dash-longa-perm-content .table th:nth-child(3),
    #dash-visitas-atraso-content .table td:nth-child(3),
    #dash-longa-perm-content .table td:nth-child(3) {
        width: 11%;
    }

    #dash-visitas-atraso-content .table th:nth-child(4),
    #dash-visitas-atraso-content .table td:nth-child(4) {
        width: 32%;
    }

    #dash-longa-perm-content .table th:nth-child(4),
    #dash-longa-perm-content .table td:nth-child(4) {
        width: 24%;
    }

    #dash-visitas-atraso-content .table th:nth-child(5),
    #dash-longa-perm-content .table th:nth-child(5),
    #dash-visitas-atraso-content .table td:nth-child(5),
    #dash-longa-perm-content .table td:nth-child(5) {
        width: 13%;
    }

    #dash-visitas-atraso-content .table th:nth-child(6),
    #dash-visitas-atraso-content .table td:nth-child(6) {
        width: 16%;
    }

    #dash-longa-perm-content .table th:nth-child(6),
    #dash-longa-perm-content .table td:nth-child(6) {
        width: 13%;
    }

    #dash-longa-perm-content .table th:nth-child(7),
    #dash-longa-perm-content .table td:nth-child(7) {
        width: 16%;
    }

    #dash-visitas-atraso-content .table td:nth-child(4),
    #dash-longa-perm-content .table td:nth-child(4) {
        white-space: nowrap;
    }

    #dash-visitas-atraso-content .table td:nth-child(4) i,
    #dash-longa-perm-content .table td:nth-child(4) i {
        font-size: 0.82rem !important;
    }
</style>

<div id="dash-visitas-atraso-content">
    <div class="dash-table-scroll">
    <table class="table table-sm table-striped table-hover table-condensed dash-sortable">
        <thead style="background: linear-gradient(135deg, #7a3a80, #5a296a);">
            <tr>
                <th scope="col" style="width:5%" class="th-sortable" data-sort-type="number">Id Int
                    <span class="sort-icons">
                        <a href="#" data-dir="asc">▲</a>
                        <a href="#" data-dir="desc">▼</a>
                    </span>
                </th>
                <th scope="col" style="width:15%" class="th-sortable" data-sort-type="text">Hospital
                    <span class="sort-icons">
                        <a href="#" data-dir="asc">▲</a>
                        <a href="#" data-dir="desc">▼</a>
                    </span>
                </th>
                <th scope="col" style="width:11%" class="th-sortable" data-sort-type="text">Seguradora
                    <span class="sort-icons">
                        <a href="#" data-dir="asc">▲</a>
                        <a href="#" data-dir="desc">▼</a>
                    </span>
                </th>
                <th scope="col" style="width:32%" class="th-sortable" data-sort-type="text">Paciente
                    <span class="sort-icons">
                        <a href="#" data-dir="asc">▲</a>
                        <a href="#" data-dir="desc">▼</a>
                    </span>
                </th>
                <th scope="col" style="width:13%" class="th-sortable" data-sort-type="date">Ultima Visita
                    <span class="sort-icons">
                        <a href="#" data-dir="asc">▲</a>
                        <a href="#" data-dir="desc">▼</a>
                    </span>
                </th>
                <th scope="col" style="width:16%" class="th-sortable" data-sort-type="number">Dias última visita
                    <span class="sort-icons">
                        <a href="#" data-dir="asc">▲</a>
                        <a href="#" data-dir="desc">▼</a>
                    </span>
                </th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($dados_visitas_atraso_list as $intern): ?>
            <?php
                if (!empty($intern["data_visita_vis"])) {
                    $date = new DateTime($intern["data_visita_vis"]);
                    $formattedDate = $date->format('d/m/Y');
                } else {
                    $formattedDate = "Sem visita";
                }
                $diasUltimaVisita = diasDesdeData($intern["data_visita_vis"] ?? null);
                if ($diasUltimaVisita === null) {
                    $diasUltimaVisita = diasDesdeData($intern["data_visita_int"] ?? null);
                }
                $limiteDiasVisita = (int)($intern["dias_visita_seg"] ?? 0);
                if ($limiteDiasVisita <= 0) {
                    $limiteDiasVisita = 10;
                }
                $classeDiasVisita = '';
                if ($diasUltimaVisita !== null && $limiteDiasVisita > 0) {
                    if ($diasUltimaVisita >= $limiteDiasVisita) {
                        $classeDiasVisita = 'text-danger';
                    } elseif ($diasUltimaVisita === ($limiteDiasVisita - 1)) {
                        $classeDiasVisita = 'text-warning';
                    } else {
                        $classeDiasVisita = 'text-success';
                    }
                }
                ?>
            <tr>
                <td scope="row"><?= (int)($intern["id_internacao"] ?? 0) ?></td>
                <td scope="row">
                    <?= htmlspecialchars($intern["nome_hosp"] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </td>
                <td scope="row">
                    <?= htmlspecialchars($intern["seguradora_seg"] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                </td>
                <td scope="row">
                    <a
                        href="<?= $BASE_URL ?>cad_visita.php?id_internacao=<?= (int)($intern["id_internacao"] ?? 0) ?>">
                        <i class="bi bi-box-arrow-in-right"
                            style="margin-right:6px; font-size:1em;"></i>
                    </a>
                    <?= htmlspecialchars($intern["nome_pac"] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </td>
                <td scope="row"><?= $formattedDate ?></td>
                <td scope="row" class="<?= $classeDiasVisita ?>">
                    <?= $diasUltimaVisita !== null ? (int)$diasUltimaVisita . ' dias' : '—' ?>
                </td>
            </tr>
            <?php endforeach; ?>

            <?php if (count($dados_visitas_atraso_list) == 0): ?>
            <tr>
                <td colspan="6" scope="row" class="col-id" style='font-size:.8rem'>
                    Não foram encontrados registros
                </td>
            </tr>
            <?php endif ?>
        </tbody>
    </table>
    </div>
</div>

<div id="dash-longa-perm-content">
    <div class="dash-table-scroll">
    <table class="table table-sm table-striped table-hover table-condensed dash-sortable">
        <thead style="background: linear-gradient(135deg, #7a3a80, #5a296a);">
            <tr>
                <th scope="col" style="width:5%" class="th-sortable" data-sort-type="number">Id Int
                    <span class="sort-icons">
                        <a href="#" data-dir="asc">▲</a>
                        <a href="#" data-dir="desc">▼</a>
                    </span>
                </th>
                <th scope="col" style="width:15%" class="th-sortable" data-sort-type="text">Hospital
                    <span class="sort-icons">
                        <a href="#" data-dir="asc">▲</a>
                        <a href="#" data-dir="desc">▼</a>
                    </span>
                </th>
                <th scope="col" style="width:11%" class="th-sortable" data-sort-type="text">Seguradora
                    <span class="sort-icons">
                        <a href="#" data-dir="asc">▲</a>
                        <a href="#" data-dir="desc">▼</a>
                    </span>
                </th>
                <th scope="col" style="width:24%" class="th-sortable" data-sort-type="text">Paciente
                    <span class="sort-icons">
                        <a href="#" data-dir="asc">▲</a>
                        <a href="#" data-dir="desc">▼</a>
                    </span>
                </th>
                <th scope="col" style="width:16%" class="th-sortable" data-sort-type="date">Data Internação
                    <span class="sort-icons">
                        <a href="#" data-dir="asc">▲</a>
                        <a href="#" data-dir="desc">▼</a>
                    </span>
                </th>
                <th scope="col" style="width:13%" class="th-sortable" data-sort-type="date">Última visita
                    <span class="sort-icons">
                        <a href="#" data-dir="asc">▲</a>
                        <a href="#" data-dir="desc">▼</a>
                    </span>
                </th>
                <th scope="col" style="width:16%" class="th-sortable" data-sort-type="number">Dias Internacao
                    <span class="sort-icons">
                        <a href="#" data-dir="asc">▲</a>
                        <a href="#" data-dir="desc" class="active">▼</a>
                    </span>
                </th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($longa_perm_list as $intern): ?>
            <?php
                if (!empty($intern["data_intern_int"])) {
                    $date = new DateTime($intern["data_intern_int"]);
                    $formattedDate = $date->format('d/m/Y');
                } else {
                    $formattedDate = "Sem visita";
                }
                $diasUltimaVisita = null;
                $ultimaVisitaData = null;
                $idIntern = (int)($intern["id_internacao"] ?? 0);
                if ($idIntern > 0 && isset($ultimaVisitaPorInternacao[$idIntern])) {
                    $rawData = $ultimaVisitaPorInternacao[$idIntern]['data'] ?? null;
                    if (!empty($rawData)) {
                        try {
                            $ultimaVisitaData = (new DateTime($rawData))->format('d/m/Y');
                        } catch (Throwable $e) {
                            $ultimaVisitaData = null;
                        }
                        $diasUltimaVisita = diasDesdeData($rawData);
                    }
                }
                $diasInternacao = diasDesdeData($intern["data_intern_int"] ?? null);
                ?>
            <tr>
                <td scope="row"><?= (int)($intern["id_internacao"] ?? 0) ?></td>
                <td scope="row">
                    <?= htmlspecialchars($intern["nome_hosp"] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </td>
                <td scope="row">
                    <?= htmlspecialchars($intern["seguradora_seg"] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                </td>
                <td scope="row">
                    <a
                        href="<?= $BASE_URL ?>show_internacao.php?id_internacao=<?= (int)($intern["id_internacao"] ?? 0) ?>">
                        <i class="bi bi-box-arrow-right"
                            style="color:green; margin-right:6px; font-size:1em;"></i>
                    </a>
                    <?= htmlspecialchars($intern["nome_pac"] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </td>
                <td scope="row"><?= $formattedDate ?></td>
                <td scope="row"><?= $ultimaVisitaData ?? '—' ?></td>
                <td scope="row" class="text-danger">
                    <?= $diasInternacao !== null ? $diasInternacao . ' dias' : '—' ?>
                </td>
            </tr>
            <?php endforeach; ?>

            <?php if (count($longa_perm_list) == 0): ?>
            <tr>
                <td colspan="7" scope="row" class="col-id" style='font-size:.8rem'>
                    Não foram encontrados registros
                </td>
            </tr>
            <?php endif ?>
        </tbody>
    </table>
    </div>
</div>
