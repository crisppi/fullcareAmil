<?php

$out = __DIR__ . '/../img/producao_preview.png';

$w = 1640;
$h = 820;
$img = imagecreatetruecolor($w, $h);
imagesavealpha($img, true);
imagealphablending($img, false);
$transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
imagefill($img, 0, 0, $transparent);
imagealphablending($img, true);

$fontDir = __DIR__ . '/../diversos/CoolAdmin-master/fonts/poppins';
$fontRegular = $fontDir . '/poppins-v5-latin-regular.ttf';
$fontSemi = $fontDir . '/poppins-v5-latin-600.ttf';
$fontBold = $fontDir . '/poppins-v5-latin-700.ttf';
foreach ([$fontRegular, $fontSemi, $fontBold] as $font) {
    if (!is_file($font)) {
        $fontRegular = $fontSemi = $fontBold = null;
        break;
    }
}

function rgba($im, int $r, int $g, int $b, int $a = 0): int
{
    return imagecolorallocatealpha($im, $r, $g, $b, $a);
}

function roundedRect($im, int $x, int $y, int $w, int $h, int $r, int $color): void
{
    imagefilledrectangle($im, $x + $r, $y, $x + $w - $r, $y + $h, $color);
    imagefilledrectangle($im, $x, $y + $r, $x + $w, $y + $h - $r, $color);
    imagefilledellipse($im, $x + $r, $y + $r, $r * 2, $r * 2, $color);
    imagefilledellipse($im, $x + $w - $r, $y + $r, $r * 2, $r * 2, $color);
    imagefilledellipse($im, $x + $r, $y + $h - $r, $r * 2, $r * 2, $color);
    imagefilledellipse($im, $x + $w - $r, $y + $h - $r, $r * 2, $r * 2, $color);
}

function strokeRoundedRect($im, int $x, int $y, int $w, int $h, int $r, int $color, int $thickness = 1): void
{
    imagesetthickness($im, $thickness);
    imageline($im, $x + $r, $y, $x + $w - $r, $y, $color);
    imageline($im, $x + $r, $y + $h, $x + $w - $r, $y + $h, $color);
    imageline($im, $x, $y + $r, $x, $y + $h - $r, $color);
    imageline($im, $x + $w, $y + $r, $x + $w, $y + $h - $r, $color);
    imagearc($im, $x + $r, $y + $r, $r * 2, $r * 2, 180, 270, $color);
    imagearc($im, $x + $w - $r, $y + $r, $r * 2, $r * 2, 270, 360, $color);
    imagearc($im, $x + $w - $r, $y + $h - $r, $r * 2, $r * 2, 0, 90, $color);
    imagearc($im, $x + $r, $y + $h - $r, $r * 2, $r * 2, 90, 180, $color);
    imagesetthickness($im, 1);
}

function textOrBar($im, ?string $font, int $size, int $x, int $y, string $text, int $color, int $barW = 80): void
{
    if ($font) {
        imagettftext($im, $size, 0, $x, $y, $color, $font, $text);
        return;
    }
    roundedRect($im, $x, $y - 12, $barW, 8, 4, $color);
}

function linePath($im, array $points, int $color, int $thickness = 8): void
{
    imagesetthickness($im, $thickness);
    for ($i = 1, $n = count($points); $i < $n; $i++) {
        imageline($im, $points[$i - 1][0], $points[$i - 1][1], $points[$i][0], $points[$i][1], $color);
    }
    imagesetthickness($im, 1);
}

function makeShadow(int $w, int $h, int $x, int $y, int $rw, int $rh, int $r, int $alpha)
{
    $shadow = imagecreatetruecolor($w, $h);
    imagesavealpha($shadow, true);
    imagealphablending($shadow, false);
    imagefill($shadow, 0, 0, imagecolorallocatealpha($shadow, 0, 0, 0, 127));
    imagealphablending($shadow, true);
    roundedRect($shadow, $x, $y, $rw, $rh, $r, imagecolorallocatealpha($shadow, 36, 20, 49, $alpha));
    for ($i = 0; $i < 12; $i++) {
        imagefilter($shadow, IMG_FILTER_GAUSSIAN_BLUR);
    }
    return $shadow;
}

