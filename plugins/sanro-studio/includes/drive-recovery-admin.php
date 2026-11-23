<?php
defined('ABSPATH') || exit;
function sanro_studio_drive_retry_form_order($set=null){static $id=0;if($set!==null)$id=(int)$set;return $id;}
function sanro_studio_admin_drive_retry_action($order) {
    if(!current_user_can('manage_woocommerce')||!sanro_studio_drive_package_enabled()||!sanro_studio_auto_drive_order_eligible($order))return;
    $job=$order->get_meta('_sanro_studio_drive_job',true);if(!is_array($job)||!in_array($job['state']??null,['blocked','retry_queued'],true))return;
    sanro_studio_drive_retry_form_order($order->get_id());
    echo '<p><button type="submit" class="button" form="sanro-drive-retry-'.esc_attr((string)$order->get_id()).'">'.esc_html('Coba ulang paket produksi').'</button></p>';
}
function sanro_studio_admin_drive_retry_footer() {
    $id=sanro_studio_drive_retry_form_order();if(!$id||!current_user_can('manage_woocommerce')||!sanro_studio_drive_package_enabled())return;
    echo '<form method="post" id="sanro-drive-retry-'.esc_attr((string)$id).'" action="'.esc_url(admin_url('admin-post.php')).'">';
    echo '<input type="hidden" name="action" value="sanro_studio_retry_production"><input type="hidden" name="order_id" value="'.esc_attr((string)$id).'">';
    wp_nonce_field('sanro_retry_production_'.$id,'sanro_retry_nonce');echo '</form>';
}
function sanro_studio_admin_drive_retry_post() {
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||!current_user_can('manage_woocommerce')||!sanro_studio_drive_package_enabled())wp_die('Forbidden','',['response'=>403]);
    $id=isset($_POST['order_id'])&&is_string($_POST['order_id'])&&ctype_digit($_POST['order_id'])?(int)$_POST['order_id']:0;
    $nonce=$_POST['sanro_retry_nonce']??null;if(!$id||!is_string($nonce)||!wp_verify_nonce($nonce,'sanro_retry_production_'.$id))wp_die('Forbidden','',['response'=>403]);
    $result=sanro_studio_request_production_retry(wc_get_order($id));if(empty($result['valid']))wp_die('Paket belum dapat dicoba ulang. Periksa status desain, pembayaran, atau proses yang sedang berjalan.','',['response'=>409]);
    $order=wc_get_order($id);$url=method_exists($order,'get_edit_order_url')?$order->get_edit_order_url():admin_url('admin.php?page=wc-orders&action=edit&id='.$id);
    wp_safe_redirect($url);exit;
}
