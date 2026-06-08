(function () {
    'use strict';

    const DEFAULT_DURATION = 4600;
    const icons = {
        success: 'bi-check-circle-fill',
        warning: 'bi-exclamation-triangle-fill',
        error: 'bi-x-circle-fill',
        info: 'bi-info-circle-fill',
    };
    const titles = {
        success: 'Tudo certo',
        warning: 'Atenção',
        error: 'Algo deu errado',
        info: 'Aviso',
    };

    function normalizeType(type) {
        const value = String(type || 'info').toLowerCase();
        if (['success', 'sucesso', 'ok'].includes(value)) return 'success';
        if (['warning', 'warn', 'aviso', 'alert'].includes(value)) return 'warning';
        if (['danger', 'error', 'erro'].includes(value)) return 'error';
        return 'info';
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getStack() {
        let stack = document.querySelector('.fc-feedback-stack');
        if (!stack) {
            stack = document.createElement('div');
            stack.className = 'fc-feedback-stack';
            stack.setAttribute('aria-live', 'polite');
            stack.setAttribute('aria-atomic', 'false');
            document.body.appendChild(stack);
        }
        return stack;
    }

    function show(options) {
        const payload = typeof options === 'string' ? { message: options } : (options || {});
        const message = String(payload.message || payload.msg || '').trim();
        if (!message) return null;

        const type = normalizeType(payload.type);
        const toast = document.createElement('div');
        const duration = Number(payload.duration || DEFAULT_DURATION);
        toast.className = `fc-feedback-toast fc-feedback-toast--${type}`;
        toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
        toast.innerHTML = `
            <span class="fc-feedback-toast__icon"><i class="bi ${icons[type] || icons.info}" aria-hidden="true"></i></span>
            <span>
                <strong class="fc-feedback-toast__title">${escapeHtml(payload.title || titles[type] || titles.info)}</strong>
                <span class="fc-feedback-toast__message">${escapeHtml(message)}</span>
                ${payload.detail ? `<small class="fc-feedback-toast__detail">${escapeHtml(payload.detail)}</small>` : ''}
            </span>
            <button type="button" class="fc-feedback-toast__close" aria-label="Fechar aviso">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        `;

        const close = () => {
            toast.classList.remove('is-visible');
            window.setTimeout(() => toast.remove(), 190);
        };
        toast.querySelector('.fc-feedback-toast__close')?.addEventListener('click', close);
        getStack().appendChild(toast);
        window.requestAnimationFrame(() => toast.classList.add('is-visible'));
        if (duration > 0) window.setTimeout(close, duration);
        return toast;
    }

    function inferSubmitFeedback(form, submitter) {
        const method = String(form.getAttribute('method') || 'get').toLowerCase();
        if (method !== 'post') return null;

        const action = String(form.getAttribute('action') || window.location.pathname).toLowerCase();
        const label = String(submitter?.textContent || submitter?.value || '').replace(/\s+/g, ' ').trim().toLowerCase();
        const danger = action.includes('/del_') || action.includes('del_') || label.includes('excluir') || label.includes('apagar') || submitter?.classList.contains('btn-danger');
        const exportAction = label.includes('export') || label.includes('excel') || label.includes('csv') || label.includes('pdf');

        if (danger) {
            return {
                type: 'warning',
                title: 'Exclusão em andamento',
                message: 'Aguarde enquanto o registro é removido.',
                duration: 2600,
            };
        }
        if (exportAction) {
            return {
                type: 'info',
                title: 'Preparando exportação',
                message: 'O arquivo será gerado em instantes.',
                duration: 3000,
            };
        }
        return {
            type: 'info',
            title: 'Salvando alterações',
            message: 'Aguarde enquanto registramos as informações.',
            duration: 2600,
        };
    }

    function bindFormFeedback() {
        let lastSubmitter = null;
        document.addEventListener('click', (event) => {
            const button = event.target.closest('button, input[type="submit"]');
            if (button) lastSubmitter = button;
        }, true);

        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) return;
            if (form.dataset.noFeedback === '1') return;
            const submitter = event.submitter || lastSubmitter;
            const feedback = inferSubmitFeedback(form, submitter);
            if (feedback) show(feedback);
        }, true);
    }

    function bindAlertBridge() {
        if (window.__fullcareAlertBridge) return;
        window.__fullcareAlertBridge = true;
        const originalAlert = window.alert;
        window.alert = function (message) {
            show({
                type: 'warning',
                title: 'Atenção',
                message: message || 'Verifique as informações antes de continuar.',
                duration: 6200,
            });
            return undefined;
        };
        window.FullCareFeedback.originalAlert = originalAlert;
    }

    function bindAjaxFeedback() {
        if (!window.jQuery) return;
        jQuery(document).ajaxError(function (_event, jqXHR, settings) {
            if (settings && settings.global === false) return;
            const url = String(settings && settings.url ? settings.url : '');
            if (url.includes('ajax/pacientes_search.php')) return;
            show({
                type: 'error',
                title: 'Falha na comunicação',
                message: 'Não foi possível concluir a operação. Tente novamente.',
                detail: jqXHR && jqXHR.status ? `Status ${jqXHR.status}` : '',
            });
        });
    }

    function showInitialMessages() {
        const items = Array.isArray(window.FullCareInitialFeedback) ? window.FullCareInitialFeedback : [];
        items.forEach((item, index) => {
            window.setTimeout(() => show(item), index * 180);
        });
    }

    window.FullCareFeedback = {
        show,
        success(message, title) {
            return show({ type: 'success', title, message });
        },
        error(message, title) {
            return show({ type: 'error', title, message });
        },
        warning(message, title) {
            return show({ type: 'warning', title, message });
        },
        info(message, title) {
            return show({ type: 'info', title, message });
        },
    };

    document.addEventListener('fullcare:feedback', (event) => show(event.detail || {}));

    function init() {
        showInitialMessages();
        bindFormFeedback();
        bindAlertBridge();
        bindAjaxFeedback();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
