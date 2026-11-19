<?php
define('ABSPATH', __DIR__ . '/');
require __DIR__ . '/../plugins/sanro-studio/includes/order-preview.php';
$checks = 0;
function check_production($ok, $label) {
    global $checks;
    if (!$ok) throw new RuntimeException('FAIL ' . $label);
    $checks++;
}
class SanroProductionItem {
    private $meta;
    public function __construct($meta) { $this->meta = $meta; }
    public function get_meta($key, $single = true) { return $this->meta[$key] ?? ''; }
}
class SanroProductionOrder {
    private $paid; private $ready; private $items;
    public function __construct($paid, $ready, $items) {
        $this->paid = $paid; $this->ready = $ready; $this->items = $items;
    }
    public function is_paid() { return $this->paid; }
    public function get_meta($key, $single = true) {
        return $key === '_sanro_studio_production_ready' ? $this->ready : '';
    }
    public function get_items($type = 'line_item') { return $this->items; }
}
$id = '12345678-1234-4234-8234-123456789abc';
$other = '87654321-4321-4321-8234-123456789abc';
$front = $id . '-front-' . str_repeat('a', 64) . '.png';
$item = new SanroProductionItem([
    '_sanro_studio_design_id' => $id,
    '_sanro_studio_preview_front_ref' => $front,
]);
$withoutPreview = new SanroProductionItem(['_sanro_studio_design_id' => $id]);
$wrong = new SanroProductionItem([
    '_sanro_studio_design_id' => $other,
    '_sanro_studio_preview_front_ref' => $front,
]);
$pending = new SanroProductionOrder(false, '', [11 => $item]);
$paid = new SanroProductionOrder(true, '', [11 => $item, 12 => $withoutPreview, 13 => $wrong]);
$approved = new SanroProductionOrder(false, 'yes', [11 => $item]);
$untrusted = new SanroProductionOrder(false, 'true', [11 => $item]);
check_production(sanro_studio_order_production_file_status(null)['error'] === 'invalid_order', 'invalid order rejected');
check_production(sanro_studio_order_production_file_status($pending)['eligible'] === false, 'unpaid order not eligible');
check_production(sanro_studio_order_production_file_status($paid)['reason'] === 'payment_confirmed', 'paid order eligible');
check_production(sanro_studio_order_production_file_status($approved)['reason'] === 'production_ready', 'admin readiness eligible');
check_production(sanro_studio_order_production_file_status($untrusted)['eligible'] === false, 'truthy metadata rejected');
check_production(sanro_studio_order_item_production_manifest($pending, 11)['ready_for_drive_integration'] === false, 'unpaid manifest not ready');
$manifest = sanro_studio_order_item_production_manifest($paid, 11);
check_production($manifest['ready_for_drive_integration'] === true && $manifest['preview_front_available'] === true && $manifest['preview_back_available'] === false, 'paid item with preview ready');
check_production(sanro_studio_order_item_production_manifest($paid, 12)['ready_for_drive_integration'] === false, 'missing preview blocks drive');
check_production(sanro_studio_order_item_production_manifest($paid, 13)['error'] === 'preview_design_mismatch', 'cross-design preview rejected');
check_production(sanro_studio_order_item_production_manifest($paid, 999)['error'] === 'invalid_order_item', 'missing item rejected');
check_production(sanro_studio_order_item_production_manifest($approved, 11)['ready_for_drive_integration'] === true, 'approved item ready');
check_production($manifest['drive_link_available'] === false && $manifest['drive_url'] === null, 'drive link never invented');
check_production(sanro_studio_attach_item_production_folder_id($paid, 11, 'validfolder123')['error'] === 'forbidden', 'drive attachment requires admin');
check_production(sanro_studio_mark_order_production_ready($pending)['error'] === 'forbidden', 'production approval requires admin');
echo "SANRO production readiness: {$checks} assertions PASS, 0 FAIL\n";
