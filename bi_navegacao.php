<?php
include_once("check_logado.php");
require_once("templates/header.php");

function e($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$navGroups = [
    [
        'title' => 'Resumo',
        'key' => 'resumo',
        'items' => [
            ['label' => 'Consolidado Gestão', 'href' => 'bi/consolidado'],
            ['label' => 'Consolidado Gestão Cards', 'href' => 'bi/consolidado-cards'],
            ['label' => 'Indicadores Essenciais', 'href' => 'IndicadoresEssenciaisHubBI.php'],
            ['label' => 'Indicadores BI', 'href' => 'bi/indicadores'],
        ],
    ],
    [
        'title' => 'Indicadores Essenciais',
        'key' => 'essenciais',
        'items' => [
            ['label' => 'Hub Essenciais', 'href' => 'IndicadoresEssenciaisHubBI.php'],
            ['label' => 'Contas Auditadas por Hospital', 'href' => 'IndicadoresEssenciaisItemBI.php?slug=contas-auditadas-hospital'],
            ['label' => 'Custo Mensal por Hospital', 'href' => 'IndicadoresEssenciaisItemBI.php?slug=custo-mensal-hospital'],
            ['label' => 'Glosa por Hospital', 'href' => 'IndicadoresEssenciaisItemBI.php?slug=glosa-hospital'],
            ['label' => 'Contas Auditadas por Auditor', 'href' => 'IndicadoresEssenciaisItemBI.php?slug=contas-auditadas-auditor'],
            ['label' => 'Glosa por Auditor', 'href' => 'IndicadoresEssenciaisItemBI.php?slug=glosa-auditor'],
            ['label' => 'Saving por Hospital', 'href' => 'IndicadoresEssenciaisItemBI.php?slug=saving-hospital'],
            ['label' => 'Saving por Auditor', 'href' => 'IndicadoresEssenciaisItemBI.php?slug=saving-auditor'],
            ['label' => 'Custo por Patologia', 'href' => 'IndicadoresEssenciaisItemBI.php?slug=custo-patologia'],
            ['label' => 'Custo por Antecedente', 'href' => 'IndicadoresEssenciaisItemBI.php?slug=custo-antecedente'],
            ['label' => 'Custo por UTI', 'href' => 'IndicadoresEssenciaisItemBI.php?slug=custo-uti'],
            ['label' => '% Internação UTI', 'href' => 'IndicadoresEssenciaisItemBI.php?slug=percentual-internacao-uti'],
            ['label' => 'Eventos Adversos por Hospital', 'href' => 'IndicadoresEssenciaisItemBI.php?slug=eventos-adversos-hospital'],
            ['label' => 'Óbitos por Hospital', 'href' => 'IndicadoresEssenciaisItemBI.php?slug=obitos-hospital'],
            ['label' => 'Qualidade Hospitalar', 'href' => 'IndicadoresEssenciaisItemBI.php?slug=qualidade-hospital'],
        ],
    ],
    [
        'title' => 'Clínico',
        'key' => 'clinico',
        'items' => [
            ['label' => 'Patologia', 'href' => 'bi/patologia'],
            ['label' => 'Grupo Patologia', 'href' => 'bi/grupo-patologia'],
            ['label' => 'Antecedente', 'href' => 'bi/antecedente'],
            ['label' => 'UTI', 'href' => 'bi/uti'],
            ['label' => 'UTI Suporte', 'href' => 'bi/uti-suporte'],
            ['label' => 'Tipo Internação', 'href' => 'bi/tipo-internacao'],
            ['label' => 'Longa Permanência', 'href' => 'bi/longa-permanencia'],
            ['label' => 'Evolução', 'href' => 'bi/evolucao'],
            ['label' => 'Visita Inicial', 'href' => 'bi/visita-inicial'],
            ['label' => 'Estratégia Terapêutica', 'href' => 'bi/estrategia-terapeutica'],
            ['label' => 'Prorrogações', 'href' => 'bi/prorrogacoes'],
            ['label' => 'Oportunidades Clínicas', 'href' => 'bi/oportunidades-clinicas'],
            ['label' => 'Desfechos e Alta', 'href' => 'bi/desfechos-alta'],
            ['label' => 'Detalhes Clínicos', 'href' => 'bi/detalhes-clinicos'],
            ['label' => 'Evento Adverso', 'href' => 'bi/evento-adverso'],
            ['label' => 'Segurança e Eventos Abertos', 'href' => 'bi/seguranca-eventos'],
        ],
    ],
    [
        'title' => 'Auditoria',
        'key' => 'auditoria',
        'items' => [
            ['label' => 'Auditor', 'href' => 'bi/auditor'],
            ['label' => 'Auditor Visitas', 'href' => 'bi/auditor-visitas'],
            ['label' => 'Auditoria Produtividade', 'href' => 'bi/auditoria-produtividade'],
            ['label' => 'Análise Negociações', 'href' => 'bi/analise-negociacoes'],
            ['label' => 'Negociações Detalhadas', 'href' => 'bi/negociacoes-detalhadas'],
            ['label' => 'Negociação Avançada', 'href' => 'bi/negociacao-avancada'],
            ['label' => 'Saving por Auditor', 'href' => 'bi/saving-por-auditor'],
            ['label' => 'Saving', 'href' => 'bi/saving'],
        ],
    ],
    [
        'title' => 'Operacional',
        'key' => 'operacional',
        'items' => [
            ['label' => 'Seguradora', 'href' => 'bi/seguradora'],
            ['label' => 'Seguradora Detalhado', 'href' => 'bi/seguradora-detalhado'],
            ['label' => 'Performance Rede Hospitalar', 'href' => 'bi/performance-rede-hospitalar'],
            ['label' => 'Alto Custo', 'href' => 'bi/alto-custo'],
            ['label' => 'Internações com Risco', 'href' => 'bi/internacoes-risco'],
            ['label' => 'Qualidade e Gestão', 'href' => 'bi/qualidade-gestao'],
            ['label' => 'Home Care', 'href' => 'bi/home-care'],
            ['label' => 'Desospitalização', 'href' => 'bi/desospitalizacao'],
            ['label' => 'OPME', 'href' => 'bi/opme'],
            ['label' => 'TUSS / Autorizações', 'href' => 'bi/tuss-autorizacoes'],
        ],
    ],
    [
        'title' => 'Rede Hospitalar',
        'key' => 'rede',
        'items' => [
            ['label' => 'Comparativa', 'href' => 'bi/rede-comparativa'],
            ['label' => 'Custo', 'href' => 'bi/rede-custo'],
            ['label' => 'Glosa', 'href' => 'bi/rede-glosa'],
            ['label' => 'Rejeição Capeante', 'href' => 'bi/rede-rejeicao-capeante'],
            ['label' => 'Permanência', 'href' => 'bi/rede-permanencia'],
            ['label' => 'Eventos Adversos', 'href' => 'bi/rede-eventos-adversos'],
            ['label' => 'Readmissão', 'href' => 'bi/rede-readmissao'],
            ['label' => 'Ranking', 'href' => 'bi/rede-ranking'],
        ],
    ],
    [
        'title' => 'Financeiro',
        'key' => 'financeiro',
        'items' => [
            ['label' => 'Financeiro Realizado', 'href' => 'bi/financeiro-realizado'],
            ['label' => 'Sinistro BI', 'href' => 'bi/sinistro'],
            ['label' => 'Perfil Sinistro', 'href' => 'bi/perfil-sinistro'],
            ['label' => 'Sinistro YTD', 'href' => 'bi/sinistro-ytd'],
            ['label' => 'Pacientes', 'href' => 'bi/pacientes'],
            ['label' => 'Hospitais', 'href' => 'bi/hospitais'],
            ['label' => 'Sinistro', 'href' => 'bi/sinistro-bi'],
        ],
    ],
    [
        'title' => 'Produção',
        'key' => 'producao',
        'items' => [
            ['label' => 'Produção BI', 'href' => 'bi/producao'],
            ['label' => 'Produção YTD', 'href' => 'bi/producao-ytd'],
        ],
    ],
    [
        'title' => 'Tops',
        'key' => 'tops',
        'items' => [
            ['label' => 'Top Hospitais', 'href' => 'bi/tops-hospitais'],
            ['label' => 'Top Pacientes', 'href' => 'bi/tops-pacientes'],
            ['label' => 'Top Patologia', 'href' => 'bi/tops-patologia'],
        ],
    ],
    [
        'title' => 'Controle de Gastos',
        'key' => 'gastos',
        'items' => [
            ['label' => 'Sinistralidade por Patologia', 'href' => 'bi/gastos-patologia'],
            ['label' => 'Sinistralidade por Hospital', 'href' => 'bi/gastos-hospital'],
            ['label' => 'Tendência de Custo', 'href' => 'bi/gastos-tendencia'],
            ['label' => 'Análise de Alto Custo', 'href' => 'bi/gastos-alto-custo'],
            ['label' => 'Custo Evitável', 'href' => 'bi/gastos-custo-evitavel'],
            ['label' => 'Concentração de Risco', 'href' => 'bi/gastos-concentracao'],
            ['label' => 'Provisão vs Realizado', 'href' => 'bi/gastos-provisao-realizado'],
            ['label' => 'Custo Médio Diárias', 'href' => 'bi/custo-medio-diarias'],
            ['label' => 'Ranking Patologia', 'href' => 'bi/ranking-patologia'],
            ['label' => 'Ranking Hospitais', 'href' => 'bi/ranking-hospitais'],
            ['label' => 'Ranking Pacientes', 'href' => 'bi/ranking-pacientes'],
        ],
    ],
    [
        'title' => 'Anomalias & Fraude',
        'key' => 'anomalias',
        'items' => [
            ['label' => 'Outliers de Permanência', 'href' => 'bi/anomalias-permanencia'],
            ['label' => 'Padrão de Negociação', 'href' => 'bi/anomalias-negociacao'],
            ['label' => 'OPME sem Justificativa', 'href' => 'bi/anomalias-opme'],
        ],
    ],
    [
        'title' => 'Conformidade & Auditoria',
        'key' => 'conformidade',
        'items' => [
            ['label' => 'Documentação Completa', 'href' => 'bi/auditoria-documentacao'],
            ['label' => 'Tempo de Resposta', 'href' => 'bi/auditoria-resposta'],
        ],
    ],
    [
        'title' => 'Segmentação de Risco',
        'key' => 'risco',
        'items' => [
            ['label' => 'Pacientes Crônicos', 'href' => 'bi/risco-cronicos'],
            ['label' => 'Risco Readmissão', 'href' => 'bi/risco-readmissao'],
            ['label' => 'Casos Caros Previsíveis', 'href' => 'bi/risco-casos-caros'],
        ],
    ],
    [
        'title' => 'Risco & Prevenção',
        'key' => 'prevencao',
        'items' => [
            ['label' => 'Matriz de Risco', 'href' => 'bi/risco-prevencao-matriz'],
            ['label' => 'Preditores', 'href' => 'bi/risco-prevencao-preditores'],
            ['label' => 'Eventos Adversos', 'href' => 'bi/risco-prevencao-eventos'],
            ['label' => 'Desospitalização Precoce', 'href' => 'bi/risco-prevencao-desospitalizacao'],
            ['label' => 'Score por Internação', 'href' => 'bi/risco-prevencao-score'],
        ],
    ],
    [
        'title' => 'Negociação & Rede',
        'key' => 'negociacao',
        'items' => [
            ['label' => 'Volume vs Custo', 'href' => 'bi/rede-volume-custo'],
            ['label' => 'Mix de Casos', 'href' => 'bi/rede-mix-casos'],
            ['label' => 'Elasticidade de Preço', 'href' => 'bi/rede-elasticidade'],
        ],
    ],
    [
        'title' => 'Qualidade & Desfecho',
        'key' => 'qualidade',
        'items' => [
            ['label' => 'Visão geral', 'href' => 'bi/qualidade-desfecho'],
            ['label' => 'Eventos Adversos', 'href' => 'bi/qualidade-eventos'],
            ['label' => 'Óbitos', 'href' => 'bi/qualidade-obitos'],
        ],
    ],
    [
        'title' => 'Inteligência',
        'key' => 'inteligencia',
        'items' => [
            ['label' => 'Inteligência Artificial', 'href' => 'bi/inteligencia'],
            ['label' => 'Tempo Médio Permanência', 'href' => 'inteligencia/tmp'],
            ['label' => 'Prorrogacao x Alta', 'href' => 'inteligencia/prorrogacao-vs-alta'],
            ['label' => 'Motivos Prorrogacao', 'href' => 'inteligencia/motivos-prorrogacao'],
            ['label' => 'Backlog Autorizacoes', 'href' => 'inteligencia/backlog-autorizacoes'],
        ],
    ],
];
?>

<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi.css?v=20260509-filter-icons">
<link rel="stylesheet" href="<?= $BASE_URL ?>css/bi-navegacao.css?v=20260110">
<script src="<?= $BASE_URL ?>js/bi.js?v=20260516-rounded-bars"></script>
<script>document.addEventListener('DOMContentLoaded', () => document.body.classList.add('bi-theme', 'bi-navegacao'));</script>

<div class="bi-wrapper bi-theme">
    <div class="bi-header">
        <h1 class="bi-title">Navegação BI</h1>
        <div class="bi-header-actions">
            <a class="bi-btn bi-btn-secondary" href="javascript:history.back()" title="Voltar">Voltar</a>
            <a class="bi-nav-icon" href="<?= $BASE_URL ?>bi/navegacao" title="Navegação">
                <i class="bi bi-grid-3x3-gap"></i>
            </a>
        </div>
    </div>

    <div class="bi-panel">
        <div class="bi-nav-search-wrap">
            <label class="bi-nav-search-label" for="bi-nav-search">Navegação geral</label>
            <div class="bi-nav-search-box">
                <i class="bi bi-search"></i>
                <input
                    type="search"
                    id="bi-nav-search"
                    class="bi-nav-search-input"
                    placeholder="Pesquisar por nome, módulo ou tema"
                    autocomplete="off"
                    spellcheck="false"
                >
            </div>
            <div class="bi-nav-search-meta">
                <span id="bi-nav-search-count">Exibindo todos os atalhos</span>
            </div>
        </div>
        <div class="bi-nav-groups-grid">
            <?php foreach ($navGroups as $idx => $group): ?>
                <?php $isOpen = $idx < 4; ?>
                <details class="bi-nav-group" data-theme="<?= e($group['key']) ?>" data-group-title="<?= e($group['title']) ?>" <?= $isOpen ? 'open' : '' ?>>
                    <summary class="bi-nav-group-summary">
                        <span class="bi-nav-group-title"><?= e($group['title']) ?></span>
                        <span class="bi-nav-group-count"><?= count($group['items']) ?></span>
                    </summary>
                    <div class="bi-nav-grid">
                        <?php foreach ($group['items'] as $link): ?>
                            <a
                                class="bi-nav-card"
                                href="<?= $BASE_URL . e($link['href']) ?>"
                                data-search-text="<?= e(mb_strtolower($group['title'] . ' ' . $link['label'], 'UTF-8')) ?>"
                            >
                                <?= e($link['label']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once("templates/footer.php"); ?>
