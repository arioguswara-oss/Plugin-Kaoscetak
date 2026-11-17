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
 $private=kld_drive_select_folder($roots['master']);if(is_wp_error($private))return $private;
 $token=kld_drive_token();if(is_wp_error($token))return $token;
 $args=['method'=>'POST','timeout'=>25,'redirection'=>0,'headers'=>[
  'Authorization'=>'Bearer '.$token,'Content-Type'=>'application/json; charset=UTF-8','Origin'=>rtrim(home_url('/'),'/'),
  'X-Upload-Content-Type'=>$mime,'X-Upload-Content-Length'=>(string)$bytes],
  'body'=>wp_json_encode(['name'=>$name,'mimeType'=>$mime,'parents'=>[$roots['master']]])];
 $res=wp_remote_request('https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&fields=id,name,mimeType,size,parents',$args);
 if(is_wp_error($res))return $res;
 $status=wp_remote_retrieve_response_code($res);$location=wp_remote_retrieve_header($res,'location');
 if($status<200||$status>=300||!is_string($location)||strpos($location,'https://www.googleapis.com/upload/')!==0)return new WP_Error('drive_upload_init','Tidak dapat membuka sesi upload Google Drive (HTTP '.$status.').');
 return $location;
}
function kld_drive_verify($fileid,$mime,$size,$expectedName,$parent=''){
 if(!is_string($fileid)||!preg_match('/^[a-zA-Z0-9_-]{10,200}$/D',$fileid))return new WP_Error('file_id','File ID invalid.');
 if(!in_array($mime,['image/png','image/jpeg'],true)||(!is_int($size)&&(!is_string($size)||!preg_match('/^[1-9][0-9]{0,7}$/D',$size)))||$size<1||$size>10485760||!is_string($expectedName)||$expectedName==='')return new WP_Error('verification','Metadata master tidak valid.');
 $data=kld_drive_request('GET','files/'.rawurlencode($fileid).'?fields=id,name,size,mimeType,parents,trashed,permissions(type)');
 if(is_wp_error($data))return $data;
 if(!is_array($data)||!isset($data['permissions'])||!is_array($data['permissions']))return new WP_Error('file_permissions','Izin file produksi tidak dapat diverifikasi.');
 foreach($data['permissions'] as $permission){
  if(!is_array($permission)||!in_array($permission['type']??'',['user','group','anyone','domain'],true))return new WP_Error('file_permissions','Izin file produksi tidak dapat diverifikasi.');
  if(in_array($permission['type'],['anyone','domain'],true))return new WP_Error('file_public','File produksi harus privat.');
 }
 if(!empty($data['trashed'])||($data['mimeType']??'')!==$mime||($data['name']??'')!==$expectedName||(int)($data['size']??-1)!==(int)$size||!in_array($parent?:get_option('kld_master_folder'),$data['parents']??[],true))return new WP_Error('verification','Metadata file di Google Drive tidak cocok dengan sesi upload.');
 $header=kld_drive_image_header($fileid,$mime);if(is_wp_error($header))return $header;
 return array_merge($data,$header);
}
/** Find an artifact created before a lost Drive response; fail closed on ambiguous matches. */
function kld_drive_find_artifact($folder,$key,$mime){
 if(!is_string($folder)||!preg_match('/^[A-Za-z0-9_-]{10,200}$/D',$folder)||!preg_match('/^[a-f0-9]{64}$/D',$key))return new WP_Error('artifact_key','Identitas artefak tidak valid.');
 $allowed=['application/vnd.google-apps.shortcut','application/pdf'];
 if(!in_array($mime,$allowed,true))return new WP_Error('artifact_type','Jenis artefak tidak valid.');
 $q="trashed = false and '".$folder."' in parents and mimeType = '".$mime."' and appProperties has { key='kldArtifact' and value='".$key."' }";
 $res=kld_drive_request('GET','files?'.http_build_query(['q'=>$q,'fields'=>'nextPageToken,files(id)','pageSize'=>2]));
 if(is_wp_error($res))return $res;
 if(!is_array($res)||!isset($res['files'])||!is_array($res['files']))return new WP_Error('artifact_lookup','Pencarian artefak tidak dapat diverifikasi.');
 if(count($res['files'])>1||!empty($res['nextPageToken']))return new WP_Error('artifact_duplicate','Artefak Drive duplikat ditemukan; perlu pemeriksaan admin.');
 return $res['files'][0]['id']??'';
}
function kld_drive_shortcut($targetid,$folder,$name,$key=''){
 if($key!==''){
  $existing=kld_drive_find_artifact($folder,$key,'application/vnd.google-apps.shortcut');
  if(is_wp_error($existing))return $existing;
  if($existing!=='')return ['id'=>$existing];
 }
 $meta=['name'=>$name,'mimeType'=>'application/vnd.google-apps.shortcut','shortcutDetails'=>['targetId'=>$targetid],'parents'=>[$folder]];
 if($key!=='')$meta['appProperties']=['kldArtifact'=>$key];
 return kld_drive_request('POST','files?fields=id',$meta);
}

