<?php

class PacienteChatService
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

    public function answer(int $patientId, string $question, array $ctx): array
    {
        $question = trim($question);
        if ($patientId <= 0) {
            throw new InvalidArgumentException('Paciente inválido.');
        }
        if ($question === '') {
            throw new InvalidArgumentException('Digite uma pergunta sobre este paciente.');
        }
        if (mb_strlen($question, 'UTF-8') > 1000) {
            throw new InvalidArgumentException('Pergunta muito longa. Tente ser mais objetivo.');
        }

        $context = $this->buildPatientContext($patientId, $ctx);
        $source = ($this->apiKey !== '' && function_exists('curl_init')) ? 'openai' : 'local';

        try {
            $answer = $this->requestAnswer($question, $context);
        } catch (Throwable $e) {
            $source = 'local';
            $answer = $this->buildLocalAnswer($question, $context)
                . "\n\nObservação técnica: a IA não respondeu agora; usei uma leitura local dos dados estruturados.";
        }

        return [
            'answer' => $answer,
            'summary' => [
                'source' => $source,
                'patient_id' => $patientId,
                'internacoes' => (int)($context['indicadores']['total_internacoes'] ?? 0),
                'antecedentes' => count($context['antecedentes'] ?? []),
            ],
        ];
    }

    private function buildPatientContext(int $patientId, array $ctx): array
    {
        $patient = $this->fetchPatient($patientId, $ctx);
        if (!$patient) {
            throw new RuntimeException('Paciente não encontrado ou sem permissão de acesso.');
        }

        $internacoes = $this->fetchInternacoes($patientId, $ctx);
        $antecedentes = $this->fetchAntecedentes($patientId, array_column($internacoes, 'id_internacao'));

        return [
            'paciente' => $this->compactPatient($patient),
            'indicadores' => $this->buildIndicators($internacoes, $antecedentes),
            'internacoes' => $internacoes,
            'antecedentes' => $antecedentes,
            'limites' => [
                'escopo' => 'Dados exclusivos deste paciente.',
                'orientacao' => 'Não usar dados externos, não inferir diagnóstico e não prescrever conduta médica.',
            ],
        ];
    }

    private function fetchPatient(int $patientId, array $ctx): ?array
    {
        $params = [':patient_id' => $patientId];
        $scope = function_exists('ajax_scope_clause_for_paciente')
            ? ajax_scope_clause_for_paciente($ctx, 'p', $params, 'pacchat')
            : '';

        $sql = "
            SELECT
                p.id_paciente,
                p.nome_pac,
                p.nome_social_pac,
                p.data_nasc_pac,
                p.recem_nascido_pac,
                p.sexo_pac,
                p.matricula_pac,
                p.num_atendimento_pac,
                p.data_create_pac,
                p.ativo_pac,
                p.deletado_pac,
                COALESCE(s.seguradora_seg, '') AS seguradora,
                COALESCE(e.nome_est, '') AS estipulante
            FROM tb_paciente p
            LEFT JOIN tb_seguradora s ON s.id_seguradora = p.fk_seguradora_pac
            LEFT JOIN tb_estipulante e ON e.id_estipulante = p.fk_estipulante_pac
            WHERE p.id_paciente = :patient_id
              AND LOWER(COALESCE(p.deletado_pac, 'n')) <> 's'
              {$scope}
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function compactPatient(array $patient): array
    {
        $ageInfo = $this->ageInfoFromDate($patient['data_nasc_pac'] ?? null);
        return [
            'id_paciente' => (int)($patient['id_paciente'] ?? 0),
            'nome' => trim((string)($patient['nome_pac'] ?? '')),
            'nome_social' => trim((string)($patient['nome_social_pac'] ?? '')),
            'data_nascimento' => (string)($patient['data_nasc_pac'] ?? ''),
            'idade_anos' => $ageInfo['anos'],
            'idade_descricao' => $ageInfo['descricao'],
            'recem_nascido_cadastro' => strtolower((string)($patient['recem_nascido_pac'] ?? '')) === 's' ? 'sim' : 'nao',
            'sexo' => (string)($patient['sexo_pac'] ?? ''),
            'matricula' => trim((string)($patient['matricula_pac'] ?? '')),
            'numero_atendimento' => trim((string)($patient['num_atendimento_pac'] ?? '')),
            'data_cadastro' => (string)($patient['data_create_pac'] ?? ''),
            'status' => (strtolower((string)($patient['ativo_pac'] ?? '')) === 'n') ? 'inativo' : 'ativo',
            'seguradora' => trim((string)($patient['seguradora'] ?? '')),
            'estipulante' => trim((string)($patient['estipulante'] ?? '')),
        ];
    }

    private function fetchInternacoes(int $patientId, array $ctx): array
    {
        try {
            $params = [':patient_id' => $patientId];
            $scope = function_exists('ajax_scope_clause_for_internacao')
                ? ajax_scope_clause_for_internacao($ctx, 'i', $params, 'pacchatint')
                : '';

            $stmt = $this->conn->prepare("
                SELECT
                    i.id_internacao,
                    i.data_intern_int,
                    al.data_alta_alt,
                    i.internado_int,
                    i.acomodacao_int,
                    i.tipo_admissao_int,
                    i.modo_internacao_int,
                    i.grupo_patologia_int,
                    COALESCE(pa.patologia_pat, '') AS patologia,
                    COALESCE(h.nome_hosp, '') AS hospital,
                    GREATEST(DATEDIFF(COALESCE(NULLIF(al.data_alta_alt, '0000-00-00'), CURDATE()), i.data_intern_int), 0) AS dias_permanencia,
                    lv.ultima_visita,
                    DATEDIFF(CURDATE(), lv.ultima_visita) AS dias_sem_visita,
                    LEFT(COALESCE(lv.relatorio, ''), 700) AS ultima_evolucao,
                    COALESCE(uti.qtd_uti, 0) AS passagens_uti,
                    COALESCE(ges.eventos_adversos, 0) AS eventos_adversos,
                    COALESCE(ges.alto_custo, 0) AS alto_custo,
                    COALESCE(ges.opme, 0) AS opme,
                    COALESCE(ges.home_care, 0) AS home_care,
                    COALESCE(ges.desospitalizacao, 0) AS desospitalizacao
                FROM tb_internacao i
                LEFT JOIN tb_hospital h ON h.id_hospital = i.fk_hospital_int
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
                           SUBSTRING_INDEX(
                               GROUP_CONCAT(COALESCE(NULLIF(v.rel_visita_vis, ''), NULLIF(v.acoes_int_vis, ''), '') ORDER BY v.data_visita_vis DESC SEPARATOR ' || '),
                               ' || ',
                               1
                           ) AS relatorio
                      FROM tb_visita v
                     GROUP BY v.fk_internacao_vis
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
                WHERE i.fk_paciente_int = :patient_id
                  AND LOWER(COALESCE(i.deletado_int, 'n')) <> 's'
                  {$scope}
                ORDER BY COALESCE(i.data_intern_int, '0000-00-00') DESC, i.id_internacao DESC
                LIMIT 25
            ");
            $this->bindParams($stmt, $params);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return array_map([$this, 'compactInternacao'], $rows);
        } catch (Throwable $e) {
            return [];
        }
    }

    private function compactInternacao(array $row): array
    {
        $status = strtolower((string)($row['internado_int'] ?? '')) === 's' ? 'internado' : 'alta/encerrado';
        $diasSemVisita = $row['dias_sem_visita'] === null ? null : (int)$row['dias_sem_visita'];
        return [
            'id_internacao' => (int)($row['id_internacao'] ?? 0),
            'data_internacao' => (string)($row['data_intern_int'] ?? ''),
            'data_alta' => (string)($row['data_alta_alt'] ?? ''),
            'status' => $status,
            'hospital' => $this->cleanLabel($row['hospital'] ?? ''),
            'acomodacao' => $this->cleanLabel($row['acomodacao_int'] ?? ''),
            'tipo_admissao' => $this->cleanLabel($row['tipo_admissao_int'] ?? ''),
            'modo_internacao' => $this->cleanLabel($row['modo_internacao_int'] ?? ''),
            'grupo_patologia' => $this->cleanClinicalLabel($row['grupo_patologia_int'] ?? ''),
            'patologia' => $this->cleanClinicalLabel($row['patologia'] ?? ''),
            'dias_permanencia' => (int)($row['dias_permanencia'] ?? 0),
            'ultima_visita' => (string)($row['ultima_visita'] ?? ''),
            'dias_sem_visita' => $status === 'internado' ? $diasSemVisita : null,
            'observacao_visita' => $status === 'internado'
                ? 'dias_sem_visita se aplica a internação aberta'
                : 'internação encerrada; não tratar dias desde a última visita como pendência atual',
            'ultima_evolucao' => $this->cleanLabel($row['ultima_evolucao'] ?? ''),
            'passagens_uti' => (int)($row['passagens_uti'] ?? 0),
            'eventos_adversos' => (int)($row['eventos_adversos'] ?? 0),
            'alto_custo' => (int)($row['alto_custo'] ?? 0),
            'opme' => (int)($row['opme'] ?? 0),
            'home_care' => (int)($row['home_care'] ?? 0),
            'desospitalizacao' => (int)($row['desospitalizacao'] ?? 0),
        ];
    }

    private function fetchAntecedentes(int $patientId, array $internacaoIds): array
    {
        $internacaoIds = array_values(array_filter(array_map('intval', $internacaoIds)));
        $placeholdersAnt = [];
        $placeholdersVis = [];
        $params = [':patient_id' => $patientId];
        foreach (array_slice($internacaoIds, 0, 30) as $idx => $id) {
            $keyAnt = ':int_ant_' . $idx;
            $keyVis = ':int_vis_' . $idx;
            $placeholdersAnt[] = $keyAnt;
            $placeholdersVis[] = $keyVis;
            $params[$keyAnt] = $id;
            $params[$keyVis] = $id;
        }
        $inAntSql = $placeholdersAnt ? implode(',', $placeholdersAnt) : '0';
        $inVisSql = $placeholdersVis ? implode(',', $placeholdersVis) : '0';

        try {
            $stmt = $this->conn->prepare("
                SELECT DISTINCT antecedente_texto
                  FROM (
                        SELECT TRIM(CONCAT_WS(' ',
                                   NULLIF(a.antecedente_ant, ''),
                                   NULLIF(c.cat, ''),
                                   NULLIF(c.descricao, '')
                               )) AS antecedente_texto
                          FROM tb_intern_antec ia
                          INNER JOIN tb_antecedente a
                                  ON a.id_antecedente = ia.intern_antec_ant_int
                          LEFT JOIN tb_cid c
                                 ON c.id_cid = a.fk_cid_10_ant
                         WHERE (
                                   ia.fk_id_paciente = :patient_id
                                OR ia.fK_internacao_ant_int IN ({$inAntSql})
                                OR ia.fk_internacao_vis IN ({$inVisSql})
                               )
                           AND (
                               COALESCE(a.antecedente_ant, '') <> ''
                               OR COALESCE(c.cat, '') <> ''
                               OR COALESCE(c.descricao, '') <> ''
                           )
                        UNION
                        SELECT TRIM(CONCAT_WS(' ',
                                   NULLIF(a.antecedente_ant, ''),
                                   NULLIF(c.cat, ''),
                                   NULLIF(c.descricao, '')
                               )) AS antecedente_texto
                          FROM tb_internacao i
                          INNER JOIN tb_antecedente a
                                  ON a.id_antecedente = i.fk_patologia2
                          LEFT JOIN tb_cid c
                                 ON c.id_cid = a.fk_cid_10_ant
                         WHERE i.fk_paciente_int = :patient_id_diag
                           AND i.fk_patologia2 IS NOT NULL
                           AND i.fk_patologia2 > 0
                    ) antecedentes
                 WHERE antecedente_texto <> ''
                 ORDER BY antecedente_texto ASC
                 LIMIT 30
            ");
            $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
            $stmt->bindValue(':patient_id_diag', $patientId, PDO::PARAM_INT);
            foreach ($params as $key => $value) {
                if ($key === ':patient_id') {
                    continue;
                }
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            }
            $stmt->execute();
            return array_values(array_filter(array_map([$this, 'cleanClinicalLabel'], $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [])));
        } catch (Throwable $e) {
            return [];
        }
    }

    private function buildIndicators(array $internacoes, array $antecedentes): array
    {
        $internados = 0;
        $dias = [];
        $eventos = 0;
        $uti = 0;
        $longas = 0;
        $semVisita = 0;
        foreach ($internacoes as $row) {
            if (($row['status'] ?? '') === 'internado') {
                $internados++;
            }
            $dias[] = (int)($row['dias_permanencia'] ?? 0);
            if ((int)($row['eventos_adversos'] ?? 0) > 0) {
                $eventos++;
            }
            if ((int)($row['passagens_uti'] ?? 0) > 0) {
                $uti++;
            }
            if ((int)($row['dias_permanencia'] ?? 0) >= 20) {
                $longas++;
            }
            if (($row['status'] ?? '') === 'internado' && ($row['dias_sem_visita'] ?? null) !== null && (int)$row['dias_sem_visita'] >= 5) {
                $semVisita++;
            }
        }

        return [
            'total_internacoes' => count($internacoes),
            'internacoes_abertas' => $internados,
            'media_permanencia' => $dias ? round(array_sum($dias) / count($dias), 1) : 0,
            'longa_permanencia_20d_ou_mais' => $longas,
            'internacoes_com_uti' => $uti,
            'internacoes_com_evento_adverso' => $eventos,
            'internacoes_abertas_sem_visita_5d_ou_mais' => $semVisita,
            'total_antecedentes' => count($antecedentes),
        ];
    }

    private function requestAnswer(string $question, array $context): string
    {
        if (!function_exists('curl_init') || $this->apiKey === '') {
            return $this->buildLocalAnswer($question, $context);
        }

        $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $payload = [
            'model' => $this->model,
            'input' => [
                [
                    'role' => 'system',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => 'Você é um assistente IA do FullCare para consulta de um único paciente. Responda em português-BR, com texto natural para usuário final. Nunca responda em JSON, objeto, array, bloco de código ou formato técnico. Use exclusivamente os dados fornecidos. Se a pergunta for sobre outro paciente, dados fora do JSON ou assunto geral, diga que só pode responder sobre este paciente. Não faça diagnóstico, prescrição, decisão médica automática nem afirmações sem registro. Não chame paciente de recém-nascido apenas porque idade_anos é 0; use idade_descricao e recem_nascido_cadastro. Não trate dias desde última visita de internação encerrada como pendência atual. Quando faltar dado, diga que não há registro suficiente. Cite IDs de internação quando mencionar internações.',
                        ],
                    ],
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => "Pergunta:\n{$question}\n\nDados exclusivos deste paciente em JSON:\n{$json}\n\nFormato obrigatório da resposta:\nResumo\n- 2 a 4 bullets em linguagem simples.\n\nPontos registrados\n- Liste apenas fatos existentes no JSON.\n\nPróximos passos\n- Sugira apenas ações operacionais compatíveis com o registro. Se a internação estiver encerrada e não houver pendência atual, diga que não há pendência operacional evidente pelos dados disponíveis.\n\nNão inclua chaves, aspas, colchetes, nomes de campos técnicos nem JSON.",
                        ],
                    ],
                ],
            ],
            'temperature' => 0.12,
            'max_output_tokens' => 900,
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
        return $this->normalizeAnswerText($text);
    }

    private function buildLocalAnswer(string $question, array $context): string
    {
        $patient = $context['paciente'] ?? [];
        $ind = $context['indicadores'] ?? [];
        $internacoes = array_slice($context['internacoes'] ?? [], 0, 5);
        $antecedentes = array_slice($context['antecedentes'] ?? [], 0, 10);

        $text = "Resumo\n";
        $text .= "- " . ($patient['nome'] ?? 'Paciente') . ", ";
        $text .= (($patient['idade_descricao'] ?? '') ?: 'idade não informada') . ", ";
        $text .= (($patient['seguradora'] ?? '') ?: 'seguradora não informada') . ".\n";
        $text .= "- Histórico com ";
        $text .= $this->formatCount((int)($ind['total_internacoes'] ?? 0), 'internação', 'internações') . ", ";
        $text .= $this->formatCount((int)($ind['internacoes_abertas'] ?? 0), 'aberta', 'abertas') . " e média de permanência de ";
        $text .= number_format((float)($ind['media_permanencia'] ?? 0), 1, ',', '.') . " dia(s), ";
        $text .= "com " . $this->formatCount((int)($ind['total_antecedentes'] ?? 0), 'antecedente registrado', 'antecedentes registrados') . ".\n\n";

        $text .= "Pontos registrados\n";
        if ($antecedentes) {
            $text .= "- Antecedentes: " . implode('; ', $antecedentes) . ".\n";
        } else {
            $text .= "- Não há antecedentes estruturados registrados no contexto consultado.\n";
        }

        if ($internacoes) {
            foreach ($internacoes as $row) {
                $text .= "- ID " . (int)($row['id_internacao'] ?? 0) . ": "
                    . (($row['hospital'] ?? '') ?: 'hospital não informado')
                    . ", " . (($row['status'] ?? '') ?: 'status não informado')
                    . ", " . (int)($row['dias_permanencia'] ?? 0) . " dia(s)";
                if (($row['patologia'] ?? '') !== '' || ($row['grupo_patologia'] ?? '') !== '') {
                    $text .= ", " . (($row['patologia'] ?? '') ?: ($row['grupo_patologia'] ?? ''));
                }
                if ((int)($row['eventos_adversos'] ?? 0) > 0) {
                    $text .= ", evento adverso registrado";
                }
                if (($row['status'] ?? '') === 'internado' && ($row['dias_sem_visita'] ?? null) !== null && (int)$row['dias_sem_visita'] >= 5) {
                    $text .= ", sem visita recente";
                }
                $text .= ".\n";
            }
        } else {
            $text .= "- Não há internações registradas para este paciente.\n";
        }

        $text .= "\nPróximos passos\n";
        if ((int)($ind['internacoes_abertas'] ?? 0) > 0) {
            $text .= "- Conferir evolução/visita mais recente e pendências da internação aberta.\n";
        } else {
            $text .= "- Não há internação aberta nem pendência operacional evidente pelos dados disponíveis.\n";
        }
        $text .= "- Manter os dados cadastrais e o histórico clínico estruturado revisados quando houver novo atendimento.";
        return $text;
    }

    private function normalizeAnswerText(string $text): string
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return $trimmed;
        }

        $json = $trimmed;
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $trimmed, $m)) {
            $json = trim($m[1]);
        }

        if ($json !== '' && ($json[0] === '{' || $json[0] === '[')) {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                return $this->jsonAnswerToText($decoded);
            }
        }

        return $trimmed;
    }

    private function jsonAnswerToText(array $data): string
    {
        $lines = [];
        $profile = $data['perfil_paciente'] ?? [];
        if (is_array($profile)) {
            $name = trim((string)($profile['nome'] ?? 'Paciente'));
            $summaryParts = [];
            if (!empty($profile['seguradora'])) {
                $summaryParts[] = 'seguradora ' . $profile['seguradora'];
            }
            if (!empty($profile['status'])) {
                $summaryParts[] = 'status ' . $profile['status'];
            }
            $intern = is_array($profile['internacoes'] ?? null) ? $profile['internacoes'] : [];
            $total = (int)($intern['total'] ?? 0);
            $open = (int)($intern['internacoes_abertas'] ?? 0);

            $lines[] = 'Resumo';
            $lines[] = '- ' . $name . ($summaryParts ? ', ' . implode(', ', $summaryParts) : '') . '.';
            $lines[] = '- Histórico com ' . $total . ' internação(ões) registrada(s) e ' . $open . ' aberta(s).';
            if (!empty($profile['antecedentes']) && is_array($profile['antecedentes'])) {
                $lines[] = '- Antecedentes registrados: ' . implode('; ', array_map('strval', $profile['antecedentes'])) . '.';
            }
            $lines[] = '';
        }

        $findings = $data['achados_registrados'] ?? $data['achados'] ?? [];
        if (is_array($findings) && $findings) {
            $lines[] = 'Pontos registrados';
            foreach ($findings as $item) {
                $item = trim((string)$item);
                if ($item !== '') {
                    $lines[] = '- ' . $this->softenTechnicalText($item);
                }
            }
            $lines[] = '';
        }

        $steps = $data['proximos_passos_operacionais'] ?? $data['proximos_passos'] ?? [];
        if (is_array($steps) && $steps) {
            $lines[] = 'Próximos passos';
            foreach ($steps as $item) {
                $item = trim((string)$item);
                if ($item !== '') {
                    $lines[] = '- ' . $this->softenTechnicalText($item);
                }
            }
        }

        return trim(implode("\n", $lines)) ?: 'Não há dados suficientes para montar uma resposta sobre este paciente.';
    }

    private function softenTechnicalText(string $text): string
    {
        $text = preg_replace('/Paciente masculino, recém-nascido \(idade 0\)\.?/iu', 'Paciente masculino, com menos de 1 ano conforme data de nascimento registrada.', $text);
        $text = preg_replace('/,\s*com\s+\d+\s+dias\s+sem\s+visita\s+após alta/iu', '', $text);
        return trim((string)$text);
    }

    private function cleanLabel($value): string
    {
        $text = trim((string)$value);
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim((string)$text);
    }

    private function cleanClinicalLabel($value): string
    {
        $text = $this->cleanLabel($value);
        if ($text === '') {
            return '';
        }

        $text = trim((string)preg_replace('/\bSEM\s+INFORMA\S*/iu', '', $text));
        $text = $this->cleanLabel($text);
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $norm = strtoupper(trim($ascii !== false ? $ascii : $text));
        if (in_array($norm, ['SEM INFORMACOES', 'SEM INFORMACAO', 'NAO INFORMADO', 'NAO INFORMADA'], true)) {
            return '';
        }
        return $text;
    }

    private function formatCount(int $count, string $singular, string $plural): string
    {
        return $count . ' ' . ($count === 1 ? $singular : $plural);
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

    private function bindParams(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, is_int($value) ? $value : (string)$value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }

    private function ageInfoFromDate($date): array
    {
        $date = trim((string)$date);
        if ($date === '' || $date === '0000-00-00') {
            return ['anos' => null, 'descricao' => 'idade não informada'];
        }
        try {
            $birth = new DateTime($date);
            $diff = $birth->diff(new DateTime());
            $years = (int)$diff->y;
            if ($years > 0) {
                return [
                    'anos' => $years,
                    'descricao' => $years . ' ano' . ($years === 1 ? '' : 's'),
                ];
            }

            $months = ((int)$diff->m) + ((int)$diff->y * 12);
            if ($months > 0) {
                return [
                    'anos' => 0,
                    'descricao' => $months . ' ' . ($months === 1 ? 'mês' : 'meses'),
                ];
            }

            $days = max(0, (int)$diff->days);
            return [
                'anos' => 0,
                'descricao' => $days > 0 ? $days . ' dia' . ($days === 1 ? '' : 's') : 'menos de 1 dia',
            ];
        } catch (Throwable $e) {
            return ['anos' => null, 'descricao' => 'idade não informada'];
        }
    }

    private function loadEnvFile(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            $value = trim($value, "\"'");
            if ($key !== '' && getenv($key) === false) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
            }
        }
    }
}
