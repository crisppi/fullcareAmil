<?php
require_once __DIR__ . '/bi_estrategico_bootstrap.php';

[$joinsInt, $whereInt, $paramsInt] = bi_scope($filters, 'i.data_intern_int', 'i');
[$joinsCapeante, $whereCapeante, $paramsCapeante] = bi_scope($filters, "COALESCE(NULLIF(ca.data_inicial_capeante,'0000-00-00'), NULLIF(ca.data_digit_capeante,'0000-00-00'), NULLIF(ca.data_fech_capeante,'0000-00-00'))", 'i');

$whereVisit = "v.data_visita_vis BETWEEN :data_ini AND :data_fim";
$paramsVisit = [':data_ini' => $filters['data_ini'], ':data_fim' => $filters['data_fim']];
if (!empty($filters['hospital_id'])) {
    $whereVisit .= " AND i.fk_hospital_int = :hospital_id";
    $paramsVisit[':hospital_id'] = (int)$filters['hospital_id'];
}
if (!empty($filters['seguradora_id'])) {
    $whereVisit .= " AND pac.fk_seguradora_pac = :seguradora_id";
    $paramsVisit[':seguradora_id'] = (int)$filters['seguradora_id'];
}

$auditorExpr = "
    CASE
        WHEN NULLIF(v.visita_auditor_prof_med,'') IS NOT NULL THEN CONCAT(COALESCE(u_med.usuario_user, v.visita_auditor_prof_med), ' (Médico)')
        WHEN NULLIF(v.visita_auditor_prof_enf,'') IS NOT NULL THEN CONCAT(COALESCE(u_enf.usuario_user, v.visita_auditor_prof_enf), ' (Enfermagem)')
        WHEN u.usuario_user IS NOT NULL THEN CONCAT(u.usuario_user, ' (Auditor)')
        ELSE 'Sem informações'
    END
";

