<?php
if (!isset($BASE_URL)) {
    $BASE_URL = '';
}

$biSections = [
    'Resumo' => [
        ['label' => 'Consolidado', 'href' => 'bi/consolidado', 'file' => 'ConsolidadoGestaoBI.php'],
        ['label' => 'Consolidado Cards', 'href' => 'bi/consolidado-cards', 'file' => 'ConsolidadoGestaoCardsBI.php'],
        ['label' => 'Indicadores Essenciais', 'href' => 'IndicadoresEssenciaisHubBI.php', 'file' => 'IndicadoresEssenciaisHubBI.php'],
        ['label' => 'Indicadores BI', 'href' => 'bi/indicadores', 'file' => 'Indicadores.php'],
    ],
    'Indicadores Essenciais' => [
        ['label' => 'Hub Essenciais', 'href' => 'IndicadoresEssenciaisHubBI.php', 'file' => 'IndicadoresEssenciaisHubBI.php'],
        ['label' => 'Contas Auditadas por Hospital', 'href' => 'IndicadoresEssenciaisItemBI.php?slug=contas-auditadas-hospital', 'file' => 'IndicadoresEssenciaisItemBI.php'],
        ['label' => 'Custo Mensal por Hospital', 'href' => 'IndicadoresEssenciaisItemBI.php?slug=custo-mensal-hospital', 'file' => 'IndicadoresEssenciaisItemBI.php'],
        ['label' => 'Glosa por Hospital', 'href' => 'IndicadoresEssenciaisItemBI.php?slug=glosa-hospital', 'file' => 'IndicadoresEssenciaisItemBI.php'],
        ['label' => 'Contas Auditadas por Auditor', 'href' => 'IndicadoresEssenciaisItemBI.php?slug=contas-auditadas-auditor', 'file' => 'IndicadoresEssenciaisItemBI.php'],
        ['label' => 'Glosa por Auditor', 'href' => 'IndicadoresEssenciaisItemBI.php?slug=glosa-auditor', 'file' => 'IndicadoresEssenciaisItemBI.php'],
        ['label' => 'Saving por Hospital', 'href' => 'IndicadoresEssenciaisItemBI.php?slug=saving-hospital', 'file' => 'IndicadoresEssenciaisItemBI.php'],
        ['label' => 'Saving por Auditor', 'href' => 'IndicadoresEssenciaisItemBI.php?slug=saving-auditor', 'file' => 'IndicadoresEssenciaisItemBI.php'],
        ['label' => 'Custo por Patologia', 'href' => 'IndicadoresEssenciaisItemBI.php?slug=custo-patologia', 'file' => 'IndicadoresEssenciaisItemBI.php'],
        ['label' => 'Custo por Antecedente', 'href' => 'IndicadoresEssenciaisItemBI.php?slug=custo-antecedente', 'file' => 'IndicadoresEssenciaisItemBI.php'],
        ['label' => 'Custo por UTI', 'href' => 'IndicadoresEssenciaisItemBI.php?slug=custo-uti', 'file' => 'IndicadoresEssenciaisItemBI.php'],
        ['label' => '% Internacao UTI', 'href' => 'IndicadoresEssenciaisItemBI.php?slug=percentual-internacao-uti', 'file' => 'IndicadoresEssenciaisItemBI.php'],
        ['label' => 'Eventos Adversos por Hospital', 'href' => 'IndicadoresEssenciaisItemBI.php?slug=eventos-adversos-hospital', 'file' => 'IndicadoresEssenciaisItemBI.php'],
        ['label' => 'Obitos por Hospital', 'href' => 'IndicadoresEssenciaisItemBI.php?slug=obitos-hospital', 'file' => 'IndicadoresEssenciaisItemBI.php'],
        ['label' => 'Qualidade Hospitalar', 'href' => 'IndicadoresEssenciaisItemBI.php?slug=qualidade-hospital', 'file' => 'IndicadoresEssenciaisItemBI.php'],
    ],
    'Clínico' => [
        ['label' => 'Patologia', 'href' => 'bi/patologia', 'file' => 'bi_patologia.php'],
        ['label' => 'Grupo Patologia', 'href' => 'bi/grupo-patologia', 'file' => 'GrupoPatologia.php'],
        ['label' => 'Antecedente', 'href' => 'bi/antecedente', 'file' => 'Antecedente.php'],
        ['label' => 'UTI', 'href' => 'bi/uti', 'file' => 'bi_uti.php'],
        ['label' => 'UTI Suporte', 'href' => 'bi/uti-suporte', 'file' => 'UtiSuporteBI.php'],
        ['label' => 'Tipo Internação', 'href' => 'bi/tipo-internacao', 'file' => 'TipoInternacaoBI.php'],
        ['label' => 'Longa Permanência', 'href' => 'bi/longa-permanencia', 'file' => 'LongaPermanenciaBI.php'],
        ['label' => 'Evolução', 'href' => 'bi/evolucao', 'file' => 'bi_evolucao.php'],
        ['label' => 'Visita Inicial', 'href' => 'bi/visita-inicial', 'file' => 'bi_visita_inicial.php'],
        ['label' => 'Estratégia Terapêutica', 'href' => 'bi/estrategia-terapeutica', 'file' => 'EstrategiaTerapeuticaBI.php'],
        ['label' => 'Prorrogações', 'href' => 'bi/prorrogacoes', 'file' => 'BiOperacionalAvancado.php'],
        ['label' => 'Oportunidades Clínicas', 'href' => 'bi/oportunidades-clinicas', 'file' => 'BiOperacionalAvancado.php'],
        ['label' => 'Desfechos e Alta', 'href' => 'bi/desfechos-alta', 'file' => 'BiOperacionalAvancado.php'],
        ['label' => 'Detalhes Clínicos', 'href' => 'bi/detalhes-clinicos', 'file' => 'DetalhesClinicosBI.php'],
        ['label' => 'Evento Adverso', 'href' => 'bi/evento-adverso', 'file' => 'EventoAdverso.php'],
        ['label' => 'Segurança e Eventos Abertos', 'href' => 'bi/seguranca-eventos', 'file' => 'BiOperacionalAvancado.php'],
    ],
    'Auditoria' => [
        ['label' => 'Auditor', 'href' => 'bi/auditor', 'file' => 'AuditorBI.php'],
        ['label' => 'Auditor Visitas', 'href' => 'bi/auditor-visitas', 'file' => 'AuditorVisitasBI.php'],
        ['label' => 'Auditoria Produtividade', 'href' => 'bi/auditoria-produtividade', 'file' => 'AuditoriaProdutividadeBI.php'],
        ['label' => 'Análise Negociações', 'href' => 'bi/analise-negociacoes', 'file' => 'bi_analise_negociacoes.php'],
        ['label' => 'Negociações Detalhadas', 'href' => 'bi/negociacoes-detalhadas', 'file' => 'bi_negociacoes_detalhadas.php'],
        ['label' => 'Negociação Avançada', 'href' => 'bi/negociacao-avancada', 'file' => 'BiOperacionalAvancado.php'],
        ['label' => 'Saving por Auditor', 'href' => 'bi/saving-por-auditor', 'file' => 'bi_saving_por_auditor.php'],
        ['label' => 'Saving', 'href' => 'bi/saving', 'file' => 'bi_saving.php'],
    ],
    'Operacional' => [
        ['label' => 'Seguradora', 'href' => 'bi/seguradora', 'file' => 'SeguradoraBI.php'],
        ['label' => 'Seguradora Detalhado', 'href' => 'bi/seguradora-detalhado', 'file' => 'SeguradoraDetalhadoBI.php'],
        ['label' => 'Performance Rede Hospitalar', 'href' => 'bi/performance-rede-hospitalar', 'file' => 'bi_performance_rede_hospitalar.php'],
        ['label' => 'Alto Custo', 'href' => 'bi/alto-custo', 'file' => 'AltoCusto.php'],
        ['label' => 'Internações com Risco', 'href' => 'bi/internacoes-risco', 'file' => 'InternacoesRiscoBI.php'],
        ['label' => 'Qualidade e Gestão', 'href' => 'bi/qualidade-gestao', 'file' => 'QualidadeGestaoBI.php'],
        ['label' => 'Home Care', 'href' => 'bi/home-care', 'file' => 'HomeCare.php'],
        ['label' => 'Desospitalização', 'href' => 'bi/desospitalizacao', 'file' => 'Desospitalizacao.php'],
        ['label' => 'OPME', 'href' => 'bi/opme', 'file' => 'Opme.php'],
        ['label' => 'TUSS / Autorizações', 'href' => 'bi/tuss-autorizacoes', 'file' => 'BiOperacionalAvancado.php'],
    ],
    'Rede Hospitalar' => [
        ['label' => 'Comparativa', 'href' => 'bi/rede-comparativa', 'file' => 'bi_rede_comparativa.php'],
        ['label' => 'Custo por hospital', 'href' => 'bi/rede-custo', 'file' => 'bi_rede_custo.php'],
        ['label' => 'Glosa por hospital', 'href' => 'bi/rede-glosa', 'file' => 'bi_rede_glosa.php'],
        ['label' => 'Contas paradas', 'href' => 'bi/rede-rejeicao-capeante', 'file' => 'bi_rede_rejeicao_capeante.php'],
        ['label' => 'Permanência média', 'href' => 'bi/rede-permanencia', 'file' => 'bi_rede_permanencia.php'],
        ['label' => 'Eventos adversos', 'href' => 'bi/rede-eventos-adversos', 'file' => 'bi_rede_eventos_adversos.php'],
        ['label' => 'Readmissão 30d', 'href' => 'bi/rede-readmissao', 'file' => 'bi_rede_readmissao.php'],
        ['label' => 'Ranking', 'href' => 'bi/rede-ranking', 'file' => 'bi_rede_ranking.php'],
    ],
    'Financeiro' => [
        ['label' => 'Sinistro', 'href' => 'bi/sinistro', 'file' => 'Sinistro.php'],
        ['label' => 'Perfil Sinistro', 'href' => 'bi/perfil-sinistro', 'file' => 'bi_perfil_sinistro.php'],
        ['label' => 'Sinistro YTD', 'href' => 'bi/sinistro-ytd', 'file' => 'bi_sinistro_ytd.php'],
        ['label' => 'Financeiro Realizado', 'href' => 'bi/financeiro-realizado', 'file' => 'FinanceiroRealizadoBI.php'],
        ['label' => 'Produção', 'href' => 'bi/producao', 'file' => 'Producao.php'],
        ['label' => 'Produção YTD', 'href' => 'bi/producao-ytd', 'file' => 'bi_producao_ytd.php'],
        ['label' => 'Pacientes', 'href' => 'bi/pacientes', 'file' => 'bi_pacientes.php'],
        ['label' => 'Hospitais', 'href' => 'bi/hospitais', 'file' => 'bi_hospitais.php'],
        ['label' => 'Inteligência Artificial', 'href' => 'bi/inteligencia', 'file' => 'bi_inteligencia.php'],
        ['label' => 'Sinistro BI', 'href' => 'bi/sinistro-bi', 'file' => 'bi_sinistro.php'],
    ],
    'Tops' => [
        ['label' => 'Hospitais', 'href' => 'bi/tops-hospitais', 'file' => 'bi_ranking_hospitais.php'],
        ['label' => 'Pacientes', 'href' => 'bi/tops-pacientes', 'file' => 'bi_ranking_pacientes.php'],
        ['label' => 'Patologia', 'href' => 'bi/tops-patologia', 'file' => 'bi_ranking_patologia.php'],
    ],
    'Faturamento' => [
        ['label' => 'Visitas', 'href' => 'bi/faturamento-visitas', 'file' => 'faturamento_visitas.php'],
        ['label' => 'Consolidado', 'href' => 'bi/faturamento-consolidado', 'file' => 'bi_faturamento_consolidado.php'],
    ],
    'Controle de Gastos' => [
        ['label' => 'Sinistralidade por Patologia', 'href' => 'bi/gastos-patologia', 'file' => 'ControleGastosPatologiaBI.php'],
        ['label' => 'Sinistralidade por Hospital', 'href' => 'bi/gastos-hospital', 'file' => 'ControleGastosHospitalBI.php'],
        ['label' => 'Tendência de Custo', 'href' => 'bi/gastos-tendencia', 'file' => 'ControleGastosTendenciaBI.php'],
        ['label' => 'Análise de Alto Custo', 'href' => 'bi/gastos-alto-custo', 'file' => 'ControleGastosAltoCustoBI.php'],
        ['label' => 'Custo Evitável', 'href' => 'bi/gastos-custo-evitavel', 'file' => 'ControleGastosCustoEvitavelBI.php'],
        ['label' => 'Concentração de Risco', 'href' => 'bi/gastos-concentracao', 'file' => 'ControleGastosConcentracaoBI.php'],
        ['label' => 'Provisão vs Realizado', 'href' => 'bi/gastos-provisao-realizado', 'file' => 'ControleGastosProvisaoRealizadoBI.php'],
        ['label' => 'Custo Médio Diárias', 'href' => 'bi/custo-medio-diarias', 'file' => 'CustoMedioDiariasBI.php'],
        ['label' => 'Ranking Patologia', 'href' => 'bi/ranking-patologia', 'file' => 'RankingPatologiaBI.php'],
        ['label' => 'Ranking Hospitais', 'href' => 'bi/ranking-hospitais', 'file' => 'RankingHospitaisBI.php'],
        ['label' => 'Ranking Pacientes', 'href' => 'bi/ranking-pacientes', 'file' => 'RankingPacientesBI.php'],
    ],
    'Anomalias & Fraude' => [
        ['label' => 'Outliers de Permanência', 'href' => 'bi/anomalias-permanencia', 'file' => 'AnomaliasPermanenciaBI.php'],
        ['label' => 'Padrão de Negociação', 'href' => 'bi/anomalias-negociacao', 'file' => 'AnomaliasNegociacaoBI.php'],
        ['label' => 'OPME sem Justificativa', 'href' => 'bi/anomalias-opme', 'file' => 'AnomaliasOPMEBI.php'],
    ],
    'Conformidade & Auditoria' => [
        ['label' => 'Documentação Completa', 'href' => 'bi/auditoria-documentacao', 'file' => 'AuditoriaDocumentacaoBI.php'],
        ['label' => 'Tempo de Resposta', 'href' => 'bi/auditoria-resposta', 'file' => 'AuditoriaTempoRespostaBI.php'],
    ],
    'Segmentação de Risco' => [
        ['label' => 'Pacientes Crônicos', 'href' => 'bi/risco-cronicos', 'file' => 'RiscoCronicosBI.php'],
        ['label' => 'Risco Readmissão', 'href' => 'bi/risco-readmissao', 'file' => 'RiscoReadmissaoBI.php'],
        ['label' => 'Casos Caros Previsíveis', 'href' => 'bi/risco-casos-caros', 'file' => 'RiscoCasosCarosBI.php'],
    ],
    'Risco & Prevenção' => [
        ['label' => 'Matriz de Risco', 'href' => 'bi/risco-prevencao-matriz', 'file' => 'RiscoPrevencaoMatrizBI.php'],
        ['label' => 'Preditores', 'href' => 'bi/risco-prevencao-preditores', 'file' => 'RiscoPrevencaoPreditoresBI.php'],
        ['label' => 'Eventos Adversos', 'href' => 'bi/risco-prevencao-eventos', 'file' => 'RiscoPrevencaoEventosBI.php'],
        ['label' => 'Desospitalização Precoce', 'href' => 'bi/risco-prevencao-desospitalizacao', 'file' => 'RiscoPrevencaoDesospitalizacaoBI.php'],
        ['label' => 'Score por Internação', 'href' => 'bi/risco-prevencao-score', 'file' => 'RiscoPrevencaoScoreBI.php'],
    ],
    'Negociação & Rede' => [
        ['label' => 'Volume vs Custo', 'href' => 'bi/rede-volume-custo', 'file' => 'RedeVolumeCustoBI.php'],
        ['label' => 'Mix de Casos', 'href' => 'bi/rede-mix-casos', 'file' => 'RedeMixCasosBI.php'],
        ['label' => 'Elasticidade de Preço', 'href' => 'bi/rede-elasticidade', 'file' => 'RedeElasticidadeBI.php'],
    ],
    'Qualidade & Desfecho' => [
        ['label' => 'Eventos Adversos', 'href' => 'bi/qualidade-eventos', 'file' => 'QualidadeEventosBI.php'],
        ['label' => 'Óbitos', 'href' => 'QualidadeObitosBI.php', 'file' => 'QualidadeObitosBI.php'],
    ],
];

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$currentUri = (string)($_SERVER['REQUEST_URI'] ?? '');
$currentPath = trim((string) parse_url($currentUri, PHP_URL_PATH), '/');
$currentQuery = (string) parse_url($currentUri, PHP_URL_QUERY);
$basePath = trim((string) parse_url($BASE_URL ?? '', PHP_URL_PATH), '/');
if ($basePath !== '' && strpos($currentPath, $basePath) === 0) {
    $currentPath = trim(substr($currentPath, strlen($basePath)), '/');
}
$currentRoute = $currentPath . ($currentQuery !== '' ? '?' . $currentQuery : '');
$currentSection = '';
$currentLabel = '';
$flatPages = [];
$matchedByHref = false;
$ieSlug = trim((string)($_GET['ie'] ?? ''));
$ieMap = [
    'contas-auditadas-hospital' => 'Contas Auditadas por Hospital',
    'custo-mensal-hospital' => 'Custo Mensal por Hospital',
    'glosa-hospital' => 'Glosa por Hospital',
    'contas-auditadas-auditor' => 'Contas Auditadas por Auditor',
    'glosa-auditor' => 'Glosa por Auditor',
    'saving-hospital' => 'Saving por Hospital',
    'saving-auditor' => 'Saving por Auditor',
    'custo-patologia' => 'Custo por Patologia',
    'custo-antecedente' => 'Custo por Antecedente',
    'custo-uti' => 'Custo por UTI',
    'percentual-internacao-uti' => '% Internacao UTI',
    'eventos-adversos-hospital' => 'Eventos Adversos por Hospital',
    'obitos-hospital' => 'Obitos por Hospital',
    'qualidade-hospital' => 'Qualidade Hospitalar',
];

