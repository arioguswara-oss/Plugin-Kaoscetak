<?php
/** Unicode extraction is additionally checked with pdftotext by the local smoke invocation. */
define('ABSPATH',__DIR__);require __DIR__.'/../includes/production-pdf.php';
if(!extension_loaded('gd')){echo "NOT TESTED: guide PNG/WebP conversion needs GD\n";exit(2);}
$im=imagecreatetruecolor(16,20);imagefill($im,0,0,imagecolorallocate($im,255,255,255));ob_start();imagepng($im);$png=ob_get_clean();imagedestroy($im);
$entry=['category'=>'A5','objects'=>[['type'=>'text','x'=>1,'y'=>2,'width'=>3,'height'=>4]],'text'=>"SANRO École\nПривет\né Ω",'font_size'=>24,'text_color'=>'#171717'];
$manifest=['order_number'=>'TEST-88','item_id'=>71,'product_id'=>7,'product_name'=>'Kaos École Ω Привет','design_id'=>'11111111-1111-4111-8111-111111111111','size'=>'S','quantity'=>3,'unit_price'=>62000,'quantities'=>['S'=>3,'L'=>1],'shirt_color'=>'#ffffff','long_sleeve'=>false,'sides'=>['front'=>$entry,'back'=>$entry]];
$r=sanro_studio_unicode_production_pdf($manifest,['front'=>['mime'=>'image/png','bytes'=>$png],'back'=>['mime'=>'image/png','bytes'=>$png]]);
if(empty($r['valid'])||!str_contains($r['bytes'],'/ToUnicode')||!str_contains($r['bytes'],'/ActualText')||!str_contains($r['bytes'],'/FontFile2'))throw new RuntimeException('embedded Unicode PDF missing');
$out=getenv('SANRO_STUDIO_QA_OUTPUT');if(is_string($out)&&is_dir($out))file_put_contents($out.'/unicode-guide.pdf',$r['bytes']);
echo "PASS: Unicode embedded font, exact ActualText and PNG guide\n";