// Notebook body and screen with a more professional BI preview.
imagecopy($img, makeShadow($w, $h, 222, 112, 1196, 498, 28, 88), 0, 0, 0, 0, $w, $h);
roundedRect($img, 226, 108, 1188, 526, 28, rgba($img, 18, 24, 36));
roundedRect($img, 238, 120, 1164, 502, 14, rgba($img, 242, 247, 252));

// App chrome.
roundedRect($img, 238, 120, 1164, 58, 14, rgba($img, 44, 18, 58));
imagefilledrectangle($img, 238, 154, 1402, 182, rgba($img, 44, 18, 58));
foreach ([[286, 151, 121, 199, 255], [322, 151, 255, 198, 108], [358, 151, 111, 223, 194]] as $dot) {
    imagefilledellipse($img, $dot[0], $dot[1], 16, 16, rgba($img, $dot[2], $dot[3], $dot[4]));
}
textOrBar($img, $fontBold, 18, 410, 158, 'FullCare BI', rgba($img, 255, 255, 255), 145);
textOrBar($img, $fontSemi, 11, 1168, 158, 'GESTÃO ASSISTENCIAL', rgba($img, 227, 215, 235), 168);

// Sidebar.
imagefilledrectangle($img, 238, 180, 430, 622, rgba($img, 30, 46, 68));
textOrBar($img, $fontBold, 11, 272, 232, 'NAVEGAÇÃO BI', rgba($img, 180, 208, 223), 106);
$menuItems = [
    [266, 270, 'Resumo', true],
    [266, 312, 'Hospitais', false],
    [266, 354, 'Contas', false],
    [266, 396, 'Glosas', false],
    [266, 438, 'Rede', false],
];
foreach ($menuItems as $item) {
    roundedRect($img, $item[0], $item[1], 132, 28, 8, $item[3] ? rgba($img, 104, 201, 185, 12) : rgba($img, 255, 255, 255, 116));
    textOrBar($img, $fontSemi, 10, $item[0] + 16, $item[1] + 19, $item[2], $item[3] ? rgba($img, 20, 58, 67) : rgba($img, 182, 198, 215), 70);
}

// Content header and filters.
textOrBar($img, $fontBold, 20, 462, 230, 'Painel Executivo', rgba($img, 33, 47, 70), 190);
textOrBar($img, $fontRegular, 11, 464, 252, 'Indicadores de produção, contas e rede hospitalar', rgba($img, 107, 122, 142), 255);
roundedRect($img, 1058, 212, 270, 36, 14, rgba($img, 255, 255, 255));
strokeRoundedRect($img, 1058, 212, 270, 36, 14, rgba($img, 214, 224, 236), 1);
textOrBar($img, $fontSemi, 10, 1078, 235, 'Período: últimos 6 meses', rgba($img, 82, 96, 116), 170);

// KPI cards.
$kpis = [
    [462, 286, 'Internações', '1.248', 77, 151, 214],
    [670, 286, 'Contas auditadas', '252', 94, 123, 255],
    [878, 286, 'Glosa total', 'R$ 289k', 118, 207, 196],
    [1086, 286, 'MP médio', '4,8 dias', 192, 110, 163],
];
foreach ($kpis as $kpi) {
    roundedRect($img, $kpi[0], $kpi[1], 178, 92, 18, rgba($img, 255, 255, 255));
    strokeRoundedRect($img, $kpi[0], $kpi[1], 178, 92, 18, rgba($img, 217, 227, 239), 1);
    roundedRect($img, $kpi[0] + 16, $kpi[1] + 18, 28, 28, 9, rgba($img, $kpi[4], $kpi[5], $kpi[6], 18));
    textOrBar($img, $fontSemi, 9, $kpi[0] + 54, $kpi[1] + 35, $kpi[2], rgba($img, 105, 119, 138), 92);
    textOrBar($img, $fontBold, 19, $kpi[0] + 54, $kpi[1] + 66, $kpi[3], rgba($img, 31, 43, 63), 88);
}

