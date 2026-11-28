<?php
/*
Plugin Name: SANRO Studio Staging Release Transport
Description: Exact-target, hash-bound administrator release transport. No shell, public API, scheduled writer or automatic installation.
Version: 0.3.3
*/
defined('ABSPATH') || exit;
require_once __DIR__.'/transaction.php';
const SSWR_TARGET='https://saddlebrown-peafowl-616656.hostingersite.com';
const SSWR_DOCROOT='/home/u606139982/domains/saddlebrown-peafowl-616656.hostingersite.com/public_html';
const SSWR_BEFORE='@VERIFIED_EXISTING_TREE_DIGEST@';
// Populated by the reproducible transport builder, never by an HTTP parameter.
require __DIR__.'/binding.php';
require_once __DIR__.'/acceptance.php';
require_once __DIR__.'/config-lease.php';
require_once __DIR__.'/checkout-acceptance.php';
require_once __DIR__.'/mixed-checkout-acceptance.php';
function sswr_identity() {
    if (rtrim(home_url('/'),'/')!==SSWR_TARGET || rtrim(site_url('/'),'/')!==SSWR_TARGET || is_multisite()
        || realpath(ABSPATH)!==SSWR_DOCROOT || realpath($_SERVER['DOCUMENT_ROOT']??'')!==SSWR_DOCROOT
        || realpath(WP_PLUGIN_DIR)!==SSWR_DOCROOT.'/wp-content/plugins' || PHP_SAPI!=='litespeed'
        || version_compare(PHP_VERSION,'8.1','<') || PHP_INT_SIZE<8) throw new RuntimeException('exact_staging_identity_required');
}
function sswr_manifest() {
    if (!hash_equals(SSWR_MANIFEST,hash_file('sha256',__DIR__.'/candidate-manifest.json')) || !hash_equals(SSWR_ARCHIVE,hash_file('sha256',__DIR__.'/candidate.zip'))) throw new RuntimeException('package_checksum_mismatch');
    $m=json_decode(file_get_contents(__DIR__.'/candidate-manifest.json'),true,32,JSON_THROW_ON_ERROR);
    if (($m['source_commit']??'')!==SSWR_SOURCE || ($m['archive_sha256']??'')!==SSWR_ARCHIVE || ($m['generator_default']??'')!=='OFF'||($m['drive_default']??'')!=='OFF') throw new RuntimeException('package_binding_mismatch');
    $pairs=[];foreach($m['files'] as $f)$pairs[]=['bytes'=>$f['bytes'],'path'=>$f['path'],'sha256'=>$f['sha256']];
    if (!hash_equals($m['plugin_digest'],hash('sha256',json_encode($pairs,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)))) throw new RuntimeException('manifest_digest_mismatch');
    return $m;
}
function sswr_flags($dtfLease=false) {
    if($dtfLease&&!sswr_config_lease_live())throw new RuntimeException('dtf_configuration_lease_required');
    $flags=[]; foreach(['STORAGE_GC','STAGING_CHECKOUT_HOOK','DTF_GENERATOR','DTF_ADMIN_GENERATE','DTF_ORDER_BATCH','DTF_IMAGE_RASTER','DTF_LAYER_RASTER','DTF_PRIVATE_ARTIFACTS','DRIVE_MASTER_EXPORT','DRIVE_AUTO_PROVISION','VERIFIED_DRIVE_ATTACHMENT','DRIVE_PACKAGE_EXPORT','DRIVE_DTF_CANDIDATES'] as $key) {
        $name='SANRO_STUDIO_ENABLE_'.$key; $flags[$key]=defined($name)?constant($name):false;
        if($flags[$key]!==false && !($dtfLease&&in_array($key,sswr_dtf_config_flags(),true)&&$flags[$key]===true)) throw new RuntimeException('writer_not_off');
    } return $flags;
}
function sswr_private_root() {
    foreach(['MASTER','PREVIEW'] as $kind) {
        $name='SANRO_STUDIO_PRIVATE_'.$kind.'_DIR'; $path=defined($name)?constant($name):null;
        if (!is_string($path)||is_link($path)||!is_dir($path)||realpath($path)!==$path||($mode=fileperms($path))===false||($mode&0077)||!is_readable($path)||!is_writable($path)||$path===SSWR_DOCROOT||strpos($path,SSWR_DOCROOT.'/')===0) throw new RuntimeException('private_storage_not_safe');
    }
    $parent=realpath(dirname(SANRO_STUDIO_PRIVATE_MASTER_DIR));
    if (!$parent || is_link(dirname(SANRO_STUDIO_PRIVATE_MASTER_DIR)) || ($parent===SSWR_DOCROOT)||strpos($parent,SSWR_DOCROOT.'/')===0 || !is_writable($parent)) throw new RuntimeException('private_release_parent_required');
    return $parent.'/staging-release-transport';
}
/** Only journal names are opened; no wp-config, SSH keys, customer files or production domain directories. */
function sswr_prior_journals($target) {
    $home='/home/u606139982';$pending=[$home];$matches=[];$dirs=0;
    for($depth=0;$depth<7 && $pending;$depth++) {
        $next=[];
        foreach($pending as $dir) {
            if(++$dirs>4000) throw new RuntimeException('journal_scan_limit');
            if(!is_readable($dir)||is_link($dir))continue;
            $path=$dir.'/.sanro-studio-transaction.json';
            if(file_exists($path)||is_link($path)) {
                $r=sswr_journal($dir);
                if(($r['target']??null)===$target) {
                    if(!in_array($r['phase'],['committed','rolled_back'],true))throw new RuntimeException('prior_transaction_requires_recovery');
                    if($r['phase']==='committed'&&$r['candidate_digest']===SSWR_BEFORE) {
                        if(sswr_tree($dir.'/'.$r['backup'])!==$r['before_digest']) throw new RuntimeException('prior_backup_mismatch');
                        $matches[]=$r['transaction'];
                    }
                }
            }
            foreach(new DirectoryIterator($dir) as $entry) if(!$entry->isDot() && !$entry->isLink() && $entry->isDir()
                && !in_array($entry->getFilename(),['domains','public_html','masters','previews','node_modules','.git','cache'],true)) $next[]=$entry->getPathname();
        } $pending=$next;
    }
    if(!$matches)throw new RuntimeException('installed_transaction_not_found');
    return count($matches);
}
function sswr_preflight($dtfLease=false) {
    sswr_identity();$m=sswr_manifest();$flags=sswr_flags($dtfLease);$target=realpath(WP_PLUGIN_DIR).'/sanro-studio';
    $active=(array)get_option('active_plugins',[]);
    if(!in_array('sanro-studio/sanro-studio.php',$active,true)||!in_array('woocommerce/woocommerce.php',$active,true))throw new RuntimeException('active_studio_and_woocommerce_required');
    $before=sswr_tree($target);$root=sswr_private_root();
    $journal=is_dir($root)?sswr_journal($root):null;
    if($journal && $journal['phase']==='committed' && $before===$journal['candidate_digest']) {
        if(sswr_tree($root.'/'.$journal['backup'])!==$journal['before_digest'])throw new RuntimeException('prior_backup_mismatch');
        $prior='new_transport_committed';
    } else {
        if($before!==SSWR_BEFORE)throw new RuntimeException('existing_tree_digest_mismatch');
        $prior=sswr_prior_journals($target);
    }
    if(!class_exists('ZipArchive')||!function_exists('fsync')||!defined('TOKEN_PARSE'))throw new RuntimeException('php_release_dependencies_missing');
    if(is_link(WP_PLUGIN_DIR.'/.sanro-studio-install.lock'))throw new RuntimeException('unsafe_installer_lock');
    if((stat(WP_PLUGIN_DIR)['dev']??null)!==(stat(dirname($root))['dev']??null))throw new RuntimeException('rollback_filesystem_mismatch');
    return ['gate'=>'PASS','source'=>SSWR_SOURCE,'archive_sha256'=>SSWR_ARCHIVE,'manifest_sha256'=>SSWR_MANIFEST,'existing_tree_digest'=>$before,'prior_journal_verified'=>$prior,'web_php'=>PHP_VERSION,'sapi'=>PHP_SAPI,'private_roots'=>'PASS','writer_flags'=>$flags,'installed'=>sswr_candidate_installed($journal,$before,$m)];
}
function sswr_apply() {
    sswr_identity();$lockpath=WP_PLUGIN_DIR.'/.sanro-studio-install.lock';
    if(is_link($lockpath))throw new RuntimeException('unsafe_installer_lock');
    $lock=fopen($lockpath,'c+');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('installer_busy');chmod($lockpath,0600);
    try {
        $root=sswr_private_root();$pending=is_dir($root)?sswr_journal($root):null;
        if($pending&&!in_array($pending['phase'],['committed','rolled_back'],true)) {
            $m=sswr_manifest();sswr_flags();foreach(['source_commit','archive_sha256','plugin_digest'] as $key)if($pending[$key]!==$m[$key])throw new RuntimeException('recovery_source_mismatch');
            return ['state'=>sswr_recover(WP_PLUGIN_DIR.'/sanro-studio',$root,$pending),'recovered'=>true,'new_install_attempted'=>false];
        }
        $gate=sswr_preflight();if($gate['installed'])return ['state'=>'already_installed','gate'=>$gate];
        $m=sswr_manifest();$root=sswr_private_root();
        if(!is_dir($root)&&!mkdir($root,0700))throw new RuntimeException('private_rollback_create_failed');
        if(is_link($root)||($mode=fileperms($root))===false||($mode&0077))throw new RuntimeException('unsafe_rollback_root');
        sswr_sync_dir($root);sswr_sync_dir(WP_PLUGIN_DIR);
        $incoming=WP_PLUGIN_DIR.'/.sanro-studio-incoming-'.bin2hex(random_bytes(16));
        if(!mkdir($incoming,0700))throw new RuntimeException('incoming_create_failed');
        $zip=new ZipArchive();if($zip->open(__DIR__.'/candidate.zip')!==true)throw new RuntimeException('zip_open_failed');
        $files=array_merge($m['files'],[$m['build_marker']]);
        if($zip->numFiles!==count($files))throw new RuntimeException('archive_inventory_mismatch');
        foreach($files as $f) {
            $name=$f['path'];if(!is_string($name)||preg_match('~(^/|\\\\|(^|/)\.\.(/|$)|\x00)~',$name))throw new RuntimeException('unsafe_member');
            $bytes=$zip->getFromName('sanro-studio/'.$name);
            if(!is_string($bytes)||strlen($bytes)!==$f['bytes']||!hash_equals($f['sha256'],hash('sha256',$bytes)))throw new RuntimeException('member_hash_mismatch');
            if(substr($name,-4)==='.php')token_get_all($bytes,TOKEN_PARSE);
            $path=$incoming.'/'.$name;if(!is_dir(dirname($path))&&!mkdir(dirname($path),0755,true))throw new RuntimeException('member_directory_failed');
            sswr_write($path,$bytes,0644);
        }$zip->close();chmod($incoming,0755);
        $gate=sswr_preflight(); // Recheck identity, flags, active plugins, exact old bytes and journal after extraction.
        $verified_external_predecessor = is_int($gate['prior_journal_verified'])
            && $gate['prior_journal_verified'] > 0;
        $r=sswr_replace(WP_PLUGIN_DIR.'/sanro-studio',$root,$incoming,$m,$gate['existing_tree_digest'],
            null,$verified_external_predecessor);
        return ['state'=>'committed','source'=>SSWR_SOURCE,'installed_tree_digest'=>$r['candidate_digest'],'rollback_retained'=>true,'flags_changed'=>false,'activation_changed'=>false,'order_writes'=>false];
    } finally {flock($lock,LOCK_UN);fclose($lock);}
}
function sswr_page() {
    if(!current_user_can('install_plugins')||!current_user_can('manage_options'))wp_die('Forbidden',403);
    echo '<div class="wrap"><h1>SANRO staging release</h1><p>Exact staging only. Installation preserves activation/data/flags; mixed checkout acceptance is available under Tools. New orders and production actions are disabled.</p>';
    try {
        if($_SERVER['REQUEST_METHOD']==='POST') {
            check_admin_referer('sswr-'.SSWR_SOURCE);
            if(($_POST['source']??null)!==SSWR_SOURCE)throw new RuntimeException('source_confirmation_mismatch');
            echo '<pre>'.esc_html(wp_json_encode(sswr_apply(),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)).'</pre>';
        }
        $pendingRoot=sswr_private_root();$pending=is_dir($pendingRoot)?sswr_journal($pendingRoot):null;
        if($pending&&!in_array($pending['phase'],['committed','rolled_back'],true)){echo '<form method="post">';wp_nonce_field('sswr-'.SSWR_SOURCE);echo '<input type="hidden" name="source" value="'.esc_attr(SSWR_SOURCE).'">';submit_button('Recover interrupted installation');echo '</form>';return;}
        $gate=sswr_preflight();echo '<pre>'.esc_html(wp_json_encode($gate,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)).'</pre>';
        if(!$gate['installed']) {echo '<form method="post">';wp_nonce_field('sswr-'.SSWR_SOURCE);echo '<input type="hidden" name="source" value="'.esc_attr(SSWR_SOURCE).'">';submit_button('Install approved staging candidate','primary','sswr_install');echo '</form>';}
        if(function_exists('sanro_studio_runtime_readiness'))echo '<h2>Runtime readiness</h2><pre>'.esc_html(wp_json_encode(sanro_studio_runtime_readiness(24),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)).'</pre>';
        echo '<h2>Read-only configuration declaration inventory</h2><pre>'.esc_html(wp_json_encode(sswr_config_inventory(),JSON_PRETTY_PRINT)).'</pre>';
        sswr_normal_checkout_evidence();
        echo '<p>Historical order #56 evidence is reused. Use Tools → SANRO mixed acceptance for the no-order boundary and isolated lock probe.</p>';
    } catch(Throwable $e) {error_log('SANRO staging release gate: '.get_class($e));echo '<p>NO-GO: '.esc_html($e instanceof RuntimeException?$e->getMessage():'release_validation_failed').'</p>';}
    echo '</div>';
}
add_action('admin_menu',static function(){if(rtrim(home_url('/'),'/')===SSWR_TARGET)add_management_page('SANRO staging release','SANRO staging release','install_plugins','sanro-staging-release','sswr_page');});

