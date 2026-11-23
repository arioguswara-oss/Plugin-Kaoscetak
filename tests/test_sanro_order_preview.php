<?php
define('ABSPATH', __DIR__ . '/');
require __DIR__ . '/../plugins/sanro-studio/includes/order-preview.php';
$checks = 0;
function check_preview($ok, $label) {
    global $checks;
    if (!$ok) throw new RuntimeException('FAIL ' . $label);
    $checks++;
}
class SanroPreviewFixture {
    private $meta;
    public function __construct($meta = []) { $this->meta = $meta; }
    public function get_meta($key, $single = true) { return $this->meta[$key] ?? ''; }
}
$id = '12345678-1234-4234-8234-123456789abc';
$front = $id . '-front-' . str_repeat('a', 64) . '.png';
$back = $id . '-back-' . str_repeat('b', 64) . '.webp';
check_preview(sanro_studio_order_preview_inventory(null)['error'] === 'invalid_order', 'invalid order rejected');
$empty = sanro_studio_order_preview_inventory(new SanroPreviewFixture());
check_preview($empty['valid'] && $empty['front'] === null && $empty['back'] === null, 'missing previews not invented');
$valid = sanro_studio_order_preview_inventory(new SanroPreviewFixture([
    '_sanro_studio_preview_front_ref' => $front,
    '_sanro_studio_preview_back_ref' => $back,
]));
check_preview($valid['front'] === $front && $valid['back'] === $back, 'valid private references accepted');
$invalid = sanro_studio_order_preview_inventory(new SanroPreviewFixture([
    '_sanro_studio_preview_front_ref' => 'https://example.com/public.png',
    '_sanro_studio_preview_back_ref' => $front,
]));
check_preview($invalid['front'] === null && $invalid['back'] === null, 'public and cross-side references rejected');
echo "SANRO order preview inventory: {$checks} assertions PASS, 0 FAIL\n";
