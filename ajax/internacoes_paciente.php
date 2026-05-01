<?php
header('Content-Type: application/json; charset=utf-8');

// Muda o diretório de trabalho para a raiz do projeto (um nível acima de /ajax)
$ROOT = dirname(__DIR__);
chdir($ROOT);

// Agora pode requerer usando caminhos relativos à raiz
require_once 'globals.php';
require_once 'db.php';
require_once 'ajax/_auth_scope.php';
require_once 'models/message.php';
require_once 'models/internacao.php'; // opcional, mas não atrapalha (require_once)
require_once 'dao/internacaoDao.php';

function hubDateToTs(?string $date): ?int
{
    if (!$date) {
        return null;
    }
    $ts = strtotime(substr((string)$date, 0, 10));
    return $ts ? (int)$ts : null;
}

function hubDaysExclusive(int $startTs, int $endTs): int
{
    if ($endTs <= $startTs) {
        return 0;
    }
    return (int) floor(($endTs - $startTs) / 86400);
}

function hubComputeCoverageAndGaps(array $intervals, int $startTs, int $endTs): array
{
    if (!$intervals) {
        return [0, hubDaysExclusive($startTs, $endTs), [[date('d/m/Y', $startTs), date('d/m/Y', $endTs)]]];
    }

    usort($intervals, fn($a, $b) => $a['s'] <=> $b['s']);

    $coveredDays = 0;
    $gaps = [];
    $curS = $intervals[0]['s'];
    $curE = $intervals[0]['e'];

    foreach ($intervals as $idx => $it) {
        if ($idx === 0) {
            continue;
        }

        if ($it['s'] <= $curE) {
            if ($it['e'] > $curE) {
                $curE = $it['e'];
            }
            continue;
        }

        if ($curS > $startTs) {
            $gapStart = $startTs;
            $gapEnd = $curS;
            if ($gapEnd > $gapStart) {
                $gaps[] = [date('d/m/Y', $gapStart), date('d/m/Y', $gapEnd)];
            }
        }

        $coveredDays += hubDaysExclusive($curS, $curE);
        $curS = $it['s'];
        $curE = $it['e'];
    }

    if ($curS > $startTs) {
        $gapStart = $startTs;
        $gapEnd = $curS;
        if ($gapEnd > $gapStart) {
            $gaps[] = [date('d/m/Y', $gapStart), date('d/m/Y', $gapEnd)];
        }
    }

    $coveredDays += hubDaysExclusive($curS, $curE);

    if ($curE < $endTs) {
        $gapStart = $curE;
        $gapEnd = $endTs;
        if ($gapEnd > $gapStart) {
            $gaps[] = [date('d/m/Y', $gapStart), date('d/m/Y', $gapEnd)];
        }
    }

    $totalDays = hubDaysExclusive($startTs, $endTs);
    $missingDays = max(0, $totalDays - $coveredDays);

    return [$coveredDays, $missingDays, $gaps];
}

function hubInternacaoEvidenceScore(array $row): int
{
    $score = 0;
    $temAlta = !empty($row['data_alta_alt']) && $row['data_alta_alt'] !== '0000-00-00';
    if ($temAlta) {
        $score += 100;
    }
    $score += ((int)($row['visitas_total'] ?? 0)) * 20;
    $score += ((int)($row['prorrogacoes'] ?? 0)) * 15;
    $score += ((int)($row['negociacoes'] ?? 0)) * 15;
    if (($row['internado_int'] ?? '') === 'n') {
        $score += 5;
    }
    return $score;
}

