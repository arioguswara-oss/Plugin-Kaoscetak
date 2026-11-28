<?php
defined('ABSPATH') || exit;

/** Public display identifier backed by a persistent, non-recycling SQL sequence. */
function sanro_studio_public_design_id_table() {
    global $wpdb;
    return is_object($wpdb) && isset($wpdb->prefix) ? $wpdb->prefix . 'sanro_studio_public_ids' : null;
}

function sanro_studio_public_design_id_install() {
    global $wpdb;
    $table = sanro_studio_public_design_id_table();
    if (!$table || !preg_match('/^[A-Za-z0-9_]+$/D', $table)) return false;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    if (!function_exists('dbDelta')) return false;
    $collate = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE {$table} (
        design_uuid char(36) NOT NULL,
        public_sequence bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        created_at datetime NOT NULL,
        PRIMARY KEY  (design_uuid),
        UNIQUE KEY public_sequence (public_sequence)
    ) {$collate};");
    return true;
}

function sanro_studio_public_design_id($design_uuid) {
    global $wpdb;
    if (!is_string($design_uuid) || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $design_uuid)) return null;
    $table = sanro_studio_public_design_id_table();
    if (!$table || !preg_match('/^[A-Za-z0-9_]+$/D', $table) || !method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'query')) return null;
    $uuid = strtolower($design_uuid);
    $lookup = $wpdb->prepare("SELECT public_sequence FROM `{$table}` WHERE design_uuid = %s", $uuid);
    $sequence = $wpdb->get_var($lookup);
    if ($sequence === null) {
        // Do not consume AUTO_INCREMENT on ordinary reads of an existing ID.
        // The unique UUID key still protects simultaneous first allocations.
        $sql = $wpdb->prepare("INSERT IGNORE INTO `{$table}` (design_uuid, created_at) VALUES (%s, %s)", $uuid, current_time('mysql', true));
        if ($wpdb->query($sql) === false) return null;
        $sequence = $wpdb->get_var($lookup);
    }
    if (!is_numeric($sequence) || (string) $sequence === '' || (float) $sequence < 1) return null;
    return 'ID-' . str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
}

function sanro_studio_public_design_id_upgrade() {
    if (get_option('sanro_studio_public_design_id_schema') === '1') return;
    if (sanro_studio_public_design_id_install()) update_option('sanro_studio_public_design_id_schema', '1', false);
}
add_action('init', 'sanro_studio_public_design_id_upgrade', 1);
