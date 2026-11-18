<?php
// Run: php plugins/sanro-studio/tests/admin-preview-delivery.php
$base = sys_get_temp_dir() . '/sanro-admin-preview-' . bin2hex(random_bytes(6));
$public = $base . '/public';
$private = $base . '/private';
mkdir($base, 0700); mkdir($public, 0700); mkdir($private, 0700);
define('ABSPATH', $public . '/');
define('SANRO_STUDIO_ENABLE_PRIVATE_PREVIEWS', true);
define('SANRO_STUDIO_PRIVATE_PREVIEW_DIR', $private);
$_SERVER['DOCUMENT_ROOT'] = $public;
require dirname(__DIR__) . '/includes/storage-policy.php';
require dirname(__DIR__) . '/includes/order-preview.php';
function verify($ok, $name) {
    if (!$ok) { fwrite(STDERR, "FAIL: $name\n"); exit(1); }
    echo "PASS: $name\n";
}
$GLOBALS['sanro_can_manage'] = false;
function current_user_can($cap) { return $cap === 'manage_woocommerce' && $GLOBALS['sanro_can_manage']; }
class SanroAdminPreviewOrder {
    private $ref;
    public function __construct($ref) { $this->ref = $ref; }
    public function get_meta($key, $single = true) {
        return $key === '_sanro_studio_preview_front_ref' ? $this->ref : '';
    }
}
$id = '123e4567-e89b-42d3-a456-123456789012';
verify(sanro_studio_admin_order_preview_bytes(new SanroAdminPreviewOrder(''), 'front')['error'] === 'forbidden', 'non-admin denied');
$GLOBALS['sanro_can_manage'] = true;
verify(sanro_studio_admin_order_preview_bytes(new SanroAdminPreviewOrder(''), 'front')['error'] === 'preview_not_found', 'missing ref denied');
verify(sanro_studio_admin_order_preview_bytes(new SanroAdminPreviewOrder('../secret'), 'front')['error'] === 'preview_not_found', 'traversal ref denied');
if (!function_exists('imagecreatetruecolor')) {
    echo "SKIP: GD missing; fixture-dependent assertions not executed\n";
} else {
    $img = imagecreatetruecolor(32, 32);
    ob_start(); imagepng($img); $bytes = ob_get_clean(); imagedestroy($img);
    $written = sanro_studio_store_private_preview($id, 'front', $bytes);
    verify(!empty($written['valid']), 'private preview fixture written');
    $order = new SanroAdminPreviewOrder($written['file_id']);
    $read = sanro_studio_admin_order_preview_bytes($order, 'front');
    verify(!empty($read['valid']) && hash_equals($bytes, $read['bytes']), 'admin can read valid linked preview');
    $path = $private . '/' . $written['file_id'];
    file_put_contents($path, str_repeat('x', strlen($bytes)));
    verify(empty(sanro_studio_admin_order_preview_bytes($order, 'front')['valid']), 'tampered linked preview denied');
    unlink($path);
}
rmdir($private); rmdir($public); rmdir($base);
