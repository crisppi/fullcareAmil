<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';

$importUserId = 41;
$importUserName = 'Roberto Crisppi';
$seguradoraAmilId = 36;
$hospitalSloId = 33;
$usuarioCreateInt = 'codex-import-pdf-2026-04-17';

$pending = [
    [
        'page' => 3,
        'nome' => 'Erika Ferreira de Souza',
        'mae' => 'Rosangela Alves da Silva',
        'sexo' => 'f',
        'data_nasc' => '13/09/1991',
        'data_intern' => '14/04/2026',
        'hora_intern' => '10:51',
        'matricula' => '084039586',
        'senha' => '202600206197',
        'leito' => 'UI 1779',
        'num_atendimento' => '53864984',
    ],
    [
        'page' => 4,
        'nome' => 'RN de Luana Cacemiro Alves',
        'mae' => 'Luana Cacemiro Alves',
        'sexo' => 'm',
        'data_nasc' => '07/04/2026',
        'data_intern' => '07/04/2026',
        'hora_intern' => '10:01',
        'matricula' => '084917667',
        'senha' => '202600193101',
        'leito' => 'UTI NEO 05',
        'num_atendimento' => '53713865',
        'recem_nascido' => 's',
    ],
    [
        'page' => 6,
        'nome' => 'Beatriz Holanda Gomes da Silva',
        'mae' => 'Fabiane Holanda Soares',
        'sexo' => 'f',
        'data_nasc' => '02/10/2003',
        'data_intern' => '09/04/2026',
        'hora_intern' => '06:30',
        'matricula' => '089367608',
        'senha' => 'CO2026003804379',
        'leito' => 'UI 1779',
        'num_atendimento' => '53748806',
    ],
    [
        'page' => 7,
        'nome' => 'Soraya Regina Fernandes de Souza',
        'mae' => 'Sandra Regina de Souza',
        'sexo' => 'f',
        'data_nasc' => '15/09/1998',
        'data_intern' => '01/04/2026',
        'hora_intern' => '21:23',
        'matricula' => '082920575',
        'senha' => 'C02026003557995',
        'leito' => 'MAT - 805',
        'num_atendimento' => '53601192',
    ],
    [
        'page' => 9,
        'nome' => 'Henry Santos de Jesus Silva',
        'mae' => 'Aline Aparecida Santos de Jesus',
        'sexo' => 'f',
        'data_nasc' => '22/09/2025',
        'data_intern' => '11/04/2026',
        'hora_intern' => '18:20',
        'matricula' => '095685121',
        'senha' => 'C02026003918185',
        'leito' => 'UI 518',
        'num_atendimento' => '53813306',
    ],
    [
        'page' => 10,
        'nome' => 'Michelle Mayra Souza Alves de Sa Bezerra',
        'mae' => 'Sandra Regina de Souza Alves',
        'sexo' => 'f',
        'data_nasc' => '16/07/2000',
        'data_intern' => '26/02/2026',
        'hora_intern' => '09:57',
        'matricula' => '088281312',
        'senha' => 'C02026002079870',
        'leito' => 'MAT - 822',
        'num_atendimento' => '52801213',
    ],
    [
        'page' => 11,
        'nome' => 'Elisangela de Souza Mingues',
        'mae' => 'Solange Maria de Souza',
        'sexo' => 'f',
        'data_nasc' => '04/07/1997',
        'data_intern' => '09/04/2026',
        'hora_intern' => '10:20',
        'matricula' => '094166853',
        'senha' => 'C02026003820390',
        'leito' => 'MAT - 803',
        'num_atendimento' => '53757045',
    ],
    [
        'page' => 12,
        'nome' => 'Roger Figueira Lemos',
        'mae' => 'Ana Cristina Figueira',
        'sexo' => 'm',
        'data_nasc' => '21/12/2005',
        'data_intern' => '13/04/2026',
        'hora_intern' => '20:52',
        'matricula' => null,
        'senha' => '202600204818',
        'leito' => 'UI 1362/A',
        'num_atendimento' => '53853410',
    ],
    [
        'page' => 13,
        'nome' => 'Rogerio Claro Albino',
        'mae' => 'Neiva de Fatima Claro Albino',
        'sexo' => 'm',
        'data_nasc' => '15/03/1986',
        'data_intern' => '10/04/2026',
        'hora_intern' => '09:05',
        'matricula' => '085126910',
        'senha' => 'C02026003866908',
        'leito' => 'UI 1371 AP',
        'num_atendimento' => '53781717',
    ],
    [
        'page' => 14,
        'nome' => 'Julia Christina Carneiro Ribeiro de Souza',
        'mae' => 'Magaly de Oliveira Carneiro',
        'sexo' => 'f',
        'data_nasc' => '25/05/1993',
        'data_intern' => '09/04/2026',
        'hora_intern' => '08:52',
        'matricula' => '093986953',
        'senha' => 'C02026003810913',
        'leito' => 'UTI 24',
        'num_atendimento' => '53753247',
    ],
    [
        'page' => 15,
        'nome' => 'Marina Moura Sena',
        'mae' => 'Daniela Moura de Carvalho',
        'sexo' => 'f',
        'data_nasc' => '26/04/2018',
        'data_intern' => '13/04/2026',
        'hora_intern' => '06:54',
        'matricula' => '089798494',
        'senha' => 'C02026003929487',
        'leito' => 'UI 402 A',
        'num_atendimento' => '53824954',
    ],
    [
        'page' => 16,
        'nome' => 'Edmur Jose Dias Junior',
        'mae' => 'Rosalina de Azevedo Dias',
        'sexo' => 'm',
        'data_nasc' => '14/05/1981',
        'data_intern' => '14/04/2026',
        'hora_intern' => '16:52',
        'matricula' => '087085429',
        'senha' => '202600207199',
        'leito' => 'UI 1678',
        'num_atendimento' => '53877877',
    ],
];

