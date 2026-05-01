<?php
include_once("check_logado.php");
require_once("templates/header.php");

if (!isset($conn) || !($conn instanceof PDO)) {
    die("Conexao invalida.");
}

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function fmtMoney($value): string
{
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function fmtInt($value): string
{
    return number_format((int)$value, 0, ',', '.');
}

function hasSinistroData(array $sinistro): bool
{
    return ($sinistro['valor_apresentado'] ?? 0) > 0
        || ($sinistro['valor_final'] ?? 0) > 0
        || ($sinistro['valor_glosa'] ?? 0) > 0;
}

function hasInternaçãoData(array $internacao): bool
{
    return ($internacao['total_internacoes'] ?? 0) > 0
        || ($internacao['total_diarias'] ?? 0) > 0;
}

function hasUtiData(array $uti): bool
{
    return ($uti['total_internacoes'] ?? 0) > 0
        || ($uti['total_diarias'] ?? 0) > 0;
}

function pctOrNull($current, $previous): ?float
{
    if ($previous <= 0) {
        return null;
    }
    return (($current - $previous) / $previous) * 100;
}

function fmtPct($value, int $decimals = 1): string
{
    return number_format((float)$value, $decimals, ',', '.') . '%';
}

function trendLabel(?float $variation, string $up = 'aumento', string $down = 'reducao'): string
{
    if ($variation === null) {
        return 'estabilidade';
    }
    return $variation >= 0 ? $up : $down;
}

function variationBadgeClass(?float $variation): string
{
    if ($variation === null) {
        return 'neutral';
    }
    if ($variation > 0) {
        return 'up';
    }
    if ($variation < 0) {
        return 'down';
    }
    return 'neutral';
}

function variationLabel(?float $variation, int $decimals = 1): string
{
    if ($variation === null) {
        return 'Sem base comparativa';
    }
    $signal = $variation > 0 ? '+' : '';
    return $signal . number_format($variation, $decimals, ',', '.') . '% vs. ano anterior';
}

function buildSelfUrlWithQuery(array $overrides = []): string
{
    $query = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '') {
            unset($query[$k]);
            continue;
        }
        $query[$k] = $v;
    }
    $basePath = strtok((string)($_SERVER['REQUEST_URI'] ?? ''), '?');
    if (!$basePath) {
        $basePath = '/bi/inteligencia';
    }
    $qs = http_build_query($query);
    return $basePath . ($qs !== '' ? ('?' . $qs) : '');
}

function aiExtractText(array $responseJson): ?string
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
    if (!$parts) {
        return null;
    }
    return trim(implode("\n", $parts));
}

function gerarNarrativaGerencialIA(array $indicadores, ?string &$erro = null): ?array
{
    $erro = null;
    if (!function_exists('curl_init')) {
        $erro = 'Extensão cURL não disponível no servidor.';
        return null;
    }
    $apiKey = getenv('MINHA_API_TOKEN') ?: ($_ENV['MINHA_API_TOKEN'] ?? '') ?: getenv('OPENAI_API_KEY') ?: ($_ENV['OPENAI_API_KEY'] ?? '');
    if (!$apiKey) {
        $erro = 'MINHA_API_TOKEN não configurada no ambiente.';
        return null;
    }

    $apiUrl = getenv('OPENAI_API_URL') ?: 'https://api.openai.com/v1/responses';
    $model = getenv('OPENAI_MODEL') ?: 'gpt-4.1-mini';

    $prompt = "Gere narrativa gerencial em português-BR, objetiva e executiva.\n"
        . "Retorne SOMENTE JSON válido com as chaves: financeiro e produtividade.\n"
        . "Cada chave deve conter texto com 2 a 3 parágrafos, sem markdown.\n"
        . "Use apenas os dados fornecidos, sem inventar valores.\n\n"
        . "DADOS (JSON):\n" . json_encode($indicadores, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    $payload = [
        'model' => $model,
        'input' => [
            [
                'role' => 'system',
                'content' => [
                    ['type' => 'input_text', 'text' => 'Você é um redator executivo de saúde suplementar. Seja factual e preciso.'],
                ],
            ],
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'input_text', 'text' => $prompt],
                ],
            ],
        ],
        'temperature' => 0.2,
        'max_output_tokens' => 900,
    ];

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
    ]);

    $raw = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $curlErr) {
        $erro = 'Falha de conexão com serviço de IA.';
        return null;
    }
    if ($httpCode < 200 || $httpCode >= 300) {
        $erro = 'Serviço de IA indisponível no momento.';
        return null;
    }

    $json = json_decode($raw, true);
    if (!is_array($json)) {
        $erro = 'Resposta inválida da IA.';
        return null;
    }

    $text = aiExtractText($json);
    if (!$text) {
        $erro = 'IA retornou resposta vazia.';
        return null;
    }

    $decoded = json_decode($text, true);
    if (!is_array($decoded)) {
        // tentativa de limpar bloco markdown
        $text = trim(preg_replace('/^```(?:json)?|```$/m', '', $text));
        $decoded = json_decode($text, true);
    }
    if (!is_array($decoded)) {
        $erro = 'Não foi possível interpretar a narrativa da IA.';
        return null;
    }

    $financeiro = trim((string)($decoded['financeiro'] ?? ''));
    $produtividade = trim((string)($decoded['produtividade'] ?? ''));
    if ($financeiro === '' || $produtividade === '') {
        $erro = 'IA retornou conteúdo incompleto.';
        return null;
    }

    return [
        'financeiro' => $financeiro,
        'produtividade' => $produtividade,
    ];
}

