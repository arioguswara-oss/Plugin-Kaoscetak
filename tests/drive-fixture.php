<?php
/** Only the isolated fixture. No real Google/OAuth/Drive request leaves this adapter. */
if(!defined('KLD_LOCAL_FIXTURE')||!KLD_LOCAL_FIXTURE||wp_parse_url(get_option('home'),PHP_URL_HOST)!=='127.0.0.1')return;
add_filter('pre_http_request',function($pre,$args,$url){
 if(!in_array(wp_parse_url($url,PHP_URL_HOST),['www.googleapis.com','oauth2.googleapis.com'],true))return $pre;
 $status=200;$body=[];$headers=[];
 if(strpos($url,'oauth2.googleapis.com')!==false)$body=['access_token'=>'synthetic-access-token','expires_in'=>3600];
 elseif(strpos($url,'/files/generateIds?')!==false){$n=(int)get_option('kld_fixture_generated_ids')+1;update_option('kld_fixture_generated_ids',$n,false);$body=['ids'=>['fixture-artifact-'.$n]];}
 elseif(strpos($url,'uploadType=resumable')!==false){$headers['location']='https://www.googleapis.com/upload/drive/v3/files?upload_id=fixture-only';}
 elseif(strpos($url,'alt=media')!==false){return ['headers'=>[],'body'=>get_option('kld_fixture_bad_header')?'not an image':base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aAvkAAAAASUVORK5CYII='),'response'=>['code'=>206,'message'=>'fixture'],'cookies'=>[]];}
 elseif(preg_match('~/files/(fixture-(?:master|orders)-folder)~',$url))$body=['id'=>'fixture-master-folder','mimeType'=>'application/vnd.google-apps.folder','trashed'=>false,'permissions'=>[['type'=>get_option('kld_fixture_public_folder')?'anyone':'user']],'capabilities'=>['canAddChildren'=>true]];
 elseif(preg_match('~/files/(fixture-file-[a-z]+)~',$url,$m)){
  $data=get_option('kld_fixture_files',[]);$body=$data[$m[1]]??[];if(!$body)$status=404;
  // Model the explicitly requested provider permission field, including omission failures.
  if($body&&!get_option('kld_fixture_missing_permissions')&&!isset($body['permissions']))$body['permissions']=[['type'=>'user']];
 }elseif(preg_match('~/files/(fixture-(?:artifact-[0-9]+|file-created-[a-f0-9]+))~',$url,$m)){
  $artifacts=get_option('kld_fixture_artifacts',[]);$hidden=(int)get_option('kld_fixture_hide_artifact_reads');
  if($hidden){update_option('kld_fixture_hide_artifact_reads',$hidden-1,false);$status=404;}
  else {$body=$artifacts[$m[1]]??[];if(!$body)$status=404;}
 }elseif(strpos($url,'files?')!==false){
  if(($args['method']??'GET')==='GET'){
   parse_str(wp_parse_url($url,PHP_URL_QUERY),$query);$q=$query['q']??'';
   preg_match("/key='(kldOrder|kldArtifact)' and value='([a-f0-9]{64})'/",$q,$key);preg_match("/'([^']+)' in parents/",$q,$parent);preg_match("/mimeType = '([^']+)'/",$q,$mime);
   $body=['files'=>[]];foreach(get_option('kld_fixture_artifacts',[]) as $id=>$artifact)if(!get_option('kld_fixture_hide_artifact_lists')&&empty($artifact['trashed'])&&($artifact['appProperties'][$key[1]??'']??'')===($key[2]??'missing')&&in_array($parent[1]??'',$artifact['parents']??[],true)&&($artifact['mimeType']??'')===($mime[1]??''))$body['files'][]=['id'=>$id];
  }else{
   if(strpos($url,'uploadType=multipart')!==false){preg_match('/\r\n\r\n(\{[^\r\n]+\})\r\n/',$args['body']??'',$match);$meta=json_decode($match[1]??'',true);}else $meta=json_decode($args['body']??'',true);
   if(!is_array($meta))return new WP_Error('invalid_fixture_metadata','Malformed synthetic create');
   $id=$meta['id']??('fixture-file-created-'.substr(hash('sha256',$args['body']??''),0,18));$artifacts=get_option('kld_fixture_artifacts',[]);
   if(isset($artifacts[$id]))$status=409;
   else {
    $meta['id']=$id;$meta['trashed']=false;$artifacts[$id]=$meta;update_option('kld_fixture_artifacts',$artifacts,false);$body=['id'=>$id,'name'=>$meta['name']??''];
    update_option('kld_fixture_http_creates',(int)get_option('kld_fixture_http_creates')+1);
    if((!empty($meta['appProperties']['kldOrder'])&&get_option('kld_fixture_drop_folder'))||get_option('kld_fixture_drop_artifact')){delete_option('kld_fixture_drop_folder');delete_option('kld_fixture_drop_artifact');update_option('kld_fixture_hide_artifact_reads',1,false);return new WP_Error('lost_response','Synthetic response lost after resource creation');}
   }
  }
 }else return new WP_Error('unexpected_fixture_request','Unsupported synthetic Drive request');
 return ['headers'=>$headers,'body'=>wp_json_encode($body),'response'=>['code'=>$status,'message'=>'fixture'],'cookies'=>[]];
},10,3);
add_action('rest_api_init',function(){register_rest_route('kld-fixture/v1','cart',['methods'=>'GET','permission_callback'=>'__return_true','callback'=>function(){if(!WC()->cart)wc_load_cart();$lines=[];foreach(WC()->cart->get_cart() as $l)$lines[]=['size'=>$l['kld_size']??'ordinary','quantity'=>$l['quantity']];return ['count'=>WC()->cart->get_cart_contents_count(),'lines'=>$lines];}]);});
add_filter('rest_post_dispatch',function($r,$server,$request){if($request->get_route()==='/kld/v1/add-cart'&&WC()->cart){$a=[];foreach(WC()->cart->get_cart() as $l)$a[]=($l['kld_size']??'ordinary').':'.$l['quantity'];$r->header('X-KLD-Fixture-Cart',implode(',',$a));}return $r;},99,3);
add_filter('pre_wp_mail',function($pre,$atts){update_option('kld_fixture_last_mail',$atts,false);return true;},10,2);
