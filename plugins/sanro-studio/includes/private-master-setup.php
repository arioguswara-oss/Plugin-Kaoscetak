<?php
/**
 * SANRO Studio automatic private master directory provisioning.
 * Independent implementation: no Lumise code, no public upload paths.
 * Master routes remain gated by the existing buyer/draft opt-in and owner nonce.
 */
defined('ABSPATH') || exit;

function sanro_studio_master_path_under($path, $root) {
    return $path === $root || strpos($path, $root . DIRECTORY_SEPARATOR) === 0;
}

function sanro_studio_private_master_path_safe($directory) {
    $wp_root = realpath(ABSPATH);
    $web_root = isset($_SERVER['DOCUMENT_ROOT']) && is_string($_SERVER['DOCUMENT_ROOT'])
        ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $actual = is_string($directory) && $directory !== '' ? realpath($directory) : false;
    if (!$wp_root || !$web_root || !$actual || !is_dir($actual)
        || is_link($directory) || !is_readable($actual) || !is_writable($actual)
        || sanro_studio_master_path_under($actual, $wp_root)
        || sanro_studio_master_path_under($actual, $web_root)) {
        return false;
    }
    // File master is sensitive; even a non-web-accessible directory must
    // not have group/world permissions after provisioning.
    $permissions = @fileperms($actual);
    return is_int($permissions) && ($permissions & 0077) === 0;
}

function sanro_studio_provision_private_master_storage() {
    if (!function_exists('get_option') || !function_exists('update_option')
        || !function_exists('home_url') || !function_exists('wp_salt')) {
        return false;
    }
    // Explicit server-side directory settings always take precedence.
    if (defined('SANRO_STUDIO_PRIVATE_MASTER_DIR')) {
        return sanro_studio_private_master_path_safe(SANRO_STUDIO_PRIVATE_MASTER_DIR);
    }
    $existing = get_option('sanro_studio_auto_private_master_dir', '');
    if ($existing !== '') {
        return sanro_studio_private_master_path_safe($existing);
    }
    $wp_root = realpath(ABSPATH);
    $web_root = isset($_SERVER['DOCUMENT_ROOT']) && is_string($_SERVER['DOCUMENT_ROOT'])
        ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    if (!$wp_root || !$web_root) return false;
    $parent = realpath(dirname($wp_root));
    if (!$parent || $parent === DIRECTORY_SEPARATOR || !is_dir($parent)
        || is_link($parent) || !is_writable($parent)
        || sanro_studio_master_path_under($parent, $web_root)) {
        return false;
    }
    $suffix = substr(hash_hmac('sha256', home_url('/'), wp_salt('auth')), 0, 20);
    $directory = $parent . DIRECTORY_SEPARATOR . 'sanro-studio-private-' . $suffix;
    if (is_link($directory)) return false;
    if (!is_dir($directory) && !@mkdir($directory, 0700) && !is_dir($directory)) {
        return false;
    }
    if (is_link($directory) || !@chmod($directory, 0700)
        || !sanro_studio_private_master_path_safe($directory)) {
        return false;
    }
    // WordPress option is non-autoloaded and never returned to public REST.
    update_option('sanro_studio_auto_private_master_dir', realpath($directory), false);
    return true;
}

function sanro_studio_initialize_auto_private_master_storage() {
    // Both existing buyer and owner-bound draft gates must explicitly be on;
    // no production upload route is enabled by a bare plugin installation.
    if (!defined('SANRO_STUDIO_ENABLE_BUYER_EDITOR')
        || SANRO_STUDIO_ENABLE_BUYER_EDITOR !== true
        || !defined('SANRO_STUDIO_ENABLE_DRAFT_API')
        || SANRO_STUDIO_ENABLE_DRAFT_API !== true
        || (defined('SANRO_STUDIO_ENABLE_PRIVATE_MASTERS')
            && SANRO_STUDIO_ENABLE_PRIVATE_MASTERS !== true)
        || (defined('SANRO_STUDIO_ENABLE_MASTER_UPLOAD')
            && SANRO_STUDIO_ENABLE_MASTER_UPLOAD !== true)) {
        return false;
    }
    if (!sanro_studio_provision_private_master_storage()) return false;
    $directory = defined('SANRO_STUDIO_PRIVATE_MASTER_DIR')
        ? SANRO_STUDIO_PRIVATE_MASTER_DIR
        : get_option('sanro_studio_auto_private_master_dir', '');
    if (!sanro_studio_private_master_path_safe($directory)) return false;
    if (!defined('SANRO_STUDIO_PRIVATE_MASTER_DIR')) {
        define('SANRO_STUDIO_PRIVATE_MASTER_DIR', $directory);
    }
    if (!defined('SANRO_STUDIO_ENABLE_PRIVATE_MASTERS')) {
        define('SANRO_STUDIO_ENABLE_PRIVATE_MASTERS', true);
    }
    if (!defined('SANRO_STUDIO_ENABLE_MASTER_UPLOAD')) {
        define('SANRO_STUDIO_ENABLE_MASTER_UPLOAD', true);
    }
    return true;
}