$ano = (int)(filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT) ?: date('Y'));
$hospitalId = filter_input(INPUT_GET, 'hospital_id', FILTER_VALIDATE_INT) ?: null;
$tipoAdmissão = trim((string)(filter_input(INPUT_GET, 'tipo_admissao') ?? ''));
$relatorioModo = strtolower(trim((string)(filter_input(INPUT_GET, 'relatorio_modo') ?? 'padrao')));
if (!in_array($relatorioModo, ['padrao', 'ia'], true)) {
    $relatorioModo = 'padrao';
}

$hospitais = $conn->query("SELECT id_hospital, nome_hosp FROM tb_hospital ORDER BY nome_hosp")
    ->fetchAll(PDO::FETCH_ASSOC);
$tiposAdm = $conn->query("SELECT DISTINCT tipo_admissao_int FROM tb_internacao WHERE tipo_admissao_int IS NOT NULL AND tipo_admissao_int <> '' ORDER BY tipo_admissao_int")
    ->fetchAll(PDO::FETCH_COLUMN);

function sinistroTotals(PDO $conn, int $ano, ?int $hospitalId, string $tipoAdmissão): array
{
    $dateExpr = "COALESCE(NULLIF(ca.data_inicial_capeante,'0000-00-00'), NULLIF(ca.data_digit_capeante,'0000-00-00'), NULLIF(ca.data_fech_capeante,'0000-00-00'))";
    $where = "ref_date IS NOT NULL AND ref_date <> '0000-00-00' AND YEAR(ref_date) = :ano";
    $params = [':ano' => $ano];
    if ($hospitalId) {
        $where .= " AND fk_hospital_int = :hospital_id";
        $params[':hospital_id'] = $hospitalId;
    }
    if ($tipoAdmissão !== '') {
        $where .= " AND tipo_admissao_int = :tipo";
        $params[':tipo'] = $tipoAdmissão;
    }

    $sql = "
        SELECT
            SUM(valor_apresentado_capeante) AS valor_apresentado,
            SUM(valor_glosa_total) AS valor_glosa,
            SUM(valor_glosa_med) AS valor_glosa_med,
            SUM(valor_glosa_enf) AS valor_glosa_enf,
            SUM(valor_final_capeante) AS valor_final
        FROM (
            SELECT
                ca.valor_apresentado_capeante,
                ca.valor_glosa_total,
                ca.valor_glosa_med,
                ca.valor_glosa_enf,
                ca.valor_final_capeante,
                {$dateExpr} AS ref_date,
                ac.fk_hospital_int,
                ac.tipo_admissao_int
            FROM tb_capeante ca
            INNER JOIN tb_internacao ac ON ac.id_internacao = ca.fk_int_capeante
        ) t
        WHERE {$where}
    ";

    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'valor_apresentado' => (float)($row['valor_apresentado'] ?? 0),
        'valor_glosa' => (float)($row['valor_glosa'] ?? 0),
        'valor_glosa_med' => (float)($row['valor_glosa_med'] ?? 0),
        'valor_glosa_enf' => (float)($row['valor_glosa_enf'] ?? 0),
        'valor_final' => (float)($row['valor_final'] ?? 0),
    ];
}

function internacaoStats(PDO $conn, int $ano, ?int $hospitalId, string $tipoAdmissão): array
{
    $where = "YEAR(i.data_intern_int) = :ano";
    $params = [':ano' => $ano];
    if ($hospitalId) {
        $where .= " AND i.fk_hospital_int = :hospital_id";
        $params[':hospital_id'] = $hospitalId;
    }
    if ($tipoAdmissão !== '') {
        $where .= " AND i.tipo_admissao_int = :tipo";
        $params[':tipo'] = $tipoAdmissão;
    }

    $sql = "
        SELECT
            COUNT(*) AS total_internacoes,
            SUM(GREATEST(1, DATEDIFF(COALESCE(al.data_alta_alt, CURDATE()), i.data_intern_int) + 1)) AS total_diarias
        FROM tb_internacao i
        LEFT JOIN (
            SELECT fk_id_int_alt, MAX(data_alta_alt) AS data_alta_alt
            FROM tb_alta
            GROUP BY fk_id_int_alt
        ) al ON al.fk_id_int_alt = i.id_internacao
        WHERE {$where}
    ";
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $totalInternações = (int)($row['total_internacoes'] ?? 0);
    $totalDiárias = (int)($row['total_diarias'] ?? 0);
    $mp = $totalInternações > 0 ? round($totalDiárias / $totalInternações, 1) : 0.0;

    return [
        'total_internacoes' => $totalInternações,
        'total_diarias' => $totalDiárias,
        'mp' => $mp,
    ];
}

function utiStats(PDO $conn, int $ano, ?int $hospitalId, string $tipoAdmissão): array
{
    $where = "YEAR(data_intern_int) = :ano";
    $params = [':ano' => $ano];
    if ($hospitalId) {
        $where .= " AND fk_hospital_int = :hospital_id";
        $params[':hospital_id'] = $hospitalId;
    }
    if ($tipoAdmissão !== '') {
        $where .= " AND tipo_admissao_int = :tipo";
        $params[':tipo'] = $tipoAdmissão;
    }

    $sql = "
        SELECT
            COUNT(*) AS total_internacoes_uti,
            SUM(GREATEST(1, DATEDIFF(COALESCE(max_data_alta, CURDATE()), min_data_internacao) + 1)) AS total_diarias_uti
        FROM (
            SELECT
                u.fk_internacao_uti,
                MIN(NULLIF(u.data_internacao_uti, '0000-00-00')) AS min_data_internacao,
                MAX(NULLIF(u.data_alta_uti, '0000-00-00')) AS max_data_alta,
                i.fk_hospital_int,
                i.tipo_admissao_int,
                i.data_intern_int
            FROM tb_uti u
            INNER JOIN tb_internacao i ON i.id_internacao = u.fk_internacao_uti
            WHERE u.data_internacao_uti IS NOT NULL AND u.data_internacao_uti <> '0000-00-00'
            GROUP BY u.fk_internacao_uti, i.fk_hospital_int, i.tipo_admissao_int, i.data_intern_int
        ) t
        WHERE {$where}
    ";

    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $totalInternações = (int)($row['total_internacoes_uti'] ?? 0);
    $totalDiárias = (int)($row['total_diarias_uti'] ?? 0);
    $mp = $totalInternações > 0 ? round($totalDiárias / $totalInternações, 1) : 0.0;

    return [
        'total_internacoes' => $totalInternações,
        'total_diarias' => $totalDiárias,
        'mp' => $mp,
    ];
}

