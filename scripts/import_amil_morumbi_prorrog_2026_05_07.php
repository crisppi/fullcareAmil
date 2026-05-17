<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';

$commit = in_array('--commit', $argv, true);

$hospitalId = 34;     // Sao Luiz Morumbi
$seguradoraId = 36;  // AMIL
$usuarioId = 41;     // Roberto Crisppi
$usuarioNome = 'Roberto Crisppi';
$usuarioCreate = 'codex-import-amil-morumbi-prorrog-2026-05-07';

$rows = [
    ['linha' => 1, 'data_ini' => '07/05/2026', 'data_ref' => '07/05/2026', 'pedido' => '502725414', 'seq' => '2', 'nome' => 'GUSTAVO SANTANA OLIVEIRA', 'solicitado' => 'ALTA TARDIA UTI'],
    ['linha' => 2, 'data_ini' => '15/04/2026', 'data_ref' => '07/05/2026', 'pedido' => '502917247', 'seq' => '17', 'nome' => 'MARIA LIBIA JUCA MAFRA', 'solicitado' => 'TROCA UTI APTO 01 A 05/05'],
    ['linha' => 3, 'data_ini' => '05/05/2026', 'data_ref' => '12/05/2026', 'pedido' => '502606466', 'seq' => '3', 'nome' => 'SERGIO PEDRO GAMMARO JUNIOR', 'solicitado' => 'SEM VESPERA'],
    ['linha' => 4, 'data_ini' => '01/05/2026', 'data_ref' => '12/05/2026', 'pedido' => '505164751', 'seq' => '9', 'nome' => 'SILVIO KAZUNORI HARA', 'solicitado' => 'TROCA UTI APTO 07/05 A 08/05'],
    ['linha' => 5, 'data_ini' => '01/05/2026', 'data_ref' => '12/05/2026', 'pedido' => '505251763', 'seq' => '5', 'nome' => 'LUNNA COSTA DO NASCIMENTO', 'solicitado' => '07/05 A 08/05 01 APTO'],
    ['linha' => 6, 'data_ini' => '01/05/2026', 'data_ref' => '12/05/2026', 'pedido' => '506350548', 'seq' => '2', 'nome' => 'FRANCISCO RODRIGUES DE OLIVEIRA NE', 'solicitado' => 'TROCA UTI APTO 09 A 10/05'],
    ['linha' => 7, 'data_ini' => '30/04/2026', 'data_ref' => null, 'pedido' => '491470849', 'seq' => '3 E 4', 'nome' => 'RICARDO AUGUSTO DA SILVA JUNIOR', 'solicitado' => 'TROCA UTI APTO 01 A 02/05'],
    ['linha' => 8, 'data_ini' => '03/05/2026', 'data_ref' => '07/05/2026', 'pedido' => '503251275', 'seq' => '', 'nome' => 'LUCIANA LARA CORAL', 'solicitado' => 'TARDIA APTO'],
    ['linha' => 9, 'data_ini' => '02/05/2026', 'data_ref' => '07/05/2026', 'pedido' => '505345497', 'seq' => '', 'nome' => 'WANG CHI HSIN', 'solicitado' => 'TROCA DE UTI PARA APTO 05 A 06/05'],
    ['linha' => 10, 'data_ini' => '02/05/2026', 'data_ref' => '07/05/2026', 'pedido' => '502725414', 'seq' => '', 'nome' => 'GUSTAVO SANTANA OLIVEIRA', 'solicitado' => 'TARDIA UTI'],
    ['linha' => 11, 'data_ini' => '05/05/2026', 'data_ref' => '14/05/2026', 'pedido' => '505720773', 'seq' => '', 'nome' => 'LUCA SOUZA CAPELLA', 'solicitado' => 'SEM TARDIA UTI'],
    ['linha' => 12, 'data_ini' => '07/05/2026', 'data_ref' => null, 'pedido' => '498990679', 'seq' => '', 'nome' => 'GABRIELA NAUFAL DE LIMA', 'solicitado' => 'TROCA APTO PARA DAY'],
    ['linha' => 13, 'data_ini' => '07/05/2026', 'data_ref' => null, 'pedido' => '499994062', 'seq' => '', 'nome' => 'CLAUDIO SILVA REGO', 'solicitado' => 'TROCA APTO PARA DAY'],
    ['linha' => 14, 'data_ini' => '07/05/2026', 'data_ref' => null, 'pedido' => '501880437', 'seq' => '', 'nome' => 'ANA CAROLINE SIQUEIRA ROCHA DE SOU', 'solicitado' => 'TROCA APTO PARA DAY'],
    ['linha' => 15, 'data_ini' => '07/05/2026', 'data_ref' => null, 'pedido' => '504075515', 'seq' => '', 'nome' => 'ABRAHAO ALVES TEIXEIRA', 'solicitado' => 'TROCA APTO PARA DAY'],
    ['linha' => 16, 'data_ini' => '07/05/2026', 'data_ref' => null, 'pedido' => '506530907', 'seq' => '', 'nome' => 'DEBORA DA SILVA OLIVEIRA', 'solicitado' => 'TROCA APTO PARA DAY'],
    ['linha' => 17, 'data_ini' => '07/05/2026', 'data_ref' => null, 'pedido' => '497508130', 'seq' => '', 'nome' => 'ANTONIO PAULO SANDOVAL', 'solicitado' => 'TROCA APTO PARA DAY'],
    ['linha' => 18, 'data_ini' => '07/05/2026', 'data_ref' => null, 'pedido' => '501180550', 'seq' => '', 'nome' => 'ENRICO FERREIRA LIMA', 'solicitado' => 'TROCA APTO PARA DAY'],
    ['linha' => 19, 'data_ini' => '07/05/2026', 'data_ref' => null, 'pedido' => '502150556', 'seq' => '', 'nome' => 'TERESA ELIANA BASILIO', 'solicitado' => 'TROCA APTO PARA DAY'],
    ['linha' => 20, 'data_ini' => '07/05/2026', 'data_ref' => null, 'pedido' => '503087456', 'seq' => '', 'nome' => 'WAGNER TAVARES DE GOES', 'solicitado' => 'TROCA APTO PARA DAY'],
    ['linha' => 21, 'data_ini' => '07/05/2026', 'data_ref' => null, 'pedido' => '505245246', 'seq' => '', 'nome' => 'GUILHERME ROCHA GERMANO', 'solicitado' => 'TROCA APTO PARA DAY'],
    ['linha' => 22, 'data_ini' => '07/05/2026', 'data_ref' => null, 'pedido' => '505293090', 'seq' => '', 'nome' => 'ANTONIO MARIA PORTO SEIXAS', 'solicitado' => 'TROCA APTO PARA DAY'],
    ['linha' => 23, 'data_ini' => '07/05/2026', 'data_ref' => null, 'pedido' => '506087600', 'seq' => '', 'nome' => 'ALETHEA CICHY BECK', 'solicitado' => 'TROCA APTO PARA DAY'],
];