foreach ($biSections as $section => $items) {
    foreach ($items as $item) {
        $file = $item['file'] ?? $item['href'];
        $flatPages[] = $file;
        $hrefPath = trim((string) ($item['href'] ?? ''), '/');
        if ($hrefPath !== '' && ($hrefPath === $currentPath || $hrefPath === $currentRoute)) {
            $currentSection = $section;
            $currentLabel = $item['label'];
            $matchedByHref = true;
        }
        if (!$matchedByHref && $file === $currentPage) {
            $currentSection = $section;
            $currentLabel = $item['label'];
        }
    }
}

if ($ieSlug !== '' && isset($ieMap[$ieSlug]) && isset($biSections['Indicadores Essenciais'])) {
    $currentSection = 'Indicadores Essenciais';
    $currentLabel = $ieMap[$ieSlug];
}

if (!in_array($currentPage, $flatPages, true) && !$matchedByHref && !($ieSlug !== '' && isset($ieMap[$ieSlug]))) {
    return;
}

$isBiTopbarItemActive = static function (array $item) use ($currentPath, $currentRoute, $currentPage, $currentSection, $ieSlug): bool {
    $itemFile = (string)($item['file'] ?? ($item['href'] ?? ''));
    $href = (string)($item['href'] ?? '');
    $hrefTarget = trim($href, '/');
    $hrefPath = trim((string)parse_url($href, PHP_URL_PATH), '/');
    $hrefQuery = parse_url($href, PHP_URL_QUERY);

    if ($hrefTarget !== '' && ($hrefTarget === $currentRoute || $hrefTarget === $currentPath)) {
        return true;
    }

    if ($hrefQuery === null && $hrefPath !== '' && $hrefPath === $currentPath) {
        return true;
    }

    if ($hrefTarget === '' && $itemFile === $currentPage) {
        return true;
    }

    if ($currentSection === 'Indicadores Essenciais' && $ieSlug !== '') {
        if (strpos($hrefTarget, 'bi/indicadores-essenciais/' . $ieSlug) === 0) {
            return true;
        }

        $qp = [];
        if (is_string($hrefQuery) && $hrefQuery !== '') {
            parse_str($hrefQuery, $qp);
        }

        $itemSlug = trim((string)($qp['slug'] ?? ''));
        if ($itemSlug !== '' && $itemSlug === $ieSlug) {
            return true;
        }
    }

    return false;
};
?>

