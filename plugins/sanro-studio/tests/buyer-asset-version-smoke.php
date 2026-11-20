<?php
// Isolated regression: buyer.js needs content-derived versioning while SANRO_STUDIO_VERSION stays fixed.
define('ABSPATH', __DIR__);
define('SANRO_STUDIO_VERSION', 'qa-fixed-version');
require_once __DIR__ . '/../includes/buyer.php';

$file = realpath(__DIR__ . '/../assets/buyer.js');
if (!$file || !is_file($file)) {
    fwrite(STDERR, "FAIL: buyer.js missing\n");
    exit(1);
}
$hash = hash_file('sha256', $file);
$expected = SANRO_STUDIO_VERSION . '-' . substr($hash, 0, 12);
$actual = sanro_studio_buyer_script_version();
if ($actual !== $expected || !preg_match('/^qa-fixed-version-[a-f0-9]{12}$/D', $actual)) {
    fwrite(STDERR, "FAIL: buyer.js cache version is not derived from asset content\n");
    exit(1);
}
// Detect accidental regression to the static plugin version.
if ($actual === SANRO_STUDIO_VERSION) {
    fwrite(STDERR, "FAIL: buyer.js version is static\n");
    exit(1);
}
echo "PASS: 3 SANRO Studio buyer asset fingerprint assertions\n";
