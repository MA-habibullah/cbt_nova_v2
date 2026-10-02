<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate or reuse captcha code
if (empty($_SESSION['captcha_code']) || isset($_GET['refresh'])) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $cap = '';
    for ($i = 0; $i < 5; $i++) {
        $cap .= $chars[random_int(0, strlen($chars) - 1)];
    }
    $_SESSION['captcha_code'] = $cap;
} else {
    $cap = $_SESSION['captcha_code'];
}

// No-cache headers
header('Content-Type: image/png');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Create image 140x45
$width  = 140;
$height = 45;
$image  = imagecreatetruecolor($width, $height);

// Light gray background
$bg    = imagecolorallocate($image, 235, 235, 235);
$black = imagecolorallocate($image, 0, 0, 0);
imagefill($image, 0, 0, $bg);

// Noise lines
for ($i = 0; $i < 6; $i++) {
    $lineColor = imagecolorallocate($image, random_int(150, 210), random_int(150, 210), random_int(150, 210));
    imageline($image, random_int(0, $width), random_int(0, $height), random_int(0, $width), random_int(0, $height), $lineColor);
}

// Noise dots
for ($i = 0; $i < 60; $i++) {
    $dotColor = imagecolorallocate($image, random_int(100, 200), random_int(100, 200), random_int(100, 200));
    imagesetpixel($image, random_int(0, $width), random_int(0, $height), $dotColor);
}

// Draw characters using built-in font 5
$charWidth  = imagefontwidth(5);
$charHeight = imagefontheight(5);
$totalWidth = strlen($cap) * ($charWidth + 4);
$startX     = (int)(($width - $totalWidth) / 2);
$startY     = (int)(($height - $charHeight) / 2);

$colors = [
    imagecolorallocate($image, 30,  80,  180),
    imagecolorallocate($image, 180, 30,  30),
    imagecolorallocate($image, 30,  140, 60),
    imagecolorallocate($image, 140, 30,  140),
    imagecolorallocate($image, 200, 100, 10),
];

for ($i = 0; $i < strlen($cap); $i++) {
    $x = $startX + $i * ($charWidth + 4);
    $y = $startY + random_int(-3, 3);
    imagestring($image, 5, $x, $y, $cap[$i], $colors[$i % count($colors)]);
}

imagepng($image);
imagedestroy($image);
