<?php
defined('ABSPATH') || exit;

function sanro_studio_runtime_status($new = null) {
    static $status = ['state' => 'waiting', 'mode' => 'none'];
    if (is_array($new)) $status = $new;
    return $status;
}

/** Fixed internal path, never supplied by a request, option, URL or customer upload. */
function sanro_studio_runtime_directory() {
    return dirname(__DIR__) . '/runtime/editor';
}

function sanro_studio_check_runtime($directory, $source) {
    $root = realpath($directory);
    if (!$root || !is_dir($root)) return 'package_missing';
    if (is_link($directory)) return 'package_changed';
    if (!is_array($source) || ($source['version'] ?? '') !== '2.1.2'
        || ($source['entrypoint'] ?? '') !== 'lumise.php'
        || empty($source['critical_files']) || !is_array($source['critical_files'])) return 'manifest_invalid';
    $required = ['lumise.php', 'includes/class-lumise.php', 'woo_connector.php',
        'core/includes/main.php', 'core/includes/config.php', 'core/includes/tmpl.php'];
    if (array_diff($required, array_keys($source['critical_files']))) return 'manifest_invalid';
    foreach ($source['critical_files'] as $relative => $digest) {
        if (!is_string($relative) || !preg_match('~^[a-zA-Z0-9_./-]+$~D', $relative)
            || substr($relative, 0, 1) === '/' || strpos($relative, '..') !== false
            || !is_string($digest) || !preg_match('/^[a-f0-9]{64}$/D', $digest)) return 'manifest_invalid';
        $file = realpath($root . '/' . $relative);
        if (!$file || strpos($file, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($file) || !is_readable($file)) return 'package_incomplete';
        $actual = hash_file('sha256', $file);
        if (!is_string($actual) || !hash_equals($digest, $actual)) return 'package_changed';
    }
    return 'valid';
}

/** Core bootstrap only. No schema import, activation, deactivation or option writes. */
function sanro_studio_boot_runtime() {
    $status = sanro_studio_runtime_status();
    if ($status['state'] !== 'waiting') return $status;
    if (!class_exists('WooCommerce', false)) {
        return sanro_studio_runtime_status(['state' => 'woocommerce_missing', 'mode' => 'none']);
    }
    $external = class_exists('LumiseWoo', false) && function_exists('LW') && defined('LUMISE');
    if ($external) {
        return sanro_studio_runtime_status(['state' => LUMISE === '2.1.2' ? 'external_loaded' : 'external_unsupported', 'mode' => 'external']);
    }
    foreach (['LW', 'override_woocommerce_template_part', 'override_woocommerce_template'] as $symbol) {
        if (function_exists($symbol)) return sanro_studio_runtime_status(['state' => 'symbol_conflict', 'mode' => 'none']);
    }
    foreach (['LumiseWoo', 'lumise'] as $symbol) {
        if (class_exists($symbol, false)) return sanro_studio_runtime_status(['state' => 'symbol_conflict', 'mode' => 'none']);
    }
    foreach (['LUMISE', 'LUMISE_FILE', 'LUMISE_PLUGIN_BASENAME'] as $symbol) {
        if (defined($symbol)) return sanro_studio_runtime_status(['state' => 'symbol_conflict', 'mode' => 'none']);
    }
    $directory = sanro_studio_runtime_directory();
    $manifest = dirname(__DIR__) . '/runtime/source.json';
    $source = is_readable($manifest) ? json_decode(file_get_contents($manifest), true) : null;
    $check = sanro_studio_check_runtime($directory, $source);
    if ($check !== 'valid') return sanro_studio_runtime_status(['state' => $check, 'mode' => 'none']);

    global $wpdb;
    // Reuse the existing core schema; never import sample SQL or create a fresh installation here.
    if (!is_object($wpdb) || !is_callable([$wpdb, 'get_var'])
        || $wpdb->get_var("SHOW TABLES LIKE 'lumise_settings'") !== 'lumise_settings') {
        return sanro_studio_runtime_status(['state' => 'schema_missing', 'mode' => 'none']);
    }
    require_once $directory . '/lumise.php';

    // Embedded runtime is not a second plugin: disable its plugin-lifecycle callbacks.
    $instance = $GLOBALS['lumise_woo'] ?? null;
    if (is_object($instance)) {
        remove_action('activated_plugin', [$instance, 'activated_plugin']);
        remove_action('activated_plugin', [$instance, 'activation_redirect']);
        remove_action('deactivated_plugin', [$instance, 'deactivated_plugin']);
    }
    $dependencies = sanro_studio_dependencies();
    return sanro_studio_runtime_status([
        'state' => $dependencies['supported'] ? 'internal_loaded' : 'bootstrap_incomplete',
        'mode' => 'internal',
    ]);
}
