<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$out = $root . '/play_store_assets';
$logoPath = $root . '/assets/branding/fullcare_footer_logo.png';
$font = '/System/Library/Fonts/Supplemental/Arial.ttf';
$fontBold = '/System/Library/Fonts/Supplemental/Arial Bold.ttf';

if (!is_dir($out)) {
    mkdir($out, 0775, true);
}

function c($img, string $hex, int $alpha = 0): int
{
    $hex = ltrim($hex, '#');
    return imagecolorallocatealpha(
        $img,
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
        $alpha
    );
}

function rr($img, int $x, int $y, int $w, int $h, int $r, int $color): void
{
    imagefilledrectangle($img, $x + $r, $y, $x + $w - $r, $y + $h, $color);
    imagefilledrectangle($img, $x, $y + $r, $x + $w, $y + $h - $r, $color);
    imagefilledellipse($img, $x + $r, $y + $r, $r * 2, $r * 2, $color);
    imagefilledellipse($img, $x + $w - $r, $y + $r, $r * 2, $r * 2, $color);
    imagefilledellipse($img, $x + $r, $y + $h - $r, $r * 2, $r * 2, $color);
    imagefilledellipse($img, $x + $w - $r, $y + $h - $r, $r * 2, $r * 2, $color);
}

function text($img, string $s, int $size, int $x, int $y, int $color, string $font): void
{
    imagettftext($img, $size, 0, $x, $y, $color, $font, $s);
}

function wrapped($img, string $s, int $size, int $x, int $y, int $max, int $line, int $color, string $font): int
{
    $words = explode(' ', $s);
    $buf = '';
    foreach ($words as $word) {
        $test = trim($buf . ' ' . $word);
        $box = imagettfbbox($size, 0, $font, $test);
        if (($box[2] - $box[0]) > $max && $buf !== '') {
            text($img, $buf, $size, $x, $y, $color, $font);
            $y += $line;
            $buf = $word;
        } else {
            $buf = $test;
        }
    }
    if ($buf !== '') {
        text($img, $buf, $size, $x, $y, $color, $font);
        $y += $line;
    }
    return $y;
}

function pasteLogo($img, string $logoPath, int $x, int $y, int $size): void
{
    $logo = imagecreatefrompng($logoPath);
    imagealphablending($img, true);
    imagesavealpha($img, true);
    imagecopyresampled($img, $logo, $x, $y, 0, 0, $size, $size, imagesx($logo), imagesy($logo));
    imagedestroy($logo);
}

function base(int $w, int $h, string $bg)
{
    $img = imagecreatetruecolor($w, $h);
    imagesavealpha($img, true);
    imagefilledrectangle($img, 0, 0, $w, $h, c($img, $bg));
    return $img;
}

function drawStatus($img, int $w, string $font, int $color): void
{
    text($img, '9:44', 26, 54, 62, $color, $font);
    imagefilledellipse($img, $w - 120, 48, 22, 22, $color);
    imagefilledrectangle($img, $w - 86, 36, $w - 60, 60, $color);
    imagefilledrectangle($img, $w - 55, 42, $w - 49, 54, $color);
}

function savePng($img, string $path): void
{
    imagepng($img, $path, 9);
    imagedestroy($img);
}

function phoneFrame($img, int $x, int $y, int $w, int $h): array
{
    rr($img, $x, $y, $w, $h, 48, c($img, '#0b1320'));
    rr($img, $x + 14, $y + 14, $w - 28, $h - 28, 38, c($img, '#f3f7fb'));
    imagefilledellipse($img, $x + $w / 2, $y + 36, 26, 26, c($img, '#0b1320'));
    return [$x + 14, $y + 14, $w - 28, $h - 28];
}

function drawMiniPhoneHome($img, int $x, int $y, int $w, int $h, string $font, string $bold): void
{
    $blue = c($img, '#116fb0');
    $ink = c($img, '#263241');
    $muted = c($img, '#667085');
    rr($img, $x, $y, $w, 84, 24, $blue);
    text($img, 'Audit', 24, $x + 22, $y + 55, c($img, '#ffffff'), $bold);
    rr($img, $x + 20, $y + 116, $w - 40, 82, 16, c($img, '#ffffff'));
    text($img, 'Gestao', 18, $x + 38, $y + 150, $ink, $bold);
    text($img, 'Auditoria', 14, $x + 38, $y + 174, $muted, $font);
    $cy = $y + 224;
    foreach (['Operacional', 'Conformidade', 'Indicadores'] as $label) {
        rr($img, $x + 20, $cy, $w - 40, 58, 14, c($img, '#ffffff'));
        imagefilledellipse($img, $x + 48, $cy + 29, 24, 24, c($img, '#25b8d6'));
        text($img, $label, 15, $x + 72, $cy + 36, $ink, $font);
        $cy += 72;
    }
}

