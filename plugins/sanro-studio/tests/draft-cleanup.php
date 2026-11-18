<?php
// Run: php tests/draft-cleanup.php
// Pure PHP mock: no WordPress bootstrap, database, or production changes.
define('ABSPATH', __DIR__);
define('ARRAY_A', 'ARRAY_A');
function maybe_unserialize($value) { return unserialize($value, ['allowed_classes' => false]); }
$GLOBALS['cache_deleted'] = [];
function wp_cache_delete($key, $group) { $GLOBALS['cache_deleted'][] = $key; return true; }
class MockDraftDb {
    public $options = 'wp_options';
    public $rows = [];
    public $race = false;
    public function prepare($sql, ...$args) {
        foreach ($args as $value) {
            $pos = strpos($sql, is_int($value) ? '%d' : '%s');
            if ($pos === false) throw new Exception('Unexpected SQL');
            $sql = substr_replace($sql, is_int($value) ? (string) $value : "'".str_replace("'", "''", $value)."'", $pos, 2);
        }
        return $sql;
    }
    public function esc_like($s) { return $s; }
    public function get_results($sql, $mode) {
        if ($mode !== ARRAY_A || !preg_match('/option_id > (\d+).*LIMIT (\d+)/s', $sql, $m)) throw new Exception('Invalid scan');
        $rows = array_values(array_filter($this->rows, fn($r) => $r['option_id'] > (int) $m[1]));
        usort($rows, fn($a,$b) => $a['option_id'] <=> $b['option_id']);
        return array_slice($rows, 0, (int) $m[2]);
    }
    public function query($sql) {
        if (!strpos($sql, 'BINARY option_value = BINARY') !== false) throw new Exception('Missing CAS');
        if (!preg_match('/option_id = (\d+)/', $sql, $m)) throw new Exception('Missing row id');
        if ($this->race) {
            $this->race = false;
            foreach ($this->rows as &$row) {
                if ($row['option_id'] === (int) $m[1]) {
                    $row['option_value'] = serialize(['status'=>'locked','expires_at'=>time()-3600]);
                    break;
                }
            }
            unset($row);
        }
        foreach ($this->rows as $i => $r) {
            if ($r['option_id'] === (int) $m[1]) {
                // SQL's BINARY equality is represented by exact serialized-byte match.
                $needle = "'".str_replace("'", "''", $r['option_value'])."'";
                if (!strpos($sql, 'BINARY '.$needle) !== false) return 0;
                unset($this->rows[$i]);
                return 1;
            }
        }
        return 0;
    }
}
require dirname(__DIR__).'/includes/draft.php';
function check($condition, $label) { if (!$condition) throw new Exception('FAIL: '.$label); echo "PASS: $label\n"; }
function row($id, $record) {
    return ['option_id'=>$id, 'option_name'=>'sanro_studio_draft_123e4567-e89b-42d3-a456-'.str_pad((string)$id, 12, '0', STR_PAD_LEFT), 'option_value'=>serialize($record)];
}
$wpdb = new MockDraftDb();
$wpdb->rows = [
    row(1, ['status'=>'draft','expires_at'=>time()+3600]),
    row(2, ['status'=>'locked','expires_at'=>time()-3600]),
    row(3, ['status'=>'draft','expires_at'=>time()-3600]),
    row(4, ['status'=>'draft','expires_at'=>time()-3600]),
];
$first = sanro_studio_cleanup_expired_drafts(2);
check($first['scanned']===2 && $first['deleted']===0 && $first['next_cursor']===2 && $first['has_more'], 'cursor advances over non-expired/locked records');
$wpdb->race = true;
$second = sanro_studio_cleanup_expired_drafts(1, $first['next_cursor']);
check($second['deleted']===0 && $second['next_cursor']===3 && $second['has_more'], 'concurrent change prevents deletion');
$third = sanro_studio_cleanup_expired_drafts(2, $second['next_cursor']);
check($third['deleted']===1 && !$third['has_more'], 'expired draft deleted');
check(count($wpdb->rows)===3, 'active, locked and raced records retained');
check(unserialize($wpdb->rows[2]['option_value'])['status']==='locked', 'concurrent lock mutation is preserved');
$last = sanro_studio_cleanup_expired_drafts(2, $third['next_cursor']);
check($last['scanned']===0 && $last['next_cursor']===4, 'empty scan preserves cursor');
