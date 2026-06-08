<?php

return [
    [
        'id' => 'onboarding',
        'title' => 'Primeiros passos no FullCare',
        'keywords' => [
            'onboarding', 'primeiro acesso', 'primeiros passos', 'treinamento', 'apresentacao',
            'manual geral', 'tour', 'navegacao'
        ],
        'summary' => 'Use o menu Manual > Geral para seguir o passo a passo inicial e validar permissões antes de abrir os casos.',
        'steps' => [
            'Revise o manual_geral.html e confirme se todos os módulos que usará estão liberados.',
            'Execute um cadastro de teste em Pacientes e Internações para validar fluxos.',
            'Alinhe com o líder quais dashboards e PDFs precisam de conferência diária.'
        ],
        'link' => 'manual.html',
        'link_label' => 'Manual Geral'
    ],
    [
        'id' => 'internacao',
        'title' => 'Cadastro de Internação e atualizações',
        'keywords' => [
            'internacao', 'censo', 'cadastro paciente', 'nova internacao', 'painel internacoes',
            'condicoes clinicas', 'leito', 'tipo de internacao'
        ],
        'summary' => 'Garanta que matrícula, senha e hospital estejam consistentes antes de salvar para evitar duplicidades.',
        'steps' => [
            'No menu Produção > Internação escolha Nova Internação e preencha matrícula, senha e hospital.',
            'Inclua patologia principal, nível de complexidade e informações de acomodação.',
            'Depois de salvar, use Gestão ou o painel específico para acompanhar prorrogações e altas.'
        ],
        'link' => 'manual_internacao.html',
        'link_label' => 'Manual de Internação'
    ],
    [
        'id' => 'negociacao',
        'title' => 'Negociações e savings',
        'keywords' => [
            'negociacao', 'savings', 'troca leito', 'plano de acao', 'dashboard negociacoes',
            'contato hospital', 'contraproposta'
        ],
        'summary' => 'Classifique o tipo de negociação e registre o combinado com o hospital para refletir no RAH e dashboards.',
        'steps' => [
            'Acesse Negociações > Nova e selecione o paciente ou internacao correspondente.',
            'Descreva o cenário negociado (ex.: troca UTI/SEMI) e anexe evidências se necessário.',
            'Finalize atualizando o status e confirme os valores para alimentar o cálculo de savings.'
        ],
        'link' => 'manual_negociacoes.html',
        'link_label' => 'Manual de Negociações'
    ],
    [
        'id' => 'contas',
        'title' => 'Fluxo de contas/RAH',
        'keywords' => [
            'conta', 'rah', 'auditoria', 'capeante', 'contas finalizadas', 'contas paradas',
            'jornada da conta', 'faturamento'
        ],
        'summary' => 'Use a lista Contas > Contas para Auditar para controlar prioridades e avançar etapas do RAH.',
        'steps' => [
            'Revise o CAPEANTE gerado (cad_capeante_rah.php) e valide se há divergências.',
            'Utilize o status da jornada para sinalizar quando a conta estiver com o hospital ou seguradora.',
            'Ao finalizar, gere o PDF correspondente e mova a conta para Contas Finalizadas.'
        ],
        'link' => 'manual_contas.html',
        'link_label' => 'Manual de Contas'
    ],
    [
        'id' => 'senha_suporte',
        'title' => 'Suporte a credenciais e permissões',
        'keywords' => [
            'senha', 'login', 'acesso', 'permissao', 'usuario sem acesso', 'erro login',
            'troca de senha', 'reset senha'
        ],
        'summary' => 'Resets podem ser feitos via Administração > Usuários, mas confirme se o colaborador finalizou o onboarding.',
        'steps' => [
            'Em Usuários, localize o colaborador e clique em editar para resetar a senha.',
            'Informe ao colaborador que o primeiro acesso exige troca imediata.',
            'Se for permissão de módulo, ajuste o nível e valide o menu disponível.'
        ],
        'link' => 'manual_usuarios.html',
        'link_label' => 'Manual de Usuários'
    ],
    [
        'id' => 'dashboard',
        'title' => 'Uso dos dashboards operacionais',
        'keywords' => [
            'dashboard', 'painel', 'indicadores', 'gestao', 'kpi', 'performance equipes',
            'dashboard 360', 'painel mensal'
        ],
        'summary' => 'Escolha o dashboard alinhado com a reunião (operacional, performance ou mensal) e valide filtros antes de exportar.',
        'steps' => [
            'Dashboard 360° reúne visão geral das internações, utilize filtros por hospital/seguradora.',
            'O painel de Performance destaca metas por equipe e visita, atualize o período antes de exportar.',
            'O Painel Mensal compara realizado x previsão e se integra ao módulo de Faturamento.'
        ],
        'link' => 'dashboard-operacional',
        'link_label' => 'Dashboard Operacional'
    ]
];
