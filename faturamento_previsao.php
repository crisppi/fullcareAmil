<?php
include_once("check_logado.php");
require_once("templates/header.php");

if (!isset($conn) || !($conn instanceof PDO)) {
    die("Conexão não disponível para o painel.");
}

function fpFetch(PDO $conn, string $sql, array $params = [], $default = 0)
{
    try {
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : $default;
    } catch (Throwable $e) {
        error_log('[FAT_PREV][VALUE] ' . $e->getMessage());
        return $default;
    }
}

function fpFetchAll(PDO $conn, string $sql, array $params = []): array
{
    try {
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('[FAT_PREV][ALL] ' . $e->getMessage());
        return [];
    }
}

$openInternacoes = fpFetch($conn, "SELECT COUNT(*) FROM tb_internacao WHERE internado_int = 's'");
$contasAndamento = fpFetch($conn, "SELECT COUNT(*) FROM tb_capeante WHERE COALESCE(encerrado_cap,'n') <> 's'");

$mesesHistorico = fpFetchAll(
    $conn,
    "SELECT
        DATE_FORMAT(COALESCE(data_digit_capeante, data_fech_capeante, data_final_capeante, data_inicial_capeante), '%Y-%m-01') AS mes_ref,
        DATE_FORMAT(COALESCE(data_digit_capeante, data_fech_capeante, data_final_capeante, data_inicial_capeante), '%b/%Y') AS etiqueta,
        COUNT(*) AS total_contas,
        SUM(COALESCE(valor_apresentado_capeante,0)) AS valor_apr,
        SUM(COALESCE(valor_final_capeante,0)) AS valor_final
     FROM tb_capeante
    WHERE COALESCE(data_digit_capeante, data_fech_capeante, data_final_capeante, data_inicial_capeante) IS NOT NULL
      AND COALESCE(data_digit_capeante, data_fech_capeante, data_final_capeante, data_inicial_capeante) >= DATE_SUB(CURDATE(), INTERVAL 9 MONTH)
    GROUP BY mes_ref, etiqueta
    ORDER BY mes_ref ASC"
);

$totalApresentado = 0;
$totalFinal = 0;
$totalContas = 0;
foreach ($mesesHistorico as $mesInfo) {
    $totalApresentado += (float)$mesInfo['valor_apr'];
    $totalFinal += (float)$mesInfo['valor_final'];
    $totalContas += (int)$mesInfo['total_contas'];
}
$glosaRate = $totalApresentado > 0 ? max(0, min(0.9, 1 - ($totalFinal / $totalApresentado))) : 0.12;
$ticketMedio = $totalContas > 0 ? ($totalFinal / $totalContas) : 0;

$recentHistory = array_slice($mesesHistorico, -3);
$recentContas = 0;
foreach ($recentHistory as $row) {
    $recentContas += (int)$row['total_contas'];
}
$baseVolume = $recentHistory ? max(1, round($recentContas / count($recentHistory))) : max(1, $totalContas / max(1, count($mesesHistorico)));

if ($ticketMedio <= 0) {
    $ticketMedio = 3500; // fallback
}

$projecoes = [];
$inicio = new DateTime('first day of next month');
for ($i = 0; $i < 4; $i++) {
    $mesLabel = $inicio->format('M/Y');
    $volumeBase = $baseVolume + round(($openInternacoes * 0.05));
    $otVolume = (int)round($volumeBase * 1.08);
    $csVolume = (int)round(max(1, $volumeBase * 0.92));

    $otGlosa = max(0, $glosaRate * 0.85);
    $csGlosa = min(0.95, $glosaRate * 1.12);

    $otValor = $otVolume * $ticketMedio * (1 - $otGlosa);
    $csValor = $csVolume * $ticketMedio * (1 - $csGlosa);

    $projecoes[] = [
        'mes'          => $mesLabel,
        'volume_ot'    => $otVolume,
        'valor_ot'     => $otValor,
        'glosa_ot'     => $otGlosa,
        'volume_cs'    => $csVolume,
        'valor_cs'     => $csValor,
        'glosa_cs'     => $csGlosa,
    ];

    $inicio->modify('+1 month');
}

?>

