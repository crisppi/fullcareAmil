<?php

class AuditoriaClinicaAIService
{
    private PDO $conn;
    private string $baseUrl;
    private string $apiKey;
    private string $apiUrl;
    private string $model;

    public function __construct(PDO $conn, string $baseUrl)
    {
        $this->conn = $conn;
        $this->baseUrl = rtrim($baseUrl, '/') . '/';
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
             WHERE LOWER(COALESCE(i.deletado_int, 'n')) <> 's' {$scope}
             ORDER BY h.nome_hosp
             LIMIT 300
        ";
        $stmt = $this->conn->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function answer(string $question, array $filters, array $ctx): array
    {
        $question = trim($question);
        if ($question === '') {
            throw new InvalidArgumentException('Digite uma pergunta clínica sobre as internações.');
        }
        if (mb_strlen($question, 'UTF-8') > 1200) {
            throw new InvalidArgumentException('Pergunta muito longa. Tente ser mais objetivo.');
        }

        $filters = $this->normalizeFilters($filters, $ctx);
        $rows = $this->fetchClinicalCases($filters, $ctx, 45);
        $summary = $this->buildDatasetSummary($rows, $filters);
        $source = ($this->apiKey !== '' && function_exists('curl_init')) ? 'openai' : 'local';

        try {
            $answer = $this->requestAnswer($question, $summary);
        } catch (Throwable $e) {
            $source = 'local';
            $answer = $this->buildLocalAnswer($question, $summary)
                . "\n\nObservação técnica: a IA não respondeu agora; usei uma leitura local dos dados clínicos estruturados.";
        }

        return [
            'answer' => $answer,
            'results' => $this->buildResults($rows),
            'summary' => [
                'total_contexto' => count($rows),
                'filtros' => $filters,
                'source' => $source,
            ],
        ];
    }

    private function normalizeFilters(array $filters, array $ctx): array
    {
        $hospitalId = (int)($filters['hospital_id'] ?? 0);
        $status = trim((string)($filters['status'] ?? 'internados'));
        $status = in_array($status, ['internados', 'todos', 'alta'], true) ? $status : 'internados';
        $focus = trim((string)($filters['focus'] ?? 'geral'));
        $focus = in_array($focus, ['geral', 'uti', 'eventos', 'patologia', 'longa_permanencia', 'sem_visita', 'oportunidade'], true) ? $focus : 'geral';
        $limitDays = (int)($filters['days'] ?? 180);
        $limitDays = max(7, min(730, $limitDays));

        if ($hospitalId > 0 && !$this->assertHospitalAccess($ctx, $hospitalId)) {
            $hospitalId = 0;
        }

        return [
            'hospital_id' => $hospitalId,
            'status' => $status,
            'focus' => $focus,
            'days' => $limitDays,
        ];
    }

    private function fetchClinicalCases(array $filters, array $ctx, int $limit): array
    {
        $params = [
            ':days' => (int)$filters['days'],
            ':limit' => max(10, min(80, $limit)),
        ];
        $where = [
            "i.data_intern_int IS NOT NULL",
            "i.data_intern_int <> '0000-00-00'",
            "i.data_intern_int >= DATE_SUB(CURDATE(), INTERVAL :days DAY)",
            "LOWER(COALESCE(i.deletado_int, 'n')) <> 's'",
        ];

        if ((int)$filters['hospital_id'] > 0) {
            $where[] = 'i.fk_hospital_int = :hospital_id';
            $params[':hospital_id'] = (int)$filters['hospital_id'];
        }

        if ($filters['status'] === 'internados') {
            $where[] = "LOWER(COALESCE(i.internado_int, '')) = 's'";
        } elseif ($filters['status'] === 'alta') {
            $where[] = "LOWER(COALESCE(i.internado_int, '')) <> 's'";
        }

        if ($filters['focus'] === 'uti') {
            $where[] = "uti.id_uti IS NOT NULL";
        } elseif ($filters['focus'] === 'eventos') {
            $where[] = "COALESCE(ges.eventos_adversos, 0) > 0";
        } elseif ($filters['focus'] === 'patologia') {
            $where[] = "(i.fk_patologia_int IS NOT NULL OR COALESCE(i.grupo_patologia_int, '') <> '')";
        } elseif ($filters['focus'] === 'longa_permanencia') {
            $where[] = "GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1) >= 15";
        } elseif ($filters['focus'] === 'sem_visita') {
            $where[] = "(lv.ultima_visita IS NULL OR DATEDIFF(CURDATE(), lv.ultima_visita) >= 5)";
        } elseif ($filters['focus'] === 'oportunidade') {
            $where[] = "(
                GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1) >= 15
                OR COALESCE(ges.home_care, 0) > 0
                OR COALESCE(ges.desospitalizacao, 0) > 0
                OR COALESCE(ges.opme, 0) > 0
                OR (lv.ultima_visita IS NULL OR DATEDIFF(CURDATE(), lv.ultima_visita) >= 5)
            )";
        }