function financeiroGerencialStats(PDO $conn, int $ano, ?int $hospitalId, string $tipoAdmissão): array
{
    $dateExpr = "COALESCE(NULLIF(ca.data_inicial_capeante,'0000-00-00'), NULLIF(ca.data_digit_capeante,'0000-00-00'), NULLIF(ca.data_fech_capeante,'0000-00-00'))";
    $where = "ref_date IS NOT NULL AND ref_date <> '0000-00-00' AND YEAR(ref_date) = :ano";
    $params = [':ano' => $ano];
    if ($hospitalId) {
        $where .= " AND fk_hospital_int = :hospital_id";
        $params[':hospital_id'] = $hospitalId;
    }
    if ($tipoAdmissão !== '') {
        $where .= " AND tipo_admissao_int = :tipo";
        $params[':tipo'] = $tipoAdmissão;
    }

    $sql = "
        SELECT
            COUNT(*) AS total_contas,
            SUM(COALESCE(valor_apresentado_capeante,0)) AS valor_apresentado,
            SUM(COALESCE(valor_final_capeante,0)) AS valor_final,
            SUM(COALESCE(valor_glosa_total,0)) AS valor_glosa,
            SUM(COALESCE(valor_glosa_med,0)) AS valor_glosa_med,
            SUM(COALESCE(valor_glosa_enf,0)) AS valor_glosa_enf
        FROM (
            SELECT
                ca.valor_apresentado_capeante,
                ca.valor_final_capeante,
                ca.valor_glosa_total,
                ca.valor_glosa_med,
                ca.valor_glosa_enf,
                {$dateExpr} AS ref_date,
                i.fk_hospital_int,
                i.tipo_admissao_int
            FROM tb_capeante ca
            INNER JOIN tb_internacao i ON i.id_internacao = ca.fk_int_capeante
        ) t
        WHERE {$where}
    ";

    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $totalContas = (int)($row['total_contas'] ?? 0);
    $valorApresentado = (float)($row['valor_apresentado'] ?? 0);
    $ticketMedio = $totalContas > 0 ? ($valorApresentado / $totalContas) : 0.0;

    return [
        'total_contas' => $totalContas,
        'valor_apresentado' => $valorApresentado,
        'valor_final' => (float)($row['valor_final'] ?? 0),
        'valor_glosa' => (float)($row['valor_glosa'] ?? 0),
        'valor_glosa_med' => (float)($row['valor_glosa_med'] ?? 0),
        'valor_glosa_enf' => (float)($row['valor_glosa_enf'] ?? 0),
        'ticket_medio' => $ticketMedio,
    ];
}

function produtividadeGerencialStats(PDO $conn, int $ano, ?int $hospitalId, string $tipoAdmissão): array
{
    $where = "YEAR(v.data_visita_vis) = :ano";
    $params = [':ano' => $ano];
    if ($hospitalId) {
        $where .= " AND i.fk_hospital_int = :hospital_id";
        $params[':hospital_id'] = $hospitalId;
    }
    if ($tipoAdmissão !== '') {
        $where .= " AND i.tipo_admissao_int = :tipo";
        $params[':tipo'] = $tipoAdmissão;
    }

    $sql = "
        SELECT
            COUNT(*) AS total_visitas,
            COUNT(DISTINCT DATE(v.data_visita_vis)) AS dias_com_visita,
            COUNT(DISTINCT v.fk_internacao_vis) AS internacoes_visitadas,
            COUNT(DISTINCT COALESCE(NULLIF(v.fk_usuario_vis, 0), NULL)) AS auditores_ativos
        FROM tb_visita v
        INNER JOIN tb_internacao i ON i.id_internacao = v.fk_internacao_vis
        WHERE {$where}
    ";
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $totalVisitas = (int)($row['total_visitas'] ?? 0);
    $diasComVisita = (int)($row['dias_com_visita'] ?? 0);
    $mediaVisitasDia = $diasComVisita > 0 ? round($totalVisitas / $diasComVisita, 2) : 0.0;

    return [
        'total_visitas' => $totalVisitas,
        'dias_com_visita' => $diasComVisita,
        'internacoes_visitadas' => (int)($row['internacoes_visitadas'] ?? 0),
        'auditores_ativos' => (int)($row['auditores_ativos'] ?? 0),
        'media_visitas_dia' => $mediaVisitasDia,
    ];
}

$sinistroAtual = sinistroTotals($conn, $ano, $hospitalId, $tipoAdmissão);
$sinistroPrev = sinistroTotals($conn, $ano - 1, $hospitalId, $tipoAdmissão);

$internacaoAtual = internacaoStats($conn, $ano, $hospitalId, $tipoAdmissão);
$internacaoPrev = internacaoStats($conn, $ano - 1, $hospitalId, $tipoAdmissão);

$utiAtual = utiStats($conn, $ano, $hospitalId, $tipoAdmissão);
$utiPrev = utiStats($conn, $ano - 1, $hospitalId, $tipoAdmissão);

