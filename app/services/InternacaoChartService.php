<?php

class InternacaoChartService
{
    private PDO $conn;
    private string $apiKey;
    private string $apiUrl;
    private string $model;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->loadEnvFile(dirname(__DIR__, 2) . '/.env');
        $this->apiKey = trim((string)(getenv('MINHA_API_TOKEN') ?: ($_ENV['MINHA_API_TOKEN'] ?? '') ?: getenv('OPENAI_API_KEY') ?: ($_ENV['OPENAI_API_KEY'] ?? '')));
        $this->apiUrl = trim((string)(getenv('OPENAI_API_URL') ?: 'https://api.openai.com/v1/responses'));
        $this->model = trim((string)(getenv('OPENAI_MODEL') ?: 'gpt-4.1-mini'));
    }

    public function listHospitals(array $ctx): array
    {
        $params = [];
        $scope = $this->scopeClause($ctx, 'i', $params, 'hosp');
        $sql = "
            SELECT DISTINCT h.id_hospital, h.nome_hosp
              FROM tb_hospital h
              JOIN tb_internacao i ON i.fk_hospital_int = h.id_hospital
             WHERE 1=1 {$scope}
             ORDER BY h.nome_hosp
             LIMIT 300
        ";
        $stmt = $this->conn->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function generate(string $question, array $filters, array $ctx): array
    {
        $question = trim($question);
        if ($question === '') {
            throw new InvalidArgumentException('Digite o gráfico que deseja criar.');
        }
        if (mb_strlen($question, 'UTF-8') > 1000) {
            throw new InvalidArgumentException('Pedido muito longo. Tente ser mais objetivo.');
        }

        $filters = $this->normalizeFilters($filters, $ctx);
        $templates = $this->templates();
        $spec = $this->chooseLocalSpec($question, $templates);
        $source = 'local';

        try {
            $aiSpec = $this->requestChartSpec($question, $filters, $templates);
            if (isset($templates[$aiSpec['template_key'] ?? ''])) {
                $spec = $templates[$aiSpec['template_key']];
                if (!empty($aiSpec['chart_type']) && in_array($aiSpec['chart_type'], ['bar', 'line', 'doughnut'], true)) {
                    $spec['chart_type'] = $aiSpec['chart_type'];
                }
                $source = 'openai';
            }
        } catch (Throwable $e) {
            $source = 'local';
        }

        $rows = $this->fetchRows($spec['key'], $filters, $ctx);
        $chart = $this->buildChart($spec, $rows);
        $insight = $this->buildLocalInsight($question, $spec, $filters, $rows);

        if ($source === 'openai') {
            try {
                $insight = $this->requestInsight($question, $spec, $filters, $rows);
            } catch (Throwable $e) {
                $source = 'local';
            }
        }

        return [
            'title' => $spec['title'],
            'chart' => $chart,
            'rows' => $rows,
            'insight' => $insight,
            'summary' => [
                'template_key' => $spec['key'],
                'metric' => $spec['metric'],
                'dimension' => $spec['dimension'],
                'source' => $source,
                'filters' => $filters,
            ],
        ];
    }

    private function normalizeFilters(array $filters, array $ctx): array
    {
        $hospitalId = (int)($filters['hospital_id'] ?? 0);
        $status = trim((string)($filters['status'] ?? 'internados'));
        $status = in_array($status, ['internados', 'todos', 'alta'], true) ? $status : 'internados';
        $days = (int)($filters['days'] ?? 180);
        $days = max(7, min(730, $days));

        if ($hospitalId > 0 && !$this->assertHospitalAccess($ctx, $hospitalId)) {
            $hospitalId = 0;
        }

        return [
            'hospital_id' => $hospitalId,
            'status' => $status,
            'days' => $days,
        ];
    }

    private function templates(): array
    {
        $templates = [
            'internacoes_por_hospital' => [
                'title' => 'Internações por hospital',
                'dimension' => 'Hospital',
                'metric' => 'Internações',
                'chart_type' => 'bar',
            ],
            'internacoes_por_seguradora' => [
                'title' => 'Internações por seguradora',
                'dimension' => 'Seguradora',
                'metric' => 'Internações',
                'chart_type' => 'bar',
            ],
            'internacoes_por_mes' => [
                'title' => 'Evolução mensal de internações',
                'dimension' => 'Mês',
                'metric' => 'Internações',
                'chart_type' => 'line',
            ],
            'longa_por_hospital' => [
                'title' => 'Longa permanência por hospital',
                'dimension' => 'Hospital',
                'metric' => 'Internações com 15 dias ou mais',
                'chart_type' => 'bar',
            ],
            'visitas_atraso_por_hospital' => [
                'title' => 'Visitas em atraso por hospital',
                'dimension' => 'Hospital',
                'metric' => 'Internações sem visita recente',
                'chart_type' => 'bar',
            ],
            'uti_por_hospital' => [
                'title' => 'Internações com UTI por hospital',
                'dimension' => 'Hospital',
                'metric' => 'Internações com UTI',
                'chart_type' => 'bar',
            ],
            'eventos_por_hospital' => [
                'title' => 'Eventos adversos por hospital',
                'dimension' => 'Hospital',
                'metric' => 'Eventos adversos',
                'chart_type' => 'bar',
            ],
            'media_permanencia_por_hospital' => [
                'title' => 'Média de permanência por hospital',
                'dimension' => 'Hospital',
                'metric' => 'Dias médios',
                'chart_type' => 'bar',
            ],
            'status_internacao' => [
                'title' => 'Status das internações',
                'dimension' => 'Status',
                'metric' => 'Internações',
                'chart_type' => 'doughnut',
            ],
            'grupo_patologia' => [
                'title' => 'Internações por grupo de patologia',
                'dimension' => 'Grupo de patologia',
                'metric' => 'Internações',
                'chart_type' => 'bar',
            ],
            'saving_por_hospital' => [
                'title' => 'Saving por hospital',
                'dimension' => 'Hospital',
                'metric' => 'Saving (R$)',
                'chart_type' => 'bar',
            ],
            'saving_por_auditor' => [
                'title' => 'Saving por auditor',
                'dimension' => 'Auditor',
                'metric' => 'Saving (R$)',
                'chart_type' => 'bar',
            ],
            'saving_por_tipo' => [
                'title' => 'Saving por tipo de negociação',
                'dimension' => 'Tipo de negociação',
                'metric' => 'Saving (R$)',
                'chart_type' => 'bar',
            ],
            'saving_mensal' => [
                'title' => 'Evolução mensal do saving',
                'dimension' => 'Mês',
                'metric' => 'Saving (R$)',
                'chart_type' => 'line',
            ],
            'saving_quantidade_por_tipo' => [
                'title' => 'Quantidade de negociações por tipo',
                'dimension' => 'Tipo de negociação',
                'metric' => 'Negociações',
                'chart_type' => 'bar',
            ],
            'faturamento_apresentado_mensal' => [
                'title' => 'Evolução mensal do valor apresentado',
                'dimension' => 'Mês',
                'metric' => 'Valor apresentado (R$)',
                'chart_type' => 'line',
            ],
            'faturamento_final_mensal' => [
                'title' => 'Evolução mensal do valor final',
                'dimension' => 'Mês',
                'metric' => 'Valor final (R$)',
                'chart_type' => 'line',
            ],
            'glosa_mensal' => [
                'title' => 'Evolução mensal da glosa',
                'dimension' => 'Mês',
                'metric' => 'Glosa (R$)',
                'chart_type' => 'line',
            ],
            'faturamento_por_hospital' => [
                'title' => 'Valor apresentado por hospital',
                'dimension' => 'Hospital',
                'metric' => 'Valor apresentado (R$)',
                'chart_type' => 'bar',
            ],
            'glosa_por_hospital' => [
                'title' => 'Glosa por hospital',
                'dimension' => 'Hospital',
                'metric' => 'Glosa (R$)',
                'chart_type' => 'bar',
            ],
            'contas_abertas_por_hospital' => [
                'title' => 'Contas abertas por hospital',
                'dimension' => 'Hospital',
                'metric' => 'Contas abertas',
                'chart_type' => 'bar',
            ],
            'contas_paradas_por_hospital' => [
                'title' => 'Contas paradas por hospital',
                'dimension' => 'Hospital',
                'metric' => 'Contas paradas',
                'chart_type' => 'bar',
            ],
            'eventos_por_tipo' => [
                'title' => 'Eventos adversos por tipo',
                'dimension' => 'Tipo de evento',
                'metric' => 'Eventos',
                'chart_type' => 'bar',
            ],
            'eventos_gestao_por_hospital' => [
                'title' => 'Eventos de gestão por hospital',
                'dimension' => 'Hospital',
                'metric' => 'Registros de gestão',
                'chart_type' => 'bar',
            ],
            'alto_custo_por_hospital' => [
                'title' => 'Sinalizações de alto custo por hospital',
                'dimension' => 'Hospital',
                'metric' => 'Sinalizações',
                'chart_type' => 'bar',
            ],
            'opme_por_hospital' => [
                'title' => 'Sinalizações de OPME por hospital',
                'dimension' => 'Hospital',
                'metric' => 'Sinalizações',
                'chart_type' => 'bar',
            ],
            'home_care_por_hospital' => [
                'title' => 'Home care por hospital',
                'dimension' => 'Hospital',
                'metric' => 'Sinalizações',
                'chart_type' => 'bar',
            ],
            'desospitalizacao_por_hospital' => [
                'title' => 'Desospitalização por hospital',
                'dimension' => 'Hospital',
                'metric' => 'Sinalizações',
                'chart_type' => 'bar',
            ],
            'visitas_por_hospital' => [
                'title' => 'Visitas por hospital',
                'dimension' => 'Hospital',
                'metric' => 'Visitas',
                'chart_type' => 'bar',
            ],
            'visitas_faturadas_mensal' => [
                'title' => 'Visitas faturadas por mês',
                'dimension' => 'Mês',
                'metric' => 'Visitas faturadas',
                'chart_type' => 'line',
            ],
        ];

        foreach ($templates as $key => $tpl) {
            $templates[$key]['key'] = $key;
        }
        return $templates;
    }

    private function chooseLocalSpec(string $question, array $templates): array
    {
        $q = $this->normalizeText($question);
        if (preg_match('/saving|savings|economia|negoci|desconto/', $q)) {
            if (preg_match('/auditor|usuario|responsavel/', $q)) {
                return $templates['saving_por_auditor'];
            }
            if (preg_match('/tipo|categoria/', $q)) {
                if (preg_match('/quantidade|qtd|volume|numero|registros/', $q)) {
                    return $templates['saving_quantidade_por_tipo'];
                }
                return $templates['saving_por_tipo'];
            }
            if (preg_match('/mes|mensal|evolucao|tendencia|periodo|linha/', $q)) {
                return $templates['saving_mensal'];
            }
            return $templates['saving_por_hospital'];
        }
        if (preg_match('/visita|visitas/', $q)) {
            if (preg_match('/faturad|mes|mensal|evolucao/', $q)) {
                return $templates['visitas_faturadas_mensal'];
            }
            return $templates['visitas_por_hospital'];
        }
        if (preg_match('/gestao|evento|eventos|adverso|alto custo|opme|home care|desospital/', $q)) {
            if (preg_match('/tipo|categoria|classificacao/', $q)) {
                return $templates['eventos_por_tipo'];
            }
            if (preg_match('/alto custo/', $q)) {
                return $templates['alto_custo_por_hospital'];
            }
            if (preg_match('/opme/', $q)) {
                return $templates['opme_por_hospital'];
            }
            if (preg_match('/home care/', $q)) {
                return $templates['home_care_por_hospital'];
            }
            if (preg_match('/desospital/', $q)) {
                return $templates['desospitalizacao_por_hospital'];
            }
            if (preg_match('/evento|adverso/', $q)) {
                return $templates['eventos_por_tipo'];
            }
            return $templates['eventos_gestao_por_hospital'];
        }
        if (preg_match('/fatur|capeante|conta|contas|glosa|apresentado|valor final|sinistro|custo|custos/', $q)) {
            if (preg_match('/parada|paradas/', $q)) {
                return $templates['contas_paradas_por_hospital'];
            }
            if (preg_match('/aberta|abertas|andamento/', $q)) {
                return $templates['contas_abertas_por_hospital'];
            }
            if (preg_match('/glosa|glosado|glosadas/', $q)) {
                if (preg_match('/mes|mensal|evolucao|tendencia|periodo|linha/', $q)) {
                    return $templates['glosa_mensal'];
                }
                return $templates['glosa_por_hospital'];
            }
            if (preg_match('/final|recebido|liquido/', $q)) {
                return $templates['faturamento_final_mensal'];
            }
            if (preg_match('/mes|mensal|evolucao|tendencia|periodo|linha/', $q)) {
                return $templates['faturamento_apresentado_mensal'];
            }
            return $templates['faturamento_por_hospital'];
        }
        if (preg_match('/seguradora|convenio|operadora/', $q)) {
            return $templates['internacoes_por_seguradora'];
        }
        if (preg_match('/mes|mensal|evolucao|tendencia|periodo|linha/', $q)) {
            return $templates['internacoes_por_mes'];
        }
        if (preg_match('/media|medio|tempo medio|dias medio|dias por hospital/', $q)) {
            return $templates['media_permanencia_por_hospital'];
        }
        if (preg_match('/longa|perman/', $q)) {
            return $templates['longa_por_hospital'];
        }
        if (preg_match('/visita|atraso|sem visita/', $q)) {
            return $templates['visitas_atraso_por_hospital'];
        }
        if (preg_match('/uti|unidade intensiva|intensiva/', $q)) {
            return $templates['uti_por_hospital'];
        }
        if (preg_match('/evento|adverso/', $q)) {
            return $templates['eventos_por_hospital'];
        }
        if (preg_match('/status|alta|internado/', $q)) {
            return $templates['status_internacao'];
        }
        if (preg_match('/patologia|cid|grupo/', $q)) {
            return $templates['grupo_patologia'];
        }
        return $templates['internacoes_por_hospital'];
    }

    private function fetchRows(string $templateKey, array $filters, array $ctx): array
    {
        $isSavingTemplate = $this->isSavingTemplate($templateKey);
        $isCapeanteTemplate = $this->isCapeanteTemplate($templateKey);
        $isGestaoTemplate = $this->isGestaoTemplate($templateKey);
        $isVisitaTemplate = $this->isVisitaTemplate($templateKey);
        $params = [
            ':days' => (int)$filters['days'],
        ];
        $where = ['1=1'];
        if (!$isSavingTemplate && !$isCapeanteTemplate && !$isGestaoTemplate && !$isVisitaTemplate) {
            $where[] = 'i.data_intern_int IS NOT NULL';
            $where[] = 'i.data_intern_int >= DATE_SUB(CURDATE(), INTERVAL :days DAY)';
        }

        if ((int)$filters['hospital_id'] > 0) {
            $where[] = 'i.fk_hospital_int = :hospital_id';
            $params[':hospital_id'] = (int)$filters['hospital_id'];
        }
        if (!$isSavingTemplate && !$isCapeanteTemplate && !$isGestaoTemplate && !$isVisitaTemplate) {
            if ($filters['status'] === 'internados') {
                $where[] = "LOWER(COALESCE(i.internado_int, '')) = 's'";
            } elseif ($filters['status'] === 'alta') {
                $where[] = "LOWER(COALESCE(i.internado_int, '')) <> 's'";
            }
        }

        $scope = $this->scopeClause($ctx, 'i', $params, 'scp');
        $whereSql = implode(' AND ', $where);
        [$selectSql, $joinSql, $extraWhere, $groupSql, $orderSql, $limitSql, $fromSql] = $this->sqlParts($templateKey);

        $sql = "
            SELECT {$selectSql}
              FROM {$fromSql}
              JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
              JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
              LEFT JOIN tb_seguradora s ON s.id_seguradora = p.fk_seguradora_pac
              {$joinSql}
             WHERE {$whereSql}
              {$scope}
              {$extraWhere}
             {$groupSql}
             {$orderSql}
             {$limitSql}
        ";

        $stmt = $this->conn->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->execute();
        return array_map(function (array $row) use ($templateKey): array {
            $label = (string)($row['label'] ?? 'Sem identificação');
            return [
                'label' => $this->formatChartLabel($label, $templateKey),
                'value' => round((float)($row['value'] ?? 0), 2),
                'extra' => isset($row['extra']) ? (string)$row['extra'] : '',
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    private function sqlParts(string $templateKey): array
    {
        $internacaoFrom = 'tb_internacao i';
        $savingFrom = 'tb_negociacao ng INNER JOIN tb_internacao i ON i.id_internacao = ng.fk_id_int';
        $capeanteFrom = 'tb_capeante ca INNER JOIN tb_internacao i ON i.id_internacao = ca.fk_int_capeante';
        $gestaoFrom = 'tb_gestao g INNER JOIN tb_internacao i ON i.id_internacao = g.fk_internacao_ges';
        $visitaFrom = 'tb_visita v INNER JOIN tb_internacao i ON i.id_internacao = v.fk_internacao_vis';
        $savingWhere = "
            AND ng.data_inicio_neg IS NOT NULL
            AND ng.data_inicio_neg <> '0000-00-00'
            AND ng.data_inicio_neg >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            AND ng.saving IS NOT NULL
            AND COALESCE(ng.fk_usuario_neg, 0) > 0
            AND UPPER(COALESCE(ng.tipo_negociacao, '')) <> 'PRORROGACAO_AUTOMATICA'
            AND COALESCE(ng.saving, 0) <> 0
        ";
        $capeanteDateExpr = "COALESCE(NULLIF(ca.data_digit_capeante,'0000-00-00'), NULLIF(ca.data_fech_capeante,'0000-00-00'), NULLIF(ca.data_final_capeante,'0000-00-00'), NULLIF(ca.data_inicial_capeante,'0000-00-00'))";
        $capeanteWhere = "
            AND {$capeanteDateExpr} IS NOT NULL
            AND {$capeanteDateExpr} >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            AND LOWER(COALESCE(ca.deletado_cap, 'n')) <> 's'
        ";
        $gestaoWhere = "
            AND g.data_create_ges IS NOT NULL
            AND g.data_create_ges >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            AND LOWER(COALESCE(g.deletado_ges, 'n')) <> 's'
        ";
        $visitaWhere = "
            AND v.data_visita_vis IS NOT NULL
            AND v.data_visita_vis <> '0000-00-00'
            AND v.data_visita_vis >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
        ";
        $latestVisitJoin = "
            LEFT JOIN (
                SELECT fk_internacao_vis, MAX(data_visita_vis) AS ultima_visita
                  FROM tb_visita
                 GROUP BY fk_internacao_vis
            ) lv ON lv.fk_internacao_vis = i.id_internacao
        ";
        $utiJoin = "
            LEFT JOIN (
                SELECT DISTINCT fk_internacao_uti
                  FROM tb_uti
            ) uti ON uti.fk_internacao_uti = i.id_internacao
        ";
        $eventoJoin = "
            LEFT JOIN (
                SELECT fk_internacao_ges, COUNT(*) AS eventos
                  FROM tb_gestao
                 WHERE LOWER(COALESCE(evento_adverso_ges, 'n')) = 's'
                 GROUP BY fk_internacao_ges
            ) ges ON ges.fk_internacao_ges = i.id_internacao
        ";
        $altaJoin = "
            LEFT JOIN (
                SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt
                  FROM tb_alta
                 GROUP BY fk_id_int_alt
            ) al ON al.fk_id_int_alt = i.id_internacao
        ";

        switch ($templateKey) {
            case 'internacoes_por_seguradora':
                return [
                    "COALESCE(NULLIF(s.seguradora_seg, ''), 'Sem seguradora') AS label, COUNT(DISTINCT i.id_internacao) AS value, '' AS extra",
                    '',
                    '',
                    'GROUP BY label',
                    'ORDER BY value DESC, label ASC',
                    'LIMIT 12',
                    $internacaoFrom,
                ];
            case 'internacoes_por_mes':
                return [
                    "DATE_FORMAT(i.data_intern_int, '%Y-%m') AS label, COUNT(DISTINCT i.id_internacao) AS value, '' AS extra",
                    '',
                    '',
                    'GROUP BY label',
                    'ORDER BY label ASC',
                    'LIMIT 24',
                    $internacaoFrom,
                ];
            case 'longa_por_hospital':
                return [
                    "COALESCE(NULLIF(h.nome_hosp, ''), 'Sem hospital') AS label, COUNT(DISTINCT i.id_internacao) AS value, '' AS extra",
                    '',
                    'AND DATEDIFF(CURDATE(), i.data_intern_int) >= 15',
                    'GROUP BY h.id_hospital, h.nome_hosp',
                    'ORDER BY value DESC, label ASC',
                    'LIMIT 12',
                    $internacaoFrom,
                ];
            case 'visitas_atraso_por_hospital':
                return [
                    "COALESCE(NULLIF(h.nome_hosp, ''), 'Sem hospital') AS label, COUNT(DISTINCT i.id_internacao) AS value, '' AS extra",
                    $latestVisitJoin,
                    'AND (lv.ultima_visita IS NULL OR DATEDIFF(CURDATE(), lv.ultima_visita) >= 5)',
                    'GROUP BY h.id_hospital, h.nome_hosp',
                    'ORDER BY value DESC, label ASC',
                    'LIMIT 12',
                    $internacaoFrom,
                ];
            case 'uti_por_hospital':
                return [
                    "COALESCE(NULLIF(h.nome_hosp, ''), 'Sem hospital') AS label, COUNT(DISTINCT i.id_internacao) AS value, '' AS extra",
                    $utiJoin,
                    'AND uti.fk_internacao_uti IS NOT NULL',
                    'GROUP BY h.id_hospital, h.nome_hosp',
                    'ORDER BY value DESC, label ASC',
                    'LIMIT 12',
                    $internacaoFrom,
                ];
            case 'eventos_por_hospital':
                return [
                    "COALESCE(NULLIF(h.nome_hosp, ''), 'Sem hospital') AS label, COALESCE(SUM(ges.eventos), 0) AS value, '' AS extra",
                    $eventoJoin,
                    'AND ges.eventos IS NOT NULL',
                    'GROUP BY h.id_hospital, h.nome_hosp',
                    'ORDER BY value DESC, label ASC',
                    'LIMIT 12',
                    $internacaoFrom,
                ];
            case 'media_permanencia_por_hospital':
                return [
                    "COALESCE(NULLIF(h.nome_hosp, ''), 'Sem hospital') AS label, AVG(GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1)) AS value, CONCAT(COUNT(DISTINCT i.id_internacao), ' internações') AS extra",
                    $altaJoin,
                    '',
                    'GROUP BY h.id_hospital, h.nome_hosp',
                    'ORDER BY value DESC, label ASC',
                    'LIMIT 12',
                    $internacaoFrom,
                ];
            case 'status_internacao':
                return [
                    "CASE WHEN LOWER(COALESCE(i.internado_int, '')) = 's' THEN 'Internados' ELSE 'Com alta' END AS label, COUNT(DISTINCT i.id_internacao) AS value, '' AS extra",
                    '',
                    '',
                    'GROUP BY label',
                    'ORDER BY value DESC',
                    'LIMIT 4',
                    $internacaoFrom,
                ];
            case 'grupo_patologia':
                return [
                    "COALESCE(NULLIF(i.grupo_patologia_int, ''), 'Sem grupo') AS label, COUNT(DISTINCT i.id_internacao) AS value, '' AS extra",
                    '',
                    '',
                    'GROUP BY label',
                    'ORDER BY value DESC, label ASC',
                    'LIMIT 12',
                    $internacaoFrom,
                ];
            case 'saving_por_auditor':
                return [
                    "COALESCE(NULLIF(u.usuario_user, ''), 'Sem auditor') AS label, SUM(COALESCE(ng.saving, 0)) AS value, CONCAT(COUNT(DISTINCT ng.id_negociacao), ' negociações') AS extra",
                    "LEFT JOIN tb_user u ON u.id_usuario = ng.fk_usuario_neg",
                    $savingWhere,
                    'GROUP BY ng.fk_usuario_neg, u.usuario_user',
                    'ORDER BY value DESC, label ASC',
                    'LIMIT 15',
                    $savingFrom,
                ];
            case 'saving_por_tipo':
                return [
                    "COALESCE(NULLIF(ng.tipo_negociacao, ''), 'Não informado') AS label, SUM(COALESCE(ng.saving, 0)) AS value, CONCAT(COUNT(DISTINCT ng.id_negociacao), ' negociações') AS extra",
                    '',
                    $savingWhere,
                    'GROUP BY label',
                    'ORDER BY value DESC, label ASC',
                    'LIMIT 12',
                    $savingFrom,
                ];
            case 'saving_mensal':
                return [
                    "DATE_FORMAT(ng.data_inicio_neg, '%Y-%m') AS label, SUM(COALESCE(ng.saving, 0)) AS value, CONCAT(COUNT(DISTINCT ng.id_negociacao), ' negociações') AS extra",
                    '',
                    $savingWhere,
                    'GROUP BY label',
                    'ORDER BY label ASC',
                    'LIMIT 24',
                    $savingFrom,
                ];
            case 'saving_quantidade_por_tipo':
                return [
                    "COALESCE(NULLIF(ng.tipo_negociacao, ''), 'Não informado') AS label, COUNT(DISTINCT ng.id_negociacao) AS value, CONCAT('R$ ', ROUND(SUM(COALESCE(ng.saving, 0)), 2)) AS extra",
                    '',
                    $savingWhere,
                    'GROUP BY label',
                    'ORDER BY value DESC, label ASC',
                    'LIMIT 12',
                    $savingFrom,
                ];
            case 'saving_por_hospital':
                return [
                    "COALESCE(NULLIF(h.nome_hosp, ''), 'Sem hospital') AS label, SUM(COALESCE(ng.saving, 0)) AS value, CONCAT(COUNT(DISTINCT ng.id_negociacao), ' negociações') AS extra",
                    '',
                    $savingWhere,
                    'GROUP BY h.id_hospital, h.nome_hosp',
                    'ORDER BY value DESC, label ASC',
                    'LIMIT 15',
                    $savingFrom,
                ];
            case 'faturamento_apresentado_mensal':
                return [
                    "DATE_FORMAT({$capeanteDateExpr}, '%Y-%m') AS label, SUM(COALESCE(ca.valor_apresentado_capeante, 0)) AS value, CONCAT(COUNT(DISTINCT ca.id_capeante), ' contas') AS extra",
                    '',
                    $capeanteWhere,
                    'GROUP BY label',
                    'ORDER BY label ASC',
                    'LIMIT 24',
                    $capeanteFrom,
                ];
            case 'faturamento_final_mensal':
                return [
                    "DATE_FORMAT({$capeanteDateExpr}, '%Y-%m') AS label, SUM(COALESCE(ca.valor_final_capeante, 0)) AS value, CONCAT(COUNT(DISTINCT ca.id_capeante), ' contas') AS extra",
                    '',
                    $capeanteWhere,
                    'GROUP BY label',
                    'ORDER BY label ASC',
                    'LIMIT 24',
                    $capeanteFrom,
                ];
            case 'glosa_mensal':
                return [
                    "DATE_FORMAT({$capeanteDateExpr}, '%Y-%m') AS label, SUM(COALESCE(ca.valor_glosa_total, 0)) AS value, CONCAT(COUNT(DISTINCT ca.id_capeante), ' contas') AS extra",
                    '',
                    $capeanteWhere,
                    'GROUP BY label',
                    'ORDER BY label ASC',
                    'LIMIT 24',
                    $capeanteFrom,
                ];
            case 'faturamento_por_hospital':
                return [
                    "COALESCE(NULLIF(h.nome_hosp, ''), 'Sem hospital') AS label, SUM(COALESCE(ca.valor_apresentado_capeante, 0)) AS value, CONCAT(COUNT(DISTINCT ca.id_capeante), ' contas') AS extra",
                    '',
                    $capeanteWhere,
                    'GROUP BY h.id_hospital, h.nome_hosp',
                    'ORDER BY value DESC, label ASC',
                    'LIMIT 15',
                    $capeanteFrom,
                ];
            case 'glosa_por_hospital':
                return [
                    "COALESCE(NULLIF(h.nome_hosp, ''), 'Sem hospital') AS label, SUM(COALESCE(ca.valor_glosa_total, 0)) AS value, CONCAT(COUNT(DISTINCT ca.id_capeante), ' contas') AS extra",
                    '',
                    $capeanteWhere,
                    'GROUP BY h.id_hospital, h.nome_hosp',
                    'ORDER BY value DESC, label ASC',
                    'LIMIT 15',
                    $capeanteFrom,
                ];
            case 'contas_abertas_por_hospital':
                return [
                    "COALESCE(NULLIF(h.nome_hosp, ''), 'Sem hospital') AS label, COUNT(DISTINCT ca.id_capeante) AS value, CONCAT('R$ ', ROUND(SUM(COALESCE(ca.valor_apresentado_capeante, 0)), 2)) AS extra",
                    '',
                    $capeanteWhere . " AND LOWER(COALESCE(ca.encerrado_cap, 'n')) <> 's'",
                    'GROUP BY h.id_hospital, h.nome_hosp',
                    'ORDER BY value DESC, label ASC',
                    'LIMIT 15',
                    $capeanteFrom,
                ];
            case 'contas_paradas_por_hospital':
                return [
                    "COALESCE(NULLIF(h.nome_hosp, ''), 'Sem hospital') AS label, COUNT(DISTINCT ca.id_capeante) AS value, CONCAT('R$ ', ROUND(SUM(COALESCE(ca.valor_apresentado_capeante, 0)), 2)) AS extra",
                    '',
                    $capeanteWhere . " AND LOWER(COALESCE(ca.conta_parada_cap, 'n')) = 's'",
                    'GROUP BY h.id_hospital, h.nome_hosp',
                    'ORDER BY value DESC, label ASC',
                    'LIMIT 15',
                    $capeanteFrom,
                ];
            case 'eventos_por_tipo':
                return [
                    "COALESCE(NULLIF(g.tipo_evento_adverso_gest, ''), 'Sem informação') AS label, COUNT(DISTINCT g.id_gestao) AS value, '' AS extra",
                    '',
                    $gestaoWhere . " AND LOWER(COALESCE(g.evento_adverso_ges, 'n')) = 's'",
                    'GROUP BY label',
                    'ORDER BY value DESC, label ASC',
                    'LIMIT 15',
                    $gestaoFrom,
                ];
            case 'eventos_gestao_por_hospital':
                return [
                    "COALESCE(NULLIF(h.nome_hosp, ''), 'Sem hospital') AS label, COUNT(DISTINCT g.id_gestao) AS value, '' AS extra",
                    '',
                    $gestaoWhere,
                    'GROUP BY h.id_hospital, h.nome_hosp',
                    'ORDER BY value DESC, label ASC',
                    'LIMIT 15',
                    $gestaoFrom,
                ];
            case 'alto_custo_por_hospital':
                return [
                    "COALESCE(NULLIF(h.nome_hosp, ''), 'Sem hospital') AS label, COUNT(DISTINCT g.id_gestao) AS value, '' AS extra",
                    '',
                    $gestaoWhere . " AND LOWER(COALESCE(g.alto_custo_ges, 'n')) = 's'",
                    'GROUP BY h.id_hospital, h.nome_hosp',
                    'ORDER BY value DESC, label ASC',
                    'LIMIT 15',
                    $gestaoFrom,
                ];
            case 'opme_por_hospital':
                return [
                    "COALESCE(NULLIF(h.nome_hosp, ''), 'Sem hospital') AS label, COUNT(DISTINCT g.id_gestao) AS value, '' AS extra",
                    '',
                    $gestaoWhere . " AND LOWER(COALESCE(g.opme_ges, 'n')) = 's'",
                    'GROUP BY h.id_hospital, h.nome_hosp',
                    'ORDER BY value DESC, label ASC',
                    'LIMIT 15',
                    $gestaoFrom,
                ];
            case 'home_care_por_hospital':
                return [
                    "COALESCE(NULLIF(h.nome_hosp, ''), 'Sem hospital') AS label, COUNT(DISTINCT g.id_gestao) AS value, '' AS extra",
                    '',
                    $gestaoWhere . " AND LOWER(COALESCE(g.home_care_ges, 'n')) = 's'",
                    'GROUP BY h.id_hospital, h.nome_hosp',
                    'ORDER BY value DESC, label ASC',
                    'LIMIT 15',
                    $gestaoFrom,
                ];
            case 'desospitalizacao_por_hospital':
                return [
                    "COALESCE(NULLIF(h.nome_hosp, ''), 'Sem hospital') AS label, COUNT(DISTINCT g.id_gestao) AS value, '' AS extra",
                    '',
                    $gestaoWhere . " AND LOWER(COALESCE(g.desospitalizacao_ges, 'n')) = 's'",
                    'GROUP BY h.id_hospital, h.nome_hosp',
                    'ORDER BY value DESC, label ASC',
                    'LIMIT 15',
                    $gestaoFrom,
                ];
            case 'visitas_por_hospital':
                return [
                    "COALESCE(NULLIF(h.nome_hosp, ''), 'Sem hospital') AS label, COUNT(DISTINCT v.id_visita) AS value, '' AS extra",
                    '',
                    $visitaWhere,
                    'GROUP BY h.id_hospital, h.nome_hosp',
                    'ORDER BY value DESC, label ASC',
                    'LIMIT 15',
                    $visitaFrom,
                ];
            case 'visitas_faturadas_mensal':
                return [
                    "DATE_FORMAT(COALESCE(NULLIF(v.data_faturamento_vis, '0000-00-00'), v.data_visita_vis), '%Y-%m') AS label, COUNT(DISTINCT v.id_visita) AS value, '' AS extra",
                    '',
                    $visitaWhere . " AND LOWER(COALESCE(v.faturado_vis, 'n')) = 's'",
                    'GROUP BY label',
                    'ORDER BY label ASC',
                    'LIMIT 24',
                    $visitaFrom,
                ];
            case 'internacoes_por_hospital':
            default:
                return [
                    "COALESCE(NULLIF(h.nome_hosp, ''), 'Sem hospital') AS label, COUNT(DISTINCT i.id_internacao) AS value, '' AS extra",
                    '',
                    '',
                    'GROUP BY h.id_hospital, h.nome_hosp',
                    'ORDER BY value DESC, label ASC',
                    'LIMIT 12',
                    $internacaoFrom,
                ];
        }
    }

    private function isSavingTemplate(string $templateKey): bool
    {
        return strpos($templateKey, 'saving_') === 0;
    }

    private function isCapeanteTemplate(string $templateKey): bool
    {
        return in_array($templateKey, [
            'faturamento_apresentado_mensal',
            'faturamento_final_mensal',
            'glosa_mensal',
            'faturamento_por_hospital',
            'glosa_por_hospital',
            'contas_abertas_por_hospital',
            'contas_paradas_por_hospital',
        ], true);
    }

    private function isGestaoTemplate(string $templateKey): bool
    {
        return in_array($templateKey, [
            'eventos_por_tipo',
            'eventos_gestao_por_hospital',
            'alto_custo_por_hospital',
            'opme_por_hospital',
            'home_care_por_hospital',
            'desospitalizacao_por_hospital',
        ], true);
    }

    private function isVisitaTemplate(string $templateKey): bool
    {
        return in_array($templateKey, [
            'visitas_por_hospital',
            'visitas_faturadas_mensal',
        ], true);
    }

    private function formatChartLabel(string $label, string $templateKey): string
    {
        $monthlyTemplates = [
            'internacoes_por_mes',
            'saving_mensal',
            'faturamento_apresentado_mensal',
            'faturamento_final_mensal',
            'glosa_mensal',
            'visitas_faturadas_mensal',
        ];
        if (!in_array($templateKey, $monthlyTemplates, true)) {
            return $label;
        }
        if (!preg_match('/^(\d{4})-(\d{2})(?:-\d{2})?$/', trim($label), $m)) {
            return $label;
        }

        $months = [
            1 => 'jan',
            2 => 'fev',
            3 => 'mar',
            4 => 'abr',
            5 => 'mai',
            6 => 'jun',
            7 => 'jul',
            8 => 'ago',
            9 => 'set',
            10 => 'out',
            11 => 'nov',
            12 => 'dez',
        ];
        $month = (int)$m[2];
        if (!isset($months[$month])) {
            return $label;
        }
        return $months[$month] . '/' . substr($m[1], -2);
    }

    private function buildChart(array $spec, array $rows): array
    {
        return [
            'type' => $spec['chart_type'],
            'labels' => array_column($rows, 'label'),
            'dataset_label' => $spec['metric'],
            'values' => array_map(fn($row) => (float)$row['value'], $rows),
            'dimension' => $spec['dimension'],
            'metric' => $spec['metric'],
        ];
    }

    private function requestChartSpec(string $question, array $filters, array $templates): array
    {
        if (!function_exists('curl_init') || $this->apiKey === '') {
            return [];
        }

        $available = array_map(function (array $tpl): array {
            return [
                'key' => $tpl['key'],
                'title' => $tpl['title'],
                'dimension' => $tpl['dimension'],
                'metric' => $tpl['metric'],
                'chart_type' => $tpl['chart_type'],
            ];
        }, array_values($templates));

        $payload = [
            'model' => $this->model,
            'input' => [
                [
                    'role' => 'system',
                    'content' => [[
                        'type' => 'input_text',
                        'text' => 'Você escolhe gráficos seguros para um sistema hospitalar. Responda apenas JSON válido. Não crie SQL. Use somente template_key da lista.',
                    ]],
                ],
                [
                    'role' => 'user',
                    'content' => [[
                        'type' => 'input_text',
                        'text' => "Pedido: {$question}\nFiltros: " . json_encode($filters, JSON_UNESCAPED_UNICODE) . "\nTemplates permitidos: " . json_encode($available, JSON_UNESCAPED_UNICODE) . "\nRetorne JSON no formato {\"template_key\":\"...\",\"chart_type\":\"bar|line|doughnut\"}.",
                    ]],
                ],
            ],
            'temperature' => 0.05,
            'max_output_tokens' => 160,
        ];

        $raw = $this->requestOpenAi($payload);
        $decoded = json_decode($raw, true);
        $text = is_array($decoded) ? $this->extractText($decoded) : '';
        $jsonText = $this->extractJsonObject((string)$text);
        $spec = json_decode($jsonText, true);
        return is_array($spec) ? $spec : [];
    }

    private function requestInsight(string $question, array $spec, array $filters, array $rows): string
    {
        if (!function_exists('curl_init') || $this->apiKey === '') {
            return $this->buildLocalInsight($question, $spec, $filters, $rows);
        }

        $payload = [
            'model' => $this->model,
            'input' => [
                [
                    'role' => 'system',
                    'content' => [[
                        'type' => 'input_text',
                        'text' => 'Você é um analista de inteligência operacional hospitalar do FullCare. Responda em português-BR, com leitura objetiva dos dados. Não faça diagnóstico nem prescrição.',
                    ]],
                ],
                [
                    'role' => 'user',
                    'content' => [[
                        'type' => 'input_text',
                        'text' => "Pergunta: {$question}\nGráfico: {$spec['title']}\nFiltros: " . json_encode($filters, JSON_UNESCAPED_UNICODE) . "\nDados: " . json_encode($rows, JSON_UNESCAPED_UNICODE) . "\nResponda em até 5 bullets curtos: principal leitura, maior concentração, atenção operacional e próximo passo.",
                    ]],
                ],
            ],
            'temperature' => 0.15,
            'max_output_tokens' => 500,
        ];

        $raw = $this->requestOpenAi($payload);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Resposta inválida da IA.');
        }
        $text = $this->extractText($decoded);
        if ($text === null || trim($text) === '') {
            throw new RuntimeException('A IA retornou resposta vazia.');
        }
        return trim($text);
    }

    private function buildLocalInsight(string $question, array $spec, array $filters, array $rows): string
    {
        if (!$rows) {
            return "Não encontrei dados para montar esse gráfico com os filtros atuais.";
        }

        $total = array_sum(array_map(fn($row) => (float)$row['value'], $rows));
        $top = $rows[0];
        $topValue = (float)$top['value'];
        $share = $total > 0 ? round(($topValue / $total) * 100, 1) : 0;
        $period = (int)$filters['days'];
        $topFormatted = $this->formatMetricValue($topValue, (string)($spec['metric'] ?? ''));
        $totalFormatted = $this->formatMetricValue((float)$total, (string)($spec['metric'] ?? ''));

        $text = "Leitura local do gráfico:\n";
        $text .= "- Indicador: {$spec['metric']} por {$spec['dimension']} nos últimos {$period} dias.\n";
        $text .= "- Maior concentração: {$top['label']} com {$topFormatted} (" . $share . "% do total exibido).\n";
        $text .= "- Total exibido no gráfico: {$totalFormatted}.\n";
        $text .= "- Próximo passo: revisar os primeiros itens do ranking e cruzar com visitas, permanência e pendências operacionais.";
        return $text;
    }

    private function formatMetricValue(float $value, string $metric): string
    {
        if (strpos($metric, 'R$') !== false || preg_match('/saving|valor|glosa|faturamento|custo/i', $metric)) {
            return 'R$ ' . number_format($value, 2, ',', '.');
        }
        return fmod($value, 1.0) === 0.0
            ? number_format($value, 0, ',', '.')
            : number_format($value, 2, ',', '.');
    }

    private function requestOpenAi(array $payload): string
    {
        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 35,
        ]);

        $raw = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $curlErr) {
            throw new RuntimeException('Falha de conexão com o serviço de IA.');
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException('Serviço de IA indisponível no momento.');
        }
        return (string)$raw;
    }

    private function extractText(array $responseJson): ?string
    {
        if (!empty($responseJson['output_text']) && is_string($responseJson['output_text'])) {
            return trim($responseJson['output_text']);
        }
        if (empty($responseJson['output']) || !is_array($responseJson['output'])) {
            return null;
        }
        $parts = [];
        foreach ($responseJson['output'] as $item) {
            foreach (($item['content'] ?? []) as $content) {
                if (!empty($content['text']) && is_string($content['text'])) {
                    $parts[] = $content['text'];
                }
            }
        }
        return $parts ? trim(implode("\n", $parts)) : null;
    }

    private function extractJsonObject(string $text): string
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end < $start) {
            return '{}';
        }
        return substr($text, $start, $end - $start + 1);
    }

    private function normalizeText(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = $conv !== false ? $conv : $text;
        return preg_replace('/[^a-z0-9 ]+/', ' ', $text);
    }

    private function scopeClause(array $ctx, string $alias, array &$params, string $prefix): string
    {
        if (function_exists('ajax_scope_clause_for_internacao')) {
            return ajax_scope_clause_for_internacao($ctx, $alias, $params, $prefix);
        }
        return '';
    }

    private function assertHospitalAccess(array $ctx, int $hospitalId): bool
    {
        if (function_exists('ajax_assert_hospital_access')) {
            return ajax_assert_hospital_access($this->conn, $ctx, $hospitalId);
        }
        return true;
    }

    private function bindParams(PDOStatement $stmt, array $params): void
    {
        if (function_exists('ajax_bind_params')) {
            ajax_bind_params($stmt, $params);
            return;
        }
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }

    private function loadEnvFile(string $path): void
    {
        if (!is_readable($path)) {
            return;
        }
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }
        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            if ($key === '') {
                continue;
            }
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }
            if (getenv($key) === false || getenv($key) === '') {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
            }
        }
    }
}
