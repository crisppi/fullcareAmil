<?php
include_once("check_logado.php");
require_once("templates/header.php");
require_once("app/services/AuditorActionService.php");

if (!isset($conn) || !($conn instanceof PDO)) {
    die("Conexão não disponível.");
}

function dashFetchCount(PDO $conn, string $sql): int
{
    try {
        $stmt = $conn->query($sql);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('[DASHBOARD_360][COUNT] ' . $e->getMessage());
        return 0;
    }
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$normCargoAccess = static function ($txt): string {
    $txt = mb_strtolower(trim((string)$txt), 'UTF-8');
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $txt);
    $txt = $ascii !== false ? $ascii : $txt;
    return preg_replace('/[^a-z]/', '', $txt);
};
$isGestorSeguradora = (strpos($normCargoAccess($_SESSION['cargo'] ?? ''), 'seguradora') !== false);
$seguradoraUserId = (int)($_SESSION['fk_seguradora_user'] ?? 0);
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
        error_log('[DASHBOARD_360][SEGURADORA] ' . $e->getMessage());
    }
}
$seguradoraFiltroPac = $isGestorSeguradora
    ? ($seguradoraUserId > 0 ? ' AND p.fk_seguradora_pac = ' . $seguradoraUserId : ' AND 1=0')
    : '';
$isAuditorOperacional = AuditorActionService::canUseOperationalSearch($_SESSION);
$auditorDashboard = ['counts' => [], 'queue' => [], 'alerts' => []];
if ($isAuditorOperacional) {
    $auditorActionService = new AuditorActionService($conn, $BASE_URL);
    $auditorDashboard = $auditorActionService->dashboardSummary($_SESSION, 10);
}

function dashCacheGet(string $key, int $ttl)
{
    $cache = $_SESSION['dash_oper_cache'] ?? [];
    if (!isset($cache[$key])) return null;
    $item = $cache[$key];
    if (!is_array($item) || !isset($item['ts'])) return null;
    if ((time() - (int)$item['ts']) > $ttl) return null;
    return $item['data'] ?? null;
}

function dashCacheSet(string $key, $data): void
{
    if (!isset($_SESSION['dash_oper_cache'])) $_SESSION['dash_oper_cache'] = [];
    $_SESSION['dash_oper_cache'][$key] = [
        'ts' => time(),
        'data' => $data,
    ];
}

$cacheScope = $isGestorSeguradora ? 'seg_' . $seguradoraUserId : 'geral';
$counts = dashCacheGet('counts_' . $cacheScope, 60);
if (!is_array($counts)) {
    $counts = [
        'internacoesAtivas' => dashFetchCount(
            $conn,
            "SELECT COUNT(*)
               FROM tb_internacao i
               JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
              WHERE i.internado_int = 's'{$seguradoraFiltroPac}"
        ),
        'contasAuditoria' => dashFetchCount(
            $conn,
            "SELECT COUNT(*)
               FROM tb_capeante c
               JOIN tb_internacao i ON i.id_internacao = c.fk_int_capeante
               JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
              WHERE COALESCE(c.encerrado_cap,'n') <> 's'{$seguradoraFiltroPac}"
        ),
        'visitasAtrasadas' => dashFetchCount(
            $conn,
            "SELECT COUNT(*)
               FROM tb_visita v
               JOIN tb_internacao i ON i.id_internacao = v.fk_internacao_vis
               JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
              WHERE DATE(IFNULL(v.data_visita_vis, DATE(v.data_lancamento_vis))) < CURDATE()
                AND (v.data_lancamento_vis IS NULL OR v.data_lancamento_vis = '0000-00-00 00:00:00'){$seguradoraFiltroPac}"
        ),
        'negociacoesPendentes' => dashFetchCount(
            $conn,
            "SELECT COUNT(*)
               FROM tb_negociacao n
               JOIN tb_internacao i ON i.id_internacao = n.fk_id_int
               JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
              WHERE (n.data_fim_neg IS NULL OR n.data_fim_neg = '0000-00-00')
                AND UPPER(COALESCE(n.tipo_negociacao, '')) <> 'PRORROGACAO_AUTOMATICA'{$seguradoraFiltroPac}"
        ),
        'eventosCriticos' => dashFetchCount(
            $conn,
            "SELECT COUNT(*)
               FROM tb_gestao g
               JOIN tb_internacao i ON i.id_internacao = g.fk_internacao_ges
               JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
              WHERE g.evento_adverso_ges = 's'
                AND (g.evento_encerrar_ges IS NULL OR g.evento_encerrar_ges <> 's'){$seguradoraFiltroPac}"
        ),
    ];
    dashCacheSet('counts_' . $cacheScope, $counts);
}

