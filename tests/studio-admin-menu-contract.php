<?php
/** Actual plugin bootstrap/menu/render callbacks; isolated WordPress contracts, no site/order writes. */
require __DIR__.'/sanro-studio-fixture.php';
function __($text,$domain=''){return $text;}
function esc_html__($text,$domain=''){return esc_html($text);}
require __DIR__.'/../plugins/sanro-studio/sanro-studio.php';
$before=$GLOBALS['ss_options'];
$menu_hooks=array_column($GLOBALS['ss_hooks']['admin_menu'][10],0);
ss_assert(array_search('sanro_studio_admin_menu',$menu_hooks,true)<array_search('sanro_studio_bootstrap_admin_menu',$menu_hooks,true),'root registered before status page hook resolution');
ss_assert(array_search('sanro_studio_admin_menu',$menu_hooks,true)<array_search('sanro_studio_storage_admin_menu',$menu_hooks,true),'root registered before storage page');
$roles=[
    'admin'=>['manage_options'=>true,'manage_woocommerce'=>true],
    'settings_only'=>['manage_options'=>true],
    'shop_manager'=>['manage_woocommerce'=>true],
    'subscriber'=>[],
];
foreach($roles as $role=>$caps){
    $GLOBALS['ss_caps']=$caps;$GLOBALS['ss_menus']=[];$GLOBALS['ss_submenus']=[];
    foreach($menu_hooks as $callback)$callback();
    ss_assert(count($GLOBALS['ss_menus'])===1,'exactly one native root: '.$role);
    $root=$GLOBALS['ss_menus'][0];ss_assert($root[3]==='sanro-studio'&&$root[4]==='sanro_studio_admin_landing','stable independent root and guarded landing: '.$role);
    ss_assert($root[2]===(isset($caps['manage_options'])?'manage_options':'manage_woocommerce'),'preserved union of prior capabilities: '.$role);
    $visible=[];
    foreach($GLOBALS['ss_submenus'] as $entry){
        ss_assert($entry[0]==='sanro-studio','no Studio child remains under WooCommerce: '.$role);
        if(current_user_can($entry[3]))$visible[]=$entry[4];
    }
    $expected=$role==='admin'?['sanro-studio','sanro-studio-status','sanro-studio-storage']:($role==='settings_only'?['sanro-studio']:($role==='shop_manager'?['sanro-studio-status','sanro-studio-storage']:[]));
    ss_assert($visible===$expected,'subpage permissions preserved: '.$role);
    ob_start();$denied=false;
    try{sanro_studio_admin_landing();}catch(SS_Denied $e){$denied=$e->getCode()===403;}
    $html=ob_get_clean();
    if($role==='subscriber')ss_assert($denied&&$html==='','unauthorized root denied');
    elseif($role==='shop_manager')ss_assert(!$denied&&strpos($html,'Integration Status')!==false&&strpos($html,'<form')===false,'manager root opens status without settings form');
    else ss_assert(!$denied&&strpos($html,'settings_type')!==false,'settings owner still gets original settings forms: '.$role);
}
$children=[];foreach($GLOBALS['ss_submenus'] as $entry)$children[$entry[4]]=$entry;
ss_assert($children['sanro-studio'][3]==='manage_options','settings capability unchanged');
ss_assert($children['sanro-studio-status'][3]==='manage_woocommerce'&&$children['sanro-studio-status'][5]==='sanro_studio_bootstrap_admin_page','old status slug/callback/capability preserved');
ss_assert($children['sanro-studio-storage'][3]==='manage_woocommerce'&&$children['sanro-studio-storage'][5]==='sanro_studio_storage_admin','storage slug/callback/capability preserved');
foreach(['woocommerce_admin_order_data_after_order_details'=>['sanro_studio_admin_order_preview_status','sanro_studio_admin_production_status','sanro_studio_admin_production_ready_action','sanro_studio_admin_drive_retry_action'],
         'woocommerce_after_order_itemmeta'=>['sanro_studio_admin_item_preview_status','sanro_studio_admin_item_dtf_status','sanro_studio_admin_item_dtf_readiness','sanro_studio_admin_item_generate_dtf_action']] as $hook=>$expected){
    $registered=[];foreach($GLOBALS['ss_hooks'][$hook] as $callbacks)$registered=array_merge($registered,array_column($callbacks,0));
    foreach($expected as $callback)ss_assert(in_array($callback,$registered,true),'Woo order panel remains: '.$callback);
}
ss_assert($GLOBALS['ss_options']===$before&&$GLOBALS['ss_writes']===[],'registration/rendering preserves stored settings without writes');
ss_assert(!sanro_studio_pricing_wc_enabled()&&!defined('SANRO_STUDIO_ENABLE_DTF_ADMIN_GENERATE')&&!defined('SANRO_STUDIO_ENABLE_DRIVE_PACKAGE_EXPORT'),'menu change activates no pricing or production writer');
ss_assert(sanro_studio_storage_gc()['error']==='storage_gc_disabled','GC remains default OFF');
ss_finish('Independent Studio menu / permissions / settings / order hooks');
