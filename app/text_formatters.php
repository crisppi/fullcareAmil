<?php

if (!function_exists('fc_sentence_case')) {
    function fc_sentence_case($value): string
    {
        $text = trim((string)$value);
        if ($text === '') {
            return '';
        }

        $lower = mb_strtolower($text, 'UTF-8');
        return mb_strtoupper(mb_substr($lower, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($lower, 1, null, 'UTF-8');
    }
}

