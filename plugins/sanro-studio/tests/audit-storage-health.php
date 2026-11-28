<?php
$root=sys_get_temp_dir().'/sanro-health-'.bin2hex(random_bytes(6));mkdir($root,0700);mkdir($root.'/public_html',0700);mkdir($root.'/private',0700);mkdir($root.'/public_html/unsafe',0700);
define('ABSPATH',$root.'/public_html');$_SERVER['DOCUMENT_ROOT']=ABSPATH;
require __DIR__.'/../includes/runtime-readiness.php';
$checks=0;function health_check($v,$s){global $checks;$checks++;if(!$v)throw new RuntimeException($s);}
try{
 health_check(!sanro_studio_storage_health("bad\0path")['safe'],'malformed configuration fails closed without fatal error');
 health_check(!sanro_studio_storage_health(null)['configured'],'missing path is not configured');
 health_check(!sanro_studio_storage_health($root.'/missing')['safe'],'configured missing directory is not ready');
 health_check(!sanro_studio_storage_health($root.'/public_html/unsafe')['safe'],'web-accessible directory is not private');
 health_check(sanro_studio_storage_health($root.'/private')['safe'],'existing private writable owner-only directory ready');
 symlink($root.'/private',$root.'/link');health_check(!sanro_studio_storage_health($root.'/link')['safe'],'configured final symlink refused');
 chmod($root.'/private',0755);clearstatcache();health_check(!sanro_studio_storage_health($root.'/private')['safe'],'world-readable directory not ready');
 health_check(!str_contains(json_encode(sanro_studio_storage_health($root.'/private')),$root),'diagnostic never reveals actual path');
 echo "PASS: $checks private storage readiness checks\n";
}finally{unlink($root.'/link');rmdir($root.'/private');rmdir($root.'/public_html/unsafe');rmdir($root.'/public_html');rmdir($root);}
