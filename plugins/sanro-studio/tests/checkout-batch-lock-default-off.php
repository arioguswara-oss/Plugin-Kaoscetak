<?php
/** No flags, WordPress or WooCommerce: checkout batch lock must be OFF. */
define('ABSPATH', __DIR__ . '/');
require dirname(__DIR__) . '/includes/checkout-batch-lock.php';
if (sanro_studio_stage_checkout_batch_lock_enabled()) {
    fwrite(STDERR, "FAIL: auto-lock enabled by default\n"); exit(1);
}
if (sanro_studio_lock_checkout_order_draft(null, [], '', '')['error'] !== 'batch_lock_disabled') {
    fwrite(STDERR, "FAIL: auto-lock did not reject disabled runtime\n"); exit(1);
}
sanro_studio_stage_checkout_lock_created_order(null);
echo "PASS: SANRO staging checkout batch lock defaults disabled\n";
