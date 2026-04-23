<?php

class UtiAuditAiService
{
    private string $apiKey;
    private string $apiUrl;
    private string $model;

    public function __construct(?string $apiKey = null, ?string $apiUrl = null, ?string $model = null)
    {
        $this->loadEnvFile(dirname(__DIR__, 2) . '/.env');
        $this->apiKey = trim((string)($apiKey ?: getenv('MINHA_API_TOKEN') ?: ($_ENV['MINHA_API_TOKEN'] ?? '') ?: getenv('OPENAI_API_KEY') ?: ($_ENV['OPENAI_API_KEY'] ?? '')));
        $this->apiUrl = trim((string)($apiUrl ?: getenv('OPENAI_API_URL') ?: 'https://api.openai.com/v1/responses'));
        $this->model = trim((string)($model ?: getenv('OPENAI_MODEL') ?: 'gpt-4.1-mini'));
    }

    public function analyzeReport(string $report): array
    {
        $report = trim($report);
        if ($report === '') {
            throw new InvalidArgumentException('Informe o relatório clínico de UTI.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Extensão cURL não disponível no servidor.');
        }
        if ($this->apiKey === '') {
            throw new RuntimeException('MINHA_API_TOKEN não configurada no ambiente.');
        }

        $prompt = $this->buildPrompt($report);
        $payload = [
            'model' => $this->model,
            'input' => [
                [
                    'role' => 'system',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => 'Você é médico auditor hospitalar. Responda em português-BR, com rigor técnico, objetividade e sem inventar dados não documentados.',
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
            'temperature' => 0.1,
            'max_output_tokens' => 1200,
        ];

        $raw = $this->request($payload);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Resposta inválida do serviço de IA.');
        }

        $text = $this->extractText($decoded);
        if ($text === null || $text === '') {
            throw new RuntimeException('O serviço de IA retornou resposta vazia.');
        }

        $parsed = $this->parseJsonResponse($text);
        if (!is_array($parsed)) {
            throw new RuntimeException('Não foi possível interpretar o parecer retornado pela IA.');
        }

        return $this->normalizeResult($parsed, $report);
    }

    private function buildPrompt(string $report): string
    {
        return "Analise o relatório clínico abaixo e classifique a pertinência de internação ou permanência em UTI.\n\n"
            . "Objetivo:\n"
            . "- Classificar em JUSTIFICADO, NAO_JUSTIFICADO ou DADOS_INSUFICIENTES.\n\n"
            . "Critérios obrigatórios:\n"
            . "- instabilidade hemodinamica\n"
            . "- necessidade de suporte ventilatorio\n"
            . "- risco de deterioracao clinica\n"
            . "- necessidade de monitorizacao continua\n"
            . "- complexidade terapeutica\n\n"
            . "Regras:\n"
            . "- Baseie-se exclusivamente no texto fornecido.\n"
            . "- Nao invente dados ausentes.\n"
            . "- Se faltarem elementos centrais, use DADOS_INSUFICIENTES.\n"
            . "- A justificativa tecnica deve ser objetiva e adequada para auditoria medica.\n\n"
            . "Classificacao complementar obrigatoria:\n"
            . "- Informe o nivel recomendado em UTI, SEMI_UTI, APTO ou INDETERMINADO.\n"
            . "- Use UTI quando houver criterios de terapia intensiva.\n"
            . "- Use SEMI_UTI quando houver criterios intermediarios, mas sem elementos suficientes para UTI plena.\n"
            . "- Use APTO quando o quadro descrito for compativel com permanencia em apartamento/enfermaria, sem criterio para UTI ou Semi-UTI.\n"
            . "- Use INDETERMINADO quando faltarem dados para definir o nivel.\n\n"
            . "Formato obrigatorio de saida: retorne SOMENTE JSON valido com esta estrutura:\n"
            . "{\n"
            . "  \"classificacao\": \"JUSTIFICADO | NAO_JUSTIFICADO | DADOS_INSUFICIENTES\",\n"
            . "  \"nivel_recomendado\": \"UTI | SEMI_UTI | APTO | INDETERMINADO\",\n"
            . "  \"resumo_clinico\": \"texto curto\",\n"
            . "  \"criterios\": {\n"
            . "    \"instabilidade_hemodinamica\": \"presente | ausente | inconclusivo\",\n"
            . "    \"suporte_ventilatorio\": \"presente | ausente | inconclusivo\",\n"
            . "    \"risco_deterioracao_clinica\": \"presente | ausente | inconclusivo\",\n"
            . "    \"monitorizacao_continua\": \"presente | ausente | inconclusivo\",\n"
            . "    \"complexidade_terapeutica\": \"presente | ausente | inconclusivo\"\n"
            . "  },\n"
            . "  \"justificativa_tecnica\": \"texto objetivo de auditoria\",\n"
            . "  \"frase_final\": \"frase curta e conclusiva para destaque visual\",\n"
            . "  \"pendencias_documentais\": [\"item 1\", \"item 2\"]\n"
            . "}\n\n"
            . "RELATORIO CLINICO:\n" . $report;
    }

    private function request(array $payload): string
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
            CURLOPT_TIMEOUT => 30,
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
            if ($httpCode === 429) {
                $detail = $apiMessage !== '' ? ' Detalhe: ' . $apiMessage : '';
                throw new RuntimeException('Limite ou cota da API atingido (HTTP 429).' . $detail);
            }
            throw new RuntimeException('Serviço de IA indisponível no momento (HTTP ' . $httpCode . ').' . ($apiMessage !== '' ? ' Detalhe: ' . $apiMessage : ''));
        }

