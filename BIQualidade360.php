<?php
require_once __DIR__ . '/bi_estrategico_bootstrap.php';

[$joins, $where, $params] = bi_scope($filters, 'i.data_intern_int', 'i');

$summary = bi_fetch_one($conn, "
    SELECT
        COUNT(DISTINCT i.id_internacao) AS internacoes,
        COUNT(DISTINCT CASE WHEN g.evento_adverso_ges = 's' THEN i.id_internacao END) AS eventos,
        COUNT(DISTINCT CASE WHEN LOWER(COALESCE(al.tipo_alta_alt,'')) LIKE '%obito%' THEN i.id_internacao END) AS obitos,
        COUNT(DISTINCT ut.fk_internacao_uti) AS uti,
        AVG(GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1)) AS permanencia
    FROM tb_internacao i
    {$joins}
    LEFT JOIN tb_gestao g ON g.fk_internacao_ges = i.id_internacao
    LEFT JOIN (SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt, MAX(tipo_alta_alt) AS tipo_alta_alt FROM tb_alta GROUP BY fk_id_int_alt) al ON al.fk_id_int_alt = i.id_internacao
    LEFT JOIN (SELECT DISTINCT fk_internacao_uti FROM tb_uti) ut ON ut.fk_internacao_uti = i.id_internacao
    WHERE {$where}
", $params);

$internacoes = (int)($summary['internacoes'] ?? 0);
$eventos = (int)($summary['eventos'] ?? 0);
$obitos = (int)($summary['obitos'] ?? 0);
$uti = (int)($summary['uti'] ?? 0);

$hospitalScore = bi_fetch_all($conn, "
    SELECT COALESCE(h.nome_hosp, 'Sem hospital') AS hospital,
           COUNT(DISTINCT i.id_internacao) AS internacoes,
           COUNT(DISTINCT CASE WHEN g.evento_adverso_ges = 's' THEN i.id_internacao END) AS eventos,
           COUNT(DISTINCT CASE WHEN LOWER(COALESCE(al.tipo_alta_alt,'')) LIKE '%obito%' THEN i.id_internacao END) AS obitos,
           COUNT(DISTINCT ut.fk_internacao_uti) AS uti,
           AVG(GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1)) AS permanencia
    FROM tb_internacao i
    {$joins}
    LEFT JOIN tb_gestao g ON g.fk_internacao_ges = i.id_internacao
    LEFT JOIN (SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt, MAX(tipo_alta_alt) AS tipo_alta_alt FROM tb_alta GROUP BY fk_id_int_alt) al ON al.fk_id_int_alt = i.id_internacao
    LEFT JOIN (SELECT DISTINCT fk_internacao_uti FROM tb_uti) ut ON ut.fk_internacao_uti = i.id_internacao
    WHERE {$where}
    GROUP BY h.id_hospital, h.nome_hosp
    ORDER BY internacoes DESC
    LIMIT 15
", $params);

foreach ($hospitalScore as &$row) {
    $base = max(1, (int)($row['internacoes'] ?? 0));
    $eventRate = ((int)($row['eventos'] ?? 0) / $base) * 100;
    $deathRate = ((int)($row['obitos'] ?? 0) / $base) * 100;
    $utiRate = ((int)($row['uti'] ?? 0) / $base) * 100;
    $stay = (float)($row['permanencia'] ?? 0);
    $row['score'] = max(0, round(100 - ($eventRate * 2.2) - ($deathRate * 2.8) - ($utiRate * 0.25) - max(0, $stay - 5) * 2, 1));
}
unset($row);
usort($hospitalScore, fn($a, $b) => ($a['score'] ?? 0) <=> ($b['score'] ?? 0));

