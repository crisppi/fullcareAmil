(function () {
  'use strict';

  const baseEl = document.querySelector('base[href]');
  const baseHref = baseEl ? baseEl.href : window.location.origin + '/';
  const appBase = new URL(baseHref, window.location.href);
  const appPath = appBase.pathname.endsWith('/') ? appBase.pathname : appBase.pathname + '/';

  const exactRoutes = {
    'menu_app.php': 'central-trabalho',
    'dashboard_operacional.php': 'dashboard-operacional',
    'dashboard_performance.php': 'inteligencia/performance-equipes',
    'faturamento_previsao.php': 'inteligencia/previsao-faturamento',
    'IndicadoresEssenciaisHubBI.php': 'bi/indicadores-essenciais',
    'SolicitacaoCustomizacao.php': 'solicitacoes/customizacao',
    'SolicitacaoCustomizacaoList.php': 'solicitacoes/customizacao/lista',
    'solicitacao_customizacao.php': 'solicitacoes/customizacao',
    'list_solicitacao_customizacao.php': 'solicitacoes/customizacao/lista',
    'admin_permissao.php': 'administracao/permissoes',
    'nova_senha.php': 'administracao/alterar-senha',
    'list_paciente.php': 'pacientes',
    'cad_paciente.php': 'pacientes/novo',
    'list_hospital.php': 'hospitais',
    'cad_hospital.php': 'hospitais/novo',
    'list_estipulante.php': 'estipulantes',
    'cad_estipulante.php': 'estipulantes/novo',
    'list_seguradora.php': 'seguradoras',
    'cad_seguradora.php': 'seguradoras/nova',
    'list_usuario.php': 'usuarios',
    'cad_usuario.php': 'usuarios/novo',
    'list_hospitalUser.php': 'usuarios/hospitais',
    'list_acomodacao.php': 'acomodacoes',
    'cad_acomodacao.php': 'acomodacoes/nova',
    'list_patologia.php': 'patologias',
    'cad_patologia.php': 'patologias/nova',
    'list_antecedente.php': 'antecedentes',
    'cad_antecedente.php': 'antecedentes/novo',
    'list_internacao.php': 'internacoes/lista',
    'cad_internacao.php': 'internacoes/nova',
    'list_internacao_uti.php': 'internacoes/uti',
    'list_internacao_uti_alta.php': 'internacoes/uti/alta',
    'list_internacao_ciclo.php': 'internacoes/ciclo',
    'list_internacao_patologia.php': 'internacoes/drg',
    'list_internacao_sem_senha.php': 'internacoes/sem-senha',
    'list_internacao_gerar_alta.php': 'internacoes/gerar-alta',
    'list_internacao_alta.php': 'internacoes/reverter-alta',
    'list_internacao_alta_lista.php': 'listas/altas',
    'list_censo.php': 'censo/lista',
    'cad_censo.php': 'censo/novo',
    'list_gestao.php': 'gestao',
    'list_pendencias_operacionais.php': 'gestao/pendencias-operacionais',
    'list_fila_tarefas.php': 'gestao/fila-tarefas',
    'list_prorrogacao_pendente.php': 'gestao/prorrogacoes-pendentes',
    'lista_visitas.php': 'visitas/lista',
    'list_visita.php': 'visitas/lista',
    'cad_visita.php': 'visitas/nova',
    'faturamento_visitas.php': 'visitas/faturamento',
    'longa_permanencia_gestao.php': 'cuidado-continuado/longa-permanencia',
    'home_care_gestao.php': 'cuidado-continuado/home-care',
    'list_internacao_cap.php': 'contas/auditoria',
    'list_internacao_cap_rah.php': 'contas/auditar',
    'list_internacao_cap_fin.php': 'contas/finalizadas',
    'contas_finalizadas_rah.php': 'internacoes/rah/finalizadas',
    'list_internacao_senha_fin.php': 'contas/senhas-finalizadas',
    'list_internacao_cap_par.php': 'contas/paradas',
    'list_internacao_cap_jornada.php': 'contas/jornada',
    'relatorios.php': 'relatorios/operacionais',
    'relatorios_capeante.php': 'relatorios/contas'
  };

  const idRoutes = {
    'show_paciente.php': ['id_paciente', 'pacientes/ver'],
    'edit_paciente.php': ['id_paciente', 'pacientes/editar'],
    'show_paciente_historico.php': ['id_paciente', 'pacientes/historico'],
    'hub_paciente.php': ['id_paciente', 'pacientes/hub'],
    'show_internacao.php': ['id_internacao', 'internacoes/visualizar'],
    'edit_internacao.php': ['id_internacao', 'internacoes/editar'],
    'show_internacao_niveis.php': ['id_internacao', 'internacoes/niveis'],
    'show_internacao_censo.php': ['id_internacao', 'internacoes/censo'],
    'show_internacao_patologia.php': ['id_internacao', 'internacoes/patologia'],
    'show_internacao_alta.php': ['id_internacao', 'internacoes/alta'],
    'show_usuario.php': ['id_usuario', 'usuarios/ver'],
    'edit_usuario.php': ['id_usuario', 'usuarios/editar'],
    'show_hospital.php': ['id_hospital', 'hospitais/ver'],
    'edit_hospital.php': ['id_hospital', 'hospitais/editar'],
    'hospital_usuarios.php': ['id_hospital', 'hospitais/usuarios'],
    'hospital_acomodacoes.php': ['id_hospital', 'hospitais/acomodacoes'],
    'show_estipulante.php': ['id_estipulante', 'estipulantes/ver'],
    'edit_estipulante.php': ['id_estipulante', 'estipulantes/editar'],
    'show_seguradora.php': ['id_seguradora', 'seguradoras/ver'],
    'edit_seguradora.php': ['id_seguradora', 'seguradoras/editar'],
    'show_acomodacao.php': ['id_acomodacao', 'acomodacoes/ver'],
    'edit_acomodacao.php': ['id_acomodacao', 'acomodacoes/editar'],
    'show_patologia.php': ['id_patologia', 'patologias/ver'],
    'edit_patologia.php': ['id_patologia', 'patologias/editar'],
    'show_antecedente.php': ['id_antecedente', 'antecedentes/ver'],
    'edit_antecedente.php': ['id_antecedente', 'antecedentes/editar'],
    'show_visita.php': ['id_visita', 'visitas/ver'],
    'SolicitacaoCustomizacaoEdit.php': ['id', 'solicitacoes/customizacao/editar'],
    'home_care_avaliacao.php': ['id_internacao', 'cuidado-continuado/home-care/avaliar'],
    'longa_permanencia_editar.php': ['id_internacao', 'cuidado-continuado/longa-permanencia/gerir'],
    'show_capeante.php': ['id_capeante', 'contas/ver'],
    'show_capeantePrt.php': ['id_capeante', 'contas/prontuario']
  };

  function relativeScript(url) {
    let path = url.pathname;
    if (!path.startsWith(appPath)) return null;
    path = path.slice(appPath.length);
    if (path.includes('/')) return null;
    return path;
  }

  function buildUrl(route, original, removeParams) {
    const next = new URL(route.replace(/^\/+/, ''), appBase.href);
    original.searchParams.forEach((value, key) => {
      if (!removeParams.includes(key)) next.searchParams.append(key, value);
    });
    next.hash = original.hash;
    return next.href;
  }

  function numericId(value) {
    return /^\d+$/.test(String(value || '')) ? String(value) : '';
  }

  function friendlyHref(href) {
    if (!href || href[0] === '#' || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) {
      return href;
    }

    let url;
    try {
      url = new URL(href, appBase.href);
    } catch (error) {
      return href;
    }

    if (url.origin !== appBase.origin) return href;
    const script = relativeScript(url);
    if (!script || !script.endsWith('.php')) return href;

    if (script === 'cad_internacao.php') {
      const id = numericId(url.searchParams.get('id_paciente'));
      return id ? buildUrl('internacoes/nova/paciente/' + id, url, ['id_paciente']) : buildUrl('internacoes/nova', url, []);
    }

    if (script === 'cad_visita.php') {
      const id = numericId(url.searchParams.get('id_internacao'));
      return id ? buildUrl('visitas/nova/internacao/' + id, url, ['id_internacao']) : buildUrl('visitas/nova', url, []);
    }

    if (script === 'cad_capeante_rah.php') {
      const idCapeante = numericId(url.searchParams.get('id_capeante'));
      if (idCapeante) return buildUrl('contas/auditar/' + idCapeante, url, ['id_capeante']);

      const idInternacao = numericId(url.searchParams.get('id_internacao'));
      if (idInternacao) return buildUrl('contas/nova/internacao/' + idInternacao, url, ['id_internacao', 'type']);
    }

    const idRoute = idRoutes[script];
    if (idRoute) {
      const id = numericId(url.searchParams.get(idRoute[0]));
      if (id) return buildUrl(idRoute[1] + '/' + id, url, [idRoute[0]]);
    }

    const exactRoute = exactRoutes[script];
    if (exactRoute) return buildUrl(exactRoute, url, []);

    return href;
  }

  function normalizeElement(el) {
    if (!el || el.dataset.friendlyUrlNormalized === '1') return;
    const href = el.getAttribute('href');
    const friendly = friendlyHref(href);
    if (friendly && friendly !== href) el.setAttribute('href', friendly);
    el.dataset.friendlyUrlNormalized = '1';
  }

  function normalizeLinks(root) {
    if (!root) return;
    if (root.matches && root.matches('a[href]')) normalizeElement(root);
    root.querySelectorAll && root.querySelectorAll('a[href]').forEach(normalizeElement);
  }

  document.addEventListener('DOMContentLoaded', function () {
    normalizeLinks(document);

    const observer = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        mutation.addedNodes.forEach(function (node) {
          if (node.nodeType === 1) normalizeLinks(node);
        });
        if (mutation.type === 'attributes' && mutation.target) {
          mutation.target.dataset.friendlyUrlNormalized = '0';
          normalizeElement(mutation.target);
        }
      });
    });

    observer.observe(document.body, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ['href']
    });
  });

  window.FullCareFriendlyUrl = friendlyHref;
})();
