<?php
/**
 * Sprint 151 / PR41: source-only staging plan guard regression.
 * No WP bootstrap, private PHP-GD files, GitHub network, or order writes.
 */
$root = dirname(__DIR__);
$path = $root . '/ops/hostinger/sanro-studio-pr41-twofile-readonly-plan.sh';
$script = @file_get_contents($path);
if (!is_string($script) || $script === '') {
    fwrite(STDERR, "FAIL: PR41 staging PLAN script unavailable\n");
    exit(2);
}
$count = 0;
function check151($okay, $label) {
    global $count;
    if (!$okay) {
        fwrite(STDERR, "FAIL: PR41 staging PLAN: $label\n");
        exit(1);
    }
    $count++;
}
check151(strpos($script, 'set -Eeuo pipefail') !== false
    && strpos($script, 'umask 077') !== false,
    'fail-closed private temporary checkout');
check151(strpos($script, "'id-dci-web1631'") !== false
    && strpos($script, "'u606139982'") !== false,
    'exact Hostinger staging host and Unix user');
check151(strpos($script, 'saddlebrown-peafowl-616656.hostingersite.com') !== false
    && strpos($script, '/home/u606139982/domains/$HOST/public_html') !== false,
    'staging root hard-scoped');
check151(strpos($script, 'SANRO_STUDIO_QA_ENV:-') !== false
    && strpos($script, "'staging'") !== false
    && strpos($script, 'EUID') !== false,
    'no root or production context');
check151(strpos($script, 'SANRO_STUDIO_STAGE_DEPLOY_MODE:-plan') !== false
    && strpos($script, '!= \'plan\'') !== false,
    'apply mode strictly rejected');
check151(strpos($script, 'SANRO_STUDIO_EXPECTED_SHA:-') !== false
    && strpos($script, '^[a-f0-9]{40}$') !== false
    && strpos($script, '"$HEAD_SHA" != "$EXPECTED"') !== false,
    '40-digit PR41 SHA verified before source comparison');
check151(strpos($script, 'manual/sanro-studio-pr41-twofile-staging-plan-20261122') !== false
    && strpos($script, 'git clone -q --depth 1 --single-branch') !== false,
    'dedicated candidate branch checkout');
check151(strpos($script, 'manual/sanro-studio-dtf-admin-staging-targeted-20261121') !== false
    && strpos($script, '31deb5e8e65a212e1e883b745187db1b685236e1') !== false,
    'fixed previously staged PR31 source baseline');
check151(strpos($script, 'got_base') !== false
    && strpos($script, '"$got_base" != "$BASE_SHA"') !== false,
    'PR31 baseline branch movement blocked');
check151(strpos($script, 'dtf-image-render.php dtf-layer-render.php dtf-admin-order-status.php') !== false
    && substr_count($script, 'cmp -s') >= 4,
    'unchanged renderer and admin dependency comparison');
check151(strpos($script, 'for f in dtf-private-artifacts.php dtf-order-workflow.php; do') !== false
    && strpos($script, 'deployed file differs from exact PR31 baseline') !== false,
    'only two changed runtime modules selected');
check151(strpos($script, 'php -l "$new"') !== false,
    'both updated runtime PHP modules linted');
check151(strpos($script, 'SANRO_STUDIO_PR38_PROBE_PHASE=after') !== false
    && strpos($script, 'sanro-studio-pr38-runtime-probe.php') !== false,
    'existing verified WordPress staging runtime probe reused');
check151(strpos($script, "'HPOS_RUNTIME: ENABLED'") !== false
    && strpos($script, "'LEGACY_PREVIEW_STATUS_GENERATOR_HOOKS: REGISTERED'") !== false
    && strpos($script, "'READINESS_HOOK: REGISTERED'") !== false,
    'Woo HPOS plus existing admin hooks must be present');
check151(strpos($script, "'READINESS_ENABLED: NO'") !== false
    && strpos($script, "'GENERATOR_ENABLED: NO'") !== false
    && strpos($script, "'DTF_RASTER_PRIVATE_BATCH: OFF'") !== false
    && strpos($script, "'DRIVE_WRITERS: OFF'") !== false,
    'all dangerous runtime features remain disabled');
check151(strpos($script, '$main_before') !== false
    && strpos($script, '$private_before') !== false
    && strpos($script, '$workflow_before') !== false,
    'detects live file drift during read-only PLAN');
check151(strpos($script, 'WORDPRESS_FILES_MODIFIED: NO') !== false
    && strpos($script, 'WOOCOMMERCE_ORDERS_MODIFIED: NO') !== false
    && strpos($script, 'PRODUCTION_MODIFIED: NO') !== false,
    'reports site and order non-mutation intent');
check151(strpos($script, 'APPLY_IMPLEMENTED: NO') !== false
    && strpos($script, 'READY_FOR_SEPARATE_PR41_STAGING_REVIEW') !== false,
    'never claims deployment authorization or completion');
check151(strpos($script, 'git -C "$SOURCE" show') !== false
    && strpos($script, '"$QA/old-$f"') !== false,
    'old baseline extracted in isolated scratch only');
check151(!preg_match('/\b(?:wp\s+db|wp\s+option|wp\s+post|wp\s+plugin|mysql|mysqldump|rsync|chmod|install|mv)\b/i', $script)
    && !preg_match('/\b(?:file_put_contents|update_option|wc_create_order)\s*\(/i', $script),
    'no WordPress installer, file move, option or order writer');
echo "PASS: $count SANRO PR41 two-file staging PLAN safety checks\n";
