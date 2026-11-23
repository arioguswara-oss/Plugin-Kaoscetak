<?php
/** Verifies a public buyer's copy is independent, with no public editing credentials. */
define('ABSPATH', __DIR__);
$GLOBALS['meta']=[42=>[
 '_kld_owner_email'=>'owner@example.com','_kld_product'=>901,
 '_kld_sides'=>['front'=>['tier'=>'A5','image'=>['x'=>1,'y'=>2,'w'=>10,'h'=>12]],'back'=>['tier'=>'none']],
 '_kld_file_front'=>'drive-master-42','_kld_preview_front'=>'BASE64JPEG',
 '_kld_file_details_front'=>['mime'=>'image/png','size'=>102400]
]];
$GLOBALS['types']=[42=>'kld_design'];$GLOBALS['nextid']=100;
function add_action(){}function get_post_type($id){return $GLOBALS['types'][$id]??false;}
function get_post_meta($id,$key,$single=true){return $GLOBALS['meta'][$id][$key]??null;}
function update_post_meta($id,$key,$v){$GLOBALS['meta'][$id][$key]=$v;}
function delete_post_meta($id,$key){unset($GLOBALS['meta'][$id][$key]);}
function absint($v){return abs((int)$v);}function wp_salt($s){return 'testkey-'.$s;}
function sanitize_email($e){return $e;}
function wp_insert_post($data,$error){$id=++$GLOBALS['nextid'];$GLOBALS['types'][$id]=$data['post_type'];return $id;}
class WP_Error{function __construct($c,$m){}}
function is_wp_error($e){return $e instanceof WP_Error;}
require __DIR__.'/../plugins/kaoscetak-lite-designer/includes/sharing.php';
function yes($condition,$why){if(!$condition)throw new Exception('FAIL: '.$why);}
$copy=kld_clone_shared_design(42,'Army Green');yes(is_array($copy),'copy created');
[$id,$token]=$copy;
yes($id!==42 && strlen($token)===64,'new ID and private edit credential');
yes(get_post_meta($id,'_kld_product',true)===901,'same product');
yes(get_post_meta($id,'_kld_file_front',true)==='drive-master-42','master reference reused without processing');
yes(get_post_meta($id,'_kld_color',true)==='Army Green','buyer color independent');
yes(get_post_meta($id,'_kld_owner_email',true)===null,'owner identity not inherited');
yes(get_post_meta($id,'_kld_fork_of',true)===42,'source trace recorded');
yes(get_post_meta($id,'_kld_status',true)==='saved_pending_verify','copied design private by default');
$GLOBALS['meta'][42]['_kld_sides']['front']['tier']='A3+';
update_post_meta(42,'_kld_deleted','yes');
yes(get_post_meta($id,'_kld_sides',true)['front']['tier']==='A5','copy layout survives source changes');
yes(kld_design_exists($id),'copy survives deletion of source');
yes(get_post_meta($id,'_kld_share_seed',true)===null,'copy sharing not inherited');
echo 'PASS: 11 independent share-copy assertions'."\n";