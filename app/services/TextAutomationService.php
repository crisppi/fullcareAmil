<?php

class TextAutomationService
{
    private PDO $conn;
    private ?array $prorrogacaoColumns = null;
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

    public function getAvailableAnalyses(): array
    {
        return [
            'completa' => 'Análise completa',
            'tempo_medio' => 'Tempo médio de internação',
            'negociacao' => 'Negociação',
            'uti' => 'Permanência em UTI',
            'diagnostico' => 'Diagnóstico',
            'antecedentes' => 'Antecedentes',
            'evolucao' => 'Evolução',
        ];
    }

    public function listInternacoesForSelect(int $limit = 120): array
    {
        $stmt = $this->conn->prepare("
            SELECT
                ac.id_internacao,
                ac.data_intern_int,
                ac.internado_int,
                hos.nome_hosp,
                pa.nome_pac
            FROM tb_internacao ac
            INNER JOIN tb_paciente pa ON pa.id_paciente = ac.fk_paciente_int
            INNER JOIN tb_hospital hos ON hos.id_hospital = ac.fk_hospital_int
            ORDER BY
                CASE WHEN LOWER(COALESCE(ac.internado_int, '')) = 's' THEN 0 ELSE 1 END,
                ac.data_intern_int DESC,
                ac.id_internacao DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', max(20, min(300, $limit)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function generateTexts(int $internacaoId, string $analysisType = 'completa'): array
    {
        if ($internacaoId <= 0) {
            throw new InvalidArgumentException('Informe o ID da internação.');
        }

        $analysisType = $this->normalizeAnalysisType($analysisType);
        $analysisLabel = $this->getAvailableAnalyses()[$analysisType] ?? $this->getAvailableAnalyses()['completa'];

        $context = $this->fetchContext($internacaoId);
        if (!$context) {
            throw new RuntimeException('Internação não encontrada ou sem dados vinculados.');
        }

        $visits = $this->fetchRecentVisits($internacaoId, 4);
        $prorrogacoes = $this->fetchProrrogacoes($internacaoId);
        $negociacoes = $this->fetchNegociacoes($internacaoId);
        $uti = $this->fetchUtiRecords($internacaoId);
        $antecedentes = $this->fetchAntecedentes(
            $internacaoId,
            (int)($context['id_paciente'] ?? $context['fk_paciente_int'] ?? 0)
        );

        $aiResult = $this->buildAiAnalysis(
            $context,
            $visits,
            $prorrogacoes,
            $negociacoes,
            $uti,
            $antecedentes,
            $analysisType,
            $analysisLabel
        );

        return [
            'context' => $context,
            'analysis_type' => $analysisType,
            'analysis_label' => $analysisLabel,
            'ai_text' => $aiResult['text'],
            'ai_source' => $aiResult['source'],
            'ai_warning' => $aiResult['warning'],
            'visit_summary' => $this->buildVisitSummary($context, $visits),
            'visit_bullets' => $this->buildVisitBullets($visits),
            'prorrogacao_summary' => $this->buildProrrogacaoSummary($context, $prorrogacoes, $visits),
            'prorrogacao_bullets' => $this->buildProrrogacaoBullets($prorrogacoes),
        ];
    }

    private function fetchContext(int $internacaoId): ?array
    {
        $sql = "
            SELECT 
                ac.id_internacao,
                ac.data_intern_int,
                ac.acomodacao_int,
                ac.grupo_patologia_int,
                ac.modo_internacao_int,
                ac.tipo_admissao_int,
                ac.internado_int,
                ac.timer_int,
                ac.fk_paciente_int,
                ac.fk_hospital_int,
                ac.fk_patologia2,
                hos.nome_hosp,
                pa.id_paciente,
                pa.nome_pac,
                pa.idade_pac,
                pa.sexo_pac,
                pa.data_nasc_pac
            FROM tb_internacao ac
            INNER JOIN tb_paciente pa ON pa.id_paciente = ac.fk_paciente_int
            INNER JOIN tb_hospital hos ON hos.id_hospital = ac.fk_hospital_int
            WHERE ac.id_internacao = :id
            LIMIT 1
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $internacaoId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['dias_internado'] = $this->calcDiasInternado($row['data_intern_int']);
        $row['idade'] = $this->resolveIdade($row['idade_pac'], $row['data_nasc_pac']);

        return $row;
    }

    private function fetchNegociacoes(int $internacaoId): array
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT
                    id_negociacao,
                    tipo_negociacao,
                    troca_de,
                    troca_para,
                    qtd,
                    saving,
                    data_inicio_neg,
                    data_fim_neg
                FROM tb_negociacao
                WHERE fk_id_int = :id
                  AND LOWER(COALESCE(deletado_neg, 'n')) <> 's'
                ORDER BY data_inicio_neg DESC, id_negociacao DESC
                LIMIT 8
            ");
            $stmt->bindValue(':id', $internacaoId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private function fetchUtiRecords(int $internacaoId): array
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT
                    id_uti,
                    data_internacao_uti,
                    data_alta_uti,
                    internacao_uti,
                    internado_uti,
                    especialidade_uti,
                    motivo_uti,
                    criterios_uti,
                    just_uti,
                    rel_uti,
                    dva_uti,
                    vm_uti,
                    saps_uti,
                    score_uti,
                    glasgow_uti,
                    suporte_vent_uti,
                    justifique_uti
                FROM tb_uti
                WHERE fk_internacao_uti = :id
                ORDER BY data_internacao_uti DESC, id_uti DESC
                LIMIT 6
            ");
            $stmt->bindValue(':id', $internacaoId, PDO::PARAM_INT);
            $stmt->execute();

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as &$row) {
                $row['dias_uti'] = $this->calcDateDiffDays($row['data_internacao_uti'] ?? null, $row['data_alta_uti'] ?? null);
            }
            unset($row);

            return $rows;
        } catch (Throwable $e) {
            return [];
        }
    }

