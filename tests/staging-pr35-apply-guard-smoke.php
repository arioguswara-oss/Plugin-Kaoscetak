<?php
/**
 * Sprint 145 — static safety contract for the proposed staging deployer.
 * Never executes any installer, WordPress bootstrap, or production write.
 */
$base = dirname(__DIR__);
$script = file_get_contents($base . '/ops/hostinger/sanro-studio-pr35-disabled-generator-stage.sh');
$probe = file_get_contents($base . '/ops/hostinger/sanro-studio-pr35-runtime-probe.php');
if (!is_string($script) || !is_string($probe)) exit(2);
$count145 = 0;
function check145($value, $name) {
    global $count145;
    if (!$value) { fwrite(STDERR, "FAIL: stage-guard: $name\n"); exit(1); }
    $count145++;
}
check145(strpos($script, "SANRO_STUDIO_STAGE_DEPLOY_MODE:-plan") !== false,
    'default mode must be plan');
check145(strpos($script, "SANRO_STUDIO_APPROVE_PR35_STAGE_APPLY") !== false
    && strpos($script, "PR35_STAGING_MODULE_ONLY_APPROVED") !== false,
    'explicit owner approval required for apply');
check145(strpos($script, "id-dci-web1631") !== false
    && strpos($script, "u606139982") !== false,
    'exact Hostinger staging user/host');
check145(strpos($script, "saddlebrown-peafowl-616656.hostingersite.com") !== false,
    'exact staging site');
check145(strpos($script, "SANRO_STUDIO_EXPECTED_SHA") !== false
    && strpos($script, 'git -C "$SOURCE" rev-parse HEAD') !== false,
    'SHA-pinned isolated Git source');
check145(strpos($script, "BASE_SHA='db91f263bca9132a2a6e4b4af9670570543eead574ab09c958cf96a504e39d8f'") !== false,
    'original live bootstrap exact SHA locked');
check145(strpos($script, "PREVIEW_SHA='5dfb26a784882356dac82b19d6d4f5883cecbce473d74ae2d7da4daaecacdf35'") !== false,
    'candidate bootstrap exact SHA locked');
check145(strpos($script, "SANRO_STUDIO_PR35_PROBE_PHASE=before") !== false
    && strpos($script, "SANRO_STUDIO_PR35_PROBE_PHASE=after") !== false,
    'before/after runtime inspection');
check145(strpos($script, "DTF_GENERATOR_HOOKS: ABSENT") !== false
    && strpos($script, "DTF_GENERATOR_HOOKS: REGISTERED") !== false,
    'admin hook absence and registration checked');
check145(strpos($probe, "'SANRO_STUDIO_ENABLE_DTF_ADMIN_GENERATE'") !== false
    && strpos($probe, "'SANRO_STUDIO_ENABLE_DTF_PRIVATE_ARTIFACTS'") !== false
    && strpos($probe, "'SANRO_STUDIO_ENABLE_DTF_ORDER_BATCH'") !== false
    && strpos($probe, "'SANRO_STUDIO_ENABLE_DRIVE_AUTO_PROVISION'") !== false,
    'all sensitive writer flags independently checked');
check145(strpos($probe, 'realpath(ABSPATH) !== $expectedRoot') !== false
    && strpos($probe, "site_url") !== false
    && strpos($probe, "home_url") !== false,
    'site URL and ABSPATH guard');
check145(strpos($probe, "custom_orders_table_usage_is_enabled") !== false,
    'HPOS enabled guard');
check145(strpos($script, "if [[ \"$MODE\" == 'plan' ]]") !== false
    && strpos($script, "WORDPRESS_FILES_MODIFIED: NO") !== false
    && strpos($script, "READY_FOR_EXPLICIT_PR35_STAGING_APPROVAL") !== false,
    'dry run exits before file modifications');
check145(strpos($script, 'trap on_exit EXIT') !== false
    && strpos($script, 'ROLLBACK_BOOTSTRAP: RESTORED') !== false,
    'rollback on failure');
check145(strpos($script, 'MODULE_ADDED=1') !== false
    && strpos($script, 'MAIN_CHANGED=1') !== false
    && strpos($script, 'SUCCEEDED=1') !== false,
    'write state tracked before and after apply');
check145(strpos($script, "flock -n 9") !== false,
    'parallel staging apply locked');
check145(strpos($script, 'PRODUCTION_MODIFIED: NO') !== false
    && strpos($script, 'WOOCOMMERCE_ORDERS_MODIFIED: NO') !== false,
    'scope limited to staging plugin file patch');
check145(strpos($script, "DEPLOYED_STAGING_ONLY_GENERATOR_DISABLED") !== false
    && strpos($script, 'STAGING_GENERATOR_ENABLED: NO') !== false,
    'success output never claims producer enabled');
check145(strpos($probe, "DTF_PRIVATE_ARTIFACTS: OFF") !== false
    && strpos($probe, "DTF_ORDER_BATCH: OFF") !== false
    && strpos($probe, "DRIVE_AUTO_PROVISION: OFF") !== false,
    'postflight writer states explicitly OFF');

echo 'PASS: ' . $count145 . " SANRO staging generator deployment guard assertions\n";
