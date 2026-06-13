function bindAdditionalTables() {
    const sections = [
        { selectId: 'relatorio-detalhado', containerId: 'detalhes-card-wrapper', bodyId: 'div-detalhado' },
        { selectId: 'select_tuss', containerId: 'container-tuss' },
        { selectId: 'select_prorrog', containerId: 'container-prorrog' },
        { selectId: 'select_gestao', containerId: 'container-gestao' },
        { selectId: 'select_uti', containerId: 'container-uti' },
        { selectId: 'select_negoc', containerId: 'container-negoc' }
    ];

    function styleSelect(select, active) {
        if (!select) return;
        const visibleButton = document.querySelector('.tabelas-selects .bootstrap-select > button.dropdown-toggle[data-id="' + select.id + '"]');
        const controls = [select, visibleButton].filter(Boolean);
        controls.forEach((control) => {
            control.style.setProperty('border', active ? '1px solid #5b8ee8' : '1px solid #83aef2', 'important');
            control.style.setProperty('background', active ? 'linear-gradient(180deg, #dbeafe 0%, #bfdbfe 100%)' : 'linear-gradient(180deg, #eaf4ff 0%, #d6eaff 100%)', 'important');
            control.style.setProperty('background-color', active ? '#dbeafe' : '#eaf4ff', 'important');
            control.style.setProperty('color', '#1f4d85', 'important');
            control.style.setProperty('font-weight', '750', 'important');
            control.style.setProperty('box-shadow', active ? '0 0 0 .14rem rgba(91, 142, 232, .16)' : 'inset 0 0 0 1px rgba(62, 113, 198, .12)', 'important');
        });

        if (visibleButton) {
            visibleButton.querySelectorAll('.filter-option, .filter-option-inner, .filter-option-inner-inner').forEach((node) => {
                node.style.setProperty('color', '#1f4d85', 'important');
                node.style.setProperty('font-weight', '750', 'important');
            });
        }
    }

    function styleAllSelects() {
        sections.forEach((section) => {
            const select = document.getElementById(section.selectId);
            styleSelect(select, select && select.value === 's');
        });
    }
    window.fullcareRestyleAdditionalLaunchers = styleAllSelects;

    function setSection(section, show) {
        const container = document.getElementById(section.containerId);
        const body = section.bodyId ? document.getElementById(section.bodyId) : null;
        if (container) container.style.display = show ? 'block' : 'none';
        if (body) body.style.display = show ? 'grid' : 'none';
    }

    function showOnly(activeSelectId) {
        sections.forEach((section) => {
            const select = document.getElementById(section.selectId);
            const show = section.selectId === activeSelectId && select && select.value === 's';
            styleSelect(select, select && select.value === 's');
            setSection(section, show);
        });
    }

    window.fullcareShowAdditionalSection = showOnly;
    window.fullcareHideAdditionalSections = function() {
        sections.forEach((section) => {
            const select = document.getElementById(section.selectId);
            styleSelect(select, select && select.value === 's');
            setSection(section, false);
        });
    };
    window.fullcareSignalAdditionalSection = function(selectId, value) {
        const section = sections.find((item) => item.selectId === selectId);
        const select = document.getElementById(selectId);
        if (!section || !select) return;
        select.value = value || '';
        styleSelect(select, select.value === 's');
        setSection(section, false);
    };

    sections.forEach((section) => {
        const select = document.getElementById(section.selectId);
        if (!select) return;
        select.addEventListener('change', () => showOnly(section.selectId));
        select.addEventListener('click', () => {
            if (select.value === 's') showOnly(section.selectId);
            styleAllSelects();
        });
    });

    window.fullcareHideAdditionalSections();
    styleAllSelects();
    window.setTimeout(styleAllSelects, 100);
    window.setTimeout(styleAllSelects, 500);
}

document.addEventListener('DOMContentLoaded', bindAdditionalTables);
