<?php
/** Offline tests of checkout boundary. No WP, DB, HTTP or order writes. */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/../');
require __DIR__ . '/staging-one-order-checkout-boundary.php';
$good = [
 'host'=>'saddlebrown-peafowl-616656.hostingersite.com',
 'environment'=>'staging', 'user'=>'u606139982',
 'root'=>'/home/u606139982/domains/saddlebrown-peafowl-616656.hostingersite.com/public_html',
 'approval'=>'ONE_SYNTHETIC_CHECKOUT_ORDER_APPROVED','mode'=>'create',
 'existing_qa_orders'=>0,'payment_gateways'=>0,'currency'=>'IDR',
 'server_quote'=>130000,'order_total'=>130000,'quantity'=>1,'size'=>'M','product_id'=>24,
 'design_id'=>'123e4567-e89b-42d3-a456-426614174000',
 'owner_authorized'=>true,'server_quote_verified'=>true,'all_items_bound'=>true,
 'hp_os_enabled'=>true,'disable_mail'=>true,'disable_stock_changes'=>true,
 'disable_production_writers'=>true,
 'verified_line_items'=>[[
   'product_id'=>24,'quantity'=>1,'size'=>'M',
   'design_id'=>'123e4567-e89b-42d3-a456-426614174000',
   'server_subtotal'=>130000,'currency'=>'IDR',
 ]],
 'checkout_transport'=>'classic','verified_checkout_nonce'=>true,
 'shipping_total'=>0,'tax_total'=>0,'discount_total'=>0,'order_write_cap'=>1,
];
$n=0;
$check=static function($label,$data,$allow,$reason) use (&$n) {
 $r=sanro_studio_qa_one_order_checkout_decision($data);
 if (($r['allowed']??null)!==$allow || ($r['reason']??null)!==$reason) {
  fwrite(STDERR,"FAIL {$label}: ".json_encode($r)."\n");exit(1);
 }
 ++$n;
};
$check('valid isolated boundary',$good,true,'eligible_for_future_isolated_runner_no_write');
foreach (['host'=>'kaoscetak.id','order_total'=>1,'owner_authorized'=>false] as $key=>$bad) {
 $v=$good;$v[$key]=$bad;
 $check("preflight rejects {$key}",$v,false,[
 'host'=>'wrong_staging_identity','order_total'=>'quote_or_product_mismatch',
 'owner_authorized'=>'authorization_or_safety_missing'][$key]);
}
foreach (['verified_line_items'=>[],] as $key=>$bad) {
 $v=$good;$v[$key]=$bad;
 $check("missing {$key}",$v,false,'line_item_evidence_missing');
}
foreach (['product_id'=>25,'quantity'=>2,'size'=>'XL',
 'design_id'=>'123e4567-e89b-42d3-a456-426614174001',
 'server_subtotal'=>129999,'currency'=>'USD'] as $key=>$bad) {
 $v=$good;$v['verified_line_items'][0][$key]=$bad;
 $check("tampered line {$key}",$v,false,'line_item_evidence_mismatch');
}
foreach (['checkout_transport'=>'blocks','verified_checkout_nonce'=>false,
 'shipping_total'=>100,'tax_total'=>100,'discount_total'=>1,'order_write_cap'=>2] as $key=>$bad) {
 $v=$good;$v[$key]=$bad;
 $check("boundary {$key}",$v,false,'checkout_boundary_not_verified');
}
$v=$good;unset($v['verified_line_items']);
$check('absent line evidence',$v,false,'line_item_evidence_missing');
$v=$good;$v['verified_line_items'][]=$v['verified_line_items'][0];
$check('multiple lines',$v,false,'line_item_evidence_missing');
echo "PASS: {$n} offline checkout boundary assertions (NO ORDER, NO DB, NO PAYMENT)\n";