$internacoesAtivas = (int)($counts['internacoesAtivas'] ?? 0);
$contasAuditoria = (int)($counts['contasAuditoria'] ?? 0);
$visitasAtrasadas = (int)($counts['visitasAtrasadas'] ?? 0);
$negociacoesPendentes = (int)($counts['negociacoesPendentes'] ?? 0);
$eventosCriticos = (int)($counts['eventosCriticos'] ?? 0);

$cards = [
    [
        'label' => 'Internações ativas',
        'value' => $internacoesAtivas,
        'icon'  => 'bi-hospital',
        'variant' => 'kpi-card-v2-1',
        'link'  => 'internacoes/lista',
        'desc'  => 'Pacientes internados em acompanhamento.'
    ],
    [
        'label' => 'Contas em auditoria',
        'value' => $contasAuditoria,
        'icon'  => 'bi-journal-text',
        'variant' => 'kpi-card-v2-2',
        'link'  => 'contas/auditar',
        'desc'  => 'Capeantes ainda sem encerramento.'
    ],
    [
        'label' => 'Visitas atrasadas',
        'value' => $visitasAtrasadas,
        'icon'  => 'bi-calendar-x',
        'variant' => 'kpi-card-v2-3',
        'link'  => 'visitas/lista?sort_field=data_visita&sort_dir=asc',
        'desc'  => 'Visitas sem lançamento atualizado.'
    ],
    [
        'label' => 'Negociações pendentes',
        'value' => $negociacoesPendentes,
        'icon'  => 'bi-arrow-repeat',
        'variant' => 'kpi-card-v2-4',
        'link'  => 'manual_negociacoes.html',
        'desc'  => 'Registros sem data de conclusão.'
    ],
    [
        'label' => 'Eventos críticos',
        'value' => $eventosCriticos,
        'icon'  => 'bi-exclamation-octagon',
        'variant' => 'kpi-card-v2-5',
        'link'  => 'manual_eventos.html',
        'desc'  => 'Eventos adversos ainda abertos.'
    ],
];

$prioridades = dashCacheGet('prioridades_' . $cacheScope, 60);
if (!is_array($prioridades)) {
    $prioridades = [];
    try {
        $sqlScore = "
        SELECT
            i.id_internacao,
            p.nome_pac,
            h.nome_hosp,
            DATEDIFF(CURDATE(), DATE(i.data_intern_int)) AS dias_internado,
            COALESCE(SUM(c.valor_apresentado_capeante), 0) AS valor_apresentado,
            COALESCE(SUM(CASE WHEN g.evento_adverso_ges = 's' AND (g.evento_encerrar_ges IS NULL OR g.evento_encerrar_ges <> 's') THEN 1 ELSE 0 END), 0) AS eventos_abertos
        FROM tb_internacao i
        JOIN tb_paciente  p ON p.id_paciente   = i.fk_paciente_int
        JOIN tb_hospital  h ON h.id_hospital   = i.fk_hospital_int
        LEFT JOIN tb_capeante c ON c.fk_int_capeante = i.id_internacao
        LEFT JOIN tb_gestao   g ON g.fk_internacao_ges = i.id_internacao
        WHERE i.internado_int = 's'{$seguradoraFiltroPac}
        GROUP BY i.id_internacao
        ORDER BY i.data_intern_int ASC
        LIMIT 30";
        $stmtScore = $conn->prepare($sqlScore);
        $stmtScore->execute();
        $rows = $stmtScore->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $dias     = max(0, (int)($row['dias_internado'] ?? 0));
            $valorApr = (float)($row['valor_apresentado'] ?? 0);
            $eventos  = max(0, (int)($row['eventos_abertos'] ?? 0));

            $score = round(($dias * 1.2) + ($valorApr / 1000) + ($eventos * 5), 1);
            $row['score'] = $score;
            $row['valor_apresentado'] = $valorApr;
            $prioridades[] = $row;
        }

        usort($prioridades, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        $prioridades = array_slice($prioridades, 0, 8);
        dashCacheSet('prioridades_' . $cacheScope, $prioridades);
    } catch (Throwable $e) {
        error_log('[DASHBOARD_360][SCORE] ' . $e->getMessage());
        $prioridades = [];
    }
}
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<link rel="stylesheet" href="<?= htmlspecialchars(rtrim($BASE_URL, '/') . '/css/listagem_padrao.css?v=' . filemtime(__DIR__ . '/css/listagem_padrao.css'), ENT_QUOTES, 'UTF-8') ?>">