$financeiroGerAtual = financeiroGerencialStats($conn, $ano, $hospitalId, $tipoAdmissão);
$financeiroGerPrev = financeiroGerencialStats($conn, $ano - 1, $hospitalId, $tipoAdmissão);
$produtividadeGerAtual = produtividadeGerencialStats($conn, $ano, $hospitalId, $tipoAdmissão);
$produtividadeGerPrev = produtividadeGerencialStats($conn, $ano - 1, $hospitalId, $tipoAdmissão);

$glosaPct = $sinistroAtual['valor_apresentado'] > 0
    ? ($sinistroAtual['valor_glosa'] / $sinistroAtual['valor_apresentado']) * 100
    : 0.0;

$apresentadoVar = pctOrNull($sinistroAtual['valor_apresentado'], $sinistroPrev['valor_apresentado']);
$internacoesVar = pctOrNull($internacaoAtual['total_internacoes'], $internacaoPrev['total_internacoes']);
$diariasVar = pctOrNull($internacaoAtual['total_diarias'], $internacaoPrev['total_diarias']);
$utiVar = pctOrNull($utiAtual['total_internacoes'], $utiPrev['total_internacoes']);
$financeiroContasVar = pctOrNull($financeiroGerAtual['total_contas'], $financeiroGerPrev['total_contas']);
$financeiroApresentadoVar = pctOrNull($financeiroGerAtual['valor_apresentado'], $financeiroGerPrev['valor_apresentado']);
$financeiroFinalVar = pctOrNull($financeiroGerAtual['valor_final'], $financeiroGerPrev['valor_final']);
$prodVisitasVar = pctOrNull($produtividadeGerAtual['total_visitas'], $produtividadeGerPrev['total_visitas']);
$prodMediaDiaVar = pctOrNull($produtividadeGerAtual['media_visitas_dia'], $produtividadeGerPrev['media_visitas_dia']);

$aproveitamentoFinanceiroPct = $financeiroGerAtual['valor_apresentado'] > 0
    ? ($financeiroGerAtual['valor_final'] / $financeiroGerAtual['valor_apresentado']) * 100
    : 0.0;
$custoMedioDiariaGeral = $internacaoAtual['total_diarias'] > 0
    ? ($financeiroGerAtual['valor_apresentado'] / $internacaoAtual['total_diarias'])
    : 0.0;
$custoMedioDiariaUti = $utiAtual['total_diarias'] > 0
    ? ($financeiroGerAtual['valor_apresentado'] / $utiAtual['total_diarias'])
    : 0.0;
$utiParticipacaoInternacoesPct = $internacaoAtual['total_internacoes'] > 0
    ? ($utiAtual['total_internacoes'] / $internacaoAtual['total_internacoes']) * 100
    : 0.0;
$utiParticipacaoDiariasPct = $internacaoAtual['total_diarias'] > 0
    ? ($utiAtual['total_diarias'] / $internacaoAtual['total_diarias']) * 100
    : 0.0;
$visitasPorInternacao = $produtividadeGerAtual['internacoes_visitadas'] > 0
    ? ($produtividadeGerAtual['total_visitas'] / $produtividadeGerAtual['internacoes_visitadas'])
    : 0.0;
$visitasPorAuditor = $produtividadeGerAtual['auditores_ativos'] > 0
    ? ($produtividadeGerAtual['total_visitas'] / $produtividadeGerAtual['auditores_ativos'])
    : 0.0;
$glosaMedShare = $financeiroGerAtual['valor_glosa'] > 0
    ? ($financeiroGerAtual['valor_glosa_med'] / $financeiroGerAtual['valor_glosa']) * 100
    : 0.0;
$glosaEnfShare = $financeiroGerAtual['valor_glosa'] > 0
    ? ($financeiroGerAtual['valor_glosa_enf'] / $financeiroGerAtual['valor_glosa']) * 100
    : 0.0;

$hospitalNome = 'Todos Hospitais';
if ($hospitalId) {
    foreach ($hospitais as $h) {
        if ((int)$h['id_hospital'] === (int)$hospitalId) {
            $hospitalNome = $h['nome_hosp'];
            break;
        }
    }
}
$tipoLabel = $tipoAdmissão !== '' ? $tipoAdmissão : 'Todos';

$temSinistro = hasSinistroData($sinistroAtual);
$temInternação = hasInternaçãoData($internacaoAtual);
$temUti = hasUtiData($utiAtual);
$temAlgum = $temSinistro || $temInternação || $temUti;
$selfReportUrl = $_SERVER['REQUEST_URI'] ?? (rtrim($BASE_URL, '/') . '/bi/inteligencia');
$selfReportUrl = strtok((string)$selfReportUrl, '#') ?: (rtrim($BASE_URL, '/') . '/bi/inteligencia');

$iaNarrativa = null;
$iaErro = null;
if ($relatorioModo === 'ia') {
    $iaNarrativa = gerarNarrativaGerencialIA([
        'contexto' => [
            'ano' => $ano,
            'hospital' => $hospitalNome,
            'tipo_admissao' => $tipoLabel,
        ],
        'financeiro' => [
            'atual' => $financeiroGerAtual,
            'anterior' => $financeiroGerPrev,
            'variacao_contas_pct' => $financeiroContasVar,
            'variacao_apresentado_pct' => $financeiroApresentadoVar,
        ],
        'produtividade' => [
            'atual' => $produtividadeGerAtual,
            'anterior' => $produtividadeGerPrev,
            'variacao_visitas_pct' => $prodVisitasVar,
            'variacao_media_dia_pct' => $prodMediaDiaVar,
        ],
    ], $iaErro);
}

$modoPadraoUrl = buildSelfUrlWithQuery(['relatorio_modo' => 'padrao']);
$modoIaUrl = buildSelfUrlWithQuery(['relatorio_modo' => 'ia']);

