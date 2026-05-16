if (typeof Chart !== 'undefined') {
  const biChartPalette = {
    internacao: '#76d5ff',
    diaria: '#56d6d0',
    custo: '#ff9a7a',
    glosa: '#f184b5',
    permanencia: '#79e6c4',
    percentual: '#ffd166',
    readmissao: '#b8a7ff',
    qualidade: '#8be9a8',
    auditoria: '#9cc8ff',
    default: '#8dd0ff',
    sequence: ['#76d5ff', '#79e6c4', '#ffd166', '#f184b5', '#b8a7ff', '#ff9a7a', '#56d6d0']
  };

  window.biChartPalette = biChartPalette;

  const biHexToRgb = function (hex) {
    const clean = String(hex || '').replace('#', '');
    const full = clean.length === 3
      ? clean.split('').map(function (char) { return char + char; }).join('')
      : clean;
    const num = parseInt(full, 16);
    if (!Number.isFinite(num)) {
      return [141, 208, 255];
    }
    return [(num >> 16) & 255, (num >> 8) & 255, num & 255];
  };

  const biRgba = function (hex, alpha) {
    const rgb = biHexToRgb(hex);
    return 'rgba(' + rgb[0] + ', ' + rgb[1] + ', ' + rgb[2] + ', ' + alpha + ')';
  };

  const biMetricColor = function (context, index) {
    const text = String(context || '').toLowerCase();
    if (/(glosa|recurso|contest)/i.test(text)) return biChartPalette.glosa;
    if (/(custo|valor|financeiro|saving|sinistro|provis|ticket|realizado|apresentado|final)/i.test(text)) return biChartPalette.custo;
    if (/(diaria|diárias|diaria|diarias)/i.test(text)) return biChartPalette.diaria;
    if (/(mp|m[ée]dia perman|perman[êe]ncia|permanencia)/i.test(text)) return biChartPalette.permanencia;
    if (/(uti|%|percentual|taxa|indice|índice)/i.test(text)) return biChartPalette.percentual;
    if (/(readmiss|reintern)/i.test(text)) return biChartPalette.readmissao;
    if (/(qualidade|óbito|obito|infecc|evento)/i.test(text)) return biChartPalette.qualidade;
    if (/(auditor|visita|conta)/i.test(text)) return biChartPalette.auditoria;
    if (/(internac|internação|internacoes|internações|paciente|hospital)/i.test(text)) return biChartPalette.internacao;
    return biChartPalette.sequence[Math.abs(index || 0) % biChartPalette.sequence.length] || biChartPalette.default;
  };

  window.biMetricColor = biMetricColor;
  window.biRgba = biRgba;

  const biBarGradient = function (chart, color) {
    const area = chart && chart.chartArea;
    const ctx = chart && chart.ctx;
    if (!ctx || !area || area.top === undefined || area.bottom === undefined) {
      return biRgba(color, 0.82);
    }
    const gradient = ctx.createLinearGradient(0, area.top, 0, area.bottom);
    gradient.addColorStop(0, biRgba(color, 0.98));
    gradient.addColorStop(0.58, biRgba(color, 0.78));
    gradient.addColorStop(1, biRgba(color, 0.58));
    return gradient;
  };

  const biStyleBars = function (chart) {
    const type = chart.config && chart.config.type;
    if (!['bar', 'horizontalBar'].includes(type) || (chart.options && chart.options.biBarTheme === false)) {
      return;
    }

    const chartId = chart.canvas ? String(chart.canvas.id || '') : '';
    const labels = chart.data && chart.data.labels ? chart.data.labels : [];
    chart.data.datasets.forEach(function (dataset, datasetIndex) {
      const meta = chart.getDatasetMeta(datasetIndex);
      if (!meta || meta.hidden) {
        return;
      }
      const datasetContext = [chartId, dataset.label || ''].join(' ');
      const datasetColor = biMetricColor(datasetContext, datasetIndex);

      if (!dataset.borderWidth) {
        dataset.borderWidth = 1.5;
      }
      dataset.borderSkipped = dataset.borderSkipped || 'bottom';

      meta.data.forEach(function (element, index) {
        if (!element || !element._model) {
          return;
        }
        const labelContext = datasetContext + ' ' + (labels[index] || '');
        const color = dataset.data && dataset.data.length > 1 && chart.data.datasets.length === 1
          ? biMetricColor(labelContext, index)
          : datasetColor;

        element._model.backgroundColor = biBarGradient(chart, color);
        element._model.borderColor = biRgba(color, 0.95);
        element._model.borderWidth = 1.5;
        element._model.hoverBackgroundColor = biRgba(color, 0.98);
        element._model.hoverBorderColor = 'rgba(255,255,255,0.78)';
      });
    });
  };

  const biApplyScaleTheme = function (chart) {
    const options = chart && chart.options ? chart.options : {};
    if (options.biGridTheme === false || !options.scales) {
      return;
    }

    const xAxes = options.scales.xAxes || [];
    const yAxes = options.scales.yAxes || [];

    xAxes.forEach(function (axis) {
      axis.ticks = Object.assign({
        fontColor: '#eaf6ff',
        padding: 8,
        maxRotation: 48,
        minRotation: 0
      }, axis.ticks || {});
      axis.gridLines = Object.assign({}, axis.gridLines || {}, {
        display: false,
        drawBorder: false,
        zeroLineColor: 'rgba(235,246,255,0.16)'
      });
    });

    yAxes.forEach(function (axis) {
      axis.ticks = Object.assign({
        fontColor: '#eaf6ff',
        padding: 8,
        beginAtZero: true,
        autoSkip: true,
        maxTicksLimit: 6
      }, axis.ticks || {});
      axis.gridLines = Object.assign({}, axis.gridLines || {}, {
        color: 'rgba(235,246,255,0.08)',
        zeroLineColor: 'rgba(235,246,255,0.2)',
        drawBorder: false,
        lineWidth: 1
      });
    });
  };

  if (Chart.defaults.global) {
    Chart.defaults.global.defaultFontColor = '#eaf6ff';
    Chart.defaults.global.legend.labels.fontColor = '#eaf6ff';
    Chart.defaults.global.responsive = true;
    Chart.defaults.global.maintainAspectRatio = false;
  }
  if (Chart.defaults.scale && Chart.defaults.scale.ticks) {
    Chart.defaults.scale.ticks.fontColor = '#eaf6ff';
    Chart.defaults.scale.ticks.display = true;
    Chart.defaults.scale.ticks.padding = 6;
    Chart.defaults.scale.ticks.autoSkip = false;
    Chart.defaults.scale.ticks.maxRotation = 50;
    Chart.defaults.scale.ticks.minRotation = 0;
  }
  if (Chart.defaults.scale && Chart.defaults.scale.gridLines) {
    Chart.defaults.scale.gridLines.color = 'rgba(235,246,255,0.08)';
    Chart.defaults.scale.gridLines.zeroLineColor = 'rgba(235,246,255,0.18)';
    Chart.defaults.scale.gridLines.drawBorder = false;
  }
  if (Chart.defaults.global && Chart.defaults.global.layout) {
    Chart.defaults.global.layout.padding = { left: 18, right: 16, top: 14, bottom: 8 };
  }
  if (Chart.defaults.global && Chart.defaults.global.elements && Chart.defaults.global.elements.rectangle) {
    Chart.defaults.global.elements.rectangle.borderWidth = 1.5;
    Chart.defaults.global.elements.rectangle.borderSkipped = 'bottom';
  }
  if (Chart.defaults.global && Chart.defaults.global.elements && Chart.defaults.global.elements.arc) {
    Chart.defaults.global.elements.arc.borderColor = 'rgba(7, 36, 64, 0.55)';
    Chart.defaults.global.elements.arc.borderWidth = 1.5;
  }

  window.biFormatChartValue = function (value, chart, dataset) {
    const num = Number(value || 0);
    const canvasId = chart && chart.canvas ? String(chart.canvas.id || '') : '';
    const label = dataset ? String(dataset.label || '') : '';
    const context = (canvasId + ' ' + label).toLowerCase();
    const isMoney = /(custo|valor|glosa|saving|sinistro|provis|financeiro|diaria|diárias|money|ticket)/i.test(context);

    if (!Number.isFinite(num)) {
      return '';
    }

    if (isMoney) {
      const abs = Math.abs(num);
      if (abs >= 1000000) {
        return 'R$ ' + (num / 1000000).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mi';
      }
      if (abs >= 1000) {
        return 'R$ ' + (num / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 0 }) + ' mil';
      }
      return 'R$ ' + num.toLocaleString('pt-BR', { maximumFractionDigits: 0 });
    }

    if (Math.abs(num) >= 1000000) {
      return (num / 1000000).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mi';
    }
    if (Math.abs(num) >= 1000) {
      return (num / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' mil';
    }
    return num.toLocaleString('pt-BR', { maximumFractionDigits: Number.isInteger(num) ? 0 : 1 });
  };

  if (Chart.plugins && !Chart._biBarThemeRegistered) {
    Chart._biBarThemeRegistered = true;
    Chart.plugins.register({
      beforeDatasetsDraw: function (chart) {
        biStyleBars(chart);
      },
      beforeInit: function (chart) {
        biApplyScaleTheme(chart);
      },
      beforeUpdate: function (chart) {
        biApplyScaleTheme(chart);
      },
      beforeDatasetDraw: function (chart) {
        const type = chart.config && chart.config.type;
        if (!['bar', 'horizontalBar'].includes(type) || (chart.options && chart.options.biBarTheme === false)) {
          return;
        }
        const ctx = chart.ctx;
        ctx.save();
        ctx.shadowColor = 'rgba(5, 20, 38, 0.28)';
        ctx.shadowBlur = 10;
        ctx.shadowOffsetY = 4;
      },
      afterDatasetDraw: function (chart) {
        const type = chart.config && chart.config.type;
        if (!['bar', 'horizontalBar'].includes(type) || (chart.options && chart.options.biBarTheme === false)) {
          return;
        }
        chart.ctx.restore();
      }
    });
  }

  if (Chart.plugins && !Chart._biValueLabelsRegistered) {
    Chart._biValueLabelsRegistered = true;
    Chart.plugins.register({
      afterDatasetsDraw: function (chart) {
        const type = chart.config && chart.config.type;
        if (chart.options && chart.options.biValueLabels === false) {
          return;
        }
        if (!['bar', 'horizontalBar', 'pie', 'doughnut'].includes(type)) {
          return;
        }

        const ctx = chart.ctx;
        ctx.save();
        ctx.font = '600 10px Poppins, Arial, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.shadowColor = 'rgba(4, 20, 36, 0.42)';
        ctx.shadowBlur = 3;
        ctx.shadowOffsetY = 1;

        chart.data.datasets.forEach(function (dataset, datasetIndex) {
          const meta = chart.getDatasetMeta(datasetIndex);
          if (!meta || meta.hidden) {
            return;
          }

          const values = dataset.data || [];

          if (type === 'pie' || type === 'doughnut') {
            ctx.fillStyle = '#ffffff';

            meta.data.forEach(function (arc, index) {
              const value = Number(values[index] || 0);
              if (!arc || arc.hidden || value === 0) {
                return;
              }
              const model = arc._model || {};
              const angle = (model.startAngle + model.endAngle) / 2;
              const radius = ((model.innerRadius || 0) + (model.outerRadius || 0)) / 2;
              const x = model.x + Math.cos(angle) * radius;
              const y = model.y + Math.sin(angle) * radius;
              const text = window.biFormatChartValue(value, chart, dataset);

              if (!text) {
                return;
              }
              ctx.fillText(text, x, y);
            });
            return;
          }

          meta.data.forEach(function (bar, index) {
            const value = Number(values[index] || 0);
            if (!bar || bar.hidden || value === 0) {
              return;
            }
            const model = bar._model || {};
            const text = window.biFormatChartValue(value, chart, dataset);
            if (!text) {
              return;
            }

            let x = model.x;
            let y = model.y;
            ctx.fillStyle = '#f8fbff';

            if (type === 'horizontalBar') {
              const base = Number(model.base || 0);
              const direction = model.x >= base ? 1 : -1;
              x = model.x + (direction * 22);
              y = model.y;
              ctx.textAlign = direction > 0 ? 'left' : 'right';
            } else {
              x = model.x;
              y = model.y - 10;
              ctx.textAlign = 'center';
            }

            ctx.fillText(text, x, y);
          });
        });

        ctx.restore();
      }
    });
  }
}

