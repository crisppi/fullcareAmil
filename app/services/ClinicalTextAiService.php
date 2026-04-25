<?php

class ClinicalTextAiService
{
    private string $apiKey;
    private string $apiUrl;
    private string $model;

    public function __construct(?string $apiKey = null, ?string $apiUrl = null, ?string $model = null)
    {
        $this->loadEnvFile(dirname(__DIR__, 2) . '/.env');
        $this->apiKey = trim((string)($apiKey ?: getenv('MINHA_API_TOKEN') ?: ($_ENV['MINHA_API_TOKEN'] ?? '') ?: getenv('OPENAI_API_KEY') ?: ($_ENV['OPENAI_API_KEY'] ?? '')));
        $this->apiUrl = trim((string)($apiUrl ?: getenv('OPENAI_API_URL') ?: 'https://api.openai.com/v1/responses'));
        $this->model = trim((string)($model ?: getenv('CLINICAL_TEXT_OPENAI_MODEL') ?: getenv('OPENAI_MODEL') ?: 'gpt-4.1-mini'));
    }

    public function improve(string $text, string $fieldLabel): string
    {
        $text = trim($text);
        if ($text === '') {
            throw new InvalidArgumentException('Informe um texto para organizar.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Extensão cURL não disponível no servidor.');
        }
        if ($this->apiKey === '') {
            throw new RuntimeException('MINHA_API_TOKEN não configurada no ambiente.');
        }

        $prompt = "Organize o texto abaixo para registro de auditoria hospitalar em português-BR.\n"
            . "Campo: {$fieldLabel}\n\n"
            . "Regras obrigatórias:\n"
            . "- Não invente dados, valores, exames, sinais vitais, dispositivos, diagnósticos ou condutas.\n"
            . "- Preserve o sentido clínico original.\n"
            . "- Corrija apenas clareza, pontuação, redundância e organização.\n"
            . "- Se houver informações inconclusivas, mantenha como inconclusivas.\n"
            . "- Não use markdown.\n"
            . "- Retorne SOMENTE JSON válido com a chave texto.\n\n"
            . "TEXTO ORIGINAL:\n" . $text;

        $payload = [
            'model' => $this->model,
            'input' => [
                [
                    'role' => 'system',
                    'content' => [
                        ['type' => 'input_text', 'text' => 'Você revisa textos clínicos para auditoria sem acrescentar informação nova. Responda apenas JSON válido.'],
                    ],
                ],
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'input_text', 'text' => $prompt],
                    ],
                ],
            ],
            'temperature' => 0.1,
            'max_output_tokens' => 900,
        ];

        $raw = $this->request($payload);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Resposta inválida do serviço de IA.');
        }

        $responseText = $this->extractText($decoded);
        $parsed = $this->parseJsonResponse((string)$responseText);
        $improved = trim((string)($parsed['texto'] ?? ''));
        if ($improved === '') {
            throw new RuntimeException('A IA retornou texto vazio.');
        }

        return substr($improved, 0, 5000);
    }

    public function checklist(array $context): array
    {
        $report = trim((string)($context['relatorio'] ?? ''));
        $actions = trim((string)($context['acoes'] ?? ''));
        $plan = trim((string)($context['programacao'] ?? ''));
        $accommodation = trim((string)($context['acomodacao'] ?? ''));
        $fullText = trim($report . "\n" . $actions . "\n" . $plan);

        if ($fullText === '') {
            throw new InvalidArgumentException('Informe texto clínico para gerar o checklist.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Extensão cURL não disponível no servidor.');
        }
        if ($this->apiKey === '') {
            throw new RuntimeException('MINHA_API_TOKEN não configurada no ambiente.');
        }

        $prompt = "Avalie o registro de auditoria hospitalar e gere um checklist documental.\n\n"
            . "Acomodação informada: " . ($accommodation !== '' ? $accommodation : 'não informada') . "\n\n"
            . "Procure especificamente:\n"
            . "- ausência de sinais vitais ou estabilidade hemodinâmica;\n"
            . "- ausência de exames/laboratório/imagem quando relevantes;\n"
            . "- ausência de justificativa para permanência/internação;\n"
            . "- conflito entre acomodação e quadro clínico documentado;\n"
            . "- pendências de conduta, programação ou desospitalização.\n\n"
            . "Regras:\n"
            . "- Baseie-se exclusivamente no texto fornecido.\n"
            . "- Não invente dados ausentes.\n"
            . "- Seja objetivo e útil para o auditor revisar antes de salvar.\n"
            . "- Retorne SOMENTE JSON válido com a estrutura:\n"
            . "{ \"resumo\": \"texto curto\", \"itens\": [{\"status\":\"ok|atenção|pendente\", \"item\":\"texto curto\", \"detalhe\":\"texto curto\"}] }\n\n"
            . "RELATÓRIO:\n{$report}\n\nAÇÕES:\n{$actions}\n\nPROGRAMAÇÃO:\n{$plan}";

        $payload = [
            'model' => $this->model,
            'input' => [
                [
                    'role' => 'system',
                    'content' => [
                        ['type' => 'input_text', 'text' => 'Você é um checklist de auditoria médica. Não conclua além do que está documentado. Responda apenas JSON válido.'],
                    ],
                ],
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'input_text', 'text' => $prompt],
                    ],
                ],
            ],
            'temperature' => 0,
            'max_output_tokens' => 900,
        ];

        $raw = $this->request($payload);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Resposta inválida do serviço de IA.');
        }

        $responseText = $this->extractText($decoded);
        $parsed = $this->parseJsonResponse((string)$responseText);
        if (!is_array($parsed)) {
            throw new RuntimeException('Não foi possível interpretar o checklist.');
        }

        return $this->normalizeChecklist($parsed);
    }

    private function normalizeChecklist(array $parsed): array
    {
        $items = [];
        foreach ((array)($parsed['itens'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $status = mb_strtolower(trim((string)($item['status'] ?? 'pendente')), 'UTF-8');
            if (!in_array($status, ['ok', 'atenção', 'pendente'], true)) {
                $status = 'pendente';
            }
            $label = trim((string)($item['item'] ?? ''));
            $detail = trim((string)($item['detalhe'] ?? ''));
            if ($label !== '') {
                $items[] = [
                    'status' => $status,
                    'item' => mb_substr($label, 0, 140, 'UTF-8'),
                    'detalhe' => mb_substr($detail, 0, 240, 'UTF-8'),
                ];
            }
        }

        return [
            'resumo' => mb_substr(trim((string)($parsed['resumo'] ?? '')), 0, 240, 'UTF-8'),
            'itens' => array_slice($items, 0, 8),
        ];
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
            throw new RuntimeException('Serviço de IA indisponível no momento (HTTP ' . $httpCode . ').');
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

    private function parseJsonResponse(string $text): array
    {
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $clean = trim((string)preg_replace('/^```(?:json)?|```$/m', '', $text));
        $decoded = json_decode($clean, true);
        return is_array($decoded) ? $decoded : [];
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
