<?php
include_once("check_logado.php");
include_once("globals.php");
require_once("app/services/OperationalIntelligenceService.php");

if (!isset($conn) || !($conn instanceof PDO)) {
    die("Conexão inválida.");
}

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$hoje = date('Y-m-d');
$dataIni = filter_input(INPUT_GET, 'data_ini') ?: date('Y-m-d', strtotime('-90 days'));
$dataFim = filter_input(INPUT_GET, 'data_fim') ?: $hoje;
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$seguradoraId = filter_input(INPUT_GET, 'seguradora_id', FILTER_VALIDATE_INT) ?: null;

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
        error_log('[INTEL_OPERADORA][SEGURADORA] ' . $e->getMessage());
    }
}

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);
$seguradoras = $conn->query("SELECT id_seguradora, seguradora_seg FROM tb_seguradora ORDER BY seguradora_seg")
    ->fetchAll(PDO::FETCH_ASSOC);
if ($isSeguradoraRole) {
    $seguradoraId = $seguradoraUserId > 0 ? $seguradoraUserId : -1;
    $seguradoras = array_values(array_filter($seguradoras, static function ($s) use ($seguradoraUserId) {
        return (int)($s['id_seguradora'] ?? 0) === (int)$seguradoraUserId;
    }));
}
$seguradoraEscopoNome = (string)($seguradoras[0]['seguradora_seg'] ?? '');

$where = "i.data_intern_int BETWEEN :data_ini AND :data_fim";
$params = [
    ':data_ini' => $dataIni,
    ':data_fim' => $dataFim,
];
if ($hospitalId) {
    $where .= " AND i.fk_hospital_int = :hospital_id";
    $params[':hospital_id'] = $hospitalId;
}
if ($seguradoraId) {
    $where .= " AND pa.fk_seguradora_pac = :seguradora_id";
    $params[':seguradora_id'] = $seguradoraId;
}

$sqlPermanencia = "
    SELECT
        i.id_internacao,
        i.data_intern_int,
        h.nome_hosp,
        pa.nome_pac,
        s.seguradora_seg,
        p.patologia_pat,
        GREATEST(DATEDIFF(CURDATE(), i.data_intern_int) + 1, 1) AS dias_atual,
        COALESCE(NULLIF(s.longa_permanencia_seg, 0), NULLIF(p.dias_pato, 0)) AS prazo_esperado,
        (
            SELECT COUNT(*)
            FROM tb_prorrogacao pr
            WHERE pr.fk_internacao_pror = i.id_internacao
        ) AS total_prorrog,
        (
            SELECT MAX(CASE WHEN g.evento_prorrogar_ges = 's' THEN 1 ELSE 0 END)
            FROM tb_gestao g
            WHERE g.fk_internacao_ges = i.id_internacao
        ) AS seguir_prorrog
    FROM tb_internacao i
    LEFT JOIN tb_alta al ON al.fk_id_int_alt = i.id_internacao
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_seguradora s ON s.id_seguradora = pa.fk_seguradora_pac
    LEFT JOIN tb_patologia p ON p.id_patologia = i.fk_patologia_int
    WHERE (i.internado_int = 's' OR i.internado_int IS NULL)
      AND (al.data_alta_alt IS NULL OR al.data_alta_alt = '0000-00-00')
      AND {$where}
    ORDER BY dias_atual DESC
    LIMIT 80
";
$stmt = $conn->prepare($sqlPermanencia);
$stmt->execute($params);
$rowsPermanencia = $stmt->fetchAll(PDO::FETCH_ASSOC);

$alertas = [];
$altaProvavel = [];
$graceDays = 2;
foreach ($rowsPermanencia as $row) {
    $dias = (int)($row['dias_atual'] ?? 0);
    $prazo = (int)($row['prazo_esperado'] ?? 0);
    if ($prazo <= 0) continue;
    $row['prazo_esperado'] = $prazo;
    $row['delta'] = $dias - $prazo;
    $row['status'] = $dias > $prazo ? 'excesso' : ($dias >= max(1, $prazo - $graceDays) ? 'alerta' : 'no_prazo');
    if ($row['status'] !== 'no_prazo') $alertas[] = $row;

    $seguirProrrog = (int)($row['seguir_prorrog'] ?? 0);
    $totalProrrog = (int)($row['total_prorrog'] ?? 0);
    if ($dias >= $prazo && $seguirProrrog === 0 && $totalProrrog === 0) {
        $altaProvavel[] = $row;
    }
}

