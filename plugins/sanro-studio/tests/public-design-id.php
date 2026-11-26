<?php
define('ABSPATH', __DIR__ . '/');
function add_action($hook, $callback, $priority = 10) { }
function get_option($name, $default = false) { return $name === 'sanro_studio_public_design_id_schema' ? '1' : $default; }
function current_time($type, $gmt = false) { return '2026-11-26 00:00:00'; }
class SanroPublicIdDb {
    public $prefix = 'wp_'; public $rows = []; public $next = 1;
    function prepare($sql, ...$args) { foreach ($args as $arg) $sql = preg_replace('/%s/', "'" . addslashes((string)$arg) . "'", $sql, 1); return $sql; }
    function query($sql) { if (preg_match("/VALUES \\('([^']+)'/", $sql, $m)) { if (!isset($this->rows[$m[1]])) $this->rows[$m[1]] = $this->next++; return 1; } return false; }
    function get_var($sql) { if (preg_match("/WHERE design_uuid = '([^']+)'/", $sql, $m)) return $this->rows[$m[1]] ?? null; return null; }
}
global $wpdb; $wpdb = new SanroPublicIdDb();
require __DIR__ . '/../includes/public-design-id.php';
$ids = ['4233e0e2-7c89-408f-b36b-fb76ce5adbc2','4233e0e2-7c89-408f-b36b-fb76ce5adbc3','4233e0e2-7c89-408f-b36b-fb76ce5adbc4'];
$first = sanro_studio_public_design_id($ids[0]);
if ($first !== 'ID-00001' || sanro_studio_public_design_id($ids[0]) !== $first) throw new RuntimeException('identity retry allocated a duplicate sequence');
if (sanro_studio_public_design_id($ids[1]) !== 'ID-00002' || sanro_studio_public_design_id($ids[2]) !== 'ID-00003') throw new RuntimeException('sequence is not monotonic');
$wpdb->rows[$ids[2]] = 100000;
if (sanro_studio_public_design_id($ids[2]) !== 'ID-100000') throw new RuntimeException('sequence does not grow beyond five digits');
if (sanro_studio_public_design_id('bad') !== null) throw new RuntimeException('invalid UUID accepted');
echo "PASS: stable UUID mapping, retry uniqueness, monotonic allocation and sequence growth\n";
