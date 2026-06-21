(function () {
    'use strict';

    const STORAGE_PREFIX = 'fullcare:listagem:v1';
    const userId = String(window.FullCareListUserId || 'anon');

    function storageKey(suffix) {
        const path = window.location.pathname.replace(/\/+$/, '') || '/';
        return `${STORAGE_PREFIX}:${userId}:${path}:${suffix}`;
    }

    function qs(selector, root = document) {
        return root.querySelector(selector);
    }

    function qsa(selector, root = document) {
        return Array.from(root.querySelectorAll(selector));
    }

    function getMainForm() {
        return qs('form#form_pesquisa') || qs('.table-filters form[method="GET"]') || qs('.table-filters form');
    }

    function getMainTable() {
        return qs('.listagem-table-wrap table') || qs('#table-content table') || qs('.complete-table table') || qs('table.table');
    }

    function serializeForm(form) {
        const data = {};
        qsa('input, select, textarea', form).forEach((el) => {
            if (!el.name || el.disabled || ['submit', 'button', 'reset', 'file'].includes((el.type || '').toLowerCase())) return;
            if ((el.type || '').toLowerCase() === 'checkbox') {
                if (!data[el.name]) data[el.name] = [];
                if (el.checked) data[el.name].push(el.value || 'on');
                return;
            }
            if ((el.type || '').toLowerCase() === 'radio') {
                if (el.checked) data[el.name] = el.value;
                return;
            }
            data[el.name] = el.value;
        });
        return data;
    }

    function applyFormValues(form, data) {
        qsa('input, select, textarea', form).forEach((el) => {
            if (!el.name || !(el.name in data)) return;
            const value = data[el.name];
            const type = (el.type || '').toLowerCase();
            if (type === 'checkbox') {
                el.checked = Array.isArray(value) && value.includes(el.value || 'on');
            } else if (type === 'radio') {
                el.checked = String(el.value) === String(value);
            } else {
                el.value = value;
            }
            el.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    function buildButton(icon, label, title, className) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = `fc-list-tool-btn ${className || ''}`;
        btn.title = title || label;
        btn.innerHTML = `<i class="bi ${icon}" aria-hidden="true"></i><span>${label}</span>`;
        return btn;
    }

    function submitForm(form) {
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    }

    function installToolbar(form, table) {
        if (form.dataset.fcListToolbar === '1') return;
        form.dataset.fcListToolbar = '1';

        const filters = form.closest('.table-filters') || form.parentElement;
        const toolbar = document.createElement('div');
        toolbar.className = 'fc-list-tools';

        const saveBtn = buildButton('bi-bookmark-check', 'Salvar filtros', 'Salvar filtros atuais');
        const restoreBtn = buildButton('bi-arrow-counterclockwise', 'Meus filtros', 'Voltar para meus filtros salvos');
        const columnsBtn = buildButton('bi-layout-three-columns', 'Colunas', 'Configurar colunas visíveis');
        const exportBtn = buildButton('bi-file-earmark-spreadsheet', 'Exportar Excel', 'Exportar tabela visível para Excel');

        const columnsMenu = document.createElement('div');
        columnsMenu.className = 'fc-list-columns-menu';
        columnsMenu.hidden = true;

        toolbar.append(saveBtn, restoreBtn, columnsBtn, exportBtn, columnsMenu);
        filters.parentElement.insertBefore(toolbar, filters.nextSibling);
        installEmptyResultHint(form, table, toolbar);

        saveBtn.addEventListener('click', () => {
            localStorage.setItem(storageKey('filters'), JSON.stringify(serializeForm(form)));
            restoreBtn.classList.add('is-ready');
            showToast(toolbar, 'Filtros salvos', 'success');
        });

        restoreBtn.addEventListener('click', () => {
            const raw = localStorage.getItem(storageKey('filters'));
            if (!raw) {
                showToast(toolbar, 'Nenhum filtro salvo', 'warning');
                return;
            }
            try {
                applyFormValues(form, JSON.parse(raw));
                showToast(toolbar, 'Filtros restaurados', 'info');
                submitForm(form);
            } catch (_) {
                showToast(toolbar, 'Filtro salvo inválido', 'error');
            }
        });

        columnsBtn.addEventListener('click', () => {
            columnsMenu.hidden = !columnsMenu.hidden;
        });

        exportBtn.addEventListener('click', () => exportVisibleTable(table));

        form.addEventListener('submit', () => {
            sessionStorage.setItem(storageKey('last_filters'), JSON.stringify(serializeForm(form)));
        });

        if (localStorage.getItem(storageKey('filters'))) {
            restoreBtn.classList.add('is-ready');
        }

        installColumnControls(table, columnsMenu);
    }

    function showToast(toolbar, message, type) {
        if (window.FullCareFeedback && typeof window.FullCareFeedback.show === 'function') {
            const titles = {
                success: 'Tudo certo',
                warning: 'Atenção',
                error: 'Erro',
                info: 'Informação',
            };
            window.FullCareFeedback.show({
                type: type || 'info',
                title: titles[type || 'info'] || 'Informação',
                message,
                duration: 2600,
            });
            return;
        }

        let toast = qs('.fc-list-global-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.className = 'fc-list-global-toast';
            document.body.appendChild(toast);
        }
        toast.textContent = message;
        toast.dataset.type = type || 'info';
        toast.classList.add('is-visible');
        window.clearTimeout(toast._timer);
        toast._timer = window.setTimeout(() => toast.classList.remove('is-visible'), 2600);
    }

    function installColumnControls(table, menu) {
        const headers = qsa('thead th', table);
        if (!headers.length) return;

        const columns = buildColumnsState(table);
        menu.innerHTML = '<strong>Colunas visíveis e ordem</strong><small>Arraste para reorganizar</small>';

        columns.forEach((column) => {
            const row = document.createElement('label');
            row.className = 'fc-list-column-option';
            row.draggable = true;
            row.dataset.columnId = column.id;
            row.innerHTML = `<span class="fc-list-column-handle" aria-hidden="true"><i class="bi bi-grip-vertical"></i></span><input type="checkbox" ${column.visible ? 'checked' : ''}> <span>${escapeHtml(column.label)}</span>`;
            const input = qs('input', row);
            input.addEventListener('change', () => {
                const current = buildColumnsStateFromMenu(menu);
                const target = current.find((item) => item.id === column.id);
                if (target) target.visible = input.checked;
                saveColumnsState(current);
                applyColumnState(table);
            });
            menu.appendChild(row);
        });

        installColumnDrag(table, menu);
        applyColumnState(table);
    }

    function buildColumnsState(table) {
        const headers = qsa('thead th', table);
        const saved = normalizeColumnsState(readJson(storageKey('columns'), []));
        const current = headers.map((th, index) => {
            const id = `col_${index}`;
            const savedItem = saved.find((item) => item.id === id);
            return {
                id,
                index,
                label: (th.textContent || `Coluna ${index + 1}`).replace(/\s+/g, ' ').trim() || `Coluna ${index + 1}`,
                visible: savedItem ? savedItem.visible !== false : true,
            };
        });

        if (!saved.length) return current;

        const ordered = [];
        saved.forEach((savedItem) => {
            const match = current.find((item) => item.id === savedItem.id);
            if (match) ordered.push(match);
        });
        current.forEach((item) => {
            if (!ordered.some((orderedItem) => orderedItem.id === item.id)) ordered.push(item);
        });
        return ordered;
    }

    function normalizeColumnsState(value) {
        if (Array.isArray(value)) {
            return value.filter((item) => item && typeof item.id === 'string');
        }
        if (value && typeof value === 'object') {
            return Object.keys(value).map((id) => ({ id, visible: value[id] !== false }));
        }
        return [];
    }

    function buildColumnsStateFromMenu(menu) {
        return qsa('.fc-list-column-option', menu).map((row) => ({
            id: row.dataset.columnId,
            visible: qs('input', row)?.checked !== false,
        })).filter((item) => item.id);
    }

    function saveColumnsState(state) {
        localStorage.setItem(storageKey('columns'), JSON.stringify(state));
    }

    function installColumnDrag(table, menu) {
        let dragged = null;
        qsa('.fc-list-column-option', menu).forEach((row) => {
            row.addEventListener('dragstart', (event) => {
                dragged = row;
                row.classList.add('is-dragging');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', row.dataset.columnId || '');
            });
            row.addEventListener('dragend', () => {
                row.classList.remove('is-dragging');
                dragged = null;
                qsa('.fc-list-column-option', menu).forEach((item) => item.classList.remove('is-drop-before', 'is-drop-after'));
            });
            row.addEventListener('dragover', (event) => {
                if (!dragged || dragged === row) return;
                event.preventDefault();
                const box = row.getBoundingClientRect();
                const before = event.clientY < box.top + box.height / 2;
                row.classList.toggle('is-drop-before', before);
                row.classList.toggle('is-drop-after', !before);
            });
            row.addEventListener('dragleave', () => {
                row.classList.remove('is-drop-before', 'is-drop-after');
            });
            row.addEventListener('drop', (event) => {
                if (!dragged || dragged === row) return;
                event.preventDefault();
                const box = row.getBoundingClientRect();
                const before = event.clientY < box.top + box.height / 2;
                row.classList.remove('is-drop-before', 'is-drop-after');
                if (before) {
                    menu.insertBefore(dragged, row);
                } else {
                    menu.insertBefore(dragged, row.nextSibling);
                }
                saveColumnsState(buildColumnsStateFromMenu(menu));
                applyColumnState(table);
            });
        });
    }

    function applyColumnState(table) {
        const headers = qsa('thead th', table);
        if (!headers.length) return;

        const saved = normalizeColumnsState(readJson(storageKey('columns'), []));
        const order = saved.length ? saved.map((item) => item.id) : headers.map((_, index) => `col_${index}`);
        headers.forEach((_, index) => {
            const id = `col_${index}`;
            if (!order.includes(id)) order.push(id);
        });

        const visibility = {};
        saved.forEach((item) => {
            visibility[item.id] = item.visible !== false;
        });

        const rows = qsa('tr', table);
        rows.forEach((tr) => {
            const cells = qsa('th, td', tr);
            const byId = new Map(cells.map((cell, index) => {
                if (!cell.dataset.fcOriginalCol) {
                    cell.dataset.fcOriginalCol = String(index);
                }
                return [`col_${cell.dataset.fcOriginalCol}`, cell];
            }));
            order.forEach((id) => {
                const cell = byId.get(id);
                if (cell) tr.appendChild(cell);
            });
            qsa('th, td', tr).forEach((cell) => {
                const id = `col_${cell.dataset.fcOriginalCol}`;
                const visible = visibility[id] !== false;
                cell.classList.toggle('fc-list-col-hidden', !visible);
            });
        });
    }

    function exportVisibleTable(table) {
        const rows = qsa('tr', table).map((tr) => {
            const cells = qsa('th:not(.fc-list-col-hidden), td:not(.fc-list-col-hidden)', tr);
            if (!cells.length) return '';
            return `<tr>${cells.map((cell) => {
                const tag = cell.tagName.toLowerCase() === 'th' ? 'th' : 'td';
                const value = (cell.textContent || '').replace(/\s+/g, ' ').trim();
                return `<${tag}>${escapeHtml(value)}</${tag}>`;
            }).join('')}</tr>`;
        }).filter(Boolean);
        if (!rows.length) {
            if (window.FullCareFeedback && typeof window.FullCareFeedback.warning === 'function') {
                window.FullCareFeedback.warning('Não há dados visíveis para exportar.', 'Exportação não realizada');
            }
            return;
        }

        const html = `<!doctype html>
<html>
<head>
<meta charset="UTF-8">
<style>
table { border-collapse: collapse; }
th, td { border: 1px solid #d9e2ec; padding: 6px 8px; mso-number-format: "\\@"; }
th { background: #eef3f8; font-weight: 700; }
</style>
</head>
<body>
<table>${rows.join('')}</table>
</body>
</html>`;
        const blob = new Blob(['\ufeff' + html], { type: 'application/vnd.ms-excel;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `fullcare-listagem-${new Date().toISOString().slice(0, 10)}.xls`;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
        if (window.FullCareFeedback && typeof window.FullCareFeedback.success === 'function') {
            window.FullCareFeedback.success('Excel gerado com as colunas visíveis.', 'Exportação concluída');
        }
    }

    function installEmptyResultHint(form, table, toolbar) {
        if (!isFilteredView(form) || !isTableEmpty(table)) return;

        const hint = document.createElement('div');
        hint.className = 'fc-list-empty-feedback';
        hint.innerHTML = '<i class="bi bi-search" aria-hidden="true"></i><span>Nenhum resultado encontrado com os filtros atuais.</span>';
        toolbar.parentElement.insertBefore(hint, toolbar.nextSibling);

        if (window.FullCareFeedback && typeof window.FullCareFeedback.info === 'function') {
            window.FullCareFeedback.info('Nenhum resultado encontrado com os filtros atuais.', 'Busca sem resultado');
        }
    }

    function isFilteredView(form) {
        const params = new URLSearchParams(window.location.search);
        if (Array.from(params.keys()).some((key) => !['pag', 'page', 'pagina'].includes(String(key).toLowerCase()))) {
            return true;
        }
        return qsa('input, select, textarea', form).some((el) => {
            if (!el.name || el.disabled) return false;
            const type = (el.type || '').toLowerCase();
            if (['submit', 'button', 'reset', 'file', 'hidden'].includes(type)) return false;
            if (type === 'checkbox' || type === 'radio') return el.checked;
            return String(el.value || '').trim() !== '';
        });
    }

    function isTableEmpty(table) {
        const bodyRows = qsa('tbody tr', table);
        const dataRows = bodyRows.filter((tr) => qsa('td', tr).length > 0);
        if (!dataRows.length) return true;
        if (dataRows.length === 1) {
            const text = (dataRows[0].textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
            return /nenhum|nada encontrado|sem registro|não encontrado|nao encontrado/.test(text);
        }
        return false;
    }

    function normalizeHeaderText(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[↕↑↓]/g, '')
            .replace(/\s+/g, ' ')
            .trim()
            .toLowerCase();
    }

    function formatPersonName(value) {
        const original = String(value || '').replace(/\s+/g, ' ').trim();
        if (!original || /^[-—]+$/.test(original)) return original;

        const particles = new Set(['da', 'das', 'de', 'do', 'dos', 'e']);
        return original
            .toLocaleLowerCase('pt-BR')
            .split(' ')
            .map((part, index) => {
                if (!part) return part;
                if (index > 0 && particles.has(part)) return part;

                return part.replace(/(^|[-'’])(\p{L})/gu, (match, prefix, letter) => {
                    return prefix + letter.toLocaleUpperCase('pt-BR');
                });
            })
            .join(' ');
    }

    function normalizePatientNamesInTable(table) {
        const headers = qsa('thead th', table);
        if (!headers.length) return;

        const patientColumns = headers
            .map((th, index) => ({ index, text: normalizeHeaderText(th.textContent) }))
            .filter((item) => item.text === 'paciente' || item.text.includes('paciente'))
            .map((item) => item.index);

        if (!patientColumns.length) return;

        qsa('tbody tr', table).forEach((tr) => {
            const cells = qsa('td', tr);
            patientColumns.forEach((index) => {
                const cell = cells[index];
                if (!cell || cell.dataset.fcPatientNameNormalized === '1') return;

                cell.classList.add('fc-patient-name');
                cell.style.textTransform = 'none';

                const rawText = (cell.textContent || '').trim();
                if (!rawText || /nenhum|nao encontrado|não encontrado|sem registro/i.test(rawText)) return;

                const formatted = formatPersonName(rawText);
                if (formatted && formatted !== rawText) {
                    cell.textContent = formatted;
                }
                cell.dataset.fcPatientNameNormalized = '1';
            });
        });
    }

    function normalizeAllPatientNames(root = document) {
        qsa('table', root).forEach(normalizePatientNamesInTable);
    }

    function applyStatusBadges(table) {
        const badgeMap = {
            ativo: 'success',
            ativa: 'success',
            internado: 'success',
            aberto: 'warning',
            aberta: 'warning',
            pendente: 'warning',
            atrasado: 'danger',
            atrasada: 'danger',
            inativo: 'muted',
            inativa: 'muted',
            encerrado: 'muted',
            encerrada: 'muted',
            finalizado: 'success',
            finalizada: 'success',
            sim: 'success',
            nao: 'muted',
            não: 'muted',
        };
        qsa('tbody td', table).forEach((td) => {
            if (td.children.length > 0) return;
            const text = (td.textContent || '').trim();
            const key = text.toLowerCase();
            if (!badgeMap[key]) return;
            td.innerHTML = `<span class="fc-status-badge fc-status-badge--${badgeMap[key]}">${escapeHtml(text)}</span>`;
        });
    }

    function readJson(key, fallback) {
        try {
            const raw = localStorage.getItem(key);
            return raw ? JSON.parse(raw) : fallback;
        } catch (_) {
            return fallback;
        }
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function init() {
        const form = getMainForm();
        const table = getMainTable();
        normalizeAllPatientNames();
        if (!form || !table) return;

        installToolbar(form, table);
        normalizePatientNamesInTable(table);
        applyStatusBadges(table);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (!(node instanceof Element)) return;
                if (node.matches && node.matches('table')) {
                    normalizePatientNamesInTable(node);
                    return;
                }
                normalizeAllPatientNames(node);
            });
        });
    });
    observer.observe(document.documentElement, { childList: true, subtree: true });
})();
