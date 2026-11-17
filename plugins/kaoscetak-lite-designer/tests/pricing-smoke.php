<?php
/** Source-level pricing regression: php tests/pricing-smoke.php (no WordPress required). */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
$GLOBALS['kld_test_options'] = [];
function get_option($key, $default = false) { return $GLOBALS['kld_test_options'][$key] ?? $default; }
function get_post_meta($id, $key, $single = false) {
    if($key==='_kld_owner_email')return [101=>'repeat@example.test',102=>'',103=>'new@example.test'][$id]??'';
    return $key === '_kld_price_preset' ? ([1 => 'reguler-24s', 2 => 'lengan-panjang-24s', 3 => 'tunik-oversize34'][$id] ?? 'none') : '';
}
function absint($v) { return abs((int)$v); }
function sanitize_email($v) { return filter_var($v,FILTER_VALIDATE_EMAIL)?$v:''; }
function is_email($v) { return filter_var($v,FILTER_VALIDATE_EMAIL)!==false; }
function wc_get_is_paid_statuses() { return ['processing','completed']; }
function wc_get_orders($args) { return $args['billing_email']==='repeat@example.test'?[91]:[]; }
function add_action(...$args) {}
function add_filter(...$args) {}
require dirname(__DIR__) . '/includes/admin.php';
require dirname(__DIR__) . '/includes/repeat.php';
$assertions = 0;
function expect_price($product, $size, $front, $back, $expected) {
    global $assertions;
    $actual = kld_unit_price($product, $size, $front, $back);
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL product=$product size=$size front=$front back=$back expected=" . var_export($expected, true) . " actual=" . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
    $assertions++;
}
$official=['S'=>[62000,72000,82000,87000],'M'=>[65000,75000,85000,90000],'L'=>[68000,78000,88000,93000],'XL'=>[72000,82000,92000,97000],'2XL'=>[77000,87000,97000,102000],'3XL'=>[80000,90000,100000,105000],'4XL'=>[83000,93000,103000,108000],'5XL'=>[85000,95000,105000,110000]];
$matrix=[];foreach($official as $size=>$row)$matrix[$size]=array_combine(['A5','A4','A3','A3+'],$row);
$surcharges = ['A5'=>15000,'A4'=>25000,'A3'=>35000,'A3+'=>40000];
$tiers = array_keys($surcharges);
foreach ($matrix as $size => $prices) {
    foreach ($tiers as $tier) {
        expect_price(1, $size, $tier, 'none', $prices[$tier]);
        expect_price(1, $size, 'none', $tier, $prices[$tier]);
        expect_price(2, $size, $tier, 'none', $prices[$tier] + 10000);
        expect_price(2, $size, 'none', $tier, $prices[$tier] + 10000);
        foreach ($tiers as $other) {
            $large = array_search($tier, $tiers, true) >= array_search($other, $tiers, true) ? $tier : $other;
            $small = $large === $tier ? $other : $tier;
            $expected = $prices[$large] + $surcharges[$small];
            expect_price(1, $size, $tier, $other, $expected);
            expect_price(1, $size, $other, $tier, $expected);
            expect_price(2, $size, $tier, $other, $expected + 10000);
            expect_price(2, $size, $other, $tier, $expected + 10000);
        }
    }
}
expect_price(1, 'L', 'A3+', 'A4', 118000);
expect_price(1, 'L', 'A4', 'A3+', 118000);
expect_price(1, 'L', 'none', 'none', null);
expect_price(1, 'INVALID', 'A5', 'none', null);
expect_price(1, 'L', 'UNKNOWN', 'none', null);
$tunic=['M'=>[85000,95000,105000,110000],'L'=>[90000,100000,110000,115000],'XL'=>[95000,105000,115000,120000]];
foreach ($tunic as $size => $prices) {
    foreach ($tiers as $i=>$tier) {
        expect_price(3,$size,$tier,'none',$prices[$i]);expect_price(3,$size,'none',$tier,$prices[$i]);
        foreach($tiers as $j=>$other){
            $expected=$prices[max($i,$j)]+$surcharges[$tiers[min($i,$j)]];
            expect_price(3,$size,$tier,$other,$expected);expect_price(3,$size,$other,$tier,$expected);
        }
    }
}
foreach ([['L','none','none'],['INVALID','A5','none'],['L','UNKNOWN','none'],[[],'A5','none'],['L',[],'none'],['L','A5',[]]] as $args)expect_price(1,...array_merge($args,[null]));
expect_price(3,'S','A5','none',null);expect_price(3,'5XL','A5','none',null);
$total=0;foreach(['S'=>2,'L'=>3,'5XL'=>1] as $size=>$qty)$total+=kld_unit_price(1,$size,'A3+','A4')*$qty;
if($total!==2*112000+3*118000+135000)throw new RuntimeException('FAIL quantity per size');$assertions++;
foreach([[101,'repeat@example.test',112100],[101,'REPEAT@EXAMPLE.TEST',112100],[101,'other@example.test',118000],[102,'repeat@example.test',118000],[103,'new@example.test',118000],[101,'',118000]] as [$id,$email,$expected]){
    if(kld_repeat_unit_price($id,kld_unit_price(1,'L','A3+','A4'),$email)!==$expected)throw new RuntimeException('FAIL repeat owner/paid-order gate');$assertions++;
}
echo "PASS: $assertions source pricing assertions" . PHP_EOL;
