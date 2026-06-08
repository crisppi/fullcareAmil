<?php

class HospitalOpportunityService
{
    private PDO $conn;

    private array $glosaColumns = [
        'Diárias' => 'glosa_diaria',
        'Mat/Med' => 'glosa_matmed',
        'Medicamentos' => 'glosa_medicamentos',
        'Materiais' => 'glosa_materiais',
        'Taxas' => 'glosa_taxas',
        'Honorários' => 'glosa_honorarios',
        'SADT' => 'glosa_sadt',
        'Oxigênio' => 'glosa_oxig',
        'OPME' => 'glosa_opme',
    ];

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function hospitalRows(array $hospitalIds, int $limit = 8): array
    {
        $hospitalIds = $this->normalizeIds($hospitalIds);
        if (empty($hospitalIds)) {
            return [];
        }

        $rows = $this->baseHospitalRows($hospitalIds);
        $negotiations = $this->negotiationRows($hospitalIds);
        $negotiationTypes = $this->negotiationTypeRows($hospitalIds);
        $negotiationSwaps = $this->negotiationSwapRows($hospitalIds);
        $glosas = $this->glosaRows($hospitalIds);

        foreach ($rows as $id => $row) {
            $neg = $negotiations[$id] ?? [
                'qtd' => 0,
                'saving' => 0.0,
                'meses' => 0,
                'qtd_media_mensal' => 0.0,
                'saving_media_mensal' => 0.0,
            ];
            $topTipo = $negotiationTypes[$id][0] ?? null;
            $topTroca = $negotiationSwaps[$id][0] ?? null;
            $glosa = $glosas[$id] ?? ['total' => 0.0, 'valor_apresentado' => 0.0, 'percentual' => 0.0, 'tipos' => []];
            $level = $this->classifyLevel(
                (float)$neg['qtd_media_mensal'],
                (float)$neg['saving_media_mensal'],
                (float)$glosa['percentual'],
                (int)($row['internados'] ?? 0)
            );

            $rows[$id]['negociacoes'] = (int)$neg['qtd'];
            $rows[$id]['negociacoes_meses'] = (int)$neg['meses'];
            $rows[$id]['negociacoes_media_mensal'] = (float)$neg['qtd_media_mensal'];
            $rows[$id]['saving'] = (float)$neg['saving'];
            $rows[$id]['saving_media_mensal'] = (float)$neg['saving_media_mensal'];
            $rows[$id]['tipo_negociacao'] = $topTipo['tipo'] ?? 'Sem negociação registrada';
            $rows[$id]['tipo_negociacao_qtd'] = (int)($topTipo['qtd'] ?? 0);
            $rows[$id]['troca_principal'] = $topTroca['troca'] ?? 'Sem troca registrada';
            $rows[$id]['troca_principal_qtd'] = (int)($topTroca['qtd'] ?? 0);
            $rows[$id]['glosa_total'] = (float)$glosa['total'];
            $rows[$id]['glosa_valor_apresentado'] = (float)$glosa['valor_apresentado'];
            $rows[$id]['glosa_percentual'] = (float)$glosa['percentual'];
            $rows[$id]['glosa_tipos'] = array_slice((array)$glosa['tipos'], 0, 3);
            $rows[$id]['nivel'] = $level['nivel'];
            $rows[$id]['nivel_label'] = $level['label'];
            $rows[$id]['nivel_icon'] = $level['icon'];
            $rows[$id]['score'] = $level['score'];
        }

        $rows = array_values($rows);
        usort($rows, static function (array $a, array $b): int {
            $scoreCmp = ((int)($b['score'] ?? 0)) <=> ((int)($a['score'] ?? 0));
            if ($scoreCmp !== 0) {
                return $scoreCmp;
            }
            return strcasecmp((string)($a['nome_hosp'] ?? ''), (string)($b['nome_hosp'] ?? ''));
        });

        return array_slice($rows, 0, max(1, $limit));
    }

