function bindInternacaoAdditionalTables() {
    const sections = [
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
        if (container) container.style.display = show ? 'block' : 'none';
    }

    function showOnly(activeSelectId) {
        sections.forEach((section) => {
            const select = document.getElementById(section.selectId);
            const show = section.selectId === activeSelectId && select && select.value === 's';
            styleSelect(select, show);
            setSection(section, show);
        });
    }

    sections.forEach((section) => {
        const select = document.getElementById(section.selectId);
        if (!select) return;
        select.addEventListener('change', () => showOnly(section.selectId));
    });

    const initiallyOpen = sections.find((section) => {
        const select = document.getElementById(section.selectId);
        return select && select.value === 's';
    });
    if (initiallyOpen) {
        showOnly(initiallyOpen.selectId);
    } else {
        sections.forEach((section) => setSection(section, false));
    }
}

document.addEventListener('DOMContentLoaded', bindInternacaoAdditionalTables);
