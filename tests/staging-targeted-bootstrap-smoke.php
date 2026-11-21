<?php
/**
 * Sprint 141 deterministic fixture for the TARGETED staging bootstrap patcher.
 * Never loads WordPress and never touches Hostinger or a customer order.
 */
$folder = sys_get_temp_dir() . '/sanro-staging-patch-'
    . bin2hex(random_bytes(8));
if (!mkdir($folder, 0700)) {
    fwrite(STDERR, "FAIL: cannot create private patch fixture\n"); exit(2);
}
$in = $folder . '/deployed.php';
$out = $folder . '/candidate.php';
$fixture = <<<'SOURCE'
<?php
/** Plugin Name: SANRO Studio */
defined('ABSPATH') || exit;
require_once __DIR__ . '/includes/order-preview.php';
add_action('woocommerce_after_order_itemmeta', 'sanro_studio_admin_item_preview_status', 10, 3);
// legacy plugin content must remain untouched
SOURCE;
file_put_contents($in, $fixture);
chmod($in, 0600);
$count = 0;
function patch_check($condition, $message) {
    global $count;
    if (!$condition) { fwrite(STDERR, "FAIL: " . $message . "\n"); exit(1); }
    $count++;
}
try {
    // Include the CLI patcher inside a local function scope: it uses its own
    // $count for preg_replace_callback, which must not mutate the fixture's
    // global $count assertion tally. All 14 assertions are independent.
    $message = (static function (string $input, string $candidate): string {
        $argv = [
            __DIR__ . '/../ops/hostinger/sanro-studio-pr31-bootstrap-preview.php',
            $input, $candidate,
        ];
        ob_start();
        try {
            require __DIR__ . '/../ops/hostinger/sanro-studio-pr31-bootstrap-preview.php';
            return (string) ob_get_clean();
        } catch (Throwable $error) {
            ob_end_clean();
            throw $error;
        }
    })($in, $out);
    $next = file_get_contents($out);
    patch_check(strpos($message, 'BOOTSTRAP_PATCH: PREPARED') !== false,
        'prepared candidate acknowledgement');
    patch_check(file_get_contents($in) === $fixture, 'deployed source left unchanged');
    patch_check(is_string($next) && $next !== $fixture, 'candidate differs');
    patch_check(strpos($next, '<?php') === 0, 'candidate remains PHP');
    patch_check(substr_count($next, "require_once __DIR__ . '/includes/order-preview.php';") === 1,
        'original preview include preserved once');
    patch_check(substr_count($next, 'sanro_studio_admin_item_preview_status') === 1,
        'original preview hook preserved once');
    patch_check(substr_count($next, 'sanro_studio_admin_item_dtf_status') === 1,
        'new DTF status hook inserted once');
    patch_check(substr_count($next, 'SANRO_STAGING_DTF_ADMIN_MODULES_BEGIN') === 1,
        'modules marker inserted once');
    patch_check(substr_count($next, 'SANRO_STAGING_DTF_ADMIN_HOOK_BEGIN') === 1,
        'hook marker inserted once');
    patch_check(substr_count($next, "require_once __DIR__ . '/includes/dtf-") === 5,
        'exactly five DTF modules inserted');
    patch_check(strpos($next, '// legacy plugin content must remain untouched') !== false,
        'unrelated plugin content preserved');
    patch_check(strpos($message, hash('sha256', $fixture)) !== false,
        'original SHA-256 recorded');
    patch_check(strpos($message, hash('sha256', $next)) !== false,
        'candidate SHA-256 recorded');
    patch_check((fileperms($out) & 0077) === 0, 'candidate remains private');
    echo 'PASS: ' . $count . " SANRO staging targeted bootstrap patch checks\n";
} finally {
    foreach ([$in,$out] as $file) {
        if (is_file($file)) unlink($file);
    }
    rmdir($folder);
}