        return (string)$raw;
    }

    private function extractErrorMessage(string $raw): string
    {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return '';
        }

        $message = $decoded['error']['message'] ?? $decoded['message'] ?? '';
        return trim((string)$message);
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

    private function parseJsonResponse(string $text): ?array
    {
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $clean = trim((string)preg_replace('/^```(?:json)?|```$/m', '', $text));
        $decoded = json_decode($clean, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function normalizeResult(array $result, string $report): array
    {
        $allowedClassifications = ['JUSTIFICADO', 'NAO_JUSTIFICADO', 'DADOS_INSUFICIENTES'];
        $allowedLevels = ['UTI', 'SEMI_UTI', 'APTO', 'INDETERMINADO'];
        $allowedCriteria = ['presente', 'ausente', 'inconclusivo'];
        $criteriaKeys = [
            'instabilidade_hemodinamica',
            'suporte_ventilatorio',
            'risco_deterioracao_clinica',
            'monitorizacao_continua',
            'complexidade_terapeutica',
        ];

        $classification = strtoupper(trim((string)($result['classificacao'] ?? 'DADOS_INSUFICIENTES')));
        if (!in_array($classification, $allowedClassifications, true)) {
            $classification = 'DADOS_INSUFICIENTES';
        }

        $recommendedLevel = strtoupper(trim((string)($result['nivel_recomendado'] ?? 'INDETERMINADO')));
        if (!in_array($recommendedLevel, $allowedLevels, true)) {
            $recommendedLevel = 'INDETERMINADO';
        }

        $criteria = [];
        $sourceCriteria = is_array($result['criterios'] ?? null) ? $result['criterios'] : [];
        foreach ($criteriaKeys as $key) {
            $value = strtolower(trim((string)($sourceCriteria[$key] ?? 'inconclusivo')));
            $criteria[$key] = in_array($value, $allowedCriteria, true) ? $value : 'inconclusivo';
        }

        $pendencias = [];
        if (!empty($result['pendencias_documentais']) && is_array($result['pendencias_documentais'])) {
            foreach ($result['pendencias_documentais'] as $item) {
                $item = trim((string)$item);
                if ($item !== '') {
                    $pendencias[] = $item;
                }
            }
        }

        $finalPhrase = trim((string)($result['frase_final'] ?? ''));
        if ($finalPhrase === '') {
            $finalPhrase = $this->defaultFinalPhrase($recommendedLevel);
        }

        return [
            'classificacao' => $classification,
            'nivel_recomendado' => $recommendedLevel,
            'resumo_clinico' => trim((string)($result['resumo_clinico'] ?? '')),
            'criterios' => $criteria,
            'justificativa_tecnica' => trim((string)($result['justificativa_tecnica'] ?? '')),
            'frase_final' => $finalPhrase,
            'pendencias_documentais' => $pendencias,
            'meta' => [
                'model' => $this->model,
                'api_url' => $this->apiUrl,
                'report_chars' => strlen($report),
            ],
        ];
    }

    private function defaultFinalPhrase(string $recommendedLevel): string
    {
        if ($recommendedLevel === 'UTI') {
            return 'Parecer IA: caso considerado com criterios para UTI.';
        }
        if ($recommendedLevel === 'SEMI_UTI') {
            return 'Parecer IA: caso considerado com criterios para Semi-UTI.';
        }
        if ($recommendedLevel === 'APTO') {
            return 'Parecer IA: caso considerado com criterios para Apto.';
        }

        return 'Parecer IA: nao foi possivel definir com seguranca o nivel assistencial com os dados informados.';
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