<style>
.forecast-wrapper {
    width: 100%;
    max-width: none;
    margin: 12px 0 56px;
    padding: 0 24px;
    font-family: 'Inter', sans-serif;
}
.forecast-hero {
    background: linear-gradient(120deg, #f6f8ff, #fef1ff);
    border-radius: 24px;
    padding: 24px 28px;
    border: 1px solid rgba(59, 35, 99, .08);
    box-shadow: 0 25px 55px rgba(33, 17, 56, .12);
    margin-bottom: 20px;
}
.forecast-hero h1 {
    font-size: 1.05rem;
    font-weight: 800;
    color: #2e1d49;
    margin-bottom: 6px;
}
.forecast-hero p {
    font-size: .76rem;
    line-height: 1.4;
}
.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 14px;
    margin-bottom: 18px;
}
.forecast-card {
    background: #fff;
    border-radius: 18px;
    padding: 14px 18px;
    border: 1px solid rgba(27, 11, 53, .08);
    box-shadow: 0 12px 25px rgba(18, 9, 29, .08);
}
.forecast-card h3 {
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .08em;
    margin: 0 0 4px;
    color: #7b6c8f;
}
.forecast-card strong {
    font-size: 1.18rem;
    color: #24172f;
}
.forecast-card small {
    font-size: .72rem;
    line-height: 1.35;
}
.forecast-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 8px;
    font-size: .82rem;
    background: #fff;
    border-radius: 20px;
    border: 1px solid rgba(27, 11, 53, .08);
    overflow: hidden;
    box-shadow: 0 20px 45px rgba(31, 17, 46, .12);
}
.forecast-table th,
.forecast-table td {
    padding: 11px 16px;
    border-bottom: 1px solid #f2eff7;
    text-align: left;
}
.forecast-table th {
    text-transform: uppercase;
    font-size: .68rem;
    letter-spacing: .08em;
    color: #85769a;
    background: #faf7ff;
}
.scenario-label {
    font-weight: 600;
    color: #4c2e6c;
}
.badge-pill {
    padding: 2px 10px;
    border-radius: 999px;
    font-size: .68rem;
    font-weight: 600;
}
.badge-optimistic {
    background: rgba(16,185,129,.15);
    color: #0f8f61;
}
.badge-conservative {
    background: rgba(245,158,11,.15);
    color: #a95a00;
}
</style>

<div class="forecast-wrapper">
    <div class="forecast-hero">
        <h1>Previsão de faturamento</h1>
        <p class="text-muted mb-0">Projeções com base nos últimos <?= count($mesesHistorico) ?> meses de faturamento,
            estoque de internações abertas e comportamento médio de glosa.</p>
    </div>

    <div class="cards-grid">
        <div class="forecast-card">
            <h3>Internações abertas</h3>
            <strong><?= number_format($openInternacoes) ?></strong>
            <small class="text-muted d-block mt-1">Pacientes ainda em acompanhamento.</small>
        </div>
        <div class="forecast-card">
            <h3>Contas em andamento</h3>
            <strong><?= number_format($contasAndamento) ?></strong>
            <small class="text-muted d-block mt-1">Capeantes sem encerramento.</small>
        </div>
        <div class="forecast-card">
            <h3>Ticket médio</h3>
            <strong>R$ <?= number_format($ticketMedio, 2, ',', '.') ?></strong>
            <small class="text-muted d-block mt-1">Com base nas contas finalizadas.</small>
        </div>
        <div class="forecast-card">
            <h3>Glosa média</h3>
            <strong><?= number_format($glosaRate * 100, 1, ',', '.') ?>%</strong>
            <small class="text-muted d-block mt-1">1 - (valor final / apresentado).</small>
        </div>
    </div>

    <table class="forecast-table">
        <thead>
            <tr>
                <th>Mês projetado</th>
                <th class="scenario-label">Otimista</th>
                <th>Volume</th>
                <th>Receita líquida</th>
                <th>Glosa (%)</th>
                <th class="scenario-label">Conservador</th>
                <th>Volume</th>
                <th>Receita líquida</th>
                <th>Glosa (%)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($projecoes as $proj): ?>
            <tr>
                <td><strong><?= $proj['mes'] ?></strong></td>
                <td><span class="badge-pill badge-optimistic">Otimista</span></td>
                <td><?= number_format($proj['volume_ot']) ?></td>
                <td>R$ <?= number_format($proj['valor_ot'], 2, ',', '.') ?></td>
                <td><?= number_format($proj['glosa_ot'] * 100, 1, ',', '.') ?>%</td>
                <td><span class="badge-pill badge-conservative">Conservador</span></td>
                <td><?= number_format($proj['volume_cs']) ?></td>
                <td>R$ <?= number_format($proj['valor_cs'], 2, ',', '.') ?></td>
                <td><?= number_format($proj['glosa_cs'] * 100, 1, ',', '.') ?>%</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once("templates/footer.php"); ?>
