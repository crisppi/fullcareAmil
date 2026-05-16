<?php
require_once("templates/header.php");

function eLogUser($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function formatCargoLogUser(?string $cargo): string
{
    $cargo = trim((string)$cargo);
    $map = [
        'Med_auditor' => 'Médico Auditor',
        'Enf_auditor' => 'Enfermeiro Auditor',
        'Enf_Auditor' => 'Enfermeiro Auditor',
    ];

    return $map[$cargo] ?? $cargo;
}

$norm = static function ($txt): string {
    $txt = mb_strtolower(trim((string)$txt), 'UTF-8');
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $txt);
    $txt = $ascii !== false ? $ascii : $txt;
    return preg_replace('/[^a-z]/', '', $txt);
};
$normCargo = $norm($_SESSION['cargo'] ?? '');
$normNivel = $norm($_SESSION['nivel'] ?? '');
$isDiretoria = in_array($normCargo, ['diretoria', 'diretor', 'administrador', 'admin', 'board'], true)
    || (strpos($normCargo, 'diretor') !== false)
    || (strpos($normCargo, 'diretoria') !== false)
    || in_array($normNivel, ['diretoria', 'diretor', 'administrador', 'admin', 'board'], true)
    || ((int)($_SESSION['nivel'] ?? 0) === -1);

if (!$isDiretoria) {
    http_response_code(403);
    die('Acesso negado. Requer cargo/nível: Diretoria.');
}

$logFile = __DIR__ . '/../logs/flow_operacional.log';
$dateFrom = trim((string)filter_input(INPUT_GET, 'data_ini')) ?: date('Y-m-d', strtotime('-30 days'));
$dateTo = trim((string)filter_input(INPUT_GET, 'data_fim')) ?: date('Y-m-d');
$userIdFilter = (int)(filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT) ?: 0);
$flowFilter = trim((string)filter_input(INPUT_GET, 'flow'));
$stageFilter = trim((string)filter_input(INPUT_GET, 'stage'));
$levelFilter = strtoupper(trim((string)filter_input(INPUT_GET, 'level')));
$qFilter = trim((string)filter_input(INPUT_GET, 'q'));
$limit = (int)(filter_input(INPUT_GET, 'limite', FILTER_VALIDATE_INT) ?: 200);
$limit = max(50, min(2000, $limit));

$dateStartTs = strtotime($dateFrom . ' 00:00:00');
$dateEndTs = strtotime($dateTo . ' 23:59:59');
if ($dateStartTs === false) {
    $dateStartTs = strtotime(date('Y-m-d') . ' 00:00:00');
}
if ($dateEndTs === false) {
    $dateEndTs = strtotime(date('Y-m-d') . ' 23:59:59');
}
if ($dateStartTs > $dateEndTs) {
    [$dateStartTs, $dateEndTs] = [$dateEndTs, $dateStartTs];
}

$usersMap = [];
try {
    $stmtUsers = $conn->query("SELECT id_usuario, usuario_user, cargo_user FROM tb_user ORDER BY usuario_user ASC");
    while ($u = $stmtUsers->fetch(PDO::FETCH_ASSOC)) {
        $id = (int)($u['id_usuario'] ?? 0);
        if ($id > 0) {
            $usersMap[$id] = [
                'nome' => trim((string)($u['usuario_user'] ?? '')),
                'cargo' => trim((string)($u['cargo_user'] ?? '')),
            ];
        }
    }
} catch (Throwable $e) {
    error_log('[LOGS_USUARIO][USERS] ' . $e->getMessage());
}

$rows = [];
$summary = [];

