<?php
require_once __DIR__ . '/bi_estrategico_bootstrap.php';

[$joins, $where, $params] = bi_scope($filters, 'i.data_intern_int', 'i');

$openWhere = "{$where} AND COALESCE(i.internado_int, 's') = 's'";

$riskRows = bi_fetch_all($conn, "
    SELECT i.id_internacao,
           COALESCE(pac.nome_pac, 'Sem paciente') AS paciente,
           COALESCE(h.nome_hosp, 'Sem hospital') AS hospital,
           COALESCE(pat.patologia_pat, 'Sem patologia') AS patologia,
           GREATEST(1, DATEDIFF(CURDATE(), i.data_intern_int) + 1) AS dias,
           SUM(COALESCE(ca.valor_final_capeante,0)) AS custo,
           COUNT(DISTINCT ut.fk_internacao_uti) AS uti,
           COUNT(DISTINCT CASE WHEN g.evento_adverso_ges = 's' THEN i.id_internacao END) AS evento
    FROM tb_internacao i
    {$joins}
    LEFT JOIN tb_patologia pat ON pat.id_patologia = i.fk_patologia_int
    LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao
    LEFT JOIN (SELECT DISTINCT fk_internacao_uti FROM tb_uti) ut ON ut.fk_internacao_uti = i.id_internacao
    LEFT JOIN tb_gestao g ON g.fk_internacao_ges = i.id_internacao
    WHERE {$openWhere}
    GROUP BY i.id_internacao, pac.nome_pac, h.nome_hosp, pat.patologia_pat
    ORDER BY custo DESC, dias DESC
    LIMIT 40
", $params);

foreach ($riskRows as &$row) {
    $score = min(45, ((float)($row['custo'] ?? 0) / 25000) * 18)
        + min(30, max(0, ((int)($row['dias'] ?? 0) - 5) * 2.2))
        + ((int)($row['uti'] ?? 0) > 0 ? 14 : 0)
        + ((int)($row['evento'] ?? 0) > 0 ? 11 : 0);
    $row['score'] = min(99, round($score));
}
unset($row);
usort($riskRows, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));
$altoCusto = array_slice($riskRows, 0, 12);
$permanencia = array_values(array_filter($riskRows, fn($r) => (int)($r['dias'] ?? 0) >= 8));
$permanencia = array_slice($permanencia, 0, 12);
$desospitalizacao = array_values(array_filter($riskRows, fn($r) => (int)($r['dias'] ?? 0) >= 10 && (int)($r['uti'] ?? 0) === 0));
$desospitalizacao = array_slice($desospitalizacao, 0, 12);

[$joinsCapeante, $whereCapeante, $paramsCapeante] = bi_scope($filters, "COALESCE(NULLIF(ca.data_inicial_capeante,'0000-00-00'), NULLIF(ca.data_digit_capeante,'0000-00-00'), NULLIF(ca.data_fech_capeante,'0000-00-00'))", 'i');
$glosaRows = bi_fetch_all($conn, "
    SELECT ca.id_capeante,
           COALESCE(pac.nome_pac, 'Sem paciente') AS paciente,
           COALESCE(h.nome_hosp, 'Sem hospital') AS hospital,
           COALESCE(ca.valor_apresentado_capeante,0) AS apresentado,
           COALESCE(ca.valor_glosa_total,0) AS glosa,
           COALESCE(ca.valor_final_capeante,0) AS final
    FROM tb_capeante ca
    JOIN tb_internacao i ON i.id_internacao = ca.fk_int_capeante
    {$joinsCapeante}
    WHERE {$whereCapeante}
    ORDER BY ca.valor_apresentado_capeante DESC
    LIMIT 40
", $paramsCapeante);
foreach ($glosaRows as &$row) {
    $ap = max(1, (float)($row['apresentado'] ?? 0));
    $rate = ((float)($row['glosa'] ?? 0) / $ap) * 100;
    $row['score'] = min(99, round(($rate * 1.15) + min(30, $ap / 20000)));
}
unset($row);
usort($glosaRows, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));
$glosaRows = array_slice($glosaRows, 0, 12);

$readmWhere = "al.data_alta_alt BETWEEN :data_ini AND :data_fim";
$readmParams = [':data_ini' => $filters['data_ini'], ':data_fim' => $filters['data_fim']];
if (!empty($filters['hospital_id'])) {
    $readmWhere .= " AND i.fk_hospital_int = :hospital_id";
    $readmParams[':hospital_id'] = (int)$filters['hospital_id'];
}
if (!empty($filters['seguradora_id'])) {
    $readmWhere .= " AND pac.fk_seguradora_pac = :seguradora_id";
    $readmParams[':seguradora_id'] = (int)$filters['seguradora_id'];
}

$readmRows = bi_fetch_all($conn, "
    SELECT COALESCE(pac.nome_pac, 'Sem paciente') AS paciente,
           COALESCE(h.nome_hosp, 'Sem hospital') AS hospital,
           COALESCE(pat.patologia_pat, 'Sem patologia') AS patologia,
           MAX(al.data_alta_alt) AS ultima_alta,
           COUNT(DISTINCT i.id_internacao) AS historico,
           MAX(GREATEST(1, DATEDIFF(al.data_alta_alt, i.data_intern_int) + 1)) AS maior_permanencia,
           COUNT(DISTINCT ut.fk_internacao_uti) AS teve_uti
    FROM tb_internacao i
    LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
    LEFT JOIN tb_paciente pac ON pac.id_paciente = i.fk_paciente_int
    JOIN tb_alta al ON al.fk_id_int_alt = i.id_internacao
    LEFT JOIN tb_patologia pat ON pat.id_patologia = i.fk_patologia_int
    LEFT JOIN (SELECT DISTINCT fk_internacao_uti FROM tb_uti) ut ON ut.fk_internacao_uti = i.id_internacao
    WHERE {$readmWhere}
    GROUP BY pac.id_paciente, pac.nome_pac, h.nome_hosp, pat.patologia_pat
    ORDER BY ultima_alta DESC
    LIMIT 40
", $readmParams);
foreach ($readmRows as &$row) {
    $score = min(35, ((int)($row['historico'] ?? 0) - 1) * 12)
        + min(35, max(0, ((int)($row['maior_permanencia'] ?? 0) - 5) * 2.5))
        + ((int)($row['teve_uti'] ?? 0) > 0 ? 18 : 0)
        + 10;
    $row['score'] = min(99, round($score));
}
unset($row);
usort($readmRows, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));
$readmRows = array_slice($readmRows, 0, 12);