function dbDate(?string $value): ?string
{
    if ($value === null || trim($value) === '') {
        return null;
    }
    $dt = DateTime::createFromFormat('!d/m/Y', trim($value));
    $errors = DateTime::getLastErrors();
    if (!$dt || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        return null;
    }
    return $dt->format('Y-m-d');
}

function normName(string $value): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value));
    return mb_strtoupper($value, 'UTF-8');
}

function datePlusOne(string $date): string
{
    return (new DateTime($date))->modify('+1 day')->format('Y-m-d');
}

function diffDays(string $inicio, string $fim): int
{
    $a = new DateTime($inicio);
    $b = new DateTime($fim);
    return max(1, (int)$a->diff($b)->days);
}

function parseDayMonth(string $text): ?string
{
    if (preg_match('/(\d{1,2})\s*\/\s*(\d{1,2})/', $text, $m)) {
        return sprintf('2026-%02d-%02d', (int)$m[2], (int)$m[1]);
    }
    return null;
}

function parseSolicitado(array $row): array
{
    $raw = trim((string)$row['solicitado']);
    $upper = mb_strtoupper($raw, 'UTF-8');
    $start = null;
    $end = null;

    if (preg_match('/(\d{1,2})(?:\/(\d{1,2}))?\s*A\s*(\d{1,2})\/(\d{1,2})/', $upper, $m)) {
        $monthStart = $m[2] !== '' ? (int)$m[2] : (int)$m[4];
        $start = sprintf('2026-%02d-%02d', $monthStart, (int)$m[1]);
        $end = sprintf('2026-%02d-%02d', (int)$m[4], (int)$m[3]);
    }

    $ref = dbDate($row['data_ref'] ?? null) ?: dbDate($row['data_ini'] ?? null);
    if ($start === null) {
        $start = $ref ?: dbDate($row['data_ini']);
    }
    if ($end === null) {
        $end = $start ? datePlusOne($start) : null;
    }

    $tipo = '';
    $trocaDe = '';
    $trocaPara = '';
    $acomodacao = 'Apto';
    $needsReview = false;

    if (strpos($upper, 'TROCA') !== false && strpos($upper, 'UTI') !== false && strpos($upper, 'APTO') !== false) {
        $tipo = 'TROCA UTI/APTO';
        $trocaDe = 'UTI';
        $trocaPara = 'Apto';
        $acomodacao = 'UTI';
    } elseif (strpos($upper, 'TROCA') !== false && strpos($upper, 'APTO') !== false && strpos($upper, 'DAY') !== false) {
        $tipo = 'TROCA APTO/DAY';
        $trocaDe = 'Apto';
        $trocaPara = 'Day Clinic';
        $acomodacao = 'Apto';
    } elseif (strpos($upper, 'TARDIA') !== false && strpos($upper, 'UTI') !== false) {
        $tipo = 'TARDIA UTI';
        $trocaDe = 'UTI';
        $trocaPara = 'UTI';
        $acomodacao = 'UTI';
    } elseif (strpos($upper, 'TARDIA') !== false && strpos($upper, 'APTO') !== false) {
        $tipo = 'TARDIA APTO';
        $trocaDe = 'Apto';
        $trocaPara = 'Apto';
        $acomodacao = 'Apto';
    } elseif (strpos($upper, 'VESPERA') !== false) {
        $tipo = 'VESPERA';
        $trocaDe = 'Apto';
        $trocaPara = 'Apto';
        $acomodacao = 'Apto';
    } elseif (strpos($upper, 'APTO') !== false) {
        $tipo = 'TARDIA APTO';
        $trocaDe = 'Apto';
        $trocaPara = 'Apto';
        $acomodacao = 'Apto';
    } else {
        $needsReview = true;
    }

    if (!$start || !$end) {
        $needsReview = true;
    }

    return [
        'raw' => $raw,
        'tipo_negociacao' => $tipo,
        'troca_de' => $trocaDe,
        'troca_para' => $trocaPara,
        'data_inicio_neg' => $start,
        'data_fim_neg' => $end,
        'qtd' => ($start && $end) ? diffDays($start, $end) : 0,
        'acomodacao_int' => $acomodacao,
        'needs_review' => $needsReview,
    ];
}

