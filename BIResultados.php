<?php
require_once __DIR__ . '/bi_estrategico_bootstrap.php';

[$joins, $where, $params] = bi_scope($filters, "COALESCE(NULLIF(ca.data_inicial_capeante,'0000-00-00'), NULLIF(ca.data_digit_capeante,'0000-00-00'), NULLIF(ca.data_fech_capeante,'0000-00-00'))", 'i');

$summary = bi_fetch_one($conn, "
    SELECT
        COUNT(DISTINCT i.id_internacao) AS internacoes,
        COUNT(DISTINCT ca.id_capeante) AS contas,
        SUM(COALESCE(ca.valor_apresentado_capeante, 0)) AS apresentado,
        SUM(COALESCE(ca.valor_glosa_total, 0)) AS glosa,
        SUM(COALESCE(ca.valor_final_capeante, 0)) AS final
    FROM tb_capeante ca
    JOIN tb_internacao i ON i.id_internacao = ca.fk_int_capeante
    {$joins}
    WHERE {$where}
", $params);

$apresentado = (float)($summary['apresentado'] ?? 0);
$glosa = (float)($summary['glosa'] ?? 0);
$final = (float)($summary['final'] ?? 0);
$contas = (int)($summary['contas'] ?? 0);
$internacoes = (int)($summary['internacoes'] ?? 0);
$saving = max(0, $apresentado - $final);

$byHospital = bi_fetch_all($conn, "
    SELECT COALESCE(h.nome_hosp, 'Sem hospital') AS label,
           COUNT(DISTINCT i.id_internacao) AS casos,
           COUNT(DISTINCT ca.id_capeante) AS contas,
           SUM(COALESCE(ca.valor_apresentado_capeante,0)) AS apresentado,
           SUM(COALESCE(ca.valor_glosa_total,0)) AS glosa,
           SUM(COALESCE(ca.valor_final_capeante,0)) AS final
    FROM tb_capeante ca
    JOIN tb_internacao i ON i.id_internacao = ca.fk_int_capeante
    {$joins}
    WHERE {$where}
    GROUP BY h.id_hospital, h.nome_hosp
    ORDER BY final DESC
    LIMIT 12
", $params);

$byPathology = bi_fetch_all($conn, "
    SELECT COALESCE(pat.patologia_pat, 'Sem patologia') AS label,
           COUNT(DISTINCT i.id_internacao) AS casos,
           SUM(COALESCE(ca.valor_final_capeante,0)) AS final,
           AVG(GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1)) AS permanencia
    FROM tb_capeante ca
    JOIN tb_internacao i ON i.id_internacao = ca.fk_int_capeante
    LEFT JOIN tb_patologia pat ON pat.id_patologia = i.fk_patologia_int
    LEFT JOIN (SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt FROM tb_alta GROUP BY fk_id_int_alt) al ON al.fk_id_int_alt = i.id_internacao
    {$joins}
    WHERE {$where}
    GROUP BY pat.id_patologia, pat.patologia_pat
    ORDER BY final DESC
    LIMIT 12
", $params);

$bySeguradora = bi_fetch_all($conn, "
    SELECT COALESCE(seg.seguradora_seg, 'Sem seguradora') AS label,
           COUNT(DISTINCT i.id_internacao) AS casos,
           COUNT(DISTINCT ca.id_capeante) AS contas,
           SUM(COALESCE(ca.valor_apresentado_capeante,0)) AS apresentado,
           SUM(COALESCE(ca.valor_final_capeante,0)) AS final
    FROM tb_capeante ca
    JOIN tb_internacao i ON i.id_internacao = ca.fk_int_capeante
    {$joins}
    WHERE {$where}
    GROUP BY seg.id_seguradora, seg.seguradora_seg
    ORDER BY final DESC
    LIMIT 12
", $params);

$monthly = bi_fetch_all($conn, "
    SELECT DATE_FORMAT(COALESCE(NULLIF(ca.data_inicial_capeante,'0000-00-00'), NULLIF(ca.data_digit_capeante,'0000-00-00'), NULLIF(ca.data_fech_capeante,'0000-00-00')), '%Y-%m') AS mes,
           SUM(COALESCE(ca.valor_apresentado_capeante,0)) AS apresentado,
           SUM(COALESCE(ca.valor_final_capeante,0)) AS final,
           SUM(COALESCE(ca.valor_glosa_total,0)) AS glosa
    FROM tb_capeante ca
    JOIN tb_internacao i ON i.id_internacao = ca.fk_int_capeante
    {$joins}
    WHERE {$where}
    GROUP BY mes
    ORDER BY mes
", $params);

bi_render_page_start('BI Resultados', 'Resultado financeiro, hospital, patologia, operadora e fechamento mensal.', $BASE_URL);
bi_render_module_nav('resultados', $BASE_URL);
bi_render_filters($filters, $filterOptions, $BASE_URL . 'bi/resultados');
bi_render_kpis([
    ['label' => 'Valor apresentado', 'value' => bi_money($apresentado), 'hint' => bi_num($contas) . ' contas', 'icon' => 'bi-receipt'],
    ['label' => 'Valor final', 'value' => bi_money($final), 'hint' => bi_num($internacoes) . ' internações', 'icon' => 'bi-wallet2'],
    ['label' => 'Glosa registrada', 'value' => bi_money($glosa), 'hint' => $apresentado > 0 ? bi_pct(($glosa / $apresentado) * 100) . ' do apresentado' : 'Sem base', 'icon' => 'bi-percent'],
    ['label' => 'Saving potencial', 'value' => bi_money($saving), 'hint' => $apresentado > 0 ? bi_pct(($saving / $apresentado) * 100) . ' de redução' : 'Sem base', 'icon' => 'bi-piggy-bank'],
]);
?>

<div class="bi-strategic-grid">
    <?php bi_render_table('Resultado por hospital', ['Hospital', 'Casos', 'Contas', 'Apresentado', 'Glosa', 'Final'], $byHospital, [
        'label',
        fn($r) => bi_num($r['casos'] ?? 0),
        fn($r) => bi_num($r['contas'] ?? 0),
        fn($r) => bi_money($r['apresentado'] ?? 0),
        fn($r) => bi_money($r['glosa'] ?? 0),
        fn($r) => bi_money($r['final'] ?? 0),
    ]); ?>

    <?php bi_render_table('Resultado por patologia', ['Patologia', 'Casos', 'Custo final', 'Permanência média'], $byPathology, [
        'label',
        fn($r) => bi_num($r['casos'] ?? 0),
        fn($r) => bi_money($r['final'] ?? 0),
        fn($r) => bi_num($r['permanencia'] ?? 0, 1) . ' d',
    ]); ?>
</div>

<div class="bi-strategic-grid">
    <?php bi_render_table('Resultado por seguradora', ['Seguradora', 'Casos', 'Contas', 'Apresentado', 'Final'], $bySeguradora, [
        'label',
        fn($r) => bi_num($r['casos'] ?? 0),
        fn($r) => bi_num($r['contas'] ?? 0),
        fn($r) => bi_money($r['apresentado'] ?? 0),
        fn($r) => bi_money($r['final'] ?? 0),
    ]); ?>

    <div class="bi-panel bi-strategic-chart">
        <h3>Executivo mensal</h3>
        <div class="bi-chart"><canvas id="chartResultadosMensal"></canvas></div>
    </div>
</div>

<script>
new Chart(document.getElementById('chartResultadosMensal'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($monthly, 'mes')) ?>,
        datasets: [
            { label: 'Apresentado', data: <?= json_encode(array_map('floatval', array_column($monthly, 'apresentado'))) ?>, backgroundColor: 'rgba(118, 213, 255, 0.76)' },
            { label: 'Final', data: <?= json_encode(array_map('floatval', array_column($monthly, 'final'))) ?>, backgroundColor: 'rgba(121, 230, 196, 0.76)' },
            { label: 'Glosa', data: <?= json_encode(array_map('floatval', array_column($monthly, 'glosa'))) ?>, backgroundColor: 'rgba(241, 132, 181, 0.76)' }
        ]
    },
    options: { responsive: true, maintainAspectRatio: false, scales: window.biChartScales ? window.biChartScales() : undefined }
});
</script>

</div>
<?php require_once("templates/footer.php"); ?>
