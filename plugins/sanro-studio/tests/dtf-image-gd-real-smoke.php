<?php
/**
 * Sprint 134: REAL GD raster verification. Never substitute a no-GD mock.
 * Run only in the isolated PHP-GD container with synthetic image fixtures.
 */
if (!extension_loaded('gd') || !function_exists('imageresolution')
    || !function_exists('imagecreatefromstring')
    || !function_exists('imagecolorsforindex')) {
    fwrite(STDERR, "FAIL: real PHP GD extension is mandatory for Sprint 134\n");
    exit(2);
}
require __DIR__ . '/dtf-image-render-smoke.php';
umask(0077);
$count134 = 0;
function verify134($ok, $message) {
    global $count134;
    if (!$ok) {
        fwrite(STDERR, "FAIL: REAL_GD: " . $message . "\n");
        exit(1);
    }
    $count134++;
}

/** Synthetic, lossless RGBA8 fixture: one opaque red pixel, rest transparent. */
function png134($width, $height) {
    $opaque = "\xff\x00\x00\xff";
    $clear = "\x00\x00\x00\x00";
    $first = "\x00" . $opaque . str_repeat($clear, $width - 1);
    $empty = "\x00" . str_repeat($clear, $width);
    $pixels = $first . str_repeat($empty, $height - 1);
    return "\x89PNG\r\n\x1a\n"
        . chunk133('IHDR', pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0))
        . chunk133('IDAT', gzcompress($pixels, 9))
        . chunk133('IEND', '');
}

$order = fixture133();
$expected_pixels = (int) round(5.0 * 300 / 2.54); // 591 px at nominal 300 DPI
$GLOBALS['image133'] = png134($expected_pixels, $expected_pixels);
$GLOBALS['sha133'] = hash('sha256', $GLOBALS['image133']);
$GLOBALS['record133']['design']['front']['objects'][0] = [
    'type' => 'image', 'x' => 2.0, 'y' => 3.0, 'width' => 5.0, 'height' => 5.0,
];
verify134($expected_pixels === 591, 'physical 5-cm pixel target');
$plan = sanro_studio_dtf_image_plan($order, 71, 'front');
verify134(!empty($plan['valid']) && $plan['width_px'] === 591
    && $plan['height_px'] === 591 && $plan['dpi'] === 300, 'server render plan');
verify134($plan['offset_x_cm'] === 2.0 && $plan['offset_y_cm'] === 3.0,
    'physical placement retained in metadata');
$render = sanro_studio_dtf_render_image_side($order, 71, 'front');
verify134(!empty($render['valid']) && ($render['kind'] ?? null)
    === 'image_only_dtf_raster_candidate'
    && ($render['quality_approved'] ?? true) === false,
    'real GD rendered output remains explicitly unapproved');
verify134(($render['width_px'] ?? 0) === 591 && ($render['height_px'] ?? 0) === 591
    && abs($render['width_px'] * 2.54 / 300 - 5.0) < 0.01,
    'actual dimensions match 5 cm within rounding tolerance');
verify134(sanro_studio_dtf_png_has_300dpi($render['bytes']),
    'PNG pHYs chunk retains physical 300 DPI');
verify134(substr($render['bytes'], 0, 8) === "\x89PNG\r\n\x1a\n"
    && ord($render['bytes'][25]) === 6
    && $render['mime'] === 'image/png', 'real output is RGBA8 PNG');
$decoded = @imagecreatefromstring($render['bytes']);
verify134($decoded !== false
    && imagesx($decoded) === 591 && imagesy($decoded) === 591,
    'real GD decoder reads output pixel dimensions');
$red = imagecolorsforindex($decoded, imagecolorat($decoded, 0, 0));
verify134($red['red'] === 255 && $red['green'] === 0
    && $red['blue'] === 0 && $red['alpha'] === 0,
    'opaque red source pixel survives raster conversion');
$clear = imagecolorsforindex($decoded, imagecolorat($decoded, 590, 590));
verify134($clear['alpha'] >= 120,
    'transparent source pixel preserves near-full transparency');
imagedestroy($decoded);
verify134(hash_equals(hash('sha256', $render['bytes']), $render['sha256'])
    && $render['source_sha256'] === hash('sha256', $GLOBALS['image133'])
    && !isset($render['source_bytes']),
    'output SHA matches bytes and source bytes remain private');

$dir = getenv('SANRO_DTF_ARTIFACT_DIR');
verify134($dir === '/out' && is_dir($dir) && is_writable($dir),
    'dedicated private artifact mount required');
$file = $dir . '/synthetic-dtf-5cm-300dpi-rgba.png';
$manifest_path = $dir . '/synthetic-dtf-manifest.json';
$written = @file_put_contents($file, $render['bytes'], LOCK_EX);
verify134($written === strlen($render['bytes']) && is_file($file)
    && hash_equals(hash_file('sha256', $file), $render['sha256']),
    'private PNG artifact saved with verified digest');

$manifest = [
    'test' => 'sanro-studio-sprint134-gd-raster',
    'kind' => $render['kind'],
    'quality_approved' => false,
    'source' => 'synthetic-rgba-5cm',
    'width_px' => $render['width_px'],
    'height_px' => $render['height_px'],
    'width_cm' => $render['width_cm'],
    'height_cm' => $render['height_cm'],
    'offset_x_cm' => $render['offset_x_cm'],
    'offset_y_cm' => $render['offset_y_cm'],
    'dpi' => $render['dpi'],
    'mime' => 'image/png',
    'sha256' => $render['sha256'],
    'filename' => basename($file),
];
$json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
verify134(is_string($json)
    && @file_put_contents($manifest_path, $json . "\n", LOCK_EX) !== false
    && json_decode(file_get_contents($manifest_path), true)['sha256'] === $render['sha256'],
    'private manifest written and validated');
verify134(is_file($file) && filesize($file) > 64
    && filesize($file) <= 33554432,
    'generated artifact bounds');

echo "GD_RASTER_REAL: VERIFIED (" . $render['width_px']
    . "x" . $render['height_px'] . " RGBA, 300 DPI, alpha pixels tested)\n";
echo "PASS: {$count134} SANRO real PHP GD DTF raster assertions\n";
echo "OUTPUT_SHA256: " . $render['sha256'] . "\n";
