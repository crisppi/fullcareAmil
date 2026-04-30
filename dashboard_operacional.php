<?php
include_once("check_logado.php");
require_once("templates/header.php");

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
        'link'  => 'list_internacao_cap_rah.php',
        'desc'  => 'Capeantes ainda sem encerramento.'
    ],
    [
        'label' => 'Visitas atrasadas',
        'value' => $visitasAtrasadas,
        'icon'  => 'bi-calendar-x',
        'variant' => 'kpi-card-v2-3',
        'link'  => 'lista_visitas.php?sort_field=data_visita&sort_dir=asc',
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

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260213b">

<style>
.dashboard-wrapper {
    width: 100%;
    max-width: none;
    margin: 14px 0 40px;
    padding: 0 18px;
}
.dash-hero {
    background: linear-gradient(120deg, #fff6fb, #fbe1f2 60%, #f3cee6);
    color: #3b1d4f;
    border-radius: 14px;
    padding: 20px 22px;
    margin-bottom: 16px;
    border: 1px solid rgba(94, 35, 99, .12);
    box-shadow: 0 20px 45px rgba(94, 35, 99, .15);
}
.dash-hero h1 {
    font-weight: 800;
    letter-spacing: .02em;
    margin-bottom: 8px;
    font-size: clamp(1.34rem, 2.2vw, 1.9rem);
}
.dash-hero p {
    margin: 0;
    opacity: .85;
    font-size: .84rem;
}
.dash-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 10px;
}
.dash-card {
    text-decoration: none;
}
.dash-card .dash-value {
    margin-top: 0;
    font-size: clamp(1.18rem, 1.8vw, 1.56rem);
    line-height: 1.05;
}
.dash-card .dash-desc {
    margin: 0;
    color: rgba(228, 241, 255, 0.85);
    font-size: .74rem;
    min-height: 2.3em;
}
.dash-card .dash-link {
    margin-top: 2px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: .74rem;
    color: #cfe8ff;
    font-weight: 600;
}
.bi-kpi.kpi-card-v2.kpi-card-v2-5 {
    background: linear-gradient(140deg, rgba(112, 31, 31, 0.96), rgba(205, 53, 53, 0.9));
    border-color: rgba(255, 168, 168, 0.46);
}
.dash-table-card {
    margin-top: 24px;
    border-radius: 14px;
    border: 1px solid rgba(94, 35, 99, .1);
    background: #fff;
    box-shadow: 0 12px 25px rgba(13, 10, 30, .08);
}
.dash-table-card h4 {
    padding: 12px 16px;
    margin: 0;
    border-bottom: 1px solid rgba(94, 35, 99, .1);
    font-weight: 800;
    color: #3b1d4f;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: .92rem;
}
.dash-table-card table {
    width: 100%;
    border-collapse: collapse;
}
.dash-table-card th,
.dash-table-card td {
    padding: 9px 12px;
    font-size: .82rem;
    text-align: left;
}
.dash-table-card th {
    text-transform: uppercase;
    letter-spacing: .06em;
    font-weight: 700;
    color: #7a6a86;
    border-bottom: 1px solid rgba(94, 35, 99, .08);
    background: #fbf7ff;
}
.dash-table-card tr + tr td {
    border-top: 1px solid rgba(94, 35, 99, .05);
}
.badge-score {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 999px;
    font-weight: 700;
    font-size: .74rem;
    color: #fff;
    background: linear-gradient(120deg, #5e2363, #a23ec3);
}
.badge-score.low { background: linear-gradient(120deg, #0d9488, #3b82f6); }
.badge-score.mid { background: linear-gradient(120deg, #f97316, #ef4444); }
.badge-score.high { background: linear-gradient(120deg, #be185d, #7e22ce); }
@media (max-width: 768px) {
    .dash-card .dash-value { font-size: clamp(1.2rem, 6vw, 2rem); }
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
    <div class="dash-hero">
        <h1>Painel Operacional 360°</h1>
        <p>Resumo em tempo real das principais frentes (internação, contas, visitas, negociações e eventos).</p>
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