    private function fetchAntecedentes(int $internacaoId, int $patientId): array
    {
        if ($patientId <= 0 && $internacaoId <= 0) {
            return [];
        }

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
                                OR ia.fK_internacao_ant_int = :internacao_id
                                OR ia.fk_internacao_vis = :internacao_id_vis
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
                         WHERE i.id_internacao = :internacao_id_diag
                           AND i.fk_patologia2 IS NOT NULL
                           AND i.fk_patologia2 > 0
                           AND (
                               COALESCE(a.antecedente_ant, '') <> ''
                               OR COALESCE(c.cat, '') <> ''
                               OR COALESCE(c.descricao, '') <> ''
                           )
                    ) antecedentes
                 WHERE antecedente_texto <> ''
                 ORDER BY antecedente_texto ASC
                 LIMIT 12
            ");
            $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
            $stmt->bindValue(':internacao_id', $internacaoId, PDO::PARAM_INT);
            $stmt->bindValue(':internacao_id_vis', $internacaoId, PDO::PARAM_INT);
            $stmt->bindValue(':internacao_id_diag', $internacaoId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private function fetchRecentVisits(int $internacaoId, int $limit = 3): array
    {
        $stmt = $this->conn->prepare("
            SELECT 
                id_visita,
                data_visita_vis,
                visita_no_vis,
                visita_med_vis,
                visita_enf_vis,
                COALESCE(rel_visita_vis, '') AS rel_visita_vis,
                COALESCE(acoes_int_vis, '') AS acoes_int_vis
            FROM tb_visita
            WHERE fk_internacao_vis = :id
            ORDER BY data_visita_vis DESC, id_visita DESC
            LIMIT :limite
        ");
        $stmt->bindValue(':id', $internacaoId, PDO::PARAM_INT);
        $stmt->bindValue(':limite', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        $visits = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $visits[] = [
                'id'        => (int) $row['id_visita'],
                'data'      => $row['data_visita_vis'],
                'numero'    => $row['visita_no_vis'],
                'resumo'    => trim($row['rel_visita_vis']),
                'acoes'     => trim($row['acoes_int_vis']),
                'resp_med'  => $row['visita_med_vis'],
                'resp_enf'  => $row['visita_enf_vis']
            ];
        }

        return $visits;
    }

    private function fetchProrrogacoes(int $internacaoId): array
    {
        $base = [
            'id_prorrogacao',
            'prorrog1_ini_pror',
            'prorrog1_fim_pror',
            'diarias_1',
            'acomod1_pror',
            'isol_1_pror'
        ];
        $optionals = [
            'alto_custo_pror',
            'rel_alto_custo_pror',
            'evento_adverso_pror',
            'rel_evento_adverso_pror',
            'home_care_pror',
            'rel_home_care_pror'
        ];
        $availableCols = $this->getProrrogacaoColumns();
        $selectCols = $base;
        foreach ($optionals as $col) {
            if (in_array($col, $availableCols, true)) {
                $selectCols[] = $col;
            }
        }

        $sql = sprintf(
            "SELECT %s FROM tb_prorrogacao WHERE fk_internacao_pror = :id ORDER BY prorrog1_ini_pror DESC, id_prorrogacao DESC LIMIT 4",
            implode(', ', $selectCols)
        );

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $internacaoId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function buildVisitSummary(array $context, array $visits): string
    {
        $nome = $context['nome_pac'] ?? 'Paciente';
        $idade = $context['idade'] ? "{$context['idade']} anos" : 'idade não informada';
        $hospital = $context['nome_hosp'] ?? 'hospital não identificado';
        $dias = $context['dias_internado'];
        $acomodacao = $context['acomodacao_int'] ?: 'acomodação não registrada';
        $patologia = $context['grupo_patologia_int'] ?: 'sem patologia principal cadastrada';

        $intro = sprintf(
            '%s (%s) está internado(a) há %s dia(s) no %s, em %s, para manejo de %s.',
            $nome,
            $idade,
            $dias,
            $hospital,
            strtolower($acomodacao),
            strtolower($patologia)
        );

        if (empty($visits)) {
            return $intro . ' Ainda não há registros de visitas assistenciais que permitam detalhar a evolução.';
        }

        $trechos = [];
        foreach ($visits as $visit) {
            $data = $visit['data'] ? date('d/m', strtotime($visit['data'])) : 'data não informada';
            $resumo = $visit['resumo'] ?: 'sem evolução registrada';
            $acoes = $visit['acoes'] ? " Ações sugeridas: {$visit['acoes']}." : '';

            $referencia = $visit['resp_med'] ?: $visit['resp_enf'] ?: 'equipe assistencial';
            $trechos[] = "{$data} - Avaliação {$referencia}: {$resumo}{$acoes}";
        }

        return $intro . ' Principais evoluções recentes: ' . implode(' ', $trechos);
    }

    private function buildVisitBullets(array $visits): array
    {
        $bullets = [];
        foreach ($visits as $visit) {
            $data = $visit['data'] ? date('d/m/Y', strtotime($visit['data'])) : 'data não informada';
            $resp = $visit['resp_med'] ?: $visit['resp_enf'] ?: 'Equipe assistencial';
            $texto = trim($visit['resumo'] ?: $visit['acoes']);
            if (!$texto) {
                $texto = 'Registro sem detalhes clínicos.';
            }
            $bullets[] = "{$data} - {$resp}: {$texto}";
        }
        return $bullets;
    }

    private function buildProrrogacaoSummary(array $context, array $prorrogacoes, array $visits): ?string
    {
        if (empty($prorrogacoes)) {
            return null;
        }

        $ultima = $prorrogacoes[0];
        $inicio = $ultima['prorrog1_ini_pror'] ? date('d/m/Y', strtotime($ultima['prorrog1_ini_pror'])) : 'data não informada';
        $fim = $ultima['prorrog1_fim_pror'] ? date('d/m/Y', strtotime($ultima['prorrog1_fim_pror'])) : 'data não informada';
        $diarias = $ultima['diarias_1'] ? "{$ultima['diarias_1']} diárias adicionais" : 'quantitativo não informado';

        $texto = "Solicita-se manutenção do internamento entre {$inicio} e {$fim}, totalizando {$diarias}. ";

        if (!empty($visits)) {
            $ultimaVisita = $visits[0];
            $texto .= "A última visita registrada descreve: " . ($ultimaVisita['resumo'] ?: 'evolução sem descrição detalhada') . '. ';
        }

        if (!empty($ultima['rel_alto_custo_pror'])) {
            $texto .= "Justificativa financeira: {$ultima['rel_alto_custo_pror']}. ";
        }
        if (!empty($ultima['rel_evento_adverso_pror'])) {
            $texto .= "Registro de evento adverso: {$ultima['rel_evento_adverso_pror']}. ";
        }
        if (!empty($ultima['rel_home_care_pror'])) {
            $texto .= "Plano de desospitalização/home care: {$ultima['rel_home_care_pror']}. ";
        }

        $texto .= "Recomenda-se manter monitoramento diário e reavaliar necessidade de prorrogação conforme resposta clínica.";

        return $texto;
    }

    private function buildProrrogacaoBullets(array $prorrogacoes): array
    {
        $bullets = [];
        foreach ($prorrogacoes as $pr) {
            $periodo = [];
            if ($pr['prorrog1_ini_pror']) {
                $periodo[] = date('d/m', strtotime($pr['prorrog1_ini_pror']));
            }
            if ($pr['prorrog1_fim_pror']) {
                $periodo[] = date('d/m/Y', strtotime($pr['prorrog1_fim_pror']));
            }
            $periodoTxt = $periodo ? implode(' a ', $periodo) : 'Período não informado';
            $diarias = $pr['diarias_1'] ? "{$pr['diarias_1']} diárias" : 'quantitativo não informado';
            $just = $pr['rel_alto_custo_pror'] ?? $pr['rel_home_care_pror'] ?? $pr['rel_evento_adverso_pror'] ?? 'Registro sem justificativa detalhada.';
            $bullets[] = "{$periodoTxt} - {$diarias}. {$just}";
        }
        return $bullets;
    }

    private function normalizeAnalysisType(string $analysisType): string
    {
        $analysisType = trim($analysisType);
        return array_key_exists($analysisType, $this->getAvailableAnalyses()) ? $analysisType : 'completa';
    }

    private function buildAiAnalysis(
        array $context,
        array $visits,
        array $prorrogacoes,
        array $negociacoes,
        array $uti,
        array $antecedentes,
        string $analysisType,
        string $analysisLabel
    ): array {
        try {
            $text = $this->requestAiAnalysis(
                $this->buildPromptData($context, $visits, $prorrogacoes, $negociacoes, $uti, $antecedentes),
                $analysisType,
                $analysisLabel
            );

            return [
                'text' => $text,
                'source' => 'openai',
                'warning' => null,
            ];
        } catch (Throwable $e) {
            return [
                'text' => $this->buildLocalAnalysis($context, $visits, $prorrogacoes, $negociacoes, $uti, $antecedentes, $analysisLabel),
                'source' => 'local',
                'warning' => 'OpenAI não respondeu agora: ' . $this->redactSecrets($e->getMessage()) . ' Exibindo rascunho local para revisão.',
            ];
        }
    }

    private function buildPromptData(
        array $context,
        array $visits,
        array $prorrogacoes,
        array $negociacoes,
        array $uti,
        array $antecedentes
    ): array {
        return [
            'paciente' => [
                'nome' => $context['nome_pac'] ?? '',
                'idade' => $context['idade'] ?? null,
                'sexo' => $context['sexo_pac'] ?? '',
            ],
            'internacao' => [
                'id' => (int)($context['id_internacao'] ?? 0),
                'hospital' => $context['nome_hosp'] ?? '',
                'data_internacao' => $context['data_intern_int'] ?? '',
                'dias_internado' => $context['dias_internado'] ?? 0,
                'acomodacao' => $context['acomodacao_int'] ?? '',
                'grupo_patologia' => $context['grupo_patologia_int'] ?? '',
                'modo_internacao' => $context['modo_internacao_int'] ?? '',
                'tipo_admissao' => $context['tipo_admissao_int'] ?? '',
                'internado' => $context['internado_int'] ?? '',
                'timer' => $context['timer_int'] ?? '',
            ],
            'antecedentes' => array_values($antecedentes),
            'visitas_recentes' => $this->limitTextInRows($visits),
            'prorrogacoes' => $this->limitTextInRows($prorrogacoes),
            'negociacoes' => $this->limitTextInRows($negociacoes),
            'uti' => $this->limitTextInRows($uti),
        ];
    }

    private function requestAiAnalysis(array $data, string $analysisType, string $analysisLabel): string
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Extensão cURL não disponível no servidor.');
        }
        if ($this->apiKey === '') {
            throw new RuntimeException('MINHA_API_TOKEN ou OPENAI_API_KEY não configurada no ambiente.');
        }

        $prompt = $this->buildAiPrompt($data, $analysisType, $analysisLabel);
        $payload = [
            'model' => $this->model,
            'input' => [
                [
                    'role' => 'system',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => 'Você é médico auditor hospitalar. Responda em português-BR, com precisão clínica, tom executivo e sem inventar dados ausentes.',
                        ],
                    ],
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $prompt,
                        ],
                    ],
                ],
            ],
            'temperature' => 0.2,
            'max_output_tokens' => 1100,
        ];

        $raw = $this->requestOpenAi($payload);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Resposta inválida do serviço de IA.');
        }

        $text = $this->extractText($decoded);
        if ($text === null || $text === '') {
            throw new RuntimeException('O serviço de IA retornou resposta vazia.');
        }

        return $text;
    }

    private function buildAiPrompt(array $data, string $analysisType, string $analysisLabel): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return "Gere um texto de auditoria médica para a análise selecionada: {$analysisLabel}.\n\n"
            . "Tipo técnico: {$analysisType}\n\n"
            . "O texto deve contemplar, quando houver dados disponíveis: tempo médio/tempo atual de internação, negociação, permanência em UTI, diagnóstico, antecedentes e evolução.\n\n"
            . "Regras:\n"
            . "- Use exclusivamente os dados enviados abaixo.\n"
            . "- Não invente exames, condutas, datas, diagnósticos ou desfechos.\n"
            . "- Quando um dado estiver ausente, escreva de forma objetiva que não há registro suficiente.\n"
            . "- Use linguagem de auditoria médica, clara para diretoria e operação.\n"
            . "- Não use markdown com tabelas. Pode usar pequenos subtítulos e parágrafos curtos.\n"
            . "- Conclua com uma recomendação prática de acompanhamento.\n\n"
            . "DADOS DA INTERNAÇÃO:\n{$json}";
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
            $apiMessage = $this->extractErrorMessage((string)$raw);
            throw new RuntimeException('Serviço de IA indisponível no momento (HTTP ' . $httpCode . ').' . ($apiMessage !== '' ? ' Detalhe: ' . $apiMessage : ''));
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
            if (empty($item['content']) || !is_array($item['content'])) {
                continue;
            }
            foreach ($item['content'] as $content) {
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
        if (!is_array($decoded)) {
            return '';
        }

        $message = $decoded['error']['message'] ?? $decoded['message'] ?? '';
        return $this->redactSecrets(trim((string)$message));
    }

    private function redactSecrets(string $message): string
    {
        return (string)preg_replace('/sk-[A-Za-z0-9_-]+/', '[chave removida]', $message);
    }

    private function buildLocalAnalysis(
        array $context,
        array $visits,
        array $prorrogacoes,
        array $negociacoes,
        array $uti,
        array $antecedentes,
        string $analysisLabel
    ): string {
        $nome = $context['nome_pac'] ?? 'Paciente';
        $hospital = $context['nome_hosp'] ?? 'hospital não identificado';
        $dias = (int)($context['dias_internado'] ?? 0);
        $acomodacao = $context['acomodacao_int'] ?: 'acomodação não registrada';
        $patologia = $context['grupo_patologia_int'] ?: 'diagnóstico/grupo patológico não registrado';

        $texto = "Análise: {$analysisLabel}\n\n";
        $texto .= "{$nome} está internado(a) no {$hospital} há {$dias} dia(s), em {$acomodacao}, com referência clínica de {$patologia}. ";

        if ($antecedentes) {
            $texto .= "Antecedentes registrados: " . implode('; ', $antecedentes) . ". ";
        } else {
            $texto .= "Não há antecedentes estruturados suficientes nos registros consultados. ";
        }

        if ($visits) {
            $ultima = $visits[0];
            $data = !empty($ultima['data']) ? date('d/m/Y', strtotime($ultima['data'])) : 'data não informada';
            $resumo = $ultima['resumo'] ?: ($ultima['acoes'] ?: 'sem descrição clínica detalhada');
            $texto .= "\n\nEvolução recente ({$data}): {$resumo}.";
        } else {
            $texto .= "\n\nEvolução recente: não há visitas cadastradas para sustentar análise evolutiva detalhada.";
        }

        $texto .= "\n\nUTI: ";
        if ($uti) {
            $ultimaUti = $uti[0];
            $texto .= trim((string)($ultimaUti['rel_uti'] ?: $ultimaUti['just_uti'] ?: $ultimaUti['motivo_uti'] ?: 'há registro de passagem/permanência em UTI, sem relatório detalhado.'));
        } else {
            $texto .= 'sem registro estruturado de UTI vinculado à internação.';
        }

        $texto .= "\n\nNegociação: ";
        if ($negociacoes) {
            $neg = $negociacoes[0];
            $tipo = $neg['tipo_negociacao'] ?: 'tipo não informado';
            $troca = trim(($neg['troca_de'] ?? '') . ' para ' . ($neg['troca_para'] ?? ''));
            $texto .= "{$tipo}" . ($troca !== 'para' ? " ({$troca})" : '') . ".";
        } else {
            $texto .= 'sem negociação registrada nos dados consultados.';
        }

        if ($prorrogacoes) {
            $texto .= "\n\nProrrogação: " . ($this->buildProrrogacaoSummary($context, $prorrogacoes, $visits) ?: 'há prorrogação registrada sem justificativa detalhada.');
        }

        $texto .= "\n\nRecomendação: revisar documentação clínica, evolução diária, pertinência de permanência e oportunidades de negociação antes da tomada de decisão.";

        return $texto;
    }

    private function limitTextInRows(array $rows, int $maxChars = 900): array
    {
        $limited = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                $limited[] = $row;
                continue;
            }
            $clean = [];
            foreach ($row as $key => $value) {
                if (is_string($value)) {
                    $clean[$key] = $this->truncateText($value, $maxChars);
                } else {
                    $clean[$key] = $value;
                }
            }
            $limited[] = $clean;
        }
        return $limited;
    }

    private function truncateText(string $text, int $maxChars): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if (mb_strlen($text, 'UTF-8') <= $maxChars) {
            return $text;
        }
        return mb_substr($text, 0, $maxChars - 3, 'UTF-8') . '...';
    }

    private function calcDiasInternado(?string $dataInternacao): int
    {
        if (!$dataInternacao) {
            return 0;
        }
        try {
            $inicio = new DateTime($dataInternacao);
            $agora = new DateTime();
            return max(0, (int)$inicio->diff($agora)->format('%a'));
        } catch (Throwable $e) {
            return 0;
        }
    }

    private function resolveIdade($idadeInformada, $dataNascimento): ?int
    {
        if (is_numeric($idadeInformada) && (int)$idadeInformada > 0) {
            return (int)$idadeInformada;
        }
        if ($dataNascimento) {
            try {
                $nasc = new DateTime($dataNascimento);
                $agora = new DateTime();
                return (int)$nasc->diff($agora)->y;
            } catch (Throwable $e) {
                return null;
            }
        }
        return null;
    }

    private function calcDateDiffDays(?string $start, ?string $end): ?int
    {
        if (!$start || $start === '0000-00-00') {
            return null;
        }
        try {
            $inicio = new DateTime($start);
            $fim = ($end && $end !== '0000-00-00') ? new DateTime($end) : new DateTime();
            return max(0, (int)$inicio->diff($fim)->format('%a'));
        } catch (Throwable $e) {
            return null;
        }
    }

    private function getProrrogacaoColumns(): array
    {
        if ($this->prorrogacaoColumns !== null) {
            return $this->prorrogacaoColumns;
        }
        try {
            $stmt = $this->conn->query("
                SELECT COLUMN_NAME 
                  FROM information_schema.COLUMNS 
                 WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'tb_prorrogacao'
            ");
            $this->prorrogacaoColumns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'COLUMN_NAME');
        } catch (Throwable $e) {
            $this->prorrogacaoColumns = [];
        }
        return $this->prorrogacaoColumns;
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

            $current = getenv($key);
            if ($current !== false && trim((string)$current) !== '') {
                continue;
            }

            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}
