<?php
if (!defined('ABSPATH')) exit;
function kld_encrypt_secret($plain){
 if(!function_exists('openssl_encrypt'))return '';
 $key=hash('sha256',wp_salt('auth'),true);$iv=random_bytes(16);
 $cipher=openssl_encrypt($plain,'aes-256-cbc',$key,OPENSSL_RAW_DATA,$iv);
 return base64_encode($iv.$cipher);
}
function kld_decrypt_secret($encoded){
 if(!$encoded||!function_exists('openssl_decrypt'))return '';
 $raw=base64_decode($encoded,true);if(!$raw||strlen($raw)<17)return '';
 return openssl_decrypt(substr($raw,16),'aes-256-cbc',hash('sha256',wp_salt('auth'),true),OPENSSL_RAW_DATA,substr($raw,0,16))?:'';
}
function kld_drive_connected(){return (bool)kld_decrypt_secret(get_option('kld_refresh_token',''));}
function kld_drive_token(){
 $cache=get_transient('kld_drive_access_token');if($cache)return $cache;
 $refresh=kld_decrypt_secret(get_option('kld_refresh_token',''));
 if(!$refresh)return new WP_Error('drive_not_connected','Google Drive belum terhubung.');
 $r=wp_remote_post('https://oauth2.googleapis.com/token',[
  'timeout'=>20,'body'=>['client_id'=>get_option('kld_client_id'),'client_secret'=>kld_decrypt_secret(get_option('kld_client_secret')),'refresh_token'=>$refresh,'grant_type'=>'refresh_token']]);
 if(is_wp_error($r))return $r;
 $data=json_decode(wp_remote_retrieve_body($r),true);
 if(wp_remote_retrieve_response_code($r)!==200||empty($data['access_token']))return new WP_Error('drive_refresh','Gagal memperbarui token Google Drive.');
 set_transient('kld_drive_access_token',$data['access_token'],max(60,(int)($data['expires_in']??3600)-120));
 return $data['access_token'];
}
function kld_drive_request($method,$path,$body=null,$extra=[]){
 $token=kld_drive_token();if(is_wp_error($token))return $token;
 $args=['method'=>$method,'timeout'=>25,'headers'=>['Authorization'=>'Bearer '.$token],'redirection'=>0];
 if($body!==null){$args['body']=wp_json_encode($body);$args['headers']['Content-Type']='application/json; charset=UTF-8';}
 $args=array_replace_recursive($args,$extra);
 $url='https://www.googleapis.com/drive/v3/'.ltrim($path,'/');
 $res=wp_remote_request($url,$args);
 if(is_wp_error($res))return $res;
 $status=wp_remote_retrieve_response_code($res);
 if($status<200||$status>=300){return new WP_Error('drive_api','Google Drive API error: HTTP '.$status.' '.substr(wp_strip_all_tags(wp_remote_retrieve_body($res)),0,250));}
 return json_decode(wp_remote_retrieve_body($res),true);
}
function kld_drive_create_folder($name,$parent=''){
 $meta=['name'=>$name,'mimeType'=>'application/vnd.google-apps.folder'];
 if($parent)$meta['parents']=[$parent];
 $res=kld_drive_request('POST','files?fields=id,name,webViewLink',$meta);
 return is_wp_error($res)?$res:($res['id']??new WP_Error('drive_folder','Drive tidak mengembalikan ID folder.'));
}
function kld_drive_roots(){
 $master=get_option('kld_master_folder','');$orders=get_option('kld_orders_folder','');
 if(!$master){$master=kld_drive_create_folder('KAOSCETAK-MASTER');if(is_wp_error($master))return $master;update_option('kld_master_folder',$master,false);}
 if(!$orders){$orders=kld_drive_create_folder('KAOSCETAK-PESANAN');if(is_wp_error($orders))return $orders;update_option('kld_orders_folder',$orders,false);}
 return ['master'=>$master,'orders'=>$orders];
}
add_action('admin_post_kld_connect',function(){
 if(!current_user_can('manage_woocommerce'))wp_die('Unauthorized');check_admin_referer('kld_connect');
 $client=get_option('kld_client_id','');if(!$client||!kld_decrypt_secret(get_option('kld_client_secret')))wp_die('Client ID/Secret belum diisi.');
 $state=bin2hex(random_bytes(20));set_transient('kld_oauth_'.get_current_user_id(),$state,600);
 $u=add_query_arg(['client_id'=>$client,'redirect_uri'=>admin_url('admin-post.php?action=kld_oauth_callback'),'response_type'=>'code','scope'=>'https://www.googleapis.com/auth/drive.file','access_type'=>'offline','prompt'=>'consent','state'=>$state], 'https://accounts.google.com/o/oauth2/v2/auth');
 wp_redirect($u,302,'Kaoscetak Lite Designer');exit;
});
add_action('admin_post_kld_oauth_callback',function(){
 if(!current_user_can('manage_woocommerce'))wp_die('Unauthorized');
 $state=sanitize_text_field(wp_unslash($_GET['state']??''));$stored=get_transient('kld_oauth_'.get_current_user_id());
 delete_transient('kld_oauth_'.get_current_user_id());
 if(!$stored||!hash_equals($stored,$state))wp_die('OAuth state tidak valid.');
 $code=sanitize_text_field(wp_unslash($_GET['code']??''));if(!$code)wp_die('Google tidak mengembalikan kode otorisasi.');
 $res=wp_remote_post('https://oauth2.googleapis.com/token',['timeout'=>25,'body'=>[
 'client_id'=>get_option('kld_client_id'),'client_secret'=>kld_decrypt_secret(get_option('kld_client_secret')),
 'code'=>$code,'redirect_uri'=>admin_url('admin-post.php?action=kld_oauth_callback'),'grant_type'=>'authorization_code']]);
 if(is_wp_error($res))wp_die(esc_html($res->get_error_message()));
 $body=json_decode(wp_remote_retrieve_body($res),true);
 if(wp_remote_retrieve_response_code($res)!==200||empty($body['refresh_token']))wp_die('OAuth gagal: refresh token tidak tersedia. Pastikan consent mengizinkan akses offline.');
 update_option('kld_refresh_token',kld_encrypt_secret($body['refresh_token']),false);delete_transient('kld_drive_access_token');
 $roots=kld_drive_roots();
 if(is_wp_error($roots))wp_die('Login berhasil tetapi pembuatan folder gagal: '.esc_html($roots->get_error_message()));
 wp_safe_redirect(admin_url('admin.php?page=kld-settings&connected=1'));exit;
});
add_action('admin_post_kld_test_drive',function(){
 if(!current_user_can('manage_woocommerce'))wp_die('Unauthorized');check_admin_referer('kld_test_drive');
 $res=kld_drive_request('GET','about?fields=user(emailAddress),storageQuota(limit,usage)');
 if(is_wp_error($res)){wp_die(esc_html($res->get_error_message()));}
 wp_die('Koneksi berhasil. Akun: '.esc_html($res['user']['emailAddress']??'Google Drive').' <p><a href="'.esc_url(admin_url('admin.php?page=kld-settings')).'">Kembali</a></p>','Uji Google Drive');
});
function kld_drive_initiate($name,$mime,$bytes){
 $roots=kld_drive_roots();if(is_wp_error($roots))return $roots;
 $token=kld_drive_token();if(is_wp_error($token))return $token;
 $args=['method'=>'POST','timeout'=>25,'redirection'=>0,'headers'=>[
  'Authorization'=>'Bearer '.$token,'Content-Type'=>'application/json; charset=UTF-8',
  'X-Upload-Content-Type'=>$mime,'X-Upload-Content-Length'=>(string)$bytes],
  'body'=>wp_json_encode(['name'=>$name,'mimeType'=>$mime,'parents'=>[$roots['master']]])];
 $res=wp_remote_request('https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&fields=id,name,mimeType,size,parents',$args);
 if(is_wp_error($res))return $res;
 $status=wp_remote_retrieve_response_code($res);$location=wp_remote_retrieve_header($res,'location');
 if($status<200||$status>=300||!is_string($location)||strpos($location,'https://www.googleapis.com/upload/')!==0)return new WP_Error('drive_upload_init','Tidak dapat membuka sesi upload Google Drive (HTTP '.$status.').');
 return $location;
}
function kld_drive_verify($fileid,$mime,$size,$expectedName){
 if(!preg_match('/^[a-zA-Z0-9_-]{10,200}$/',$fileid))return new WP_Error('file_id','File ID invalid.');
 $data=kld_drive_request('GET','files/'.rawurlencode($fileid).'?fields=id,name,size,mimeType,parents,trashed');
 if(is_wp_error($data))return $data;
 if(!empty($data['trashed'])||($data['mimeType']??'')!==$mime||($data['name']??'')!==$expectedName||(int)($data['size']??-1)!==(int)$size||!in_array(get_option('kld_master_folder'),$data['parents']??[],true))return new WP_Error('verification','Metadata file di Google Drive tidak cocok dengan sesi upload.');
 return $data;
}
function kld_drive_shortcut($targetid,$folder,$name){
 return kld_drive_request('POST','files?fields=id',['name'=>$name,'mimeType'=>'application/vnd.google-apps.shortcut','shortcutDetails'=>['targetId'=>$targetid],'parents'=>[$folder]]);
}