<?php
/** Temporary QA transport only. Never distributed with the Studio plugin. */
defined('ABSPATH') || exit;

function sswr_mixed_target() {
    return rtrim(home_url('/'), '/') === SSWR_TARGET && rtrim(site_url('/'), '/') === SSWR_TARGET;
}
function sswr_mixed_identity() {
    sswr_identity();
    if (!is_ssl()) throw new RuntimeException('https_required');
    $marker = json_decode(file_get_contents(WP_PLUGIN_DIR.'/sanro-studio/SANRO-STUDIO-BUILD.json'), true);
    if (($marker['source_commit'] ?? null) !== SSWR_SOURCE) throw new RuntimeException('installed_source_mismatch');
    sswr_flags();
}

/** Canonical expectations contain no owner token, address, artwork or private URL. */
function sswr_mixed_expectation($value) {
    if (!is_array($value) || count($value) !== 2 || !isset($value['designs'], $value['total_idr'])
        || !is_int($value['total_idr']) || $value['total_idr'] < 1
        || !is_array($value['designs']) || count($value['designs']) !== 2) throw new RuntimeException('two_designs_required');
    $designs = []; $products = []; $types = []; $sum = 0;
    foreach ($value['designs'] as $entry) {
        if (!is_array($entry) || count($entry) !== 5
            || !is_string($entry['design_id'] ?? null) || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/iD', $entry['design_id'])
            || !is_string($entry['public_id'] ?? null) || !preg_match('/^ID-[0-9]{5,20}$/D', $entry['public_id'])
            || !is_int($entry['product_id'] ?? null) || $entry['product_id'] < 1
            || !in_array($entry['type'] ?? null, ['simple','variable'], true)
            || !is_array($entry['lines'] ?? null) || !$entry['lines'] || count($entry['lines']) > 8
            || isset($designs[$entry['design_id']]) || isset($products[$entry['product_id']])) throw new RuntimeException('invalid_design_mapping');
        $lines = [];
        foreach ($entry['lines'] as $line) {
            if (!is_array($line) || count($line) !== 5 || !in_array($line['size'] ?? null, ['S','M','L','XL','2XL','3XL','4XL','5XL'], true)
                || !is_int($line['qty'] ?? null) || $line['qty'] < 1 || $line['qty'] > 999
                || !is_int($line['unit_idr'] ?? null) || $line['unit_idr'] < 1 || $line['unit_idr'] > 10000000
                || !is_int($line['variation_id'] ?? null) || $line['variation_id'] < 0
                || ($entry['type'] === 'simple' ? $line['variation_id'] !== 0 : $line['variation_id'] < 1)
                || !is_string($line['color'] ?? null) || !preg_match('/^#[a-f0-9]{6}$/D', $line['color'])
                || isset($lines[$line['size']])) throw new RuntimeException('invalid_line');
            $lines[$line['size']] = ['size'=>$line['size'], 'qty'=>$line['qty'], 'unit_idr'=>$line['unit_idr'], 'variation_id'=>$line['variation_id'], 'color'=>$line['color']];
            $sum += $line['qty'] * $line['unit_idr'];
        }
        ksort($lines);
        $designs[$entry['design_id']] = ['design_id'=>$entry['design_id'], 'public_id'=>$entry['public_id'], 'product_id'=>$entry['product_id'], 'type'=>$entry['type'], 'lines'=>array_values($lines)];
        $products[$entry['product_id']] = true; $types[$entry['type']] = true;
    }
    if ($sum !== $value['total_idr'] || count($types) !== 2 || count(array_unique(array_column($designs, 'public_id'))) !== 2) throw new RuntimeException('mixed_total_or_type_mismatch');
    ksort($designs);
    return ['designs'=>array_values($designs), 'total_idr'=>$sum];
}

