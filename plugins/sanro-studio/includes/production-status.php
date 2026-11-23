<?php
defined('ABSPATH') || exit;

/** Admin-only read model. No render, scheduling, OAuth writes or print approval. */
function sanro_studio_production_status($order,$item_id) {
    if(!current_user_can('manage_woocommerce'))return ['valid'=>false];
    $manifest=sanro_studio_production_item_manifest($order,$item_id);
    $previews=false;if(!empty($manifest['valid'])){$front=sanro_studio_bound_order_preview_bytes($order,'front',$item_id);$back=sanro_studio_bound_order_preview_bytes($order,'back',$item_id);$previews=!empty($front['valid'])&&!empty($back['valid']);}
    $guide=sanro_studio_production_font()!==false;
    if(!empty($manifest['valid'])){foreach(['front','back'] as $side)if(!sanro_studio_text_glyphs_supported($manifest['manifest']['sides'][$side]['text']))$guide=false;if(!sanro_studio_text_glyphs_supported($manifest['manifest']['product_name']))$guide=false;}
    $receipt=sanro_studio_order_package_receipt($order);
    return ['valid'=>true,'package_state'=>$receipt['state'],'package_recorded'=>!empty($receipt['valid']),'order_ready'=>sanro_studio_auto_drive_order_eligible($order),'design_ready'=>!empty($manifest['valid']),'previews_ready'=>$previews,
        'guide_capable'=>$guide,
        'generator_enabled'=>function_exists('sanro_studio_dtf_order_batch_enabled')&&sanro_studio_dtf_order_batch_enabled(),
        'drive_enabled'=>sanro_studio_drive_package_enabled(),'print_approved'=>false];
}
function sanro_studio_admin_item_production_readiness($order,$item_id) {
    $s=sanro_studio_production_status($order,$item_id);if(empty($s['valid']))return;
    $labels=['Pesanan: '.($s['order_ready']?'dibayar / disetujui produksi':'menunggu pembayaran / persetujuan'),
        'Desain & harga: '.($s['design_ready']?'terikat dan valid':'belum siap'),'Preview dua sisi: '.($s['previews_ready']?'tersedia':'belum lengkap'),
        'Panduan: '.($s['guide_capable']?'font tersedia':'font / aksara belum didukung'),
        'Generator: '.($s['generator_enabled']?'aktif; cek hasil kandidat':'nonaktif'),'Drive: '.($s['drive_enabled']?'aktif; cek berkas terverifikasi':'nonaktif'),
        'Paket pesanan: '.($s['package_recorded']?'tercatat lengkap; izin berkas mengikuti verifikasi Drive':($s['package_state']==='retry_queued'?'menunggu retry':($s['package_state']==='stale'?'perlu pemulihan konsistensi':'belum lengkap'))),
        'Persetujuan hasil cetak: belum diberikan otomatis'];
    echo '<div class="sanro-studio-production-status"><strong>'.esc_html('Kesiapan produksi').'</strong><p>'.esc_html(implode(' | ',$labels)).'</p>';
    if(function_exists('sanro_studio_verified_order_drive_url')){$url=sanro_studio_verified_order_drive_url($order);if($url)echo '<a target="_blank" rel="noopener noreferrer" href="'.esc_url($url).'">'.esc_html('Folder pesanan privat').'</a>';}
    echo '</div>';
}
