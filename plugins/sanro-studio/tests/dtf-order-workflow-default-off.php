<?php
/** No WordPress, GD, filesystem, batch flag, or public hooks. */
define('ABSPATH', __DIR__ . '/');
require dirname(__DIR__) . '/includes/dtf-order-workflow.php';
if (sanro_studio_dtf_order_batch_enabled()
    || (sanro_studio_dtf_prepare_order_item_candidates(null, 0)['error'] ?? null)
        !== 'order_batch_disabled'
    || (sanro_studio_dtf_order_item_private_status(null, 0)['error'] ?? null)
        !== 'artifact_storage_disabled') {
    fwrite(STDERR, "FAIL: DTF order batch did not fail closed\n");
    exit(1);
}
echo "PASS: SANRO DTF order batch defaults disabled\n";
