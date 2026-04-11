<?php

if (!function_exists('fullcare_intel_norm_role')) {
    function fullcare_intel_norm_role($txt): string
    {
        $txt = trim((string)$txt);
        if ($txt === '') {
            return '';
        }
        if (function_exists('iconv')) {
            $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $txt);
            if ($conv !== false) {
                $txt = $conv;
            }
        }
        $txt = strtolower($txt);
        return preg_replace('/[^a-z]/', '', $txt);
    }
}

if (!function_exists('fullcare_is_diretoria_inteligencia')) {
    function fullcare_is_diretoria_inteligencia(): bool
    {
        $cargo = fullcare_intel_norm_role($_SESSION['cargo'] ?? '');
        $nivelRaw = (string)($_SESSION['nivel'] ?? '');
        $nivel = fullcare_intel_norm_role($nivelRaw);
        $nivelInt = (int)$nivelRaw;

        return in_array($cargo, ['diretoria', 'diretor', 'administrador', 'admin', 'board'], true)
            || strpos($cargo, 'diretor') !== false
            || strpos($cargo, 'diretoria') !== false
            || in_array($nivel, ['diretoria', 'diretor', 'administrador', 'admin', 'board'], true)
            || ($nivelInt === -1);
    }
}

if (!function_exists('fullcare_is_inteligencia_request')) {
    function fullcare_is_inteligencia_request(): bool
    {
        $uriPath = strtolower((string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ''));
        if ($uriPath !== '' && preg_match('#/inteligencia(/|$)#', $uriPath)) {
            return true;
        }

        $script = (string)basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($script === '') {
            return false;
        }

        $intelPages = [
            'dashboard_operacional.php',
            'dashboard_performance.php',
            'faturamento_previsao.php',
            'dashboard_mensal.php',
            'inteligencia_operadora.php',
            'relatorio_tmp.php',
            'relatorio_prorrogacao_vs_alta.php',
            'relatorio_motivos_prorrogacao.php',
            'relatorio_backlog_autorizacoes.php',
            'operational_intelligence.php',
            'permanencia_alertas.php',
            'explicabilidade_insights.php',
            'risco_glosa.php',
            'clusterizacao_clinica.php',
            'text_automation.php',
            'inteligencia_logs_usuario.php',
        ];

        return in_array($script, $intelPages, true);
    }
}

if (!function_exists('fullcare_enforce_inteligencia_access')) {
    function fullcare_enforce_inteligencia_access(): void
    {
        if (!fullcare_is_inteligencia_request()) {
            return;
        }
        if (fullcare_is_diretoria_inteligencia()) {
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['mensagem'] = 'Acesso à Inteligência Operacional permitido somente para diretoria.';
        $_SESSION['mensagem_tipo'] = 'danger';
        header('Location: menu_app.php', true, 303);
        exit;
    }
}