function drawHome($img, int $x, int $y, int $w, int $h, string $font, string $bold): void
{
    $blue = c($img, '#116fb0');
    $white = c($img, '#ffffff');
    $ink = c($img, '#263241');
    $muted = c($img, '#667085');
    rr($img, $x, $y, $w, 190, 32, $blue);
    drawStatus($img, $w, $font, $white);
    text($img, 'FullCare Audit', 32, $x + 54, $y + 128, $white, $bold);
    rr($img, $x + 34, $y + 230, $w - 68, 220, 22, c($img, '#ffffff'));
    text($img, 'Gestao de auditoria', 28, $x + 64, $y + 292, $ink, $bold);
    wrapped($img, 'Controles operacionais, conformidade e evidencias em ambiente restrito.', 20, $x + 64, $y + 338, $w - 128, 30, $muted, $font);
    $rows = [
        ['Auditoria operacional', 'Atividades e responsaveis'],
        ['Conformidade', 'Registros e evidencias'],
        ['Indicadores gerenciais', 'Acompanhamento administrativo'],
    ];
    $cy = $y + 500;
    foreach ($rows as $i => $row) {
        rr($img, $x + 34, $cy, $w - 68, 164, 22, c($img, '#ffffff'));
        imagefilledellipse($img, $x + 82, $cy + 82, 52, 52, c($img, $i === 1 ? '#5b1b67' : '#25b8d6'));
        text($img, $row[0], 24, $x + 130, $cy + 72, $ink, $bold);
        text($img, $row[1], 19, $x + 130, $cy + 105, $muted, $font);
        $cy += 184;
    }
}

function drawLogin($img, int $w, int $h, string $font, string $bold, string $logoPath): void
{
    $blue = c($img, '#116fb0');
    $white = c($img, '#ffffff');
    $ink = c($img, '#263241');
    $muted = c($img, '#667085');
    rr($img, 0, 0, $w, 360, 0, $blue);
    drawStatus($img, $w, $font, $white);
    pasteLogo($img, $logoPath, ($w - 190) / 2, 122, 190);
    rr($img, 54, 300, $w - 108, 1180, 34, c($img, '#ffffff'));
    text($img, 'FullCare Audit', 42, 116, 430, $ink, $bold);
    wrapped($img, 'Acesso seguro para gestao de auditoria e controles operacionais.', 23, 116, 482, $w - 232, 34, $muted, $font);
    rr($img, 116, 610, $w - 232, 86, 18, c($img, '#f7fafc'));
    text($img, 'Usuario', 22, 146, 663, c($img, '#4b5563'), $font);
    rr($img, 116, 730, $w - 232, 86, 18, c($img, '#f7fafc'));
    text($img, 'Senha', 22, 146, 783, c($img, '#4b5563'), $font);
    rr($img, 116, 880, $w - 232, 92, 22, $blue);
    text($img, 'Entrar', 26, 475, 938, $white, $bold);
    text($img, 'Politica de privacidade', 20, 398, 1065, $blue, $font);
}

function drawAuditList($img, int $w, int $h, string $font, string $bold): void
{
    $blue = c($img, '#116fb0');
    $white = c($img, '#ffffff');
    $ink = c($img, '#263241');
    $muted = c($img, '#667085');
    imagefilledrectangle($img, 0, 0, $w, $h, c($img, '#eef4fb'));
    rr($img, 0, 0, $w, 190, 0, $blue);
    drawStatus($img, $w, $font, $white);
    text($img, 'Auditorias', 34, 94, 130, $white, $bold);
    rr($img, 58, 250, $w - 116, 86, 24, $white);
    text($img, 'Pesquisar beneficiario ou prestador', 22, 92, 304, $muted, $font);
    text($img, 'Total de registros: 50', 22, 58, 400, $blue, $bold);
    $names = ['APARECIDA ROMERO GOGORA', 'ANDERSON DE ALMEIDA', 'ANA JALES OLIVEIRA', 'AMANDA RIBEIRO MORALES'];
    $cy = 455;
    foreach ($names as $i => $name) {
        rr($img, 54, $cy, $w - 108, 220, 22, c($img, '#ffffff'));
        text($img, $name, 24, 88, $cy + 62, $ink, $bold);
        text($img, 'Prestador: BP Mirante - Sao Jose', 21, 88, $cy + 105, $muted, $font);
        text($img, 'Convenio: MEDISERVICE', 21, 88, $cy + 141, $muted, $font);
        text($img, 'Status: em analise', 21, 88, $cy + 177, $muted, $font);
        text($img, '>', 34, $w - 112, $cy + 128, c($img, '#4b5563'), $bold);
        $cy += 248;
    }
}

