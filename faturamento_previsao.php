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
body {
    background: #f4f6fb;
}
.forecast-wrapper {
    width: 100%;
    max-width: none;
    min-height: calc(100vh - 160px);
    margin: 12px 0 56px;
    padding: 0 24px;
    font-family: 'Inter', sans-serif;
    color: #251636;
}
.forecast-hero {
    background: linear-gradient(120deg, #f4faff 0%, #e8f4fb 58%, #dff2fb 100%);
    border-radius: 16px;
    padding: 20px 24px;
    border: 1px solid rgba(76, 142, 187, .22);
    box-shadow: 0 18px 36px rgba(35, 102, 147, .13);
    margin-bottom: 18px;
}
.forecast-hero h1 {
    font-size: 1.05rem;
    font-weight: 800;
    color: #24384f;
    margin-bottom: 6px;
}
.forecast-hero p {
    font-size: .76rem;
    line-height: 1.4;
    color: #5d6f82 !important;
}
.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 14px;
    margin-bottom: 18px;
}
.forecast-card {
    position: relative;
    background: #fff;
    border-radius: 12px;
    padding: 16px 18px 15px;
    border: 1px solid rgba(76, 142, 187, .22);
    box-shadow: 0 12px 28px rgba(35, 102, 147, .11);
    overflow: hidden;
}
.forecast-card::before {
    content: "";
    position: absolute;
    inset: 0 0 auto;
    height: 4px;
    background: linear-gradient(90deg, #2f6f9f, #5eb4d8);
}
.forecast-card h3 {
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .06em;
    margin: 0 0 7px;
    color: #2f6f9f;
    font-weight: 800;
}
.forecast-card strong {
    font-size: 1.3rem;
    line-height: 1.1;
    color: #20102f;
}
.forecast-card small {
    font-size: .72rem;
    line-height: 1.35;
    color: #5f6876 !important;
}
.forecast-table-wrap {
    margin-top: 8px;
    border-radius: 14px;
    border: 1px solid rgba(76, 142, 187, .22);
    background: #fff;
    box-shadow: 0 14px 30px rgba(35, 102, 147, .11);
    overflow-x: auto;
    overflow-y: hidden;
}
.forecast-table {
    width: 100%;
    min-width: 980px;
    border-collapse: collapse;
    font-size: .82rem;
    background: #fff;
    overflow: hidden;
}
.forecast-table th,
.forecast-table td {
    padding: 12px 16px;
    border-bottom: 1px solid #e0edf5;
    text-align: left;
    color: #3f3b46;
}
.forecast-table th {
    text-transform: uppercase;
    font-size: .68rem;
    letter-spacing: .06em;
    color: #ffffff;
    background: #2f6f9f;
    font-weight: 800;
}
.forecast-table tbody tr:nth-child(even) {
    background: #fbfcff;
}
.forecast-table tbody tr:hover {
    background: #f4faff;
}
.forecast-table tbody tr:last-child td {
    border-bottom: 0;
}
.scenario-label {
    font-weight: 800;
    color: #24384f !important;
}
.badge-pill {
    display: inline-flex;
    align-items: center;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: .68rem;
    font-weight: 800;
    border: 1px solid transparent;
}
.badge-optimistic {
    background: #dff7ec;
    color: #0b6f4b;
    border-color: #b7ebd2;
}
.badge-conservative {
    background: #fff1d9;
    color: #8a4b00;
    border-color: #ffdba3;
}
@media (max-width: 768px) {
    .forecast-wrapper {
        padding: 0 14px;
    }

    .forecast-hero {
        padding: 16px 18px;
    }
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

    <div class="forecast-table-wrap">
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
</div>

<?php require_once("templates/footer.php"); ?>
