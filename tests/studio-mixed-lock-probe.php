<?php
/** Test transport transaction/rollback and production CAS calls using a DB double. No DB service. */
define('ABSPATH', __DIR__.'/');
define('WP_PLUGIN_DIR', sys_get_temp_dir().'/sanro-cas-'.bin2hex(random_bytes(6)));
const SSWR_TARGET='https://staging.example.test';const SSWR_SOURCE='fixture-source';
mkdir(WP_PLUGIN_DIR.'/sanro-studio',0700,true);
file_put_contents(WP_PLUGIN_DIR.'/sanro-studio/SANRO-STUDIO-BUILD.json',json_encode(['source_commit'=>SSWR_SOURCE]));
function sswr_identity(){} function sswr_flags(){} function is_ssl(){return true;}
function add_action(...$v){} function home_url($p){return SSWR_TARGET;} function site_url($p){return SSWR_TARGET;}
function maybe_serialize($v){return serialize($v);} function wp_cache_delete(...$v){}
require __DIR__.'/../plugins/sanro-studio/includes/pricing-integration.php';
require __DIR__.'/../tools/staging-web-release/mixed-checkout-acceptance.php';
class ProbeDb {
    public $options='wp_options'; public $rows=[]; public $backup=[]; public $in_transaction=0; public $engine='InnoDB'; public $fail_update=false;
    function prepare($sql,...$args){return ['sql'=>$sql,'args'=>$args];}
    function insert($table,$data){$this->rows[$data['option_name']]=$data['option_value'];return 1;}
    function get_var($query){
        if(is_string($query))return $this->in_transaction;
        if(str_contains($query['sql'],'information_schema'))return $this->engine;
        $key=$query['args'][0];
        if(str_contains($query['sql'],'SELECT option_name'))return isset($this->rows[$key])?$key:null;
        return $this->rows[$key]??null;
    }
    function query($query){
        if($query==='START TRANSACTION'){$this->backup=$this->rows;$this->in_transaction=1;return 0;}
        if($query==='ROLLBACK'){$this->rows=$this->backup;$this->in_transaction=0;return 0;}
        if($this->fail_update)return false;
        // Simulate DB compare-and-swap of both aliases, inspecting actual production bindings.
        $args=$query['args'];$n=count($args)/3;
        if($n!==2||!str_contains($query['sql'],' CROSS JOIN ')||substr_count($query['sql'],'BINARY option_value')>0||substr_count($query['sql'],'.option_value = BINARY %s')!==2)throw new RuntimeException('unexpected production SQL');
        for($i=0;$i<$n;$i++)if(($this->rows[$args[$n+2*$i]]??null)!==$args[$n+2*$i+1])return 0;
        $changed=0;for($i=0;$i<$n;$i++){$key=$args[$n+2*$i];if($this->rows[$key]!==$args[$i])$changed++;$this->rows[$key]=$args[$i];}
        return $changed;
    }
}
$wpdb=new ProbeDb();$wpdb->rows=['unrelated-real-option'=>'retained'];$checks=0;
function probe_check($ok,$name){global $checks;$checks++;if(!$ok)throw new RuntimeException($name);}
try {
    $r=sswr_mixed_lock_probe();
    probe_check($r['state']==='ISOLATED_DATABASE_CAS_PASS','actual CAS transport flow passed against SQL double');
    probe_check($r['stale_batch_atomic_denial']&&$r['two_row_lock_readback']&&$r['duplicate_stale_submission_denied'],'stale/duplicate and two row cases exercised');
    probe_check($r['rollback_verified']&&$wpdb->rows===['unrelated-real-option'=>'retained'],'rollback preserves unrelated rows and removes fixtures');
    $wpdb->fail_update=true;$denied=false;try{sswr_mixed_lock_probe();}catch(RuntimeException $e){$denied=true;}
    probe_check($denied&&$wpdb->rows===['unrelated-real-option'=>'retained']&&!$wpdb->in_transaction,'SQL error rolls back');
    $wpdb->fail_update=false;$wpdb->engine='MyISAM';$denied=false;try{sswr_mixed_lock_probe();}catch(RuntimeException $e){$denied=true;}
    probe_check($denied,'nontransactional engine refused');
    $wpdb->engine='InnoDB';$wpdb->in_transaction=1;$denied=false;try{sswr_mixed_lock_probe();}catch(RuntimeException $e){$denied=true;}
    probe_check($denied&&$wpdb->in_transaction===1,'existing transaction left untouched');
    echo "PASS: $checks isolated lock transport/CAS assertions (DB DOUBLE; live database NOT TESTED)\n";
} finally {
    unlink(WP_PLUGIN_DIR.'/sanro-studio/SANRO-STUDIO-BUILD.json');rmdir(WP_PLUGIN_DIR.'/sanro-studio');rmdir(WP_PLUGIN_DIR);
}
