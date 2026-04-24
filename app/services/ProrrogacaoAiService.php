<?php

class ProrrogacaoAiService
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

    public function analyze(string $report): array
    {
        $report = trim($report);
        if ($report === '') {
            throw new InvalidArgumentException('Informe o contexto clínico para análise da prorrogação.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Extensão cURL não disponível no servidor.');
        }
        if ($this->apiKey === '') {
            throw new RuntimeException('MINHA_API_TOKEN não configurada no ambiente.');
        }

        $payload = [
            'model' => $this->model,
            'input' => [
                [
                    'role' => 'system',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => 'Você é médico auditor hospitalar. Responda em português-BR, com rigor técnico, objetividade e sem inventar dados ausentes.',
                        ],
                    ],
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $this->buildPrompt($report),
                        ],
                    ],
                ],
            ],
            'temperature' => 0.1,
            'max_output_tokens' => 1400,
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
            throw new RuntimeException('Não foi possível interpretar o parecer da prorrogação.');
        }

        return $this->normalizeResult($parsed, $report);
    }

    private function buildPrompt(string $report): string
    {
        return "Analise o contexto clínico abaixo e sugira o nível assistencial mais adequado para manutenção do cuidado.\n\n"
            . "Objetivo:\n"
            . "- Avaliar se há base para manter INTERNACAO, SEMI_UTI, UTI ou DESOSPITALIZACAO.\n"
            . "- Se não houver dados suficientes, use INDETERMINADO.\n\n"
            . "Regras:\n"
            . "- Baseie-se exclusivamente no texto fornecido.\n"
            . "- Nao invente sinais, exames, dispositivos ou diagnósticos.\n"
            . "- Considere gravidade clínica, estabilidade hemodinâmica, necessidade de monitorização, suporte ventilatório, complexidade terapêutica e potencial de transição de cuidado.\n"
            . "- Em DESOSPITALIZACAO, considere elegibilidade para alta, home care ou continuidade fora do ambiente hospitalar.\n\n"
            . "Retorne SOMENTE JSON válido com esta estrutura:\n"
            . "{\n"
            . "  \"nivel_recomendado\": \"INTERNACAO | SEMI_UTI | UTI | DESOSPITALIZACAO | INDETERMINADO\",\n"
            . "  \"resumo_clinico\": \"texto curto\",\n"
            . "  \"justificativa\": \"texto objetivo com racional clínico-assistencial\",\n"
            . "  \"sinais_favoraveis\": [\"item 1\", \"item 2\"],\n"
            . "  \"riscos_pendencias\": [\"item 1\", \"item 2\"],\n"
            . "  \"frase_final\": \"frase curta para destaque visual\"\n"
            . "}\n\n"
            . "CONTEXTO CLINICO:\n" . $report;
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
        return trim((string)($decoded['error']['message'] ?? $decoded['message'] ?? ''));
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
        $allowedLevels = ['INTERNACAO', 'SEMI_UTI', 'UTI', 'DESOSPITALIZACAO', 'INDETERMINADO'];
        $level = strtoupper(trim((string)($result['nivel_recomendado'] ?? 'INDETERMINADO')));
        if (!in_array($level, $allowedLevels, true)) {
            $level = 'INDETERMINADO';
        }

        $positives = [];
        foreach ((array)($result['sinais_favoraveis'] ?? []) as $item) {
            $item = trim((string)$item);
            if ($item !== '') {
                $positives[] = $item;
            }
        }

        $risks = [];
        foreach ((array)($result['riscos_pendencias'] ?? []) as $item) {
            $item = trim((string)$item);
            if ($item !== '') {
                $risks[] = $item;
            }
        }

        $finalPhrase = trim((string)($result['frase_final'] ?? ''));
        if ($finalPhrase === '') {
            $finalPhrase = $this->defaultFinalPhrase($level);
        }

        return [
            'nivel_recomendado' => $level,
            'resumo_clinico' => trim((string)($result['resumo_clinico'] ?? '')),
            'justificativa' => trim((string)($result['justificativa'] ?? '')),
            'sinais_favoraveis' => $positives,
            'riscos_pendencias' => $risks,
            'frase_final' => $finalPhrase,
            'meta' => [
                'model' => $this->model,
                'api_url' => $this->apiUrl,
                'report_chars' => strlen($report),
            ],
        ];
    }

    private function defaultFinalPhrase(string $level): string
    {
        if ($level === 'UTI') {
            return 'Parecer IA de prorrogação: há base para manutenção em UTI.';
        }
        if ($level === 'SEMI_UTI') {
            return 'Parecer IA de prorrogação: há base para manutenção em Semi-UTI.';
        }
        if ($level === 'INTERNACAO') {
            return 'Parecer IA de prorrogação: há base para manutenção em internação clínica.';
        }
        if ($level === 'DESOSPITALIZACAO') {
            return 'Parecer IA de prorrogação: há sinal favorável para desospitalização.';
        }

        return 'Parecer IA de prorrogação: não foi possível definir com segurança o melhor nível assistencial.';
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