// Main line chart.
roundedRect($img, 462, 410, 534, 178, 18, rgba($img, 255, 255, 255));
strokeRoundedRect($img, 462, 410, 534, 178, 18, rgba($img, 217, 227, 239), 1);
textOrBar($img, $fontBold, 14, 486, 442, 'Evolução mensal', rgba($img, 33, 47, 70), 130);
foreach ([480, 518, 556] as $gy) {
    imageline($img, 488, $gy, 966, $gy, rgba($img, 222, 230, 239, 42));
}
linePath($img, [[494, 548], [560, 520], [626, 532], [694, 494], [758, 506], [826, 472], [906, 484], [954, 456]], rgba($img, 77, 151, 214), 6);
linePath($img, [[494, 536], [560, 500], [626, 512], [694, 478], [758, 488], [826, 466], [906, 438], [954, 448]], rgba($img, 118, 207, 196), 5);
foreach ([[504, 568, 72], [608, 552, 96], [712, 532, 120], [816, 518, 146], [920, 494, 164]] as $bar) {
    roundedRect($img, $bar[0], $bar[1] - $bar[2] / 2, 28, (int)($bar[2] / 2), 6, rgba($img, 141, 208, 255, 34));
}

// Bar chart and compact table.
roundedRect($img, 1024, 410, 304, 178, 18, rgba($img, 255, 255, 255));
strokeRoundedRect($img, 1024, 410, 304, 178, 18, rgba($img, 217, 227, 239), 1);
textOrBar($img, $fontBold, 14, 1048, 442, 'Auditoria por hospital', rgba($img, 33, 47, 70), 154);
foreach ([[1056, 526, 34, 40], [1110, 502, 34, 64], [1164, 482, 34, 84], [1218, 510, 34, 56], [1272, 462, 34, 104]] as $bar) {
    roundedRect($img, $bar[0], $bar[1], $bar[2], $bar[3], 7, rgba($img, 77, 151, 214, 10));
}
linePath($img, [[1054, 506], [1118, 486], [1180, 496], [1240, 466], [1304, 454]], rgba($img, 255, 198, 108), 5);

// Compact bottom activity strip.
roundedRect($img, 462, 604, 866, 32, 12, rgba($img, 255, 255, 255));
strokeRoundedRect($img, 462, 604, 866, 32, 12, rgba($img, 217, 227, 239), 1);
foreach ([[486, 'Rede Alpha', 202], [724, 'Contas liberadas', 172], [938, 'Glosa monitorada', 168], [1148, 'Status OK', 126]] as $strip) {
    roundedRect($img, $strip[0], 613, $strip[2], 14, 7, rgba($img, 235, 242, 248));
    textOrBar($img, $fontSemi, 8, $strip[0] + 10, 624, $strip[1], rgba($img, 91, 108, 128), 70);
}

// Bottom screen edge and monitor pedestal.
imagefilledrectangle($img, 238, 610, 1402, 622, rgba($img, 229, 237, 246));
roundedRect($img, 226, 622, 1188, 12, 0, rgba($img, 16, 22, 35));
imagefilledellipse($img, 820, 632, 18, 18, rgba($img, 70, 82, 100, 24));

$stand = [
    764, 640,
    876, 640,
    912, 706,
    728, 706,
];
imagefilledpolygon($img, $stand, 4, rgba($img, 178, 187, 200, 32));
imagefilledpolygon($img, [780, 640, 860, 640, 884, 698, 756, 698], 4, rgba($img, 218, 225, 234, 38));
roundedRect($img, 704, 704, 232, 24, 12, rgba($img, 176, 185, 198, 34));
roundedRect($img, 646, 722, 348, 16, 8, rgba($img, 126, 138, 154, 82));
roundedRect($img, 724, 710, 192, 10, 5, rgba($img, 231, 236, 243, 42));

imagepng($img, $out, 9);
imagedestroy($img);

echo $out . PHP_EOL;