$auditores = bi_fetch_all($conn, "
    SELECT {$auditorExpr} AS auditor,
           COUNT(*) AS visitas,
           COUNT(DISTINCT DATE(v.data_visita_vis)) AS dias_ativos,
           COUNT(DISTINCT v.fk_internacao_vis) AS casos,
           ROUND(COUNT(*) / NULLIF(COUNT(DISTINCT DATE(v.data_visita_vis)), 0), 2) AS visitas_dia
    FROM tb_visita v
    LEFT JOIN tb_user u ON u.id_usuario = v.fk_usuario_vis
    LEFT JOIN tb_user u_med ON u_med.id_usuario = CAST(NULLIF(v.visita_auditor_prof_med,'') AS UNSIGNED)
    LEFT JOIN tb_user u_enf ON u_enf.id_usuario = CAST(NULLIF(v.visita_auditor_prof_enf,'') AS UNSIGNED)
    LEFT JOIN tb_internacao i ON i.id_internacao = v.fk_internacao_vis
    LEFT JOIN tb_paciente pac ON pac.id_paciente = i.fk_paciente_int
    WHERE {$whereVisit}
    GROUP BY auditor
    ORDER BY visitas_dia DESC, visitas DESC
    LIMIT 15
", $paramsVisit);

$funilInternacoes = (int)(bi_fetch_one($conn, "SELECT COUNT(DISTINCT i.id_internacao) AS total FROM tb_internacao i {$joinsInt} WHERE {$whereInt}", $paramsInt)['total'] ?? 0);
$funilVisitas = (int)(bi_fetch_one($conn, "SELECT COUNT(*) AS total FROM tb_visita v LEFT JOIN tb_internacao i ON i.id_internacao = v.fk_internacao_vis LEFT JOIN tb_paciente pac ON pac.id_paciente = i.fk_paciente_int WHERE {$whereVisit}", $paramsVisit)['total'] ?? 0);
$funilContas = (int)(bi_fetch_one($conn, "SELECT COUNT(DISTINCT ca.id_capeante) AS total FROM tb_capeante ca JOIN tb_internacao i ON i.id_internacao = ca.fk_int_capeante {$joinsCapeante} WHERE {$whereCapeante}", $paramsCapeante)['total'] ?? 0);
$funilFechadas = (int)(bi_fetch_one($conn, "SELECT COUNT(DISTINCT ca.id_capeante) AS total FROM tb_capeante ca JOIN tb_internacao i ON i.id_internacao = ca.fk_int_capeante {$joinsCapeante} WHERE {$whereCapeante} AND COALESCE(ca.encerrado_cap,'n') = 's'", $paramsCapeante)['total'] ?? 0);
$funilAltas = (int)(bi_fetch_one($conn, "SELECT COUNT(DISTINCT al.id_alta) AS total FROM tb_alta al JOIN tb_internacao i ON i.id_internacao = al.fk_id_int_alt {$joinsInt} WHERE al.data_alta_alt BETWEEN :data_ini AND :data_fim" . (!empty($filters['hospital_id']) ? " AND i.fk_hospital_int = :hospital_id" : "") . (!empty($filters['seguradora_id']) ? " AND pac.fk_seguradora_pac = :seguradora_id" : ""), $paramsInt)['total'] ?? 0);

$cycle = bi_fetch_one($conn, "
    SELECT
        AVG(DATEDIFF(v.first_visit, i.data_intern_int)) AS tempo_primeira_visita,
        AVG(DATEDIFF(al.data_alta_alt, i.data_intern_int)) AS tempo_alta,
        AVG(DATEDIFF(ca.first_conta, i.data_intern_int)) AS tempo_conta
    FROM tb_internacao i
    {$joinsInt}
    LEFT JOIN (SELECT fk_internacao_vis, MIN(data_visita_vis) AS first_visit FROM tb_visita GROUP BY fk_internacao_vis) v ON v.fk_internacao_vis = i.id_internacao
    LEFT JOIN (SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt FROM tb_alta GROUP BY fk_id_int_alt) al ON al.fk_id_int_alt = i.id_internacao
    LEFT JOIN (SELECT fk_int_capeante, MIN(COALESCE(NULLIF(data_inicial_capeante,'0000-00-00'), NULLIF(data_digit_capeante,'0000-00-00'), NULLIF(data_fech_capeante,'0000-00-00'))) AS first_conta FROM tb_capeante GROUP BY fk_int_capeante) ca ON ca.fk_int_capeante = i.id_internacao
    WHERE {$whereInt}
", $paramsInt);

$hospitais = bi_fetch_all($conn, "
    SELECT COALESCE(h.nome_hosp, 'Sem hospital') AS hospital,
           COUNT(DISTINCT i.id_internacao) AS internacoes,
           COUNT(DISTINCT v.id_visita) AS visitas,
           COUNT(DISTINCT ca.id_capeante) AS contas,
           SUM(COALESCE(ca.valor_glosa_total,0)) AS glosa
    FROM tb_internacao i
    {$joinsInt}
    LEFT JOIN tb_visita v ON v.fk_internacao_vis = i.id_internacao
    LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao
    WHERE {$whereInt}
    GROUP BY h.id_hospital, h.nome_hosp
    ORDER BY visitas DESC, contas DESC
    LIMIT 15
", $paramsInt);

$totalVisitas = array_sum(array_map(fn($r) => (int)($r['visitas'] ?? 0), $auditores));
$totalDias = array_sum(array_map(fn($r) => (int)($r['dias_ativos'] ?? 0), $auditores));

bi_render_page_start('BI Produtividade', 'Auditores, funil operacional, ciclo e eficiência por hospital.', $BASE_URL);
bi_render_module_nav('produtividade', $BASE_URL);
bi_render_filters($filters, $filterOptions, $BASE_URL . 'bi/produtividade');
bi_render_kpis([
    ['label' => 'Visitas registradas', 'value' => bi_num($funilVisitas), 'hint' => bi_num($funilInternacoes) . ' internações', 'icon' => 'bi-person-check'],
    ['label' => 'Contas no funil', 'value' => bi_num($funilContas), 'hint' => bi_num($funilFechadas) . ' fechadas', 'icon' => 'bi-ui-checks'],
    ['label' => 'Visitas/dia médio', 'value' => $totalDias > 0 ? bi_num($totalVisitas / $totalDias, 2) : '0,00', 'hint' => 'Base por auditor ativo', 'icon' => 'bi-speedometer2'],
    ['label' => 'Tempo até 1ª visita', 'value' => bi_num($cycle['tempo_primeira_visita'] ?? 0, 1) . ' d', 'hint' => 'Média do período', 'icon' => 'bi-clock-history'],
]);
?>

<div class="bi-strategic-grid">
    <?php bi_render_table('Produtividade por auditor', ['Auditor', 'Visitas', 'Dias ativos', 'Casos', 'Visitas/dia'], $auditores, [
        'auditor',
        fn($r) => bi_num($r['visitas'] ?? 0),
        fn($r) => bi_num($r['dias_ativos'] ?? 0),
        fn($r) => bi_num($r['casos'] ?? 0),
        fn($r) => bi_num($r['visitas_dia'] ?? 0, 2),
    ]); ?>

    <div class="bi-panel bi-strategic-chart">
        <h3>Funil da auditoria</h3>
        <div class="bi-chart"><canvas id="chartFunil"></canvas></div>
    </div>
</div>

<div class="bi-strategic-grid">
    <?php bi_render_table('Tempo de ciclo por etapa', ['Etapa', 'Tempo médio'], [
        ['etapa' => 'Internação até primeira visita', 'tempo' => bi_num($cycle['tempo_primeira_visita'] ?? 0, 1) . ' d'],
        ['etapa' => 'Internação até alta', 'tempo' => bi_num($cycle['tempo_alta'] ?? 0, 1) . ' d'],
        ['etapa' => 'Internação até primeira conta', 'tempo' => bi_num($cycle['tempo_conta'] ?? 0, 1) . ' d'],
    ], ['etapa', 'tempo']); ?>

    <?php bi_render_table('Produtividade hospitalar', ['Hospital', 'Internações', 'Visitas', 'Contas', 'Glosa'], $hospitais, [
        'hospital',
        fn($r) => bi_num($r['internacoes'] ?? 0),
        fn($r) => bi_num($r['visitas'] ?? 0),
        fn($r) => bi_num($r['contas'] ?? 0),
        fn($r) => bi_money($r['glosa'] ?? 0),
    ]); ?>
</div>

<script>
new Chart(document.getElementById('chartFunil'), {
    type: 'horizontalBar',
    data: {
        labels: ['Internações', 'Visitas', 'Altas', 'Contas', 'Contas fechadas'],
        datasets: [{ data: [<?= $funilInternacoes ?>, <?= $funilVisitas ?>, <?= $funilAltas ?>, <?= $funilContas ?>, <?= $funilFechadas ?>], backgroundColor: 'rgba(118, 213, 255, 0.78)' }]
    },
    options: { responsive: true, maintainAspectRatio: false, legend: { display: false }, scales: window.biChartScales ? window.biChartScales() : undefined }
});
</script>

</div>
<?php require_once("templates/footer.php"); ?>
