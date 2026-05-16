<?php

require_once(__DIR__ . '/../utils/longa_permanencia_schema.php');

class LongaPermanenciaDAO
{
    private PDO $conn;
    private string $baseUrl;

    public function __construct(PDO $conn, string $baseUrl)
    {
        $this->conn = $conn;
        $this->baseUrl = $baseUrl;
        fullcareEnsureLongaPermanenciaSchema($this->conn);
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

    public function getStatusOptions(): array
    {
        return [
            'em_analise' => 'Em análise',
            'plano_definido' => 'Plano definido',
            'aguardando_hospital' => 'Aguardando hospital',
            'aguardando_familia' => 'Aguardando família',
            'aguardando_seguradora' => 'Aguardando seguradora',
            'desospitalizacao' => 'Desospitalização em curso',
            'resolvido' => 'Resolvido',
        ];
    }

    public function getMotivoOptions(): array
    {
        return [
            'aguarda_alta_clinica' => 'Aguarda alta clínica',
            'desmame_ventilatorio' => 'Desmame ventilatório',
            'home_care' => 'Definição de home care',
            'reabilitacao' => 'Transição para reabilitação',
            'pendencia_exame' => 'Pendência de exame/procedimento',
            'pendencia_familia' => 'Pendência familiar/social',
            'pendencia_seguradora' => 'Pendência seguradora',
            'pendencia_hospital' => 'Pendência hospitalar',
            'opme' => 'OPME/autorização',
            'cuidados_paliativos' => 'Cuidados paliativos',
            'outros' => 'Outros',
        ];
    }

    public function getRiscoOptions(): array
    {
        return [
            'baixo' => 'Baixo',
            'medio' => 'Médio',
            'alto' => 'Alto',
            'critico' => 'Crítico',
        ];
    }

    public function fetchQueue(array $filters = []): array
    {
        $where = [];
        $params = [':limiar_padrao' => 30];

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
                $where[] = '(lp.status_lp IS NULL OR lp.status_lp = "")';
            } else {
                $where[] = 'lp.status_lp = :status_lp';
                $params[':status_lp'] = (string)$filters['status'];
            }
        }
        if (!empty($filters['escalonamento'])) {
            $where[] = 'COALESCE(lp.necessita_escalonamento_lp, "n") = :esc';
            $params[':esc'] = (string)$filters['escalonamento'];
        }
        if (!empty($filters['sem_atualizacao'])) {
            $days = max(1, (int)$filters['sem_atualizacao']);
            $where[] = '(lp.data_atualizacao_lp IS NULL OR lp.data_atualizacao_lp < DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY))';
        }

