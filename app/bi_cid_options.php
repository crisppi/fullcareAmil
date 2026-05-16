<?php

if (!function_exists('bi_cid_label_expr')) {
    function bi_cid_label_expr(string $alias = 'c'): string
    {
        return "COALESCE(NULLIF(CONCAT_WS(' - ', NULLIF({$alias}.cat, ''), NULLIF({$alias}.descricao, '')), ''), CONCAT('CID ', {$alias}.id_cid))";
    }
}

if (!function_exists('bi_cid_idade_cond')) {
    function bi_cid_idade_cond(string $faixa, string $alias = 'pa'): ?string
    {
        switch ($faixa) {
            case '0-19':
                return "{$alias}.idade_pac < 20";
            case '20-39':
                return "{$alias}.idade_pac >= 20 AND {$alias}.idade_pac < 40";
            case '40-59':
                return "{$alias}.idade_pac >= 40 AND {$alias}.idade_pac < 60";
            case '60-79':
                return "{$alias}.idade_pac >= 60 AND {$alias}.idade_pac < 80";
            case '80+':
                return "{$alias}.idade_pac >= 80";
            case 'Sem informacao':
                return "{$alias}.idade_pac IS NULL";
            default:
                return null;
        }
    }
}

if (!function_exists('bi_filter_add_join')) {
    function bi_filter_add_join(array &$joins, string $key, string $sql): void
    {
        if (!isset($joins[$key])) {
            $joins[$key] = $sql;
        }
    }
}

if (!function_exists('bi_apply_internacao_option_filters')) {
    function bi_apply_internacao_option_filters(array $filters, string $dateExpr, array &$joins, array &$where, array &$params, array $exclude = []): void
    {
        $skip = array_fill_keys($exclude, true);

        $needsCapeante = strpos($dateExpr, 'ca.') !== false;
        if ($needsCapeante) {
            bi_filter_add_join($joins, 'capeante', "INNER JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao");
        }

        $needsPaciente = (!isset($skip['sexo']) && !empty($filters['sexo']))
            || (!isset($skip['faixa_etaria']) && !empty($filters['faixa_etaria']))
            || (!isset($skip['seguradora_id']) && !empty($filters['seguradora_id']));
        if ($needsPaciente) {
            bi_filter_add_join($joins, 'paciente', "LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int");
        }

        if (empty($skip['ano']) && array_key_exists('ano', $filters) && !empty($filters['ano'])) {
            $where[] = "YEAR({$dateExpr}) = :opt_ano";
            $params[':opt_ano'] = (int)$filters['ano'];
        }
        if (empty($skip['mes']) && array_key_exists('mes', $filters) && !empty($filters['mes'])) {
            $where[] = "MONTH({$dateExpr}) = :opt_mes";
            $params[':opt_mes'] = (int)$filters['mes'];
        }
        if (empty($skip['data_periodo']) && !empty($filters['data_inicio']) && !empty($filters['data_fim'])) {
            $where[] = "{$dateExpr} BETWEEN :opt_data_inicio AND :opt_data_fim";
            $params[':opt_data_inicio'] = $filters['data_inicio'];
            $params[':opt_data_fim'] = $filters['data_fim'];
        }
        if (empty($skip['hospital_id']) && !empty($filters['hospital_id'])) {
            $where[] = "i.fk_hospital_int = :opt_hospital_id";
            $params[':opt_hospital_id'] = (int)$filters['hospital_id'];
        }
        if (empty($skip['tipo_internacao']) && !empty($filters['tipo_internacao'])) {
            $where[] = "i.tipo_admissao_int = :opt_tipo_internacao";
            $params[':opt_tipo_internacao'] = $filters['tipo_internacao'];
        }
        if (empty($skip['modo_internacao']) && !empty($filters['modo_internacao'])) {
            $where[] = "i.modo_internacao_int = :opt_modo_internacao";
            $params[':opt_modo_internacao'] = $filters['modo_internacao'];
        }
        if (empty($skip['patologia_id']) && !empty($filters['patologia_id'])) {
            $where[] = "i.fk_cid_int = :opt_patologia_id";
            $params[':opt_patologia_id'] = (int)$filters['patologia_id'];
        }
        if (empty($skip['grupo_patologia']) && !empty($filters['grupo_patologia'])) {
            $where[] = "i.grupo_patologia_int = :opt_grupo_patologia";
            $params[':opt_grupo_patologia'] = $filters['grupo_patologia'];
        }
        if (empty($skip['antecedente_id']) && !empty($filters['antecedente_id'])) {
            $where[] = "i.fk_patologia2 = :opt_antecedente_id";
            $params[':opt_antecedente_id'] = (int)$filters['antecedente_id'];
        }
        if (empty($skip['internado']) && !empty($filters['internado'])) {
            $where[] = "i.internado_int = :opt_internado";
            $params[':opt_internado'] = $filters['internado'];
        }
        if (empty($skip['seguradora_id']) && !empty($filters['seguradora_id'])) {
            bi_filter_add_join($joins, 'paciente', "LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int");
            $where[] = "pa.fk_seguradora_pac = :opt_seguradora_id";
            $params[':opt_seguradora_id'] = (int)$filters['seguradora_id'];
        }
        if (empty($skip['sexo']) && !empty($filters['sexo'])) {
            bi_filter_add_join($joins, 'paciente', "LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int");
            $where[] = "pa.sexo_pac = :opt_sexo";
            $params[':opt_sexo'] = $filters['sexo'];
        }
        if (empty($skip['faixa_etaria']) && !empty($filters['faixa_etaria'])) {
            bi_filter_add_join($joins, 'paciente', "LEFT JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int");
            $idadeCond = bi_cid_idade_cond((string)$filters['faixa_etaria'], 'pa');
            if ($idadeCond) {
                $where[] = $idadeCond;
            }
        }
        if (empty($skip['regiao']) && !empty($filters['regiao'])) {
            bi_filter_add_join($joins, 'hospital', "LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int");
            $where[] = "h.estado_hosp = :opt_regiao";
            $params[':opt_regiao'] = $filters['regiao'];
        }

        if (empty($skip['uti']) && array_key_exists('uti', $filters) && $filters['uti'] !== '') {
            bi_filter_add_join($joins, 'uti', "LEFT JOIN (SELECT DISTINCT fk_internacao_uti FROM tb_uti) opt_ut ON opt_ut.fk_internacao_uti = i.id_internacao");
            if ($filters['uti'] === 's') {
                $where[] = "opt_ut.fk_internacao_uti IS NOT NULL";
            } elseif ($filters['uti'] === 'n') {
                $where[] = "opt_ut.fk_internacao_uti IS NULL";
            }
        }
    }
}