function sswr_mixed_current_summary() {
    $verified = $GLOBALS['sanro_studio_pricing_checkout']['designs'] ?? null;
    $cart = WC()->cart;
    if (!is_array($verified) || count($verified) !== 2 || !is_object($cart)) throw new RuntimeException('native_mixed_authorization_missing');
    $items = $cart->get_cart(); $groups = []; $total = 0; $summary = [];
    foreach ($items as $line) {
        if (!sanro_studio_pricing_is_line($line)) throw new RuntimeException('foreign_cart_item');
        $groups[$line['sanro_studio_design_id']][] = $line;
    }
    if (count($groups) !== 2 || array_diff_key($verified, $groups)) throw new RuntimeException('design_substitution');
    foreach ($verified as $id=>$v) {
        // Re-run the real authorization, preview, server quote and product checks.
        $fresh = sanro_studio_pricing_checkout_design($id, $v['token'] ?? null, $groups[$id]);
        if (!is_array($fresh) || isset($fresh['existing_order_id']) || $fresh['contract'] !== $v['contract']) throw new RuntimeException('native_quote_or_ownership_changed');
        $prepared = sanro_studio_prepare_size_cart_lines($fresh['contract']);
        if (empty($prepared['valid'])) throw new RuntimeException('invalid_server_lines');
        $expected = []; foreach ($prepared['lines'] as $line) $expected[$line['sanro_studio_size']] = $line;
        $product_id = $fresh['contract']['product_id']; $product = wc_get_product($product_id); $lines = [];
        foreach ($groups[$id] as $line) {
            $size = $line['sanro_studio_size'] ?? ''; $q = $expected[$size] ?? null;
            if (!is_array($q) || ($line['product_id'] ?? null) !== $product_id
                || sanro_studio_pricing_quantity($line['quantity'] ?? null) !== $q['quantity']
                || (int)($line['variation_id'] ?? 0) !== ($q['variation_id'] ?? 0)
                || ($line['variation'] ?? []) !== ($q['variation'] ?? [])
                || ($line['sanro_studio_pricing_version'] ?? null) !== 1
                || ($line['sanro_studio_color'] ?? '') !== $q['sanro_studio_color']
                || !is_object($line['data'] ?? null)
                || sanro_studio_idr_charge($line['data']->get_price()) !== $q['sanro_studio_unit_price']
                || sanro_studio_idr_charge($line['line_subtotal'] ?? null) !== $q['quantity'] * $q['sanro_studio_unit_price']
                || sanro_studio_idr_charge($line['line_total'] ?? null) !== $q['quantity'] * $q['sanro_studio_unit_price']) throw new RuntimeException('cart_line_metadata_mismatch');
            $lines[] = ['size'=>$size, 'qty'=>$q['quantity'], 'unit_idr'=>$q['sanro_studio_unit_price'], 'variation_id'=>$q['variation_id'] ?? 0, 'color'=>$q['sanro_studio_color']];
            unset($expected[$size]);
        }
        if ($expected) throw new RuntimeException('missing_size');
        $summary[] = ['design_id'=>$id, 'public_id'=>sanro_studio_public_design_id($id), 'product_id'=>$product_id, 'type'=>$product->get_type(), 'lines'=>$lines];
        $total += $fresh['contract']['quote']['total_price'];
    }
    // This acceptance case deliberately excludes shipping, tax, coupons and fees.
    if (sanro_studio_idr_charge($cart->get_total('edit')) !== $total) throw new RuntimeException('cart_grand_total_mismatch');
    return sswr_mixed_expectation(['designs'=>$summary, 'total_idr'=>$total]);
}