$backlogRows = bi_fetch_all($conn, "
    SELECT COALESCE(h.nome_hosp, 'Sem hospital') AS hospital,
           COUNT(DISTINCT i.id_internacao) AS internados,
           COUNT(DISTINCT v.id_visita) AS visitas,
           COUNT(DISTINCT ca.id_capeante) AS contas,
           SUM(CASE WHEN COALESCE(ca.encerrado_cap,'n') <> 's' THEN 1 ELSE 0 END) AS contas_abertas
    FROM tb_internacao i
    {$joins}
    LEFT JOIN tb_visita v ON v.fk_internacao_vis = i.id_internacao
    LEFT JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao
    WHERE {$openWhere}
    GROUP BY h.id_hospital, h.nome_hosp
    ORDER BY contas_abertas DESC, internados DESC
    LIMIT 12
", $params);

$critical = count(array_filter($riskRows, fn($r) => (int)($r['score'] ?? 0) >= 70));
$avgScore = $riskRows ? array_sum(array_map(fn($r) => (int)$r['score'], $riskRows)) / count($riskRows) : 0;

$riskCell = function ($row): string {
    return (string)(int)($row['score'] ?? 0);
};

bi_render_page_start('BI Preditivo', 'Scores explicáveis para alto custo, permanência, glosa, readmissão, desospitalização e backlog.', $BASE_URL);
bi_render_module_nav('preditivo', $BASE_URL);
bi_render_filters($filters, $filterOptions, $BASE_URL . 'bi/preditivo');
bi_render_kpis([
    ['label' => 'Casos ativos avaliados', 'value' => bi_num(count($riskRows)), 'hint' => bi_num($critical) . ' críticos', 'icon' => 'bi-bullseye'],
    ['label' => 'Score médio de risco', 'value' => bi_num($avgScore, 1), 'hint' => 'Escala 0 a 99', 'icon' => 'bi-speedometer'],
    ['label' => 'Risco permanência', 'value' => bi_num(count($permanencia)), 'hint' => 'Casos com 8+ dias', 'icon' => 'bi-hourglass-split'],
    ['label' => 'Oportunidade desospitalização', 'value' => bi_num(count($desospitalizacao)), 'hint' => '10+ dias sem UTI', 'icon' => 'bi-house-check'],
]);
?>

<div class="bi-strategic-grid">
    <?php bi_render_table('Risco de alto custo em aberto', ['Score', 'Paciente', 'Hospital', 'Patologia', 'Dias', 'Custo'], $altoCusto, [
        $riskCell, 'paciente', 'hospital', 'patologia', fn($r) => bi_num($r['dias'] ?? 0), fn($r) => bi_money($r['custo'] ?? 0),
    ]); ?>

    <?php bi_render_table('Probabilidade de glosa', ['Score', 'Paciente', 'Hospital', 'Apresentado', 'Glosa', 'Taxa'], $glosaRows, [
        $riskCell, 'paciente', 'hospital', fn($r) => bi_money($r['apresentado'] ?? 0), fn($r) => bi_money($r['glosa'] ?? 0), fn($r) => ((float)($r['apresentado'] ?? 0) > 0 ? bi_pct(((float)$r['glosa'] / (float)$r['apresentado']) * 100) : '0,0%'),
    ]); ?>
</div>

<div class="bi-strategic-grid">
    <?php bi_render_table('Risco de estouro de permanência', ['Score', 'Paciente', 'Hospital', 'Patologia', 'Dias'], $permanencia, [
        $riskCell, 'paciente', 'hospital', 'patologia', fn($r) => bi_num($r['dias'] ?? 0),
    ]); ?>

    <?php bi_render_table('Predição de readmissão', ['Score', 'Paciente', 'Hospital', 'Patologia', 'Histórico', 'Maior permanência'], $readmRows, [
        $riskCell, 'paciente', 'hospital', 'patologia', fn($r) => bi_num($r['historico'] ?? 0), fn($r) => bi_num($r['maior_permanencia'] ?? 0) . ' d',
    ]); ?>
</div>

<div class="bi-strategic-grid">
    <?php bi_render_table('Alerta de desospitalização', ['Score', 'Paciente', 'Hospital', 'Patologia', 'Dias'], $desospitalizacao, [
        $riskCell, 'paciente', 'hospital', 'patologia', fn($r) => bi_num($r['dias'] ?? 0),
    ]); ?>

    <?php bi_render_table('Backlog futuro de auditoria', ['Hospital', 'Internados', 'Visitas', 'Contas', 'Contas abertas'], $backlogRows, [
        'hospital', fn($r) => bi_num($r['internados'] ?? 0), fn($r) => bi_num($r['visitas'] ?? 0), fn($r) => bi_num($r['contas'] ?? 0), fn($r) => bi_num($r['contas_abertas'] ?? 0),
    ]); ?>
</div>

</div>
<?php require_once("templates/footer.php"); ?>