if (!function_exists('bi_fetch_filter_options')) {
    function bi_fetch_filter_options(PDO $conn, string $field, array $filters = [], array $options = []): array
    {
        $dateExpr = $options['date_expr'] ?? 'i.data_intern_int';
        $joins = [];
        $where = ["1=1"];
        $params = [];

        $configs = [
            'hospital' => [
                'value' => 'h.id_hospital',
                'label' => 'h.nome_hosp',
                'joins' => ['hospital' => "INNER JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int"],
                'where' => ["h.id_hospital IS NOT NULL", "h.nome_hosp IS NOT NULL", "h.nome_hosp <> ''"],
                'exclude' => ['hospital_id'],
                'order' => 'label',
            ],
            'tipo_internacao' => [
                'value' => 'i.tipo_admissao_int',
                'label' => 'i.tipo_admissao_int',
                'where' => ["i.tipo_admissao_int IS NOT NULL", "i.tipo_admissao_int <> ''"],
                'exclude' => ['tipo_internacao'],
                'order' => 'label',
            ],
            'modo_internacao' => [
                'value' => 'i.modo_internacao_int',
                'label' => 'i.modo_internacao_int',
                'where' => ["i.modo_internacao_int IS NOT NULL", "i.modo_internacao_int <> ''"],
                'exclude' => ['modo_internacao'],
                'order' => 'label',
            ],
            'grupo_patologia' => [
                'value' => 'i.grupo_patologia_int',
                'label' => 'i.grupo_patologia_int',
                'where' => ["i.grupo_patologia_int IS NOT NULL", "i.grupo_patologia_int <> ''"],
                'exclude' => ['grupo_patologia'],
                'order' => 'label',
            ],
            'antecedente' => [
                'value' => 'ant.id_antecedente',
                'label' => 'ant.antecedente_ant',
                'joins' => ['antecedente' => "INNER JOIN tb_antecedente ant ON ant.id_antecedente = i.fk_patologia2"],
                'where' => ["ant.id_antecedente IS NOT NULL", "ant.antecedente_ant IS NOT NULL", "ant.antecedente_ant <> ''"],
                'exclude' => ['antecedente_id'],
                'order' => 'label',
            ],
            'seguradora' => [
                'value' => 's.id_seguradora',
                'label' => 's.seguradora_seg',
                'joins' => [
                    'paciente' => "INNER JOIN tb_paciente pa ON pa.id_paciente = i.fk_paciente_int",
                    'seguradora' => "INNER JOIN tb_seguradora s ON s.id_seguradora = pa.fk_seguradora_pac",
                ],
                'where' => ["s.id_seguradora IS NOT NULL", "s.seguradora_seg IS NOT NULL", "s.seguradora_seg <> ''"],
                'exclude' => ['seguradora_id'],
                'order' => 'label',
            ],
            'regiao' => [
                'value' => 'h.estado_hosp',
                'label' => 'h.estado_hosp',
                'joins' => ['hospital' => "INNER JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int"],
                'where' => ["h.estado_hosp IS NOT NULL", "h.estado_hosp <> ''"],
                'exclude' => ['regiao'],
                'order' => 'label',
            ],
        ];

        if (empty($configs[$field])) {
            return [];
        }

        $config = $configs[$field];
        foreach (($config['joins'] ?? []) as $key => $joinSql) {
            bi_filter_add_join($joins, $key, $joinSql);
        }
        foreach (($config['where'] ?? []) as $condition) {
            $where[] = $condition;
        }

        $exclude = array_values(array_unique(array_merge($config['exclude'] ?? [], $options['exclude'] ?? [])));
        bi_apply_internacao_option_filters($filters, $dateExpr, $joins, $where, $params, $exclude);

        $valueExpr = $config['value'];
        $labelExpr = $config['label'];
        $order = $config['order'] ?? 'label';
        $sql = "
            SELECT
                {$valueExpr} AS value,
                {$labelExpr} AS label,
                COUNT(DISTINCT i.id_internacao) AS total_internacoes
            FROM tb_internacao i
            " . implode("\n            ", $joins) . "
            WHERE " . implode(" AND ", $where) . "
            GROUP BY value, label
            ORDER BY {$order}
        ";

        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('bi_fetch_cid_options')) {
    function bi_fetch_cid_options(PDO $conn, array $filters = [], array $options = []): array
    {
        $dateExpr = $options['date_expr'] ?? 'i.data_intern_int';
        $joins = ['cid' => "INNER JOIN tb_cid c ON c.id_cid = i.fk_cid_int"];
        $where = [
            "i.fk_cid_int IS NOT NULL",
            "i.fk_cid_int <> 0",
        ];
        $params = [];

        $needsCapeante = !empty($options['join_capeante']) || strpos($dateExpr, 'ca.') !== false;
        if ($needsCapeante) {
            bi_filter_add_join($joins, 'capeante', "INNER JOIN tb_capeante ca ON ca.fk_int_capeante = i.id_internacao");
        }

        $exclude = array_values(array_unique(array_merge(['patologia_id'], $options['exclude'] ?? [])));
        bi_apply_internacao_option_filters($filters, $dateExpr, $joins, $where, $params, $exclude);

        $labelExpr = bi_cid_label_expr('c');
        $sql = "
            SELECT
                c.id_cid AS id_patologia,
                {$labelExpr} AS patologia_pat,
                COUNT(DISTINCT i.id_internacao) AS total_internacoes
            FROM tb_internacao i
            " . implode("\n            ", $joins) . "
            WHERE " . implode(" AND ", $where) . "
            GROUP BY c.id_cid, c.cat, c.descricao
            ORDER BY c.cat, c.descricao
        ";

        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
