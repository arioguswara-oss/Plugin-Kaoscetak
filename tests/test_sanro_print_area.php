<?php
define('ABSPATH', __DIR__ . '/');
require __DIR__ . '/../plugins/sanro-studio/includes/print-area.php';
$checks = 0;
function check_area($expected, $actual, $label) {
    global $checks;
    if ($expected !== $actual) throw new RuntimeException('FAIL: ' . $label . ' expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    $checks++;
}
$area = ['width' => 40, 'height' => 50];
$img = ['type' => 'image', 'x' => 0, 'y' => 0, 'width' => 10, 'height' => 10];
$text = ['type' => 'text', 'x' => 12, 'y' => 15, 'width' => 8, 'height' => 5];
check_area(true, sanro_studio_validate_print_side([$img], 'A5', $area)['valid'], 'single image');
check_area(false, sanro_studio_validate_print_side([$img, $text], 'A5', $area)['valid'], 'combined span exceeds A5');
check_area(true, sanro_studio_validate_print_side([$img, $text], 'A4', $area)['valid'], 'combined span fits A4');
check_area('too_many_objects', sanro_studio_validate_print_side([$img, $text, $img], 'A4', $area)['error'], 'too many');
check_area('invalid_object_type', sanro_studio_validate_print_side([$img, $img], 'A4', $area)['error'], 'duplicate image');
check_area('invalid_category', sanro_studio_validate_print_side([$img], 'A2', $area)['error'], 'unknown category');
check_area('outside_safe_area', sanro_studio_validate_print_side([array_merge($img, ['x' => 35])], 'A3+', $area)['error'], 'safe area');
check_area('invalid_geometry', sanro_studio_validate_print_side([array_merge($img, ['width' => INF])], 'A4', $area)['error'], 'infinite dimension');
check_area('invalid_geometry', sanro_studio_validate_print_side([array_merge($img, ['width' => 'NaN'])], 'A4', $area)['error'], 'nan dimension');
check_area('outside_safe_area', sanro_studio_validate_print_side([array_merge($img, ['x' => -1])], 'A4', $area)['error'], 'negative position');
check_area(true, sanro_studio_validate_print_side([], 'A5', $area)['empty'], 'empty side');
check_area('invalid_safe_area', sanro_studio_validate_print_side([$img], 'A5', ['width' => 0, 'height' => 10])['error'], 'invalid safe area');
check_area('category_exceeded', sanro_studio_validate_print_side([array_merge($img, ['width' => 30])], 'A4', $area)['error'], 'category remains fixed when object too wide');
echo "SANRO print bounds: {$checks} assertions PASS, 0 FAIL\n";
