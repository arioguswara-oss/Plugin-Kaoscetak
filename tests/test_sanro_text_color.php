<?php
define('ABSPATH', __DIR__ . '/');
function absint($value) { return abs((int) $value); }
function get_post_meta($id, $key, $single) {
    return ['front'=>['width'=>32,'height'=>48], 'back'=>['width'=>32,'height'=>48]];
}
require __DIR__ . '/../plugins/sanro-studio/includes/print-area.php';
require __DIR__ . '/../plugins/sanro-studio/includes/product-area.php';
$design = ['front'=>[
    'category'=>'A5',
    'objects'=>[['type'=>'text','x'=>1,'y'=>1,'width'=>8,'height'=>4]],
    'text'=>'SANRO', 'font_size'=>22, 'text_color'=>'#fF00aA',
]];
$checks = 0;
function verify_color($valid, $message) {
    global $checks;
    if (!$valid) throw new RuntimeException('FAIL: ' . $message);
    $checks++;
}
verify_color(sanro_studio_validate_product_design(17,$design)['valid'], 'hex color accepted');
foreach (['red', '#12345', '#1234567', '#gg0000', '', 123456, '<script>'] as $color) {
    $bad=$design; $bad['front']['text_color']=$color;
    verify_color(!sanro_studio_validate_product_design(17,$bad)['valid'], 'bad color rejected');
}
echo "SANRO text color: {$checks} assertions PASS, 0 FAIL\n";
