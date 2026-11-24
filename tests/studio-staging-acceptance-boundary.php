<?php
/** Safety regression: a resumed/ambiguous run must never attempt another order. */
require __DIR__.'/../tools/staging-web-release/transaction.php';
$root=sys_get_temp_dir().'/sswr-acceptance-'.bin2hex(random_bytes(8));mkdir($root,0700);
function sswr_private_root(){return $GLOBALS['root'];}
function wp_json_encode($v,$flags=0){return json_encode($v,$flags);}
function sswr_preflight($lease=false){return ['installed'=>true];}
function sswr_config_lease_live(){return false;}
function current_user_can($v){return true;}
function add_action(...$args){}
function wc_load_cart(){throw new RuntimeException('unexpected_cart_or_order_creation');}
function WC(){throw new RuntimeException('unexpected_cart_or_order_creation');}
function wc_get_order($id){return new class($id){private $id;function __construct($id){$this->id=$id;}function get_id(){return $this->id;}function is_paid(){return false;}function get_currency(){return 'IDR';}function get_total(){return '240000';}function get_status(){return 'pending';}function get_customer_id(){return 0;}function get_items(){return [];}};}
function sanro_studio_saved_order_context($order,$preview){return ['valid'=>true,'design_id'=>SSWR_DESIGN];}
function sanro_studio_auto_drive_order_eligible($order){return true;}
require __DIR__.'/../tools/staging-web-release/acceptance.php';
sswr_acceptance_record(['design_id'=>SSWR_DESIGN,'state'=>'creating','order_id'=>null]);
try{sswr_create_synthetic_order();throw new RuntimeException('ambiguous attempt accepted');}catch(RuntimeException $e){if($e->getMessage()!=='previous_order_attempt_requires_readback_no_second_order')throw $e;}
sswr_acceptance_record(['design_id'=>SSWR_DESIGN,'state'=>'verified_unpaid','order_id'=>123]);
$r=sswr_create_synthetic_order();if($r['order_id']!==123||$r['paid']!==false)throw new RuntimeException('resume created a different order');
define('SANRO_STUDIO_ENABLE_DTF_ADMIN_GENERATE',false);
try{sswr_generate_synthetic_dtf();throw new RuntimeException('explicit OFF overridden');}catch(RuntimeException $e){if($e->getMessage()!=='explicit_runtime_flag_requires_server_configuration_DTF_ADMIN_GENERATE')throw $e;}
if(SANRO_STUDIO_ENABLE_DTF_ADMIN_GENERATE!==false)throw new RuntimeException('runtime OFF changed');
foreach(glob($root.'/*') as $p)unlink($p);rmdir($root);
echo "PASS: ambiguous retry rejected, existing order reused, explicit writer OFF never overridden\n";
