<?php
require_once("templates/header.php");
require_once("models/message.php");

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function fmt_date_br($raw)
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '-';
    }
    $dateOnly = substr($raw, 0, 10);
    if ($dt = DateTime::createFromFormat('Y-m-d', $dateOnly)) {
        return $dt->format('d/m/Y');
    }
    $ts = strtotime($raw);
    return $ts ? date('d/m/Y', $ts) : $raw;
}

function fmt_responsavel_label($raw)
{
    $value = trim((string)$raw);
    if ($value === '') {
        return '-';
    }

    $normalized = strtolower($value);
    if (str_starts_with($normalized, 'codex-import-') || str_contains($normalized, 'import-')) {
        return 'Importação automática';
    }

    if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
        $local = strstr($value, '@', true);
        if ($local !== false && $local !== '') {
            $value = $local;
        }
    }

    $value = preg_replace('/[._-]+/', ' ', $value);
    $value = preg_replace('/\s+/', ' ', (string)$value);
    $value = trim((string)$value);

    return $value !== '' ? mb_convert_case($value, MB_CASE_TITLE, 'UTF-8') : '-';
}

$dt_ini = filter_input(INPUT_GET, 'dt_ini', FILTER_SANITIZE_SPECIAL_CHARS);
$dt_fim = filter_input(INPUT_GET, 'dt_fim', FILTER_SANITIZE_SPECIAL_CHARS);
$seguradora_id = filter_input(INPUT_GET, 'seguradora_id', FILTER_VALIDATE_INT);
$responsavel = trim((string) filter_input(INPUT_GET, 'responsavel', FILTER_SANITIZE_SPECIAL_CHARS));
$visita_pag = (int)(filter_input(INPUT_GET, 'v_pag') ?: 1);
$conta_pag = (int)(filter_input(INPUT_GET, 'c_pag') ?: 1);
$limite_visitas = 10;
$limite_contas = 10;

$seguradoras = [];
try {
    $seguradoras = $conn->query("SELECT DISTINCT seguradora_seg, id_seguradora FROM tb_seguradora WHERE deletado_seg <> 's' OR deletado_seg IS NULL ORDER BY seguradora_seg")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $th) {
    $seguradoras = [];
}

$visitasPendentes = [];
$contasPendentes = [];
$visitasErro = null;
$contasErro = null;