<style>
:root {
    --bi-sidebar-top: 76px;
    --bi-sidebar-width: 308px;
    --bi-sidebar-collapsed-width: 84px;
}

body.bi-theme {
    transition: padding-left .22s ease;
    padding-left: var(--bi-sidebar-width);
}

body.bi-theme.bi-nav-collapsed {
    padding-left: var(--bi-sidebar-collapsed-width);
}

.bi-side-toggle {
    position: fixed;
    left: calc(var(--bi-sidebar-width) - 40px);
    top: calc(var(--bi-sidebar-top) + 4px);
    z-index: 1202;
    width: 36px;
    height: 36px;
    border: 1px solid rgba(255, 255, 255, 0.22);
    border-radius: 12px;
    background: linear-gradient(135deg, rgba(18, 47, 76, 0.96), rgba(28, 78, 118, 0.96));
    color: #eff8ff;
    box-shadow: 0 10px 26px rgba(8, 28, 45, 0.35);
    transition: left .22s ease, transform .15s ease;
}

body.bi-theme.bi-nav-collapsed .bi-side-toggle {
    left: calc((var(--bi-sidebar-collapsed-width) / 2) - 20px);
}

.bi-side-toggle:hover {
    transform: translateY(-1px);
}

.bi-sidebar-shell {
    position: fixed;
    left: 0;
    top: var(--bi-sidebar-top);
    bottom: 0;
    width: var(--bi-sidebar-width);
    z-index: 1200;
    display: flex;
    flex-direction: column;
    background:
        radial-gradient(circle at top left, rgba(96, 200, 215, 0.18), transparent 38%),
        linear-gradient(180deg, rgba(10, 42, 70, 0.98), rgba(9, 29, 48, 0.98));
    border-right: 1px solid rgba(255, 255, 255, 0.12);
    box-shadow: 16px 0 36px rgba(5, 20, 34, 0.28);
    transition: width .22s ease, transform .22s ease;
    overflow: hidden;
}