$extractUserId = static function (array $ctx, array $data): int {
    $candidates = [
        $ctx['session_user_id'] ?? null,
        $ctx['fk_usuario_int'] ?? null,
        $ctx['fk_usuario_vis'] ?? null,
        $ctx['fk_usuario_alt'] ?? null,
        $ctx['fk_usuario_neg'] ?? null,
        $ctx['fk_usuario_tuss'] ?? null,
        $ctx['fk_user_uti'] ?? null,
        $ctx['fk_usuario_pror'] ?? null,
        $ctx['fk_usuario_ges'] ?? null,
        $data['session_user_id'] ?? null,
    ];

    foreach ($ctx as $k => $v) {
        if (strpos((string)$k, 'fk_usuario') === 0 || strpos((string)$k, 'fk_user') === 0) {
            $candidates[] = $v;
        }
    }
    foreach ($data as $k => $v) {
        if (strpos((string)$k, 'fk_usuario') === 0 || strpos((string)$k, 'fk_user') === 0) {
            $candidates[] = $v;
        }
    }

    foreach ($candidates as $candidate) {
        if ($candidate === null || $candidate === '') {
            continue;
        }
        if (is_numeric($candidate)) {
            $id = (int)$candidate;
            if ($id > 0) {
                return $id;
            }
        }
    }
    return 0;
};

$extractRecordRef = static function (array $ctx, array $data, string $uri): string {
    $keys = [
        'id_internacao',
        'fk_internacao_vis',
        'fk_internacao_ges',
        'fk_internacao_pror',
        'fk_internacao_uti',
        'fk_id_int',
        'fk_id_int_alt',
        'id_paciente',
        'fk_paciente_int',
        'id_usuario',
        'fk_usuario_int',
        'id_capeante',
    ];

    foreach ($keys as $key) {
        if (isset($ctx[$key]) && $ctx[$key] !== '' && $ctx[$key] !== null) {
            return $key . ': ' . (string)$ctx[$key];
        }
        if (isset($data[$key]) && $data[$key] !== '' && $data[$key] !== null) {
            return $key . ': ' . (string)$data[$key];
        }
    }

    if ($uri !== '') {
        $parts = parse_url($uri);
        if (!empty($parts['query'])) {
            parse_str((string)$parts['query'], $queryParams);
            foreach ($keys as $key) {
                if (isset($queryParams[$key]) && $queryParams[$key] !== '') {
                    return $key . ': ' . (string)$queryParams[$key];
                }
            }
        }
    }

    return '--';
};