function hubDeduplicateInternacoes(array $rows): array
{
    $grouped = [];
    foreach ($rows as $row) {
        $key = implode('|', [
            (string)($row['fk_hospital_int'] ?? 0),
            (string)($row['data_intern_int'] ?? ''),
        ]);
        $grouped[$key][] = $row;
    }

    $result = [];
    foreach ($grouped as $group) {
        if (count($group) <= 1) {
            $result[] = $group[0];
            continue;
        }

        $hasZeroEvidence = false;
        $hasRichEvidence = false;
        foreach ($group as $row) {
            $score = hubInternacaoEvidenceScore($row);
            if ($score <= 0) {
                $hasZeroEvidence = true;
            } else {
                $hasRichEvidence = true;
            }
        }

        if ($hasZeroEvidence && $hasRichEvidence) {
            usort($group, static function ($a, $b) {
                $scoreCompare = hubInternacaoEvidenceScore($b) <=> hubInternacaoEvidenceScore($a);
                if ($scoreCompare !== 0) {
                    return $scoreCompare;
                }
                return ((int)($b['id_internacao'] ?? 0)) <=> ((int)($a['id_internacao'] ?? 0));
            });
            $result[] = $group[0];
            continue;
        }

        foreach ($group as $row) {
            $result[] = $row;
        }
    }

    usort($result, static function ($a, $b) {
        $dateCompare = strcmp((string)($b['data_intern_int'] ?? ''), (string)($a['data_intern_int'] ?? ''));
        if ($dateCompare !== 0) {
            return $dateCompare;
        }
        return ((int)($b['id_internacao'] ?? 0)) <=> ((int)($a['id_internacao'] ?? 0));
    });

    return $result;
}