usort($alertas, fn($a, $b) => ($b['delta'] ?? 0) <=> ($a['delta'] ?? 0));
usort($altaProvavel, fn($a, $b) => ($b['delta'] ?? 0) <=> ($a['delta'] ?? 0));
$alertas = array_slice($alertas, 0, 30);
$altaProvavel = array_slice($altaProvavel, 0, 30);

$sqlOpme = "
    SELECT
        i.id_internacao,
        pa.nome_pac,
        h.nome_hosp,
        s.seguradora_seg,
        g.rel_opme_ges
    FROM tb_gestao g
    JOIN tb_internacao i ON i.id_internacao = g.fk_internacao_ges
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    LEFT JOIN tb_seguradora s ON s.id_seguradora = pa.fk_seguradora_pac
    WHERE g.opme_ges = 's'
      AND {$where}
    ORDER BY i.data_intern_int DESC
    LIMIT 25
";
$stmt = $conn->prepare($sqlOpme);
$stmt->execute($params);
$opmeRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sqlUti = "
    SELECT
        i.id_internacao,
        pa.nome_pac,
        h.nome_hosp,
        s.seguradora_seg,
        u.just_uti
    FROM tb_uti u
    JOIN tb_internacao i ON i.id_internacao = u.fk_internacao_uti
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    LEFT JOIN tb_seguradora s ON s.id_seguradora = pa.fk_seguradora_pac
    WHERE u.just_uti = 'Não pertinente'
      AND {$where}
    ORDER BY i.data_intern_int DESC
    LIMIT 25
";
$stmt = $conn->prepare($sqlUti);
$stmt->execute($params);
$utiRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sqlDuplic = "
    SELECT
        i.id_internacao,
        pa.nome_pac,
        h.nome_hosp,
        s.seguradora_seg,
        tu.tuss_solicitado,
        COUNT(*) AS duplicados
    FROM tb_tuss tu
    JOIN tb_internacao i ON i.id_internacao = tu.fk_int_tuss
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    LEFT JOIN tb_seguradora s ON s.id_seguradora = pa.fk_seguradora_pac
    WHERE tu.tuss_solicitado IS NOT NULL
      AND tu.tuss_solicitado <> ''
      AND {$where}
    GROUP BY i.id_internacao, pa.nome_pac, h.nome_hosp, s.seguradora_seg, tu.tuss_solicitado
    HAVING COUNT(*) > 1
    ORDER BY duplicados DESC
    LIMIT 25
";
$stmt = $conn->prepare($sqlDuplic);
$stmt->execute($params);
$duplicRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sqlFora = "
    SELECT
        i.id_internacao,
        pa.nome_pac,
        h.nome_hosp,
        s.seguradora_seg,
        tu.tuss_solicitado,
        tu.tuss_liberado_sn,
        tu.qtd_tuss_solicitado,
        tu.qtd_tuss_liberado
    FROM tb_tuss tu
    JOIN tb_internacao i ON i.id_internacao = tu.fk_int_tuss
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    LEFT JOIN tb_seguradora s ON s.id_seguradora = pa.fk_seguradora_pac
    WHERE (
        tu.tuss_liberado_sn IS NULL OR tu.tuss_liberado_sn = '' OR tu.tuss_liberado_sn = 'n'
        OR (
            tu.qtd_tuss_solicitado IS NOT NULL
            AND tu.qtd_tuss_liberado IS NOT NULL
            AND tu.qtd_tuss_liberado < tu.qtd_tuss_solicitado
        )
    )
      AND {$where}
    ORDER BY i.data_intern_int DESC
    LIMIT 30
