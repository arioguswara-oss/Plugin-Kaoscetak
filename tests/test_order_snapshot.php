<?php
// Isolated callback test; runs without WP. This does NOT certify WooCommerce hook ordering.
define('ABSPATH',__DIR__);
$GLOBALS['hooks']=[];$GLOBALS['meta']=[];
function add_action($name,$fn,$priority=10,$argCount=1){$GLOBALS['hooks'][$name][]=$fn;}
function add_filter(){}
function absint($n){return abs((int)$n);}
function get_post_meta($id,$key,$single=true){return $GLOBALS['meta'][$id][$key]??null;}
class FakeLineItem{
 function get_meta($key,$one=true){return $key==='_kld_design_id'?77:null;}
}
class FakeWCOrder{
 public $meta=[];
 function get_items(){return [new FakeLineItem];}
 function update_meta_data($k,$v){$this->meta[$k]=$v;}
 function get_meta($k,$one=true){return $this->meta[$k]??null;}
}
require __DIR__.'/../plugins/kaoscetak-lite-designer/includes/store.php';
$GLOBALS['meta'][77]=[
 '_kld_sides'=>['front'=>['tier'=>'A5','image'=>['x'=>1,'y'=>1,'w'=>10,'h'=>12]],'back'=>['tier'=>'none']],
 '_kld_file_front'=>'original-master-77a', '_kld_preview_front'=>base64_encode('snapshot-preview'),
];
$order=new FakeWCOrder();
$callbacks=$GLOBALS['hooks']['woocommerce_checkout_create_order']??[];
if(count($callbacks)!==1)throw new Exception('Missing snapshot hook');
$callbacks[0]($order);
$original=$order->get_meta('_kld_order_design_snapshot');
if(($original[77]['files']['front']??'')!=='original-master-77a')throw new Exception('Snapshot original missing');
$GLOBALS['meta'][77]['_kld_sides']['front']['tier']='A3+';
$GLOBALS['meta'][77]['_kld_file_front']='replaced-master-77b';
$GLOBALS['meta'][77]['_kld_preview_front']=base64_encode('altered');
if((kld_order_designs($order)[77]['front']['tier']??'')!=='A5')throw new Exception('Old order changed after edit');
if($order->get_meta('_kld_order_design_snapshot')[77]['files']['front']!=='original-master-77a')throw new Exception('Master file changed after edit');
if($order->get_meta('_kld_order_design_snapshot')[77]['previews']['front']!==base64_encode('snapshot-preview'))throw new Exception('Preview changed after edit');
echo "PASS: order snapshot remains unchanged after owner edits design master\n";