function toDbDate(?string $value): ?string
{
    if ($value === null || trim($value) === '') {
        return null;
    }

    $dt = DateTime::createFromFormat('d/m/Y', trim($value));
    return $dt ? $dt->format('Y-m-d') : null;
}

function normalizeName(string $value): string
{
    $value = preg_replace('/\s+/', ' ', trim($value)) ?: '';
    return mb_strtoupper($value, 'UTF-8');
}

function inferAcomodacao(string $leito): string
{
    $upper = mb_strtoupper($leito, 'UTF-8');
    if (strpos($upper, 'UTI') !== false) {
        return 'UTI';
    }

    return 'APTO/ENF';
}

function findPaciente(PDO $conn, array $entry): ?array
{
    if (!empty($entry['matricula'])) {
        $stmt = $conn->prepare("
            SELECT id_paciente, nome_pac, matricula_pac
              FROM tb_paciente
             WHERE matricula_pac = :matricula
             LIMIT 1
        ");
        $stmt->bindValue(':matricula', $entry['matricula'], PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            return $row;
        }
    }

    $stmt = $conn->prepare("
        SELECT id_paciente, nome_pac, matricula_pac
          FROM tb_paciente
         WHERE UPPER(nome_pac) = UPPER(:nome)
         LIMIT 1
    ");
    $stmt->bindValue(':nome', $entry['nome'], PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function createPaciente(
    PDO $conn,
    array $entry,
    int $seguradoraId,
    int $userId,
    string $userName
): int {
    $stmt = $conn->prepare("
        INSERT INTO tb_paciente (
            nome_pac,
            data_nasc_pac,
            ativo_pac,
            mae_pac,
            fk_estipulante_pac,
            sexo_pac,
            fk_seguradora_pac,
            matricula_pac,
            usuario_create_pac,
            fk_usuario_pac,
            deletado_pac,
            data_create_pac,
            recem_nascido_pac
        ) VALUES (
            :nome,
            :data_nasc,
            's',
            :mae,
            1,
            :sexo,
            :seguradora,
            :matricula,
            :usuario_create,
            :fk_usuario,
            'n',
            NOW(),
            :recem_nascido
        )
    ");
    $stmt->bindValue(':nome', $entry['nome'], PDO::PARAM_STR);
    $stmt->bindValue(':data_nasc', toDbDate($entry['data_nasc']));
    $stmt->bindValue(':mae', $entry['mae'], PDO::PARAM_STR);
    $stmt->bindValue(':sexo', $entry['sexo'], PDO::PARAM_STR);
    $stmt->bindValue(':seguradora', $seguradoraId, PDO::PARAM_INT);
    $stmt->bindValue(':matricula', $entry['matricula']);
    $stmt->bindValue(':usuario_create', $userName, PDO::PARAM_STR);
    $stmt->bindValue(':fk_usuario', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':recem_nascido', $entry['recem_nascido'] ?? 'n', PDO::PARAM_STR);
    $stmt->execute();

    return (int)$conn->lastInsertId();
}

function findInternacao(PDO $conn, int $pacienteId, ?string $senha): ?array
{
    if ($senha) {
        $stmt = $conn->prepare("
            SELECT id_internacao, senha_int
              FROM tb_internacao
             WHERE senha_int = :senha
             LIMIT 1
        ");
        $stmt->bindValue(':senha', $senha, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            return $row;
        }
    }

    $stmt = $conn->prepare("
        SELECT id_internacao, senha_int
          FROM tb_internacao
         WHERE fk_paciente_int = :paciente
           AND internado_int = 's'
         ORDER BY id_internacao DESC
         LIMIT 1
    ");
    $stmt->bindValue(':paciente', $pacienteId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function createInternacao(
    PDO $conn,
    array $entry,
    int $pacienteId,
    int $hospitalId,
    int $userId,
    string $usuarioCreateInt
): int {
    $stmt = $conn->prepare("
        INSERT INTO tb_internacao (
            fk_paciente_int,
            fk_hospital_int,
            usuario_create_int,
            data_intern_int,
            data_lancamento_int,
            hora_intern_int,
            acomodacao_int,
            internado_int,
            senha_int,
            data_create_int,
            primeira_vis_int,
            visita_no_int,
            visita_enf_int,
            visita_med_int,
            fk_usuario_int,
            censo_int,
            programacao_int,
            origem_int,
            int_pertinente_int,
            rel_pertinente_int,
            deletado_int,
            num_atendimento_int
        ) VALUES (
            :paciente,
            :hospital,
            :usuario_create,
            :data_intern,
            NOW(),
            :hora_intern,
            :acomodacao,
            's',
            :senha,
            NOW(),
            's',
            '0',
            'n',
            'n',
            :fk_usuario,
            'n',
            '',
            '',
            '',
            '',
            'n',
            :num_atendimento
        )
    ");
    $stmt->bindValue(':paciente', $pacienteId, PDO::PARAM_INT);
    $stmt->bindValue(':hospital', $hospitalId, PDO::PARAM_INT);
    $stmt->bindValue(':usuario_create', $usuarioCreateInt, PDO::PARAM_STR);
    $stmt->bindValue(':data_intern', toDbDate($entry['data_intern']));
    $stmt->bindValue(':hora_intern', $entry['hora_intern'], PDO::PARAM_STR);
    $stmt->bindValue(':acomodacao', inferAcomodacao($entry['leito']), PDO::PARAM_STR);
    $stmt->bindValue(':senha', $entry['senha'], PDO::PARAM_STR);
    $stmt->bindValue(':fk_usuario', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':num_atendimento', $entry['num_atendimento'], PDO::PARAM_STR);
    $stmt->execute();

    return (int)$conn->lastInsertId();
}

$results = [];

foreach ($pending as $entry) {
    $entry['nome'] = trim($entry['nome']);
    $entry['mae'] = trim($entry['mae']);

    $conn->beginTransaction();

    try {
        $paciente = findPaciente($conn, $entry);
        $pacienteId = $paciente ? (int)$paciente['id_paciente'] : createPaciente(
            $conn,
            $entry,
            $seguradoraAmilId,
            $importUserId,
            $importUserName
        );

        $internacao = findInternacao($conn, $pacienteId, $entry['senha']);
        $internacaoId = $internacao ? (int)$internacao['id_internacao'] : createInternacao(
            $conn,
            $entry,
            $pacienteId,
            $hospitalSloId,
            $importUserId,
            $usuarioCreateInt
        );

        $conn->commit();

        $results[] = [
            'page' => $entry['page'],
            'nome' => $entry['nome'],
            'paciente_id' => $pacienteId,
            'internacao_id' => $internacaoId,
            'status_paciente' => $paciente ? 'existing' : 'created',
            'status_internacao' => $internacao ? 'existing' : 'created',
            'senha' => $entry['senha'],
        ];
    } catch (Throwable $e) {
        $conn->rollBack();
        $results[] = [
            'page' => $entry['page'],
            'nome' => $entry['nome'],
            'error' => $e->getMessage(),
        ];
    }
}

foreach ($results as $result) {
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