body.bi-theme.bi-nav-collapsed .bi-sidebar-shell {
    width: var(--bi-sidebar-collapsed-width);
}

.bi-sidebar-shell::before {
    content: "";
    display: block;
    height: 6px;
    background: linear-gradient(90deg, #2f6fa0, #58c7cf, #8f67db);
}

.bi-sidebar-head {
    padding: 16px 16px 12px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.bi-topbar-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.64rem;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: rgba(225, 241, 255, 0.62);
    font-weight: 700;
}

.bi-crumb {
    margin-top: 8px;
    color: #f1f8ff;
    font-weight: 600;
    font-size: 0.86rem;
    line-height: 1.35;
}

.bi-crumb span {
    color: rgba(225, 241, 255, 0.44);
    font-weight: 600;
    margin: 0 6px;
}

.bi-sidebar-navlink {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: 12px;
    padding: 9px 12px;
    border-radius: 12px;
    background: linear-gradient(135deg, #5a79ff, #3c56d6);
    border: 1px solid rgba(77, 104, 228, 0.9);
    color: #ffffff;
    text-decoration: none;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.bi-sidebar-navlink.is-active {
    background: linear-gradient(135deg, #4b5fd6, #2c3fb6);
}

.bi-sidebar-search-trigger {
    display: none;
    align-items: center;
    justify-content: center;
    margin-top: 10px;
    width: 42px;
    height: 42px;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.12);
    background: rgba(255, 255, 255, 0.06);
    color: #edf4ff;
    cursor: pointer;
    transition: background .15s ease, border-color .15s ease, transform .15s ease;
}

.bi-sidebar-search-trigger:hover {
    background: rgba(255, 255, 255, 0.10);
    border-color: rgba(255, 255, 255, 0.22);
    transform: translateY(-1px);
}

.bi-sidebar-search {
    margin-top: 10px;
}

.bi-sidebar-search-box {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 12px;
    height: 38px;
    border-radius: 10px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(255, 255, 255, 0.04);
    transition: border-color .15s ease, background .15s ease, box-shadow .15s ease;
}

.bi-sidebar-search-box:focus-within {
    border-color: rgba(99, 213, 192, 0.45);
    background: rgba(255, 255, 255, 0.06);
    box-shadow: 0 0 0 3px rgba(99, 213, 192, 0.12);
}

.bi-sidebar-search-box i {
    color: rgba(237, 244, 255, 0.62);
    font-size: 0.76rem;
}

.bi-sidebar-search-input {
    width: 100%;
    border: 0;
    outline: none;
    background: transparent;
    color: #edf4ff;
    font-size: 0.76rem;
}

.bi-sidebar-search-input::placeholder {
    color: rgba(237, 244, 255, 0.42);
}

.bi-sidebar-search-meta {
    min-height: 16px;
    margin-top: 6px;
    color: rgba(237, 244, 255, 0.5);
    font-size: 0.66rem;
    text-align: center;
}

.bi-sidebar-body {
    flex: 1 1 auto;
    overflow-y: auto;
    padding: 12px 12px 132px;
}

.bi-sidebar-body::-webkit-scrollbar {
    width: 8px;
}

.bi-sidebar-body::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 999px;
}

.bi-sidebar-group {
    border: 1px solid rgba(255, 255, 255, 0.09);
    border-radius: 16px;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.065), rgba(255, 255, 255, 0.038));
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
    overflow: hidden;
}