/** Active transport blocks ALL Studio checkout on the exact staging target, even unarmed/expired. */
function sswr_mixed_checkout_boundary($data, $errors) {
    if (!sswr_mixed_target()) return;
    $cart = function_exists('WC') ? WC()->cart : null;
    $studio = false;
    foreach (is_object($cart) ? (array)$cart->get_cart() : [] as $line) if (sanro_studio_pricing_is_line($line)) $studio = true;
    if (!$studio) return;
    // Woo always supplies WP_Error here. Reject an unexpected caller too.
    if (!is_object($errors) || !method_exists($errors, 'add')) wp_die('Staging checkout boundary unavailable.', '', ['response'=>503]);
    $prior = $errors->get_error_codes();
    $errors->add('sanro_staging_mixed_boundary', 'SANRO staging acceptance: berhenti sebelum profil pelanggan, order atau pembayaran baru.');
    try {
        sswr_mixed_identity();
        $arm = get_option('sswr_mixed_arm', false);
        if (!is_array($arm) || ($arm['source'] ?? null) !== SSWR_SOURCE || ($arm['expires'] ?? 0) < time()) throw new RuntimeException('acceptance_not_armed');
        $summary = sswr_mixed_current_summary();
        if ($summary !== $arm['expected']) throw new RuntimeException('acceptance_case_mismatch');
        $digest = hash('sha256', wp_json_encode($summary));
        $key = 'sswr_mixed_seen_'.$digest;
        $first = add_option($key, ['source'=>SSWR_SOURCE,'expires'=>$arm['expires']], '', false);
        if (!$first && (get_option($key, [])['source'] ?? null) !== SSWR_SOURCE) throw new RuntimeException('duplicate_receipt_storage_failed');
        $result = ['source'=>SSWR_SOURCE, 'captured_at'=>gmdate('c'), 'state'=>$first?'MIXED_AUTHORIZATION_PASS_STOPPED_BEFORE_ORDER':'DUPLICATE_SUBMISSION_BLOCKED', 'summary'=>$summary, 'upstream_codes'=>array_values(array_filter($prior, static function($code){return is_string($code)&&preg_match('/^[a-z0-9_-]{1,80}$/iD',$code);})), 'order_created'=>false, 'payment'=>false, 'real_drafts_locked'=>false, 'locking_probe'=>'SEPARATE_ISOLATED_DATABASE_CAS_ROLLBACK'];
        update_option('sswr_mixed_evidence', $result, false);
    } catch (Throwable $e) {
        // The unconditional Woo error is already present, including storage failure.
        update_option('sswr_mixed_evidence', ['source'=>SSWR_SOURCE,'state'=>'MIXED_VALIDATION_FAILED_CLOSED','order_created'=>false,'payment'=>false], false);
    }
}
add_action('woocommerce_after_checkout_validation', 'sswr_mixed_checkout_boundary', PHP_INT_MAX, 2);

/** Exercise the production multi-row SQL CAS on isolated QA option rows; always roll back. */
function sswr_mixed_lock_probe() {
    global $wpdb;
    sswr_mixed_identity();
    if (!preg_match('/^[A-Za-z0-9_]+$/D', $wpdb->options)) throw new RuntimeException('invalid_table');
    $engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $wpdb->options));
    $transaction = $wpdb->get_var('SELECT @@in_transaction');
    if ($engine !== 'InnoDB' || (string)$transaction !== '0') throw new RuntimeException('isolated_innodb_transaction_required');
    $keys = []; $started = false; $result = null;
    try {
        if ($wpdb->query('START TRANSACTION') === false) throw new RuntimeException('transaction_failed');
        $started = true; $updates = [];
        $run = bin2hex(random_bytes(12));
        foreach ([1,2] as $index) {
            $key = 'sswr_mixed_probe_'.$run.'_'.$index; $keys[] = $key;
            $before = ['qa'=>true,'design'=>$index,'status'=>'draft'];
            $after = ['qa'=>true,'design'=>$index,'status'=>'locked'];
            if ($wpdb->insert($wpdb->options, ['option_name'=>$key,'option_value'=>maybe_serialize($before),'autoload'=>'no']) !== 1) throw new RuntimeException('fixture_insert_failed');
            $updates[] = ['key'=>$key,'before'=>$before,'after'=>$after];
        }
        // A stale second row must prevent BOTH rows changing.
        $stale = $updates; $stale[1]['before']['design'] = 999;
        $denied = false;
        try { sanro_studio_pricing_commit_order_drafts($stale); } catch (RuntimeException $e) { $denied = true; }
        if (!$denied) throw new RuntimeException('stale_batch_accepted');
        foreach ($updates as $u) if ($wpdb->get_var($wpdb->prepare("SELECT option_value FROM `{$wpdb->options}` WHERE option_name=%s", $u['key'])) !== maybe_serialize($u['before'])) throw new RuntimeException('partial_lock');
        sanro_studio_pricing_commit_order_drafts($updates);
        foreach ($updates as $u) if ($wpdb->get_var($wpdb->prepare("SELECT option_value FROM `{$wpdb->options}` WHERE option_name=%s", $u['key'])) !== maybe_serialize($u['after'])) throw new RuntimeException('lock_readback_failed');
        $denied = false;
        try { sanro_studio_pricing_commit_order_drafts($updates); } catch (RuntimeException $e) { $denied = true; }
        if (!$denied) throw new RuntimeException('duplicate_lock_accepted');
        $result = ['source'=>SSWR_SOURCE,'state'=>'ISOLATED_DATABASE_CAS_PASS','stale_batch_atomic_denial'=>true,'two_row_lock_readback'=>true,'duplicate_stale_submission_denied'=>true,'real_drafts_or_orders_touched'=>false];
    } finally {
        if ($started && $wpdb->query('ROLLBACK') === false) throw new RuntimeException('rollback_failed');
        foreach ($keys as $key) wp_cache_delete($key, 'options');
    }
    foreach ($keys as $key) if ($wpdb->get_var($wpdb->prepare("SELECT option_name FROM `{$wpdb->options}` WHERE option_name=%s", $key)) !== null) throw new RuntimeException('rollback_not_verified');
    $result['rollback_verified'] = true;
    return $result;
}