try {
    ajax_require_active_session();
    $ctx = ajax_user_context($conn);
    $pacId = filter_input(INPUT_GET, 'id_paciente', FILTER_VALIDATE_INT);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = min(50, max(1, (int) ($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;

    if (!$pacId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'id_paciente obrigatório']);
        exit;
    }

    if (!ajax_assert_patient_access($conn, $ctx, (int)$pacId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'acesso_negado']);
        exit;
    }

    $scopeParams = [];
    $scopeSql = ajax_scope_clause_for_internacao($ctx, 'ac', $scopeParams, 'ipp');

    $sql = "SELECT
                ac.id_internacao,
                ac.data_intern_int,
                ac.hora_intern_int,
                ac.internado_int,
                ac.fk_hospital_int,
                ho.nome_hosp,
                al.data_alta_alt,
                al.hora_alta_alt,
                (
                    SELECT COUNT(*)
                      FROM tb_prorrogacao pr
                     WHERE pr.fk_internacao_pror = ac.id_internacao
                ) AS prorrogacoes,
                (
                    SELECT COUNT(*)
                      FROM tb_negociacao ng
                     WHERE ng.fk_id_int = ac.id_internacao
                       AND UPPER(COALESCE(ng.tipo_negociacao, '')) <> 'PRORROGACAO_AUTOMATICA'
                ) AS negociacoes,
                (
                    SELECT COUNT(*)
                      FROM tb_visita vi
                     WHERE vi.fk_internacao_vis = ac.id_internacao
                       AND (vi.retificado IS NULL OR vi.retificado = 0)
                ) AS visitas_total
            FROM tb_internacao ac
            LEFT JOIN tb_hospital ho ON ho.id_hospital = ac.fk_hospital_int
            LEFT JOIN tb_alta al ON al.id_alta = (
                SELECT al2.id_alta
                  FROM tb_alta al2
                 WHERE al2.fk_id_int_alt = ac.id_internacao
                 ORDER BY COALESCE(al2.data_alta_alt, '0000-00-00') DESC, al2.id_alta DESC
                 LIMIT 1
            )
            WHERE ac.fk_paciente_int = :pac {$scopeSql}
            ORDER BY ac.data_intern_int DESC, ac.id_internacao DESC";
    $stmtRows = $conn->prepare($sql);
    ajax_bind_params($stmtRows, array_merge([
        ':pac' => (int)$pacId,
    ], $scopeParams));
    $stmtRows->execute();
    $rows = $stmtRows->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $rows = hubDeduplicateInternacoes($rows);
    $total = count($rows);
    $rows = array_slice($rows, $offset, $limit);

    $ids = array_values(array_unique(array_filter(array_map(static fn($r) => (int)($r['id_internacao'] ?? 0), $rows))));
    $prorrogacoesByInternacao = [];

    if ($ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmtPr = $conn->prepare("
            SELECT fk_internacao_pror, prorrog1_ini_pror, prorrog1_fim_pror
            FROM tb_prorrogacao
            WHERE fk_internacao_pror IN ({$placeholders})
            ORDER BY fk_internacao_pror, prorrog1_ini_pror
        ");
        $stmtPr->execute($ids);
        while ($row = $stmtPr->fetch(PDO::FETCH_ASSOC)) {
            $fk = (int)($row['fk_internacao_pror'] ?? 0);
            if ($fk > 0) {
                $prorrogacoesByInternacao[$fk][] = $row;
            }
        }

        $stmtNegPr = $conn->prepare("
            SELECT fk_id_int AS fk_internacao_pror, data_inicio_neg AS prorrog1_ini_pror, data_fim_neg AS prorrog1_fim_pror
            FROM tb_negociacao
            WHERE fk_id_int IN ({$placeholders})
              AND tipo_negociacao = 'PRORROGACAO_AUTOMATICA'
            ORDER BY fk_id_int, data_inicio_neg
        ");
        $stmtNegPr->execute($ids);
        while ($row = $stmtNegPr->fetch(PDO::FETCH_ASSOC)) {
            $fk = (int)($row['fk_internacao_pror'] ?? 0);
            if ($fk > 0 && empty($prorrogacoesByInternacao[$fk])) {
                $prorrogacoesByInternacao[$fk][] = $row;
            }
        }
    }

    // formata datas
    $fmtDate = function ($d) {
        if (!$d || $d === '0000-00-00')
            return '';
        $dt = DateTime::createFromFormat('Y-m-d', $d);
        return $dt ? $dt->format('d/m/Y') : '';
    };

    $todayTs = strtotime(date('Y-m-d'));
    $payload = array_map(function ($r) use ($fmtDate, $todayTs, $prorrogacoesByInternacao) {
        $idInternacao = (int)($r['id_internacao'] ?? 0);
        $startTs = hubDateToTs($r['data_intern_int'] ?? null);
        $endTs = hubDateToTs($r['data_alta_alt'] ?? null) ?: $todayTs;
        $prorrogPendentes = 0;
        $prorrogPendentesLabel = '';

        if ($startTs && $endTs > $startTs) {
            $intervals = [];
            foreach (($prorrogacoesByInternacao[$idInternacao] ?? []) as $p) {
                $iniTs = hubDateToTs($p['prorrog1_ini_pror'] ?? null);
                if (!$iniTs) {
                    continue;
                }
                $fimBaseTs = hubDateToTs($p['prorrog1_fim_pror'] ?? null) ?: ($endTs - 86400);
                $fimTs = $fimBaseTs + 86400;
                if ($fimTs <= $startTs || $iniTs >= $endTs) {
                    continue;
                }
                $intervals[] = [
                    's' => max($startTs, $iniTs),
                    'e' => min($endTs, $fimTs),
                ];
            }

            $coverageStartTs = $intervals ? min(array_column($intervals, 's')) : $startTs;
            [, $missingDays, $gaps] = hubComputeCoverageAndGaps($intervals, $coverageStartTs, $endTs);
            if ($missingDays > 0) {
                $prorrogPendentes = count($gaps);
                $prorrogPendentesLabel = implode(' | ', array_map(static fn($g) => $g[0] . ' -> ' . $g[1], $gaps));
            }
        }

        $temAlta = !empty($r['data_alta_alt']) && $r['data_alta_alt'] !== '0000-00-00';

        return [
            'id_internacao' => $idInternacao,
            'admissao' => $fmtDate($r['data_intern_int'] ?? null),
            'alta' => $fmtDate($r['data_alta_alt'] ?? null),
            'hora_admissao' => $r['hora_intern_int'] ?? null,
            'hora_alta' => $r['hora_alta_alt'] ?? null,
            'unidade' => trim($r['nome_hosp'] ?? ''),
            'medico' => '', // TODO: incluir no SELECT se precisar
            'status' => $temAlta ? 'Alta' : ((isset($r['internado_int']) && $r['internado_int'] === 's') ? 'Internado' : 'Alta'),
            'prorrogacoes' => count($prorrogacoesByInternacao[$idInternacao] ?? []),
            'prorrogacoes_pendentes' => $prorrogPendentes,
            'prorrogacoes_pendentes_label' => $prorrogPendentesLabel,
            'negociacoes' => (int)($r['negociacoes'] ?? 0),
            'visitas' => (int)($r['visitas_total'] ?? 0),
            'tem_alta' => $temAlta,
        ];
    }, $rows ?: []);

    echo json_encode([
        'success' => true,
        'total' => (int) $total,
        'page' => $page,
        'limit' => $limit,
        'rows' => $payload
    ]);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro interno'
    ]);
    exit;
}
