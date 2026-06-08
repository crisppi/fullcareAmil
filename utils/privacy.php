<?php

if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $string, ?string $encoding = null): string
    {
        return strtolower($string);
    }
}

if (!function_exists('mb_substr')) {
    function mb_substr(string $string, int $start, ?int $length = null, ?string $encoding = null): string
    {
        return $length === null ? substr($string, $start) : substr($string, $start, $length);
    }
}

if (!function_exists('fullcare_mask_person_name')) {
    function fullcare_mask_person_name($name): string
    {
        $normalizedName = preg_replace('/\s+/u', ' ', (string)$name);
        $raw = trim(is_string($normalizedName) ? $normalizedName : (string)$name);
        if ($raw === '') {
            return '';
        }

        $parts = preg_split('/\s+/u', $raw) ?: [];
        $parts = array_values(array_filter($parts, static function ($part): bool {
            return trim((string)$part) !== '';
        }));

        if (!$parts) {
            return '';
        }

        if (count($parts) === 1) {
            return mb_substr($parts[0], 0, 1, 'UTF-8') . '.';
        }

        $particles = ['da', 'das', 'de', 'do', 'dos', 'e'];
        $masked = [$parts[0]];

        foreach (array_slice($parts, 1) as $part) {
            $normalized = mb_strtolower(trim($part, " \t\n\r\0\x0B.-'"), 'UTF-8');
            if ($normalized === '' || in_array($normalized, $particles, true)) {
                continue;
            }

            $masked[] = mb_substr($part, 0, 1, 'UTF-8') . '.';
        }

        return implode(' ', $masked);
    }
}

if (!function_exists('fullcare_mask_person_name_e')) {
    function fullcare_mask_person_name_e($name): string
    {
        return htmlspecialchars(fullcare_mask_person_name($name), ENT_QUOTES, 'UTF-8');
    }
}
