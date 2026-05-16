<?php
include_once("check_logado.php");
require_once("templates/header.php");

if (!isset($conn) || !($conn instanceof PDO)) {
    die("Conexao invalida.");
}

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$riscoFiltro = strtolower(trim((string)(filter_input(INPUT_GET, 'risco') ?? '')));

$limiteDiasSemVisita = 2;
$pontosPermanencia = 30;
$pontosAltoCusto = 30;
$pontosSemVisita = 30;
$pontosAntecedente = 10;

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);

$where = "i.internado_int = 's'";
$params = [];
if (!empty($hospitalId)) {
    $where .= " AND i.fk_hospital_int = :hospital_id";
    $params[':hospital_id'] = (int)$hospitalId;
}

$sql = "
    SELECT
        i.id_internacao,
        i.data_intern_int,
        DATEDIFF(CURDATE(), i.data_intern_int) AS dias_internacao,
        pa.nome_pac,
        ho.nome_hosp,
        p.dias_pato,
        g.alto_custo_ges,
        lv.ultima_visita,
    TIMESTAMPDIFF(HOUR, lv.ultima_visita, NOW()) AS horas_sem_visita,
    DATEDIFF(CURDATE(), lv.ultima_visita) AS dias_sem_visita,
        COALESCE(ar.risco_antecedentes, 0) AS risco_antecedentes
    FROM tb_internacao i
    LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int
    LEFT JOIN tb_hospital ho ON ho.id_hospital = i.fk_hospital_int
    LEFT JOIN tb_patologia p ON p.id_patologia = i.fk_patologia_int
    LEFT JOIN (
        SELECT fk_internacao_ges, MAX(alto_custo_ges) AS alto_custo_ges
        FROM tb_gestao
        GROUP BY fk_internacao_ges
    ) g ON g.fk_internacao_ges = i.id_internacao
    LEFT JOIN (
        SELECT fk_internacao_vis, MAX(data_visita_vis) AS ultima_visita
        FROM tb_visita
        GROUP BY fk_internacao_vis
    ) lv ON lv.fk_internacao_vis = i.id_internacao
    LEFT JOIN (
        SELECT ia.fK_internacao_ant_int AS fk_internacao_int,
               SUM(
                   CASE
                       WHEN LOWER(a.antecedente_ant) LIKE '%diabet%'
                         OR LOWER(a.antecedente_ant) LIKE '%cardiopat%'
                         OR LOWER(a.antecedente_ant) LIKE '%neoplas%'
                       THEN 1 ELSE 0
                   END
               ) AS risco_antecedentes
        FROM tb_intern_antec ia
        JOIN tb_antecedente a ON a.id_antecedente = ia.intern_antec_ant_int
        GROUP BY ia.fK_internacao_ant_int
    ) ar ON ar.fk_internacao_int = i.id_internacao
    WHERE {$where}
    ORDER BY i.data_intern_int DESC, i.id_internacao DESC
