<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';

$commit = in_array('--commit', $argv, true);
$importUserId = 41;
$importUserName = 'Roberto Crisppi';
$seguradoraAmilId = 36;
$hospitalSinoId = 33;
$usuarioCreateInt = 'codex-import-amil-sino-2026-04-25';

$pending = [
    ['page' => 1, 'nome' => 'Luis Irapua Moraes Stone', 'mae' => 'Masria Loeci Moraes Stone', 'sexo' => 'm', 'data_nasc' => '27/09/1974', 'data_intern' => '26/03/2026', 'hora_intern' => '18:50', 'matricula' => '086248120', 'senha' => 'C02026003315817', 'leito' => 'UI 1666', 'num_atendimento' => '53462444'],
    ['page' => 2, 'nome' => 'Loide Ribeiro da Luz Silva', 'mae' => 'Romilda Mauriola', 'sexo' => 'f', 'data_nasc' => '04/09/1961', 'data_intern' => '03/04/2026', 'hora_intern' => '17:16', 'matricula' => '080515231', 'senha' => '202600198656', 'leito' => 'UTI 1873', 'num_atendimento' => '53637246'],
    ['page' => 4, 'nome' => 'Nivaldo da Costa Braga', 'mae' => 'Dezolina Rosa De Souza Braga', 'sexo' => 'm', 'data_nasc' => '15/10/1954', 'data_intern' => '21/02/2026', 'hora_intern' => '08:47', 'matricula' => '096229147', 'senha' => '202600102489', 'leito' => 'UI 1569', 'num_atendimento' => '52685764'],
    ['page' => 5, 'nome' => 'Helena Santiago Araujo', 'mae' => 'Mariene Santiago Do Prado', 'sexo' => 'f', 'data_nasc' => '17/02/2026', 'data_intern' => '17/02/2026', 'hora_intern' => '03:36', 'matricula' => '095551532', 'senha' => '202600094822', 'leito' => 'UTI NEO 10', 'num_atendimento' => '52593171', 'recem_nascido' => 's'],
    ['page' => 7, 'nome' => 'RN de Giullana Micalioni Galate Bastos', 'mae' => 'Giullana Micalioni Galate Bastos', 'sexo' => 'm', 'data_nasc' => '31/03/2026', 'data_intern' => '04/04/2026', 'hora_intern' => '20:29', 'matricula' => '096477056', 'senha' => '202600187579', 'leito' => 'UTI PED 02', 'num_atendimento' => '53851154', 'recem_nascido' => 's'],
    ['page' => 8, 'nome' => 'RN de Monara Gracindo Alves Levenetz', 'mae' => 'Monara Gracindo Alves Levenetz', 'sexo' => 'm', 'data_nasc' => '07/04/2026', 'data_intern' => '07/04/2026', 'hora_intern' => '07:17', 'matricula' => '086469728', 'senha' => '202600192882', 'leito' => 'UTI NEO 11', 'num_atendimento' => '53895567', 'recem_nascido' => 's'],
    ['page' => 9, 'nome' => 'Melissa Lira dos Santos Feitosa', 'mae' => 'Emily Lira Abriz', 'sexo' => 'f', 'data_nasc' => '20/09/2018', 'data_intern' => '08/04/2026', 'hora_intern' => '18:38', 'matricula' => '096444902', 'senha' => '202600185079', 'leito' => 'UTI PED 011', 'num_atendimento' => '53744407'],
    ['page' => 11, 'nome' => 'Beatriz Holanda Gomes da Silva', 'mae' => 'Fabiane Holanda Soares', 'sexo' => 'f', 'data_nasc' => '02/10/2003', 'data_intern' => '09/04/2026', 'hora_intern' => '06:30', 'matricula' => '089367608', 'senha' => 'C02026003804379', 'leito' => 'UI 1779', 'num_atendimento' => '53748808'],
    ['page' => 12, 'nome' => 'Daniel Silva Amaral', 'mae' => 'Camila Silva Amaral', 'sexo' => 'm', 'data_nasc' => '31/01/2026', 'data_intern' => '28/03/2026', 'hora_intern' => '18:20', 'matricula' => '096702075', 'senha' => '202600174946', 'leito' => 'UTI PED 07', 'num_atendimento' => '53604124'],
    ['page' => 13, 'nome' => 'Julia Christina Carneiro Ribeiro de Souza', 'mae' => 'Magaly de Oliveira Carneiro', 'sexo' => 'f', 'data_nasc' => '25/05/1993', 'data_intern' => '09/04/2026', 'hora_intern' => '08:52', 'matricula' => '093986953', 'senha' => 'C02026003810913', 'leito' => 'UTI 24', 'num_atendimento' => '53753247'],
    ['page' => 14, 'nome' => 'Adalberto Jeronimo da Silva', 'mae' => 'Marta Elsa Da Silva', 'sexo' => 'm', 'data_nasc' => '14/06/1971', 'data_intern' => '07/04/2026', 'hora_intern' => '16:04', 'matricula' => '085620125', 'senha' => '202600193316', 'leito' => 'UTI 717', 'num_atendimento' => '53709981'],
    ['page' => 17, 'nome' => 'Gabriel Sousa Santana', 'mae' => 'Simone Silva De Sousa', 'sexo' => 'm', 'data_nasc' => '18/10/2003', 'data_intern' => '08/04/2026', 'hora_intern' => '10:42', 'matricula' => '084739477', 'senha' => '202600194359', 'leito' => 'UI 711/B', 'num_atendimento' => '53729808'],
    ['page' => 18, 'nome' => 'Jacqueline Ferreira Domingos', 'mae' => 'Edjane Ferreira De Lima', 'sexo' => 'f', 'data_nasc' => '18/03/1986', 'data_intern' => '11/04/2026', 'hora_intern' => '07:16', 'matricula' => '087307276', 'senha' => '202600200520', 'leito' => 'UI 1385/A', 'num_atendimento' => '53802136'],
    ['page' => 21, 'nome' => 'RN de Luana Cacemiro Alves', 'mae' => 'Luana Cacemiro Alves', 'sexo' => 'm', 'data_nasc' => '07/04/2026', 'data_intern' => '07/04/2026', 'hora_intern' => '16:57', 'matricula' => '084917667', 'senha' => '202600193101', 'leito' => 'UTI NEO 10', 'num_atendimento' => '53713865', 'recem_nascido' => 's'],
    ['page' => 22, 'nome' => 'Sergio Pereira dos Santos', 'mae' => 'Maria Pereira Dos Santos', 'sexo' => 'm', 'data_nasc' => '19/11/1970', 'data_intern' => '11/04/2026', 'hora_intern' => '19:27', 'matricula' => '088369623', 'senha' => '202600201488', 'leito' => 'UI 624 A', 'num_atendimento' => '53813764'],
    ['page' => 23, 'nome' => 'Ana Carolina Nogueira de Souza', 'mae' => 'Edna Janaina Bonilha Nogueira', 'sexo' => 'f', 'data_nasc' => '22/08/1994', 'data_intern' => '18/04/2026', 'hora_intern' => '17:49', 'matricula' => '096476908', 'senha' => '202600215770', 'leito' => 'UI 1770', 'num_atendimento' => '53979244'],
    ['page' => 24, 'nome' => 'Henrique Tartarini', 'mae' => 'Leticia Oliveira Tartarini', 'sexo' => 'm', 'data_nasc' => '12/09/2025', 'data_intern' => '18/04/2026', 'hora_intern' => '12:22', 'matricula' => '095436201', 'senha' => '202600215447', 'leito' => 'UTI PED 510', 'num_atendimento' => '53976257'],
    ['page' => 27, 'nome' => 'Monica Mincon Pereira', 'mae' => 'Ivete Mincon Pereira', 'sexo' => 'f', 'data_nasc' => '04/10/1982', 'data_intern' => '20/04/2026', 'hora_intern' => '14:55', 'matricula' => '092857509', 'senha' => 'C02026004255886', 'leito' => 'UI 1372 AP', 'num_atendimento' => '54003411'],
    ['page' => 31, 'nome' => 'Evelyn Antonio do Nascimento Brandao', 'mae' => 'Edna Antonio Vieira', 'sexo' => 'f', 'data_nasc' => '11/12/1991', 'data_intern' => '13/04/2026', 'hora_intern' => '09:43', 'matricula' => '087926157', 'senha' => '202600204518', 'leito' => 'UI 602/B', 'num_atendimento' => '53831734'],
    ['page' => 32, 'nome' => 'Waldomiro Marcelino de Jesus', 'mae' => 'Benedita Xavier De Jesus', 'sexo' => 'm', 'data_nasc' => '10/12/1965', 'data_intern' => '13/04/2026', 'hora_intern' => '11:10', 'matricula' => '084625290', 'senha' => '202600204410', 'leito' => 'UTI 1869', 'num_atendimento' => '53835762'],
    ['page' => 33, 'nome' => 'Jessica Aline Lima dos Santos', 'mae' => 'Alba Lucia Neves De Lima Santos', 'sexo' => 'f', 'data_nasc' => '17/03/1989', 'data_intern' => '13/04/2026', 'hora_intern' => '08:44', 'matricula' => '083672762', 'senha' => '202600204148', 'leito' => 'UI 1776', 'num_atendimento' => '53828970'],
    ['page' => 34, 'nome' => 'Julia Akiko Miyazaki', 'mae' => 'Ditsu Kubata', 'sexo' => 'f', 'data_nasc' => '11/02/1947', 'data_intern' => '13/04/2026', 'hora_intern' => '20:28', 'matricula' => '081328007', 'senha' => '202600204552', 'leito' => 'UI 619', 'num_atendimento' => '53853098'],
    ['page' => 35, 'nome' => 'Ana Patricia Cavalari Pimentel Araujo', 'mae' => 'Waldecira Cavalari', 'sexo' => 'f', 'data_nasc' => '20/05/1976', 'data_intern' => '15/04/2026', 'hora_intern' => '01:47', 'matricula' => '087523212', 'senha' => '202600207346', 'leito' => 'UTI 10', 'num_atendimento' => '53884129'],
    ['page' => 36, 'nome' => 'Anny Sophia da Silva Lino', 'mae' => 'Amanda Cristina Da Silva Lino', 'sexo' => 'f', 'data_nasc' => '03/01/2026', 'data_intern' => '15/04/2026', 'hora_intern' => '13:41', 'matricula' => '096308288', 'senha' => '202600209611', 'leito' => 'UI 525 A', 'num_atendimento' => '53900224'],
    ['page' => 37, 'nome' => 'Leandro Pereira da Costa', 'mae' => 'Gildete Pereira Da Costa', 'sexo' => 'm', 'data_nasc' => '10/03/1987', 'data_intern' => '16/04/2026', 'hora_intern' => '13:50', 'matricula' => '087277613', 'senha' => '202600209726', 'leito' => 'UI 707/B', 'num_atendimento' => '53929555'],
    ['page' => 38, 'nome' => 'Daisy Aparecida Fernandes Cardoso', 'mae' => 'Rosa Fernandes', 'sexo' => 'f', 'data_nasc' => '22/04/1954', 'data_intern' => '16/04/2026', 'hora_intern' => '10:34', 'matricula' => '217227775', 'senha' => '202600211945', 'leito' => 'UTI 04', 'num_atendimento' => '53923334'],
    ['page' => 39, 'nome' => 'Zayan Lucca Feitosa de Oliveira', 'mae' => 'Veronica Ferreira De Oliveira', 'sexo' => 'm', 'data_nasc' => '06/05/2025', 'data_intern' => '14/04/2026', 'hora_intern' => '10:31', 'matricula' => '094265017', 'senha' => 'CO2026004006570', 'leito' => 'UI 519', 'num_atendimento' => '53863966'],
    ['page' => 40, 'nome' => 'Rivania Cardoso de Almeida', 'mae' => 'Railde Pereira Dos Santos', 'sexo' => 'f', 'data_nasc' => '11/07/1986', 'data_intern' => '15/04/2026', 'hora_intern' => '20:52', 'matricula' => '085146202', 'senha' => '202600209738', 'leito' => 'UI 1378/B', 'num_atendimento' => '53911562'],
    ['page' => 41, 'nome' => 'Jessica Dias Pereira', 'mae' => 'Gislaine Aparecida Vieira Dias', 'sexo' => 'f', 'data_nasc' => '30/04/1998', 'data_intern' => '18/04/2026', 'hora_intern' => '13:12', 'matricula' => '095528583', 'senha' => 'CO2026004215717', 'leito' => 'MAT - 803', 'num_atendimento' => '53976860'],
    ['page' => 42, 'nome' => 'Maria Alice Machado Bezerra Pansera', 'mae' => 'Jessika Cristine Machado Pansera', 'sexo' => 'f', 'data_nasc' => '08/04/2025', 'data_intern' => '15/04/2026', 'hora_intern' => '12:46', 'matricula' => '094195130', 'senha' => '202600209514', 'leito' => 'UTI PED 506', 'num_atendimento' => '53898440'],
    ['page' => 43, 'nome' => 'Marina Benfica Garcia', 'mae' => 'Jamille Gleicy Benfica Garcia', 'sexo' => 'f', 'data_nasc' => '08/08/2025', 'data_intern' => '16/04/2026', 'hora_intern' => '17:44', 'matricula' => '097060796', 'senha' => '202600215381', 'leito' => 'UTI Pediatria', 'num_atendimento' => '53937114'],
    ['page' => 44, 'nome' => 'Luccas Ferrari Scauri', 'mae' => 'Carla Ferrari', 'sexo' => 'm', 'data_nasc' => '27/03/2026', 'data_intern' => '15/04/2026', 'hora_intern' => '18:29', 'matricula' => '096992414', 'senha' => '202600209680', 'leito' => 'UTI PED 011', 'num_atendimento' => '53909337'],
    ['page' => 45, 'nome' => 'Lorena Barbosa Domingos', 'mae' => 'Thamara Regina Barbosa Domingos', 'sexo' => 'f', 'data_nasc' => '28/06/2025', 'data_intern' => '15/04/2026', 'hora_intern' => '20:29', 'matricula' => '094728030', 'senha' => '202600208917', 'leito' => 'UTI PED 05', 'num_atendimento' => '53893960'],
    ['page' => 46, 'nome' => 'Gabriel de Brito Madureira', 'mae' => 'Loriane De Brito Delgado', 'sexo' => 'm', 'data_nasc' => '05/12/1999', 'data_intern' => '15/04/2026', 'hora_intern' => '07:55', 'matricula' => '094650699', 'senha' => '202600208729', 'leito' => 'UTI 28', 'num_atendimento' => '53887320'],
    ['page' => 47, 'nome' => 'Francisco Assis Costa', 'mae' => 'Zuleide Galvao Da Costa', 'sexo' => 'm', 'data_nasc' => '18/07/1970', 'data_intern' => '12/04/2026', 'hora_intern' => '10:17', 'matricula' => '424558670', 'senha' => '202600201993', 'leito' => 'UTI 32', 'num_atendimento' => '53816798'],
    ['page' => 48, 'nome' => 'Thais Ferrari', 'mae' => 'Marenise Kerber', 'sexo' => 'f', 'data_nasc' => '15/09/1994', 'data_intern' => '16/04/2026', 'hora_intern' => '02:11', 'matricula' => '084327061', 'senha' => 'C02026004104001', 'leito' => 'UI 1766', 'num_atendimento' => '53913246'],
    ['page' => 49, 'nome' => 'Elisabeth Macedo Couto', 'mae' => 'Yone M Oliveira', 'sexo' => 'f', 'data_nasc' => '24/12/1954', 'data_intern' => '17/04/2026', 'hora_intern' => '11:50', 'matricula' => '095576345', 'senha' => '202600214091', 'leito' => 'UI 601', 'num_atendimento' => '53954294'],
    ['page' => 50, 'nome' => 'Emily Silva Ferreira', 'mae' => 'Solange Jesus Silva', 'sexo' => 'f', 'data_nasc' => '03/10/2007', 'data_intern' => '13/04/2026', 'hora_intern' => '13:34', 'matricula' => '094122667', 'senha' => '202600204554', 'leito' => 'UI 605/B', 'num_atendimento' => '53840536'],
    ['page' => 51, 'nome' => 'Matheus Ferreira Braz', 'mae' => 'Alessandra Costa Braz Ferreira', 'sexo' => 'f', 'data_nasc' => '17/05/2025', 'data_intern' => '13/04/2026', 'hora_intern' => '19:46', 'matricula' => '094506825', 'senha' => '202600204697', 'leito' => 'UI 518', 'num_atendimento' => '53852471'],
    ['page' => 52, 'nome' => 'Jose Viana dos Reis', 'mae' => 'Auristela Viana Dos Reis', 'sexo' => 'm', 'data_nasc' => '31/05/1968', 'data_intern' => '14/04/2026', 'hora_intern' => '02:33', 'matricula' => '076341553', 'senha' => '202600204726', 'leito' => 'UI 707/B', 'num_atendimento' => '53855080'],
];