        $scope = $this->scopeClause($ctx, 'i', $params, 'clin');
        $whereSql = implode(' AND ', $where);

        $sql = "
            SELECT
                i.id_internacao,
                i.fk_paciente_int,
                i.data_intern_int,
                i.internado_int,
                i.acomodacao_int,
                i.especialidade_int,
                i.tipo_admissao_int,
                i.modo_internacao_int,
                i.grupo_patologia_int,
                i.fk_cid_int,
                LEFT(COALESCE(i.rel_int, ''), 600) AS rel_internacao,
                LEFT(COALESCE(i.acoes_int, ''), 500) AS acoes_internacao,
                GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1) AS dias_internado,
                al.data_alta_alt,
                p.nome_pac,
                p.idade_pac,
                p.sexo_pac,
                h.id_hospital,
                h.nome_hosp,
                COALESCE(s.seguradora_seg, '') AS seguradora,
                COALESCE(pa.patologia_pat, '') AS patologia,
                COALESCE(pa.grupo_patologia_pat, i.grupo_patologia_int, '') AS grupo_patologia,
                COALESCE(pa.dias_pato, 0) AS dias_pato,
                lv.ultima_visita,
                lv.total_visitas,
                DATEDIFF(CURDATE(), lv.ultima_visita) AS dias_sem_visita,
                LEFT(COALESCE(lv.resumo_visita, ''), 900) AS resumo_visita,
                uti.id_uti,
                uti.data_internacao_uti,
                uti.data_alta_uti,
                uti.internado_uti,
                uti.especialidade_uti,
                uti.motivo_uti,
                uti.score_uti,
                uti.saps_uti,
                uti.glasgow_uti,
                uti.dva_uti,
                uti.vm_uti,
                uti.suporte_vent_uti,
                LEFT(COALESCE(uti.criterios_uti, ''), 450) AS criterios_uti,
                LEFT(COALESCE(uti.just_uti, ''), 450) AS just_uti,
                LEFT(COALESCE(uti.rel_uti, ''), 700) AS rel_uti,
                COALESCE(ges.registros_gestao, 0) AS registros_gestao,
                COALESCE(ges.eventos_adversos, 0) AS eventos_adversos,
                COALESCE(ges.eventos_abertos, 0) AS eventos_abertos,
                COALESCE(ges.opme, 0) AS opme,
                COALESCE(ges.home_care, 0) AS home_care,
                COALESCE(ges.desospitalizacao, 0) AS desospitalizacao,
                COALESCE(ges.alto_custo, 0) AS alto_custo,
                LEFT(COALESCE(ges.tipos_evento, ''), 600) AS tipos_evento,
                LEFT(COALESCE(ges.rel_eventos, ''), 900) AS rel_eventos
            FROM tb_internacao i
            JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
            JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
            LEFT JOIN tb_seguradora s ON s.id_seguradora = p.fk_seguradora_pac
            LEFT JOIN tb_patologia pa ON pa.id_patologia = i.fk_patologia_int
            LEFT JOIN (
                SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt
                  FROM tb_alta
                 WHERE data_alta_alt IS NOT NULL AND data_alta_alt <> '0000-00-00'
                 GROUP BY fk_id_int_alt
            ) al ON al.fk_id_int_alt = i.id_internacao
            LEFT JOIN (
                SELECT v.fk_internacao_vis,
                       MAX(v.data_visita_vis) AS ultima_visita,
                       COUNT(*) AS total_visitas,
                       SUBSTRING_INDEX(
                           GROUP_CONCAT(
                               NULLIF(CONCAT_WS(' | ',
                                   NULLIF(v.rel_visita_vis, ''),
                                   NULLIF(v.acoes_int_vis, ''),
                                   NULLIF(v.programacao_enf, ''),
                                   NULLIF(v.oportunidades_enf, ''),
                                   NULLIF(v.exames_enf, '')
                               ), '')
                               ORDER BY v.data_visita_vis DESC, v.id_visita DESC
                               SEPARATOR ' || '
                           ),
                           ' || ',
                           1
                       ) AS resumo_visita
                  FROM tb_visita v
                 WHERE v.data_visita_vis IS NOT NULL AND v.data_visita_vis <> '0000-00-00'
                 GROUP BY v.fk_internacao_vis
            ) lv ON lv.fk_internacao_vis = i.id_internacao
            LEFT JOIN (
                SELECT u1.*
                  FROM tb_uti u1
                  JOIN (
                        SELECT fk_internacao_uti, MAX(id_uti) AS max_id
                          FROM tb_uti
                         GROUP BY fk_internacao_uti
                  ) ux ON ux.max_id = u1.id_uti
            ) uti ON uti.fk_internacao_uti = i.id_internacao
            LEFT JOIN (
                SELECT g.fk_internacao_ges,
                       COUNT(*) AS registros_gestao,
                       SUM(CASE WHEN LOWER(COALESCE(g.evento_adverso_ges, 'n')) = 's' THEN 1 ELSE 0 END) AS eventos_adversos,
                       SUM(CASE WHEN LOWER(COALESCE(g.evento_adverso_ges, 'n')) = 's' AND LOWER(COALESCE(g.evento_encerrar_ges, 'n')) <> 's' THEN 1 ELSE 0 END) AS eventos_abertos,
                       SUM(CASE WHEN LOWER(COALESCE(g.opme_ges, 'n')) = 's' THEN 1 ELSE 0 END) AS opme,
                       SUM(CASE WHEN LOWER(COALESCE(g.home_care_ges, 'n')) = 's' THEN 1 ELSE 0 END) AS home_care,
                       SUM(CASE WHEN LOWER(COALESCE(g.desospitalizacao_ges, 'n')) = 's' THEN 1 ELSE 0 END) AS desospitalizacao,
                       SUM(CASE WHEN LOWER(COALESCE(g.alto_custo_ges, 'n')) = 's' THEN 1 ELSE 0 END) AS alto_custo,
                       GROUP_CONCAT(DISTINCT NULLIF(g.tipo_evento_adverso_gest, '') ORDER BY g.tipo_evento_adverso_gest SEPARATOR ', ') AS tipos_evento,
                       SUBSTRING_INDEX(
                           GROUP_CONCAT(
                               NULLIF(CONCAT_WS(' | ',
                                   NULLIF(g.tipo_evento_adverso_gest, ''),
                                   NULLIF(g.rel_evento_adverso_ges, ''),
                                   NULLIF(g.rel_opme_ges, ''),
                                   NULLIF(g.rel_home_care_ges, ''),
                                   NULLIF(g.rel_desospitalizacao_ges, '')
                               ), '')
                               ORDER BY g.data_create_ges DESC, g.id_gestao DESC
                               SEPARATOR ' || '
                           ),
                           ' || ',
                           2
                       ) AS rel_eventos
                  FROM tb_gestao g
                 WHERE LOWER(COALESCE(g.deletado_ges, 'n')) <> 's'
                 GROUP BY g.fk_internacao_ges
            ) ges ON ges.fk_internacao_ges = i.id_internacao
            WHERE {$whereSql}
            {$scope}
            ORDER BY
                CASE WHEN COALESCE(ges.eventos_adversos, 0) > 0 THEN 0 ELSE 1 END,
                CASE WHEN uti.id_uti IS NOT NULL THEN 0 ELSE 1 END,
                dias_internado DESC,
                i.id_internacao DESC
            LIMIT :limit
        ";

        $stmt = $this->conn->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function buildDatasetSummary(array $rows, array $filters): array
    {
        $ind = [
            'total_consultado' => count($rows),
            'internados' => 0,
            'longa_permanencia_15d_ou_mais' => 0,
            'sem_visita_5d_ou_mais' => 0,
            'com_uti' => 0,
            'com_evento_adverso' => 0,
            'eventos_abertos' => 0,
            'opme' => 0,
            'home_care' => 0,
            'desospitalizacao' => 0,
            'oportunidade_qualitativa' => 0,
        ];
        $patologias = [];
        $hospitais = [];
        $eventos = [];

        foreach ($rows as $row) {
            $dias = (int)($row['dias_internado'] ?? 0);
            $semVisita = (($row['ultima_visita'] ?? '') === '' || (int)($row['dias_sem_visita'] ?? 0) >= 5);
            $uti = !empty($row['id_uti']);
            $evento = (int)($row['eventos_adversos'] ?? 0) > 0;
            $oportunidade = $dias >= 15 || $semVisita || $uti || (int)($row['home_care'] ?? 0) > 0 || (int)($row['desospitalizacao'] ?? 0) > 0 || (int)($row['opme'] ?? 0) > 0;

            if (strtolower((string)($row['internado_int'] ?? '')) === 's') {
                $ind['internados']++;
            }
            if ($dias >= 15) {
                $ind['longa_permanencia_15d_ou_mais']++;
            }
            if ($semVisita) {
                $ind['sem_visita_5d_ou_mais']++;
            }
            if ($uti) {
                $ind['com_uti']++;
            }
            if ($evento) {
                $ind['com_evento_adverso']++;
            }
            $ind['eventos_abertos'] += (int)($row['eventos_abertos'] ?? 0);
            $ind['opme'] += (int)($row['opme'] ?? 0);
            $ind['home_care'] += (int)($row['home_care'] ?? 0);
            $ind['desospitalizacao'] += (int)($row['desospitalizacao'] ?? 0);
            if ($oportunidade) {
                $ind['oportunidade_qualitativa']++;
            }

            $pat = trim((string)($row['patologia'] ?: $row['grupo_patologia'] ?: 'Sem patologia informada'));
            $hosp = trim((string)($row['nome_hosp'] ?: 'Sem hospital'));
            $tipo = trim((string)($row['tipos_evento'] ?: 'Sem tipo informado'));
            $patologias[$pat] = ($patologias[$pat] ?? 0) + 1;
            $hospitais[$hosp] = ($hospitais[$hosp] ?? 0) + 1;
            if ($evento) {
                $eventos[$tipo] = ($eventos[$tipo] ?? 0) + 1;
            }
        }

        arsort($patologias);
        arsort($hospitais);
        arsort($eventos);

        return [
            'escopo' => 'Auditoria clinica em Producao. Dados financeiros, faturamento, custos e saving real nao sao consultados nesta pagina.',
            'filtros' => $filters,
            'indicadores' => $ind,
            'rankings' => [
                'patologias' => $this->mapRanking($patologias, 10),
                'hospitais' => $this->mapRanking($hospitais, 8),
                'eventos_adversos' => $this->mapRanking($eventos, 8),
            ],
            'internacoes' => array_map(fn($row) => $this->rowForPrompt($row), $rows),
        ];
    }

    private function mapRanking(array $items, int $limit): array
    {
        $out = [];
        foreach (array_slice($items, 0, $limit, true) as $label => $total) {
            $out[] = ['label' => $label, 'total' => (int)$total];
        }
        return $out;
    }

    private function rowForPrompt(array $row): array
    {
        return [
            'id_internacao' => (int)($row['id_internacao'] ?? 0),
            'paciente' => $row['nome_pac'] ?? '',
            'hospital' => $row['nome_hosp'] ?? '',
            'seguradora' => $row['seguradora'] ?? '',
            'idade' => $row['idade_pac'] ?? null,
            'sexo' => $row['sexo_pac'] ?? '',
            'data_internacao' => $row['data_intern_int'] ?? '',
            'data_alta' => $row['data_alta_alt'] ?? null,
            'dias_internado' => (int)($row['dias_internado'] ?? 0),
            'status_internado' => $row['internado_int'] ?? '',
            'acomodacao' => $row['acomodacao_int'] ?? '',
            'especialidade' => $row['especialidade_int'] ?? '',
            'tipo_admissao' => $row['tipo_admissao_int'] ?? '',
            'modo_internacao' => $row['modo_internacao_int'] ?? '',
            'patologia' => $row['patologia'] ?: ($row['grupo_patologia'] ?? ''),
            'grupo_patologia' => $row['grupo_patologia'] ?? '',
            'cid' => $row['fk_cid_int'] ?? '',
            'dias_previstos_patologia' => (int)($row['dias_pato'] ?? 0),
            'relato_internacao' => $this->truncate((string)($row['rel_internacao'] ?? ''), 420),
            'acoes_internacao' => $this->truncate((string)($row['acoes_internacao'] ?? ''), 320),
            'ultima_visita' => $row['ultima_visita'] ?? null,
            'total_visitas' => (int)($row['total_visitas'] ?? 0),
            'dias_sem_visita' => $row['dias_sem_visita'] !== null ? (int)$row['dias_sem_visita'] : null,
            'resumo_ultima_visita' => $this->truncate((string)($row['resumo_visita'] ?? ''), 520),
            'uti' => [
                'tem_registro' => !empty($row['id_uti']),
                'internado_uti' => $row['internado_uti'] ?? '',
                'data_internacao_uti' => $row['data_internacao_uti'] ?? null,
                'data_alta_uti' => $row['data_alta_uti'] ?? null,
                'especialidade' => $row['especialidade_uti'] ?? '',
                'motivo' => $row['motivo_uti'] ?? '',
                'score' => $row['score_uti'] ?? '',
                'saps' => $row['saps_uti'] ?? '',
                'glasgow' => $row['glasgow_uti'] ?? '',
                'dva' => $row['dva_uti'] ?? '',
                'vm' => $row['vm_uti'] ?? '',
                'suporte_ventilatorio' => $row['suporte_vent_uti'] ?? '',
                'criterios' => $this->truncate((string)($row['criterios_uti'] ?? ''), 300),
                'justificativa' => $this->truncate((string)($row['just_uti'] ?? ''), 300),
                'relatorio' => $this->truncate((string)($row['rel_uti'] ?? ''), 420),
            ],
            'eventos' => [
                'eventos_adversos' => (int)($row['eventos_adversos'] ?? 0),
                'eventos_abertos' => (int)($row['eventos_abertos'] ?? 0),
                'tipos' => $row['tipos_evento'] ?? '',
                'relatos' => $this->truncate((string)($row['rel_eventos'] ?? ''), 520),
                'opme' => (int)($row['opme'] ?? 0),
                'home_care' => (int)($row['home_care'] ?? 0),
                'desospitalizacao' => (int)($row['desospitalizacao'] ?? 0),
                'alto_custo_sinalizado_sem_valores' => (int)($row['alto_custo'] ?? 0),
            ],
        ];
    }

    private function buildResults(array $rows): array
    {
        return array_map(function (array $row): array {
            $dias = (int)($row['dias_internado'] ?? 0);
            $diasVisita = $row['dias_sem_visita'] !== null ? (int)$row['dias_sem_visita'] : null;
            $flags = [];
            if ($dias >= 15) {
                $flags[] = 'Longa permanencia';
            }
            if ($diasVisita === null || $diasVisita >= 5) {
                $flags[] = 'Sem visita recente';
            }
            if (!empty($row['id_uti'])) {
                $flags[] = 'UTI';
            }
            if ((int)($row['eventos_adversos'] ?? 0) > 0) {
                $flags[] = 'Evento adverso';
            }
            if ((int)($row['home_care'] ?? 0) > 0 || (int)($row['desospitalizacao'] ?? 0) > 0) {
                $flags[] = 'Desospitalizacao';
            }
            if ((int)($row['opme'] ?? 0) > 0) {
                $flags[] = 'OPME';
            }

            $id = (int)($row['id_internacao'] ?? 0);
            return [
                'id' => $id,
                'paciente' => (string)($row['nome_pac'] ?? ''),
                'hospital' => (string)($row['nome_hosp'] ?? ''),
                'patologia' => (string)($row['patologia'] ?: $row['grupo_patologia'] ?: 'Sem patologia'),
                'dias_internado' => $dias,
                'ultima_visita' => (string)($row['ultima_visita'] ?? ''),
                'dias_sem_visita' => $diasVisita,
                'flags' => $flags,
                'url' => $this->baseUrl . 'internacoes/visualizar/' . $id,
            ];
        }, array_slice($rows, 0, 18));
    }

    private function requestAnswer(string $question, array $summary): string
    {
        if (!function_exists('curl_init') || $this->apiKey === '') {
            return $this->buildLocalAnswer($question, $summary);
        }

        $json = json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $payload = [
            'model' => $this->model,
            'input' => [
                [
                    'role' => 'system',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => 'Voce e um assistente de pesquisa clinico-operacional para auditoria hospitalar do FullCare. Responda em portugues-BR. Use exclusivamente dados de internacao, patologia, visitas, UTI e eventos clinicos fornecidos. Nao faca diagnostico, prescricao, nem decisao medica automatica. Nao use nem invente custos, faturamento, glosa, capeante, negociacao ou saving real. Se o usuario pedir valores ou saving, explique que esta pagina nao consulta dados financeiros; voce pode apenas apontar oportunidade qualitativa de economia assistencial sem numeros.',
                        ],
                    ],
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => "Pergunta do auditor:\n{$question}\n\nDados clinicos disponiveis em JSON:\n{$json}\n\nResponda com:\n1. sintese direta;\n2. casos ou achados com ID da internacao;\n3. pontos de auditoria clinica a revisar.\nUse bullets curtos e deixe claro quando faltar registro.",
                        ],
                    ],
                ],
            ],
            'temperature' => 0.12,
            'max_output_tokens' => 1200,
        ];

        $raw = $this->requestOpenAi($payload);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Resposta invalida da IA.');
        }
        $text = $this->extractText($decoded);
        if ($text === null || $text === '') {
            throw new RuntimeException('A IA retornou resposta vazia.');
        }
        return $text;
    }

    private function buildLocalAnswer(string $question, array $summary): string
    {
        $ind = $summary['indicadores'] ?? [];
        $rank = $summary['rankings'] ?? [];
        $rows = $summary['internacoes'] ?? [];
        $top = array_slice($rows, 0, 7);
        $asksFinancial = (bool)preg_match('/saving|custo|custos|fatur|glosa|capeante|valor|sinistro|negocia/i', $question);

        $text = "Leitura clinica da auditoria: foram consultadas " . (int)($ind['total_consultado'] ?? 0) . " internacoes; "
            . (int)($ind['longa_permanencia_15d_ou_mais'] ?? 0) . " em longa permanencia, "
            . (int)($ind['com_uti'] ?? 0) . " com registro de UTI, "
            . (int)($ind['com_evento_adverso'] ?? 0) . " com evento adverso e "
            . (int)($ind['sem_visita_5d_ou_mais'] ?? 0) . " sem visita recente.\n\n";

        if ($asksFinancial) {
            $text .= "Importante: esta pagina nao consulta saving real, custos, faturamento, glosa ou negociacoes. Posso apenas apontar possibilidades qualitativas de economia assistencial, como longa permanencia, pendencia de visita, OPME, UTI, home care ou desospitalizacao.\n\n";
        }

        $text .= "Principais casos para revisao clinica:\n";
        if (!$top) {
            $text .= "- Nenhum caso encontrado para os filtros selecionados.\n";
        }
        foreach ($top as $row) {
            $flags = [];
            if ((int)($row['dias_internado'] ?? 0) >= 15) {
                $flags[] = 'longa permanencia';
            }
            if (!empty($row['uti']['tem_registro'])) {
                $flags[] = 'UTI';
            }
            if ((int)($row['eventos']['eventos_adversos'] ?? 0) > 0) {
                $flags[] = 'evento adverso';
            }
            if (($row['dias_sem_visita'] ?? null) === null || (int)$row['dias_sem_visita'] >= 5) {
                $flags[] = 'sem visita recente';
            }
            $text .= "- ID " . (int)($row['id_internacao'] ?? 0) . " - "
                . ($row['paciente'] ?? 'Paciente') . ", "
                . ($row['hospital'] ?? 'hospital') . ", "
                . (int)($row['dias_internado'] ?? 0) . " dia(s), "
                . (($row['patologia'] ?? '') ?: 'patologia nao informada')
                . ($flags ? " (" . implode(', ', $flags) . ")" : "")
                . ".\n";
        }

        $text .= "\nPatologias mais frequentes no recorte:\n";
        $patologias = array_slice($rank['patologias'] ?? [], 0, 5);
        if (!$patologias) {
            $text .= "- Sem patologia registrada no recorte.\n";
        }
        foreach ($patologias as $item) {
            $text .= "- " . ($item['label'] ?? 'Sem patologia') . ": " . (int)($item['total'] ?? 0) . " caso(s).\n";
        }

        $text .= "\nPontos de auditoria clinica: validar pertinencia da permanencia, registro da evolucao, ultima visita, criterios de UTI, eventos adversos abertos e possibilidade qualitativa de desospitalizacao/home care quando houver indicacao documentada.";
        return $text;
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
            throw new RuntimeException('Falha de conexao com o servico de IA.');
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            $message = $this->extractErrorMessage((string)$raw);
            throw new RuntimeException('Servico de IA indisponivel no momento (HTTP ' . $httpCode . ').' . ($message !== '' ? ' Detalhe: ' . $message : ''));
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

    private function extractErrorMessage(string $raw): string
    {
        $decoded = json_decode($raw, true);
        $message = is_array($decoded) ? (string)($decoded['error']['message'] ?? $decoded['message'] ?? '') : '';
        return (string)preg_replace('/sk-[A-Za-z0-9_-]+/', '[chave removida]', trim($message));
    }

    private function scopeClause(array $ctx, string $alias, array &$params, string $prefix): string
    {
        if (function_exists('ajax_scope_clause_for_internacao')) {
            return ajax_scope_clause_for_internacao($ctx, $alias, $params, $prefix);
        }

        $mode = $this->scopeMode($ctx);
        if ($mode === 'full') {
            return '';
        }
        if ($mode === 'seguradora') {
            $segId = (int)($ctx['seguradora_id'] ?? 0);
            if ($segId <= 0) {
                return ' AND 1=0 ';
            }
            $key = ':' . $prefix . '_seg';
            $params[$key] = $segId;
            return " AND EXISTS (
                SELECT 1
                  FROM tb_paciente pa_scope
                 WHERE pa_scope.id_paciente = {$alias}.fk_paciente_int
                   AND pa_scope.fk_seguradora_pac = {$key}
            ) ";
        }

        $uid = (int)($ctx['user_id'] ?? 0);
        if ($uid <= 0) {
            return ' AND 1=0 ';
        }
        $key = ':' . $prefix . '_uid';
        $params[$key] = $uid;
        return " AND EXISTS (
            SELECT 1
              FROM tb_hospitalUser hu_scope
             WHERE hu_scope.fk_hospital_user = {$alias}.fk_hospital_int
               AND hu_scope.fk_usuario_hosp = {$key}
        ) ";
    }

    private function assertHospitalAccess(array $ctx, int $hospitalId): bool
    {
        if (function_exists('ajax_assert_hospital_access')) {
            return ajax_assert_hospital_access($this->conn, $ctx, $hospitalId);
        }

        $mode = $this->scopeMode($ctx);
        if ($mode === 'full') {
            return true;
        }
        if ($mode === 'seguradora') {
            $segId = (int)($ctx['seguradora_id'] ?? 0);
            if ($segId <= 0) {
                return false;
            }
            $stmt = $this->conn->prepare("
                SELECT 1
                  FROM tb_internacao i
                  JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
                 WHERE i.fk_hospital_int = :hospital_id
                   AND p.fk_seguradora_pac = :seguradora_id
                 LIMIT 1
            ");
            $stmt->bindValue(':hospital_id', $hospitalId, PDO::PARAM_INT);
            $stmt->bindValue(':seguradora_id', $segId, PDO::PARAM_INT);
            $stmt->execute();
            return (bool)$stmt->fetchColumn();
        }

        $uid = (int)($ctx['user_id'] ?? 0);
        if ($uid <= 0) {
            return false;
        }
        $stmt = $this->conn->prepare("
            SELECT 1
              FROM tb_hospitalUser
             WHERE fk_usuario_hosp = :user_id
               AND fk_hospital_user = :hospital_id
             LIMIT 1
        ");
        $stmt->bindValue(':user_id', $uid, PDO::PARAM_INT);
        $stmt->bindValue(':hospital_id', $hospitalId, PDO::PARAM_INT);
        $stmt->execute();
        return (bool)$stmt->fetchColumn();
    }

    private function scopeMode(array $ctx): string
    {
        if (function_exists('ajax_scope_mode')) {
            return ajax_scope_mode($ctx);
        }

        if (!empty($ctx['is_diretoria']) || !empty($ctx['is_system_admin'])) {
            return 'full';
        }
        if (!empty($ctx['is_seguradora'])) {
            return 'seguradora';
        }

        return 'hospital';
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

    private function truncate(string $text, int $max): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        return mb_strlen($text, 'UTF-8') > $max ? mb_substr($text, 0, $max - 3, 'UTF-8') . '...' : $text;
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