/** Authenticated HTTPS bridge for the existing exact-target gate and its single install action. */
function sswr_rest_permission($request) {
    if(!current_user_can('install_plugins')||!current_user_can('manage_options'))return new WP_Error('sswr_forbidden','Forbidden',['status'=>403]);
    if($request->get_method()==='POST'&&!wp_verify_nonce($request->get_header('X-WP-Nonce'),'wp_rest'))return new WP_Error('sswr_nonce_required','Forbidden',['status'=>403]);
    return true;
}
function sswr_register_rest_routes() {
    if(rtrim(home_url('/'),'/')!==SSWR_TARGET)return;
    register_rest_route('sanro-studio-staging-release/v1','/gate',['methods'=>'GET','permission_callback'=>'sswr_rest_permission','callback'=>static function(){
        try{
            sswr_identity();
            $runtime=function_exists('sanro_studio_runtime_readiness')?sanro_studio_runtime_readiness(0):null;
            return rest_ensure_response(['gate'=>sswr_preflight(),'runtime_readiness'=>$runtime]);
        } catch(Throwable $e){return new WP_Error('sswr_gate_blocked',$e instanceof RuntimeException&&preg_match('/^[A-Za-z0-9_]{1,120}$/D',$e->getMessage())?$e->getMessage():'release_validation_failed',['status'=>409]);}
    }]);
    register_rest_route('sanro-studio-staging-release/v1','/install',['methods'=>'POST','permission_callback'=>'sswr_rest_permission','callback'=>static function($request){
        if($request->get_param('source')!==SSWR_SOURCE)return new WP_Error('sswr_source_mismatch','Source confirmation required',['status'=>409]);
        try{sswr_identity();return rest_ensure_response(sswr_apply());}
        catch(Throwable $e){return new WP_Error('sswr_install_refused','Candidate, staging gate, lock or transaction validation refused the operation',['status'=>409]);}
    }]);
}
add_action('rest_api_init','sswr_register_rest_routes');

add_action('admin_init',static function(){
    if(($_GET['page']??null)!=='sanro-staging-release'||($_SERVER['REQUEST_METHOD']??'')!=='POST'||!isset($_POST['acceptance']))return;
    sswr_identity();
    if(!current_user_can('manage_options')||!current_user_can('install_plugins')||!current_user_can('manage_woocommerce'))wp_die('Forbidden',403);
    check_admin_referer('sswr-'.SSWR_SOURCE);
    if(($_POST['source']??null)!==SSWR_SOURCE)wp_die('Source mismatch',409);
    wp_die('Legacy order and production acceptance actions are disabled for this candidate.', '', ['response'=>403]);
});