function drawCompliance($img, int $w, int $h, string $font, string $bold, string $logoPath): void
{
    $blue = c($img, '#116fb0');
    $white = c($img, '#ffffff');
    $ink = c($img, '#263241');
    $muted = c($img, '#667085');
    imagefilledrectangle($img, 0, 0, $w, $h, c($img, '#f5f8fc'));
    drawStatus($img, $w, $font, $ink);
    pasteLogo($img, $logoPath, 54, 96, 74);
    text($img, 'Conformidade', 34, 148, 146, $ink, $bold);
    text($img, 'Gestao de auditoria', 21, 148, 180, $muted, $font);
    $stats = [['24', 'Atividades'], ['8', 'Pendencias'], ['96%', 'Concluido']];
    $x = 54;
    foreach ($stats as $i => $stat) {
        rr($img, $x, 250, 300, 180, 24, $white);
        text($img, $stat[0], 40, $x + 28, 330, $i === 1 ? c($img, '#5b1b67') : $blue, $bold);
        text($img, $stat[1], 21, $x + 28, 375, $muted, $font);
        $x += 330;
    }
    rr($img, 54, 490, $w - 108, 360, 24, $white);
    text($img, 'Evidencias recentes', 26, 88, 555, $ink, $bold);
    $rows = ['Documento revisado', 'Checklist atualizado', 'Registro validado', 'Relatorio preparado'];
    $cy = 620;
    foreach ($rows as $i => $row) {
        imagefilledellipse($img, 105, $cy - 9, 24, 24, c($img, $i === 1 ? '#5b1b67' : '#25b8d6'));
        text($img, $row, 22, 132, $cy, $ink, $font);
        text($img, 'Hoje', 19, $w - 158, $cy, $muted, $font);
        $cy += 58;
    }
    rr($img, 54, 920, $w - 108, 240, 24, c($img, '#e8f5f9'));
    text($img, 'Acompanhamento restrito', 26, 88, 988, $ink, $bold);
    wrapped($img, 'Tela voltada a controles administrativos, trilhas de auditoria e organizacao de evidencias.', 21, 88, 1035, $w - 176, 32, $muted, $font);
}

function feature(string $path, string $font, string $bold, string $logoPath): void
{
    $img = base(1024, 500, '#f3f8fc');
    $blue = c($img, '#116fb0');
    $purple = c($img, '#5b1b67');
    $ink = c($img, '#263241');
    pasteLogo($img, $logoPath, 58, 72, 108);
    text($img, 'FullCare Audit', 56, 58, 238, $ink, $bold);
    wrapped($img, 'Gestao de auditoria e conformidade para operacoes restritas.', 27, 62, 292, 470, 38, c($img, '#526070'), $font);
    rr($img, 62, 400, 320, 58, 20, $blue);
    text($img, 'Auditoria gerencial', 23, 91, 438, c($img, '#ffffff'), $bold);
    [$px, $py, $pw, $ph] = phoneFrame($img, 668, 40, 250, 420);
    drawMiniPhoneHome($img, $px, $py, $pw, $ph, $font, $bold);
    imagefilledellipse($img, 596, 396, 120, 120, c($img, '#25b8d6', 20));
    imagefilledellipse($img, 940, 96, 86, 86, c($img, '#5b1b67', 40));
    savePng($img, $path);
}

function icon(string $path, string $logoPath): void
{
    $img = base(512, 512, '#116fb0');
    rr($img, 28, 28, 456, 456, 96, c($img, '#ffffff'));
    pasteLogo($img, $logoPath, 84, 72, 344);
    savePng($img, $path);
}

icon($out . '/icon-512.png', $logoPath);
feature($out . '/feature-graphic-1024x500.png', $font, $fontBold, $logoPath);

$screen = base(1080, 1920, '#ffffff');
drawLogin($screen, 1080, 1920, $font, $fontBold, $logoPath);
savePng($screen, $out . '/phone-01-login.png');

$screen = base(1080, 1920, '#eef4fb');
drawHome($screen, 0, 0, 1080, 1920, $font, $fontBold);
savePng($screen, $out . '/phone-02-home.png');

$screen = base(1080, 1920, '#eef4fb');
drawAuditList($screen, 1080, 1920, $font, $fontBold);
savePng($screen, $out . '/phone-03-auditorias.png');

$screen = base(1080, 1920, '#ffffff');
drawCompliance($screen, 1080, 1920, $font, $fontBold, $logoPath);
savePng($screen, $out . '/phone-04-conformidade.png');

echo "Assets created in {$out}\n";