function loadAcomodacaoValues(PDO $conn, int $hospitalId): array
{
    $stmt = $conn->prepare('SELECT acomodacao_aco, valor_aco FROM tb_acomodacao WHERE fk_hospital = :hospital');
    $stmt->bindValue(':hospital', $hospitalId, PDO::PARAM_INT);
    $stmt->execute();

    $values = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $values[mb_strtolower(trim((string)$row['acomodacao_aco']), 'UTF-8')] = (float)$row['valor_aco'];
    }
    return $values;
}

function calcSaving(array $neg, array $valores): float
{
    $de = (float)($valores[mb_strtolower($neg['troca_de'], 'UTF-8')] ?? 0);
    $para = (float)($valores[mb_strtolower($neg['troca_para'], 'UTF-8')] ?? 0);
    $qtd = (int)$neg['qtd'];
    $tipo = mb_strtoupper($neg['tipo_negociacao'], 'UTF-8');

    if (strpos($tipo, 'TROCA') === 0) {
        return ($de - $para) * $qtd;
    }
    if (strpos($tipo, '1/2 DIARIA') !== false) {
        return ($de / 2) * $qtd;
    }
    return $de * $qtd;
}

function findPaciente(PDO $conn, string $nome): ?array
{
    $stmt = $conn->prepare('
        SELECT id_paciente, nome_pac
          FROM tb_paciente
         WHERE UPPER(TRIM(nome_pac)) = UPPER(TRIM(:nome))
           AND COALESCE(deletado_pac, "n") <> "s"
         ORDER BY id_paciente DESC
         LIMIT 1
    ');
    $stmt->bindValue(':nome', $nome, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function createPaciente(PDO $conn, string $nome, int $seguradoraId, int $usuarioId, string $usuarioNome): int
{
    $stmt = $conn->prepare('
        INSERT INTO tb_paciente (
            nome_pac, ativo_pac, fk_estipulante_pac, fk_seguradora_pac,
            usuario_create_pac, fk_usuario_pac, deletado_pac, data_create_pac
        ) VALUES (
            :nome, "s", 1, :seguradora,
            :usuario_nome, :usuario_id, "n", CURDATE()
        )
    ');
    $stmt->bindValue(':nome', $nome, PDO::PARAM_STR);
    $stmt->bindValue(':seguradora', $seguradoraId, PDO::PARAM_INT);
    $stmt->bindValue(':usuario_nome', $usuarioNome, PDO::PARAM_STR);
    $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
    $stmt->execute();
    return (int)$conn->lastInsertId();
}

function findInternacao(PDO $conn, int $pacienteId, string $pedido): ?array
{
    $stmt = $conn->prepare('
        SELECT id_internacao, data_intern_int, senha_int
          FROM tb_internacao
         WHERE senha_int = :pedido
           AND COALESCE(deletado_int, "n") <> "s"
         ORDER BY id_internacao DESC
         LIMIT 1
    ');
    $stmt->bindValue(':pedido', $pedido, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (is_array($row)) {
        return $row;
    }

    $stmt = $conn->prepare('
        SELECT id_internacao, data_intern_int, senha_int
          FROM tb_internacao
         WHERE fk_paciente_int = :paciente
           AND internado_int = "s"
           AND COALESCE(deletado_int, "n") <> "s"
         ORDER BY id_internacao DESC
         LIMIT 1
    ');
    $stmt->bindValue(':paciente', $pacienteId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function createInternacao(PDO $conn, array $item, int $pacienteId, int $hospitalId, int $usuarioId, string $usuarioCreate): int
{
    $stmt = $conn->prepare('
        INSERT INTO tb_internacao (
            fk_paciente_int, fk_hospital_int, usuario_create_int, data_intern_int,
            data_lancamento_int, acomodacao_int, internado_int, senha_int,
            data_create_int, primeira_vis_int, visita_no_int, visita_enf_int,
            visita_med_int, fk_usuario_int, censo_int, programacao_int,
            origem_int, int_pertinente_int, rel_pertinente_int, deletado_int
        ) VALUES (
            :paciente, :hospital, :usuario_create, :data_intern,
            NOW(), :acomodacao, "s", :senha,
            NOW(), "s", "0", "n",
            "n", :usuario_id, "n", "",
            "", "", "", "n"
        )
    ');
    $stmt->bindValue(':paciente', $pacienteId, PDO::PARAM_INT);
    $stmt->bindValue(':hospital', $hospitalId, PDO::PARAM_INT);
    $stmt->bindValue(':usuario_create', $usuarioCreate, PDO::PARAM_STR);
    $stmt->bindValue(':data_intern', $item['data_intern'], PDO::PARAM_STR);
    $stmt->bindValue(':acomodacao', $item['acomodacao_int'], PDO::PARAM_STR);
    $stmt->bindValue(':senha', $item['pedido'], PDO::PARAM_STR);
    $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
    $stmt->execute();
    return (int)$conn->lastInsertId();
}

function findNegociacao(PDO $conn, int $internacaoId, array $neg): ?array
{
    $stmt = $conn->prepare('
        SELECT id_negociacao
          FROM tb_negociacao
         WHERE fk_id_int = :internacao
           AND tipo_negociacao = :tipo
           AND troca_de = :troca_de
           AND troca_para = :troca_para
           AND qtd = :qtd
           AND data_inicio_neg = :inicio
           AND data_fim_neg = :fim
         LIMIT 1
    ');
    $stmt->bindValue(':internacao', $internacaoId, PDO::PARAM_INT);
    $stmt->bindValue(':tipo', $neg['tipo_negociacao'], PDO::PARAM_STR);
    $stmt->bindValue(':troca_de', $neg['troca_de'], PDO::PARAM_STR);
    $stmt->bindValue(':troca_para', $neg['troca_para'], PDO::PARAM_STR);
    $stmt->bindValue(':qtd', (int)$neg['qtd'], PDO::PARAM_INT);
    $stmt->bindValue(':inicio', $neg['data_inicio_neg'], PDO::PARAM_STR);
    $stmt->bindValue(':fim', $neg['data_fim_neg'], PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function createNegociacao(PDO $conn, int $internacaoId, array $neg, int $usuarioId): int
{
    $stmt = $conn->prepare('
        INSERT INTO tb_negociacao (
            fk_id_int, troca_de, troca_para, qtd, saving, fk_usuario_neg,
            data_inicio_neg, data_fim_neg, tipo_negociacao
        ) VALUES (
            :internacao, :troca_de, :troca_para, :qtd, :saving, :usuario,
            :inicio, :fim, :tipo
        )
    ');
    $stmt->bindValue(':internacao', $internacaoId, PDO::PARAM_INT);
    $stmt->bindValue(':troca_de', $neg['troca_de'], PDO::PARAM_STR);
    $stmt->bindValue(':troca_para', $neg['troca_para'], PDO::PARAM_STR);
    $stmt->bindValue(':qtd', (int)$neg['qtd'], PDO::PARAM_INT);
    $stmt->bindValue(':saving', number_format((float)$neg['saving'], 2, '.', ''), PDO::PARAM_STR);
    $stmt->bindValue(':usuario', $usuarioId, PDO::PARAM_INT);
    $stmt->bindValue(':inicio', $neg['data_inicio_neg'], PDO::PARAM_STR);
    $stmt->bindValue(':fim', $neg['data_fim_neg'], PDO::PARAM_STR);
    $stmt->bindValue(':tipo', $neg['tipo_negociacao'], PDO::PARAM_STR);
    $stmt->execute();
    return (int)$conn->lastInsertId();
}

$valores = loadAcomodacaoValues($conn, $hospitalId);
$items = [];

foreach ($rows as $row) {
    $key = normName($row['nome']) . '|' . $row['pedido'];
    $neg = parseSolicitado($row);
    $neg['saving'] = calcSaving($neg, $valores);
    $neg['linha'] = $row['linha'];

    if (!isset($items[$key])) {
        $items[$key] = [
            'nome' => normName($row['nome']),
            'pedido' => $row['pedido'],
            'data_intern' => dbDate($row['data_ini']),
            'acomodacao_int' => $neg['acomodacao_int'],
            'linhas' => [],
            'negociacoes' => [],
        ];
    } elseif (dbDate($row['data_ini']) && $items[$key]['data_intern'] > dbDate($row['data_ini'])) {
        $items[$key]['data_intern'] = dbDate($row['data_ini']);
    }

    $items[$key]['linhas'][] = $row['linha'];
    $negKey = implode('|', [
        $neg['tipo_negociacao'],
        $neg['troca_de'],
        $neg['troca_para'],
        $neg['data_inicio_neg'],
        $neg['data_fim_neg'],
        (string)$neg['qtd'],
    ]);
    if (!isset($items[$key]['negociacoes'][$negKey])) {
        $neg['linhas'] = [$row['linha']];
        $items[$key]['negociacoes'][$negKey] = $neg;
    } else {
        $items[$key]['negociacoes'][$negKey]['linhas'][] = $row['linha'];
    }
}

$summary = [
    'mode' => $commit ? 'commit' : 'dry-run',
    'items' => count($items),
    'rows' => count($rows),
    'pacientes_created' => 0,
    'pacientes_existing' => 0,
    'internacoes_created' => 0,
    'internacoes_existing' => 0,
    'negociacoes_created' => 0,
    'negociacoes_existing' => 0,
    'negociacoes_review' => 0,
    'skipped' => 0,
];

foreach ($items as $item) {
    $conn->beginTransaction();
    try {
        $paciente = findPaciente($conn, $item['nome']);
        $pacienteId = $paciente ? (int)$paciente['id_paciente'] : 0;
        $pacienteStatus = $paciente ? 'existing' : 'would_create';

        if (!$paciente && $commit) {
            $pacienteId = createPaciente($conn, $item['nome'], $seguradoraId, $usuarioId, $usuarioNome);
            $pacienteStatus = 'created';
        }

        $internacao = $pacienteId > 0 ? findInternacao($conn, $pacienteId, $item['pedido']) : null;
        $internacaoId = $internacao ? (int)$internacao['id_internacao'] : 0;
        $internacaoStatus = $internacao ? 'existing' : 'would_create';

        if (!$internacao && $commit) {
            if ($pacienteId <= 0) {
                $pacienteId = createPaciente($conn, $item['nome'], $seguradoraId, $usuarioId, $usuarioNome);
                $pacienteStatus = 'created';
            }
            $internacaoId = createInternacao($conn, $item, $pacienteId, $hospitalId, $usuarioId, $usuarioCreate);
            $internacaoStatus = 'created';
        }

        $negResults = [];
        foreach ($item['negociacoes'] as $neg) {
            if ($neg['needs_review'] || $neg['tipo_negociacao'] === '' || $neg['saving'] <= 0 || $neg['qtd'] <= 0) {
                $summary['negociacoes_review']++;
                $negResults[] = [
                    'linhas' => $neg['linhas'] ?? [$neg['linha']],
                    'status' => 'review',
                    'solicitado' => $neg['raw'],
                    'tipo' => $neg['tipo_negociacao'],
                    'saving' => round((float)$neg['saving'], 2),
                ];
                continue;
            }

            $existingNeg = $internacaoId > 0 ? findNegociacao($conn, $internacaoId, $neg) : null;
            $negStatus = $existingNeg ? 'existing' : 'would_create';
            $negId = $existingNeg ? (int)$existingNeg['id_negociacao'] : null;

            if (!$existingNeg && $commit && $internacaoId > 0) {
                $negId = createNegociacao($conn, $internacaoId, $neg, $usuarioId);
                $negStatus = 'created';
            }

            if ($negStatus === 'existing') {
                $summary['negociacoes_existing']++;
            } else {
                $summary['negociacoes_created']++;
            }

            $negResults[] = [
                'linhas' => $neg['linhas'] ?? [$neg['linha']],
                'status' => $negStatus,
                'id' => $negId,
                'tipo' => $neg['tipo_negociacao'],
                'periodo' => $neg['data_inicio_neg'] . ' a ' . $neg['data_fim_neg'],
                'qtd' => (int)$neg['qtd'],
                'troca_de' => $neg['troca_de'],
                'troca_para' => $neg['troca_para'],
                'saving' => round((float)$neg['saving'], 2),
            ];
        }

        if ($pacienteStatus === 'existing') {
            $summary['pacientes_existing']++;
        } else {
            $summary['pacientes_created']++;
        }
        if ($internacaoStatus === 'existing') {
            $summary['internacoes_existing']++;
        } else {
            $summary['internacoes_created']++;
        }

        if ($commit) {
            $conn->commit();
        } else {
            $conn->rollBack();
        }

        echo json_encode([
            'nome' => $item['nome'],
            'pedido' => $item['pedido'],
            'linhas' => $item['linhas'],
            'paciente_id' => $pacienteId ?: null,
            'paciente_status' => $pacienteStatus,
            'internacao_id' => $internacaoId ?: null,
            'internacao_status' => $internacaoStatus,
            'data_internacao' => $item['data_intern'],
            'negociacoes' => $negResults,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $summary['skipped']++;
        echo json_encode([
            'nome' => $item['nome'],
            'pedido' => $item['pedido'],
            'status' => 'error',
            'error' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
}

echo json_encode(['summary' => $summary], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
