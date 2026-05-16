<?php

require_once(__DIR__ . '/../utils/home_care_schema.php');

class HomeCareDAO
{
    private PDO $conn;
    private string $baseUrl;

    public function __construct(PDO $conn, string $baseUrl)
    {
        $this->conn = $conn;
        $this->baseUrl = $baseUrl;
        fullcareEnsureHomeCareSchema($this->conn);
    }

    private function bindAll(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            if (is_int($value)) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } elseif ($value === null) {
                $stmt->bindValue($key, null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue($key, (string)$value, PDO::PARAM_STR);
            }
        }
    }

    private function toNullableText($value, int $max = 0): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        if ($max > 0) {
            $value = mb_substr($value, 0, $max);
        }
        return $value;
    }

    private function toNullableDate($value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        $ts = strtotime($value);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    private function toMoney($value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        $normalized = str_replace(['.', ','], ['', '.'], $value);
        return is_numeric($normalized) ? number_format((float)$normalized, 2, '.', '') : null;
    }

    private function yesNo($value): string
    {
        return ((string)$value === 's') ? 's' : 'n';
    }

    private function scorePoint($value): int
    {
        return in_array((int)$value, [0, 1, 2], true) ? (int)$value : 0;
    }

    public function getStatusOptions(): array
    {
        return [
            'em_avaliacao' => 'Em avaliação',
            'elegivel' => 'Elegível',
            'implantacao' => 'Implantação em curso',
            'aguardando_familia' => 'Aguardando família',
            'aguardando_hospital' => 'Aguardando hospital',
            'aguardando_operadora' => 'Aguardando operadora',
            'implantado' => 'Implantado',
            'negado' => 'Negado',
            'descontinuado' => 'Descontinuado',
        ];
    }

    public function getModalidadeOptions(): array
    {
        return [
            'procedimento_pontual' => 'Procedimento pontual',
            'atendimento_multiprofissional' => 'Atendimento multiprofissional',
            'internacao_domiciliar_6h' => 'Internação domiciliar 6h',
            'internacao_domiciliar_12h' => 'Internação domiciliar 12h',
            'internacao_domiciliar_24h' => 'Internação domiciliar 24h',
        ];
    }

    public function getBarreiraOptions(): array
    {
        return [
            'familia' => 'Família / cuidador',
            'ambiente' => 'Estrutura do domicílio',
            'fornecedor' => 'Fornecedor / rede',
            'hospital' => 'Hospital de origem',
            'operadora' => 'Operadora / autorização',
            'equipamentos' => 'Equipamentos / insumos',
            'clinica' => 'Critério clínico',
            'outros' => 'Outros',
        ];
    }

    public function calculateNead(array $data): array
    {
        $g1 = [
            $this->yesNo($data['nead_grupo1_cuidador_hc'] ?? 'n'),
            $this->yesNo($data['nead_grupo1_ambiente_hc'] ?? 'n'),
            $this->yesNo($data['nead_grupo1_locomocao_hc'] ?? 'n'),
        ];
        $elegivel = !in_array('n', $g1, true);

        $g2 = [
            $this->yesNo($data['nead_grupo2_vm_hc'] ?? 'n'),
            $this->yesNo($data['nead_grupo2_aspiracao_hc'] ?? 'n'),
            $this->yesNo($data['nead_grupo2_medicacao_ev_hc'] ?? 'n'),
            $this->yesNo($data['nead_grupo2_dieta_parenteral_hc'] ?? 'n'),
            $this->yesNo($data['nead_grupo2_lesao_complexa_hc'] ?? 'n'),
        ];
        $indicacaoImediata = in_array('s', $g2, true);

        $score = 0;
        $score += $this->scorePoint($data['nead_grupo3_katz_hc'] ?? 0);
        $score += $this->scorePoint($data['nead_grupo3_enteral_hc'] ?? 0);
        $score += $this->scorePoint($data['nead_grupo3_oxigenio_hc'] ?? 0);
        $score += $this->scorePoint($data['nead_grupo3_traqueostomia_hc'] ?? 0);
        $score += $this->scorePoint($data['nead_grupo3_dialise_hc'] ?? 0);

        $classificacao = 'Nao elegivel';
        $modalidade = null;
        if ($elegivel) {
            if ($indicacaoImediata) {
                $classificacao = 'Indicacao imediata para internacao domiciliar';
                $modalidade = 'internacao_domiciliar_24h';
            } elseif ($score >= 7) {
                $classificacao = 'Internacao domiciliar de alta complexidade';
                $modalidade = 'internacao_domiciliar_24h';
            } elseif ($score >= 5) {
                $classificacao = 'Internacao domiciliar intermediaria';
                $modalidade = 'internacao_domiciliar_12h';
            } elseif ($score >= 3) {
                $classificacao = 'Atendimento domiciliar multiprofissional';
                $modalidade = 'atendimento_multiprofissional';
            } else {
                $classificacao = 'Procedimentos / acompanhamento pontual';
                $modalidade = 'procedimento_pontual';
            }
        }

        return [
            'nead_elegivel_hc' => $elegivel ? 's' : 'n',
            'nead_indicacao_imediata_hc' => $indicacaoImediata ? 's' : 'n',
            'nead_pontuacao_hc' => $score,
            'nead_classificacao_hc' => $classificacao,
            'modalidade_sugerida_hc' => $data['modalidade_sugerida_hc'] ?? $modalidade,
        ];
    }

    public function fetchQueue(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['hospital_id'])) {
            $where[] = 'i.fk_hospital_int = :hospital_id';
            $params[':hospital_id'] = (int)$filters['hospital_id'];
        }
        if (!empty($filters['seguradora_id'])) {
            $where[] = 'se.id_seguradora = :seguradora_id';
            $params[':seguradora_id'] = (int)$filters['seguradora_id'];
        }
        if (!empty($filters['status'])) {
            if ($filters['status'] === '__sem_status__') {
                $where[] = '(hc.status_hc IS NULL OR hc.status_hc = "")';
            } else {
                $where[] = 'hc.status_hc = :status_hc';
                $params[':status_hc'] = (string)$filters['status'];
            }
        }
        if (!empty($filters['modalidade'])) {
            $where[] = 'COALESCE(hc.modalidade_aprovada_hc, hc.modalidade_sugerida_hc) = :modalidade';
            $params[':modalidade'] = (string)$filters['modalidade'];
        }
        if (!empty($filters['sem_atualizacao'])) {
            $days = max(1, (int)$filters['sem_atualizacao']);
            $where[] = '(hc.data_atualizacao_hc IS NULL OR hc.data_atualizacao_hc < DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY))';
        }

        $whereSql = $where ? ' AND ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT
                i.id_internacao,
                p.nome_pac,
                p.matricula_pac,
                h.nome_hosp,
                se.id_seguradora,
                se.seguradora_seg,
                i.data_intern_int,
                GREATEST(1, DATEDIFF(CURRENT_DATE(), i.data_intern_int) + 1) AS diarias,
                hc.id_home_care,
                hc.data_atualizacao_hc,
                hc.status_hc,
                hc.modalidade_sugerida_hc,
                hc.modalidade_aprovada_hc,
                hc.previsao_implantacao_hc,
                hc.barreira_principal_hc,
                hc.fornecedor_hc,
                hc.nead_pontuacao_hc,
                hc.nead_classificacao_hc,
                hc.nead_elegivel_hc,
                CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM tb_gestao g
                        WHERE g.fk_internacao_ges = i.id_internacao
                          AND (g.home_care_ges = 's' OR g.desospitalizacao_ges = 's')
                          AND (g.deletado_ges IS NULL OR g.deletado_ges <> 's')
                    ) THEN 's'
                    ELSE 'n'
                END AS sinalizado_hc
            FROM tb_internacao i
            LEFT JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
            LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
            LEFT JOIN tb_seguradora se ON se.id_seguradora = p.fk_seguradora_pac
            LEFT JOIN (
                SELECT hc1.*
                FROM tb_home_care_avaliacao hc1
                INNER JOIN (
                    SELECT fk_internacao_hc, MAX(id_home_care) AS max_id
                    FROM tb_home_care_avaliacao
                    GROUP BY fk_internacao_hc
                ) last_hc ON last_hc.max_id = hc1.id_home_care
            ) hc ON hc.fk_internacao_hc = i.id_internacao
            WHERE i.data_intern_int IS NOT NULL
              AND i.data_intern_int <> '0000-00-00'
              AND i.internado_int = 's'
              AND (
                    hc.id_home_care IS NOT NULL
                    OR EXISTS (
                        SELECT 1
                        FROM tb_gestao g
                        WHERE g.fk_internacao_ges = i.id_internacao
                          AND (g.home_care_ges = 's' OR g.desospitalizacao_ges = 's')
                          AND (g.deletado_ges IS NULL OR g.deletado_ges <> 's')
                    )
              )
              {$whereSql}
            ORDER BY
                CASE
                    WHEN COALESCE(hc.status_hc, '') IN ('implantado', 'negado', 'descontinuado') THEN 1
                    ELSE 0
                END ASC,
                COALESCE(hc.nead_elegivel_hc, 'n') DESC,
                GREATEST(1, DATEDIFF(CURRENT_DATE(), i.data_intern_int) + 1) DESC,
                i.data_intern_int ASC
            LIMIT 300
        ";

        $stmt = $this->conn->prepare($sql);
        $this->bindAll($stmt, $params);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getContextByInternacao(int $internacaoId): ?array
    {
        $sql = "
            SELECT
                i.id_internacao,
                i.senha_int,
                i.rel_int,
                i.data_intern_int,
                i.internado_int,
                i.tipo_admissao_int,
                i.modo_internacao_int,
                p.nome_pac,
                p.matricula_pac,
                h.nome_hosp,
                se.seguradora_seg,
                GREATEST(1, DATEDIFF(CURRENT_DATE(), i.data_intern_int) + 1) AS diarias,
                (
                    SELECT MAX(g.data_create_ges)
                    FROM tb_gestao g
                    WHERE g.fk_internacao_ges = i.id_internacao
                      AND g.home_care_ges = 's'
                ) AS ultima_sinalizacao_hc
            FROM tb_internacao i
            LEFT JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
            LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
            LEFT JOIN tb_seguradora se ON se.id_seguradora = p.fk_seguradora_pac
            WHERE i.id_internacao = :id
            LIMIT 1
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $internacaoId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getHistoryByInternacao(int $internacaoId): array
    {
        $sql = "
            SELECT hc.*, u.usuario_user
            FROM tb_home_care_avaliacao hc
            LEFT JOIN tb_user u ON u.id_usuario = hc.fk_usuario_hc
            WHERE hc.fk_internacao_hc = :id
            ORDER BY hc.data_atualizacao_hc DESC, hc.id_home_care DESC
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $internacaoId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getLatestByInternacao(int $internacaoId): ?array
    {
        $history = $this->getHistoryByInternacao($internacaoId);
        return $history[0] ?? null;
    }

    public function createUpdate(array $data): int
    {
        $calculated = $this->calculateNead($data);

        $sql = "
            INSERT INTO tb_home_care_avaliacao (
                fk_internacao_hc,
                fk_usuario_hc,
                status_hc,
                fornecedor_hc,
                modalidade_sugerida_hc,
                modalidade_aprovada_hc,
                previsao_implantacao_hc,
                data_visita_domiciliar_hc,
                barreira_principal_hc,
                plano_transicao_hc,
                pendencia_familia_hc,
                pendencia_hospital_hc,
                pendencia_operadora_hc,
                equipamentos_hc,
                custo_hospital_dia_hc,
                custo_home_care_dia_hc,
                potencial_economia_hc,
                observacoes_hc,
                nead_grupo1_cuidador_hc,
                nead_grupo1_ambiente_hc,
                nead_grupo1_locomocao_hc,
                nead_grupo2_vm_hc,
                nead_grupo2_aspiracao_hc,
                nead_grupo2_medicacao_ev_hc,
                nead_grupo2_dieta_parenteral_hc,
                nead_grupo2_lesao_complexa_hc,
                nead_grupo3_katz_hc,
                nead_grupo3_enteral_hc,
                nead_grupo3_oxigenio_hc,
                nead_grupo3_traqueostomia_hc,
                nead_grupo3_dialise_hc,
                nead_elegivel_hc,
                nead_indicacao_imediata_hc,
                nead_pontuacao_hc,
                nead_classificacao_hc
            ) VALUES (
                :fk_internacao_hc,
                :fk_usuario_hc,
                :status_hc,
                :fornecedor_hc,
                :modalidade_sugerida_hc,
                :modalidade_aprovada_hc,
                :previsao_implantacao_hc,
                :data_visita_domiciliar_hc,
                :barreira_principal_hc,
                :plano_transicao_hc,
                :pendencia_familia_hc,
                :pendencia_hospital_hc,
                :pendencia_operadora_hc,
                :equipamentos_hc,
                :custo_hospital_dia_hc,
                :custo_home_care_dia_hc,
                :potencial_economia_hc,
                :observacoes_hc,
                :nead_grupo1_cuidador_hc,
                :nead_grupo1_ambiente_hc,
                :nead_grupo1_locomocao_hc,
                :nead_grupo2_vm_hc,
                :nead_grupo2_aspiracao_hc,
                :nead_grupo2_medicacao_ev_hc,
                :nead_grupo2_dieta_parenteral_hc,
                :nead_grupo2_lesao_complexa_hc,
                :nead_grupo3_katz_hc,
                :nead_grupo3_enteral_hc,
                :nead_grupo3_oxigenio_hc,
                :nead_grupo3_traqueostomia_hc,
                :nead_grupo3_dialise_hc,
                :nead_elegivel_hc,
                :nead_indicacao_imediata_hc,
                :nead_pontuacao_hc,
                :nead_classificacao_hc
            )
        ";

        $payload = [
            ':fk_internacao_hc' => (int)$data['fk_internacao_hc'],
            ':fk_usuario_hc' => !empty($data['fk_usuario_hc']) ? (int)$data['fk_usuario_hc'] : null,
            ':status_hc' => $this->toNullableText($data['status_hc'] ?? '', 50),
            ':fornecedor_hc' => $this->toNullableText($data['fornecedor_hc'] ?? '', 120),
            ':modalidade_sugerida_hc' => $this->toNullableText($calculated['modalidade_sugerida_hc'] ?? '', 60),
            ':modalidade_aprovada_hc' => $this->toNullableText($data['modalidade_aprovada_hc'] ?? '', 60),
            ':previsao_implantacao_hc' => $this->toNullableDate($data['previsao_implantacao_hc'] ?? ''),
            ':data_visita_domiciliar_hc' => $this->toNullableDate($data['data_visita_domiciliar_hc'] ?? ''),
            ':barreira_principal_hc' => $this->toNullableText($data['barreira_principal_hc'] ?? '', 120),
            ':plano_transicao_hc' => $this->toNullableText($data['plano_transicao_hc'] ?? ''),
            ':pendencia_familia_hc' => $this->toNullableText($data['pendencia_familia_hc'] ?? ''),
            ':pendencia_hospital_hc' => $this->toNullableText($data['pendencia_hospital_hc'] ?? ''),
            ':pendencia_operadora_hc' => $this->toNullableText($data['pendencia_operadora_hc'] ?? ''),
            ':equipamentos_hc' => $this->toNullableText($data['equipamentos_hc'] ?? ''),
            ':custo_hospital_dia_hc' => $this->toMoney($data['custo_hospital_dia_hc'] ?? ''),
            ':custo_home_care_dia_hc' => $this->toMoney($data['custo_home_care_dia_hc'] ?? ''),
            ':potencial_economia_hc' => $this->toMoney($data['potencial_economia_hc'] ?? ''),
            ':observacoes_hc' => $this->toNullableText($data['observacoes_hc'] ?? ''),
            ':nead_grupo1_cuidador_hc' => $this->yesNo($data['nead_grupo1_cuidador_hc'] ?? 'n'),
            ':nead_grupo1_ambiente_hc' => $this->yesNo($data['nead_grupo1_ambiente_hc'] ?? 'n'),
            ':nead_grupo1_locomocao_hc' => $this->yesNo($data['nead_grupo1_locomocao_hc'] ?? 'n'),
            ':nead_grupo2_vm_hc' => $this->yesNo($data['nead_grupo2_vm_hc'] ?? 'n'),
            ':nead_grupo2_aspiracao_hc' => $this->yesNo($data['nead_grupo2_aspiracao_hc'] ?? 'n'),
            ':nead_grupo2_medicacao_ev_hc' => $this->yesNo($data['nead_grupo2_medicacao_ev_hc'] ?? 'n'),
            ':nead_grupo2_dieta_parenteral_hc' => $this->yesNo($data['nead_grupo2_dieta_parenteral_hc'] ?? 'n'),
            ':nead_grupo2_lesao_complexa_hc' => $this->yesNo($data['nead_grupo2_lesao_complexa_hc'] ?? 'n'),
            ':nead_grupo3_katz_hc' => $this->scorePoint($data['nead_grupo3_katz_hc'] ?? 0),
            ':nead_grupo3_enteral_hc' => $this->scorePoint($data['nead_grupo3_enteral_hc'] ?? 0),
            ':nead_grupo3_oxigenio_hc' => $this->scorePoint($data['nead_grupo3_oxigenio_hc'] ?? 0),
            ':nead_grupo3_traqueostomia_hc' => $this->scorePoint($data['nead_grupo3_traqueostomia_hc'] ?? 0),
            ':nead_grupo3_dialise_hc' => $this->scorePoint($data['nead_grupo3_dialise_hc'] ?? 0),
            ':nead_elegivel_hc' => $calculated['nead_elegivel_hc'],
            ':nead_indicacao_imediata_hc' => $calculated['nead_indicacao_imediata_hc'],
            ':nead_pontuacao_hc' => (int)$calculated['nead_pontuacao_hc'],
            ':nead_classificacao_hc' => $this->toNullableText($calculated['nead_classificacao_hc'] ?? '', 80),
        ];

        $stmt = $this->conn->prepare($sql);
        $this->bindAll($stmt, $payload);
        $stmt->execute();
        return (int)$this->conn->lastInsertId();
    }
}
