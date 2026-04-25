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
        select.style.color = active ? 'black' : 'gray';
        select.style.fontWeight = active ? 'bold' : 'normal';
        select.style.border = active ? '2px solid green' : '1px solid gray';
        select.style.backgroundColor = active ? 'rgba(128, 110, 129, 0.3)' : '';
    }

    function setSection(section, show) {
        const container = document.getElementById(section.containerId);
        const body = section.bodyId ? document.getElementById(section.bodyId) : null;
        if (container) container.style.display = show ? 'block' : 'none';
        if (body) body.style.display = show ? 'block' : 'none';
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
        });
    });

    window.fullcareHideAdditionalSections();
}

document.addEventListener('DOMContentLoaded', bindAdditionalTables);