<style>
.dashboard-wrapper {
    width: 100%;
    max-width: none;
    margin: 2px 0 18px;
    padding: 0 4px;
}

.dash-hero {
    min-height: 40px;
    margin-bottom: 6px;
    padding: 5px 12px;
    border-radius: 8px;
}

.dash-hero .listagem-title {
    font-size: .78rem !important;
    line-height: 1 !important;
    font-weight: 800;
}

.dash-hero .listagem-kicker {
    font-size: .44rem !important;
    letter-spacing: .08em !important;
    line-height: 1 !important;
}

.dash-hero .listagem-subtitle {
    margin-top: 1px;
    color: rgba(255, 255, 255, .76);
    font-size: .54rem !important;
    line-height: 1 !important;
}

.dash-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 6px;
}
.dash-card {
    text-decoration: none;
    min-height: 62px !important;
    padding: 7px 9px !important;
    border-radius: 8px !important;
    box-shadow: 0 2px 8px rgba(20, 42, 74, .12) !important;
}
.kpi-card-v2-head {
    gap: 6px !important;
    margin-bottom: 3px !important;
}
.kpi-card-v2-head small {
    font-size: .5rem !important;
    letter-spacing: .07em;
}
.kpi-card-v2-icon {
    width: 20px !important;
    height: 20px !important;
    border-radius: 6px !important;
    font-size: .68rem !important;
}
.dash-card .dash-value {
    margin-top: 0;
    font-size: .98rem;
    line-height: 1.05;
}
.dash-card .dash-desc {
    margin: 0;
    color: rgba(228, 241, 255, 0.85);
    font-size: .54rem;
    min-height: 0;
    line-height: 1.1;
}
.dash-card .dash-link {
    margin-top: 2px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: .55rem;
    color: #cfe8ff;
    font-weight: 800;
}
.bi-kpi.kpi-card-v2.kpi-card-v2-5 {
    background: linear-gradient(140deg, rgba(112, 31, 31, 0.96), rgba(205, 53, 53, 0.9));
    border-color: rgba(255, 168, 168, 0.46);
}
.dash-table-card {
    margin-top: 10px;
    border-radius: 9px;
    border: 1px solid rgba(76, 142, 187, .18);
    background: #fff;
    box-shadow: 0 2px 9px rgba(34, 45, 60, .12);
}
.dash-table-card h4 {
    padding: 8px 12px;
    margin: 0;
    border-bottom: 1px solid rgba(76, 142, 187, .16);
    font-weight: 800;
    color: #24384f;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: .76rem;
}
.dash-table-card table {
    width: 100%;
    border-collapse: collapse;
}
.dash-table-card th,
.dash-table-card td {
    padding: 3px 6px;
    font-size: .68rem;
    text-align: left;
}
.dash-table-card th {
    text-transform: uppercase;
    letter-spacing: .06em;
    font-weight: 700;
    color: #ffffff;
    border-bottom: 1px solid rgba(47, 111, 159, .22);
    background: #2f6f9f;
}
.dash-table-card tr + tr td {
    border-top: 1px solid rgba(76, 142, 187, .10);
}
.badge-score {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 9px;
    border-radius: 999px;
    font-weight: 700;
    font-size: .68rem;
    color: #fff;
    background: linear-gradient(120deg, #2f6f9f, #5eb4d8);
}
.badge-score.low { background: linear-gradient(120deg, #0d9488, #3b82f6); }
.badge-score.mid { background: linear-gradient(120deg, #f97316, #ef4444); }
.badge-score.high { background: linear-gradient(120deg, #be185d, #7e22ce); }
.auditor-action-wrap {
    margin-top: 10px;
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(320px, .42fr);
    gap: 10px;
    align-items: start;
}
.auditor-panel {
    border-radius: 9px;
    border: 1px solid rgba(76, 142, 187, .18);
    background: #fff;
    box-shadow: 0 2px 9px rgba(34, 45, 60, .12);
    overflow: hidden;
}
.auditor-panel__head {
    padding: 8px 12px;
    border-bottom: 1px solid rgba(76, 142, 187, .14);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}
.auditor-panel__head h4 {
    margin: 0;
    color: #24384f;
    font-size: .78rem;
    font-weight: 800;
}
.auditor-panel__head small {
    color: #7a6a86;
    font-size: .64rem;
}
.auditor-action-list {
    display: grid;
}
.auditor-action-item {
    display: grid;
    grid-template-columns: 30px minmax(0, 1fr) auto;
    gap: 8px;
    align-items: center;
    padding: 7px 10px;
    text-decoration: none;
    color: #24384f;
}
.auditor-action-item + .auditor-action-item {
    border-top: 1px solid rgba(76, 142, 187, .10);
}
.auditor-action-item:hover {
    background: #f7fbff;
    color: #24384f;
}
.auditor-action-icon {
    width: 30px;
    height: 30px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    background: #eff7ff;
    color: #2f6f9f;
}
.auditor-action-icon.danger { background: #fff1f2; color: #be123c; }
.auditor-action-icon.warning { background: #fff7ed; color: #c2410c; }
.auditor-action-icon.info { background: #eef6ff; color: #2563eb; }
.auditor-action-icon.primary { background: #f2e8f7; color: #5e2363; }
.auditor-action-main {
    min-width: 0;
}
.auditor-action-title {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: center;
    font-size: .68rem;
    font-weight: 800;
}
.auditor-action-title span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 100%;
}
.auditor-action-meta {
    margin-top: 2px;
    color: #6b7280;
    font-size: .62rem;
}
.auditor-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    min-height: 24px;
    padding: 0 8px;
    border-radius: 7px;
    background: #f4faff;
    border: 1px solid #d7e8f3;
    color: #2f6f9f;
    font-size: .62rem;
    font-weight: 800;
    white-space: nowrap;
}
.auditor-alert-stack {
    padding: 8px;
    display: grid;
    gap: 7px;
}
.auditor-alert {
    display: flex;
    align-items: flex-start;
    gap: 7px;
    padding: 7px 9px;
    border-radius: 8px;
    font-size: .66rem;
    font-weight: 700;
}
.auditor-alert.danger { background: #fff1f2; color: #881337; }
.auditor-alert.warning { background: #fff7ed; color: #9a3412; }
.auditor-alert.info { background: #eff6ff; color: #1e3a8a; }
.auditor-mini-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 7px;
    padding: 8px;
    border-top: 1px solid rgba(76, 142, 187, .10);
}
.auditor-mini-kpi {
    min-height: 46px;
    border-radius: 8px;
    border: 1px solid rgba(76, 142, 187, .14);
    background: #fbfdff;
    padding: 6px 8px;
}
.auditor-mini-kpi small {
    display: block;
    color: #6b7280;
    font-size: .55rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.auditor-mini-kpi strong {
    display: block;
    color: #24384f;
    font-size: .9rem;
    line-height: 1.15;
}
@media (max-width: 1120px) {
    .auditor-action-wrap {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 768px) {
    .dash-card .dash-value { font-size: clamp(1.2rem, 6vw, 2rem); }
    .auditor-action-item {
        grid-template-columns: 34px minmax(0, 1fr);
    }
    .auditor-action-btn {
        grid-column: 2;
        justify-self: start;
    }
}
@media (max-width: 1320px) {
    .dash-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
@media (max-width: 860px) {
    .dash-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 640px) {
    .dash-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="dashboard-wrapper">
    <div class="listagem-hero listagem-hero--module listagem-hero--gestao dash-hero">
        <div class="listagem-hero__copy">
            <p class="listagem-kicker">Gestão</p>
            <h1 class="listagem-title">Painel Operacional 360°</h1>
            <p class="listagem-subtitle">Resumo em tempo real das principais frentes operacionais.</p>
        </div>
    </div>

    <div class="dash-grid">
        <?php foreach ($cards as $card): ?>
        <a class="dash-card bi-kpi kpi-card-v2 <?= htmlspecialchars($card['variant']) ?>" href="<?= $BASE_URL . $card['link'] ?>">
            <div class="kpi-card-v2-head">
                <span class="kpi-card-v2-icon"><i class="bi <?= $card['icon'] ?>"></i></span>
                <small><?= htmlspecialchars($card['label']) ?></small>
            </div>
            <strong class="dash-value"><?= number_format($card['value'], 0, ',', '.') ?></strong>
            <p class="dash-desc"><?= htmlspecialchars($card['desc']) ?></p>
            <span class="dash-link">Ver detalhes <i class="bi bi-arrow-right-short"></i></span>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if ($isAuditorOperacional): ?>
    <?php
        $audCounts = $auditorDashboard['counts'] ?? [];
        $audQueue = $auditorDashboard['queue'] ?? [];
        $audAlerts = $auditorDashboard['alerts'] ?? [];
    ?>
    <div class="auditor-action-wrap">
        <div class="auditor-panel">
            <div class="auditor-panel__head">
                <div>
                    <h4>Fila do auditor</h4>
                    <small>Prioridades operacionais da sua carteira</small>
                </div>
                <small><?= number_format((int)($audCounts['fila_total'] ?? 0), 0, ',', '.') ?> pendências</small>
            </div>
            <div class="auditor-action-list">
                <?php if (!$audQueue): ?>
                    <div class="p-4 text-center text-muted" style="font-size:.82rem;">Nenhuma ação crítica para o auditor no momento.</div>
                <?php else: ?>
                    <?php foreach ($audQueue as $item): ?>
                        <a class="auditor-action-item" href="<?= htmlspecialchars((string)$item['action_url'], ENT_QUOTES, 'UTF-8') ?>">
                            <span class="auditor-action-icon <?= htmlspecialchars((string)$item['severity'], ENT_QUOTES, 'UTF-8') ?>">
                                <i class="bi <?= htmlspecialchars((string)$item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                            </span>
                            <span class="auditor-action-main">
                                <span class="auditor-action-title">
                                    <strong><?= htmlspecialchars((string)$item['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span><?= htmlspecialchars((string)$item['paciente'], ENT_QUOTES, 'UTF-8') ?></span>
                                </span>
                                <span class="auditor-action-meta">
                                    <?= htmlspecialchars((string)$item['hospital'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php if (!empty($item['senha'])): ?> · senha <?= htmlspecialchars((string)$item['senha'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                                    <?php if ((int)($item['dias'] ?? 0) > 0): ?> · <?= (int)$item['dias'] ?> dia(s)<?php endif; ?>
                                    · <?= htmlspecialchars((string)$item['detail'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </span>
                            <span class="auditor-action-btn"><?= htmlspecialchars((string)$item['action_label'], ENT_QUOTES, 'UTF-8') ?> <i class="bi bi-arrow-right-short"></i></span>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="auditor-panel">
            <div class="auditor-panel__head">
                <div>
                    <h4>Alertas do auditor</h4>
                    <small>Sinais que pedem atenção hoje</small>
                </div>
            </div>
            <div class="auditor-alert-stack">
                <?php if (!$audAlerts): ?>
                    <div class="auditor-alert info"><i class="bi bi-check-circle"></i><span>Sem alertas críticos para sua carteira.</span></div>
                <?php else: ?>
                    <?php foreach ($audAlerts as $alert): ?>
                        <div class="auditor-alert <?= htmlspecialchars((string)$alert['level'], ENT_QUOTES, 'UTF-8') ?>">
                            <i class="bi <?= htmlspecialchars((string)$alert['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                            <span><?= htmlspecialchars((string)$alert['text'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="auditor-mini-grid">
                <div class="auditor-mini-kpi"><small>Visitas atrasadas</small><strong><?= number_format((int)($audCounts['visitas_atrasadas'] ?? 0), 0, ',', '.') ?></strong></div>
                <div class="auditor-mini-kpi"><small>Eventos abertos</small><strong><?= number_format((int)($audCounts['eventos_abertos'] ?? 0), 0, ',', '.') ?></strong></div>
                <div class="auditor-mini-kpi"><small>Contas pendentes</small><strong><?= number_format((int)($audCounts['contas_pendentes'] ?? 0), 0, ',', '.') ?></strong></div>
                <div class="auditor-mini-kpi"><small>Negociações</small><strong><?= number_format((int)($audCounts['negociacoes_pendentes'] ?? 0), 0, ',', '.') ?></strong></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="dash-table-card">
        <h4>
            Score de prioridade por paciente
            <small style="font-size:.74rem;color:#7a6a86;">Fórmula: dias internado (x1.2) + valor apresentado (÷1000) + eventos (x5)</small>
        </h4>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Internação</th>
                        <th>Paciente</th>
                        <th>Hospital</th>
                        <th>Dias</th>
                        <th>Valor apresentado (R$)</th>
                        <th>Eventos</th>
                        <th>Score</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$prioridades): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;color:#7a6a86;padding:20px;font-size:.82rem;">
                            Nenhum paciente priorizado no momento.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($prioridades as $row):
                        $scoreLabel = $row['score'] >= 25 ? 'high' : ($row['score'] >= 15 ? 'mid' : 'low');
                    ?>
                    <tr>
                        <td>#<?= (int) $row['id_internacao'] ?></td>
                        <td><?= htmlspecialchars($row['nome_pac']) ?></td>
                        <td><?= htmlspecialchars($row['nome_hosp']) ?></td>
                        <td><?= (int) $row['dias_internado'] ?></td>
                        <td>R$ <?= number_format($row['valor_apresentado'], 2, ',', '.') ?></td>
                        <td><?= (int) $row['eventos_abertos'] ?></td>
                        <td><span class="badge-score <?= $scoreLabel ?>"><?= $row['score'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