if (is_file($logFile) && is_readable($logFile)) {
    $fh = fopen($logFile, 'rb');
    if ($fh) {
        while (($line = fgets($fh)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $entry = json_decode($line, true);
            if (!is_array($entry)) {
                continue;
            }

            $tsRaw = (string)($entry['ts'] ?? '');
            $ts = strtotime($tsRaw);
            if ($ts === false || $ts < $dateStartTs || $ts > $dateEndTs) {
                continue;
            }

            $ctx = (isset($entry['ctx']) && is_array($entry['ctx'])) ? $entry['ctx'] : [];
            $data = (isset($entry['data']) && is_array($entry['data'])) ? $entry['data'] : [];
            $userId = $extractUserId($ctx, $data);

            $flow = (string)($entry['flow'] ?? '');
            $stage = (string)($entry['stage'] ?? '');
            $level = strtoupper((string)($entry['level'] ?? 'INFO'));
            $requestUri = (string)($ctx['request_uri'] ?? '');

            $tipoEvento = 'Sistema';
            if ($flow === 'page_access') {
                $tipoEvento = 'Acesso';
            } elseif ($flow === 'auth_login') {
                $tipoEvento = 'Login';
            } elseif (strpos($flow, 'process_') === 0) {
                $tipoEvento = 'Registro';
            }

            $acao = $stage !== '' ? $stage : ($flow !== '' ? $flow : '--');
            $registroRef = $extractRecordRef($ctx, $data, $requestUri);

            if ($userIdFilter > 0 && $userId !== $userIdFilter) {
                continue;
            }
            if ($flowFilter !== '' && stripos($flow, $flowFilter) === false) {
                continue;
            }
            if ($stageFilter !== '' && stripos($stage, $stageFilter) === false) {
                continue;
            }
            if ($levelFilter !== '' && $level !== $levelFilter) {
                continue;
            }
            if ($qFilter !== '') {
                $haystack = $line . ' ' . json_encode($ctx, JSON_UNESCAPED_UNICODE) . ' ' . json_encode($data, JSON_UNESCAPED_UNICODE);
                if (stripos((string)$haystack, $qFilter) === false) {
                    continue;
                }
            }

            $displayName = 'Sem usuário';
            if ($userId > 0 && isset($usersMap[$userId])) {
                $displayName = $usersMap[$userId]['nome'] !== '' ? $usersMap[$userId]['nome'] : ('Usuário #' . $userId);
            } elseif (!empty($ctx['session_user_name'])) {
                $displayName = (string)$ctx['session_user_name'];
            } elseif (!empty($data['session_user_name'])) {
                $displayName = (string)$data['session_user_name'];
            } elseif ($userId > 0) {
                $displayName = 'Usuário #' . $userId;
            }

            $summaryKey = ($userId > 0) ? ('id_' . $userId) : ('name_' . md5($displayName));
            if (!isset($summary[$summaryKey])) {
                $summary[$summaryKey] = [
                    'user_id' => $userId,
                    'nome' => $displayName,
                    'cargo' => ($userId > 0 && isset($usersMap[$userId])) ? ($usersMap[$userId]['cargo'] ?: '--') : '--',
                    'total' => 0,
                    'info' => 0,
                    'warning' => 0,
                    'error' => 0,
                    'ultimo_ts' => 0,
                    'flows' => [],
                ];
            }

            $summary[$summaryKey]['total']++;
            if ($level === 'ERROR') {
                $summary[$summaryKey]['error']++;
            } elseif ($level === 'WARNING' || $level === 'WARN') {
                $summary[$summaryKey]['warning']++;
            } else {
                $summary[$summaryKey]['info']++;
            }
            if ($ts > $summary[$summaryKey]['ultimo_ts']) {
                $summary[$summaryKey]['ultimo_ts'] = $ts;
            }
            $summary[$summaryKey]['flows'][$flow] = true;

            $rows[] = [
                'ts' => $ts,
                'ts_raw' => $tsRaw,
                'user_id' => $userId,
                'user_nome' => $displayName,
                'tipo_evento' => $tipoEvento,
                'acao' => $acao,
                'registro_ref' => $registroRef,
                'flow' => $flow,
                'stage' => $stage,
                'level' => $level,
                'trace_id' => (string)($entry['trace_id'] ?? ''),
                'request_uri' => $requestUri,
            ];
        }
        fclose($fh);
    }
}

usort($rows, static fn($a, $b) => $b['ts'] <=> $a['ts']);
if (count($rows) > $limit) {
    $rows = array_slice($rows, 0, $limit);
}

foreach ($summary as &$item) {
    $item['flows'] = implode(', ', array_slice(array_keys($item['flows']), 0, 4));
}
unset($item);
usort($summary, static fn($a, $b) => $b['total'] <=> $a['total']);

$totalRows = count($rows);
$totalSummaryUsers = count($summary);
$hasFile = is_file($logFile);
$fileMtime = $hasFile ? @filemtime($logFile) : null;
$acessosRows = array_values(array_filter($rows, static fn($r) => in_array($r['tipo_evento'], ['Acesso', 'Login'], true)));
$acessosRows = array_slice($acessosRows, 0, 30);
?>

<div class="container-fluid form_container" style="margin-top:15px;">
    <h4 class="page-title">Logs por Usuário</h4>
    <hr style="margin-top:5px;margin-bottom:10px;">

    <div class="alert alert-info py-2 mb-3">
        <strong>Arquivo:</strong> <code><?= eLogUser($logFile) ?></code>
        <?php if ($fileMtime): ?>
        <span class="ms-2">Atualizado em <?= eLogUser(date('d/m/Y H:i:s', (int)$fileMtime)) ?></span>
        <?php endif; ?>
    </div>

    <div class="complete-table">
        <div class="table-filters fc-list-filters">
            <form method="GET" class="fc-list-filters-line">
                <div class="fc-filter-item w-select">
                    <select class="form-control form-control-sm" name="user_id">
                        <option value="0">Todos usuários</option>
                        <?php foreach ($usersMap as $id => $u): ?>
                        <option value="<?= (int)$id ?>" <?= $userIdFilter === (int)$id ? 'selected' : '' ?>>
                            <?= eLogUser($u['nome'] !== '' ? $u['nome'] : ('Usuário #' . $id)) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fc-filter-item w-date">
                    <input class="form-control form-control-sm" type="date" name="data_ini" value="<?= eLogUser(date('Y-m-d', (int)$dateStartTs)) ?>">
                </div>
                <div class="fc-filter-item w-date">
                    <input class="form-control form-control-sm" type="date" name="data_fim" value="<?= eLogUser(date('Y-m-d', (int)$dateEndTs)) ?>">
                </div>
                <div class="fc-filter-item w-short">
                    <input class="form-control form-control-sm" type="text" name="flow" placeholder="Fluxo" value="<?= eLogUser($flowFilter) ?>">
                </div>
                <div class="fc-filter-item w-short">
                    <input class="form-control form-control-sm" type="text" name="stage" placeholder="Etapa" value="<?= eLogUser($stageFilter) ?>">
                </div>
                <div class="fc-filter-item w-select">
                    <select class="form-control form-control-sm" name="level">
                        <option value="">Nível (todos)</option>
                        <?php foreach (['INFO', 'WARNING', 'ERROR'] as $lv): ?>
                        <option value="<?= eLogUser($lv) ?>" <?= $levelFilter === $lv ? 'selected' : '' ?>><?= eLogUser($lv) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fc-filter-item w-short">
                    <input class="form-control form-control-sm" type="number" name="limite" min="50" max="2000" step="50"
                        value="<?= (int)$limit ?>" placeholder="Limite">
                </div>
                <div class="fc-filter-item w-paciente">
                    <input class="form-control form-control-sm" type="text" name="q" placeholder="Busca livre no JSON" value="<?= eLogUser($qFilter) ?>">
                </div>
                <div class="fc-filter-item w-actions">
                    <button type="submit" class="btn btn-primary"
                        style="background-color:#5e2363;width:42px;height:32px;border-color:#5e2363">
                        <span class="material-icons" style="margin-left:-3px;margin-top:-2px;">search</span>
                    </button>
                    <a href="<?= eLogUser($BASE_URL) ?>inteligencia/logs-usuarios" class="btn btn-light btn-sm" title="Limpar filtros">
                        <i class="bi bi-trash3"></i>
                    </a>
                </div>
            </form>
        </div>

        <?php if (!$hasFile): ?>
        <div class="alert alert-warning mt-3">Arquivo de logs ainda não existe: <code><?= eLogUser($logFile) ?></code></div>
        <?php else: ?>
        <div class="row mt-2 mb-3">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body py-2">
                        <div class="small text-muted">Registros filtrados</div>
                        <div style="font-size:1.4rem;font-weight:700;"><?= (int)$totalRows ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body py-2">
                        <div class="small text-muted">Usuários no período</div>
                        <div style="font-size:1.4rem;font-weight:700;"><?= (int)$totalSummaryUsers ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body py-2">
                        <div class="small text-muted">Filtro de datas</div>
                        <div style="font-size:1.0rem;font-weight:700;">
                            <?= eLogUser(date('d/m/Y', (int)$dateStartTs)) ?> a <?= eLogUser(date('d/m/Y', (int)$dateEndTs)) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <h6 class="mb-2">Resumo por usuário</h6>
        <div class="table-responsive mb-3">
            <table class="table table-sm table-striped table-hover table-condensed">
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>Cargo</th>
                        <th class="text-center">Total</th>
                        <th class="text-center">INFO</th>
                        <th class="text-center">WARNING</th>
                        <th class="text-center">ERROR</th>
                        <th>Último log</th>
                        <th>Fluxos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$summary): ?>
                    <tr>
                        <td colspan="8" class="text-muted">Sem dados para os filtros aplicados.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($summary as $s): ?>
                    <tr>
                        <td>
                            <?php if ((int)$s['user_id'] > 0): ?>
                            #<?= (int)$s['user_id'] ?> - <?= eLogUser($s['nome']) ?>
                            <?php else: ?>
                            <?= eLogUser($s['nome']) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= eLogUser(formatCargoLogUser($s['cargo'])) ?></td>
                        <td class="text-center fw-bold"><?= (int)$s['total'] ?></td>
                        <td class="text-center"><?= (int)$s['info'] ?></td>
                        <td class="text-center text-warning"><?= (int)$s['warning'] ?></td>
                        <td class="text-center text-danger fw-bold"><?= (int)$s['error'] ?></td>
                        <td><?= $s['ultimo_ts'] > 0 ? eLogUser(date('d/m/Y H:i:s', (int)$s['ultimo_ts'])) : '--' ?></td>
                        <td><?= eLogUser($s['flows']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <h6 class="mb-2">Últimos acessos (login e navegação)</h6>
        <div class="table-responsive mb-3">
            <table class="table table-sm table-striped table-hover table-condensed">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Usuário</th>
                        <th>Tipo</th>
                        <th>Ação</th>
                        <th>Tela/URI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$acessosRows): ?>
                    <tr>
                        <td colspan="5" class="text-muted">Sem acessos no período/filtros.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($acessosRows as $a): ?>
                    <tr>
                        <td><?= eLogUser(date('d/m/Y H:i:s', (int)$a['ts'])) ?></td>
                        <td>
                            <?php if ((int)$a['user_id'] > 0): ?>
                            #<?= (int)$a['user_id'] ?> - <?= eLogUser($a['user_nome']) ?>
                            <?php else: ?>
                            <?= eLogUser($a['user_nome']) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= eLogUser($a['tipo_evento']) ?></td>
                        <td><?= eLogUser($a['acao']) ?></td>
                        <td><?= eLogUser($a['request_uri']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <h6 class="mb-2">Últimos registros (máx. <?= (int)$limit ?>)</h6>
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover table-condensed">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Usuário</th>
                        <th>Tipo</th>
                        <th>Ação</th>
                        <th>Registro</th>
                        <th>Fluxo</th>
                        <th>Nível</th>
                        <th>Trace</th>
                        <th>URI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                    <tr>
                        <td colspan="9" class="text-muted">Sem registros para os filtros aplicados.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= eLogUser(date('d/m/Y H:i:s', (int)$r['ts'])) ?></td>
                        <td>
                            <?php if ((int)$r['user_id'] > 0): ?>
                            #<?= (int)$r['user_id'] ?> - <?= eLogUser($r['user_nome']) ?>
                            <?php else: ?>
                            <?= eLogUser($r['user_nome']) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= eLogUser($r['tipo_evento']) ?></td>
                        <td><?= eLogUser($r['acao']) ?></td>
                        <td><?= eLogUser($r['registro_ref']) ?></td>
                        <td><?= eLogUser($r['flow']) ?></td>
                        <td>
                            <?php if ($r['level'] === 'ERROR'): ?>
                            <span class="badge bg-danger"><?= eLogUser($r['level']) ?></span>
                            <?php elseif ($r['level'] === 'WARNING' || $r['level'] === 'WARN'): ?>
                            <span class="badge bg-warning text-dark"><?= eLogUser($r['level']) ?></span>
                            <?php else: ?>
                            <span class="badge bg-secondary"><?= eLogUser($r['level']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><code><?= eLogUser($r['trace_id']) ?></code></td>
                        <td><?= eLogUser($r['request_uri']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
