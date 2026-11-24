<?php
// Contract harness only; does not simulate a full WordPress/WooCommerce installation.
define('ABSPATH', __DIR__ . '/');
$scenario = $argv[1] ?? 'normal';
if ($scenario !== 'missing_woo') { class WooCommerce {} }
if ($scenario !== 'missing_lumise') {
    class LumiseWoo {}
    function LW() { throw new RuntimeException('Vendor instance must not be requested'); }
    define('LUMISE', $scenario === 'unsupported' ? '9.9.9<script>' : '2.1.2');
}
if ($scenario === 'vendor_admin') { define('LUMISE_ADMIN', true); }
$GLOBALS['ss_hooks'] = [];
$GLOBALS['ss_options'] = [
    'kld_client_secret' => 'synthetic-preserved', 'lumise_editor_page' => 52,
    'kld_price_presets' => ['synthetic' => 123], 'lumise_config' => ['synthetic' => 1],
];
$GLOBALS['ss_admin'] = false;
$GLOBALS['ss_cap'] = true;
$GLOBALS['ss_nonce'] = true;
$GLOBALS['ss_writes'] = [];
$GLOBALS['ss_assertions'] = 0;
function add_filter($name, $callback, $priority = 10, $args = 1) {
    $GLOBALS['ss_hooks'][$name][$priority][] = [$callback, $args];
}
function add_action($name, $callback, $priority = 10, $args = 1) { add_filter($name, $callback, $priority, $args); }
function remove_action($name, $callback, $priority = 10) {
    foreach ($GLOBALS['ss_hooks'][$name][$priority] ?? [] as $index => $entry) {
        if ($entry[0] === $callback) unset($GLOBALS['ss_hooks'][$name][$priority][$index]);
    }
}
function apply_filters($name, $value, ...$args) {
    $hooks = $GLOBALS['ss_hooks'][$name] ?? []; ksort($hooks);
    foreach ($hooks as $callbacks) foreach ($callbacks as [$callback, $count]) {
        $value = call_user_func_array($callback, array_slice(array_merge([$value], $args), 0, $count));
    }
    return $value;
}
function get_option($key, $default = false) { return $GLOBALS['ss_options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) {
    if ($key !== 'sanro_studio_options' || $autoload !== false) throw new RuntimeException('Unexpected option write');
    $GLOBALS['ss_writes'][] = [$key, $value]; $GLOBALS['ss_options'][$key] = $value;
}
function current_user_can($cap) { return isset($GLOBALS['ss_caps']) ? !empty($GLOBALS['ss_caps'][$cap]) : ($GLOBALS['ss_cap'] && $cap === 'manage_options'); }
function is_admin() { return $GLOBALS['ss_admin']; }
class SS_Denied extends RuntimeException {}
class SS_Redirect extends RuntimeException {}
function wp_die($message, $title = '', $args = []) { throw new SS_Denied($message, $args['response'] ?? 403); }
function check_admin_referer($action) {
    if ($action !== 'sanro_studio_settings' || !$GLOBALS['ss_nonce']) throw new SS_Denied('nonce', 403);
}
function wp_safe_redirect($url) { throw new SS_Redirect($url); }
function admin_url($path='') { return 'https://example.test/wp-admin/' . $path; }
function get_permalink($id) { return $id === 52 ? 'https://example.test/custom/' : false; }
function esc_html($text) { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
function esc_url($url) { return htmlspecialchars((string)$url, ENT_QUOTES, 'UTF-8'); }
function checked($value) { if ($value) echo 'checked="checked"'; }
function selected($selected, $current = true, $echo = true) {
    $value = (string)$selected === (string)$current ? 'selected="selected"' : '';
    if ($echo) echo $value;
    return $value;
}
function wp_nonce_field($action) { echo '<input name="_wpnonce" value="synthetic">'; }
function wp_dropdown_pages($args) { echo '<select name="' . esc_html($args['name']) . '"></select>'; }
function esc_attr($text) { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
function sanitize_key($text) { return preg_replace('/[^a-z0-9_\\-]/', '', strtolower((string)$text)); }
function wp_unslash($value) { return $value; }
function submit_button($label) { echo '<button>' . esc_html($label) . '</button>'; }
function add_submenu_page(...$args) { $GLOBALS['ss_submenus'][] = $args; }
function add_menu_page(...$args) { $GLOBALS['ss_menu'] = $args; $GLOBALS['ss_menus'][] = $args; }
function wp_remote_request(...$args) { throw new RuntimeException('Network forbidden'); }
function ss_assert($condition, $label) {
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $GLOBALS['ss_assertions']++;
}
function ss_finish($label) { echo $label . ': ' . $GLOBALS['ss_assertions'] . " assertions PASS, 0 FAIL\n"; }
