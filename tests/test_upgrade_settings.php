<?php
/** Bootstrap/lifecycle regression with existing options. No WordPress, secrets or real network. */
define('ABSPATH',__DIR__);
$GLOBALS['existing_options']=[
 'kld_client_id'=>'synthetic-existing-client', 'kld_client_secret'=>'synthetic-encrypted-client-secret',
 'kld_refresh_token'=>'synthetic-encrypted-refresh-token', 'kld_master_folder'=>'existing-master-folder',
 'kld_orders_folder'=>'existing-orders-folder', 'kld_public_enabled'=>'yes',
 'kld_price_presets'=>['custom-fixture'=>['L'=>['A4'=>123456]]], 'kld_ux_enabled'=>'yes',
];
$GLOBALS['hooks']=[];$GLOBALS['activation']=null;$GLOBALS['deactivation']=null;$count=0;
function add_action($name,$callback,...$args){$GLOBALS['hooks'][$name][]=$callback;}
function add_filter(){}
function get_option($key,$default=false){return $GLOBALS['existing_options'][$key]??$default;}
function update_option(){throw new RuntimeException('Unexpected option write during upgrade lifecycle');}
function delete_option(){throw new RuntimeException('Unexpected option deletion during upgrade lifecycle');}
function wp_remote_request(){throw new RuntimeException('Unexpected upgrade network request');}
function wp_remote_post(){throw new RuntimeException('Unexpected upgrade network request');}
function plugin_dir_path($file){return dirname($file).'/';}
function plugin_dir_url(){return 'https://fixture.invalid/wp-content/plugins/kaoscetak-lite-designer/';}
function register_activation_hook($file,$callback){$GLOBALS['activation']=$callback;}
function register_deactivation_hook($file,$callback){$GLOBALS['deactivation']=$callback;}
function wp_clear_scheduled_hook($hook){} // WordPress may clear cleanup scheduling; Drive options must remain untouched.
function check_upgrade($ok,$label){global $count;$count++;if(!$ok)throw new RuntimeException('FAIL '.$label);}
$before=serialize($GLOBALS['existing_options']);
require __DIR__.'/../plugins/kaoscetak-lite-designer/kaoscetak-lite-designer.php';
check_upgrade(serialize($GLOBALS['existing_options'])===$before,'bootstrap preserves existing settings');
check_upgrade(KLD_VERSION==='0.3.2','release version continues installed RC');
check_upgrade(basename(rtrim(KLD_DIR,'/'))==='kaoscetak-lite-designer','plugin directory identity retained');
check_upgrade(is_callable($GLOBALS['activation'])&&is_callable($GLOBALS['deactivation']),'normal plugin lifecycle hooks registered');
($GLOBALS['activation'])();check_upgrade(serialize($GLOBALS['existing_options'])===$before,'activation preserves OAuth/folders/prices/public options');
($GLOBALS['deactivation'])();check_upgrade(serialize($GLOBALS['existing_options'])===$before,'deactivation preserves settings');
($GLOBALS['activation'])();check_upgrade(serialize($GLOBALS['existing_options'])===$before,'reactivation preserves settings');
foreach(array_keys($GLOBALS['existing_options']) as $key)check_upgrade(get_option($key)===unserialize($before)[$key],'existing option preserved: '.$key);
echo "PASS: $count upgrade/configuration preservation assertions; no network or settings writes\n";