        $whereSql = $where ? ' AND ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT *
            FROM (
                SELECT
                    i.id_internacao,
                    p.nome_pac,
                    h.nome_hosp,
                    se.id_seguradora,
                    se.seguradora_seg,
                    i.data_intern_int,
                    GREATEST(1, DATEDIFF(CURRENT_DATE(), i.data_intern_int) + 1) AS diarias,
                    COALESCE(NULLIF(se.longa_permanencia_seg, 0), :limiar_padrao) AS limiar,
                    lp.id_longa_perm,
                    lp.data_atualizacao_lp,
                    lp.status_lp,
                    lp.motivo_principal_lp,
                    lp.responsavel_lp,
                    lp.proxima_revisao_lp,
                    lp.previsao_alta_lp,
                    lp.necessita_escalonamento_lp,
                    lp.risco_sinistro_lp
                FROM tb_internacao i
                LEFT JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
                LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
                LEFT JOIN tb_seguradora se ON se.id_seguradora = p.fk_seguradora_pac
                LEFT JOIN (
                    SELECT lp1.*
                    FROM tb_longa_permanencia_gestao lp1
                    INNER JOIN (
                        SELECT fk_internacao_lp, MAX(id_longa_perm) AS max_id
                        FROM tb_longa_permanencia_gestao
                        GROUP BY fk_internacao_lp
                    ) last_lp ON last_lp.max_id = lp1.id_longa_perm
                ) lp ON lp.fk_internacao_lp = i.id_internacao
                WHERE i.data_intern_int IS NOT NULL
                  AND i.data_intern_int <> '0000-00-00'
                  AND i.internado_int = 's'
                  {$whereSql}
            ) fila
            WHERE fila.diarias >= fila.limiar
            ORDER BY (fila.diarias - fila.limiar) DESC,
                     fila.data_intern_int ASC
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
                p.nome_pac,
                p.matricula_pac,
                h.nome_hosp,
                se.seguradora_seg,
                COALESCE(NULLIF(se.longa_permanencia_seg, 0), 30) AS limiar,
                GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1) AS diarias
            FROM tb_internacao i
            LEFT JOIN tb_paciente p ON p.id_paciente = i.fk_paciente_int
            LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
            LEFT JOIN tb_seguradora se ON se.id_seguradora = p.fk_seguradora_pac
            LEFT JOIN (
                SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt
                FROM tb_alta
                GROUP BY fk_id_int_alt
            ) al ON al.fk_id_int_alt = i.id_internacao
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
            SELECT
                lp.*,
                u.usuario_user
            FROM tb_longa_permanencia_gestao lp
            LEFT JOIN tb_user u ON u.id_usuario = lp.fk_usuario_lp
            WHERE lp.fk_internacao_lp = :id
            ORDER BY lp.data_atualizacao_lp DESC, lp.id_longa_perm DESC
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
        $sql = "
            INSERT INTO tb_longa_permanencia_gestao (
                fk_internacao_lp,
                fk_usuario_lp,
                status_lp,
                motivo_principal_lp,
                barreira_clinica_lp,
                barreira_administrativa_lp,
                plano_acao_lp,
                responsavel_lp,
                prazo_acao_lp,
                previsao_alta_lp,
                proxima_revisao_lp,
                potencial_desospitalizacao_lp,
                necessita_escalonamento_lp,
                risco_sinistro_lp,
                observacoes_lp
            ) VALUES (
                :fk_internacao_lp,
                :fk_usuario_lp,
                :status_lp,
                :motivo_principal_lp,
                :barreira_clinica_lp,
                :barreira_administrativa_lp,
                :plano_acao_lp,
                :responsavel_lp,
                :prazo_acao_lp,
                :previsao_alta_lp,
                :proxima_revisao_lp,
                :potencial_desospitalizacao_lp,
                :necessita_escalonamento_lp,
                :risco_sinistro_lp,
                :observacoes_lp
            )
        ";

        $stmt = $this->conn->prepare($sql);
        $payload = [
            ':fk_internacao_lp' => (int)$data['fk_internacao_lp'],
            ':fk_usuario_lp' => !empty($data['fk_usuario_lp']) ? (int)$data['fk_usuario_lp'] : null,
            ':status_lp' => $this->toNullableText($data['status_lp'] ?? '', 50),
            ':motivo_principal_lp' => $this->toNullableText($data['motivo_principal_lp'] ?? '', 120),
            ':barreira_clinica_lp' => $this->toNullableText($data['barreira_clinica_lp'] ?? ''),
            ':barreira_administrativa_lp' => $this->toNullableText($data['barreira_administrativa_lp'] ?? ''),
            ':plano_acao_lp' => $this->toNullableText($data['plano_acao_lp'] ?? ''),
            ':responsavel_lp' => $this->toNullableText($data['responsavel_lp'] ?? '', 120),
            ':prazo_acao_lp' => $this->toNullableDate($data['prazo_acao_lp'] ?? ''),
            ':previsao_alta_lp' => $this->toNullableDate($data['previsao_alta_lp'] ?? ''),
            ':proxima_revisao_lp' => $this->toNullableDate($data['proxima_revisao_lp'] ?? ''),
            ':potencial_desospitalizacao_lp' => (($data['potencial_desospitalizacao_lp'] ?? 'n') === 's') ? 's' : 'n',
            ':necessita_escalonamento_lp' => (($data['necessita_escalonamento_lp'] ?? 'n') === 's') ? 's' : 'n',
            ':risco_sinistro_lp' => $this->toNullableText($data['risco_sinistro_lp'] ?? '', 30),
            ':observacoes_lp' => $this->toNullableText($data['observacoes_lp'] ?? ''),
        ];
        $this->bindAll($stmt, $payload);
        $stmt->execute();
        return (int)$this->conn->lastInsertId();
    }
}
