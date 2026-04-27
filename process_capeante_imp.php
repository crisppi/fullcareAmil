<?php

if (!defined("FLOW_LOGGER_AUTO_V1")) {
    define("FLOW_LOGGER_AUTO_V1", 1);
    @require_once(__DIR__ . "/utils/flow_logger.php");
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


require_once("globals.php");
require_once("db.php");
require_once("dao/capeanteDao.php");
require_once("utils/audit_logger.php");

// Instantiate capeanteDAO
$capeanteDao = new capeanteDAO($conn, $BASE_URL);

// Get the ID from POST data
$id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT); // Validate as integer

if ($id) {
    // Find capeante by ID
    $capeante = $capeanteDao->findById($id);

    if ($capeante) {
        $before = clone $capeante;
        // Set the "impresso_cap" field to 's'
        $capeante->impresso_cap = 's';

        // Update the capeante in the database
        $capeanteDao->update($capeante);
        fullcareAuditLog($conn, [
            'action' => 'update',
            'entity_type' => 'capeante',
            'entity_id' => (int)$id,
            'before' => $before,
            'after' => $capeante,
            'summary' => 'Capeante marcado como impresso.',
            'source' => 'process_capeante_imp.php',
        ], $BASE_URL);
        echo "Record updated successfully.";
    } else {
        echo "Record not found.";
    }
} else {
    echo "Invalid ID.";
}
