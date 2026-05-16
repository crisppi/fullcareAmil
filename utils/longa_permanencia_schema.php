<?php

if (!function_exists('fullcareEnsureLongaPermanenciaSchema')) {
    function fullcareEnsureLongaPermanenciaSchema(PDO $conn): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        $sql = "
            CREATE TABLE IF NOT EXISTS tb_longa_permanencia_gestao (
                id_longa_perm INT UNSIGNED NOT NULL AUTO_INCREMENT,
                fk_internacao_lp INT NOT NULL,
                fk_usuario_lp INT NULL,
                data_atualizacao_lp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                status_lp VARCHAR(50) NULL,
                motivo_principal_lp VARCHAR(120) NULL,
                barreira_clinica_lp TEXT NULL,
                barreira_administrativa_lp TEXT NULL,
                plano_acao_lp TEXT NULL,
                responsavel_lp VARCHAR(120) NULL,
                prazo_acao_lp DATE NULL,
                previsao_alta_lp DATE NULL,
                proxima_revisao_lp DATE NULL,
                potencial_desospitalizacao_lp CHAR(1) NOT NULL DEFAULT 'n',
                necessita_escalonamento_lp CHAR(1) NOT NULL DEFAULT 'n',
                risco_sinistro_lp VARCHAR(30) NULL,
                observacoes_lp TEXT NULL,
                updated_at_lp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id_longa_perm),
                KEY idx_lp_internacao (fk_internacao_lp),
                KEY idx_lp_status (status_lp),
                KEY idx_lp_data (data_atualizacao_lp),
                KEY idx_lp_revisao (proxima_revisao_lp)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        $conn->exec($sql);
        $ensured = true;
    }
}
