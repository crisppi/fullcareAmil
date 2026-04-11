<?php

if (!function_exists('ensure_visita_timer_column')) {
    function ensure_visita_timer_column(PDO $conn): void
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;
        try {
            $stmt = $conn->query("
                SELECT COUNT(*) 
                  FROM information_schema.COLUMNS 
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'tb_visita'
                   AND COLUMN_NAME = 'timer_vis'
            ");
            $exists = (int)$stmt->fetchColumn() > 0;
            if ($exists) {
                return;
            }
            $conn->exec("
                ALTER TABLE tb_visita
                ADD COLUMN timer_vis INT NULL DEFAULT NULL AFTER programacao_enf
            ");
        } catch (Throwable $e) {
            error_log('[SCHEMA][timer_vis] ' . $e->getMessage());
        }
    }
}

if (!function_exists('ensure_internacao_timer_column')) {
    function ensure_internacao_timer_column(PDO $conn): void
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;
        try {
            $stmt = $conn->query("
                SELECT COUNT(*)
                  FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'tb_internacao'
                   AND COLUMN_NAME = 'timer_int'
            ");
            $exists = (int)$stmt->fetchColumn() > 0;
            if ($exists) return;
            $conn->exec("
                ALTER TABLE tb_internacao
                ADD COLUMN timer_int INT NULL DEFAULT NULL AFTER num_atendimento_int
            ");
        } catch (Throwable $e) {
            error_log('[SCHEMA][timer_int] ' . $e->getMessage());
        }
    }
}

if (!function_exists('ensure_internacao_forecast_columns')) {
    function ensure_internacao_forecast_columns(PDO $conn): void
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        $columns = [
            'forecast_total_days' => "ALTER TABLE tb_internacao ADD COLUMN forecast_total_days DECIMAL(6,2) NULL DEFAULT NULL AFTER timer_int",
            'forecast_lower_days' => "ALTER TABLE tb_internacao ADD COLUMN forecast_lower_days DECIMAL(6,2) NULL DEFAULT NULL AFTER forecast_total_days",
            'forecast_upper_days' => "ALTER TABLE tb_internacao ADD COLUMN forecast_upper_days DECIMAL(6,2) NULL DEFAULT NULL AFTER forecast_lower_days",
            'forecast_generated_at' => "ALTER TABLE tb_internacao ADD COLUMN forecast_generated_at DATETIME NULL DEFAULT NULL AFTER forecast_upper_days",
            'forecast_model' => "ALTER TABLE tb_internacao ADD COLUMN forecast_model VARCHAR(60) NULL DEFAULT NULL AFTER forecast_generated_at",
            'forecast_confidence' => "ALTER TABLE tb_internacao ADD COLUMN forecast_confidence TINYINT NULL DEFAULT NULL AFTER forecast_model",
        ];

        foreach ($columns as $column => $ddl) {
            try {
                $stmt = $conn->prepare("
                    SELECT COUNT(*)
                      FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME = 'tb_internacao'
                       AND COLUMN_NAME = :column
                ");
                $stmt->bindValue(':column', $column, PDO::PARAM_STR);
                $stmt->execute();
                $exists = (int)$stmt->fetchColumn() > 0;
                if ($exists) {
                    continue;
                }
                $conn->exec($ddl);
            } catch (Throwable $e) {
                error_log('[SCHEMA][forecast:' . $column . '] ' . $e->getMessage());
            }
        }
    }
}

if (!function_exists('ensure_internacao_core_columns')) {
    function ensure_internacao_core_columns(PDO $conn): void
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        try {
            $stmt = $conn->query(" 
                SELECT COUNT(*)
                  FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'tb_internacao'
                   AND COLUMN_NAME = 'fk_cid_int'
            ");
            $hasFkCid = (int)$stmt->fetchColumn() > 0;
            if ($hasFkCid) {
                return;
            }

            $stmtPos = $conn->query(" 
                SELECT COUNT(*)
                  FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'tb_internacao'
                   AND COLUMN_NAME = 'fk_patologia_int'
            ");
            $hasFkPatologia = (int)$stmtPos->fetchColumn() > 0;

            if ($hasFkPatologia) {
                $conn->exec(" 
                    ALTER TABLE tb_internacao
                    ADD COLUMN fk_cid_int INT NULL DEFAULT NULL AFTER fk_patologia_int
                ");
            } else {
                $conn->exec(" 
                    ALTER TABLE tb_internacao
                    ADD COLUMN fk_cid_int INT NULL DEFAULT NULL
                ");
            }
        } catch (Throwable $e) {
            error_log('[SCHEMA][internacao:fk_cid_int] ' . $e->getMessage());
        }
    }
}

if (!function_exists('ensure_visita_faturamento_columns')) {
    function ensure_visita_faturamento_columns(PDO $conn): void
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        try {
            $stmtFat = $conn->query(" 
                SELECT COUNT(*)
                  FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'tb_visita'
                   AND COLUMN_NAME = 'faturado_vis'
            ");
            $hasFaturado = (int)$stmtFat->fetchColumn() > 0;
            if (!$hasFaturado) {
                $stmtAfter = $conn->query(" 
                    SELECT COUNT(*)
                      FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME = 'tb_visita'
                       AND COLUMN_NAME = 'data_lancamento_vis'
                ");
                $hasLancamento = (int)$stmtAfter->fetchColumn() > 0;
                if ($hasLancamento) {
                    $conn->exec(" 
                        ALTER TABLE tb_visita
                        ADD COLUMN faturado_vis VARCHAR(5) NULL DEFAULT 'n' AFTER data_lancamento_vis
                    ");
                } else {
                    $conn->exec(" 
                        ALTER TABLE tb_visita
                        ADD COLUMN faturado_vis VARCHAR(5) NULL DEFAULT 'n'
                    ");
                }
            }

            $stmtDataFat = $conn->query(" 
                SELECT COUNT(*)
                  FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'tb_visita'
                   AND COLUMN_NAME = 'data_faturamento_vis'
            ");
            $hasDataFaturamento = (int)$stmtDataFat->fetchColumn() > 0;
            if (!$hasDataFaturamento) {
                $stmtAfterFat = $conn->query(" 
                    SELECT COUNT(*)
                      FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME = 'tb_visita'
                       AND COLUMN_NAME = 'faturado_vis'
                ");
                $hasFaturadoNow = (int)$stmtAfterFat->fetchColumn() > 0;
                if ($hasFaturadoNow) {
                    $conn->exec(" 
                        ALTER TABLE tb_visita
                        ADD COLUMN data_faturamento_vis DATE NULL AFTER faturado_vis
                    ");
                } else {
                    $conn->exec(" 
                        ALTER TABLE tb_visita
                        ADD COLUMN data_faturamento_vis DATE NULL
                    ");
                }
            }
        } catch (Throwable $e) {
            error_log('[SCHEMA][visita:faturamento] ' . $e->getMessage());
        }
    }
}

if (!function_exists('ensure_schema_version_table')) {
    function ensure_schema_version_table(PDO $conn): void
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;
        try {
            $stmt = $conn->prepare("
                SELECT COUNT(*)
                  FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'schema_version'
            ");
            $stmt->execute();
            $exists = (int)$stmt->fetchColumn() > 0;
            if ($exists) {
                return;
            }
            $conn->exec("
                CREATE TABLE schema_version (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    version VARCHAR(64) NOT NULL,
                    description TEXT NULL,
                    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    applied_by VARCHAR(150) NULL,
                    file_name VARCHAR(150) NULL,
                    checksum CHAR(64) NULL,
                    UNIQUE KEY uq_schema_version (version)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (Throwable $e) {
            error_log('[SCHEMA][schema_version] ' . $e->getMessage());
        }
    }
}

if (!function_exists('ensure_password_reset_table')) {
    function ensure_password_reset_table(PDO $conn): void
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;
        try {
            $stmt = $conn->prepare("
                SELECT COUNT(*)
                  FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'tb_user_password_reset'
            ");
            $stmt->execute();
            $exists = (int)$stmt->fetchColumn() > 0;
            if ($exists) {
                return;
            }
            $conn->exec("
                CREATE TABLE tb_user_password_reset (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    email VARCHAR(191) NOT NULL,
                    token_hash CHAR(64) NOT NULL,
                    code_hash CHAR(64) NOT NULL,
                    expires_at DATETIME NOT NULL,
                    used_at DATETIME NULL DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    request_ip VARCHAR(64) NULL DEFAULT NULL,
                    user_agent VARCHAR(191) NULL DEFAULT NULL,
                    UNIQUE KEY uq_token_hash (token_hash),
                    KEY idx_user_id (user_id),
                    KEY idx_email (email),
                    KEY idx_expires_at (expires_at),
                    KEY idx_used_at (used_at),
                    CONSTRAINT fk_password_reset_user
                        FOREIGN KEY (user_id) REFERENCES tb_user(id_usuario)
                        ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (Throwable $e) {
            error_log('[SCHEMA][tb_user_password_reset] ' . $e->getMessage());
        }
    }
}

if (!function_exists('ensure_user_login_security_columns')) {
    function ensure_user_login_security_columns(PDO $conn): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        $columns = [
            'login_fail_count' => "ALTER TABLE tb_user ADD COLUMN login_fail_count INT NOT NULL DEFAULT 0 AFTER senha_default_user",
            'login_locked_until' => "ALTER TABLE tb_user ADD COLUMN login_locked_until DATETIME NULL DEFAULT NULL AFTER login_fail_count",
            'login_last_fail_at' => "ALTER TABLE tb_user ADD COLUMN login_last_fail_at DATETIME NULL DEFAULT NULL AFTER login_locked_until",
        ];

        foreach ($columns as $column => $ddl) {
            try {
                $stmt = $conn->prepare("
                    SELECT COUNT(*)
                      FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME = 'tb_user'
                       AND COLUMN_NAME = :column
                ");
                $stmt->bindValue(':column', $column, PDO::PARAM_STR);
                $stmt->execute();
                $exists = (int)$stmt->fetchColumn() > 0;
                if ($exists) {
                    continue;
                }
                $conn->exec($ddl);
            } catch (Throwable $e) {
                error_log('[SCHEMA][tb_user:' . $column . '] ' . $e->getMessage());
            }
        }
    }
}

if (!function_exists('schema_table_exists')) {
    function schema_table_exists(PDO $conn, string $table): bool
    {
        static $tableMap = null;
        if ($tableMap === null) {
            $tableMap = [];
            $stmt = $conn->query("
                SELECT TABLE_NAME
                  FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
            ");
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $tableName) {
                $tableMap[(string)$tableName] = true;
            }
        }
        return !empty($tableMap[$table]);
    }
}

if (!function_exists('schema_columns_exist')) {
    function schema_columns_exist(PDO $conn, string $table, array $columns): bool
    {
        if (!$columns) {
            return false;
        }
        static $columnsByTable = null;
        if ($columnsByTable === null) {
            $columnsByTable = [];
            $stmt = $conn->query("
                SELECT TABLE_NAME, COLUMN_NAME
                  FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
            ");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $t = (string)($row['TABLE_NAME'] ?? '');
                $c = (string)($row['COLUMN_NAME'] ?? '');
                if ($t === '' || $c === '') {
                    continue;
                }
                if (!isset($columnsByTable[$t])) {
                    $columnsByTable[$t] = [];
                }
                $columnsByTable[$t][$c] = true;
            }
        }

        $current = $columnsByTable[$table] ?? [];
        foreach ($columns as $col) {
            if (empty($current[(string)$col])) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('schema_index_exists')) {
    function schema_index_exists(PDO $conn, string $table, string $indexName, array $columns): bool
    {
        static $indexNamesByTable = null;
        static $indexColsByTable = null;
        if ($indexNamesByTable === null || $indexColsByTable === null) {
            $indexNamesByTable = [];
            $indexColsByTable = [];
            $stmt = $conn->query("
                SELECT TABLE_NAME, INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') AS cols
                  FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                 GROUP BY TABLE_NAME, INDEX_NAME
            ");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $t = (string)($row['TABLE_NAME'] ?? '');
                $i = (string)($row['INDEX_NAME'] ?? '');
                $c = (string)($row['cols'] ?? '');
                if ($t === '' || $i === '') {
                    continue;
                }
                if (!isset($indexNamesByTable[$t])) {
                    $indexNamesByTable[$t] = [];
                }
                if (!isset($indexColsByTable[$t])) {
                    $indexColsByTable[$t] = [];
                }
                $indexNamesByTable[$t][$i] = true;
                if ($c !== '') {
                    $indexColsByTable[$t][$c] = true;
                }
            }
        }

        if (!empty($indexNamesByTable[$table][$indexName])) {
            return true;
        }

        $target = implode(',', $columns);
        return !empty($indexColsByTable[$table][$target]);
    }
}

if (!function_exists('ensure_index_if_missing')) {
    function ensure_index_if_missing(PDO $conn, string $table, string $indexName, array $columns): void
    {
        try {
            if (!schema_table_exists($conn, $table)) {
                return;
            }
            if (!schema_columns_exist($conn, $table, $columns)) {
                return;
            }
            if (schema_index_exists($conn, $table, $indexName, $columns)) {
                return;
            }
            $colSql = implode(', ', array_map(static fn($c) => "`{$c}`", $columns));
            $conn->exec("ALTER TABLE `{$table}` ADD INDEX `{$indexName}` ({$colSql})");
        } catch (Throwable $e) {
            error_log('[SCHEMA][INDEX][' . $table . '][' . $indexName . '] ' . $e->getMessage());
        }
    }
}

if (!function_exists('ensure_operational_list_indexes')) {
    function ensure_operational_list_indexes(PDO $conn): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        $indexes = [
            ['tb_internacao', 'idx_int_internado_data', ['internado_int', 'data_intern_int']],
            ['tb_internacao', 'idx_int_hospital_data', ['fk_hospital_int', 'data_intern_int']],
            ['tb_internacao', 'idx_int_paciente_data', ['fk_paciente_int', 'data_intern_int']],
            ['tb_visita', 'idx_vis_internacao_data', ['fk_internacao_vis', 'data_visita_vis']],
            ['tb_prorrogacao', 'idx_pror_internacao_periodo', ['fk_internacao_pror', 'prorrog1_ini_pror', 'prorrog1_fim_pror']],
            ['tb_gestao', 'idx_ges_internacao_evento', ['fk_internacao_ges', 'evento_adverso_ges', 'evento_encerrar_ges']],
            ['tb_alta', 'idx_alta_internacao_data', ['fk_id_int_alt', 'data_alta_alt']],
            ['tb_uti', 'idx_uti_internacao_data', ['fk_internacao_uti', 'data_internacao_uti', 'data_alta_uti']],
            ['tb_negociacao', 'idx_neg_internacao_datas', ['fk_id_int', 'data_inicio_neg', 'data_fim_neg']],
            ['tb_capeante', 'idx_cap_internacao_status', ['fk_int_capeante', 'encerrado_cap', 'data_inicial_capeante', 'data_final_capeante']],
        ];

        foreach ($indexes as [$table, $indexName, $columns]) {
            ensure_index_if_missing($conn, $table, $indexName, $columns);
        }
    }
}

if (!function_exists('ensure_hospital_related_tables')) {
    function ensure_hospital_related_tables(PDO $conn): void
    {
        static $checked = false;
        if ($checked) return;
        $checked = true;

        $tables = [
            'tb_hospital_endereco' => "
                CREATE TABLE tb_hospital_endereco (
                    id_hospital_endereco INT AUTO_INCREMENT PRIMARY KEY,
                    fk_hospital INT NOT NULL,
                    tipo_endereco VARCHAR(60) NULL,
                    cep_endereco VARCHAR(20) NULL,
                    endereco_endereco VARCHAR(255) NULL,
                    numero_endereco VARCHAR(30) NULL,
                    bairro_endereco VARCHAR(120) NULL,
                    cidade_endereco VARCHAR(120) NULL,
                    estado_endereco VARCHAR(10) NULL,
                    complemento_endereco VARCHAR(120) NULL,
                    principal_endereco TINYINT(1) NOT NULL DEFAULT 0,
                    ativo_endereco CHAR(1) NOT NULL DEFAULT 's',
                    data_create_endereco DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_hosp_endereco_fk (fk_hospital),
                    CONSTRAINT fk_hosp_endereco_hospital
                        FOREIGN KEY (fk_hospital) REFERENCES tb_hospital(id_hospital)
                        ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            'tb_hospital_telefone' => "
                CREATE TABLE tb_hospital_telefone (
                    id_hospital_telefone INT AUTO_INCREMENT PRIMARY KEY,
                    fk_hospital INT NOT NULL,
                    tipo_telefone VARCHAR(40) NULL,
                    numero_telefone VARCHAR(20) NULL,
                    ramal_telefone VARCHAR(20) NULL,
                    contato_telefone VARCHAR(120) NULL,
                    principal_telefone TINYINT(1) NOT NULL DEFAULT 0,
                    ativo_telefone CHAR(1) NOT NULL DEFAULT 's',
                    data_create_telefone DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_hosp_telefone_fk (fk_hospital),
                    CONSTRAINT fk_hosp_telefone_hospital
                        FOREIGN KEY (fk_hospital) REFERENCES tb_hospital(id_hospital)
                        ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            'tb_hospital_contato' => "
                CREATE TABLE tb_hospital_contato (
                    id_hospital_contato INT AUTO_INCREMENT PRIMARY KEY,
                    fk_hospital INT NOT NULL,
                    nome_contato VARCHAR(150) NULL,
                    cargo_contato VARCHAR(120) NULL,
                    setor_contato VARCHAR(120) NULL,
                    email_contato VARCHAR(150) NULL,
                    telefone_contato VARCHAR(20) NULL,
                    principal_contato TINYINT(1) NOT NULL DEFAULT 0,
                    ativo_contato CHAR(1) NOT NULL DEFAULT 's',
                    data_create_contato DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_hosp_contato_fk (fk_hospital),
                    CONSTRAINT fk_hosp_contato_hospital
                        FOREIGN KEY (fk_hospital) REFERENCES tb_hospital(id_hospital)
                        ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            'tb_estipulante_endereco' => "
                CREATE TABLE tb_estipulante_endereco (
                    id_estipulante_endereco INT AUTO_INCREMENT PRIMARY KEY,
                    fk_estipulante INT NOT NULL,
                    tipo_endereco VARCHAR(60) NULL,
                    cep_endereco VARCHAR(20) NULL,
                    endereco_endereco VARCHAR(255) NULL,
                    numero_endereco VARCHAR(30) NULL,
                    bairro_endereco VARCHAR(120) NULL,
                    cidade_endereco VARCHAR(120) NULL,
                    estado_endereco VARCHAR(10) NULL,
                    complemento_endereco VARCHAR(120) NULL,
                    principal_endereco TINYINT(1) NOT NULL DEFAULT 0,
                    ativo_endereco CHAR(1) NOT NULL DEFAULT 's',
                    data_create_endereco DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_est_endereco_fk (fk_estipulante),
                    CONSTRAINT fk_est_endereco_est
                        FOREIGN KEY (fk_estipulante) REFERENCES tb_estipulante(id_estipulante)
                        ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            'tb_estipulante_telefone' => "
                CREATE TABLE tb_estipulante_telefone (
                    id_estipulante_telefone INT AUTO_INCREMENT PRIMARY KEY,
                    fk_estipulante INT NOT NULL,
                    tipo_telefone VARCHAR(40) NULL,
                    numero_telefone VARCHAR(20) NULL,
                    ramal_telefone VARCHAR(20) NULL,
                    contato_telefone VARCHAR(120) NULL,
                    principal_telefone TINYINT(1) NOT NULL DEFAULT 0,
                    ativo_telefone CHAR(1) NOT NULL DEFAULT 's',
                    data_create_telefone DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_est_telefone_fk (fk_estipulante),
                    CONSTRAINT fk_est_telefone_est
                        FOREIGN KEY (fk_estipulante) REFERENCES tb_estipulante(id_estipulante)
                        ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            'tb_estipulante_contato' => "
                CREATE TABLE tb_estipulante_contato (
                    id_estipulante_contato INT AUTO_INCREMENT PRIMARY KEY,
                    fk_estipulante INT NOT NULL,
                    nome_contato VARCHAR(150) NULL,
                    cargo_contato VARCHAR(120) NULL,
                    setor_contato VARCHAR(120) NULL,
                    email_contato VARCHAR(150) NULL,
                    telefone_contato VARCHAR(20) NULL,
                    principal_contato TINYINT(1) NOT NULL DEFAULT 0,
                    ativo_contato CHAR(1) NOT NULL DEFAULT 's',
                    data_create_contato DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_est_contato_fk (fk_estipulante),
                    CONSTRAINT fk_est_contato_est
                        FOREIGN KEY (fk_estipulante) REFERENCES tb_estipulante(id_estipulante)
                        ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            'tb_seguradora_endereco' => "
                CREATE TABLE tb_seguradora_endereco (
                    id_seguradora_endereco INT AUTO_INCREMENT PRIMARY KEY,
                    fk_seguradora INT NOT NULL,
                    tipo_endereco VARCHAR(60) NULL,
                    cep_endereco VARCHAR(20) NULL,
                    endereco_endereco VARCHAR(255) NULL,
                    numero_endereco VARCHAR(30) NULL,
                    bairro_endereco VARCHAR(120) NULL,
                    cidade_endereco VARCHAR(120) NULL,
                    estado_endereco VARCHAR(10) NULL,
                    complemento_endereco VARCHAR(120) NULL,
                    principal_endereco TINYINT(1) NOT NULL DEFAULT 0,
                    ativo_endereco CHAR(1) NOT NULL DEFAULT 's',
                    data_create_endereco DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_seg_endereco_fk (fk_seguradora),
                    CONSTRAINT fk_seg_endereco_seg
                        FOREIGN KEY (fk_seguradora) REFERENCES tb_seguradora(id_seguradora)
                        ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            'tb_seguradora_telefone' => "
                CREATE TABLE tb_seguradora_telefone (
                    id_seguradora_telefone INT AUTO_INCREMENT PRIMARY KEY,
                    fk_seguradora INT NOT NULL,
                    tipo_telefone VARCHAR(40) NULL,
                    numero_telefone VARCHAR(20) NULL,
                    ramal_telefone VARCHAR(20) NULL,
                    contato_telefone VARCHAR(120) NULL,
                    principal_telefone TINYINT(1) NOT NULL DEFAULT 0,
                    ativo_telefone CHAR(1) NOT NULL DEFAULT 's',
                    data_create_telefone DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_seg_telefone_fk (fk_seguradora),
                    CONSTRAINT fk_seg_telefone_seg
                        FOREIGN KEY (fk_seguradora) REFERENCES tb_seguradora(id_seguradora)
                        ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            'tb_seguradora_contato' => "
                CREATE TABLE tb_seguradora_contato (
                    id_seguradora_contato INT AUTO_INCREMENT PRIMARY KEY,
                    fk_seguradora INT NOT NULL,
                    nome_contato VARCHAR(150) NULL,
                    cargo_contato VARCHAR(120) NULL,
                    setor_contato VARCHAR(120) NULL,
                    email_contato VARCHAR(150) NULL,
                    telefone_contato VARCHAR(20) NULL,
                    principal_contato TINYINT(1) NOT NULL DEFAULT 0,
                    ativo_contato CHAR(1) NOT NULL DEFAULT 's',
                    data_create_contato DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_seg_contato_fk (fk_seguradora),
                    CONSTRAINT fk_seg_contato_seg
                        FOREIGN KEY (fk_seguradora) REFERENCES tb_seguradora(id_seguradora)
                        ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
        ];

        foreach ($tables as $tableName => $ddl) {
            try {
                if (schema_table_exists($conn, $tableName)) {
                    continue;
                }
                $conn->exec($ddl);
            } catch (Throwable $e) {
                error_log('[SCHEMA][' . $tableName . '] ' . $e->getMessage());
            }
        }
    }
}
