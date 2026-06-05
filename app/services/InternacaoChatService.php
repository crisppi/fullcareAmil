<?php

class InternacaoChatService
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
             WHERE 1=1 {$scope}
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
            throw new InvalidArgumentException('Digite uma pergunta sobre as internações.');
        }
        if (mb_strlen($question, 'UTF-8') > 1200) {
            throw new InvalidArgumentException('Pergunta muito longa. Tente ser mais objetivo.');
        }

        $filters = $this->normalizeFilters($filters, $ctx);
        $rows = $this->fetchInternacoes($filters, $ctx, 40);
        $summary = $this->buildDatasetSummary($rows, $filters);
        $summary['contexto_operacional'] = $this->fetchOperationalContext($filters, $ctx);
        $source = ($this->apiKey !== '' && function_exists('curl_init')) ? 'openai' : 'local';
        try {
            $answer = $this->requestAnswer($question, $summary);
        } catch (Throwable $e) {
            $source = 'local';
            $answer = $this->buildLocalAnswer($question, $summary)
                . "\n\nObservação técnica: a IA não respondeu agora; usei uma leitura local dos dados estruturados.";
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
        $risk = trim((string)($filters['risk'] ?? 'geral'));
        $risk = in_array($risk, ['geral', 'longa_permanencia', 'sem_visita', 'uti', 'evento_adverso'], true) ? $risk : 'geral';
        $limitDays = (int)($filters['days'] ?? 180);
        $limitDays = max(7, min(730, $limitDays));

        if ($hospitalId > 0 && !$this->assertHospitalAccess($ctx, $hospitalId)) {
            $hospitalId = 0;
        }

        return [
            'hospital_id' => $hospitalId,
            'status' => $status,
            'risk' => $risk,
            'days' => $limitDays,
        ];
    }

    private function fetchInternacoes(array $filters, array $ctx, int $limit): array
    {
        $params = [
            ':days' => (int)$filters['days'],
            ':limit' => max(10, min(80, $limit)),
        ];
        $where = [
            "i.data_intern_int >= DATE_SUB(CURDATE(), INTERVAL :days DAY)",
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

        if ($filters['risk'] === 'longa_permanencia') {
            $where[] = "DATEDIFF(CURDATE(), i.data_intern_int) >= 15";
        } elseif ($filters['risk'] === 'sem_visita') {
            $where[] = "(lv.ultima_visita IS NULL OR DATEDIFF(CURDATE(), lv.ultima_visita) >= 5)";
        } elseif ($filters['risk'] === 'uti') {
            $where[] = "EXISTS (SELECT 1 FROM tb_uti u WHERE u.fk_internacao_uti = i.id_internacao)";
        } elseif ($filters['risk'] === 'evento_adverso') {
            $where[] = "EXISTS (
                SELECT 1 FROM tb_gestao g
                 WHERE g.fk_internacao_ges = i.id_internacao
                   AND LOWER(COALESCE(g.evento_adverso_ges, 'n')) = 's'
            )";
        }

        $scope = $this->scopeClause($ctx, 'i', $params, 'scp');
        $whereSql = implode(' AND ', $where);

        $sql = "
            SELECT
                i.id_internacao,
                i.data_intern_int,
                i.internado_int,
                i.acomodacao_int,
                i.grupo_patologia_int,
                i.tipo_admissao_int,
                i.modo_internacao_int,
                DATEDIFF(CURDATE(), i.data_intern_int) AS dias_internado,
                p.id_paciente,
                p.nome_pac,
                p.idade_pac,
                p.sexo_pac,
                h.id_hospital,
                h.nome_hosp,
                COALESCE(s.seguradora_seg, '') AS seguradora,
                lv.ultima_visita,
                DATEDIFF(CURDATE(), lv.ultima_visita) AS dias_sem_visita,
                LEFT(COALESCE(lv.relatorio, ''), 600) AS ultimo_relatorio,
                COALESCE(uti.qtd_uti, 0) AS qtd_uti,
                COALESCE(ges.eventos_adversos, 0) AS eventos_adversos,
                COALESCE(ges.alto_custo, 0) AS alto_custo,
                COALESCE(ges.opme, 0) AS opme,
                COALESCE(ges.home_care, 0) AS home_care,
                COALESCE(ges.desospitalizacao, 0) AS desospitalizacao,
                COALESCE(neg.qtd_negociacoes, 0) AS qtd_negociacoes,
                COALESCE(neg.saving_total, 0) AS saving_total,
                COALESCE(cap.contas_abertas, 0) AS contas_abertas,
                COALESCE(cap.contas_paradas, 0) AS contas_paradas,
                COALESCE(cap.valor_apresentado, 0) AS valor_apresentado,
                COALESCE(cap.valor_final, 0) AS valor_final,
                COALESCE(cap.valor_glosa, 0) AS valor_glosa
            FROM tb_internacao i
            JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
            JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
            LEFT JOIN tb_seguradora s ON s.id_seguradora = p.fk_seguradora_pac
            LEFT JOIN (
                SELECT v1.fk_internacao_vis,
                       MAX(v1.data_visita_vis) AS ultima_visita,
                       SUBSTRING_INDEX(
                           GROUP_CONCAT(COALESCE(NULLIF(v1.rel_visita_vis, ''), NULLIF(v1.acoes_int_vis, ''), '') ORDER BY v1.data_visita_vis DESC SEPARATOR ' || '),
                           ' || ',
                           1
                       ) AS relatorio
                  FROM tb_visita v1
                 GROUP BY v1.fk_internacao_vis
            ) lv ON lv.fk_internacao_vis = i.id_internacao
            LEFT JOIN (
                SELECT fk_internacao_uti, COUNT(*) AS qtd_uti
                  FROM tb_uti
                 GROUP BY fk_internacao_uti
            ) uti ON uti.fk_internacao_uti = i.id_internacao
            LEFT JOIN (
                SELECT fk_internacao_ges,
                       SUM(CASE WHEN LOWER(COALESCE(evento_adverso_ges, 'n')) = 's' THEN 1 ELSE 0 END) AS eventos_adversos,
                       SUM(CASE WHEN LOWER(COALESCE(alto_custo_ges, 'n')) = 's' THEN 1 ELSE 0 END) AS alto_custo,
                       SUM(CASE WHEN LOWER(COALESCE(opme_ges, 'n')) = 's' THEN 1 ELSE 0 END) AS opme,
                       SUM(CASE WHEN LOWER(COALESCE(home_care_ges, 'n')) = 's' THEN 1 ELSE 0 END) AS home_care,
                       SUM(CASE WHEN LOWER(COALESCE(desospitalizacao_ges, 'n')) = 's' THEN 1 ELSE 0 END) AS desospitalizacao
                  FROM tb_gestao
                 WHERE LOWER(COALESCE(deletado_ges, 'n')) <> 's'
                 GROUP BY fk_internacao_ges
            ) ges ON ges.fk_internacao_ges = i.id_internacao
            LEFT JOIN (
                SELECT fk_id_int,
                       COUNT(*) AS qtd_negociacoes,
                       SUM(COALESCE(saving, 0)) AS saving_total
                  FROM tb_negociacao
                 WHERE LOWER(COALESCE(deletado_neg, 'n')) <> 's'
                   AND UPPER(COALESCE(tipo_negociacao, '')) <> 'PRORROGACAO_AUTOMATICA'
                 GROUP BY fk_id_int
            ) neg ON neg.fk_id_int = i.id_internacao
            LEFT JOIN (
                SELECT fk_int_capeante,
                       SUM(CASE WHEN LOWER(COALESCE(encerrado_cap, 'n')) <> 's' THEN 1 ELSE 0 END) AS contas_abertas,
                       SUM(CASE WHEN LOWER(COALESCE(conta_parada_cap, 'n')) = 's' THEN 1 ELSE 0 END) AS contas_paradas,
                       SUM(COALESCE(valor_apresentado_capeante, 0)) AS valor_apresentado,
                       SUM(COALESCE(valor_final_capeante, 0)) AS valor_final,
                       SUM(COALESCE(valor_glosa_total, 0)) AS valor_glosa
                  FROM tb_capeante
                 WHERE LOWER(COALESCE(deletado_cap, 'n')) <> 's'
                 GROUP BY fk_int_capeante
            ) cap ON cap.fk_int_capeante = i.id_internacao
            WHERE {$whereSql}
            {$scope}
            ORDER BY
                CASE WHEN (lv.ultima_visita IS NULL OR DATEDIFF(CURDATE(), lv.ultima_visita) >= 5) THEN 0 ELSE 1 END,
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
        $internados = 0;
        $longa = 0;
        $semVisita = 0;
        $eventos = 0;
        $uti = 0;
        foreach ($rows as $row) {
            if (strtolower((string)($row['internado_int'] ?? '')) === 's') {
                $internados++;
            }
            if ((int)($row['dias_internado'] ?? 0) >= 15) {
                $longa++;
            }
            if (($row['ultima_visita'] ?? '') === '' || (int)($row['dias_sem_visita'] ?? 0) >= 5) {
                $semVisita++;
            }
            if ((int)($row['eventos_adversos'] ?? 0) > 0) {
                $eventos++;
            }
            if ((int)($row['qtd_uti'] ?? 0) > 0) {
                $uti++;
            }
        }

        return [
            'filtros' => $filters,
            'indicadores' => [
                'total_consultado' => count($rows),
                'internados' => $internados,
                'longa_permanencia_15d_ou_mais' => $longa,
                'sem_visita_5d_ou_mais' => $semVisita,
                'com_evento_adverso' => $eventos,
                'com_registro_uti' => $uti,
            ],
            'internacoes' => array_map(fn($row) => $this->rowForPrompt($row), $rows),
        ];
    }

    private function fetchOperationalContext(array $filters, array $ctx): array
    {
        return [
            'financeiro_capeante' => [
                'totais' => $this->fetchFinanceiroTotals($filters, $ctx),
                'top_hospitais_apresentado' => $this->fetchFinanceiroTopHospitais($filters, $ctx, 'valor_apresentado_capeante'),
                'top_hospitais_glosa' => $this->fetchFinanceiroTopHospitais($filters, $ctx, 'valor_glosa_total'),
            ],
            'negociacoes_saving' => [
                'totais' => $this->fetchSavingTotals($filters, $ctx),
                'top_hospitais' => $this->fetchSavingTopHospitais($filters, $ctx),
                'top_tipos' => $this->fetchSavingTopTipos($filters, $ctx),
            ],
            'gestao_eventos' => [
                'totais' => $this->fetchGestaoTotals($filters, $ctx),
                'top_tipos_evento' => $this->fetchGestaoTopTipos($filters, $ctx),
            ],
            'visitas' => [
                'totais' => $this->fetchVisitaTotals($filters, $ctx),
                'top_hospitais' => $this->fetchVisitaTopHospitais($filters, $ctx),
            ],
            'uti' => [
                'totais' => $this->fetchUtiTotals($filters, $ctx),
            ],
        ];
    }

    private function fetchFinanceiroTotals(array $filters, array $ctx): array
    {
        $dateExpr = $this->capeanteDateExpr();
        $params = [':days' => (int)$filters['days']];
        $where = [
            "{$dateExpr} IS NOT NULL",
            "{$dateExpr} >= DATE_SUB(CURDATE(), INTERVAL :days DAY)",
            "LOWER(COALESCE(ca.deletado_cap, 'n')) <> 's'",
        ];
        $this->addHospitalFilter($where, $params, $filters);
        $scope = $this->scopeClause($ctx, 'i', $params, 'fin');
        $sql = "
            SELECT COUNT(DISTINCT ca.id_capeante) AS contas,
                   SUM(COALESCE(ca.valor_apresentado_capeante, 0)) AS valor_apresentado,
                   SUM(COALESCE(ca.valor_final_capeante, 0)) AS valor_final,
                   SUM(COALESCE(ca.valor_glosa_total, 0)) AS valor_glosa,
                   SUM(COALESCE(ca.desconto_valor_cap, 0)) AS desconto,
                   SUM(CASE WHEN LOWER(COALESCE(ca.encerrado_cap, 'n')) <> 's' THEN 1 ELSE 0 END) AS contas_abertas,
                   SUM(CASE WHEN LOWER(COALESCE(ca.conta_parada_cap, 'n')) = 's' THEN 1 ELSE 0 END) AS contas_paradas
              FROM tb_capeante ca
              JOIN tb_internacao i ON i.id_internacao = ca.fk_int_capeante
             WHERE " . implode(' AND ', $where) . " {$scope}
        ";
        return $this->fetchAssoc($sql, $params);
    }

    private function fetchFinanceiroTopHospitais(array $filters, array $ctx, string $field): array
    {
        $allowed = ['valor_apresentado_capeante', 'valor_glosa_total', 'valor_final_capeante'];
        $field = in_array($field, $allowed, true) ? $field : 'valor_apresentado_capeante';
        $dateExpr = $this->capeanteDateExpr();
        $params = [':days' => (int)$filters['days']];
        $where = [
            "{$dateExpr} IS NOT NULL",
            "{$dateExpr} >= DATE_SUB(CURDATE(), INTERVAL :days DAY)",
            "LOWER(COALESCE(ca.deletado_cap, 'n')) <> 's'",
        ];
        $this->addHospitalFilter($where, $params, $filters);
        $scope = $this->scopeClause($ctx, 'i', $params, 'finh');
        $sql = "
            SELECT COALESCE(NULLIF(h.nome_hosp, ''), 'Sem hospital') AS hospital,
                   SUM(COALESCE(ca.{$field}, 0)) AS valor,
                   COUNT(DISTINCT ca.id_capeante) AS contas
              FROM tb_capeante ca
              JOIN tb_internacao i ON i.id_internacao = ca.fk_int_capeante
              JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
             WHERE " . implode(' AND ', $where) . " {$scope}
             GROUP BY h.id_hospital, h.nome_hosp
             ORDER BY valor DESC
             LIMIT 8
        ";
        return $this->fetchAll($sql, $params);
    }

    private function fetchSavingTotals(array $filters, array $ctx): array
    {
        $params = [':days' => (int)$filters['days']];
        $where = $this->savingWhere();
        $this->addHospitalFilter($where, $params, $filters);
        $scope = $this->scopeClause($ctx, 'i', $params, 'sav');
        $sql = "
            SELECT COUNT(DISTINCT ng.id_negociacao) AS negociacoes,
                   SUM(COALESCE(ng.saving, 0)) AS saving,
                   SUM(COALESCE(ng.qtd, 0)) AS quantidade
              FROM tb_negociacao ng
              JOIN tb_internacao i ON i.id_internacao = ng.fk_id_int
             WHERE " . implode(' AND ', $where) . " {$scope}
        ";
        return $this->fetchAssoc($sql, $params);
    }

    private function fetchSavingTopHospitais(array $filters, array $ctx): array
    {
        $params = [':days' => (int)$filters['days']];
        $where = $this->savingWhere();
        $this->addHospitalFilter($where, $params, $filters);
        $scope = $this->scopeClause($ctx, 'i', $params, 'savh');
        $sql = "
            SELECT COALESCE(NULLIF(h.nome_hosp, ''), 'Sem hospital') AS hospital,
                   SUM(COALESCE(ng.saving, 0)) AS saving,
                   COUNT(DISTINCT ng.id_negociacao) AS negociacoes
              FROM tb_negociacao ng
              JOIN tb_internacao i ON i.id_internacao = ng.fk_id_int
              JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
             WHERE " . implode(' AND ', $where) . " {$scope}
             GROUP BY h.id_hospital, h.nome_hosp
             ORDER BY saving DESC
             LIMIT 8
        ";
        return $this->fetchAll($sql, $params);
    }

    private function fetchSavingTopTipos(array $filters, array $ctx): array
    {
        $params = [':days' => (int)$filters['days']];
        $where = $this->savingWhere();
        $this->addHospitalFilter($where, $params, $filters);
        $scope = $this->scopeClause($ctx, 'i', $params, 'savt');
        $sql = "
            SELECT COALESCE(NULLIF(ng.tipo_negociacao, ''), 'Não informado') AS tipo,
                   SUM(COALESCE(ng.saving, 0)) AS saving,
                   COUNT(DISTINCT ng.id_negociacao) AS negociacoes
              FROM tb_negociacao ng
              JOIN tb_internacao i ON i.id_internacao = ng.fk_id_int
             WHERE " . implode(' AND ', $where) . " {$scope}
             GROUP BY tipo
             ORDER BY saving DESC
             LIMIT 8
        ";
        return $this->fetchAll($sql, $params);
    }

    private function fetchGestaoTotals(array $filters, array $ctx): array
    {
        $params = [':days' => (int)$filters['days']];
        $where = $this->gestaoWhere();
        $this->addHospitalFilter($where, $params, $filters);
        $scope = $this->scopeClause($ctx, 'i', $params, 'ges');
        $sql = "
            SELECT COUNT(DISTINCT g.id_gestao) AS registros,
                   SUM(CASE WHEN LOWER(COALESCE(g.evento_adverso_ges, 'n')) = 's' THEN 1 ELSE 0 END) AS eventos_adversos,
                   SUM(CASE WHEN LOWER(COALESCE(g.evento_encerrar_ges, 'n')) <> 's' THEN 1 ELSE 0 END) AS eventos_abertos,
                   SUM(CASE WHEN LOWER(COALESCE(g.alto_custo_ges, 'n')) = 's' THEN 1 ELSE 0 END) AS alto_custo,
                   SUM(CASE WHEN LOWER(COALESCE(g.opme_ges, 'n')) = 's' THEN 1 ELSE 0 END) AS opme,
                   SUM(CASE WHEN LOWER(COALESCE(g.home_care_ges, 'n')) = 's' THEN 1 ELSE 0 END) AS home_care,
                   SUM(CASE WHEN LOWER(COALESCE(g.desospitalizacao_ges, 'n')) = 's' THEN 1 ELSE 0 END) AS desospitalizacao
              FROM tb_gestao g
              JOIN tb_internacao i ON i.id_internacao = g.fk_internacao_ges
             WHERE " . implode(' AND ', $where) . " {$scope}
        ";
        return $this->fetchAssoc($sql, $params);
    }

    private function fetchGestaoTopTipos(array $filters, array $ctx): array
    {
        $params = [':days' => (int)$filters['days']];
        $where = $this->gestaoWhere();
        $where[] = "LOWER(COALESCE(g.evento_adverso_ges, 'n')) = 's'";
        $this->addHospitalFilter($where, $params, $filters);
        $scope = $this->scopeClause($ctx, 'i', $params, 'gest');
        $sql = "
            SELECT COALESCE(NULLIF(g.tipo_evento_adverso_gest, ''), 'Sem informação') AS tipo,
                   COUNT(DISTINCT g.id_gestao) AS eventos
              FROM tb_gestao g
              JOIN tb_internacao i ON i.id_internacao = g.fk_internacao_ges
             WHERE " . implode(' AND ', $where) . " {$scope}
             GROUP BY tipo
             ORDER BY eventos DESC
             LIMIT 8
        ";
        return $this->fetchAll($sql, $params);
    }

    private function fetchVisitaTotals(array $filters, array $ctx): array
    {
        $params = [':days' => (int)$filters['days']];
        $where = $this->visitaWhere();
        $this->addHospitalFilter($where, $params, $filters);
        $scope = $this->scopeClause($ctx, 'i', $params, 'vis');
        $sql = "
            SELECT COUNT(DISTINCT v.id_visita) AS visitas,
                   SUM(CASE WHEN LOWER(COALESCE(v.faturado_vis, 'n')) = 's' THEN 1 ELSE 0 END) AS faturadas,
                   MAX(v.data_visita_vis) AS ultima_visita
              FROM tb_visita v
              JOIN tb_internacao i ON i.id_internacao = v.fk_internacao_vis
             WHERE " . implode(' AND ', $where) . " {$scope}
        ";
        return $this->fetchAssoc($sql, $params);
    }

    private function fetchVisitaTopHospitais(array $filters, array $ctx): array
    {
        $params = [':days' => (int)$filters['days']];
        $where = $this->visitaWhere();
        $this->addHospitalFilter($where, $params, $filters);
        $scope = $this->scopeClause($ctx, 'i', $params, 'vish');
        $sql = "
            SELECT COALESCE(NULLIF(h.nome_hosp, ''), 'Sem hospital') AS hospital,
                   COUNT(DISTINCT v.id_visita) AS visitas
              FROM tb_visita v
              JOIN tb_internacao i ON i.id_internacao = v.fk_internacao_vis
              JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
             WHERE " . implode(' AND ', $where) . " {$scope}
             GROUP BY h.id_hospital, h.nome_hosp
             ORDER BY visitas DESC
             LIMIT 8
        ";
        return $this->fetchAll($sql, $params);
    }

    private function fetchUtiTotals(array $filters, array $ctx): array
    {
        $params = [':days' => (int)$filters['days']];
        $where = [
            "u.data_internacao_uti IS NOT NULL",
            "u.data_internacao_uti >= DATE_SUB(CURDATE(), INTERVAL :days DAY)",
        ];
        $this->addHospitalFilter($where, $params, $filters);
        $scope = $this->scopeClause($ctx, 'i', $params, 'uti');
        $sql = "
            SELECT COUNT(DISTINCT u.id_uti) AS registros_uti,
                   COUNT(DISTINCT i.id_internacao) AS internacoes_com_uti
              FROM tb_uti u
              JOIN tb_internacao i ON i.id_internacao = u.fk_internacao_uti
             WHERE " . implode(' AND ', $where) . " {$scope}
        ";
        return $this->fetchAssoc($sql, $params);
    }

    private function rowForPrompt(array $row): array
    {
        return [
            'id_internacao' => (int)($row['id_internacao'] ?? 0),
            'paciente' => $row['nome_pac'] ?? '',
            'hospital' => $row['nome_hosp'] ?? '',
            'seguradora' => $row['seguradora'] ?? '',
            'data_internacao' => $row['data_intern_int'] ?? '',
            'dias_internado' => (int)($row['dias_internado'] ?? 0),
            'status_internado' => $row['internado_int'] ?? '',
            'acomodacao' => $row['acomodacao_int'] ?? '',
            'grupo_patologia' => $row['grupo_patologia_int'] ?? '',
            'ultima_visita' => $row['ultima_visita'] ?? null,
            'dias_sem_visita' => $row['dias_sem_visita'] !== null ? (int)$row['dias_sem_visita'] : null,
            'resumo_ultima_visita' => $this->truncate((string)($row['ultimo_relatorio'] ?? ''), 450),
            'registros_uti' => (int)($row['qtd_uti'] ?? 0),
            'eventos_adversos' => (int)($row['eventos_adversos'] ?? 0),
            'alto_custo' => (int)($row['alto_custo'] ?? 0),
            'opme' => (int)($row['opme'] ?? 0),
            'home_care' => (int)($row['home_care'] ?? 0),
            'desospitalizacao' => (int)($row['desospitalizacao'] ?? 0),
            'negociacoes' => (int)($row['qtd_negociacoes'] ?? 0),
            'saving_total' => (float)($row['saving_total'] ?? 0),
            'contas_abertas' => (int)($row['contas_abertas'] ?? 0),
            'contas_paradas' => (int)($row['contas_paradas'] ?? 0),
            'valor_apresentado' => (float)($row['valor_apresentado'] ?? 0),
            'valor_final' => (float)($row['valor_final'] ?? 0),
            'valor_glosa' => (float)($row['valor_glosa'] ?? 0),
        ];
    }

    private function buildResults(array $rows): array
    {
        return array_map(function (array $row): array {
            $dias = (int)($row['dias_internado'] ?? 0);
            $diasVisita = $row['dias_sem_visita'] !== null ? (int)$row['dias_sem_visita'] : null;
            $flags = [];
            if ($dias >= 15) {
                $flags[] = 'Longa permanência';
            }
            if ($diasVisita === null || $diasVisita >= 5) {
                $flags[] = 'Sem visita recente';
            }
            if ((int)($row['eventos_adversos'] ?? 0) > 0) {
                $flags[] = 'Evento adverso';
            }
            if ((int)($row['qtd_uti'] ?? 0) > 0) {
                $flags[] = 'UTI';
            }

            $id = (int)($row['id_internacao'] ?? 0);
            return [
                'id' => $id,
                'paciente' => (string)($row['nome_pac'] ?? ''),
                'hospital' => (string)($row['nome_hosp'] ?? ''),
                'seguradora' => (string)($row['seguradora'] ?? ''),
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
                            'text' => 'Você é um assistente de inteligência operacional hospitalar do FullCare. Responda em português-BR. Use exclusivamente os dados fornecidos. Não faça diagnóstico, prescrição, nem decisão médica automática. Quando faltar dado, diga que não há registro suficiente. Cite IDs de internação quando apontar casos.',
                        ],
                    ],
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => "Pergunta do usuário:\n{$question}\n\nDados disponíveis em JSON:\n{$json}\n\nResponda com:\n1. síntese direta;\n2. principais casos ou achados com ID da internação;\n3. próximos passos operacionais sugeridos.\nUse bullets curtos.",
                        ],
                    ],
                ],
            ],
            'temperature' => 0.15,
            'max_output_tokens' => 1200,
        ];

        $raw = $this->requestOpenAi($payload);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Resposta inválida da IA.');
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
        $rows = $summary['internacoes'] ?? [];
        $op = $summary['contexto_operacional'] ?? [];
        $top = array_slice($rows, 0, 6);

        $text = "Ainda não há chave de IA configurada ou cURL disponível, então gerei uma leitura local dos dados.\n\n";
        $text .= "Síntese: foram consultadas " . (int)($ind['total_consultado'] ?? 0) . " internações; "
            . (int)($ind['longa_permanencia_15d_ou_mais'] ?? 0) . " em longa permanência, "
            . (int)($ind['sem_visita_5d_ou_mais'] ?? 0) . " sem visita recente e "
            . (int)($ind['com_evento_adverso'] ?? 0) . " com evento adverso registrado.\n\n";
        if ($op) {
            $fin = $op['financeiro_capeante']['totais'] ?? [];
            $sav = $op['negociacoes_saving']['totais'] ?? [];
            $ges = $op['gestao_eventos']['totais'] ?? [];
            $vis = $op['visitas']['totais'] ?? [];
            $text .= "Contexto operacional do período: "
                . "valor apresentado " . $this->fmtMoney((float)($fin['valor_apresentado'] ?? 0)) . ", "
                . "glosa " . $this->fmtMoney((float)($fin['valor_glosa'] ?? 0)) . ", "
                . "saving " . $this->fmtMoney((float)($sav['saving'] ?? 0)) . ", "
                . (int)($sav['negociacoes'] ?? 0) . " negociação(ões), "
                . (int)($ges['eventos_adversos'] ?? 0) . " evento(s) adverso(s) e "
                . (int)($vis['visitas'] ?? 0) . " visita(s).\n\n";
        }
        $text .= "Casos para olhar primeiro:\n";
        foreach ($top as $row) {
            $text .= "- ID " . (int)($row['id_internacao'] ?? 0) . " — "
                . ($row['paciente'] ?? 'Paciente') . ", "
                . ($row['hospital'] ?? 'hospital') . ", "
                . (int)($row['dias_internado'] ?? 0) . " dia(s) internado(s)";
            if (($row['dias_sem_visita'] ?? null) !== null) {
                $text .= ", " . (int)$row['dias_sem_visita'] . " dia(s) sem visita";
            }
            $text .= ".\n";
        }
        $text .= "\nPróximos passos: revisar evolução/visita recente, pertinência de permanência, pendências de conta e registros de evento adverso antes de acionar auditoria ou negociação.";
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
            throw new RuntimeException('Falha de conexão com o serviço de IA.');
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            $message = $this->extractErrorMessage((string)$raw);
            throw new RuntimeException('Serviço de IA indisponível no momento (HTTP ' . $httpCode . ').' . ($message !== '' ? ' Detalhe: ' . $message : ''));
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

    private function capeanteDateExpr(): string
    {
        return "COALESCE(NULLIF(ca.data_digit_capeante,'0000-00-00'), NULLIF(ca.data_fech_capeante,'0000-00-00'), NULLIF(ca.data_final_capeante,'0000-00-00'), NULLIF(ca.data_inicial_capeante,'0000-00-00'))";
    }

    private function savingWhere(): array
    {
        return [
            "ng.data_inicio_neg IS NOT NULL",
            "ng.data_inicio_neg <> '0000-00-00'",
            "ng.data_inicio_neg >= DATE_SUB(CURDATE(), INTERVAL :days DAY)",
            "ng.saving IS NOT NULL",
            "COALESCE(ng.fk_usuario_neg, 0) > 0",
            "UPPER(COALESCE(ng.tipo_negociacao, '')) <> 'PRORROGACAO_AUTOMATICA'",
            "COALESCE(ng.saving, 0) <> 0",
            "LOWER(COALESCE(ng.deletado_neg, 'n')) <> 's'",
        ];
    }

    private function gestaoWhere(): array
    {
        return [
            "g.data_create_ges IS NOT NULL",
            "g.data_create_ges >= DATE_SUB(CURDATE(), INTERVAL :days DAY)",
            "LOWER(COALESCE(g.deletado_ges, 'n')) <> 's'",
        ];
    }

    private function visitaWhere(): array
    {
        return [
            "v.data_visita_vis IS NOT NULL",
            "v.data_visita_vis <> '0000-00-00'",
            "v.data_visita_vis >= DATE_SUB(CURDATE(), INTERVAL :days DAY)",
        ];
    }

    private function addHospitalFilter(array &$where, array &$params, array $filters): void
    {
        if ((int)($filters['hospital_id'] ?? 0) > 0) {
            $where[] = 'i.fk_hospital_int = :hospital_id';
            $params[':hospital_id'] = (int)$filters['hospital_id'];
        }
    }

    private function fetchAssoc(string $sql, array $params): array
    {
        $stmt = $this->conn->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function fetchAll(string $sql, array $params): array
    {
        $stmt = $this->conn->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function fmtMoney(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
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