$eventTypes = bi_fetch_all($conn, "
    SELECT COALESCE(NULLIF(g.tipo_evento_adverso_gest,''), 'Não classificado') AS tipo,
           COUNT(DISTINCT i.id_internacao) AS total
    FROM tb_internacao i
    {$joins}
    JOIN tb_gestao g ON g.fk_internacao_ges = i.id_internacao
    WHERE {$where} AND g.evento_adverso_ges = 's'
    GROUP BY tipo
    ORDER BY total DESC
    LIMIT 12
", $params);

$docWhere = "v.data_visita_vis BETWEEN :data_ini AND :data_fim";
$docParams = [':data_ini' => $filters['data_ini'], ':data_fim' => $filters['data_fim']];
if (!empty($filters['hospital_id'])) {
    $docWhere .= " AND i.fk_hospital_int = :hospital_id";
    $docParams[':hospital_id'] = (int)$filters['hospital_id'];
}
if (!empty($filters['seguradora_id'])) {
    $docWhere .= " AND pac.fk_seguradora_pac = :seguradora_id";
    $docParams[':seguradora_id'] = (int)$filters['seguradora_id'];
}

$docRows = bi_fetch_all($conn, "
    SELECT COALESCE(h.nome_hosp, 'Sem hospital') AS hospital,
           COUNT(*) AS visitas,
           SUM(CASE WHEN COALESCE(v.rel_visita_vis,'') <> '' AND COALESCE(v.acoes_int_vis,'') <> '' AND COALESCE(v.programacao_enf,'') <> '' THEN 1 ELSE 0 END) AS completas
    FROM tb_visita v
    JOIN tb_internacao i ON i.id_internacao = v.fk_internacao_vis
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    LEFT JOIN tb_paciente pac ON pac.id_paciente = i.fk_paciente_int
    WHERE {$docWhere}
    GROUP BY h.id_hospital, h.nome_hosp
    ORDER BY completas DESC
    LIMIT 12
", $docParams);

bi_render_page_start('BI Qualidade', 'Qualidade hospitalar, eventos, óbitos, UTI, documentação e complicações.', $BASE_URL);
bi_render_module_nav('qualidade', $BASE_URL);
bi_render_filters($filters, $filterOptions, $BASE_URL . 'bi/qualidade-360');
bi_render_kpis([
    ['label' => 'Internações avaliadas', 'value' => bi_num($internacoes), 'hint' => 'Base do período', 'icon' => 'bi-hospital'],
    ['label' => 'Eventos adversos', 'value' => bi_num($eventos), 'hint' => $internacoes > 0 ? bi_pct(($eventos / $internacoes) * 100) . ' das internações' : 'Sem base', 'icon' => 'bi-exclamation-octagon'],
    ['label' => 'Óbitos', 'value' => bi_num($obitos), 'hint' => $internacoes > 0 ? bi_pct(($obitos / $internacoes) * 100) . ' das internações' : 'Sem base', 'icon' => 'bi-heart-pulse'],
    ['label' => 'Internação UTI', 'value' => bi_num($uti), 'hint' => $internacoes > 0 ? bi_pct(($uti / $internacoes) * 100) . ' da base' : 'Sem base', 'icon' => 'bi-activity'],
]);
?>

<div class="bi-strategic-grid">
    <?php bi_render_table('Qualidade hospitalar 360', ['Hospital', 'Score', 'Internações', 'Eventos', 'Óbitos', 'UTI', 'Permanência'], $hospitalScore, [
        'hospital',
        fn($r) => bi_num($r['score'] ?? 0, 1),
        fn($r) => bi_num($r['internacoes'] ?? 0),
        fn($r) => bi_num($r['eventos'] ?? 0),
        fn($r) => bi_num($r['obitos'] ?? 0),
        fn($r) => bi_num($r['uti'] ?? 0),
        fn($r) => bi_num($r['permanencia'] ?? 0, 1) . ' d',
    ]); ?>

    <?php bi_render_table('Eventos adversos analítico', ['Tipo', 'Casos'], $eventTypes, [
        'tipo',
        fn($r) => bi_num($r['total'] ?? 0),
    ]); ?>
</div>

<div class="bi-strategic-grid">
    <?php bi_render_table('Qualidade documental por hospital', ['Hospital', 'Visitas', 'Completas', 'Completude'], $docRows, [
        'hospital',
        fn($r) => bi_num($r['visitas'] ?? 0),
        fn($r) => bi_num($r['completas'] ?? 0),
        fn($r) => ((int)($r['visitas'] ?? 0) > 0 ? bi_pct(((int)$r['completas'] / (int)$r['visitas']) * 100) : '0,0%'),
    ]); ?>

    <div class="bi-panel bi-strategic-chart">
        <h3>Hospitais críticos por score</h3>
        <div class="bi-chart"><canvas id="chartQualidadeScore"></canvas></div>
    </div>
</div>

<script>
new Chart(document.getElementById('chartQualidadeScore'), {
    type: 'horizontalBar',
    data: {
        labels: <?= json_encode(array_column(array_slice($hospitalScore, 0, 10), 'hospital')) ?>,
        datasets: [{ data: <?= json_encode(array_map('floatval', array_column(array_slice($hospitalScore, 0, 10), 'score'))) ?>, backgroundColor: 'rgba(241, 132, 181, 0.78)' }]
    },
    options: { responsive: true, maintainAspectRatio: false, legend: { display: false }, scales: window.biChartScales ? window.biChartScales() : undefined }
});
</script>

</div>
<?php require_once("templates/footer.php"); ?>