.bi-sidebar-group.is-search-hidden,
.bi-sidebar-link.is-search-hidden {
    display: none !important;
}

.bi-sidebar-group + .bi-sidebar-group {
    margin-top: 10px;
}

.bi-sidebar-group summary {
    list-style: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    color: #f6fbff;
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.01em;
    transition: background .15s ease, color .15s ease;
}

.bi-sidebar-group summary::-webkit-details-marker {
    display: none;
}

.bi-sidebar-group[open] summary,
.bi-sidebar-group summary:hover {
    background: rgba(255, 255, 255, 0.085);
    color: #ffffff;
}

.bi-sidebar-dot {
    flex: 0 0 10px;
    width: 10px;
    height: 10px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.2);
    box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.035);
}

.bi-sidebar-group.is-active .bi-sidebar-dot {
    background: #63d5c0;
}

.bi-sidebar-chevron {
    margin-left: auto;
    font-size: 0.8rem;
    color: rgba(237, 247, 255, 0.54);
    transition: transform .15s ease;
}

.bi-sidebar-group[open] .bi-sidebar-chevron {
    transform: rotate(90deg);
}

.bi-sidebar-links {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 4px 10px 10px;
    background: rgba(5, 18, 32, 0.18);
}

.bi-sidebar-link {
    display: flex;
    align-items: center;
    min-height: 42px;
    padding: 8px 12px 8px 16px;
    border-radius: 12px;
    color: rgba(214, 227, 239, 0.9);
    text-decoration: none;
    font-size: 0.74rem;
    font-weight: 500;
    line-height: 1.25;
    border: 1px solid rgba(255, 255, 255, 0.03);
    background: rgba(255, 255, 255, 0.03);
}