$resumoExecutivo = [];
$resumoExecutivo[] = 'No recorte de ' . $ano . ', o painel consolidou ' . fmtInt($internacaoAtual['total_internacoes']) . ' internações, '
    . fmtInt($internacaoAtual['total_diarias']) . ' diárias e permanência média de '
    . number_format($internacaoAtual['mp'], 1, ',', '.') . ' dias para ' . $hospitalNome . '.';
$resumoExecutivo[] = 'No financeiro, foram auditadas ' . fmtInt($financeiroGerAtual['total_contas']) . ' contas, com valor apresentado de '
    . fmtMoney($financeiroGerAtual['valor_apresentado']) . ', valor final de ' . fmtMoney($financeiroGerAtual['valor_final'])
    . ' e glosa consolidada de ' . fmtMoney($financeiroGerAtual['valor_glosa']) . '.';
$resumoExecutivo[] = 'Em produtividade, a operação registrou ' . fmtInt($produtividadeGerAtual['total_visitas']) . ' visitas, média de '
    . number_format($produtividadeGerAtual['media_visitas_dia'], 2, ',', '.') . ' visitas por dia produtivo e '
    . number_format($visitasPorAuditor, 2, ',', '.') . ' visitas por auditor ativo.';

$alertasExecutivos = [];
$alertasExecutivos[] = [
    'tone' => $glosaPct >= 5 ? 'attention' : 'positive',
    'title' => 'Eficiência financeira',
    'detail' => $glosaPct >= 5
        ? 'A glosa de ' . fmtPct($glosaPct, 2) . ' sugere oportunidade de reforço em qualidade documental e negociação técnica.'
        : 'A glosa de ' . fmtPct($glosaPct, 2) . ' sinaliza boa aderência entre conta apresentada, documentação e auditoria.',
];
$alertasExecutivos[] = [
    'tone' => ($diariasVar !== null && $internacoesVar !== null && $diariasVar > $internacoesVar) ? 'attention' : 'neutral',
    'title' => 'Pressão assistencial',
    'detail' => ($diariasVar !== null && $internacoesVar !== null && $diariasVar > $internacoesVar)
        ? 'As diárias cresceram acima do volume de internações, sugerindo mudança de case mix ou alongamento de permanência.'
        : 'A relação entre internações e diárias está mais alinhada ao volume assistencial do período.',
];
$alertasExecutivos[] = [
    'tone' => $utiParticipacaoInternacoesPct >= 25 ? 'attention' : 'neutral',
    'title' => 'Gravidade e UTI',
    'detail' => 'A participação da UTI foi de ' . fmtPct($utiParticipacaoInternacoesPct, 1) . ' das internações e '
        . fmtPct($utiParticipacaoDiariasPct, 1) . ' das diárias do período.',
];
$alertasExecutivos[] = [
    'tone' => ($prodMediaDiaVar !== null && $prodMediaDiaVar < 0) ? 'attention' : 'positive',
    'title' => 'Capacidade operacional',
    'detail' => ($prodMediaDiaVar !== null && $prodMediaDiaVar < 0)
        ? 'A produtividade média diária recuou frente ao ano anterior e merece acompanhamento de capacidade e carteira.'
        : 'A produtividade média diária sustenta leitura favorável para acompanhamento da carteira e dimensionamento da equipe.',
];

$kpiCards = [
    [
        'label' => 'Internações',
        'value' => fmtInt($internacaoAtual['total_internacoes']),
        'meta' => 'Ano ' . $ano,
        'variation' => $internacoesVar,
    ],
    [
        'label' => 'MP Geral',
        'value' => number_format($internacaoAtual['mp'], 1, ',', '.') . ' dias',
        'meta' => fmtInt($internacaoAtual['total_diarias']) . ' diárias',
        'variation' => $diariasVar,
    ],
    [
        'label' => 'Valor Apresentado',
        'value' => fmtMoney($financeiroGerAtual['valor_apresentado']),
        'meta' => fmtInt($financeiroGerAtual['total_contas']) . ' contas',
        'variation' => $financeiroApresentadoVar,
    ],
    [
        'label' => 'Valor Final',
        'value' => fmtMoney($financeiroGerAtual['valor_final']),
        'meta' => 'Aproveitamento ' . fmtPct($aproveitamentoFinanceiroPct, 1),
        'variation' => $financeiroFinalVar,
    ],
    [
        'label' => 'Participação UTI',
        'value' => fmtPct($utiParticipacaoInternacoesPct, 1),
        'meta' => number_format($utiAtual['mp'], 1, ',', '.') . ' dias de MP UTI',
        'variation' => $utiVar,
    ],
    [
        'label' => 'Visitas',
        'value' => fmtInt($produtividadeGerAtual['total_visitas']),
        'meta' => number_format($produtividadeGerAtual['media_visitas_dia'], 2, ',', '.') . ' por dia',
        'variation' => $prodVisitasVar,
    ],
];

