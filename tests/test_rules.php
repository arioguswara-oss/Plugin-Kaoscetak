<?php
// Standalone deterministic unit tests; NOT a substitute for an installed WordPress+WooCommerce.
define('ABSPATH',__DIR__);
$GLOBALS['prices']=[];
function add_action(){}
function add_filter(){}
function get_option($key,$default=[]){ if($key==='kld_surcharges')return ['A5'=>15000,'A4'=>25000,'A3'=>35000,'A3+'=>40000];return $default; }
function get_post_meta($product,$key,$single=true){return $GLOBALS['prices'][$product][$key]??null;}
function sanitize_textarea_field($text){return trim($text);}
function sanitize_hex_color($color){return preg_match('/^#[0-9a-f]{6}$/i',$color)?$color:null;}
function absint($x){return abs((int)$x);}
require __DIR__.'/../plugins/kaoscetak-lite-designer/includes/admin.php';
require __DIR__.'/../plugins/kaoscetak-lite-designer/includes/api.php';

function check($test,$message){global $assertions;$assertions++;if(!$test)throw new Exception('FAIL '.$message);}
$assertions=0;
$matrix=[
'S'=>[62000,72000,82000,87000], 'M'=>[65000,75000,85000,90000],
'L'=>[68000,78000,88000,93000], 'XL'=>[72000,82000,92000,97000],
'2XL'=>[77000,87000,97000,102000], '3XL'=>[80000,90000,100000,105000],
'4XL'=>[83000,93000,103000,108000], '5XL'=>[85000,95000,105000,110000],
];
$tiers=['A5','A4','A3','A3+'];$extra=['A5'=>15000,'A4'=>25000,'A3'=>35000,'A3+'=>40000];
foreach($matrix as $size=>$priceRow){$GLOBALS['prices'][1]['_kld_matrix'][$size]=array_combine($tiers,$priceRow);}
$GLOBALS['prices'][1]['_kld_price_custom']='yes';
foreach($matrix as $size=>$priceRow){
 foreach($tiers as $i=>$a){
  check(kld_unit_price(1,$size,$a,'none')===$priceRow[$i], "$size $a one side");
  check(kld_unit_price(1,$size,'none',$a)===$priceRow[$i], "$size $a reverse side");
  foreach($tiers as $j=>$b){
   $expected=$priceRow[max($i,$j)]+$extra[$tiers[min($i,$j)]];
   check(kld_unit_price(1,$size,$a,$b)===$expected,"$size $a $b");
   check(kld_unit_price(1,$size,$b,$a)===$expected,"$size $b $a symmetry");
  }
 }
}
$cases=[
 ['tier'=>'A5','placement'=>['center_offset_cm'=>0,'top_from_collar_cm'=>10.83],'image'=>['x'=>1,'y'=>2,'w'=>10,'h'=>12]],
 ['tier'=>'A3+','placement'=>['center_offset_cm'=>0,'top_from_collar_cm'=>10.83],'image'=>['x'=>1,'y'=>2,'w'=>30,'h'=>45]],
];
foreach($cases as $case){$v=kld_validate_side($case);check($v!==false,'valid side');check(kld_design_fits_size(['front'=>$v,'back'=>['tier'=>'none']],'S'),'fit size S');}
$invalid=[
 ['tier'=>'A5','image'=>['x'=>12,'y'=>2,'w'=>5,'h'=>7]],
 ['tier'=>'A4','image'=>['x'=>-1,'y'=>2,'w'=>5,'h'=>7]],
 ['tier'=>'A3+','text'=>['value'=>str_repeat('X',200),'x'=>1,'y'=>1,'w'=>5,'h'=>2]],
 ['tier'=>'A3','placement'=>['top_from_collar_cm'=>-5],'image'=>['x'=>1,'y'=>1,'w'=>5,'h'=>5]],
];
foreach($invalid as $case)check(kld_validate_side($case)===false,'reject invalid side');
$small=kld_validate_side(['tier'=>'A5','placement'=>['top_from_collar_cm'=>50],'image'=>['x'=>1,'y'=>1,'w'=>10,'h'=>12]]);
check($small!==false,'small late placement structurally valid');
check(kld_design_fits_size(['front'=>$small,'back'=>['tier'=>'none']],'S')===false,'reject print below safe hem');
echo "PASS: $assertions PHP assertions (price matrix, both sides, dimension rules)\n";