";
$stmt = $conn->prepare($sqlFora);
$stmt->execute($params);
$foraRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$service = new OperationalIntelligenceService($conn);
$glosaData = $service->glosaRiskAlerts(50);
if (!empty($glosaData['available'])) {
    $entries = $glosaData['entries'] ?? [];
    if ($hospitalId) {
        $entries = array_filter($entries, fn($e) => (int)($e['hospital_id'] ?? 0) === (int)$hospitalId);
    }
    if ($seguradoraId) {
        $entries = array_filter($entries, fn($e) => (int)($e['seguradora_id'] ?? 0) === (int)$seguradoraId);
    }
    $glosaData['entries'] = array_slice(array_values($entries), 0, 25);
}

include_once("templates/header.php");
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Inteligência da Operadora</title>
    <style>
        .report-wrapper {
            width: 100%;
            max-width: none;
            margin: 8px 0 44px;
            padding: 0 24px;
        }
        .report-header {
            background: linear-gradient(120deg, #fef6ff, #f3e6f9);
            border-radius: 18px;
            padding: 18px 22px;
            border: 1px solid rgba(94, 35, 99, .12);
            margin-bottom: 16px;
        }
        .report-header h1 {
            margin: 0 0 4px;
            font-weight: 700;
            color: #4b2054;
            font-size: 1.06rem;
        }
        .report-card {
            background: #fff;
            border-radius: 16px;
            padding: 14px 18px;
            border: 1px solid rgba(94, 35, 99, .08);
            box-shadow: 0 10px 24px rgba(45, 18, 70, .08);
            margin-bottom: 14px;
        }
        .report-wrapper .text-muted,
        .report-wrapper .form-label,
        .report-wrapper .form-control,
        .report-wrapper .form-select,
        .report-wrapper .table,
        .report-wrapper small {
            font-size: .78rem;
        }
        .report-wrapper .row.g-3 {
            --bs-gutter-y: .6rem;
            --bs-gutter-x: .8rem;
        }
        .table thead th {
            background: #f8f3fb;
            color: #4b2054;
            font-size: .7rem;
            padding-top: .65rem;
            padding-bottom: .65rem;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 0.2rem 0.7rem;
            border-radius: 999px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        .status-excesso { background: #fee2e2; color: #991b1b; }
        .status-alerta { background: #fff1c3; color: #a15c00; }
        .status-ok { background: #dcfce7; color: #166534; }
        .risk-pill.alto {background:#fee2e2;color:#991b1b;}
        .risk-pill.moderado {background:#fff1c3;color:#a15c00;}
        .risk-pill.baixo {background:#dcfce7;color:#166534;}
        .scope-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: .72rem;
            font-weight: 700;
            background: #f3edff;
            border: 1px solid #d6c5f7;
            color: #5e2363;
        }
    </style>
</head>
<body>
    <div class="report-wrapper">
        <div class="report-header">
            <h1>Inteligência da Operadora</h1>
            <div class="text-muted">Foco em redução de custo assistencial e priorização de ações de auditoria.</div>
            <?php if ($isSeguradoraRole): ?>
                <div class="scope-badge">
                    Escopo: Seguradora <?= e($seguradoraEscopoNome !== '' ? $seguradoraEscopoNome : ('#' . $seguradoraUserId)) ?>
                </div>
            <?php endif; ?>
        </div>

        <form class="report-card" method="get">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Data inicial</label>
                    <input type="date" class="form-control" name="data_ini" value="<?= e($dataIni) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Data final</label>
                    <input type="date" class="form-control" name="data_fim" value="<?= e($dataFim) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Hospital</label>
                    <select class="form-select" name="hospital_id">
                        <option value="">Todos</option>
                        <?php foreach ($hospitais as $h): ?>
                            <option value="<?= (int)$h['id_hospital'] ?>" <?= $hospitalId == $h['id_hospital'] ? 'selected' : '' ?>>
                                <?= e($h['nome_hosp']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Operadora</label>
                    <?php if ($isSeguradoraRole): ?>
                        <input type="hidden" name="seguradora_id" value="<?= (int)$seguradoraId ?>">
                        <input type="text" class="form-control" readonly value="<?= e($seguradoras[0]['seguradora_seg'] ?? 'Minha operadora') ?>">
                    <?php else: ?>
                        <select class="form-select" name="seguradora_id">
                            <option value="">Todas</option>
                            <?php foreach ($seguradoras as $s): ?>
                                <option value="<?= (int)$s['id_seguradora'] ?>" <?= $seguradoraId == $s['id_seguradora'] ? 'selected' : '' ?>>
                                    <?= e($s['seguradora_seg']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit">Aplicar filtros</button>
                    <a class="btn btn-outline-secondary btn-filtro-limpar" href="<?= htmlspecialchars($BASE_URL . 'inteligencia_operadora.php', ENT_QUOTES, 'UTF-8') ?>">Limpar filtros</a>
                </div>
            </div>
        </form>

        <div class="report-card">
            <h5 class="mb-2">Detecção precoce de permanência excessiva</h5>
            <div class="text-muted small mb-3">Baseado na média da patologia e nos prazos definidos pela operadora.</div>
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Paciente</th>
                            <th>Hospital</th>
                            <th>Operadora</th>
                            <th>Patologia</th>
                            <th class="text-center">Dias</th>
                            <th class="text-center">Prazo</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$alertas): ?>
                            <tr><td colspan="7" class="text-muted">Sem registros para os filtros aplicados.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($alertas as $row): ?>
                            <?php
                            $statusClass = $row['status'] === 'excesso' ? 'status-excesso' : 'status-alerta';
                            $statusLabel = $row['status'] === 'excesso' ? 'Excesso' : 'Alerta';
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= e($row['nome_pac']) ?></div>
                                    <small class="text-muted">Int. #<?= (int)$row['id_internacao'] ?> · <?= date('d/m/Y', strtotime($row['data_intern_int'])) ?></small>
                                </td>
                                <td><?= e($row['nome_hosp']) ?></td>
                                <td><?= e($row['seguradora_seg']) ?></td>
                                <td><?= e($row['patologia_pat']) ?></td>
                                <td class="text-center fw-semibold"><?= (int)$row['dias_atual'] ?>d</td>
                                <td class="text-center"><?= (int)$row['prazo_esperado'] ?>d</td>
                                <td class="text-center">
                                    <span class="status-pill <?= $statusClass ?>"><?= $statusLabel ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="report-card">
            <h5 class="mb-2">Indicação de alta provável</h5>
            <div class="text-muted small mb-3">Casos no prazo esperado, sem indicação de prorrogação.</div>
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Paciente</th>
                            <th>Hospital</th>
                            <th>Operadora</th>
                            <th>Patologia</th>
                            <th class="text-center">Dias</th>
                            <th class="text-center">Prazo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$altaProvavel): ?>
                            <tr><td colspan="6" class="text-muted">Sem registros para os filtros aplicados.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($altaProvavel as $row): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= e($row['nome_pac']) ?></div>
                                    <small class="text-muted">Int. #<?= (int)$row['id_internacao'] ?> · <?= date('d/m/Y', strtotime($row['data_intern_int'])) ?></small>
                                </td>
                                <td><?= e($row['nome_hosp']) ?></td>
                                <td><?= e($row['seguradora_seg']) ?></td>
                                <td><?= e($row['patologia_pat']) ?></td>
                                <td class="text-center fw-semibold"><?= (int)$row['dias_atual'] ?>d</td>
                                <td class="text-center"><?= (int)$row['prazo_esperado'] ?>d</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="report-card">
            <h5 class="mb-2">Oportunidade de glosa</h5>
            <div class="text-muted small mb-3">Priorização por impacto e probabilidade de ajuste.</div>
            <?php if (empty($glosaData['available'])): ?>
                <div class="text-muted"><?= e($glosaData['message'] ?? 'Sem dados disponíveis.') ?></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Conta / Paciente</th>
                                <th>Hospital</th>
                                <th>Operadora</th>
                                <th class="text-center">Dias em aberto</th>
                                <th class="text-center">Oportunidade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($glosaData['entries'])): ?>
                                <tr><td colspan="5" class="text-muted">Sem registros para os filtros aplicados.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($glosaData['entries'] as $entry): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold">Capeante #<?= (int)$entry['id_capeante'] ?> · <?= e($entry['nome_pac'] ?? 'Paciente') ?></div>
                                        <small class="text-muted">Int. #<?= (int)$entry['internacao_id'] ?></small>
                                    </td>
                                    <td><?= e($entry['nome_hosp'] ?? '-') ?></td>
                                    <td><?= e($entry['operadora'] ?? '-') ?></td>
                                    <td class="text-center"><?= (int)$entry['dias_aberto'] ?>d</td>
                                    <td class="text-center">
                                        <span class="status-pill risk-pill <?= $entry['risk_level'] ?>">
                                            <?= ucfirst($entry['risk_level']) ?> · <?= number_format($entry['probability'] * 100, 1) ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="report-card">
            <h5 class="mb-2">Auditoria assistida</h5>
            <div class="text-muted small mb-3">OPME, UTI não pertinente, duplicidades e itens fora de protocolo.</div>

            <h6>OPME sinalizada</h6>
            <div class="table-responsive mb-4">
                <table class="table table-sm table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Paciente</th>
                            <th>Hospital</th>
                            <th>Operadora</th>
                            <th>Observação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$opmeRows): ?>
                            <tr><td colspan="4" class="text-muted">Sem registros para os filtros aplicados.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($opmeRows as $row): ?>
                            <tr>
                                <td><?= e($row['nome_pac']) ?></td>
                                <td><?= e($row['nome_hosp']) ?></td>
                                <td><?= e($row['seguradora_seg']) ?></td>
                                <td><?= e($row['rel_opme_ges'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h6>UTI não pertinente</h6>
            <div class="table-responsive mb-4">
                <table class="table table-sm table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Paciente</th>
                            <th>Hospital</th>
                            <th>Operadora</th>
                            <th>Justificativa</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$utiRows): ?>
                            <tr><td colspan="4" class="text-muted">Sem registros para os filtros aplicados.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($utiRows as $row): ?>
                            <tr>
                                <td><?= e($row['nome_pac']) ?></td>
                                <td><?= e($row['nome_hosp']) ?></td>
                                <td><?= e($row['seguradora_seg']) ?></td>
                                <td><?= e($row['just_uti'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h6>Duplicidades TUSS</h6>
            <div class="table-responsive mb-4">
                <table class="table table-sm table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Paciente</th>
                            <th>Hospital</th>
                            <th>Operadora</th>
                            <th>Procedimento</th>
                            <th class="text-center">Duplicados</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$duplicRows): ?>
                            <tr><td colspan="5" class="text-muted">Sem registros para os filtros aplicados.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($duplicRows as $row): ?>
                            <tr>
                                <td><?= e($row['nome_pac']) ?></td>
                                <td><?= e($row['nome_hosp']) ?></td>
                                <td><?= e($row['seguradora_seg']) ?></td>
                                <td><?= e($row['tuss_solicitado']) ?></td>
                                <td class="text-center"><?= (int)$row['duplicados'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h6>Fora de protocolo / pendências</h6>
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Paciente</th>
                            <th>Hospital</th>
                            <th>Operadora</th>
                            <th>Procedimento</th>
                            <th class="text-center">Solicitado</th>
                            <th class="text-center">Liberado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$foraRows): ?>
                            <tr><td colspan="6" class="text-muted">Sem registros para os filtros aplicados.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($foraRows as $row): ?>
                            <tr>
                                <td><?= e($row['nome_pac']) ?></td>
                                <td><?= e($row['nome_hosp']) ?></td>
                                <td><?= e($row['seguradora_seg']) ?></td>
                                <td><?= e($row['tuss_solicitado']) ?></td>
                                <td class="text-center"><?= e($row['qtd_tuss_solicitado'] ?? '-') ?></td>
                                <td class="text-center"><?= e($row['qtd_tuss_liberado'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>

<?php require_once("templates/footer.php"); ?>
