<?php
/** Deterministic interleaving of option operations; no real SQL or WordPress DB. */
define('ABSPATH',__DIR__);$GLOBALS['opts']=[];$GLOBALS['race']=false;$count=0;
function get_option($k){return $GLOBALS['opts'][$k]??false;}
function add_option($k,$v,...$args){if(array_key_exists($k,$GLOBALS['opts']))return false;$GLOBALS['opts'][$k]=$v;return true;}
function delete_option($k){unset($GLOBALS['opts'][$k]);}
function wp_generate_uuid4(){static $n=0;return 'fixture-token-'.++$n;}
function maybe_serialize($v){return serialize($v);}function wp_cache_delete($k,$group){}
class FixtureWpdb {
 public $options='fixture_options';
 function prepare($sql,...$args){return $args;}
 function query($args){[$key,$expected]=$args;if($GLOBALS['race']){$GLOBALS['opts'][$key]=['token'=>'successor','expires'=>time()+180];$GLOBALS['race']=false;}if(isset($GLOBALS['opts'][$key])&&serialize($GLOBALS['opts'][$key])===$expected){unset($GLOBALS['opts'][$key]);return 1;}return 0;}
}
$GLOBALS['wpdb']=new FixtureWpdb;
$plugin=getenv('KLD_TEST_PLUGIN_DIR')?:__DIR__.'/../plugins/kaoscetak-lite-designer';require $plugin.'/includes/validation.php';
function check($ok,$label){global $count;$count++;if(!$ok)throw new RuntimeException('FAIL '.$label);}
$a=kld_lock_acquire('kld_cart_lock_42');check(is_string($a),'first writer acquires');check(kld_lock_acquire('kld_cart_lock_42')===false,'second writer blocked');
kld_lock_release('kld_cart_lock_42','wrong');check(get_option('kld_cart_lock_42')['token']===$a,'wrong writer cannot release');
kld_lock_release('kld_cart_lock_42',$a);check(get_option('kld_cart_lock_42')===false,'matching writer releases');
$GLOBALS['opts']['kld_cart_lock_42']=['token'=>'expired','expires'=>time()-1];check(kld_lock_acquire('kld_cart_lock_42')===false,'expiry requires fresh request');check(get_option('kld_cart_lock_42')===false,'expired lease removed');
$b=kld_lock_acquire('kld_cart_lock_42');check(is_string($b),'fresh request acquires');
$GLOBALS['race']=true;kld_lock_release('kld_cart_lock_42',$b);check(get_option('kld_cart_lock_42')['token']==='successor','stale releaser cannot delete successor');
kld_lock_release('kld_cart_lock_42',$b);check(get_option('kld_cart_lock_42')['token']==='successor','stale token stays rejected');
kld_lock_release('kld_cart_lock_42','successor');check(get_option('kld_cart_lock_42')===false,'successor releases own lease');
echo "PASS: $count atomic lock assertions (synthetic interleaving)\n";
