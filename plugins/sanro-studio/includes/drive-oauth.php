<?php
defined('ABSPATH') || exit;

/** Native confidential-client OAuth. Client secrets/refresh tokens never enter HTML, REST, manifests or logs. Picker receives only a short-lived access token on an admin POST. */
function sanro_studio_drive_oauth_origin(){return hash('sha256',rtrim(home_url('/'),'/'));}
function sanro_studio_drive_oauth_settings(){
    $s=get_option('sanro_studio_drive_oauth',[]);
    return is_array($s)&&($s['schema']??null)===1&&($s['origin']??null)===sanro_studio_drive_oauth_origin()
        &&is_string($s['revision']??null)&&preg_match('/^[a-f0-9]{32}$/D',$s['revision'])
        &&is_string($s['client_id']??null)&&strlen($s['client_id'])<=256&&preg_match('/^[0-9]+-[A-Za-z0-9_-]+\.apps\.googleusercontent\.com$/D',$s['client_id'])
        &&is_string($s['secret']??null)?$s:[];
}
function sanro_studio_drive_oauth_seal($value,$purpose){
    if(!function_exists('openssl_encrypt')||!function_exists('wp_salt'))return false;
    $plain=wp_json_encode($value);if(!is_string($plain)||strlen($plain)>16384)return false;
    $iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($plain,'aes-256-gcm',hash('sha256',wp_salt('secure_auth').'|sanro-drive-oauth',true),OPENSSL_RAW_DATA,$iv,$tag,'sanro-drive-v1|'.$purpose,16);
    return is_string($cipher)&&strlen($tag)===16?'v1:'.base64_encode($iv.$tag.$cipher):false;
}
function sanro_studio_drive_oauth_open($sealed,$purpose){
    if(!is_string($sealed)||strlen($sealed)>32768||strpos($sealed,'v1:')!==0||!function_exists('openssl_decrypt'))return false;
    $raw=base64_decode(substr($sealed,3),true);if(!is_string($raw)||strlen($raw)<29)return false;
    $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',hash('sha256',wp_salt('secure_auth').'|sanro-drive-oauth',true),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),'sanro-drive-v1|'.$purpose);
    $value=is_string($plain)?json_decode($plain,true):null;return is_array($value)?$value:false;
}
function sanro_studio_drive_oauth_valid_token($value){return is_string($value)&&strlen($value)>=16&&strlen($value)<=8192&&preg_match('#^[A-Za-z0-9._~+/=-]+$#D',$value);}
function sanro_studio_drive_oauth_token_result($response,$old_refresh='',$require_refresh=false){
    if(is_wp_error($response)||wp_remote_retrieve_response_code($response)!==200)return false;
    $body=wp_remote_retrieve_body($response);if(!is_string($body)||strlen($body)>65536)return false;$v=json_decode($body,true);if(!is_array($v)||($require_refresh&&!isset($v['scope'])))return false;
    $expiry=$v['expires_in']??null;if(is_string($expiry)&&ctype_digit($expiry))$expiry=(int)$expiry;
    $scope=$v['scope']??'https://www.googleapis.com/auth/drive.file';
    $refresh=$v['refresh_token']??$old_refresh;
    if(!is_array($v)||!sanro_studio_drive_oauth_valid_token($v['access_token']??null)||!sanro_studio_drive_oauth_valid_token($refresh)
        ||strcasecmp((string)($v['token_type']??''),'Bearer')!==0||!is_int($expiry)||$expiry<120||$expiry>86400
        ||!is_string($scope)||trim($scope)!=='https://www.googleapis.com/auth/drive.file'||($require_refresh&&!isset($v['refresh_token'])))return false;
    return ['access_token'=>$v['access_token'],'refresh_token'=>$refresh,'expires_at'=>time()+$expiry,'scope'=>'https://www.googleapis.com/auth/drive.file'];
}
function sanro_studio_drive_oauth_exchange($fields){
    return wp_remote_post('https://oauth2.googleapis.com/token',['timeout'=>20,'redirection'=>0,'sslverify'=>true,'limit_response_size'=>65536,'body'=>$fields]);
}
/** Shared settings/refresh/root lock. Request teardown also releases it on an OAuth redirect. */
function sanro_studio_drive_oauth_lock(){
    $dir=defined('SANRO_STUDIO_PRIVATE_MASTER_DIR')?SANRO_STUDIO_PRIVATE_MASTER_DIR:null;
    if(!function_exists('sanro_studio_storage_health')||!sanro_studio_storage_health($dir)['safe'])return false;
    $path=realpath($dir).'/.sanro-drive-oauth.lock';if(is_link($path))return false;$lock=@fopen($path,'c+');
    if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){if(is_resource($lock))fclose($lock);return false;}chmod($path,0600);return $lock;
}
function sanro_studio_drive_oauth_token($require_root=true){
    $s=sanro_studio_drive_oauth_settings();$t=sanro_studio_drive_oauth_open($s['tokens']??null,'tokens');
    if(!$s||!$t||($require_root&&empty($s['parent_id'])))return '';
    if($require_root&&defined('SANRO_STUDIO_DRIVE_PARENT_FOLDER_ID')&&SANRO_STUDIO_DRIVE_PARENT_FOLDER_ID!==$s['parent_id'])return '';
    if(($t['scope']??null)!=='https://www.googleapis.com/auth/drive.file'||!sanro_studio_drive_oauth_valid_token($t['refresh_token']??null))return '';
    if(($t['expires_at']??0)>time()+60&&sanro_studio_drive_oauth_valid_token($t['access_token']??null))return $t['access_token'];
    $lock=sanro_studio_drive_oauth_lock();if(!$lock)return '';
    try{
        $latest=sanro_studio_drive_oauth_settings();if(($latest['revision']??null)!==($s['revision']??null))return '';
        $t=sanro_studio_drive_oauth_open($latest['tokens']??null,'tokens');$secret=sanro_studio_drive_oauth_open($latest['secret']??null,'client');
        if(!$t||!$secret||!is_string($secret['secret']??null))return '';
        if(($t['expires_at']??0)>time()+60&&sanro_studio_drive_oauth_valid_token($t['access_token']??null))return $t['access_token'];
        $fresh=sanro_studio_drive_oauth_token_result(sanro_studio_drive_oauth_exchange(['client_id'=>$latest['client_id'],'client_secret'=>$secret['secret'],'refresh_token'=>$t['refresh_token'],'grant_type'=>'refresh_token']),$t['refresh_token']);
        if(!$fresh)return '';$sealed=sanro_studio_drive_oauth_seal($fresh,'tokens');if(!$sealed)return '';
        if((sanro_studio_drive_oauth_settings()['revision']??null)!==$latest['revision'])return '';
        $latest['tokens']=$sealed;update_option('sanro_studio_drive_oauth',$latest,false);return $fresh['access_token'];
    }catch(Throwable $e){return '';}finally{flock($lock,LOCK_UN);fclose($lock);}
}
function sanro_studio_drive_oauth_api($method,$path,$token,$body=null){
    if(!sanro_studio_drive_oauth_valid_token($token)||strpos($path,'files')!==0||strpos($path,'://')!==false)return false;
    $args=['method'=>$method,'timeout'=>20,'redirection'=>0,'sslverify'=>true,'limit_response_size'=>65536,'headers'=>['Authorization'=>'Bearer '.$token]];
    if($body!==null){$args['headers']['Content-Type']='application/json';$args['body']=wp_json_encode($body);}
    $response=wp_remote_request('https://www.googleapis.com/drive/v3/'.$path,$args);
    if(is_wp_error($response)||!in_array(wp_remote_retrieve_response_code($response),[200,201],true))return false;
    $bytes=wp_remote_retrieve_body($response);return is_string($bytes)&&strlen($bytes)<=65536?json_decode($bytes,true):false;
}
function sanro_studio_drive_oauth_private_root($token){
    // Reuse only the explicitly selected existing folder. Never create a replacement root.
    $s=sanro_studio_drive_oauth_settings();
    $id=$s['requested_parent_id']??($s['parent_id']??'');
    if(!is_string($id)||!preg_match('/^[A-Za-z0-9_-]{10,128}$/D',$id))return false;
    if(defined('SANRO_STUDIO_DRIVE_PARENT_FOLDER_ID')&&SANRO_STUDIO_DRIVE_PARENT_FOLDER_ID!==$id)return false;
    $folder=sanro_studio_drive_oauth_api('GET','files/'.rawurlencode($id).'?fields=id,mimeType,trashed,capabilities(canAddChildren)',$token);
    if(!is_array($folder)||($folder['id']??null)!==$id||($folder['mimeType']??null)!=='application/vnd.google-apps.folder'||!empty($folder['trashed'])||($folder['capabilities']['canAddChildren']??false)!==true)return false;
    $p=sanro_studio_drive_oauth_api('GET','files/'.rawurlencode($id).'/permissions?fields=nextPageToken,permissions(type,role)&pageSize=100',$token);
    if(!is_array($p)||empty($p['permissions'])||!empty($p['nextPageToken']))return false;$owner=false;
    foreach($p['permissions'] as $permission){if(($permission['type']??null)!=='user')return false;if(($permission['role']??null)==='owner')$owner=true;}
    return $owner?$id:false;
}
function sanro_studio_drive_oauth_callback_url(){return admin_url('admin-post.php?action=sanro_studio_drive_oauth_callback');}
function sanro_studio_drive_oauth_guard($action){
    if(!current_user_can('manage_options'))wp_die('Akses ditolak.','',['response'=>403]);
    if(!is_ssl()||strpos(sanro_studio_drive_oauth_callback_url(),'https://')!==0)wp_die('OAuth memerlukan HTTPS.','',['response'=>409]);
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST')wp_die('Gunakan formulir admin.','',['response'=>405]);
    check_admin_referer('sanro_drive_'.$action);
}
function sanro_studio_drive_oauth_redirect($result){wp_safe_redirect(add_query_arg('drive_result',$result,admin_url('admin.php?page=sanro-studio-drive')),303);exit;}
function sanro_studio_drive_oauth_save(){
    sanro_studio_drive_oauth_guard('save');$lock=sanro_studio_drive_oauth_lock();if(!$lock)sanro_studio_drive_oauth_redirect('storage_or_busy');
    $id=isset($_POST['client_id'])&&is_string($_POST['client_id'])?trim(wp_unslash($_POST['client_id'])):'';
    $secret=isset($_POST['client_secret'])&&is_string($_POST['client_secret'])?trim(wp_unslash($_POST['client_secret'])):'';$old=sanro_studio_drive_oauth_settings();
    if(!preg_match('/^[0-9]+-[A-Za-z0-9_-]+\.apps\.googleusercontent\.com$/D',$id)||strlen($id)>256)sanro_studio_drive_oauth_redirect('invalid_client');
    $parent=isset($_POST['parent_id'])&&is_string($_POST['parent_id'])?trim(wp_unslash($_POST['parent_id'])):'';
    $picker_key=isset($_POST['picker_key'])&&is_string($_POST['picker_key'])?trim(wp_unslash($_POST['picker_key'])):'';
    if(!preg_match('/^[A-Za-z0-9_-]{10,128}$/D',$parent)||($picker_key!==''&&!preg_match('/^[A-Za-z0-9_-]{20,128}$/D',$picker_key)))sanro_studio_drive_oauth_redirect('invalid_folder');
    if(defined('SANRO_STUDIO_DRIVE_PARENT_FOLDER_ID')&&SANRO_STUDIO_DRIVE_PARENT_FOLDER_ID!==$parent)sanro_studio_drive_oauth_redirect('invalid_folder');
    if($secret===''&&($old['client_id']??null)===$id&&!empty($old['secret'])){
        if(($old['requested_parent_id']??'')!==$parent)unset($old['parent_id']);
        $old['requested_parent_id']=$parent;$old['picker_key']=$picker_key;$old['revision']=bin2hex(random_bytes(16));
        update_option('sanro_studio_drive_oauth',$old,false);sanro_studio_drive_oauth_redirect('saved');
    }
    if(strlen($secret)<16||strlen($secret)>256||!preg_match('/^[A-Za-z0-9_-]+$/D',$secret))sanro_studio_drive_oauth_redirect('invalid_client');
    $sealed=sanro_studio_drive_oauth_seal(['secret'=>$secret],'client');if(!$sealed)sanro_studio_drive_oauth_redirect('crypto_unavailable');
    update_option('sanro_studio_drive_oauth',['schema'=>1,'origin'=>sanro_studio_drive_oauth_origin(),'client_id'=>$id,'secret'=>$sealed,'requested_parent_id'=>$parent,'picker_key'=>$picker_key,'revision'=>bin2hex(random_bytes(16))],false);
    sanro_studio_drive_oauth_redirect('saved');
}
function sanro_studio_drive_oauth_start(){
    sanro_studio_drive_oauth_guard('connect');$s=sanro_studio_drive_oauth_settings();if(!$s||!sanro_studio_drive_oauth_open($s['secret']??null,'client'))sanro_studio_drive_oauth_redirect('configure_client');
    $state=bin2hex(random_bytes(32));$verifier=bin2hex(random_bytes(32));set_transient('sanro_drive_state_'.hash('sha256',$state),['user'=>get_current_user_id(),'origin'=>sanro_studio_drive_oauth_origin(),'revision'=>$s['revision'],'verifier'=>$verifier],600);
    $url='https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query(['client_id'=>$s['client_id'],'redirect_uri'=>sanro_studio_drive_oauth_callback_url(),'response_type'=>'code','scope'=>'https://www.googleapis.com/auth/drive.file','access_type'=>'offline','prompt'=>'consent','include_granted_scopes'=>'false','state'=>$state,'code_challenge_method'=>'S256','code_challenge'=>rtrim(strtr(base64_encode(hash('sha256',$verifier,true)),'+/','-_'),'=')]);
    header('Referrer-Policy: no-referrer');nocache_headers();wp_redirect($url,303);exit;
}
function sanro_studio_drive_oauth_callback(){
    header('Referrer-Policy: no-referrer');nocache_headers();
    if(!current_user_can('manage_options')||!is_ssl())wp_die('Akses ditolak.','',['response'=>403]);
    $state=$_GET['state']??null;if(!is_string($state)||!preg_match('/^[a-f0-9]{64}$/D',$state))sanro_studio_drive_oauth_redirect('invalid_state');
    $key='sanro_drive_state_'.hash('sha256',$state);$pending=get_transient($key);$s=sanro_studio_drive_oauth_settings();
    if(!is_array($pending)||!is_string($pending['verifier']??null)||!preg_match('/^[a-f0-9]{64}$/D',$pending['verifier'])||($pending['user']??null)!==get_current_user_id()||($pending['origin']??null)!==sanro_studio_drive_oauth_origin()||($pending['revision']??null)!==($s['revision']??null)||!delete_transient($key))sanro_studio_drive_oauth_redirect('invalid_state');
    $lock=sanro_studio_drive_oauth_lock();if(!$lock)sanro_studio_drive_oauth_redirect('storage_or_busy');
    $s=sanro_studio_drive_oauth_settings();if(($pending['revision']??null)!==($s['revision']??null))sanro_studio_drive_oauth_redirect('invalid_state');
    $code=$_GET['code']??null;if(!is_string($code)||strlen($code)<10||strlen($code)>4096||isset($_GET['error']))sanro_studio_drive_oauth_redirect('consent_required');
    $secret=sanro_studio_drive_oauth_open($s['secret']??null,'client');if(!$secret)sanro_studio_drive_oauth_redirect('configure_client');
    $tokens=sanro_studio_drive_oauth_token_result(sanro_studio_drive_oauth_exchange(['client_id'=>$s['client_id'],'client_secret'=>$secret['secret'],'code'=>wp_unslash($code),'code_verifier'=>$pending['verifier'],'grant_type'=>'authorization_code','redirect_uri'=>sanro_studio_drive_oauth_callback_url()]),'',true);
    if(!$tokens)sanro_studio_drive_oauth_redirect('token_failed');$sealed=sanro_studio_drive_oauth_seal($tokens,'tokens');if(!$sealed)sanro_studio_drive_oauth_redirect('crypto_unavailable');
    if((sanro_studio_drive_oauth_settings()['revision']??null)!==$s['revision'])sanro_studio_drive_oauth_redirect('invalid_state');
    $s['tokens']=$sealed;unset($s['parent_id']);update_option('sanro_studio_drive_oauth',$s,false);
    $parent=sanro_studio_drive_oauth_private_root($tokens['access_token']);if(!$parent)sanro_studio_drive_oauth_redirect('root_pending');
    if((sanro_studio_drive_oauth_settings()['revision']??null)!==$s['revision'])sanro_studio_drive_oauth_redirect('invalid_state');$s=sanro_studio_drive_oauth_settings();$s['parent_id']=$parent;update_option('sanro_studio_drive_oauth',$s,false);sanro_studio_drive_oauth_redirect('connected');
}
function sanro_studio_drive_oauth_validate_root(){
    sanro_studio_drive_oauth_guard('root');$s=sanro_studio_drive_oauth_settings();$token=sanro_studio_drive_oauth_token(false);if($token==='')sanro_studio_drive_oauth_redirect('consent_required');
    $lock=sanro_studio_drive_oauth_lock();if(!$lock)sanro_studio_drive_oauth_redirect('storage_or_busy');
    if((sanro_studio_drive_oauth_settings()['revision']??null)!==($s['revision']??null))sanro_studio_drive_oauth_redirect('invalid_state');
    $parent=sanro_studio_drive_oauth_private_root($token);if(!$parent)sanro_studio_drive_oauth_redirect('root_pending');
    if((sanro_studio_drive_oauth_settings()['revision']??null)!==($s['revision']??null))sanro_studio_drive_oauth_redirect('invalid_state');
    $s=sanro_studio_drive_oauth_settings();$s['parent_id']=$parent;update_option('sanro_studio_drive_oauth',$s,false);sanro_studio_drive_oauth_redirect('connected');
}
function sanro_studio_drive_oauth_page(){
    if(!current_user_can('manage_options'))return;$s=sanro_studio_drive_oauth_settings();
    echo '<div class="wrap"><h1>SANRO Studio — Koneksi Google Drive</h1><p>Koneksi ini tidak mengaktifkan writer Drive, DTF atau GC. Client secret dan refresh token disimpan terenkripsi; tidak ditampilkan kembali. Scope hanya file/folder yang dibuat atau diberikan kepada aplikasi.</p>';
    $messages=['saved'=>'Konfigurasi client tersimpan. Lanjutkan Sambungkan Google Drive.','connected'=>'OAuth terhubung dan folder produksi privat telah diverifikasi. Writer tetap OFF.','root_pending'=>'OAuth tersimpan. Pilih folder existing melalui Google Picker, lalu validasi akses privat.','invalid_folder'=>'Folder ID atau konfigurasi Picker belum valid, atau berbeda dari konfigurasi server.','invalid_client'=>'Client ID/secret web OAuth belum valid.','configure_client'=>'Isi client web OAuth terlebih dahulu.','crypto_unavailable'=>'Penyimpanan credential aman tidak tersedia.','storage_or_busy'=>'Pastikan storage master privat siap dan tidak ada proses koneksi lain, lalu ulangi.','invalid_state'=>'Sesi OAuth tidak valid atau telah dipakai. Mulai koneksi baru.','consent_required'=>'Persetujuan Google diperlukan.','token_failed'=>'Pertukaran OAuth gagal. Periksa client, callback dan persetujuan offline.'];$result=$_GET['drive_result']??'';
    if(is_string($result)&&isset($messages[$result]))echo '<p role="status">'.esc_html($messages[$result]).'</p>';
    echo '<p>Status: '.esc_html(!empty($s['parent_id'])?'folder terkonfigurasi; gunakan validasi untuk memeriksa akses terkini':'belum terhubung').'</p><p>Callback web OAuth yang harus didaftarkan:</p><code>'.esc_html(sanro_studio_drive_oauth_callback_url()).'</code>';
    echo '<p>Gunakan OAuth Client jenis Web application milik Owner, Google Drive API aktif, scope drive.file. Masukkan credential langsung pada halaman HTTPS ini, bukan lewat chat/GitHub. Persetujuan akun dilakukan pada halaman resmi Google.</p>';
    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="sanro_studio_drive_oauth_save">';wp_nonce_field('sanro_drive_save');
    echo '<p><label>Folder produksi existing (ID)<br><input class="large-text" name="parent_id" value="'.esc_attr($s['requested_parent_id']??(defined('SANRO_STUDIO_DRIVE_PARENT_FOLDER_ID')?SANRO_STUDIO_DRIVE_PARENT_FOLDER_ID:'')).'" required></label></p><p><label>Google Picker API key (batasi referrer dan API)<br><input class="large-text" name="picker_key" value="'.esc_attr($s['picker_key']??'').'"></label></p>';
    echo '<p><label>Google OAuth Client ID<br><input class="large-text" name="client_id" autocomplete="off" value="'.esc_attr($s['client_id']??'').'" required></label></p><p><label>Google OAuth Client Secret<br><input class="large-text" type="password" name="client_secret" autocomplete="new-password" value=""></label><br>Kosongkan hanya untuk mempertahankan client yang sama.</p>';submit_button('Simpan client OAuth','primary','sanro_drive_save');echo '</form>';
    if(!empty($s['secret']))foreach(['connect'=>['sanro_studio_drive_oauth_start','Sambungkan Google Drive'],'root'=>['sanro_studio_drive_oauth_root','Validasi folder privat']] as $kind=>$action){echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="'.esc_attr($action[0]).'">';wp_nonce_field('sanro_drive_'.$kind);submit_button($action[1],'secondary','sanro_drive_'.$kind);echo '</form>';}
    if(!empty($s['tokens'])&&!empty($s['picker_key'])){
        echo '<button type="button" class="button" id="sanro-drive-pick">Pilih folder existing melalui Google</button><p id="sanro-drive-pick-status" role="status"></p>';
        wp_enqueue_script('sanro-drive-picker',plugins_url('../assets/drive-picker.js',__FILE__),[],SANRO_STUDIO_VERSION,true);
        wp_localize_script('sanro-drive-picker','sanroDrivePicker',['url'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('sanro_drive_picker')]);
    }
    if(!empty($s['parent_id']))echo '<p><a target="_blank" rel="noopener noreferrer" href="'.esc_url('https://drive.google.com/drive/folders/'.$s['parent_id']).'">Buka folder produksi privat</a></p>';echo '</div>';
}
/** Only a signed-in administrator can obtain the short-lived Picker token; never the refresh token. */
function sanro_studio_drive_oauth_picker(){
    if(!current_user_can('manage_options')||!is_ssl()||($_SERVER['REQUEST_METHOD']??'')!=='POST')wp_die('Akses ditolak.','',['response'=>403]);
    check_ajax_referer('sanro_drive_picker','nonce');nocache_headers();header('Referrer-Policy: no-referrer');
    $s=sanro_studio_drive_oauth_settings();$token=sanro_studio_drive_oauth_token(false);
    if($token===''||empty($s['picker_key']))wp_send_json_error(['message'=>'Sambungkan OAuth dan konfigurasi Picker terlebih dahulu.'],409);
    preg_match('/^([0-9]+)-/',$s['client_id'],$match);
    wp_send_json_success(['token'=>$token,'key'=>$s['picker_key'],'appId'=>$match[1],'parentId'=>$s['requested_parent_id']??'']);
}
function sanro_studio_register_drive_oauth(){
    add_action('wp_ajax_sanro_studio_drive_picker','sanro_studio_drive_oauth_picker');
    $s=sanro_studio_drive_oauth_settings();if(!defined('SANRO_STUDIO_DRIVE_PARENT_FOLDER_ID')&&!empty($s['parent_id'])&&is_string($s['parent_id'])&&preg_match('/^[A-Za-z0-9_-]{10,128}$/D',$s['parent_id']))define('SANRO_STUDIO_DRIVE_PARENT_FOLDER_ID',$s['parent_id']);
    add_filter('sanro_studio_drive_access_token',static function($existing){return is_string($existing)&&$existing!==''?$existing:sanro_studio_drive_oauth_token();});
    add_action('admin_menu',static function(){add_submenu_page('sanro-studio','Koneksi Google Drive','Koneksi Google Drive','manage_options','sanro-studio-drive','sanro_studio_drive_oauth_page');});
    foreach(['save'=>'sanro_studio_drive_oauth_save','start'=>'sanro_studio_drive_oauth_start','callback'=>'sanro_studio_drive_oauth_callback','root'=>'sanro_studio_drive_oauth_validate_root'] as $action=>$function)add_action('admin_post_sanro_studio_drive_oauth_'.$action,$function);
}