/** Recover an order folder even after a lost POST response. No public permissions are created. */
function kld_drive_order_folder($order,$parent){
 $key=hash('sha256',home_url('/').'|order|'.$order->get_id());
 if(!is_string($parent)||!preg_match('/^[A-Za-z0-9_-]{10,200}$/D',$parent))return new WP_Error('folder','Folder induk order tidak valid.');
 $q="trashed = false and '".$parent."' in parents and mimeType = 'application/vnd.google-apps.folder' and appProperties has { key='kldOrder' and value='".$key."' }";
 $found=kld_drive_request('GET','files?'.http_build_query(['q'=>$q,'fields'=>'files(id)','pageSize'=>1]));
 if(is_wp_error($found))return $found;
 if(!empty($found['files'][0]['id']))return $found['files'][0]['id'];
 $created=kld_drive_request('POST','files?fields=id',['name'=>'ORDER-'.$order->get_order_number(),'mimeType'=>'application/vnd.google-apps.folder','parents'=>[$parent],'appProperties'=>['kldOrder'=>$key]]);
 return is_wp_error($created)?$created:($created['id']??new WP_Error('folder','Folder order tidak tersedia.'));
}

function kld_drive_select_folder($id){
 if(!is_string($id)||!preg_match('/^[A-Za-z0-9_-]{10,200}$/D',$id))return new WP_Error('folder','Folder ID tidak valid.');
 $data=kld_drive_request('GET','files/'.rawurlencode($id).'?fields=id,mimeType,trashed,permissions(type),capabilities(canAddChildren)');
 if(is_wp_error($data))return $data;
 if(!empty($data['trashed'])||($data['mimeType']??'')!=='application/vnd.google-apps.folder'||empty($data['capabilities']['canAddChildren'])||!isset($data['permissions']))return new WP_Error('folder','Folder tidak dapat digunakan. Pilih folder milik akun aplikasi dengan izin menambah file.');
 foreach($data['permissions'] as $permission)if(in_array($permission['type']??'',['anyone','domain'],true))return new WP_Error('folder_public','Gunakan folder privat, bukan folder publik/domain.');
 return $id;
}

/** Bounded header inspection only: never download/render/store a full production master. */
function kld_drive_image_header($fileid,$mime){
 $token=kld_drive_token();if(is_wp_error($token))return $token;
 $r=wp_remote_get('https://www.googleapis.com/drive/v3/files/'.rawurlencode($fileid).'?alt=media',[
  'timeout'=>20,'redirection'=>0,'limit_response_size'=>262144,
  'headers'=>['Authorization'=>'Bearer '.$token,'Range'=>'bytes=0-262143']]);
 if(is_wp_error($r))return $r;
 if(!in_array(wp_remote_retrieve_response_code($r),[200,206],true))return new WP_Error('image_header','Header file master tidak dapat dibaca.');
 $bytes=wp_remote_retrieve_body($r);$info=@getimagesizefromstring($bytes);
 $type=$mime==='image/png'?IMAGETYPE_PNG:IMAGETYPE_JPEG;
 if(!$info||$info[2]!==$type||$info[0]>16000||$info[1]>16000||$info[0]*$info[1]>26000000)return new WP_Error('image_header','Master bukan PNG/JPEG yang didukung (maks. 26 megapiksel).');
 return ['width'=>$info[0],'height'=>$info[1]];
}
