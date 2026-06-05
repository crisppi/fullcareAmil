<?php
// process_rah.php
declare(strict_types=1);

if (!defined("FLOW_LOGGER_AUTO_V1")) {
    define("FLOW_LOGGER_AUTO_V1", 1);
    @require_once(__DIR__ . "/../../utils/flow_logger.php");
    if (function_exists("flowLogStart") && function_exists("flowLog")) {
        $__flowCtxAuto = flowLogStart(basename(__FILE__, ".php"), [
            "type" => $_POST["type"] ?? $_GET["type"] ?? null,
            "method" => $_SERVER["REQUEST_METHOD"] ?? null,
        ]);
        register_shutdown_function(function () use ($__flowCtxAuto) {
            $err = error_get_last();
            if ($err && in_array(($err["type"] ?? 0), [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                flowLog($__flowCtxAuto, "shutdown.fatal", "ERROR", [
                    "message" => $err["message"] ?? null,
                    "file" => $err["file"] ?? null,
                    "line" => $err["line"] ?? null,
                ]);
            }
            flowLog($__flowCtxAuto, "request.finish", "INFO");
        });
    }
}
require_once 'globals.php';
require_once 'db.php';
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function h(?string $v)
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

$id_capeante = filter_input(INPUT_POST, 'id_capeante', FILTER_VALIDATE_INT);
$id_internacao = filter_input(INPUT_POST, 'id_internacao', FILTER_VALIDATE_INT); // vindo do form (hidden/select)
if (!$id_internacao) {
    throw new RuntimeException("id_internacao obrigatório.");
}

$hojeYMD = (new DateTime('now'))->format('Y-m-d');
$dataDigit = $_POST['data_digit_capeante'] ?? null;
if (!$dataDigit) {
    throw new RuntimeException("data_digit_capeante obrigatória.");
}

try {
    $conn->beginTransaction();

    // 1) Garante o cabeçalho do capeante
    if (!$id_capeante) {
        // cria
        $stmt = $conn->prepare("
      INSERT INTO tb_capeante (fk_int_capeante, data_fech_capeante, data_digit_capeante, pacote)
      VALUES (:fk, :fech, :digit, :pacote)
    ");
        $stmt->execute([
            ':fk'     => $id_internacao,
            ':fech'   => $_POST['data_fech_capeante'] ?? $hojeYMD,
            ':digit'  => $dataDigit,
            ':pacote' => ($_POST['pacote'] ?? 'n')
        ]);
        $id_capeante = (int)$conn->lastInsertId();
    } else {
        // atualiza (id_capeante já existe)
        $stmt = $conn->prepare("
      UPDATE tb_capeante
         SET fk_int_capeante = :fk,
             data_fech_capeante = :fech,
             data_digit_capeante = :digit,
             pacote = :pacote
       WHERE id_capeante = :id
    ");
        $stmt->execute([
            ':fk'    => $id_internacao,
            ':fech'  => $_POST['data_fech_capeante'] ?? $hojeYMD,
            ':digit' => $dataDigit,
            ':pacote' => ($_POST['pacote'] ?? 'n'),
            ':id'    => $id_capeante
        ]);
    }

    // ⚠️ IMPORTANTE SOBRE FKs NOS GRUPOS:
    // Prefira que as tabelas de valores referenciem SEMPRE fk_capeante (-> tb_capeante.id_capeante).
    // Se você também mantiver fk_int_capeante nelas, preencha ambos consistentemente.
    // A ordem de gravação deve ser: cria/garante tb_capeante -> insere/atualiza grupos usando fk_capeante (e fk_int_capeante = $id_internacao).

    // Exemplo de upsert simples para um grupo (adapte para cada tabela/grupo):
    // Supondo tabela: tb_cap_valores_diar (id auto, fk_capeante, opcional fk_int_capeante, campos *_qtd, *_cobrado, *_glosado, *_obs, *_liberado)
    if (isset($_POST['diarias'])) {
        // $_POST['diarias'] pode ser um array com linhas do grupo
        // Limpa e regrava (estratégia simples e robusta):
        $conn->prepare("DELETE FROM tb_cap_valores_diar WHERE fk_capeante = :c")
            ->execute([':c' => $id_capeante]);

        $ins = $conn->prepare("
      INSERT INTO tb_cap_valores_diar
        (fk_capeante, fk_int_capeante, desc_item, qtd, valor_cobrado, valor_glosado, valor_liberado, obs)
      VALUES
        (:capeante, :int, :desc, :qtd, :cob, :glo, (:cob - :glo), :obs)
    ");
        foreach ($_POST['diarias'] as $linha) {
            $ins->execute([
                ':capeante' => $id_capeante,
                ':int'      => $id_internacao,
                ':desc'     => trim($linha['desc'] ?? ''),
                ':qtd'      => (float)($linha['qtd'] ?? 0),
                ':cob'      => (float)($linha['valor_cobrado'] ?? 0),
                ':glo'      => (float)($linha['valor_glosado'] ?? 0),
                ':obs'      => trim($linha['obs'] ?? '')
            ]);
        }
    }

    // Repita a mesma lógica para UTI, CC, exames, materiais/OPME, honorários, OUTROS, etc.

    $conn->commit();

    // 3) PRG — redireciona para evitar re-post e já carregar por id_capeante
    header("Location: form_capeante_auditRah.php?id_capeante=" . $id_capeante);
    exit;
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    // log/mostrar erro amigável
    http_response_code(500);
    echo "Erro ao salvar: " . h($e->getMessage());
}