?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260501">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260501"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme'));</script>
<style>
    .bi-report-title {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: 12px;
        align-items: flex-end;
        margin-bottom: 18px;
    }
    .bi-report-title h3 {
        margin: 0;
    }
    .bi-report-meta-stack {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    .bi-report-meta-stack span {
        padding: 8px 12px;
        border-radius: 999px;
        background: rgba(41, 83, 116, .10);
        color: #41586c;
        font-size: .84rem;
    }
    .bi-section-anchor-nav {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 16px;
    }
    .bi-section-anchor-nav a {
        padding: 9px 12px;
        border-radius: 10px;
        background: rgba(41, 83, 116, .08);
        color: #26435a;
        text-decoration: none;
        font-size: .84rem;
    }
    .bi-report-intro {
        margin-top: 16px;
        padding: 18px 20px;
        border-radius: 14px;
        background: rgba(41, 83, 116, .06);
        border: 1px solid rgba(41, 83, 116, .12);
        color: #243746;
        line-height: 1.7;
    }
    .bi-report-section {
        margin-top: 18px;
        padding: 0;
        border-radius: 0;
        background: transparent;
        border: 0;
        box-shadow: none;
    }
    .bi-report-section h4 {
        display: block;
        margin-bottom: 10px;
        font-size: 1.04rem;
        color: #1a2a35;
    }
    .bi-report-section p {
        color: #243746;
        line-height: 1.82;
        margin-bottom: 14px;
    }
    .bi-report-section p:last-child {
        margin-bottom: 0;
    }
    .bi-report-section + .bi-report-section {
        border-top: 1px solid rgba(255,255,255,.08);
        padding-top: 18px;
    }
    .bi-report-submeta {
        margin-top: 10px;
        color: #5a7286;
        font-size: .84rem;
    }
    .bi-report-highlight {
        color: #1b4f72;
        font-weight: 700;
    }
    @media (max-width: 991.98px) {
        .bi-report-title {
            align-items: flex-start;
        }
    }
</style>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Relatório Executivo com Inteligência Artificial</h1>
        <div class="bi-header-actions">
            <div class="text-end text-muted"></div>
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegação">
                <i class="bi bi-grid-3x3-gap"></i>
            </a>
        </div>
    </div>

    <form class="bi-panel bi-filters" method="get">
        <input type="hidden" name="relatorio_modo" value="<?= e($relatorioModo) ?>">
        <div class="bi-filter">
            <label>Ano</label>
            <input type="number" name="ano" value="<?= e($ano) ?>">
        </div>
        <div class="bi-filter">
            <label>Hospital</label>
            <select name="hospital_id">
                <option value="">Todos</option>
                <?php foreach ($hospitais as $h): ?>
                    <option value="<?= (int)$h['id_hospital'] ?>" <?= $hospitalId == $h['id_hospital'] ? 'selected' : '' ?>>
                        <?= e($h['nome_hosp']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-filter">
            <label>Tipo admissão</label>
            <select name="tipo_admissao">
                <option value="">Todos</option>
                <?php foreach ($tiposAdm as $tipo): ?>
                    <option value="<?= e($tipo) ?>" <?= $tipoAdmissão === $tipo ? 'selected' : '' ?>>
                        <?= e($tipo) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="bi-actions">
            <button class="bi-btn" type="submit">Aplicar</button>
        </div>
    </form>

    <div class="bi-panel" style="margin-top:16px;">
        <div class="text-white-50" style="font-size:.92rem;">
            Relatório textual automático com base em internações, permanência, UTI, custos, glosas e produtividade do recorte selecionado.
        </div>
        <div class="d-flex flex-wrap" style="gap:10px;margin-top:18px;">
            <a class="bi-btn <?= $relatorioModo === 'padrao' ? '' : 'bi-btn-secondary' ?>" href="<?= e($modoPadraoUrl) ?>#relatorio-executivo">
                Usar texto padrão
            </a>
            <a class="bi-btn <?= $relatorioModo === 'ia' ? '' : 'bi-btn-secondary' ?>" href="<?= e($modoIaUrl) ?>#relatorio-executivo">
                Gerar texto com IA
            </a>
            <a class="bi-btn bi-btn-secondary" href="<?= e($selfReportUrl) ?>#relatorio-gerencial-financeiro">
                Financeiro
            </a>
            <a class="bi-btn bi-btn-secondary" href="<?= e($selfReportUrl) ?>#relatorio-gerencial-produtividade">
                Produtividade
            </a>
        </div>
        <?php if ($relatorioModo === 'ia' && $iaNarrativa === null && $iaErro): ?>
            <div class="alert alert-warning mt-3 mb-0" role="alert">
                IA indisponível: <?= e($iaErro) ?>. Exibindo texto padrão.
            </div>
        <?php endif; ?>
    </div>

    <div class="bi-panel bi-report" style="margin-top:16px;">
        <div class="bi-report-title">
            <div>
                <h3>Relatório Anual de Sinistralidade Hospitalar - <?= e($hospitalNome) ?> - (<?= e($ano) ?>)</h3>
                <div class="bi-report-submeta">Tipo admissão: <?= e($tipoLabel) ?></div>
            </div>
            <div class="bi-report-meta-stack">
                <span>Modo: <?= e(strtoupper($relatorioModo)) ?></span>
                <span><?= fmtInt($internacaoAtual['total_internacoes']) ?> internações</span>
                <span><?= fmtInt($financeiroGerAtual['total_contas']) ?> contas</span>
            </div>
        </div>
        <div class="bi-section-anchor-nav">
            <a href="#relatorio-gerencial-financeiro">Financeiro</a>
            <a href="#relatorio-gerencial-produtividade">Produtividade</a>
        </div>
        <div class="bi-report-intro" id="relatorio-executivo">
            <?php foreach ($resumoExecutivo as $paragrafo): ?>
                <p><?= e($paragrafo) ?></p>
            <?php endforeach; ?>
            <p style="margin-bottom:0;">
                Principais leituras do recorte:
                <?php foreach ($alertasExecutivos as $index => $alerta): ?>
                    <span class="bi-report-highlight"><?= e($alerta['title']) ?></span>: <?= e($alerta['detail']) ?><?= $index < count($alertasExecutivos) - 1 ? ' ' : '' ?>
                <?php endforeach; ?>
            </p>
        </div>

        <?php if (!$temAlgum): ?>
            <p>Sem dados para o recorte selecionado.</p>
        <?php endif; ?>

        <?php if ($temSinistro): ?>
        <div class="bi-report-section">
            <h4>1. Analise de Contas Apresentadas</h4>
            <p>
                O valor total das contas apresentadas no ano foi de <strong><?= fmtMoney($sinistroAtual['valor_apresentado']) ?></strong>.
                <?php if ($sinistroPrev['valor_apresentado'] > 0): ?>
                    Em relacao a <?= e($ano - 1) ?> (<?= fmtMoney($sinistroPrev['valor_apresentado']) ?>), houve
                    <?= trendLabel($apresentadoVar, 'aumento', 'reducao') ?>
                    de <strong><?= fmtPct(abs($apresentadoVar ?? 0), 1) ?></strong>.
                <?php else: ?>
                    Nao ha base comparativa em <?= e($ano - 1) ?> para este recorte.
                <?php endif; ?>
            </p>
            <p>
                A base auditada contempla <strong><?= fmtInt($financeiroGerAtual['total_contas']) ?></strong> contas no período, com
                ticket médio de apresentação em <strong><?= fmtMoney($financeiroGerAtual['ticket_medio']) ?></strong>.
                Em termos de intensidade financeira por permanência, o custo médio apresentado por diária geral foi de
                <strong><?= fmtMoney($custoMedioDiariaGeral) ?></strong>.
            </p>
            <p>
                Leitura executiva: o movimento de contas apresentadas indica
                <strong><?= trendLabel($financeiroContasVar, 'expansao', 'retracao') ?></strong> do volume faturado no recorte selecionado,
                com impacto direto na pressão de auditoria e no planejamento de capacidade das equipes.
            </p>
        </div>

        <div class="bi-report-section">
            <h4>2. Resultado Final e Oportunidade de Glosa</h4>
            <p>
                Apos ajustes e auditorias, o valor final consolidado foi de <strong><?= fmtMoney($sinistroAtual['valor_final']) ?></strong>.
                A oportunidade de glosa somou <strong><?= fmtMoney($sinistroAtual['valor_glosa']) ?></strong>,
                representando <strong><?= number_format($glosaPct, 2, ',', '.') ?>%</strong> do valor apresentado.
            </p>
            <p class="bi-report-list">
                Glosa medica: <strong><?= fmtMoney($sinistroAtual['valor_glosa_med']) ?></strong> | 
                Glosa de enfermagem: <strong><?= fmtMoney($sinistroAtual['valor_glosa_enf']) ?></strong>
            </p>
            <p>
                O índice de aproveitamento financeiro (valor final sobre valor apresentado) ficou em
                <strong><?= fmtPct($aproveitamentoFinanceiroPct, 2) ?></strong>.
                Na composição da glosa, a participação foi de <strong><?= fmtPct($glosaMedShare, 2) ?></strong> para frente médica e
                <strong><?= fmtPct($glosaEnfShare, 2) ?></strong> para enfermagem.
            </p>
            <p>
                Leitura executiva: o patamar de glosa observado sinaliza
                <?= $glosaPct <= 2 ? '<strong>boa aderência documental e assistencial</strong>' : '<strong>oportunidade de reforço em qualidade de registro e negociação</strong>' ?>,
                com potencial de melhorar resultado sem reduzir acesso assistencial.
            </p>
        </div>
        <?php endif; ?>

        <?php if ($temInternação): ?>
        <div class="bi-report-section">
            <h4>3. Internações Gerais</h4>
            <p>
                O total de internacoes registradas foi de <strong><?= fmtInt($internacaoAtual['total_internacoes']) ?></strong>,
                com <strong><?= fmtInt($internacaoAtual['total_diarias']) ?></strong> diarias e MP de
                <strong><?= number_format($internacaoAtual['mp'], 1, ',', '.') ?> dias</strong>.
            </p>
            <p>
                <?php if ($internacaoPrev['total_internacoes'] > 0): ?>
                    Em relacao a <?= e($ano - 1) ?>, a variacao foi de
                    <strong><?= number_format(abs($internacoesVar ?? 0), 1, ',', '.') ?>%</strong> nas internacoes e
                    <strong><?= number_format(abs($diariasVar ?? 0), 1, ',', '.') ?>%</strong> nas diarias.
                <?php else: ?>
                    Nao ha historico comparativo para internacoes no ano anterior.
                <?php endif; ?>
            </p>
            <p>
                A relação entre volume e permanência mantém a operação sob monitoramento de uso de leito:
                cada internação consumiu em média <strong><?= number_format($internacaoAtual['mp'], 1, ',', '.') ?> dias</strong>,
                com custo apresentado estimado em <strong><?= fmtMoney($custoMedioDiariaGeral) ?></strong> por diária.
            </p>
            <p>
                Leitura executiva: variações simultâneas de internações e diárias sugerem
                <?= ($internacoesVar !== null && $diariasVar !== null && abs($diariasVar) > abs($internacoesVar))
                    ? '<strong>mudança de case mix/perfil de permanência</strong>'
                    : '<strong>mudança principalmente de volume assistencial</strong>' ?>,
                demandando acompanhamento conjunto de desfecho, permanência e custo.
            </p>
        </div>
        <?php endif; ?>

        <?php if ($temUti): ?>
        <div class="bi-report-section">
            <h4>4. Internações em UTI</h4>
            <p>
                Foram registradas <strong><?= fmtInt($utiAtual['total_internacoes']) ?></strong> internacoes em UTI,
                com <strong><?= fmtInt($utiAtual['total_diarias']) ?></strong> diarias e MP UTI de
                <strong><?= number_format($utiAtual['mp'], 1, ',', '.') ?> dias</strong>.
            </p>
            <p>
                <?php if ($utiPrev['total_internacoes'] > 0): ?>
                    A variacao frente a <?= e($ano - 1) ?> foi de
                    <strong><?= number_format(abs($utiVar ?? 0), 1, ',', '.') ?>%</strong> no volume de internacoes em UTI.
                <?php else: ?>
                    Nao ha historico comparativo de UTI no ano anterior para este recorte.
                <?php endif; ?>
            </p>
            <p>
                A UTI representou <strong><?= fmtPct($utiParticipacaoInternacoesPct, 1) ?></strong> das internações e
                <strong><?= fmtPct($utiParticipacaoDiariasPct, 1) ?></strong> das diárias no período.
                O custo apresentado por diária UTI (proxy do recorte atual) foi de <strong><?= fmtMoney($custoMedioDiariaUti) ?></strong>.
            </p>
            <p>
                Leitura executiva: a participação de UTI e a MP UTI de
                <strong><?= number_format($utiAtual['mp'], 1, ',', '.') ?> dias</strong> devem ser acompanhadas como indicadores críticos
                de gravidade clínica, pressão de custo e risco operacional.
            </p>
        </div>
        <?php endif; ?>

        <div class="bi-report-section" id="relatorio-gerencial-financeiro">
            <h4>5. Relatório Gerencial Financeiro</h4>
            <?php if ($relatorioModo === 'ia' && $iaNarrativa !== null): ?>
                <p style="white-space: pre-line;"><?= nl2br(e($iaNarrativa['financeiro'])) ?></p>
            <?php else: ?>
                <p>
                    No recorte selecionado, foram analisadas <strong><?= fmtInt($financeiroGerAtual['total_contas']) ?></strong> contas,
                    com valor apresentado de <strong><?= fmtMoney($financeiroGerAtual['valor_apresentado']) ?></strong> e
                    valor final de <strong><?= fmtMoney($financeiroGerAtual['valor_final']) ?></strong>.
                </p>
                <p>
                    A glosa total foi de <strong><?= fmtMoney($financeiroGerAtual['valor_glosa']) ?></strong>,
                    sendo glosa médica de <strong><?= fmtMoney($financeiroGerAtual['valor_glosa_med']) ?></strong> e
                    glosa de enfermagem de <strong><?= fmtMoney($financeiroGerAtual['valor_glosa_enf']) ?></strong>.
                    O ticket médio por conta ficou em <strong><?= fmtMoney($financeiroGerAtual['ticket_medio']) ?></strong>.
                </p>
                <p>
                    <?php if ($financeiroGerPrev['total_contas'] > 0 || $financeiroGerPrev['valor_apresentado'] > 0): ?>
                        Em relação a <?= e($ano - 1) ?>, houve <strong><?= trendLabel($financeiroContasVar, 'aumento', 'reducao') ?></strong> de
                        <strong><?= fmtPct(abs($financeiroContasVar ?? 0), 1) ?></strong> no total de contas,
                        <strong><?= trendLabel($financeiroApresentadoVar, 'aumento', 'reducao') ?></strong> de
                        <strong><?= fmtPct(abs($financeiroApresentadoVar ?? 0), 1) ?></strong> no valor apresentado e
                        <strong><?= trendLabel($financeiroFinalVar, 'aumento', 'reducao') ?></strong> de
                        <strong><?= fmtPct(abs($financeiroFinalVar ?? 0), 1) ?></strong> no valor final.
                    <?php else: ?>
                        Não há base comparativa suficiente em <?= e($ano - 1) ?> para o relatório financeiro gerencial.
                    <?php endif; ?>
                </p>
                <p>
                    Síntese gerencial: a performance financeira combina volume auditado, eficácia de glosa e conversão em valor final.
                    O foco recomendado é preservar o equilíbrio entre sustentabilidade econômica e fluidez assistencial.
                </p>
            <?php endif; ?>
        </div>

        <div class="bi-report-section" id="relatorio-gerencial-produtividade">
            <h4>6. Relatório Gerencial de Produtividade</h4>
            <?php if ($relatorioModo === 'ia' && $iaNarrativa !== null): ?>
                <p style="white-space: pre-line;"><?= nl2br(e($iaNarrativa['produtividade'])) ?></p>
            <?php else: ?>
                <p>
                    Foram registradas <strong><?= fmtInt($produtividadeGerAtual['total_visitas']) ?></strong> visitas no período,
                    distribuídas em <strong><?= fmtInt($produtividadeGerAtual['dias_com_visita']) ?></strong> dias com produção.
                    A média diária foi de <strong><?= number_format($produtividadeGerAtual['media_visitas_dia'], 2, ',', '.') ?></strong> visitas/dia.
                </p>
                <p>
                    As visitas cobriram <strong><?= fmtInt($produtividadeGerAtual['internacoes_visitadas']) ?></strong> internações,
                    com <strong><?= fmtInt($produtividadeGerAtual['auditores_ativos']) ?></strong> auditores ativos no lançamento.
                </p>
                <p>
                    <?php if ($produtividadeGerPrev['total_visitas'] > 0 || $produtividadeGerPrev['media_visitas_dia'] > 0): ?>
                        Comparado a <?= e($ano - 1) ?>, houve variação de
                        <strong><?= number_format(abs($prodVisitasVar ?? 0), 1, ',', '.') ?>%</strong> no volume de visitas e
                        <strong><?= number_format(abs($prodMediaDiaVar ?? 0), 1, ',', '.') ?>%</strong> na produtividade média diária.
                    <?php else: ?>
                        Não há base comparativa suficiente em <?= e($ano - 1) ?> para o relatório de produtividade.
                    <?php endif; ?>
                </p>
                <p>
                    Indicadores operacionais complementares: média de <strong><?= number_format($visitasPorInternacao, 2, ',', '.') ?></strong>
                    visitas por internação acompanhada e <strong><?= number_format($visitasPorAuditor, 2, ',', '.') ?></strong> visitas por auditor no período.
                    Esses números apoiam decisões de dimensionamento de equipe e priorização de carteira.
                </p>
                <p>
                    Síntese gerencial: produtividade deve ser lida em conjunto com qualidade do registro, tempo de resposta e desfecho,
                    evitando otimização apenas de volume.
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