.bi-sidebar-link:hover {
    background: rgba(255, 255, 255, 0.07);
    border-color: rgba(255, 255, 255, 0.08);
    color: #ffffff;
}

.bi-sidebar-link.is-active {
    background: linear-gradient(135deg, #63d5c0, #2fa38c);
    border-color: rgba(60, 160, 140, 0.9);
    color: #0f2a25;
    font-weight: 700;
    box-shadow: 0 10px 20px rgba(23, 103, 94, 0.28);
}

.bi-sidebar-foot {
    padding: 12px 12px 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(6, 20, 34, 0.42);
    position: sticky;
    bottom: 0;
    z-index: 2;
    backdrop-filter: blur(10px);
    min-height: 82px;
}

.bi-topbar-select {
    width: 100%;
    border-radius: 12px;
    padding: 10px 12px;
    border: 1px solid rgba(255, 255, 255, 0.14);
    font-size: 0.76rem;
    color: #f1f8ff;
    background: rgba(255, 255, 255, 0.08);
    min-height: 48px;
}

.bi-topbar-select option,
.bi-topbar-select optgroup {
    color: #1f2d3a;
}

body.bi-theme.bi-nav-collapsed .bi-crumb,
body.bi-theme.bi-nav-collapsed .bi-sidebar-navlink,
body.bi-theme.bi-nav-collapsed .bi-sidebar-search,
body.bi-theme.bi-nav-collapsed .bi-sidebar-links,
body.bi-theme.bi-nav-collapsed .bi-sidebar-foot,
body.bi-theme.bi-nav-collapsed .bi-sidebar-chevron,
body.bi-theme.bi-nav-collapsed .bi-topbar-title span,
body.bi-theme.bi-nav-collapsed .bi-sidebar-group summary span {
    opacity: 0;
    pointer-events: none;
}

body.bi-theme.bi-nav-collapsed .bi-sidebar-search-trigger {
    display: inline-flex;
}

body.bi-theme.bi-nav-collapsed .bi-sidebar-head {
    padding-left: 8px;
    padding-right: 8px;
}

body.bi-theme.bi-nav-collapsed .bi-sidebar-group {
    border-radius: 14px;
}

body.bi-theme.bi-nav-collapsed .bi-sidebar-group summary {
    justify-content: center;
    padding: 14px 10px;
}

body.bi-theme.bi-nav-collapsed .bi-sidebar-dot {
    width: 12px;
    height: 12px;
}

body.bi-theme.bi-nav-collapsed .bi-sidebar-body {
    padding-left: 8px;
    padding-right: 8px;
}

.bi-mobile-backdrop {
    display: none;
}

@media (max-width: 1100px) {
    body.bi-theme,
    body.bi-theme.bi-nav-collapsed {
        padding-left: 0;
    }

    .bi-side-toggle {
        left: 14px !important;
        top: calc(var(--bi-sidebar-top) + 4px);
    }

    .bi-sidebar-shell {
        transform: translateX(-100%);
        width: min(320px, calc(100vw - 28px));
    }

    .bi-sidebar-body {
        padding-bottom: 150px;
    }

    .bi-sidebar-foot {
        min-height: 94px;
        padding-bottom: calc(24px + env(safe-area-inset-bottom, 0px));
    }

    body.bi-theme.bi-nav-open .bi-sidebar-shell {
        transform: translateX(0);
    }

    .bi-mobile-backdrop {
        position: fixed;
        inset: 0;
        top: var(--bi-sidebar-top);
        background: rgba(3, 16, 27, 0.46);
        z-index: 1190;
    }

    body.bi-theme.bi-nav-open .bi-mobile-backdrop {
        display: block;
    }
}
</style>

<?php
$sectionDisplay = [
    'Rede Hospitalar' => 'Comparativa Rede',
    'Controle de Gastos' => 'Controle de Gastos',
    'Anomalias & Fraude' => 'Anomalias & Fraude',
    'Auditoria' => 'Auditoria',
    'Conformidade & Auditoria' => 'Conformidade & Auditoria',
    'Segmentação de Risco' => 'Segmentação de Risco',
    'Risco & Prevenção' => 'Risco & Prevenção',
    'Negociação & Rede' => 'Negociação & Rede',
    'Qualidade & Desfecho' => 'Qualidade & Desfecho',
    'Faturamento' => 'Faturamento',
    'Indicadores Essenciais' => 'Indicadores Essenciais',
];
$activeSection = $currentSection ?: array_key_first($biSections);
$navUrl = $BASE_URL . 'bi/navegacao';
$navActive = $currentPage === 'bi_navegacao.php' || trim((string) $currentPath, '/') === 'bi/navegacao';
?>

<button type="button" class="bi-side-toggle" id="biSideToggle" aria-label="Alternar navegação BI">☰</button>
<div class="bi-mobile-backdrop" id="biMobileBackdrop"></div>

<aside class="bi-sidebar-shell" id="biSidebarShell">
    <div class="bi-sidebar-head">
        <div class="bi-topbar-title"><span>Navegação BI</span></div>
        <div class="bi-crumb">
            <?= htmlspecialchars($sectionDisplay[$activeSection] ?? $activeSection ?: 'Resumo', ENT_QUOTES, 'UTF-8') ?>
            <span>/</span>
            <?= htmlspecialchars($currentLabel ?: 'Painel', ENT_QUOTES, 'UTF-8') ?>
        </div>
        <a class="bi-sidebar-navlink <?= $navActive ? 'is-active' : '' ?>"
            href="<?= htmlspecialchars($navUrl, ENT_QUOTES, 'UTF-8') ?>">
            Navegação Geral
        </a>
        <button type="button" class="bi-sidebar-search-trigger" id="biSidebarSearchTrigger" aria-label="Abrir pesquisa do BI" title="Pesquisar no BI">
            <i class="bi bi-search"></i>
        </button>
        <div class="bi-sidebar-search">
            <div class="bi-sidebar-search-box">
                <i class="bi bi-search"></i>
                <input
                    type="search"
                    id="biSidebarSearch"
                    class="bi-sidebar-search-input"
                    placeholder="Pesquisar relatório ou módulo"
                    autocomplete="off"
                    spellcheck="false"
                >
            </div>
            <div class="bi-sidebar-search-meta" id="biSidebarSearchMeta">Exibindo todos os atalhos</div>
        </div>
    </div>

    <div class="bi-sidebar-body">
        <?php foreach ($biSections as $section => $items): ?>
        <?php
        $sectionName = $sectionDisplay[$section] ?? $section;
        $isActiveSection = $section === $activeSection;
        ?>
        <details class="bi-sidebar-group <?= $isActiveSection ? 'is-active' : '' ?>" <?= $isActiveSection ? 'open' : '' ?>>
            <summary>
                <span class="bi-sidebar-dot"></span>
                <span><?= htmlspecialchars($sectionName, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="bi-sidebar-chevron">›</span>
            </summary>
            <div class="bi-sidebar-links">
                <?php foreach ($items as $item): ?>
                <?php $isActiveChip = $isBiTopbarItemActive($item); ?>
                <a class="bi-sidebar-link <?= $isActiveChip ? 'is-active' : '' ?>"
                    href="<?= $BASE_URL . $item['href'] ?>"
                    title="<?= htmlspecialchars($section . ' • ' . $item['label'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
                </a>
                <?php endforeach; ?>
            </div>
        </details>
        <?php endforeach; ?>
    </div>

    <div class="bi-sidebar-foot">
        <select class="bi-topbar-select" onchange="if (this.value) window.location.href=this.value;">
            <option value="">Ir para relatório...</option>
            <?php foreach ($biSections as $section => $items): ?>
            <?php $sectionName = $sectionDisplay[$section] ?? $section; ?>
            <optgroup label="<?= htmlspecialchars($sectionName, ENT_QUOTES, 'UTF-8') ?>">
                <?php foreach ($items as $item): ?>
                <option value="<?= $BASE_URL . $item['href'] ?>" <?= $isBiTopbarItemActive($item) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
                </option>
                <?php endforeach; ?>
            </optgroup>
            <?php endforeach; ?>
        </select>
    </div>
</aside>

<script>
(function () {
    const body = document.body;
    const toggle = document.getElementById('biSideToggle');
    const backdrop = document.getElementById('biMobileBackdrop');
    const searchInput = document.getElementById('biSidebarSearch');
    const searchMeta = document.getElementById('biSidebarSearchMeta');
    const searchTrigger = document.getElementById('biSidebarSearchTrigger');
    const mobileMq = window.matchMedia('(max-width: 1100px)');
    const storageKey = 'bi_sidebar_collapsed';

    function syncInitialState() {
        if (mobileMq.matches) {
            body.classList.remove('bi-nav-collapsed');
            return;
        }
        const collapsed = window.localStorage.getItem(storageKey) === '1';
        body.classList.toggle('bi-nav-collapsed', collapsed);
    }

    function toggleSidebar() {
        if (mobileMq.matches) {
            body.classList.toggle('bi-nav-open');
            return;
        }
        const nextState = !body.classList.contains('bi-nav-collapsed');
        body.classList.toggle('bi-nav-collapsed', nextState);
        window.localStorage.setItem(storageKey, nextState ? '1' : '0');
    }

    syncInitialState();
    toggle?.addEventListener('click', toggleSidebar);
    backdrop?.addEventListener('click', () => body.classList.remove('bi-nav-open'));
    searchTrigger?.addEventListener('click', () => {
        if (!mobileMq.matches && body.classList.contains('bi-nav-collapsed')) {
            body.classList.remove('bi-nav-collapsed');
            window.localStorage.setItem(storageKey, '0');
        } else if (mobileMq.matches && !body.classList.contains('bi-nav-open')) {
            body.classList.add('bi-nav-open');
        }
        window.setTimeout(() => searchInput?.focus(), 120);
    });
    mobileMq.addEventListener('change', () => {
        body.classList.remove('bi-nav-open');
        syncInitialState();
    });

    if (searchInput) {
        const normalize = (value) => (value || '')
            .toString()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim();

        const groups = Array.from(document.querySelectorAll('.bi-sidebar-group'));
        const initiallyOpen = new WeakMap();
        groups.forEach((group) => {
            const hasActiveLink = !!group.querySelector('.bi-sidebar-link.is-active');
            initiallyOpen.set(group, group.hasAttribute('open') || hasActiveLink);
            group.open = initiallyOpen.get(group);
        });

        const applySearch = () => {
            const term = normalize(searchInput.value);
            let visible = 0;

            groups.forEach((group) => {
                const title = normalize(group.querySelector('summary span:nth-child(2)')?.textContent || '');
                const links = Array.from(group.querySelectorAll('.bi-sidebar-link'));
                let visibleInGroup = 0;

                links.forEach((link) => {
                    const text = normalize((link.getAttribute('title') || '') + ' ' + (link.textContent || ''));
                    const match = term === '' || text.includes(term) || title.includes(term);
                    link.classList.toggle('is-search-hidden', !match);
                    if (match) {
                        visible += 1;
                        visibleInGroup += 1;
                    }
                });

                const showGroup = visibleInGroup > 0;
                group.classList.toggle('is-search-hidden', !showGroup);
                group.open = term !== '' ? showGroup : !!initiallyOpen.get(group);
            });

            if (searchMeta) {
                searchMeta.textContent = term === ''
                    ? 'Digite para localizar uma tela do BI'
                    : 'Resultados encontrados: ' + visible;
            }
        };

        searchInput.addEventListener('input', applySearch);
        applySearch();
    }
})();
</script>
