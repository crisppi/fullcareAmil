<?php
include_once("check_logado.php");
require_once("templates/header.php");

if (!isset($conn) || !($conn instanceof PDO)) {
    die("Conexao invalida.");
}

if (!function_exists('e')) {
    function e($v)
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('bi_money')) {
    function bi_money($value): string
    {
        return 'R$ ' . number_format((float)$value, 2, ',', '.');
    }
}

if (!function_exists('bi_num')) {
    function bi_num($value, int $decimals = 0): string
    {
        return number_format((float)$value, $decimals, ',', '.');
    }
}

if (!function_exists('bi_pct')) {
    function bi_pct($value, int $decimals = 1): string
    {
        return number_format((float)$value, $decimals, ',', '.') . '%';
    }
}

if (!function_exists('bi_fetch_all')) {
    function bi_fetch_all(PDO $conn, string $sql, array $params = []): array
    {
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('bi_fetch_one')) {
    function bi_fetch_one(PDO $conn, string $sql, array $params = []): array
    {
        $rows = bi_fetch_all($conn, $sql, $params);
        return $rows[0] ?? [];
    }
}

if (!function_exists('bi_filter_options')) {
    function bi_filter_options(PDO $conn): array
    {
        return [
            'hospitais' => $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'seguradoras' => $conn->query("SELECT id_seguradora, seguradora_seg FROM tb_seguradora ORDER BY seguradora_seg")->fetchAll(PDO::FETCH_ASSOC) ?: [],
        ];
    }
}

if (!function_exists('bi_read_filters')) {
    function bi_read_filters(): array
    {
        $dataIni = filter_input(INPUT_GET, 'data_ini') ?: date('Y-m-01', strtotime('-11 months'));
        $dataFim = filter_input(INPUT_GET, 'data_fim') ?: date('Y-m-d');
        return [
            'data_ini' => $dataIni,
            'data_fim' => $dataFim,
            'hospital_id' => filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null,
            'seguradora_id' => filter_input(INPUT_GET, 'seguradora_id', FILTER_VALIDATE_INT) ?: null,
        ];
    }
}

if (!function_exists('bi_scope')) {
    function bi_scope(array $filters, string $dateExpr, string $internAlias = 'i'): array
    {
        $joins = "
            LEFT JOIN tb_hospital h ON h.id_hospital = {$internAlias}.fk_hospital_int
            LEFT JOIN tb_paciente pac ON pac.id_paciente = {$internAlias}.fk_paciente_int
            LEFT JOIN tb_seguradora seg ON seg.id_seguradora = pac.fk_seguradora_pac
        ";
        $where = "{$dateExpr} BETWEEN :data_ini AND :data_fim";
        $params = [
            ':data_ini' => $filters['data_ini'],
            ':data_fim' => $filters['data_fim'],
        ];
        if (!empty($filters['hospital_id'])) {
            $where .= " AND {$internAlias}.fk_hospital_int = :hospital_id";
            $params[':hospital_id'] = (int)$filters['hospital_id'];
        }
        if (!empty($filters['seguradora_id'])) {
            $where .= " AND pac.fk_seguradora_pac = :seguradora_id";
            $params[':seguradora_id'] = (int)$filters['seguradora_id'];
        }
        return [$joins, $where, $params];
    }
}

if (!function_exists('bi_render_page_start')) {
    function bi_render_page_start(string $title, string $subtitle, string $baseUrl): void
    {
        $biCssVersion = @filemtime(__DIR__ . '/../../css/bi.css') ?: '20260509-filter-icons';
        $biStrategicCssVersion = @filemtime(__DIR__ . '/../../css/bi-estrategico.css') ?: '20260523-align2';
        ?>
        <link rel="stylesheet" href="<?= $baseUrl ?>css/bi.css?v=<?= $biCssVersion ?>">
        <link rel="stylesheet" href="<?= $baseUrl ?>css/bi-estrategico.css?v=<?= $biStrategicCssVersion ?>">
        <script src="diversos/chartjs/Chart.min.js"></script>
        <script src="<?= $baseUrl ?>js/bi.js?v=20260516-rounded-bars"></script>
        <script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>
        <div class="bi-wrapper bi-theme bi-strategic-page">
            <div class="bi-header">
                <div>
                    <h1 class="bi-title"><?= e($title) ?></h1>
                    <div class="bi-strategic-subtitle"><?= e($subtitle) ?></div>
                </div>
                <div class="bi-header-actions">
                    <a class="bi-nav-icon" href="<?= $baseUrl ?>bi/navegacao" title="Navegação BI">
                        <i class="bi bi-grid-3x3-gap"></i>
                    </a>
                </div>
            </div>
        <?php
    }
}

if (!function_exists('bi_render_filters')) {
    function bi_render_filters(array $filters, array $options, string $clearHref): void
    {
        ?>
        <form class="bi-panel bi-filters bi-strategic-filters" method="get">
            <div class="bi-filter">
                <label>Data início</label>
                <input type="date" name="data_ini" value="<?= e($filters['data_ini']) ?>">
            </div>
            <div class="bi-filter">
                <label>Data fim</label>
                <input type="date" name="data_fim" value="<?= e($filters['data_fim']) ?>">
            </div>
            <div class="bi-filter">
                <label>Hospital</label>
                <select name="hospital_id">
                    <option value="">Todos</option>
                    <?php foreach ($options['hospitais'] as $h): ?>
                        <option value="<?= (int)$h['id_hospital'] ?>" <?= (int)$filters['hospital_id'] === (int)$h['id_hospital'] ? 'selected' : '' ?>>
                            <?= e($h['nome_hosp']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="bi-filter">
                <label>Seguradora</label>
                <select name="seguradora_id">
                    <option value="">Todas</option>
                    <?php foreach ($options['seguradoras'] as $s): ?>
                        <option value="<?= (int)$s['id_seguradora'] ?>" <?= (int)$filters['seguradora_id'] === (int)$s['id_seguradora'] ? 'selected' : '' ?>>
                            <?= e($s['seguradora_seg']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="bi-actions">
                <button class="bi-btn" type="submit">Aplicar</button>
                <a class="bi-btn bi-btn-secondary" href="<?= e($clearHref) ?>">Limpar</a>
            </div>
        </form>
        <?php
    }
}

if (!function_exists('bi_render_module_nav')) {
    function bi_render_module_nav(string $current, string $baseUrl): void
    {
        $items = [
            'resultados' => ['BI Resultados', 'bi/resultados'],
            'produtividade' => ['BI Produtividade', 'bi/produtividade'],
            'qualidade' => ['BI Qualidade', 'bi/qualidade-360'],
            'preditivo' => ['BI Preditivo', 'bi/preditivo'],
        ];
        ?>
        <div class="bi-strategic-nav">
            <?php foreach ($items as $key => $item): ?>
                <a class="<?= $current === $key ? 'is-active' : '' ?>" href="<?= $baseUrl . $item[1] ?>"><?= e($item[0]) ?></a>
            <?php endforeach; ?>
        </div>
        <?php
    }
}

if (!function_exists('bi_render_kpis')) {
    function bi_render_kpis(array $kpis): void
    {
        ?>
        <div class="bi-kpis kpi-dashboard-v2 bi-strategic-kpis">
            <?php foreach ($kpis as $idx => $kpi): ?>
                <div class="bi-kpi kpi-card-v2 kpi-card-v2-<?= ($idx % 4) + 1 ?>">
                    <div class="kpi-card-v2-head">
                        <span class="kpi-card-v2-icon"><i class="bi <?= e($kpi['icon'] ?? 'bi-bar-chart') ?>"></i></span>
                        <small><?= e($kpi['label']) ?></small>
                    </div>
                    <strong><?= e($kpi['value']) ?></strong>
                    <span class="kpi-trend kpi-trend-neutral"><?= e($kpi['hint'] ?? '') ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
}

if (!function_exists('bi_render_table')) {
    function bi_render_table(string $title, array $headers, array $rows, array $keys): void
    {
        ?>
        <div class="bi-panel bi-strategic-table">
            <h3><?= e($title) ?></h3>
            <div class="table-responsive">
                <table class="bi-table">
                    <thead><tr><?php foreach ($headers as $h): ?><th><?= e($h) ?></th><?php endforeach; ?></tr></thead>
                    <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="<?= count($headers) ?>">Sem dados no período.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <?php foreach ($keys as $key): ?>
                                    <td><?= e(is_callable($key) ? $key($row) : ($row[$key] ?? '')) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
}

$filters = bi_read_filters();
$filterOptions = bi_filter_options($conn);
?>
