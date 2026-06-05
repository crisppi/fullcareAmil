<?php
include_once("check_logado.php");
require_once("templates/header.php");
require_once("ajax/_auth_scope.php");
require_once("app/services/InternacaoChartService.php");

if (!isset($conn) || !($conn instanceof PDO)) {
    die("Conexão não disponível.");
}

$ctx = ajax_user_context($conn);
$chartService = new InternacaoChartService($conn);
$hospitais = $chartService->listHospitals($ctx);
?>

<style>
.ai-chart-page {
    padding: 0 14px 28px;
    color: #27364a;
}
.ai-chart-page .fc-module-header {
    margin-bottom: 12px;
}
.ai-chart-shell {
    display: grid;
    grid-template-columns: 300px minmax(0, 1fr);
    gap: 12px;
    align-items: start;
}
.ai-chart-panel {
    background: #fff;
    border: 1px solid #dceaf2;
    border-radius: 8px;
    box-shadow: 0 8px 20px rgba(31, 76, 110, .08);
}
.ai-chart-sidebar {
    padding: 16px;
    background:
        linear-gradient(165deg, rgba(47, 111, 159, .98) 0%, rgba(35, 82, 124, .98) 52%, rgba(32, 139, 122, .96) 100%);
    border-color: rgba(31, 76, 110, .22);
    color: #fff;
    box-shadow: 0 14px 30px rgba(31, 76, 110, .2);
    overflow: hidden;
}
.ai-chart-sidebar h2,
.ai-chart-table h2,
.ai-chart-insight h2 {
    margin: 0 0 12px;
    font-size: .78rem;
    text-transform: uppercase;
    color: #2f6f9f;
    font-weight: 800;
}
.ai-chart-sidebar h2 {
    color: #fff;
    letter-spacing: .02em;
}
.ai-chart-field {
    margin-bottom: 12px;
}
.ai-chart-field label {
    display: block;
    margin-bottom: 5px;
    color: #526277;
    font-size: .68rem;
    font-weight: 800;
    text-transform: uppercase;
}
.ai-chart-sidebar .ai-chart-field label {
    color: rgba(255, 255, 255, .78);
}
.ai-chart-field select,
.ai-chart-prompt textarea {
    width: 100%;
    border: 1px solid #cadde8;
    border-radius: 8px;
    color: #26384f;
    background: #f9fcff;
}
.ai-chart-field select {
    height: 38px;
    padding: 0 10px;
}
.ai-chart-sidebar .ai-chart-field select {
    border-color: rgba(255, 255, 255, .28);
    background: rgba(255, 255, 255, .96);
    color: #223247;
    box-shadow: 0 8px 18px rgba(18, 48, 78, .16);
}
.ai-chart-prompt {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 48px 54px;
    gap: 10px;
    padding: 14px;
    border-bottom: 1px solid #e2edf4;
}
.ai-chart-prompt textarea {
    min-height: 60px;
    max-height: 140px;
    resize: vertical;
    padding: 11px 12px;
}
.ai-chart-submit {
    border: 0;
    border-radius: 8px;
    background: linear-gradient(145deg, #5e3db8, #1aa58d);
    color: #fff;
    font-size: 1.15rem;
    box-shadow: 0 8px 18px rgba(94, 61, 184, .22);
}
.ai-chart-clear {
    border: 1px solid #d5e3ec;
    border-radius: 8px;
    background: #f7fbfd;
    color: #5c6f84;
    font-size: 1rem;
}
.ai-chart-clear:hover {
    background: #edf5fa;
    color: #26384f;
}
.ai-chart-suggestions {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 14px;
}
.ai-chart-suggestion {
    border: 1px solid rgba(255, 255, 255, .18);
    border-left: 4px solid rgba(255, 255, 255, .72);
    background: rgba(255, 255, 255, .14);
    color: #fff;
    border-radius: 8px;
    padding: 9px 10px;
    text-align: left;
    font-size: .78rem;
    font-weight: 800;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .14);
}
.ai-chart-suggestion:hover {
    background: rgba(255, 255, 255, .24);
    border-top-color: rgba(255, 255, 255, .34);
    border-right-color: rgba(255, 255, 255, .34);
    border-bottom-color: rgba(255, 255, 255, .34);
    transform: translateY(-1px);
}
.ai-chart-suggestion:nth-child(4n + 1) { border-left-color: #8fd6ff; }
.ai-chart-suggestion:nth-child(4n + 2) { border-left-color: #b7f0cf; }
.ai-chart-suggestion:nth-child(4n + 3) { border-left-color: #ffd36e; }
.ai-chart-suggestion:nth-child(4n + 4) { border-left-color: #ff9aa6; }
.ai-chart-suggestion:active {
    transform: translateY(0);
}
.ai-chart-main {
    min-width: 0;
}
.ai-chart-stage {
    padding: 14px;
}
.ai-chart-stage-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
}
.ai-chart-stage-title {
    margin: 0;
    color: #24384f;
    font-size: 1.02rem;
    font-weight: 850;
}
.ai-chart-stage-meta {
    color: #6b7c90;
    font-size: .78rem;
    font-weight: 700;
}
.ai-chart-canvas-wrap {
    position: relative;
    height: 390px;
    border: 1px solid #d8e9f2;
    border-radius: 8px;
    padding: 12px;
    background:
        linear-gradient(180deg, #ffffff 0%, #f7fbff 100%);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .9);
}
.ai-chart-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 390px;
    color: #738297;
    text-align: center;
    font-size: .9rem;
}
.ai-chart-insight {
    margin-top: 12px;
    padding: 14px;
}
.ai-chart-insight-meta {
    display: flex;
    gap: 8px;
    align-items: center;
    margin: 0 0 6px;
    color: #637286;
    font-size: .66rem;
    font-weight: 700;
}
.ai-chart-insight-speaker {
    color: #24384f;
}
.ai-chart-insight-time {
    color: #7b8ca0;
    font-weight: 600;
}
.ai-chart-insight-text {
    margin: 0;
    padding: 12px 14px;
    white-space: pre-wrap;
    color: #2f3f55;
    line-height: 1.48;
    font-size: .9rem;
    background: linear-gradient(180deg, #ffffff 0%, #f2f8fc 100%);
    border: 1px solid #c7deea;
    border-left: 4px solid #5eb4d8;
    border-radius: 8px;
    box-shadow: 0 10px 24px rgba(31, 76, 110, .12);
}
.ai-chart-table {
    margin-top: 12px;
    padding: 14px;
    overflow-x: auto;
}
.ai-chart-table table {
    width: 100%;
    border-collapse: collapse;
    font-size: .82rem;
}
.ai-chart-table th {
    background: #2f6f9f;
    color: #fff;
    border: 0;
    padding: 9px 10px;
    text-transform: uppercase;
    font-size: .66rem;
    letter-spacing: 0;
}
.ai-chart-table td {
    border-bottom: 1px solid #e8eef3;
    padding: 9px 10px;
    color: #334155;
}
.ai-chart-table td:last-child {
    text-align: right;
    font-weight: 800;
    color: #2f6f9f;
}
@media (max-width: 980px) {
    .ai-chart-shell {
        grid-template-columns: 1fr;
    }
    .ai-chart-prompt {
        grid-template-columns: minmax(0, 1fr) 44px 48px;
    }
}
</style>

<div class="ai-chart-page">
    <div class="fc-module-header fc-module-header--inteligencia">
        <div class="fc-module-header__copy">
            <p class="fc-module-header__kicker">Inteligência Operacional</p>
            <h1 class="fc-module-header__title">IA Gráficos</h1>
            <p class="fc-module-header__subtitle">Crie gráficos operacionais a partir de perguntas sobre saving, negociações, internações, hospitais, seguradoras, permanência, visitas, UTI e eventos.</p>
        </div>
        <div class="fc-module-header__actions">
            <a class="btn btn-light btn-sm" href="<?= htmlspecialchars($BASE_URL . 'inteligencia/assistente-internacoes', ENT_QUOTES, 'UTF-8') ?>">
                <i class="bi bi-chat-dots"></i> IA de Internações
            </a>
        </div>
    </div>

    <div class="ai-chart-shell">
        <aside class="ai-chart-panel ai-chart-sidebar">
            <h2>Filtros</h2>
            <div class="ai-chart-field">
                <label for="chartHospital">Hospital</label>
                <select id="chartHospital">
                    <option value="">Todos os hospitais</option>
                    <?php foreach ($hospitais as $h): ?>
                        <option value="<?= (int)$h['id_hospital'] ?>"><?= htmlspecialchars((string)$h['nome_hosp'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ai-chart-field">
                <label for="chartStatus">Status</label>
                <select id="chartStatus">
                    <option value="internados">Internados</option>
                    <option value="todos">Todos</option>
                    <option value="alta">Com alta</option>
                </select>
            </div>
            <div class="ai-chart-field">
                <label for="chartDays">Período</label>
                <select id="chartDays">
                    <option value="30">Últimos 30 dias</option>
                    <option value="90">Últimos 90 dias</option>
                    <option value="180" selected>Últimos 180 dias</option>
                    <option value="365">Últimos 12 meses</option>
                    <option value="730">Últimos 24 meses</option>
                </select>
            </div>

            <div class="ai-chart-suggestions">
                <button type="button" class="ai-chart-suggestion" data-question="Crie um gráfico de saving por hospital">Saving por hospital</button>
                <button type="button" class="ai-chart-suggestion" data-question="Mostre saving por auditor">Saving por auditor</button>
                <button type="button" class="ai-chart-suggestion" data-question="Mostre a evolução mensal do saving">Evolução do saving</button>
                <button type="button" class="ai-chart-suggestion" data-question="Mostre saving por tipo de negociação">Saving por tipo</button>
                <button type="button" class="ai-chart-suggestion" data-question="Mostre faturamento por hospital">Faturamento</button>
                <button type="button" class="ai-chart-suggestion" data-question="Mostre evolução mensal da glosa">Glosa mensal</button>
                <button type="button" class="ai-chart-suggestion" data-question="Mostre contas abertas por hospital">Contas abertas</button>
                <button type="button" class="ai-chart-suggestion" data-question="Mostre eventos adversos por tipo">Eventos adversos</button>
                <button type="button" class="ai-chart-suggestion" data-question="Crie um gráfico de internações por hospital">Internações por hospital</button>
                <button type="button" class="ai-chart-suggestion" data-question="Mostre a evolução mensal das internações">Evolução mensal</button>
                <button type="button" class="ai-chart-suggestion" data-question="Faça um gráfico de visitas em atraso por hospital">Visitas em atraso</button>
                <button type="button" class="ai-chart-suggestion" data-question="Mostre longa permanência por hospital">Longa permanência</button>
                <button type="button" class="ai-chart-suggestion" data-question="Compare internações por seguradora">Por seguradora</button>
            </div>
        </aside>

        <main class="ai-chart-main">
            <section class="ai-chart-panel">
                <form id="aiChartForm" class="ai-chart-prompt">
                    <textarea id="aiChartQuestion" placeholder="Ex.: crie um gráfico de saving por hospital nos últimos 180 dias" required></textarea>
                    <button class="ai-chart-submit" type="submit" title="Gerar gráfico"><i class="bi bi-stars"></i></button>
                    <button id="aiChartClear" class="ai-chart-clear" type="button" title="Limpar conteúdo"><i class="bi bi-x-lg"></i></button>
                </form>
                <div class="ai-chart-stage">
                    <div class="ai-chart-stage-head">
                        <h2 id="aiChartTitle" class="ai-chart-stage-title">Gráfico sob demanda</h2>
                        <span id="aiChartMeta" class="ai-chart-stage-meta">Aguardando pedido</span>
                    </div>
                    <div id="aiChartEmpty" class="ai-chart-empty">Digite um pedido ou escolha um exemplo para gerar o gráfico.</div>
                    <div id="aiChartWrap" class="ai-chart-canvas-wrap" style="display:none;">
                        <canvas id="aiChartCanvas"></canvas>
                    </div>
                </div>
            </section>

            <section class="ai-chart-panel ai-chart-insight">
                <h2>Leitura da IA</h2>
                <div class="ai-chart-insight-meta">
                    <span class="ai-chart-insight-speaker">FullCare - IA</span>
                    <span id="aiChartInsightTime" class="ai-chart-insight-time"><?= htmlspecialchars(date('d/m/Y H:i'), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <p id="aiChartInsight" class="ai-chart-insight-text">A interpretação aparecerá após gerar o gráfico.</p>
            </section>

            <section class="ai-chart-panel ai-chart-table">
                <h2>Dados usados</h2>
                <div id="aiChartTable">Nenhum dado carregado ainda.</div>
            </section>
        </main>
    </div>
</div>

<script>
(function() {
    const endpoint = <?= json_encode($BASE_URL . 'ajax/internacao_chart_ai.php') ?>;
    const form = document.getElementById('aiChartForm');
    const input = document.getElementById('aiChartQuestion');
    const title = document.getElementById('aiChartTitle');
    const meta = document.getElementById('aiChartMeta');
    const insight = document.getElementById('aiChartInsight');
    const insightTime = document.getElementById('aiChartInsightTime');
    const table = document.getElementById('aiChartTable');
    const empty = document.getElementById('aiChartEmpty');
    const wrap = document.getElementById('aiChartWrap');
    const canvas = document.getElementById('aiChartCanvas');
    const clearBtn = document.getElementById('aiChartClear');
    let chartInstance = null;

    function filters() {
        return {
            hospital_id: document.getElementById('chartHospital').value,
            status: document.getElementById('chartStatus').value,
            days: document.getElementById('chartDays').value
        };
    }

    function palette(count) {
        const colors = ['#2f6f9f', '#1aa58d', '#5e3db8', '#f59e0b', '#d94b67', '#0ea5e9', '#7c3aed', '#20a37a', '#ef7d34', '#2563eb', '#b85ab5', '#64748b'];
        return Array.from({length: count}, function(_, idx) {
            return colors[idx % colors.length];
        });
    }

    function hexToRgba(hex, alpha) {
        const clean = String(hex || '#2f6f9f').replace('#', '');
        const value = clean.length === 3
            ? clean.split('').map(function(ch) { return ch + ch; }).join('')
            : clean;
        const intVal = parseInt(value, 16);
        const r = (intVal >> 16) & 255;
        const g = (intVal >> 8) & 255;
        const b = intVal & 255;
        return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
    }

    function metricColor(chart) {
        const metric = String((chart && (chart.metric || chart.dataset_label)) || '').toLowerCase();
        const titleText = String(title.textContent || '').toLowerCase();
        const combined = metric + ' ' + titleText;
        if (/saving|economia/.test(combined)) return '#1aa58d';
        if (/glosa/.test(combined)) return '#d94b67';
        if (/valor|faturamento|custo|apresentado|final/.test(combined)) return '#2f6f9f';
        if (/evento|gestão|gestao|alto custo|opme/.test(combined)) return '#f59e0b';
        if (/visita/.test(combined)) return '#0ea5e9';
        if (/uti/.test(combined)) return '#5e3db8';
        if (/seguradora/.test(combined)) return '#7c3aed';
        return '#386fa4';
    }

    function isMoneyMetric(chart) {
        const metric = String((chart && (chart.metric || chart.dataset_label)) || '');
        return metric.indexOf('R$') !== -1 || /saving|valor|glosa|faturamento|custo/i.test(metric);
    }

    function formatNumberBR(value, decimals) {
        const num = Number(value || 0);
        return num.toLocaleString('pt-BR', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }

    function formatMoneyBR(value) {
        return 'R$ ' + formatNumberBR(value, 2);
    }

    function formatMetricValue(value, chart) {
        return isMoneyMetric(chart) ? formatMoneyBR(value) : formatNumberBR(value, Number(value) % 1 === 0 ? 0 : 2);
    }

    function formatMessageDate(date) {
        return date.toLocaleString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function touchInsightTime() {
        insightTime.textContent = formatMessageDate(new Date());
    }

    function formatExtraValue(value) {
        const text = String(value || '');
        const match = text.match(/^R\$\s*(-?\d+(?:[.,]\d+)?)$/);
        if (!match) {
            return text || '-';
        }
        return formatMoneyBR(Number(match[1].replace(',', '.')));
    }

    function renderChart(payload) {
        const chart = payload.chart || {};
        const labels = chart.labels || [];
        const values = chart.values || [];
        title.textContent = payload.title || 'Gráfico';
        meta.textContent = (chart.metric || 'Indicador') + ' por ' + (chart.dimension || 'dimensão');
        insight.textContent = payload.insight || 'Sem leitura disponível.';
        touchInsightTime();
        renderTable(payload.rows || [], chart);

        if (!labels.length) {
            if (chartInstance) {
                chartInstance.destroy();
                chartInstance = null;
            }
            wrap.style.display = 'none';
            empty.style.display = 'flex';
            empty.textContent = 'Nenhum dado encontrado para os filtros atuais.';
            return;
        }

        empty.style.display = 'none';
        wrap.style.display = '';
        if (chartInstance) {
            chartInstance.destroy();
        }

        const type = chart.type || 'bar';
        const colors = palette(labels.length);
        const lineColor = metricColor(chart);
        const ctx = canvas.getContext('2d');
        const lineFill = ctx.createLinearGradient(0, 0, 0, 360);
        lineFill.addColorStop(0, hexToRgba(lineColor, .28));
        lineFill.addColorStop(1, hexToRgba(lineColor, .04));
        const dataset = {
            label: chart.dataset_label || chart.metric || 'Valor',
            data: values,
            backgroundColor: type === 'line' ? lineFill : colors.map(function(color) { return hexToRgba(color, .82); }),
            borderColor: type === 'line' ? lineColor : colors,
            borderWidth: type === 'line' ? 3 : 1,
            pointBackgroundColor: type === 'line' ? lineColor : colors,
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
            fill: type === 'line'
        };
        if (type === 'doughnut') {
            dataset.backgroundColor = colors.map(function(color) { return hexToRgba(color, .86); });
            dataset.borderColor = '#fff';
            dataset.borderWidth = 2;
        }

        const options = {
            responsive: true,
            maintainAspectRatio: false,
            legend: {display: type === 'doughnut'},
            tooltips: {
                mode: 'index',
                intersect: false,
                callbacks: {
                    label: function(tooltipItem, data) {
                        const dataset = data.datasets[tooltipItem.datasetIndex] || {};
                        const rawValue = type === 'doughnut' ? dataset.data[tooltipItem.index] : tooltipItem.yLabel;
                        const prefix = dataset.label ? dataset.label + ': ' : '';
                        return prefix + formatMetricValue(rawValue, chart);
                    }
                }
            }
        };
        if (type !== 'doughnut') {
            options.scales = {
                yAxes: [{ticks: {
                    beginAtZero: true,
                    callback: function(value) {
                        return formatMetricValue(value, chart);
                    }
                }}],
                xAxes: [{ticks: {autoSkip: false, maxRotation: 35, minRotation: 0}}]
            };
        }

        chartInstance = new Chart(ctx, {
            type: type,
            data: {
                labels: labels,
                datasets: [dataset]
            },
            options: options
        });
    }

    function renderTable(rows, chart) {
        if (!rows.length) {
            table.textContent = 'Nenhum dado para exibir.';
            return;
        }
        const dimension = escapeHtml(chart.dimension || 'Dimensão');
        const metric = escapeHtml(chart.metric || 'Valor');
        table.innerHTML = '<table><thead><tr><th>' + dimension + '</th><th>Observação</th><th>' + metric + '</th></tr></thead><tbody>' +
            rows.map(function(row) {
                return '<tr><td>' + escapeHtml(row.label || '-') + '</td><td>' + escapeHtml(formatExtraValue(row.extra)) + '</td><td>' + escapeHtml(formatMetricValue(row.value || 0, chart)) + '</td></tr>';
            }).join('') +
            '</tbody></table>';
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function(ch) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[ch];
        });
    }

    function resetContent() {
        input.value = '';
        title.textContent = 'Gráfico sob demanda';
        meta.textContent = 'Aguardando pedido';
        insight.textContent = 'A interpretação aparecerá após gerar o gráfico.';
        touchInsightTime();
        table.textContent = 'Nenhum dado carregado ainda.';
        empty.style.display = 'flex';
        empty.textContent = 'Digite um pedido ou escolha um exemplo para gerar o gráfico.';
        wrap.style.display = 'none';
        if (chartInstance) {
            chartInstance.destroy();
            chartInstance = null;
        }
        input.focus();
    }

    async function generate(question) {
        title.textContent = 'Gerando gráfico...';
        meta.textContent = 'Consultando dados';
        insight.textContent = 'Analisando pedido e preparando visualização...';
        touchInsightTime();
        table.textContent = 'Carregando dados...';
        empty.style.display = 'flex';
        empty.textContent = 'Gerando gráfico...';
        wrap.style.display = 'none';

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({question: question, filters: filters()})
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.error || 'Não foi possível gerar o gráfico.');
            }
            renderChart(data);
        } catch (err) {
            title.textContent = 'Não foi possível gerar';
            meta.textContent = 'Erro';
            insight.textContent = err && err.message ? err.message : 'Erro inesperado.';
            touchInsightTime();
            table.textContent = 'Sem dados.';
            empty.style.display = 'flex';
            empty.textContent = 'Tente ajustar o pedido ou os filtros.';
            wrap.style.display = 'none';
        }
    }

    form.addEventListener('submit', function(event) {
        event.preventDefault();
        const question = input.value.trim();
        if (question) {
            generate(question);
        }
    });

    document.querySelectorAll('.ai-chart-suggestion').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const question = btn.getAttribute('data-question') || btn.textContent;
            input.value = question;
            generate(question);
        });
    });

    clearBtn.addEventListener('click', resetContent);
})();
</script>

<?php include_once("templates/footer.php"); ?>