";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$resultados = [];
foreach ($rows as $row) {
    $diasIntern = isset($row['dias_internacao']) ? (int)$row['dias_internacao'] : null;
    $diasEsperado = isset($row['dias_pato']) ? (int)$row['dias_pato'] : null;
    $horasSemVisita = $row['horas_sem_visita'] !== null ? (int)$row['horas_sem_visita'] : null;
    $diasSemVisita = $row['dias_sem_visita'] !== null ? (int)$row['dias_sem_visita'] : null;
    $altoCusto = strtolower((string)($row['alto_custo_ges'] ?? '')) === 's';
    $riscoAntecedentes = (int)($row['risco_antecedentes'] ?? 0);

    $score = 0;
    $motivos = [];
    if ($diasIntern !== null && $diasEsperado !== null && $diasEsperado > 0 && $diasIntern > $diasEsperado) {
        $score += $pontosPermanencia;
        $motivos[] = "Permanência > esperado";
    }
    if ($altoCusto) {
        $score += $pontosAltoCusto;
        $motivos[] = "Alto custo";
    }
    if ($diasSemVisita === null || $diasSemVisita > $limiteDiasSemVisita) {
        $score += $pontosSemVisita;
        $motivos[] = "Sem visita > {$limiteDiasSemVisita}d";
    }
    if ($riscoAntecedentes > 0) {
        $score += $pontosAntecedente * $riscoAntecedentes;
        $motivos[] = "Antecedentes risco ({$riscoAntecedentes})";
    }

    if ($score >= 60) {
        $nivel = 'Alto';
    } elseif ($score >= 30) {
        $nivel = 'Medio';
    } else {
        $nivel = 'Baixo';
    }

    if ($riscoFiltro) {
        if (($riscoFiltro === 'alto' && $nivel !== 'Alto')
            || ($riscoFiltro === 'medio' && $nivel !== 'Medio')
            || ($riscoFiltro === 'baixo' && $nivel !== 'Baixo')) {
            continue;
        }
    }

    $resultados[] = [
        'id_internacao' => $row['id_internacao'],
        'nome_pac' => $row['nome_pac'],
        'nome_hosp' => $row['nome_hosp'],
        'data_intern_int' => $row['data_intern_int'],
        'dias_internacao' => $diasIntern,
        'dias_pato' => $diasEsperado,
        'ultima_visita' => $row['ultima_visita'],
        'horas_sem_visita' => $horasSemVisita,
        'dias_sem_visita' => $diasSemVisita,
        'alto_custo' => $altoCusto ? 's' : 'n',
        'risco_antecedentes' => $riscoAntecedentes,
        'score' => $score,
        'nivel' => $nivel,
        'motivos' => $motivos,
    ];
}
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260509-filter-icons"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Internações com Risco</h1>
        <div class="bi-header-actions">
            <div class="text-end text-muted"></div>
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegação">
                <i class="bi bi-grid-3x3-gap"></i>
            </a>
        </div>
    </div>

    <form class="bi-panel bi-filters" method="get">
        <div class="bi-filter">
            <label>Hospitais</label>
            <select name="hospital_id">
                <option value="">Todos</option>
                <?php foreach ($hospitais as $h): ?>
                    <option value="<?= (int)$h['id_hospital'] ?>" <?= $hospitalId == $h['id_hospital'] ? 'selected' : '' ?>>
                        <?= e($h['nome_hosp']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Nível de Risco</label>
            <select name="risco">
                <option value="">Todos</option>
                <option value="alto" <?= $riscoFiltro === 'alto' ? 'selected' : '' ?>>Alto</option>
                <option value="medio" <?= $riscoFiltro === 'medio' ? 'selected' : '' ?>>Medio</option>
                <option value="baixo" <?= $riscoFiltro === 'baixo' ? 'selected' : '' ?>>Baixo</option>
            </select>
        </div>
        <div class="bi-actions">
            <button class="bi-btn" type="submit">Aplicar</button>
        </div>
    </form>

    <div class="bi-panel" style="margin-top:16px;">
        <h3 class="text-center" style="margin-bottom:12px;">Internações com Risco</h3>
        <div class="table-responsive">
            <table class="bi-table">
                <thead>
                    <tr>
                        <th>Internação</th>
                        <th>Paciente</th>
                        <th style="min-width: 220px;">Hospital</th>
                        <th>Dias Internação</th>
                        <th>Dias Esperado</th>
                        <th>Ultima Visita</th>
                        <th>Dias Sem Visita</th>
                        <th>Alto Custo</th>
                        <th>Antecedentes Risco</th>
                        <th>Score</th>
                        <th>Nível</th>
                        <th>Motivos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$resultados): ?>
                        <tr>
                            <td colspan="12">Sem informações</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($resultados as $row): ?>
                            <tr>
                                <td><?= (int)$row['id_internacao'] ?></td>
                                <td><?= e($row['nome_pac']) ?></td>
                                <td><?= e($row['nome_hosp']) ?></td>
                                <td><?= $row['dias_internacao'] !== null ? (int)$row['dias_internacao'] : '-' ?></td>
                                <td><?= $row['dias_pato'] !== null ? (int)$row['dias_pato'] : '-' ?></td>
                                <td>
                                    <?php if ($row['ultima_visita']): ?>
                                        <?= e(date('d/m/Y', strtotime($row['ultima_visita']))) ?>
                                    <?php else: ?>
                                        Sem visita
                                    <?php endif; ?>
                                </td>
                                <td><?= $row['dias_sem_visita'] !== null ? (int)$row['dias_sem_visita'] : 'Sem visita' ?></td>
                                <td><?= $row['alto_custo'] === 's' ? 'Sim' : 'Não' ?></td>
                                <td><?= (int)$row['risco_antecedentes'] ?></td>
                                <td><?= (int)$row['score'] ?></td>
                                <td><?= e($row['nivel']) ?></td>
                                <td><?= e(implode(', ', $row['motivos'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
