<?php
define('ABSPATH', __DIR__ . '/');
$GLOBALS['status'] = 'publish';
$GLOBALS['profile'] = 'cotton_combed_24s';
$GLOBALS['purchasable'] = true;
$GLOBALS['areas'] = ['front'=>['width'=>32.0,'height'=>48.0],'back'=>['width'=>32.0,'height'=>48.0]];
$GLOBALS['catalog_status'] = '';
function absint($v) { return abs((int)$v); }
function get_post_meta($id,$key,$single) {
    if ($key === '_sanro_studio_pricing_profile') return $GLOBALS['profile'];
    if ($key === '_sanro_studio_safe_areas') return $GLOBALS['areas'];
    if ($key === '_sanro_studio_catalog_status') return $GLOBALS['catalog_status'];
    return null;
}
class SanroEditorProduct {
    function get_status() { return $GLOBALS['status']; }
    function is_purchasable() { return $GLOBALS['purchasable']; }
}
function wc_get_product($id) { return $id===17 ? new SanroEditorProduct() : false; }
require __DIR__.'/../plugins/sanro-studio/includes/print-area.php';
require __DIR__.'/../plugins/sanro-studio/includes/product-area.php';
require __DIR__.'/../plugins/sanro-studio/includes/catalog-lifecycle.php';
require __DIR__.'/../plugins/sanro-studio/includes/editor-config.php';
$checks=0;
function verify_editor($expected,$actual,$label) {
    global $checks;
    if ($expected!==$actual) throw new RuntimeException('FAIL '.$label);
    $checks++;
}
verify_editor(true,sanro_studio_public_editor_config(17)['valid'],'published product');
verify_editor(true,sanro_studio_catalog_is_buyer_visible(17),'legacy product remains visible by compatibility default');
$GLOBALS['catalog_status']='disabled';
verify_editor(false,sanro_studio_public_editor_config(17)['valid'],'disabled Studio listing hidden from buyer');
verify_editor(true,sanro_studio_public_editor_config(17,true)['valid'],'disabled listing remains configurable in admin');
$GLOBALS['catalog_status']='archived';
verify_editor(false,sanro_studio_public_editor_config(17)['valid'],'archived Studio listing hidden from buyer');
verify_editor(true,sanro_studio_public_editor_config(17,true)['valid'],'archived listing configuration remains readable');
$GLOBALS['catalog_status']='';
verify_editor(32.0,sanro_studio_public_editor_config(17)['safe_areas']['front']['width'],'admin geometry');
verify_editor('cm',sanro_studio_public_editor_config(17)['unit'],'cm units');
verify_editor(false,isset(sanro_studio_public_editor_config(17)['owner_token']),'no owner token');
verify_editor('product_unavailable',sanro_studio_public_editor_config(18)['error'],'unknown product');
$GLOBALS['status']='draft';
verify_editor('product_unavailable',sanro_studio_public_editor_config(17)['error'],'draft product denied');
$GLOBALS['status']='publish';$GLOBALS['purchasable']=false;
verify_editor('product_unavailable',sanro_studio_public_editor_config(17)['error'],'unavailable product denied');
$GLOBALS['purchasable']=true;$GLOBALS['profile']='other';
verify_editor('product_unavailable',sanro_studio_public_editor_config(17)['error'],'other pricing denied');
$GLOBALS['profile']='cotton_combed_24s';$GLOBALS['areas']=[];
verify_editor('product_unavailable',sanro_studio_public_editor_config(17)['error'],'no safe area denied');

// WordPress REST passes value, request and parameter name to validate_callback.
// A one-argument internal PHP function such as is_numeric is not safe here.
$GLOBALS['rest_routes'] = [];
function register_rest_route($namespace, $route, $args) {
    $GLOBALS['rest_routes'][$namespace . $route] = $args;
}
sanro_studio_register_editor_config_route();
$route = $GLOBALS['rest_routes']['sanro-studio/v1/products/(?P<id>[1-9][0-9]*)/editor-config'];
$validate = $route['args']['id']['validate_callback'];
foreach ([17, '17', '999999'] as $valid_id) {
    verify_editor(true, $validate($valid_id, null, 'id'), 'REST three-argument valid ID ' . $valid_id);
}
foreach ([0, '0', -1, '1.5', '1e3', 'abc', [], null] as $invalid_id) {
    verify_editor(false, $validate($invalid_id, null, 'id'), 'REST three-argument invalid ID');
}

// Exercise the public REST callback, not just argument validation.
// WordPress supplies WP_Error and rest_ensure_response at runtime.
class SanroConfigTestRequest {
    private $id;
    function __construct($id) { $this->id = $id; }
    function get_param($key) { return $key === 'id' ? $this->id : null; }
}
class WP_Error {
    private $code;
    private $data;
    function __construct($code, $message, $data) {
        $this->code = $code;
        $this->data = $data;
    }
    function get_error_code() { return $this->code; }
    function get_error_data() { return $this->data; }
}
function rest_ensure_response($value) { return $value; }

verify_editor('sanro_studio_rest_editor_config', $route['callback'], 'REST route callback registered');
$missing_response = sanro_studio_rest_editor_config(new SanroConfigTestRequest(999999));
verify_editor(true, $missing_response instanceof WP_Error, 'REST unavailable product returns WP_Error');
verify_editor(404, $missing_response->get_error_data()['status'], 'REST unavailable product HTTP 404');

$GLOBALS['areas'] = ['front'=>['width'=>32.0,'height'=>48.0],'back'=>['width'=>32.0,'height'=>48.0]];
$available_response = sanro_studio_rest_editor_config(new SanroConfigTestRequest(17));
verify_editor(true, $available_response['valid'], 'REST configured product valid');
verify_editor(17, $available_response['product_id'], 'REST product ID');
verify_editor(false, isset($available_response['owner_token']), 'REST response hides owner token');
$GLOBALS['catalog_status'] = 'unmapped';
verify_editor(false,sanro_studio_public_editor_config(17)['valid'],'new explicitly unmapped product cannot be purchased despite complete config');
verify_editor(true,sanro_studio_public_editor_config(17,true)['valid'],'unmapped product config remains editable');
$GLOBALS['catalog_status'] = '';
$GLOBALS['profile'] = '';
verify_editor(false,sanro_studio_catalog_is_mapped(17),'default native fields without pricing are not a legacy mapping');
verify_editor('unmapped',sanro_studio_catalog_status(17),'incomplete native product is unmapped');
verify_editor(false,sanro_studio_public_editor_config(17)['valid'],'incomplete native product is buyer-invisible');
$GLOBALS['profile'] = 'cotton_combed_24s';
verify_editor(true,sanro_studio_catalog_is_mapped(17),'real validator recognizes complete legacy product');
verify_editor(true,sanro_studio_public_editor_config(17)['valid'],'complete legacy product remains buyer-visible');
echo "SANRO editor REST config: {$checks} assertions PASS, 0 FAIL\n";
