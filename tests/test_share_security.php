<?php
/** Deterministic security-unit tests. Not a WordPress integration test. */
define('ABSPATH', __DIR__);
$GLOBALS['meta'] = [];$GLOBALS['types']=[42=>'kld_design'];$GLOBALS['hooks']=[];$assertions=0;
function add_action($hook,$fn){$GLOBALS['hooks'][$hook]=$fn;}
function get_post_type($id){return $GLOBALS['types'][$id]??false;}
function get_post_meta($id,$key,$single=true){return $GLOBALS['meta'][$id][$key]??'';}
function update_post_meta($id,$key,$value){$GLOBALS['meta'][$id][$key]=$value;}
function delete_post_meta($id,$key){unset($GLOBALS['meta'][$id][$key]);}
function sanitize_email($s){return filter_var($s,FILTER_VALIDATE_EMAIL)?$s:'';}
function is_email($s){return !!filter_var($s,FILTER_VALIDATE_EMAIL);}
function wp_salt($scheme){return 'test-salt-never-for-production-'.$scheme;}
function home_url($s){return 'https://kaoscetak.example'.$s;}
function add_query_arg($args,$url){return $url.'?'.http_build_query($args);}
function absint($v){return abs((int)$v);}
function check($b,$s){global $assertions;$assertions++;if(!$b)throw new Exception('FAIL: '.$s);}
require __DIR__.'/../plugins/kaoscetak-lite-designer/includes/sharing.php';
$owner=str_repeat('a',64);$wrong=str_repeat('b',64);
check(!kld_verify_owner_token(42,$owner),'unverified owner cannot manage');
check(kld_share_token(42)==='','private by default');
update_post_meta(42,'_kld_pending_owner',['email'=>'owner@example.com','hash'=>hash('sha256',$owner),'expires'=>time()+120]);
check(!kld_verify_owner_token(42,$wrong),'wrong owner token rejected');
check(kld_complete_owner_verification(42,$owner),'valid email token verified');
check(kld_verify_owner_token(42,$owner),'verified management token is accepted');
check(get_post_meta(42,'_kld_owner_email',true)==='owner@example.com','owner email saved');
check(get_post_meta(42,'_kld_status',true)==='saved','saved status only after verified');
check(kld_verify_owner_token(42,$owner),'owner can return using same private email link');
check(kld_share_token(42)==='','sharing not automatically enabled');
update_post_meta(42,'_kld_share_seed',bin2hex(random_bytes(20)));
$shared=kld_share_token(42);
check(strlen($shared)===64,'link public token generated');
check(kld_share_allowed(42,$shared),'public link accepted while sharing enabled');
check(!kld_share_allowed(42,$owner),'owner link cannot be used as share link');
check(!kld_verify_owner_token(42,$shared),'public share link cannot manage owner data');
check(strpos(kld_share_url(42),'kld_share=42')!==false,'share URL points to public viewer');
delete_post_meta(42,'_kld_share_seed');
check(!kld_share_allowed(42,$shared),'revoked link fails');
update_post_meta(42,'_kld_share_seed',bin2hex(random_bytes(20)));
$new=kld_share_token(42);
check($new!==$shared,'resharing rotates token');
check(!kld_share_allowed(42,$shared),'old token stays revoked after reshare');
check(kld_share_allowed(42,$new),'new share URL works');
update_post_meta(42,'_kld_deleted','yes');
check(!kld_share_allowed(42,$new),'deletion revokes public link');
check(!kld_verify_owner_token(42,$owner),'deleted design cannot be managed');
$GLOBALS['types'][42]='post';
check(!kld_verify_owner_token(42,$owner),'ordinary post never impersonates design');
echo "PASS: $assertions share/owner authorization assertions\n";