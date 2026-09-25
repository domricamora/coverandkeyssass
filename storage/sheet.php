<?php
$dir = 'public/img/demo/tour'; $out = 'storage/sheets'; @mkdir($out);
foreach (['room','bath','lobby','pool','breakfast','spa','dining','dish','bar','kitchen','terrace'] as $cat) {
    $files = glob("$dir/$cat-*.jpg"); sort($files);
    $w = 300; $h = 200; $sheet = imagecreatetruecolor($w * 4, $h * 2);
    foreach ($files as $i => $f) {
        $src = @imagecreatefromjpeg($f); if (!$src) { echo "BAD $f\n"; continue; }
        imagecopyresampled($sheet, $src, ($i % 4) * $w, intdiv($i, 4) * $h, 0, 0, $w, $h, imagesx($src), imagesy($src));
        imagestring($sheet, 5, ($i % 4) * $w + 6, intdiv($i, 4) * $h + 6, (string) $i, imagecolorallocate($sheet, 255, 0, 0));
    }
    imagejpeg($sheet, "$out/sheet_$cat.jpg", 80);
}
