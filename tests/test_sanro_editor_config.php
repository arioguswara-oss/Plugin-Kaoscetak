<?php
define('ABSPATH', __DIR__ . '/');
$GLOBALS['status'] = 'publish';
$GLOBALS['profile'] = 'cotton_combed_24s';
$GLOBALS['purchasable'] = true;
$GLOBALS['areas'] = ['front'=>['width'=>32.0,'height'=>48.0],'back'=>['width'=>32.0,'height'=>48.0]];
function absint($v) { return abs((int)$v); }
function get_post_meta($id,$key,$single) {
    if ($key === '_sanro_studio_pricing_profile') return $GLOBALS['profile'];
    if ($key === '_sanro_studio_safe_areas') return $GLOBALS['areas'];
    return null;
}
class SanroEditorProduct {
    function get_status() { return $GLOBALS['status']; }
    function is_purchasable() { return $GLOBALS['purchasable']; }
}
function wc_get_product($id) { return $id===17 ? new SanroEditorProduct() : false; }
require __DIR__.'/../plugins/sanro-studio/includes/print-area.php';
require __DIR__.'/../plugins/sanro-studio/includes/product-area.php';
require __DIR__.'/../plugins/sanro-studio/includes/editor-config.php';
$checks=0;
function verify_editor($expected,$actual,$label) {
    global $checks;
    if ($expected!==$actual) throw new RuntimeException('FAIL '.$label);
    $checks++;
}
verify_editor(true,sanro_studio_public_editor_config(17)['valid'],'published product');
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
echo "SANRO editor REST config: {$checks} assertions PASS, 0 FAIL\n";
