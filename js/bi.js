if (typeof Chart !== 'undefined') {
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
  }
  if (Chart.defaults.scale && Chart.defaults.scale.gridLines) {
    Chart.defaults.scale.gridLines.color = 'rgba(255,255,255,0.12)';
  }
  if (Chart.defaults.global && Chart.defaults.global.layout) {
    Chart.defaults.global.layout.padding = { left: 18, right: 8, top: 6, bottom: 6 };
  }
}

window.biChartScales = function () {
  return {
    xAxes: [{
      ticks: { fontColor: '#eaf6ff', display: true, padding: 6 },
      gridLines: { color: 'rgba(255,255,255,0.12)' }
    }],
    yAxes: [{
      ticks: { fontColor: '#eaf6ff', display: true, padding: 6, beginAtZero: true },
      gridLines: { color: 'rgba(255,255,255,0.12)' }
    }]
  };
};

window.biLegendWhite = { labels: { fontColor: '#eaf6ff' } };

window.biMoneyTick = function (value) {
  const num = Number(value || 0);
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
      resetBtn.textContent = 'Limpar';
      resetBtn.addEventListener('click', () => {
        const url = new URL(window.location.href);
        url.search = '';
        window.location.href = url.toString();
      });
      actions.appendChild(resetBtn);
    }
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
      resetBtn.textContent = 'Limpar';
      resetBtn.addEventListener('click', () => {
        const url = new URL(window.location.href);
        url.search = '';
        window.location.href = url.toString();
      });
      actions.appendChild(resetBtn);
    }
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
