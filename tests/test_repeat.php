<?php
/* Standalone unit test; real WooCommerce integration is a separate gate. */
define('ABSPATH', __DIR__);
$GLOBALS['metadata'] = [101 => ['_kld_owner_email' => 'repeat@example.com'],
                        102 => ['_kld_owner_email' => ''],
                        103 => ['_kld_owner_email' => 'new@example.com']];
$GLOBALS['paid'] = ['repeat@example.com' => [42]];
$GLOBALS['queries'] = [];
function get_post_meta($id,$key,$single=true) {return $GLOBALS['metadata'][$id][$key]??'';}
function absint($x){return abs((int)$x);}
function sanitize_email($x){return filter_var($x,FILTER_SANITIZE_EMAIL);}
function is_email($x){return filter_var($x,FILTER_VALIDATE_EMAIL)!==false;}
function wc_get_is_paid_statuses(){return ['processing','completed'];}
function wc_get_orders($args){$GLOBALS['queries'][]=$args;return $GLOBALS['paid'][$args['billing_email']]??[];}
require __DIR__.'/../plugins/kaoscetak-lite-designer/includes/repeat.php';
$tests = [
 [101,'repeat@example.com',100000,95000,'previous buyer same verified email'],
 [101,'REPEAT@EXAMPLE.COM',103000,97850,'case insensitive verified email'],
 [101,'intruder@example.com',100000,100000,'no other email can use discount'],
 [102,'repeat@example.com',100000,100000,'unverified copy blocked'],
 [103,'new@example.com',100000,100000,'no earlier order'],
 [101,'',100000,100000,'unknown billing email blocked'],
 [101,'repeat@example.com',65001,61751,'round half up rupiah'],
];
foreach ($tests as [$id,$email,$base,$want,$desc]) {
 $got=kld_repeat_unit_price($id,$base,$email);
 if($got!==$want)throw new Exception("FAIL $desc: got $got expected $want");
}
if(kld_repeat_unit_price(101,0,'repeat@example.com')!==0)throw new Exception('FAIL zero price');
if(count($GLOBALS['queries'])!==2)throw new Exception('FAIL expected caching once per validated email');
foreach($GLOBALS['queries'] as $q)if($q['status']!==['processing','completed']||$q['return']!=='ids'||$q['limit']!==1)throw new Exception('FAIL query restrictions');
echo "PASS repeat: 10 assertions, email ownership, paid-order eligibility, rounding, caching\n";