window.biChartScales = function () {
  return {
    xAxes: [{
      ticks: { fontColor: '#eaf6ff', display: true, padding: 8, autoSkip: false, maxRotation: 48, minRotation: 0 },
      gridLines: {
        display: false,
        drawBorder: false,
        zeroLineColor: 'rgba(235,246,255,0.16)'
      }
    }],
    yAxes: [{
      ticks: { fontColor: '#eaf6ff', display: true, padding: 8, beginAtZero: true, autoSkip: true, maxTicksLimit: 6 },
      gridLines: {
        color: 'rgba(235,246,255,0.08)',
        zeroLineColor: 'rgba(235,246,255,0.2)',
        drawBorder: false,
        lineWidth: 1
      }
    }]
  };
};

window.biLegendWhite = { labels: { fontColor: '#eaf6ff' } };

window.biMoneyTick = function (value) {
  const num = Number(value || 0);
  const abs = Math.abs(num);
  if (abs >= 1000000) {
    return 'R$ ' + (num / 1000000).toLocaleString('pt-BR', {
      minimumFractionDigits: 0,
      maximumFractionDigits: 1
    }) + ' mi';
  }
  if (abs >= 1000) {
    return 'R$ ' + (num / 1000).toLocaleString('pt-BR', {
      minimumFractionDigits: 0,
      maximumFractionDigits: 0
    }) + ' mil';
  }
  return 'R$ ' + num.toLocaleString('pt-BR', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  });
};