try {
    $visitaWhere = ["ac.internado_int = 's'"];
    $visitaParams = [];

    if ($dt_ini) {
        $visitaWhere[] = "DATE(ac.data_intern_int) >= :v_dt_ini";
        $visitaParams[':v_dt_ini'] = $dt_ini;
    }
    if ($dt_fim) {
        $visitaWhere[] = "DATE(ac.data_intern_int) <= :v_dt_fim";
        $visitaParams[':v_dt_fim'] = $dt_fim;
    }
    if ($seguradora_id) {
        $visitaWhere[] = "pa.fk_seguradora_pac = :v_seguradora_id";
        $visitaParams[':v_seguradora_id'] = $seguradora_id;
    }
    if ($responsavel !== '') {
        $visitaWhere[] = "ac.usuario_create_int LIKE :v_resp";
        $visitaParams[':v_resp'] = "%" . $responsavel . "%";
    }

    $visitaCountSql = "
        SELECT COUNT(*) FROM (
            SELECT ac.id_internacao
            FROM tb_internacao ac
            LEFT JOIN tb_paciente pa ON pa.id_paciente = ac.fk_paciente_int
            LEFT JOIN tb_hospital ho ON ho.id_hospital = ac.fk_hospital_int
            LEFT JOIN tb_seguradora se ON se.id_seguradora = pa.fk_seguradora_pac
            LEFT JOIN tb_visita vi
                ON vi.fk_internacao_vis = ac.id_internacao
                AND (vi.retificado IS NULL OR vi.retificado IN (0, '0', '', 'n', 'N'))
            WHERE " . implode(" AND ", $visitaWhere) . "
            GROUP BY ac.id_internacao
            HAVING CURDATE() > DATE_ADD(
                COALESCE(MAX(COALESCE(DATE(vi.data_visita_vis), DATE(vi.data_lancamento_vis))), MIN(ac.data_intern_int)),
                INTERVAL CASE
                    WHEN (MIN(ac.internado_uti_int) = 's' OR MIN(ac.internacao_uti_int) = 's')
                        THEN COALESCE(NULLIF(MIN(se.dias_visita_uti_seg), 0), NULLIF(MIN(se.dias_visita_seg), 0), 7)
                    ELSE COALESCE(NULLIF(MIN(se.dias_visita_seg), 0), 7)
                END DAY
            )
        ) x
    ";
    $stmt = $conn->prepare($visitaCountSql);
    foreach ($visitaParams as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $visita_total = (int)($stmt->fetchColumn() ?: 0);

    $visita_pag = max(1, $visita_pag);
    $visita_total_pag = max(1, (int)ceil($visita_total / $limite_visitas));
    $visita_pag = min($visita_pag, $visita_total_pag);
    $visita_offset = ($visita_pag - 1) * $limite_visitas;

    $visitaSql = "
        SELECT
            ac.id_internacao,
            ac.data_intern_int,
            ac.usuario_create_int,
            pa.nome_pac,
            ho.nome_hosp,
            se.seguradora_seg
        FROM tb_internacao ac
        LEFT JOIN tb_paciente pa ON pa.id_paciente = ac.fk_paciente_int
        LEFT JOIN tb_hospital ho ON ho.id_hospital = ac.fk_hospital_int
        LEFT JOIN tb_seguradora se ON se.id_seguradora = pa.fk_seguradora_pac
        LEFT JOIN tb_visita vi
            ON vi.fk_internacao_vis = ac.id_internacao
            AND (vi.retificado IS NULL OR vi.retificado IN (0, '0', '', 'n', 'N'))
        WHERE " . implode(" AND ", $visitaWhere) . "
        GROUP BY ac.id_internacao
        HAVING CURDATE() > DATE_ADD(
            COALESCE(MAX(COALESCE(DATE(vi.data_visita_vis), DATE(vi.data_lancamento_vis))), MIN(ac.data_intern_int)),
            INTERVAL CASE
                WHEN (MIN(ac.internado_uti_int) = 's' OR MIN(ac.internacao_uti_int) = 's')
                    THEN COALESCE(NULLIF(MIN(se.dias_visita_uti_seg), 0), NULLIF(MIN(se.dias_visita_seg), 0), 7)
                ELSE COALESCE(NULLIF(MIN(se.dias_visita_seg), 0), 7)
            END DAY
        )
        ORDER BY ac.data_intern_int DESC
        LIMIT :v_limit OFFSET :v_offset
    ";

    $stmt = $conn->prepare($visitaSql);
    foreach ($visitaParams as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':v_limit', $limite_visitas, PDO::PARAM_INT);
    $stmt->bindValue(':v_offset', $visita_offset, PDO::PARAM_INT);
    $stmt->execute();
    $visitasPendentes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $th) {
    $visitasErro = $th->getMessage();
}

try {
    $contaWhere = [
        "(ca.conta_faturada_cap IS NULL OR ca.conta_faturada_cap IN ('', 'n', 'N', '0'))"
    ];
    $contaParams = [];

    if ($dt_ini) {
        $contaWhere[] = "DATE(COALESCE(ca.data_create_cap, ac.data_intern_int)) >= :c_dt_ini";
        $contaParams[':c_dt_ini'] = $dt_ini;
    }
    if ($dt_fim) {
        $contaWhere[] = "DATE(COALESCE(ca.data_create_cap, ac.data_intern_int)) <= :c_dt_fim";
        $contaParams[':c_dt_fim'] = $dt_fim;
    }
    if ($seguradora_id) {
        $contaWhere[] = "pa.fk_seguradora_pac = :c_seguradora_id";
        $contaParams[':c_seguradora_id'] = $seguradora_id;
    }
    if ($responsavel !== '') {
        $contaWhere[] = "ca.usuario_create_cap LIKE :c_resp";
        $contaParams[':c_resp'] = "%" . $responsavel . "%";
    }

    $contaCountSql = "
        SELECT COUNT(*) AS total
        FROM tb_capeante ca
        LEFT JOIN tb_internacao ac ON ac.id_internacao = ca.fk_int_capeante
        LEFT JOIN tb_paciente pa ON pa.id_paciente = ac.fk_paciente_int
        LEFT JOIN tb_hospital ho ON ho.id_hospital = ac.fk_hospital_int
        LEFT JOIN tb_seguradora se ON se.id_seguradora = pa.fk_seguradora_pac
        WHERE " . implode(" AND ", $contaWhere) . "
    ";
    $stmt = $conn->prepare($contaCountSql);
    foreach ($contaParams as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $conta_total = (int)($stmt->fetchColumn() ?: 0);

    $conta_pag = max(1, $conta_pag);
    $conta_total_pag = max(1, (int)ceil($conta_total / $limite_contas));
    $conta_pag = min($conta_pag, $conta_total_pag);
    $conta_offset = ($conta_pag - 1) * $limite_contas;

    $contaSql = "
        SELECT
            ca.id_capeante,
            ca.lote_cap,
            ca.data_create_cap,
            ca.usuario_create_cap,
            ac.id_internacao,
            pa.nome_pac,
            ho.nome_hosp,
            se.seguradora_seg
        FROM tb_capeante ca
        LEFT JOIN tb_internacao ac ON ac.id_internacao = ca.fk_int_capeante
        LEFT JOIN tb_paciente pa ON pa.id_paciente = ac.fk_paciente_int
        LEFT JOIN tb_hospital ho ON ho.id_hospital = ac.fk_hospital_int
        LEFT JOIN tb_seguradora se ON se.id_seguradora = pa.fk_seguradora_pac
        WHERE " . implode(" AND ", $contaWhere) . "
        ORDER BY ca.data_create_cap DESC
        LIMIT :c_limit OFFSET :c_offset
    ";

    $stmt = $conn->prepare($contaSql);
    foreach ($contaParams as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':c_limit', $limite_contas, PDO::PARAM_INT);
    $stmt->bindValue(':c_offset', $conta_offset, PDO::PARAM_INT);
    $stmt->execute();
    $contasPendentes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $th) {
    $contasErro = $th->getMessage();
}
?>

    <div class="container-fluid" id="main-container" style="margin-top:-5px">
        <style>
            .fila-hero {
                background: linear-gradient(135deg, #5e2363, #9b70d1);
                color: #fff;
                border-radius: 28px;
                padding: 18px 24px;
                box-shadow: 0 20px 40px rgba(24, 0, 30, 0.25);
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                margin-bottom: 14px;
            }
            .fila-hero h1 {
                margin: 0;
                font-size: 1.4rem;
                letter-spacing: .02em;
                color: #fff;
            }
            .fila-hero__tag {
                background: rgba(255, 255, 255, 0.2);
                padding: 6px 14px;
                border-radius: 999px;
                font-weight: 600;
                font-size: .78rem;
                color: #fff;
                white-space: nowrap;
            }
            .fila-subtitle {
                color: #6c757d;
                margin: 4px 0 0;
            }

            .fila-filter-row {
                flex-wrap: nowrap !important;
                align-items: flex-end;
            }

            .fila-filter-row > [class*="col-"] {
                max-width: none;
                min-width: 0;
            }

            .fila-filter-date {
                flex: 0 0 170px;
                max-width: 170px;
            }

            .fila-filter-convenio {
                flex: 1.2 1 0;
            }

            .fila-filter-responsavel {
                flex: 0.92 1 0;
            }

            .fila-filter-actions {
                flex: 0 0 168px;
                max-width: 168px;
                display: flex;
                align-items: stretch;
                gap: 8px;
                white-space: nowrap;
            }

            .fila-filter-actions .btn {
                flex: 1 1 0;
                min-height: 32px;
                height: 32px;
                font-size: .72rem;
            }
        </style>
        <div class="fila-hero">
            <h1>Fila de Tarefas</h1>
            <span class="fila-hero__tag">Pendências</span>
        </div>
        <p class="fila-subtitle">Visitas e contas pendentes, com filtros por periodo, convenio e responsavel.</p>

    <form method="GET" class="row g-2 align-items-end mb-3 fila-filter-row">
        <div class="col-sm-2 fila-filter-date">
            <label class="form-label mb-1">Data inicio</label>
            <input type="date" class="form-control form-control-sm" name="dt_ini" value="<?= h($dt_ini) ?>">
        </div>
        <div class="col-sm-2 fila-filter-date">
            <label class="form-label mb-1">Data fim</label>
            <input type="date" class="form-control form-control-sm" name="dt_fim" value="<?= h($dt_fim) ?>">
        </div>
        <div class="col-sm-4 fila-filter-convenio">
            <label class="form-label mb-1">Convenio</label>
            <select class="form-select form-select-sm" name="seguradora_id">
                <option value="">Todos</option>
                <?php foreach ($seguradoras as $seg): ?>
                <option value="<?= (int) $seg['id_seguradora'] ?>" <?= $seguradora_id == $seg['id_seguradora'] ? 'selected' : '' ?>>
                    <?= h($seg['seguradora_seg']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-3 fila-filter-responsavel">
            <label class="form-label mb-1">Responsavel</label>
            <input type="text" class="form-control form-control-sm" name="responsavel" placeholder="Nome ou email"
                value="<?= h($responsavel) ?>">
        </div>
        <div class="col-sm-1 fila-filter-actions">
            <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
            <a class="btn btn-outline-secondary btn-sm btn-filtro-limpar" href="list_fila_tarefas.php">Limpar</a>
        </div>
    </form>

    <style>
        .fila-table thead th {
            color: #3a184f;
            background: #f1edf6;
        }
        .internacao-card__header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin: 0;
            padding: 14px 16px;
            border-bottom: 1px solid #d9cfe6;
            background: #e1d8ee;
        }
        .internacao-card__eyebrow {
            text-transform: uppercase;
            letter-spacing: .35em;
            font-size: .65rem;
            margin: 0;
            color: #2b0f3f;
        }
        .internacao-card__title {
            margin: 2px 0 0;
            font-size: 1.1rem;
            color: #2b0f3f;
            font-weight: 600;
        }
        .internacao-card__tag {
            background: #f8eefc;
            color: #5e2363;
            padding: 6px 16px;
            border-radius: 999px;
            font-weight: 600;
            font-size: .8rem;
        }
    </style>

    <div class="card mb-4">
        <div class="internacao-card__header">
            <div>
                <p class="internacao-card__eyebrow">Fila</p>
                <h3 class="internacao-card__title">Visitas pendentes</h3>
            </div>
            <span class="internacao-card__tag"><?= (int)($visita_total ?? count($visitasPendentes)) ?></span>
        </div>
        <div class="card-body table-responsive">
            <?php if ($visitasErro): ?>
            <div class="alert alert-warning">Falha ao carregar visitas pendentes. <?= h($visitasErro) ?></div>
            <?php endif; ?>
            <table class="table table-striped table-sm align-middle fila-table">
                <thead class="table-light">
                    <tr>
                        <th>Internacao</th>
                        <th>Paciente</th>
                        <th>Hospital</th>
                        <th>Convenio</th>
                        <th>Data internacao</th>
                        <th>Responsavel</th>
                        <th class="text-end">Acao</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$visitasPendentes): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted">Nenhuma pendencia encontrada.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($visitasPendentes as $row): ?>
                    <tr>
                        <td><?= h($row['id_internacao']) ?></td>
                        <td><?= h($row['nome_pac']) ?></td>
                        <td><?= h($row['nome_hosp']) ?></td>
                        <td><?= h($row['seguradora_seg'] ?? '-') ?></td>
                        <td><?= h(fmt_date_br($row['data_intern_int'] ?? '')) ?></td>
                        <td><?= h(fmt_responsavel_label($row['usuario_create_int'] ?? '')) ?></td>
                        <td class="text-end">
                            <a class="btn btn-outline-primary btn-sm"
                                href="<?= h(rtrim($BASE_URL, '/') . '/internacoes/visualizar/' . (int)$row['id_internacao']) ?>">Abrir</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if (($visita_total_pag ?? 1) > 1): ?>
            <nav class="d-flex justify-content-center mt-3">
                <ul class="pagination pagination-sm mb-0">
                    <?php
                    $baseQuery = [
                        'dt_ini' => $dt_ini,
                        'dt_fim' => $dt_fim,
                        'seguradora_id' => $seguradora_id,
                        'responsavel' => $responsavel,
                        'c_pag' => $conta_pag,
                    ];
                    $v_window = 5;
                    $v_start = max(1, $visita_pag - $v_window);
                    $v_end = min($visita_total_pag, $visita_pag + $v_window);
                    if ($v_end - $v_start < 2 * $v_window) {
                        if ($v_start == 1) {
                            $v_end = min($visita_total_pag, $v_start + 2 * $v_window);
                        } elseif ($v_end == $visita_total_pag) {
                            $v_start = max(1, $v_end - 2 * $v_window);
                        }
                    }
                    ?>
                    <?php if ($visita_pag > 1): ?>
                    <li class="page-item"><a class="page-link" href="list_fila_tarefas.php?<?= h(http_build_query($baseQuery + ['v_pag' => 1])) ?>">&laquo;</a></li>
                    <li class="page-item"><a class="page-link" href="list_fila_tarefas.php?<?= h(http_build_query($baseQuery + ['v_pag' => $visita_pag - 1])) ?>">&lsaquo;</a></li>
                    <?php endif; ?>
                    <?php for ($i = $v_start; $i <= $v_end; $i++): ?>
                    <li class="page-item <?= $i === $visita_pag ? 'active' : '' ?>">
                        <a class="page-link" href="list_fila_tarefas.php?<?= h(http_build_query($baseQuery + ['v_pag' => $i])) ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    <?php if ($visita_pag < $visita_total_pag): ?>
                    <li class="page-item"><a class="page-link" href="list_fila_tarefas.php?<?= h(http_build_query($baseQuery + ['v_pag' => $visita_pag + 1])) ?>">&rsaquo;</a></li>
                    <li class="page-item"><a class="page-link" href="list_fila_tarefas.php?<?= h(http_build_query($baseQuery + ['v_pag' => $visita_total_pag])) ?>">&raquo;</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="internacao-card__header">
            <div>
                <p class="internacao-card__eyebrow">Fila</p>
                <h3 class="internacao-card__title">Contas Pendentes</h3>
            </div>
            <span class="internacao-card__tag"><?= (int)($conta_total ?? count($contasPendentes)) ?></span>
        </div>
        <div class="card-body table-responsive">
            <?php if ($contasErro): ?>
            <div class="alert alert-warning">Falha ao carregar contas pendentes. <?= h($contasErro) ?></div>
            <?php endif; ?>
            <table class="table table-striped table-sm align-middle fila-table">
                <thead class="table-light">
                    <tr>
                        <th>Conta</th>
                        <th>Internacao</th>
                        <th>Paciente</th>
                        <th>Hospital</th>
                        <th>Convenio</th>
                        <th>Data criacao</th>
                        <th>Responsavel</th>
                        <th class="text-end">Acao</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$contasPendentes): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted">Nenhuma pendencia encontrada.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($contasPendentes as $row): ?>
                    <tr>
                        <td><?= h($row['id_capeante']) ?></td>
                        <td><?= h($row['id_internacao'] ?? '-') ?></td>
                        <td><?= h($row['nome_pac']) ?></td>
                        <td><?= h($row['nome_hosp']) ?></td>
                        <td><?= h($row['seguradora_seg'] ?? '-') ?></td>
                        <td><?= h(fmt_date_br($row['data_create_cap'] ?? '')) ?></td>
                        <td><?= h(fmt_responsavel_label($row['usuario_create_cap'] ?? '')) ?></td>
                        <td class="text-end">
                            <?php
                            $capeanteId = (int)($row['id_capeante'] ?? 0);
                            $internacaoId = (int)($row['id_internacao'] ?? 0);
                            $capeanteLink = $capeanteId
                                ? $BASE_URL . "cad_capeante_rah.php?id_capeante=" . $capeanteId
                                : $BASE_URL . "cad_capeante_rah.php?id_internacao=" . $internacaoId . "&type=create";
                            ?>
                            <a class="btn btn-outline-primary btn-sm" href="<?= h($capeanteLink) ?>">Abrir</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if (($conta_total_pag ?? 1) > 1): ?>
            <nav class="d-flex justify-content-center mt-3">
                <ul class="pagination pagination-sm mb-0">
                    <?php
                    $baseQuery = [
                        'dt_ini' => $dt_ini,
                        'dt_fim' => $dt_fim,
                        'seguradora_id' => $seguradora_id,
                        'responsavel' => $responsavel,
                        'v_pag' => $visita_pag,
                    ];
                    $c_window = 5;
                    $c_start = max(1, $conta_pag - $c_window);
                    $c_end = min($conta_total_pag, $conta_pag + $c_window);
                    if ($c_end - $c_start < 2 * $c_window) {
                        if ($c_start == 1) {
                            $c_end = min($conta_total_pag, $c_start + 2 * $c_window);
                        } elseif ($c_end == $conta_total_pag) {
                            $c_start = max(1, $c_end - 2 * $c_window);
                        }
                    }
                    ?>
                    <?php if ($conta_pag > 1): ?>
                    <li class="page-item"><a class="page-link" href="list_fila_tarefas.php?<?= h(http_build_query($baseQuery + ['c_pag' => 1])) ?>">&laquo;</a></li>
                    <li class="page-item"><a class="page-link" href="list_fila_tarefas.php?<?= h(http_build_query($baseQuery + ['c_pag' => $conta_pag - 1])) ?>">&lsaquo;</a></li>
                    <?php endif; ?>
                    <?php for ($i = $c_start; $i <= $c_end; $i++): ?>
                    <li class="page-item <?= $i === $conta_pag ? 'active' : '' ?>">
                        <a class="page-link" href="list_fila_tarefas.php?<?= h(http_build_query($baseQuery + ['c_pag' => $i])) ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    <?php if ($conta_pag < $conta_total_pag): ?>
                    <li class="page-item"><a class="page-link" href="list_fila_tarefas.php?<?= h(http_build_query($baseQuery + ['c_pag' => $conta_pag + 1])) ?>">&rsaquo;</a></li>
                    <li class="page-item"><a class="page-link" href="list_fila_tarefas.php?<?= h(http_build_query($baseQuery + ['c_pag' => $conta_total_pag])) ?>">&raquo;</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>
