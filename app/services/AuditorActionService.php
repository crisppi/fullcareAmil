<?php

class AuditorActionService
{
    private PDO $conn;
    private string $baseUrl;

    public function __construct(PDO $conn, string $baseUrl)
    {
        $this->conn = $conn;
        $this->baseUrl = rtrim($baseUrl, '/') . '/';
    }

    public static function normalizeRole(?string $value): string
    {
        $txt = mb_strtolower(trim((string)$value), 'UTF-8');
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $txt);
        $txt = $ascii !== false ? $ascii : $txt;
        return (string)preg_replace('/[^a-z]/', '', $txt);
    }

    public static function isAuditorProfile(?string $cargo, $nivel = null): bool
    {
        $role = self::normalizeRole($cargo);
        if ($role === '') {
            return false;
        }

        if (strpos($role, 'seguradora') !== false || strpos($role, 'diretor') !== false || strpos($role, 'admin') !== false) {
            return false;
        }

        return strpos($role, 'auditor') !== false
            || strpos($role, 'auditoria') !== false
            || strpos($role, 'medaudit') !== false
            || strpos($role, 'enfaudit') !== false;
    }

    public static function isAuditorOrDirectorProfile(?string $cargo, $nivel = null): bool
    {
        $role = self::normalizeRole($cargo);
        if (strpos($role, 'seguradora') !== false) {
            return false;
        }

        $isDirector = in_array($role, ['diretoria', 'diretor', 'board'], true)
            || strpos($role, 'diretor') !== false
            || strpos($role, 'diretoria') !== false
            || (int)$nivel === -1;

        return $isDirector || self::isAuditorProfile($cargo, $nivel);
    }

    public static function canUseOperationalSearch(array $session): bool
    {
        $cargo = self::normalizeRole($session['cargo'] ?? '');
        $usuario = self::normalizeRole($session['usuario_user'] ?? '');
        $nivel = (int)($session['nivel'] ?? 0);

        if (strpos($cargo, 'seguradora') !== false || strpos($usuario, 'seguradora') !== false) {
            return false;
        }

        $isDirector = in_array($cargo, ['diretoria', 'diretor', 'board'], true)
            || in_array($usuario, ['diretoria', 'diretor', 'board'], true)
            || strpos($cargo, 'diretor') !== false
            || strpos($cargo, 'diretoria') !== false
            || strpos($usuario, 'diretor') !== false
            || strpos($usuario, 'diretoria') !== false
            || $nivel === -1;

        return $isDirector
            || $nivel >= 4
            || self::isAuditorProfile((string)($session['cargo'] ?? ''), $nivel);
    }

    public function dashboardSummary(array $session, int $limit = 10): array
    {
        $counts = [
            'fila_total' => 0,
            'visitas_atrasadas' => 0,
            'eventos_abertos' => 0,
            'contas_pendentes' => 0,
            'negociacoes_pendentes' => 0,
            'longa_permanencia' => 0,
        ];

        foreach ([
            'visitas_atrasadas' => $this->sqlCountVisitasAtrasadas($session),
            'eventos_abertos' => $this->sqlCountEventosAbertos($session),
            'contas_pendentes' => $this->sqlCountContasPendentes($session),
            'negociacoes_pendentes' => $this->sqlCountNegociacoesPendentes($session),
            'longa_permanencia' => $this->sqlCountLongaPermanencia($session),
        ] as $key => $query) {
            $counts[$key] = $this->fetchCount($query['sql'], $query['params']);
        }

        $counts['fila_total'] = array_sum($counts) - (int)$counts['fila_total'];
        $queue = $this->actionQueue($session, $limit);
        $alerts = $this->buildAlerts($counts);

        return [
            'counts' => $counts,
            'queue' => $queue,
            'alerts' => $alerts,
        ];
    }

    public function patientSnapshot(int $patientId, array $session): array
    {
        $counts = [
            'visitas_atrasadas' => 0,
            'eventos_abertos' => 0,
            'contas_pendentes' => 0,
            'negociacoes_pendentes' => 0,
            'internacoes_ativas' => 0,
        ];

        foreach ([
            'visitas_atrasadas' => $this->sqlCountVisitasAtrasadas($session, $patientId),
            'eventos_abertos' => $this->sqlCountEventosAbertos($session, $patientId),
            'contas_pendentes' => $this->sqlCountContasPendentes($session, $patientId),
            'negociacoes_pendentes' => $this->sqlCountNegociacoesPendentes($session, $patientId),
            'internacoes_ativas' => $this->sqlCountInternacoesAtivas($session, $patientId),
        ] as $key => $query) {
            $counts[$key] = $this->fetchCount($query['sql'], $query['params']);
        }

        return [
            'counts' => $counts,
            'pending' => $this->patientPendingItems($patientId, $session),
            'timeline' => $this->patientTimeline($patientId, $session),
        ];
    }

    public function globalSearch(string $query, array $session, int $limit = 10, ?string $type = null): array
    {
        $q = trim($query);
        if (mb_strlen($q) < 2) {
            return [];
        }

        $type = strtolower(trim((string)($type ?? 'paciente')));
        if (!in_array($type, ['paciente', 'internacao', 'conta'], true)) {
            $type = 'paciente';
        }

        $items = [];
        if ($type === 'paciente') {
            $items = $this->searchPatients($q, $session, $limit);
        } elseif ($type === 'internacao') {
            $items = $this->searchAdmissions($q, $session, $limit);
        } else {
            $items = $this->searchCapeantes($q, $session, $limit);
        }

        usort($items, static function (array $a, array $b): int {
            return ($b['rank'] ?? 0) <=> ($a['rank'] ?? 0);
        });

        return array_slice($items, 0, max(1, $limit));
    }

    private function actionQueue(array $session, int $limit): array
    {
        $items = [];
        foreach ([
            'visita' => $this->rowsVisitasAtrasadas($session),
            'evento' => $this->rowsEventosAbertos($session),
            'conta' => $this->rowsContasPendentes($session),
            'negociacao' => $this->rowsNegociacoesPendentes($session),
            'permanencia' => $this->rowsLongaPermanencia($session),
        ] as $type => $rows) {
            foreach ($rows as $row) {
                $items[] = $this->formatQueueRow($type, $row);
            }
        }

        usort($items, static function (array $a, array $b): int {
            $cmp = ($b['priority_score'] ?? 0) <=> ($a['priority_score'] ?? 0);
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp((string)($a['paciente'] ?? ''), (string)($b['paciente'] ?? ''));
        });

        return array_slice($items, 0, max(1, $limit));
    }

    private function patientPendingItems(int $patientId, array $session): array
    {
        $items = [];
        foreach ([
            'visita' => $this->rowsVisitasAtrasadas($session, $patientId),
            'evento' => $this->rowsEventosAbertos($session, $patientId),
            'conta' => $this->rowsContasPendentes($session, $patientId),
            'negociacao' => $this->rowsNegociacoesPendentes($session, $patientId),
            'permanencia' => $this->rowsLongaPermanencia($session, $patientId),
        ] as $type => $rows) {
            foreach ($rows as $row) {
                $items[] = $this->formatQueueRow($type, $row);
            }
        }

        usort($items, static function (array $a, array $b): int {
            return ($b['priority_score'] ?? 0) <=> ($a['priority_score'] ?? 0);
        });
        return array_slice($items, 0, 6);
    }

    private function patientTimeline(int $patientId, array $session): array
    {
        $scopeInt = $this->scopeClause('i', $session, 'tli');
        $scopeVis = $this->scopeClause('i', $session, 'tlv');
        $scopeGes = $this->scopeClause('i', $session, 'tlg');
        $scopeCap = $this->scopeClause('i', $session, 'tlc');
        $params = array_merge(
            [
                ':pac_i' => $patientId,
                ':pac_v' => $patientId,
                ':pac_g' => $patientId,
                ':pac_c' => $patientId,
            ],
            $scopeInt['params'],
            $scopeVis['params'],
            $scopeGes['params'],
            $scopeCap['params']
        );
        $sql = "
            SELECT tipo, ref_id, data_ref, titulo, detalhe
              FROM (
                    SELECT 'Internação' AS tipo,
                           i.id_internacao AS ref_id,
                           i.data_intern_int AS data_ref,
                           CONCAT('Internação #', i.id_internacao) AS titulo,
                           CONCAT(COALESCE(h.nome_hosp, 'Hospital não informado'), ' · senha ', COALESCE(NULLIF(i.senha_int, ''), '—')) AS detalhe
                      FROM tb_internacao i
                      LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
                     WHERE i.fk_paciente_int = :pac_i {$scopeInt['sql']}
                    UNION ALL
                    SELECT 'Visita' AS tipo,
                           v.id_visita AS ref_id,
                           v.data_visita_vis AS data_ref,
                           CONCAT('Visita #', v.id_visita) AS titulo,
                           COALESCE(NULLIF(v.visita_no_vis, ''), 'Registro de visita') AS detalhe
                      FROM tb_visita v
                      JOIN tb_internacao i ON i.id_internacao = v.fk_internacao_vis
                     WHERE i.fk_paciente_int = :pac_v {$scopeVis['sql']}
                    UNION ALL
                    SELECT 'Evento' AS tipo,
                           g.id_gestao AS ref_id,
                           g.evento_data_ges AS data_ref,
                           CONCAT('Evento #', g.id_gestao) AS titulo,
                           COALESCE(NULLIF(g.tipo_evento_adverso_gest, ''), 'Evento adverso') AS detalhe
                      FROM tb_gestao g
                      JOIN tb_internacao i ON i.id_internacao = g.fk_internacao_ges
                     WHERE i.fk_paciente_int = :pac_g {$scopeGes['sql']}
                       AND COALESCE(g.evento_adverso_ges, 'n') = 's'
                    UNION ALL
                    SELECT 'Conta' AS tipo,
                           c.id_capeante AS ref_id,
                           COALESCE(c.data_final_capeante, c.data_inicial_capeante, c.data_create_cap) AS data_ref,
                           CONCAT('Capeante #', c.id_capeante) AS titulo,
                           CONCAT('Valor apresentado R$ ', FORMAT(COALESCE(c.valor_apresentado_capeante, 0), 2)) AS detalhe
                      FROM tb_capeante c
                      JOIN tb_internacao i ON i.id_internacao = c.fk_int_capeante
                     WHERE i.fk_paciente_int = :pac_c {$scopeCap['sql']}
                   ) base
             ORDER BY COALESCE(data_ref, '1900-01-01') DESC
             LIMIT 10
        ";

        try {
            $stmt = $this->conn->prepare($sql);
            $this->bind($stmt, $params);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as &$row) {
                $refId = (int)($row['ref_id'] ?? 0);
                if (($row['tipo'] ?? '') === 'Internação') {
                    $row['url'] = $this->baseUrl . 'internacoes/visualizar/' . $refId;
                } elseif (($row['tipo'] ?? '') === 'Visita') {
                    $row['url'] = $this->baseUrl . 'visitas/ver/' . $refId;
                } elseif (($row['tipo'] ?? '') === 'Evento') {
                    $row['url'] = $this->baseUrl . 'show_gestao.php?id_gestao=' . $refId;
                } else {
                    $row['url'] = $this->baseUrl . 'contas/auditar/' . $refId;
                }
            }
            unset($row);
            return $rows;
        } catch (Throwable $e) {
            error_log('[AUDITOR_ACTION][TIMELINE] ' . $e->getMessage());
            return [];
        }
    }

    private function searchPatients(string $q, array $session, int $limit): array
    {
        $scope = $this->scopeClauseForPatient('p', $session, 'sp');
        $sql = "
            SELECT p.id_paciente, p.nome_pac, p.matricula_pac, p.data_nasc_pac,
                   (
                       SELECT i2.senha_int
                         FROM tb_internacao i2
                        WHERE i2.fk_paciente_int = p.id_paciente
                        ORDER BY i2.data_intern_int DESC, i2.id_internacao DESC
                        LIMIT 1
                   ) AS ultima_senha
              FROM tb_paciente p
             WHERE IFNULL(p.deletado_pac, 'n') <> 's'
               {$scope['sql']}
               AND (
                    p.nome_pac LIKE :q_nome
                    OR p.matricula_pac LIKE :q_matricula
               )
             ORDER BY p.nome_pac ASC
             LIMIT {$limit}
        ";
        $like = '%' . $q . '%';
        $params = array_merge([
            ':q_nome' => $like,
            ':q_matricula' => $like,
        ], $scope['params']);
        $rows = $this->fetchRows($sql, $params);
        return array_map(function (array $row): array {
            $nasc = $this->formatDate((string)($row['data_nasc_pac'] ?? ''));
            $meta = array_filter([
                !empty($row['ultima_senha']) ? 'Senha: ' . $row['ultima_senha'] : null,
                !empty($row['matricula_pac']) ? 'Matrícula: ' . $row['matricula_pac'] : null,
                $nasc ? 'Nasc.: ' . $nasc : null,
            ]);
            return [
                'type' => 'paciente',
                'rank' => 100,
                'id_paciente' => (int)$row['id_paciente'],
                'nome' => (string)$row['nome_pac'],
                'title' => (string)$row['nome_pac'],
                'subtitle' => implode(' · ', $meta),
                'url' => $this->baseUrl . 'pacientes/hub/' . (int)$row['id_paciente'],
                'matricula' => (string)($row['matricula_pac'] ?? ''),
                'nascimento_fmt' => $nasc,
                'senha' => (string)($row['ultima_senha'] ?? ''),
                'icon' => 'bi-person-vcard',
            ];
        }, $rows);
    }

    private function searchAdmissions(string $q, array $session, int $limit): array
    {
        $scope = $this->scopeClause('i', $session, 'si');
        $sql = "
            SELECT i.id_internacao, i.senha_int, i.data_intern_int, i.internado_int,
                   p.id_paciente, p.nome_pac, h.nome_hosp
              FROM tb_internacao i
              JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
              LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
             WHERE IFNULL(p.deletado_pac, 'n') <> 's'
               {$scope['sql']}
               AND (
                    i.id_internacao = :idq
                    OR p.nome_pac LIKE :q_nome
               )
             ORDER BY i.data_intern_int DESC, i.id_internacao DESC
             LIMIT {$limit}
        ";
        $like = '%' . $q . '%';
        $params = array_merge([
            ':q_nome' => $like,
            ':idq' => ctype_digit($q) ? (int)$q : 0,
        ], $scope['params']);
        $rows = $this->fetchRows($sql, $params);
        return array_map(function (array $row): array {
            $status = strtolower((string)($row['internado_int'] ?? '')) === 's' ? 'Internado' : 'Encerrado';
            return [
                'type' => 'internacao',
                'rank' => 90,
                'id_paciente' => (int)$row['id_paciente'],
                'id_internacao' => (int)$row['id_internacao'],
                'nome' => (string)$row['nome_pac'],
                'title' => 'Internação #' . (int)$row['id_internacao'] . ' · ' . (string)$row['nome_pac'],
                'subtitle' => trim($status . ' · ' . ($row['nome_hosp'] ?? '') . ' · senha ' . (($row['senha_int'] ?? '') ?: '—'), ' ·'),
                'url' => $this->baseUrl . 'internacoes/visualizar/' . (int)$row['id_internacao'],
                'senha' => (string)($row['senha_int'] ?? ''),
                'icon' => 'bi-hospital',
            ];
        }, $rows);
    }

    private function searchCapeantes(string $q, array $session, int $limit): array
    {
        $scope = $this->scopeClause('i', $session, 'sc');
        $sql = "
            SELECT c.id_capeante, c.encerrado_cap, c.valor_apresentado_capeante,
                   i.id_internacao, i.senha_int, p.id_paciente, p.nome_pac
              FROM tb_capeante c
              JOIN tb_internacao i ON i.id_internacao = c.fk_int_capeante
              JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
             WHERE IFNULL(p.deletado_pac, 'n') <> 's'
               {$scope['sql']}
               AND (
                    c.id_capeante = :idq
                    OR p.nome_pac LIKE :q_nome
               )
             ORDER BY c.id_capeante DESC
             LIMIT {$limit}
        ";
        $like = '%' . $q . '%';
        $params = array_merge([
            ':q_nome' => $like,
            ':idq' => ctype_digit($q) ? (int)$q : 0,
        ], $scope['params']);
        $rows = $this->fetchRows($sql, $params);
        return array_map(function (array $row): array {
            $status = strtolower((string)($row['encerrado_cap'] ?? 'n')) === 's' ? 'Encerrado' : 'Pendente';
            return [
                'type' => 'conta',
                'rank' => 80,
                'id_paciente' => (int)$row['id_paciente'],
                'id_internacao' => (int)$row['id_internacao'],
                'id_capeante' => (int)$row['id_capeante'],
                'nome' => (string)$row['nome_pac'],
                'title' => 'Capeante #' . (int)$row['id_capeante'] . ' · ' . (string)$row['nome_pac'],
                'subtitle' => $status . ' · internação #' . (int)$row['id_internacao'] . ' · senha ' . (($row['senha_int'] ?? '') ?: '—'),
                'url' => $this->baseUrl . 'contas/auditar/' . (int)$row['id_capeante'],
                'senha' => (string)($row['senha_int'] ?? ''),
                'icon' => 'bi-receipt',
            ];
        }, $rows);
    }

    private function buildAlerts(array $counts): array
    {
        $alerts = [];
        if (($counts['eventos_abertos'] ?? 0) > 0) {
            $alerts[] = ['level' => 'danger', 'icon' => 'bi-exclamation-octagon', 'text' => $counts['eventos_abertos'] . ' evento(s) crítico(s) aberto(s).'];
        }
        if (($counts['visitas_atrasadas'] ?? 0) > 0) {
            $alerts[] = ['level' => 'warning', 'icon' => 'bi-calendar-x', 'text' => $counts['visitas_atrasadas'] . ' visita(s) atrasada(s).'];
        }
        if (($counts['longa_permanencia'] ?? 0) > 0) {
            $alerts[] = ['level' => 'info', 'icon' => 'bi-hourglass-split', 'text' => $counts['longa_permanencia'] . ' internação(ões) com longa permanência.'];
        }
        return $alerts;
    }

    private function formatQueueRow(string $type, array $row): array
    {
        $idInternacao = (int)($row['id_internacao'] ?? 0);
        $idPaciente = (int)($row['id_paciente'] ?? 0);
        $base = [
            'type' => $type,
            'id_internacao' => $idInternacao,
            'id_paciente' => $idPaciente,
            'paciente' => (string)($row['nome_pac'] ?? 'Paciente não informado'),
            'hospital' => (string)($row['nome_hosp'] ?? 'Hospital não informado'),
            'senha' => (string)($row['senha_int'] ?? ''),
            'dias' => (int)($row['dias'] ?? 0),
            'paciente_url' => $this->baseUrl . 'pacientes/hub/' . $idPaciente,
            'internacao_url' => $this->baseUrl . 'internacoes/visualizar/' . $idInternacao,
        ];

        if ($type === 'visita') {
            return $base + [
                'label' => 'Visita atrasada',
                'detail' => 'Registrar ou revisar visita vencida',
                'action_label' => 'Lançar visita',
                'action_url' => $this->baseUrl . 'visitas/nova/internacao/' . $idInternacao,
                'severity' => 'warning',
                'icon' => 'bi-calendar-x',
                'priority_score' => 90 + (int)($row['dias'] ?? 0),
            ];
        }
        if ($type === 'evento') {
            return $base + [
                'label' => 'Evento crítico aberto',
                'detail' => (string)($row['evento_tipo'] ?? 'Evento adverso sem encerramento'),
                'action_label' => 'Abrir internação',
                'action_url' => $this->baseUrl . 'internacoes/visualizar/' . $idInternacao,
                'severity' => 'danger',
                'icon' => 'bi-exclamation-octagon',
                'priority_score' => 120 + (int)($row['dias'] ?? 0),
            ];
        }
        if ($type === 'conta') {
            $idCapeante = (int)($row['id_capeante'] ?? 0);
            return $base + [
                'label' => 'Conta pendente',
                'detail' => 'Capeante #' . $idCapeante . ' em auditoria',
                'action_label' => 'Auditar conta',
                'action_url' => $this->baseUrl . 'contas/auditar/' . $idCapeante,
                'severity' => 'primary',
                'icon' => 'bi-receipt-cutoff',
                'priority_score' => 70 + (int)($row['dias'] ?? 0),
            ];
        }
        if ($type === 'negociacao') {
            return $base + [
                'label' => 'Negociação pendente',
                'detail' => (string)($row['tipo_negociacao'] ?? 'Negociação sem conclusão'),
                'action_label' => 'Ver negociação',
                'action_url' => $this->baseUrl . 'negociacoes',
                'severity' => 'info',
                'icon' => 'bi-arrow-repeat',
                'priority_score' => 65 + (int)($row['dias'] ?? 0),
            ];
        }

        return $base + [
            'label' => 'Longa permanência',
            'detail' => 'Paciente internado há ' . (int)($row['dias'] ?? 0) . ' dia(s)',
            'action_label' => 'Abrir hub',
            'action_url' => $this->baseUrl . 'pacientes/hub/' . $idPaciente,
            'severity' => 'secondary',
            'icon' => 'bi-hourglass-split',
            'priority_score' => 55 + (int)($row['dias'] ?? 0),
        ];
    }

    private function rowsVisitasAtrasadas(array $session, ?int $patientId = null): array
    {
        $scope = $this->scopeClause('i', $session, 'rv');
        $params = $scope['params'];
        $patientSql = '';
        if ($patientId) {
            $patientSql = ' AND p.id_paciente = :pac_rv ';
            $params[':pac_rv'] = $patientId;
        }
        return $this->fetchRows("
            SELECT i.id_internacao, i.senha_int, p.id_paciente, p.nome_pac, h.nome_hosp,
                   v.id_visita,
                   GREATEST(DATEDIFF(CURDATE(), DATE(IFNULL(v.data_visita_vis, v.data_lancamento_vis))), 0) AS dias
              FROM tb_visita v
              JOIN tb_internacao i ON i.id_internacao = v.fk_internacao_vis
              JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
              LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
             WHERE i.internado_int = 's'
               AND DATE(IFNULL(v.data_visita_vis, v.data_lancamento_vis)) < CURDATE()
               AND (v.data_lancamento_vis IS NULL OR v.data_lancamento_vis = '0000-00-00 00:00:00')
               {$scope['sql']}
               {$patientSql}
             ORDER BY dias DESC, v.id_visita DESC
             LIMIT 8
        ", $params);
    }

    private function rowsEventosAbertos(array $session, ?int $patientId = null): array
    {
        $scope = $this->scopeClause('i', $session, 're');
        $params = $scope['params'];
        $patientSql = '';
        if ($patientId) {
            $patientSql = ' AND p.id_paciente = :pac_re ';
            $params[':pac_re'] = $patientId;
        }
        return $this->fetchRows("
            SELECT i.id_internacao, i.senha_int, p.id_paciente, p.nome_pac, h.nome_hosp,
                   g.id_gestao, COALESCE(NULLIF(g.tipo_evento_adverso_gest, ''), 'Evento adverso') AS evento_tipo,
                   GREATEST(DATEDIFF(CURDATE(), DATE(IFNULL(g.evento_data_ges, i.data_intern_int))), 0) AS dias
              FROM tb_gestao g
              JOIN tb_internacao i ON i.id_internacao = g.fk_internacao_ges
              JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
              LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
             WHERE COALESCE(g.evento_adverso_ges, 'n') = 's'
               AND COALESCE(g.evento_encerrar_ges, 'n') <> 's'
               {$scope['sql']}
               {$patientSql}
             ORDER BY dias DESC, g.id_gestao DESC
             LIMIT 8
        ", $params);
    }

    private function rowsContasPendentes(array $session, ?int $patientId = null): array
    {
        $scope = $this->scopeClause('i', $session, 'rc');
        $params = $scope['params'];
        $patientSql = '';
        if ($patientId) {
            $patientSql = ' AND p.id_paciente = :pac_rc ';
            $params[':pac_rc'] = $patientId;
        }
        return $this->fetchRows("
            SELECT i.id_internacao, i.senha_int, p.id_paciente, p.nome_pac, h.nome_hosp,
                   c.id_capeante,
                   GREATEST(DATEDIFF(CURDATE(), DATE(IFNULL(c.data_create_cap, c.data_inicial_capeante))), 0) AS dias
              FROM tb_capeante c
              JOIN tb_internacao i ON i.id_internacao = c.fk_int_capeante
              JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
             LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
             WHERE COALESCE(c.encerrado_cap, 'n') <> 's'
               {$scope['sql']}
               {$patientSql}
             ORDER BY dias DESC, c.id_capeante DESC
             LIMIT 8
        ", $params);
    }

    private function rowsNegociacoesPendentes(array $session, ?int $patientId = null): array
    {
        $scope = $this->scopeClause('i', $session, 'rn');
        $params = $scope['params'];
        $patientSql = '';
        if ($patientId) {
            $patientSql = ' AND p.id_paciente = :pac_rn ';
            $params[':pac_rn'] = $patientId;
        }
        return $this->fetchRows("
            SELECT i.id_internacao, i.senha_int, p.id_paciente, p.nome_pac, h.nome_hosp,
                   n.id_negociacao, n.tipo_negociacao,
                   GREATEST(DATEDIFF(CURDATE(), DATE(n.data_inicio_neg)), 0) AS dias
              FROM tb_negociacao n
              JOIN tb_internacao i ON i.id_internacao = n.fk_id_int
              JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
              LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
             WHERE (n.data_fim_neg IS NULL OR n.data_fim_neg = '0000-00-00')
               AND UPPER(COALESCE(n.tipo_negociacao, '')) <> 'PRORROGACAO_AUTOMATICA'
               {$scope['sql']}
               {$patientSql}
             ORDER BY dias DESC, n.id_negociacao DESC
             LIMIT 8
        ", $params);
    }

    private function rowsLongaPermanencia(array $session, ?int $patientId = null): array
    {
        $scope = $this->scopeClause('i', $session, 'rl');
        $params = $scope['params'];
        $patientSql = '';
        if ($patientId) {
            $patientSql = ' AND p.id_paciente = :pac_rl ';
            $params[':pac_rl'] = $patientId;
        }
        return $this->fetchRows("
            SELECT i.id_internacao, i.senha_int, p.id_paciente, p.nome_pac, h.nome_hosp,
                   GREATEST(DATEDIFF(CURDATE(), DATE(i.data_intern_int)), 0) AS dias
              FROM tb_internacao i
              JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
              LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
             WHERE i.internado_int = 's'
               AND DATEDIFF(CURDATE(), DATE(i.data_intern_int)) >= 15
               {$scope['sql']}
               {$patientSql}
             ORDER BY dias DESC, i.id_internacao DESC
             LIMIT 8
        ", $params);
    }

    private function sqlCountVisitasAtrasadas(array $session, ?int $patientId = null): array
    {
        $scope = $this->scopeClause('i', $session, 'cv');
        $params = $scope['params'];
        $patientSql = '';
        if ($patientId) {
            $patientSql = ' AND p.id_paciente = :pac_cv ';
            $params[':pac_cv'] = $patientId;
        }
        return [
            'sql' => "
                SELECT COUNT(*)
                  FROM tb_visita v
                  JOIN tb_internacao i ON i.id_internacao = v.fk_internacao_vis
                  JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
                 WHERE i.internado_int = 's'
                   AND DATE(IFNULL(v.data_visita_vis, v.data_lancamento_vis)) < CURDATE()
                   AND (v.data_lancamento_vis IS NULL OR v.data_lancamento_vis = '0000-00-00 00:00:00')
                   {$scope['sql']} {$patientSql}
            ",
            'params' => $params,
        ];
    }

    private function sqlCountEventosAbertos(array $session, ?int $patientId = null): array
    {
        $scope = $this->scopeClause('i', $session, 'ce');
        $params = $scope['params'];
        $patientSql = '';
        if ($patientId) {
            $patientSql = ' AND p.id_paciente = :pac_ce ';
            $params[':pac_ce'] = $patientId;
        }
        return [
            'sql' => "
                SELECT COUNT(*)
                  FROM tb_gestao g
                  JOIN tb_internacao i ON i.id_internacao = g.fk_internacao_ges
                  JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
                 WHERE COALESCE(g.evento_adverso_ges, 'n') = 's'
                   AND COALESCE(g.evento_encerrar_ges, 'n') <> 's'
                   {$scope['sql']} {$patientSql}
            ",
            'params' => $params,
        ];
    }

    private function sqlCountContasPendentes(array $session, ?int $patientId = null): array
    {
        $scope = $this->scopeClause('i', $session, 'cc');
        $params = $scope['params'];
        $patientSql = '';
        if ($patientId) {
            $patientSql = ' AND p.id_paciente = :pac_cc ';
            $params[':pac_cc'] = $patientId;
        }
        return [
            'sql' => "
                SELECT COUNT(*)
                  FROM tb_capeante c
                  JOIN tb_internacao i ON i.id_internacao = c.fk_int_capeante
                  JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
                 WHERE COALESCE(c.encerrado_cap, 'n') <> 's'
                   {$scope['sql']} {$patientSql}
            ",
            'params' => $params,
        ];
    }

    private function sqlCountNegociacoesPendentes(array $session, ?int $patientId = null): array
    {
        $scope = $this->scopeClause('i', $session, 'cn');
        $params = $scope['params'];
        $patientSql = '';
        if ($patientId) {
            $patientSql = ' AND p.id_paciente = :pac_cn ';
            $params[':pac_cn'] = $patientId;
        }
        return [
            'sql' => "
                SELECT COUNT(*)
                  FROM tb_negociacao n
                  JOIN tb_internacao i ON i.id_internacao = n.fk_id_int
                  JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
                 WHERE (n.data_fim_neg IS NULL OR n.data_fim_neg = '0000-00-00')
                   AND UPPER(COALESCE(n.tipo_negociacao, '')) <> 'PRORROGACAO_AUTOMATICA'
                   {$scope['sql']} {$patientSql}
            ",
            'params' => $params,
        ];
    }

    private function sqlCountLongaPermanencia(array $session): array
    {
        $scope = $this->scopeClause('i', $session, 'cl');
        return [
            'sql' => "
                SELECT COUNT(*)
                  FROM tb_internacao i
                 WHERE i.internado_int = 's'
                   AND DATEDIFF(CURDATE(), DATE(i.data_intern_int)) >= 15
                   {$scope['sql']}
            ",
            'params' => $scope['params'],
        ];
    }

    private function sqlCountInternacoesAtivas(array $session, int $patientId): array
    {
        $scope = $this->scopeClause('i', $session, 'ci');
        return [
            'sql' => "
                SELECT COUNT(*)
                  FROM tb_internacao i
                 WHERE i.internado_int = 's'
                   AND i.fk_paciente_int = :pac_ci
                   {$scope['sql']}
            ",
            'params' => array_merge([':pac_ci' => $patientId], $scope['params']),
        ];
    }

    private function scopeClause(string $alias, array $session, string $prefix): array
    {
        $userId = (int)($session['id_usuario'] ?? 0);
        if ($userId <= 0) {
            return ['sql' => ' AND 1=0 ', 'params' => []];
        }

        $role = self::normalizeRole($session['cargo'] ?? '');
        if (strpos($role, 'seguradora') !== false) {
            return ['sql' => ' AND 1=0 ', 'params' => []];
        }

        $usuario = self::normalizeRole($session['usuario_user'] ?? '');
        if (in_array($role, ['diretoria', 'diretor', 'board'], true)
            || in_array($usuario, ['diretoria', 'diretor', 'board'], true)
            || strpos($role, 'diretor') !== false
            || strpos($role, 'diretoria') !== false
            || strpos($usuario, 'diretor') !== false
            || strpos($usuario, 'diretoria') !== false
            || (int)($session['nivel'] ?? 0) === -1) {
            return ['sql' => '', 'params' => []];
        }

        $param = ':' . $prefix . '_uid';
        return [
            'sql' => " AND EXISTS (
                SELECT 1
                  FROM tb_hospitalUser hu_scope
                 WHERE hu_scope.fk_hospital_user = {$alias}.fk_hospital_int
                   AND hu_scope.fk_usuario_hosp = {$param}
            ) ",
            'params' => [$param => $userId],
        ];
    }

    private function scopeClauseForPatient(string $alias, array $session, string $prefix): array
    {
        $userId = (int)($session['id_usuario'] ?? 0);
        if ($userId <= 0) {
            return ['sql' => ' AND 1=0 ', 'params' => []];
        }

        $role = self::normalizeRole($session['cargo'] ?? '');
        if (strpos($role, 'seguradora') !== false) {
            return ['sql' => ' AND 1=0 ', 'params' => []];
        }

        $usuario = self::normalizeRole($session['usuario_user'] ?? '');
        if (in_array($role, ['diretoria', 'diretor', 'board'], true)
            || in_array($usuario, ['diretoria', 'diretor', 'board'], true)
            || strpos($role, 'diretor') !== false
            || strpos($role, 'diretoria') !== false
            || strpos($usuario, 'diretor') !== false
            || strpos($usuario, 'diretoria') !== false
            || (int)($session['nivel'] ?? 0) === -1) {
            return ['sql' => '', 'params' => []];
        }

        $param = ':' . $prefix . '_uid';
        return [
            'sql' => " AND EXISTS (
                SELECT 1
                  FROM tb_internacao i_scope
                  JOIN tb_hospitalUser hu_scope ON hu_scope.fk_hospital_user = i_scope.fk_hospital_int
                 WHERE i_scope.fk_paciente_int = {$alias}.id_paciente
                   AND hu_scope.fk_usuario_hosp = {$param}
            ) ",
            'params' => [$param => $userId],
        ];
    }

    private function fetchRows(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->conn->prepare($sql);
            $this->bind($stmt, $params);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('[AUDITOR_ACTION][ROWS] ' . $e->getMessage());
            return [];
        }
    }

    private function fetchCount(string $sql, array $params = []): int
    {
        try {
            $stmt = $this->conn->prepare($sql);
            $this->bind($stmt, $params);
            $stmt->execute();
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('[AUDITOR_ACTION][COUNT] ' . $e->getMessage());
            return 0;
        }
    }

    private function bind(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            if (is_int($value)) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, (string)$value, PDO::PARAM_STR);
            }
        }
    }

    private function formatDate(string $date): string
    {
        if ($date === '' || $date === '0000-00-00') {
            return '';
        }
        try {
            return (new DateTime($date))->format('d/m/Y');
        } catch (Throwable $e) {
            return '';
        }
    }
}