document.addEventListener('DOMContentLoaded', () => {
  if (document.body) {
    document.body.classList.add('bi-theme');
    const nav = document.querySelector('.navbar.fixed-top');
    const navHeight = nav ? nav.offsetHeight : 100;
    document.body.style.paddingTop = (navHeight + 16) + 'px';
  }

  const applyDateStyles = (root) => {
    root.querySelectorAll('input[type="date"]').forEach((input) => {
      input.style.setProperty('background-color', 'var(--bi-panel-strong)', 'important');
      input.style.setProperty('color', '#eaf6ff', 'important');
      input.style.setProperty('border-color', 'rgba(255,255,255,0.35)', 'important');
      input.style.setProperty('-webkit-text-fill-color', '#eaf6ff', 'important');
    });
  };

  const setupFilterStates = (root) => {
    const updateFilterState = (filter) => {
      const control = filter.querySelector('select, input');
      if (!control) {
        return;
      }
      const label = filter.querySelector('label');
      const value = (control.value || '').trim();
      const isSelected = value !== '';
      filter.classList.toggle('is-selected', isSelected);
      if (isSelected) {
        control.setAttribute('data-selected', '1');
        control.style.setProperty('border-color', 'rgba(255, 220, 120, 0.95)', 'important');
        control.style.setProperty('background-color', 'rgba(255, 220, 120, 0.2)', 'important');
        control.style.setProperty('box-shadow', '0 0 0 2px rgba(255, 220, 120, 0.25)', 'important');
        if (label) {
          label.style.setProperty('color', '#ffe2a6', 'important');
        }
      } else {
        control.removeAttribute('data-selected');
        control.style.removeProperty('border-color');
        control.style.removeProperty('background-color');
        control.style.removeProperty('box-shadow');
        if (label) {
          label.style.removeProperty('color');
        }
      }
    };

    root.querySelectorAll('.bi-filter').forEach((filter) => {
      updateFilterState(filter);
      const control = filter.querySelector('select, input');
      if (control) {
        control.addEventListener('change', () => updateFilterState(filter));
        control.addEventListener('input', () => updateFilterState(filter));
      }
    });
  };

  const iconizeFilterActionButtons = (root) => {
    const normalize = (value) => (value || '')
      .toString()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .trim();

    root.querySelectorAll('.bi-actions .bi-btn, .bi-filter-actions .bi-filter-btn').forEach((button) => {
      if (button.dataset.biIconized === '1') {
        return;
      }

      const text = normalize(button.textContent);
      const isReset = button.classList.contains('bi-btn-reset') || /limpar/.test(text);
      const isSubmit = button.tagName === 'BUTTON' && (button.getAttribute('type') || 'submit').toLowerCase() === 'submit';
      const isApply = isSubmit || /aplicar|atualizar/.test(text);

      if (!isReset && !isApply) {
        return;
      }

      const label = isReset ? 'Limpar filtros' : 'Aplicar filtros';
      const icon = isReset ? 'bi-trash3' : 'bi-search';
      button.dataset.biIconized = '1';
      button.classList.add('bi-icon-action');
      button.classList.toggle('bi-icon-action-reset', isReset);
      button.classList.toggle('bi-icon-action-apply', !isReset);
      button.setAttribute('aria-label', label);
      button.setAttribute('title', label);
      button.innerHTML = '<i class="bi ' + icon + '" aria-hidden="true"></i>';
    });
  };

  document.querySelectorAll('.bi-panel.bi-filters').forEach((panel) => {
    const isWrap = panel.classList.contains('bi-filters-wrap');
    if (!isWrap) {
      panel.style.display = 'flex';
      panel.style.flexWrap = 'nowrap';
      panel.style.gap = '12px';
      panel.style.alignItems = 'flex-end';
      panel.style.overflowX = 'auto';
      panel.style.paddingBottom = '6px';

      panel.querySelectorAll('.bi-filter').forEach((filter) => {
        filter.style.minWidth = '170px';
        filter.style.flex = '0 0 170px';
      });
    }

    applyDateStyles(panel);
    setupFilterStates(panel);

    const form = panel.closest('form');
    if (form && !form.querySelector('.bi-btn-reset')) {
      let actions = panel.querySelector('.bi-actions');
      if (!actions) {
        actions = document.createElement('div');
        actions.className = 'bi-actions';
        panel.appendChild(actions);
      }
      const resetBtn = document.createElement('button');
      resetBtn.type = 'button';
      resetBtn.className = 'bi-btn bi-btn-secondary bi-btn-reset';
      resetBtn.textContent = 'Limpar filtros';
      resetBtn.addEventListener('click', () => {
        const url = new URL(window.location.href);
        url.search = '';
        window.location.href = url.toString();
      });
      actions.appendChild(resetBtn);
    }

    iconizeFilterActionButtons(panel);
  });

  document.querySelectorAll('.bi-filter-card').forEach((card) => {
    applyDateStyles(card);
    setupFilterStates(card);

    const form = card.closest('form');
    if (form && !form.querySelector('.bi-btn-reset')) {
      let actions = card.querySelector('.bi-filter-actions');
      if (!actions) {
        actions = document.createElement('div');
        actions.className = 'bi-filter-actions';
        card.appendChild(actions);
      }
      const resetBtn = document.createElement('button');
      resetBtn.type = 'button';
      resetBtn.className = 'bi-filter-btn bi-filter-btn-secondary bi-btn-reset';
      resetBtn.textContent = 'Limpar filtros';
      resetBtn.addEventListener('click', () => {
        const url = new URL(window.location.href);
        url.search = '';
        window.location.href = url.toString();
      });
      actions.appendChild(resetBtn);
    }

    iconizeFilterActionButtons(card);
  });

  const navSearchInput = document.getElementById('bi-nav-search');
  const navSearchCount = document.getElementById('bi-nav-search-count');
  if (navSearchInput) {
    const groups = Array.from(document.querySelectorAll('.bi-nav-group'));
    const normalize = (value) => (value || '')
      .toString()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .trim();

    const updateNavSearch = () => {
      const term = normalize(navSearchInput.value);
      let visibleCards = 0;

      groups.forEach((group) => {
        const cards = Array.from(group.querySelectorAll('.bi-nav-card'));
        let visibleInGroup = 0;

        cards.forEach((card) => {
          const haystack = normalize(card.dataset.searchText || card.textContent || '');
          const match = term === '' || haystack.includes(term);
          card.classList.toggle('is-hidden', !match);
          if (match) {
            visibleCards += 1;
            visibleInGroup += 1;
          }
        });

        const count = group.querySelector('.bi-nav-group-count');
        if (count) {
          count.textContent = String(visibleInGroup);
        }

        const shouldShowGroup = visibleInGroup > 0;
        group.classList.toggle('is-hidden', !shouldShowGroup);
        if (term !== '' && shouldShowGroup) {
          group.open = true;
        }
      });

      if (navSearchCount) {
        navSearchCount.textContent = term === ''
          ? 'Exibindo todos os atalhos'
          : 'Resultados encontrados: ' + visibleCards;
      }
    };

    navSearchInput.addEventListener('input', updateNavSearch);
    updateNavSearch();
  }
});