    private function normalizeIds(array $hospitalIds): array
    {
        $ids = [];
        foreach ($hospitalIds as $id) {
            $id = is_array($id) ? ($id['id_hospital'] ?? 0) : $id;
            $id = (int)$id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }

    private function placeholders(array $ids, string $prefix): array
    {
        $params = [];
        $tokens = [];
        foreach ($ids as $idx => $id) {
            $key = ':' . $prefix . $idx;
            $tokens[] = $key;
            $params[$key] = (int)$id;
        }
        return [$tokens, $params];
    }

    private function executeForHospitals(string $sql, array $ids, string $prefix): array
    {
        [$tokens, $params] = $this->placeholders($ids, $prefix);
        $stmt = $this->conn->prepare(str_replace('{HOSPITAL_IDS}', implode(', ', $tokens), $sql));
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function baseHospitalRows(array $ids): array
    {
        $rows = $this->executeForHospitals("
            SELECT h.id_hospital,
                   h.nome_hosp,
                   COUNT(DISTINCT CASE WHEN i.internado_int = 's' THEN i.id_internacao END) AS internados
              FROM tb_hospital h
              LEFT JOIN tb_internacao i ON i.fk_hospital_int = h.id_hospital
             WHERE h.id_hospital IN ({HOSPITAL_IDS})
             GROUP BY h.id_hospital, h.nome_hosp
        ", $ids, 'bh');

        $out = [];
        foreach ($rows as $row) {
            $id = (int)($row['id_hospital'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $out[$id] = [
                'id_hospital' => $id,
                'nome_hosp' => (string)($row['nome_hosp'] ?? ('Hospital #' . $id)),
                'internados' => (int)($row['internados'] ?? 0),
            ];
        }
        return $out;
    }

    private function negotiationRows(array $ids): array
    {
        $rows = $this->executeForHospitals("
            SELECT i.fk_hospital_int AS id_hospital,
                   COUNT(DISTINCT ng.id_negociacao) AS qtd,
                   COALESCE(SUM(COALESCE(ng.saving, 0)), 0) AS saving,
                   COUNT(DISTINCT DATE_FORMAT(
                       COALESCE(
                           NULLIF(ng.data_inicio_neg, '0000-00-00'),
                           NULLIF(ng.data_fim_neg, '0000-00-00'),
                           DATE(ng.updated_at),
                           CURRENT_DATE
                       ),
                       '%Y-%m'
                   )) AS meses
              FROM tb_negociacao ng
              INNER JOIN tb_internacao i ON i.id_internacao = ng.fk_id_int
             WHERE i.fk_hospital_int IN ({HOSPITAL_IDS})
               AND UPPER(COALESCE(ng.tipo_negociacao, '')) <> 'PRORROGACAO_AUTOMATICA'
             GROUP BY i.fk_hospital_int
        ", $ids, 'neg');

        $out = [];
        foreach ($rows as $row) {
            $qtd = (int)($row['qtd'] ?? 0);
            $saving = (float)($row['saving'] ?? 0);
            $meses = max(1, (int)($row['meses'] ?? 0));
            $out[(int)$row['id_hospital']] = [
                'qtd' => $qtd,
                'saving' => $saving,
                'meses' => $qtd > 0 ? $meses : 0,
                'qtd_media_mensal' => $qtd > 0 ? round($qtd / $meses, 1) : 0.0,
                'saving_media_mensal' => $qtd > 0 ? round($saving / $meses, 2) : 0.0,
            ];
        }
        return $out;
    }

    private function negotiationTypeRows(array $ids): array
    {
        $rows = $this->executeForHospitals("
            SELECT i.fk_hospital_int AS id_hospital,
                   COALESCE(NULLIF(TRIM(ng.tipo_negociacao), ''), 'Não informado') AS tipo,
                   COUNT(*) AS qtd,
                   COALESCE(SUM(COALESCE(ng.saving, 0)), 0) AS saving
              FROM tb_negociacao ng
              INNER JOIN tb_internacao i ON i.id_internacao = ng.fk_id_int
             WHERE i.fk_hospital_int IN ({HOSPITAL_IDS})
               AND UPPER(COALESCE(ng.tipo_negociacao, '')) <> 'PRORROGACAO_AUTOMATICA'
             GROUP BY i.fk_hospital_int, tipo
             ORDER BY qtd DESC, saving DESC, tipo ASC
        ", $ids, 'nt');

        $out = [];
        foreach ($rows as $row) {
            $id = (int)$row['id_hospital'];
            $out[$id][] = [
                'tipo' => (string)($row['tipo'] ?? 'Não informado'),
                'qtd' => (int)($row['qtd'] ?? 0),
                'saving' => (float)($row['saving'] ?? 0),
            ];
        }
        return $out;
    }

    private function negotiationSwapRows(array $ids): array
    {
        $rows = $this->executeForHospitals("
            SELECT i.fk_hospital_int AS id_hospital,
                   CONCAT(
                       COALESCE(NULLIF(TRIM(ng.troca_de), ''), 'Origem não informada'),
                       ' → ',
                       COALESCE(NULLIF(TRIM(ng.troca_para), ''), 'Destino não informado')
                   ) AS troca,
                   COUNT(*) AS qtd,
                   COALESCE(SUM(COALESCE(ng.saving, 0)), 0) AS saving
              FROM tb_negociacao ng
              INNER JOIN tb_internacao i ON i.id_internacao = ng.fk_id_int
             WHERE i.fk_hospital_int IN ({HOSPITAL_IDS})
               AND UPPER(COALESCE(ng.tipo_negociacao, '')) <> 'PRORROGACAO_AUTOMATICA'
             GROUP BY i.fk_hospital_int, troca
             ORDER BY qtd DESC, saving DESC
        ", $ids, 'ns');

        $out = [];
        foreach ($rows as $row) {
            $id = (int)$row['id_hospital'];
            $out[$id][] = [
                'troca' => (string)($row['troca'] ?? 'Sem troca registrada'),
                'qtd' => (int)($row['qtd'] ?? 0),
                'saving' => (float)($row['saving'] ?? 0),
            ];
        }
        return $out;
    }

    private function glosaRows(array $ids): array
    {
        $selects = [];
        foreach ($this->glosaColumns as $label => $column) {
            $selects[] = "COALESCE(SUM(COALESCE(ca.`{$column}`, 0)), 0) AS `{$column}`";
        }

        $rows = $this->executeForHospitals("
            SELECT i.fk_hospital_int AS id_hospital,
                   COALESCE(SUM(COALESCE(ca.valor_glosa_total, 0)), 0) AS total_glosa,
                   COALESCE(SUM(COALESCE(ca.valor_apresentado_capeante, 0)), 0) AS valor_apresentado,
                   " . implode(",\n                   ", $selects) . "
              FROM tb_capeante ca
              INNER JOIN tb_internacao i ON i.id_internacao = ca.fk_int_capeante
             WHERE i.fk_hospital_int IN ({HOSPITAL_IDS})
             GROUP BY i.fk_hospital_int
        ", $ids, 'gl');

        $out = [];
        foreach ($rows as $row) {
            $tipos = [];
            $totalGlosa = (float)($row['total_glosa'] ?? 0);
            $valorApresentado = (float)($row['valor_apresentado'] ?? 0);
            foreach ($this->glosaColumns as $label => $column) {
                $value = (float)($row[$column] ?? 0);
                if ($value > 0) {
                    $tipos[] = [
                        'tipo' => $label,
                        'valor' => $value,
                        'percentual' => $totalGlosa > 0 ? round(($value / $totalGlosa) * 100, 1) : 0.0,
                    ];
                }
            }
            usort($tipos, static function (array $a, array $b): int {
                return ((float)$b['valor']) <=> ((float)$a['valor']);
            });

            $out[(int)$row['id_hospital']] = [
                'total' => $totalGlosa,
                'valor_apresentado' => $valorApresentado,
                'percentual' => $valorApresentado > 0 ? round(($totalGlosa / $valorApresentado) * 100, 1) : 0.0,
                'tipos' => $tipos,
            ];
        }
        return $out;
    }

    private function classifyLevel(float $negotiationsMonthly, float $savingMonthly, float $glosaPercent, int $internados): array
    {
        $score = 0;

        if ($negotiationsMonthly >= 5) {
            $score += 3;
        } elseif ($negotiationsMonthly >= 2) {
            $score += 2;
        } elseif ($negotiationsMonthly >= 1) {
            $score += 1;
        }

        if ($savingMonthly >= 5000) {
            $score += 3;
        } elseif ($savingMonthly >= 1000) {
            $score += 2;
        } elseif ($savingMonthly > 0) {
            $score += 1;
        }

        if ($glosaPercent >= 15) {
            $score += 3;
        } elseif ($glosaPercent >= 8) {
            $score += 2;
        } elseif ($glosaPercent > 0) {
            $score += 1;
        }

        if ($internados >= 10) {
            $score += 1;
        }

        if ($score >= 6) {
            return ['nivel' => 'alto', 'label' => 'Alto', 'icon' => 'bi-exclamation-circle-fill', 'score' => $score];
        }
        if ($score >= 3) {
            return ['nivel' => 'medio', 'label' => 'Médio', 'icon' => 'bi-dash-circle-fill', 'score' => $score];
        }
        return ['nivel' => 'baixo', 'label' => 'Baixo', 'icon' => 'bi-check-circle-fill', 'score' => $score];
    }
}