function toDbDate(?string $value): ?string
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

function inferAcomodacao(string $leito): string
{
    $upper = mb_strtoupper($leito, 'UTF-8');
    return strpos($upper, 'UTI') !== false ? 'UTI' : 'APTO/ENF';
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

function createPaciente(PDO $conn, array $entry, int $seguradoraId, int $userId, string $userName): int
{
    $stmt = $conn->prepare("
        INSERT INTO tb_paciente (
            nome_pac, data_nasc_pac, ativo_pac, mae_pac, fk_estipulante_pac,
            sexo_pac, fk_seguradora_pac, matricula_pac, usuario_create_pac,
            fk_usuario_pac, deletado_pac, data_create_pac, recem_nascido_pac
        ) VALUES (
            :nome, :data_nasc, 's', :mae, 1,
            :sexo, :seguradora, :matricula, :usuario_create,
            :fk_usuario, 'n', NOW(), :recem_nascido
        )
    ");
    $stmt->bindValue(':nome', $entry['nome'], PDO::PARAM_STR);
    $stmt->bindValue(':data_nasc', toDbDate($entry['data_nasc']));
    $stmt->bindValue(':mae', $entry['mae'], PDO::PARAM_STR);
    $stmt->bindValue(':sexo', $entry['sexo'], PDO::PARAM_STR);
    $stmt->bindValue(':seguradora', $seguradoraId, PDO::PARAM_INT);
    $stmt->bindValue(':matricula', $entry['matricula'], PDO::PARAM_STR);
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

function createInternacao(PDO $conn, array $entry, int $pacienteId, int $hospitalId, int $userId, string $usuarioCreateInt): int
{
    $stmt = $conn->prepare("
        INSERT INTO tb_internacao (
            fk_paciente_int, fk_hospital_int, usuario_create_int, data_intern_int,
            data_lancamento_int, hora_intern_int, acomodacao_int, internado_int,
            senha_int, data_create_int, primeira_vis_int, visita_no_int,
            visita_enf_int, visita_med_int, fk_usuario_int, censo_int,
            programacao_int, origem_int, int_pertinente_int, rel_pertinente_int,
            deletado_int, num_atendimento_int
        ) VALUES (
            :paciente, :hospital, :usuario_create, :data_intern,
            NOW(), :hora_intern, :acomodacao, 's',
            :senha, NOW(), 's', '0',
            'n', 'n', :fk_usuario, 'n',
            '', '', '', '',
            'n', :num_atendimento
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

function validateEntry(array $entry): array
{
    $errors = [];
    foreach (['nome', 'data_nasc', 'data_intern', 'hora_intern', 'senha', 'num_atendimento'] as $field) {
        if (!isset($entry[$field]) || trim((string)$entry[$field]) === '') {
            $errors[] = "campo {$field} vazio";
        }
    }
    if (toDbDate($entry['data_nasc'] ?? null) === null) {
        $errors[] = 'data_nasc invalida';
    }
    if (toDbDate($entry['data_intern'] ?? null) === null) {
        $errors[] = 'data_intern invalida';
    }

    return $errors;
}

$summary = [
    'mode' => $commit ? 'commit' : 'dry-run',
    'total' => count($pending),
    'pacientes_created' => 0,
    'pacientes_existing' => 0,
    'internacoes_created' => 0,
    'internacoes_existing' => 0,
    'skipped' => 0,
];

foreach ($pending as $entry) {
    $entry['nome'] = trim($entry['nome']);
    $entry['mae'] = trim($entry['mae']);
    $validationErrors = validateEntry($entry);
    if ($validationErrors) {
        $summary['skipped']++;
        echo json_encode([
            'page' => $entry['page'],
            'nome' => $entry['nome'],
            'status' => 'skipped',
            'errors' => $validationErrors,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        continue;
    }

    $conn->beginTransaction();
    try {
        $paciente = findPaciente($conn, $entry);
        $pacienteCreated = false;
        $pacienteId = $paciente ? (int)$paciente['id_paciente'] : 0;

        if (!$paciente && $commit) {
            $pacienteId = createPaciente($conn, $entry, $seguradoraAmilId, $importUserId, $importUserName);
            $pacienteCreated = true;
        }

        $internacao = $pacienteId > 0 ? findInternacao($conn, $pacienteId, $entry['senha']) : null;
        $internacaoCreated = false;
        $internacaoId = $internacao ? (int)$internacao['id_internacao'] : 0;

        if (!$internacao && $commit) {
            if ($pacienteId <= 0) {
                $pacienteId = createPaciente($conn, $entry, $seguradoraAmilId, $importUserId, $importUserName);
                $pacienteCreated = true;
            }
            $internacaoId = createInternacao($conn, $entry, $pacienteId, $hospitalSinoId, $importUserId, $usuarioCreateInt);
            $internacaoCreated = true;
        }

        if ($commit) {
            $conn->commit();
        } else {
            $conn->rollBack();
        }

        $statusPaciente = $pacienteCreated ? 'created' : ($paciente ? 'existing' : 'would_create');
        $statusInternacao = $internacaoCreated ? 'created' : ($internacao ? 'existing' : 'would_create');

        $summary[$statusPaciente === 'existing' ? 'pacientes_existing' : 'pacientes_created']++;
        $summary[$statusInternacao === 'existing' ? 'internacoes_existing' : 'internacoes_created']++;

        echo json_encode([
            'page' => $entry['page'],
            'nome' => $entry['nome'],
            'paciente_id' => $pacienteId ?: null,
            'internacao_id' => $internacaoId ?: null,
            'status_paciente' => $statusPaciente,
            'status_internacao' => $statusInternacao,
            'senha' => $entry['senha'],
            'matricula' => $entry['matricula'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $summary['skipped']++;
        echo json_encode([
            'page' => $entry['page'],
            'nome' => $entry['nome'],
            'status' => 'error',
            'error' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
}

echo json_encode(['summary' => $summary], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
