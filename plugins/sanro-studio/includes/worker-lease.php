<?php
defined('ABSPATH') || exit;

/** Atomic token/expiry leases; never steal a legacy unversioned lock. */
function sanro_studio_lease_database() {
    global $wpdb;
    return is_object($wpdb)&&is_string($wpdb->options??null)&&preg_match('/^[a-zA-Z0-9_]+$/D',$wpdb->options)&&method_exists($wpdb,'prepare')&&method_exists($wpdb,'query')?$wpdb:null;
}
function sanro_studio_lease_cache($key) { if(function_exists('wp_cache_delete')){wp_cache_delete($key,'options');wp_cache_delete('notoptions','options');} }
function sanro_studio_lease_acquire($key,$ttl=900) {
    $db=sanro_studio_lease_database();if(!$db||!is_string($key)||strlen($key)>180)return false;
    $value=['version'=>1,'owner'=>bin2hex(random_bytes(16)),'expires'=>time()+$ttl];
    // add_option uses an upsert in WordPress: use a true insert-only primitive.
    $inserted=$db->query($db->prepare("INSERT IGNORE INTO `{$db->options}` (option_name, option_value, autoload) VALUES (%s, %s, 'no')",$key,maybe_serialize($value)));
    sanro_studio_lease_cache($key);
    if($inserted===1)return ['key'=>$key,'value'=>$value];
    if($inserted===false)return false;
    sanro_studio_lease_cache($key);$before=get_option($key,false);
    if(!is_array($before)||($before['version']??null)!==1||!is_int($before['expires']??null)||$before['expires']>time())return false;
    $n=$db->query($db->prepare("UPDATE `{$db->options}` SET option_value = %s WHERE option_name = %s AND BINARY option_value = BINARY %s",maybe_serialize($value),$key,maybe_serialize($before)));
    sanro_studio_lease_cache($key);return $n===1?['key'=>$key,'value'=>$value]:false;
}
function sanro_studio_lease_refresh(&$lease,$ttl=900) {
    $db=sanro_studio_lease_database();if(!$db||!is_array($lease)||($lease['value']['expires']??0)<time())return false;
    sanro_studio_lease_cache($lease['key']);$before=get_option($lease['key'],false);
    if($before!==$lease['value'])return false;
    $after=$before;$after['expires']=max($before['expires']+1,time()+$ttl);
    $n=$db->query($db->prepare("UPDATE `{$db->options}` SET option_value = %s WHERE option_name = %s AND BINARY option_value = BINARY %s",maybe_serialize($after),$lease['key'],maybe_serialize($before)));
    sanro_studio_lease_cache($lease['key']);if($n!==1)return false;$lease['value']=$after;return true;
}
function sanro_studio_lease_release($lease) {
    $db=sanro_studio_lease_database();if(!$db||!is_array($lease))return false;
    $n=$db->query($db->prepare("DELETE FROM `{$db->options}` WHERE option_name = %s AND BINARY option_value = BINARY %s",$lease['key'],maybe_serialize($lease['value'])));
    sanro_studio_lease_cache($lease['key']);return $n===1;
}