function sswr_mixed_cleanup() {
    $arm = get_option('sswr_mixed_arm', false);
    if (is_array($arm)) delete_option('sswr_mixed_seen_'.hash('sha256', wp_json_encode($arm['expected'])));
    foreach (['sswr_mixed_arm','sswr_mixed_evidence','sswr_mixed_lock_evidence'] as $key) delete_option($key);
}

function sswr_mixed_page() {
    if (!current_user_can('manage_options') || !current_user_can('install_plugins')) wp_die('Forbidden', '', ['response'=>403]);
    echo '<div class="wrap"><h1>SANRO mixed checkout acceptance</h1><p>Temporary exact-staging guard: all Studio submissions stop before orders while this transport is active. Remove transport after acceptance.</p>';
    try {
        sswr_mixed_identity();
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            check_admin_referer('sswr-mixed-'.SSWR_SOURCE);
            $action = $_POST['mixed_action'] ?? '';
            if ($action === 'arm') {
                $raw = $_POST['expectation'] ?? '';
                if (!is_string($raw) || strlen($raw) > 12000) throw new RuntimeException('invalid_expectation');
                $expected = sswr_mixed_expectation(json_decode(wp_unslash($raw), true, 16, JSON_THROW_ON_ERROR));
                sswr_mixed_cleanup();
                delete_option('sswr_mixed_seen_'.hash('sha256', wp_json_encode($expected)));
                update_option('sswr_mixed_arm', ['source'=>SSWR_SOURCE,'expires'=>time()+900,'expected'=>$expected], false);
                delete_option('sswr_mixed_evidence');
            } elseif ($action === 'probe') {
                update_option('sswr_mixed_lock_evidence', sswr_mixed_lock_probe(), false);
            } elseif ($action === 'cleanup') {
                sswr_mixed_cleanup();
            } else throw new RuntimeException('unknown_action');
        }
        foreach (['sswr_mixed_arm','sswr_mixed_evidence','sswr_mixed_lock_evidence'] as $key) echo '<h2>'.esc_html($key).'</h2><pre>'.esc_html(wp_json_encode(get_option($key, false), JSON_PRETTY_PRINT)).'</pre>';
        foreach (['arm'=>'Arm expected mixed checkout (15 minutes)', 'probe'=>'Run isolated atomic lock probe (rollback)', 'cleanup'=>'Remove acceptance evidence and arm'] as $action=>$label) {
            echo '<form method="post">'; wp_nonce_field('sswr-mixed-'.SSWR_SOURCE);
            echo '<input type="hidden" name="mixed_action" value="'.esc_attr($action).'">';
            if ($action === 'arm') echo '<label>Expected two-design JSON<textarea name="expectation" rows="12" cols="100" required></textarea></label>';
            submit_button($label); echo '</form>';
        }
    } catch (Throwable $e) { echo '<p>Acceptance operation refused. Verify source, HTTPS, exact staging, flags and input; no order is authorized.</p>'; }
    echo '</div>';
}
add_action('admin_menu', static function(){ if (sswr_mixed_target()) add_management_page('SANRO mixed acceptance', 'SANRO mixed acceptance', 'manage_options', 'sanro-mixed-acceptance', 'sswr_mixed_page'); });
