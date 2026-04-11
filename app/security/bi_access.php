<?php

if (!function_exists('fullcare_norm_role')) {
    function fullcare_norm_role($txt): string
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

if (!function_exists('fullcare_is_gestor_seguradora')) {
    function fullcare_is_gestor_seguradora(): bool
    {
        $cargo = fullcare_norm_role($_SESSION['cargo'] ?? '');
        if ($cargo === '') {
            return false;
        }

        if (in_array($cargo, [
            'gestorseguradora',
            'gestorplanosaude',
            'gestoroperadora',
            'gestoroperadorasaude'
        ], true)) {
            return true;
        }

        $isGestor = strpos($cargo, 'gestor') !== false;
        $isOperadora = strpos($cargo, 'seguradora') !== false
            || strpos($cargo, 'planosaude') !== false
            || strpos($cargo, 'operadora') !== false;

        return $isGestor && $isOperadora;
    }
}

if (!function_exists('fullcare_has_bi_access')) {
    function fullcare_is_bi_allowed_user(): bool
    {
        $candidates = [
            fullcare_norm_role($_SESSION['usuario_user'] ?? ''),
            fullcare_norm_role($_SESSION['login_user'] ?? ''),
            fullcare_norm_role($_SESSION['email_user'] ?? ''),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }
            if (
                $candidate === 'robertocrisppi'
                || strpos($candidate, 'robertocrisppi') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    function fullcare_is_diretoria_bi(): bool
    {
        $cargo = fullcare_norm_role($_SESSION['cargo'] ?? '');
        $nivelRaw = (string)($_SESSION['nivel'] ?? '');
        $nivel = fullcare_norm_role($nivelRaw);
        $nivelInt = (int)$nivelRaw;

        return in_array($cargo, ['diretoria', 'diretor', 'administrador', 'admin', 'board'], true)
            || strpos($cargo, 'diretor') !== false
            || strpos($cargo, 'diretoria') !== false
            || in_array($nivel, ['diretoria', 'diretor', 'administrador', 'admin', 'board'], true)
            || ($nivelInt === -1);
    }

    function fullcare_has_bi_access(): bool
    {
        $idUsuario = (int)($_SESSION['id_usuario'] ?? 0);
        $ativo = strtolower((string)($_SESSION['ativo'] ?? ''));

        if ($idUsuario <= 0 || $ativo !== 's') {
            return false;
        }
        if (fullcare_is_bi_allowed_user()) {
            return true;
        }
        if (!fullcare_is_gestor_seguradora() && !fullcare_is_diretoria_bi()) {
            return false;
        }

        return true;
    }
}

if (!function_exists('fullcare_is_bi_request')) {
    function fullcare_is_bi_request(): bool
    {
        $uriPath = strtolower((string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ''));
        if ($uriPath !== '' && preg_match('#/(bi|bi/|bi$)#', $uriPath)) {
            return true;
        }

        $base = (string)basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($base === '') {
            return false;
        }
        if (preg_match('/^bi_/i', $base) || preg_match('/bi\.php$/i', $base)) {
            return true;
        }

        $legacyBiPages = [
            'Indicadores.php',
            'GrupoPatologia.php',
            'Antecedente.php',
            'AltoCusto.php',
            'HomeCare.php',
            'Desospitalizacao.php',
            'EventoAdverso.php',
            'Opme.php',
            'Producao.php',
            'Sinistro.php',
        ];

        return in_array($base, $legacyBiPages, true);
    }
}

if (!function_exists('fullcare_bi_deny_redirect')) {
    function fullcare_bi_deny_redirect(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['mensagem'] = 'Acesso ao BI permitido para gestor de seguradora e diretoria.';
        $_SESSION['mensagem_tipo'] = 'danger';
        header('Location: menu_app.php', true, 303);
        exit;
    }
}

if (!function_exists('fullcare_enforce_bi_access')) {
    function fullcare_enforce_bi_access(): void
    {
        if (!fullcare_is_bi_request()) {
            return;
        }
        if (fullcare_has_bi_access()) {
            return;
        }
        fullcare_bi_deny_redirect();
    }
}
