<?php
define('ABSPATH',__DIR__);require __DIR__.'/../includes/pricing-integration.php';require __DIR__.'/../includes/order-preview.php';
function esc_html($v){return htmlspecialchars($v,ENT_QUOTES,'UTF-8');}function current_user_can($v){return $GLOBALS['allow']??true;}
$id='4233e0e2-7c89-408f-b36b-fb76ce5adbc2';$original=[['key'=>'Other','value'=>'kept']];$line=['sanro_studio_pricing_version'=>1,'sanro_studio_design_id'=>$id,'sanro_studio_size'=>'M','owner_token'=>'NEVER_DISPLAY'];
$data=sanro_studio_pricing_cart_item_data($original,$line);if(count($data)!==3||$data[1]['display']!=='M'||$data[2]['value']!==$id||strpos(json_encode($data),'NEVER_DISPLAY')!==false)throw new RuntimeException('cart labels invalid');
foreach([[],array_replace($line,['sanro_studio_design_id'=>'<script>']),array_replace($line,['sanro_studio_size'=>'<img>'])] as $bad)if(sanro_studio_pricing_cart_item_data($original,$bad)!==$original)throw new RuntimeException('unrelated or unsafe cart modified');
$item=new class($id){private $id;function __construct($id){$this->id=$id;}function get_meta($k,$single){if($k==='_sanro_studio_design_id')return $this->id;foreach(['front','back']as$s)if($k==='_sanro_studio_preview_'.$s.'_ref')return $this->id.'-'.$s.'-'.str_repeat('a',64).'.jpg';return '';}};
$order=new class($item){private $item;function __construct($item){$this->item=$item;}function get_meta($k,$single){return '';}function get_items($kind){return [2=>$this->item,3=>$this->item,4=>$this->item];}};
ob_start();sanro_studio_admin_order_preview_status($order);$html=ob_get_clean();if(strpos($html,'3 item')===false||strpos($html,'Belum tersedia')!==false||strpos($html,str_repeat('a',64))!==false)throw new RuntimeException('native order summary incorrect');
$GLOBALS['allow']=false;ob_start();sanro_studio_admin_order_preview_status($order);if(ob_get_clean()!=='')throw new RuntimeException('preview summary permission missing');
echo "PASS: friendly cart identities, unsafe/ordinary lines unchanged, native multi-item summary and admin permission\n";
