<?php
/**
 * Sprint 148: static security contract for the future Hostinger APPLY tool.
 * Never runs the installer, WP bootstrap, Git clone, SQL or HTTP.
 */
$base = dirname(__DIR__);
$deploy = file_get_contents($base . '/ops/hostinger/sanro-studio-pr38-readiness-disabled-stage.sh');
$probe = file_get_contents($base . '/ops/hostinger/sanro-studio-pr38-runtime-probe.php');
if (!is_string($deploy) || !is_string($probe)) {
    fwrite(STDERR, "FAIL: PR38 source unreadable\n");
    exit(2);
}
$checks = 0;
function pr38_check($yes, $reason) {
    global $checks;
    if (!$yes) {
        fwrite(STDERR, "FAIL: PR38 staging guard: $reason\n");
        exit(1);
    }
    $checks++;
}
pr38_check(strpos($deploy, 'SANRO_STUDIO_STAGE_DEPLOY_MODE:-plan') !== false,
    'PLAN must be default');
pr38_check(strpos($deploy, 'PR38_STAGING_READINESS_OFF_APPROVED') !== false
    && strpos($deploy, 'SANRO_STUDIO_APPROVE_PR38_STAGE_APPLY') !== false,
    'separate owner approval token for APPLY');
pr38_check(strpos($deploy, 'id-dci-web1631') !== false
    && strpos($deploy, 'u606139982') !== false,
    'specific Hostinger staging user and host');
pr38_check(strpos($deploy, 'saddlebrown-peafowl-616656.hostingersite.com') !== false,
    'exact isolated staging domain');
pr38_check(strpos($deploy, "BASE_SHA='5dfb26a784882356dac82b19d6d4f5883cecbce473d74ae2d7da4daaecacdf35'") !== false,
    'prior verified PR35 live bootstrap SHA');
pr38_check(strpos($deploy, "PREVIEW_SHA='fb9d36d5c196e0681799f214259075eaf2f6ea3e3f6076cc8ee935204d3fb25d'") !== false,
    'actual verified PR37 candidate bootstrap SHA');
pr38_check(strpos($deploy, 'SANRO_STUDIO_EXPECTED_SHA') !== false
    && strpos($deploy, 'git -C "$SOURCE" rev-parse HEAD') !== false,
    'exact Git SHA pinned');
pr38_check(strpos($deploy, 'flock -n 9') !== false,
    'concurrent deployments locked');
pr38_check(strpos($deploy, 'SANRO_STUDIO_PR38_PROBE_PHASE=before') !== false
    && strpos($deploy, 'SANRO_STUDIO_PR38_PROBE_PHASE=after') !== false,
    'independent before/after runtime');
pr38_check(strpos($probe, "['before', 'after']") !== false
    && strpos($probe, 'realpath(ABSPATH) !== $root') !== false,
    'runtime phase and WP root checks');
pr38_check(strpos($probe, 'custom_orders_table_usage_is_enabled') !== false,
    'HPOS live runtime verified');
pr38_check(strpos($probe, "'SANRO_STUDIO_ENABLE_DTF_ADMIN_READINESS'") !== false
    && strpos($probe, "'SANRO_STUDIO_ENABLE_DTF_ADMIN_GENERATE'") !== false
    && strpos($probe, "'SANRO_STUDIO_ENABLE_DTF_PRIVATE_ARTIFACTS'") !== false
    && strpos($probe, "'SANRO_STUDIO_ENABLE_DTF_ORDER_BATCH'") !== false
    && strpos($probe, "'SANRO_STUDIO_ENABLE_DRIVE_AUTO_PROVISION'") !== false,
    'all sensitive flags checked independently');
pr38_check(strpos($probe, 'READINESS_HOOK: ') !== false
    && strpos($probe, "sanro_studio_admin_item_dtf_readiness") !== false,
    'new hook absent before registered after');
pr38_check(strpos($probe, 'sanro_studio_dtf_admin_readiness_enabled() !== false') !== false,
    'readiness stays OFF after install');
pr38_check(strpos($probe, 'sanro_studio_dtf_admin_generate_enabled()') !== false,
    'existing generator stays OFF');
pr38_check(strpos($deploy, 'READY_FOR_EXPLICIT_PR38_STAGING_APPROVAL') !== false
    && strpos($deploy, 'WORDPRESS_FILES_MODIFIED: NO') !== false,
    'PLAN exits without WordPress writes');
pr38_check(strpos($deploy, 'ROLLBACK_BOOTSTRAP: RESTORED_VERIFIED') !== false
    && strpos($deploy, 'ROLLBACK_BOOTSTRAP: FAILED_MANUAL_INTERVENTION_REQUIRED') !== false,
    'rollback must verify restored bootstrap');
pr38_check(strpos($deploy, '[[ "$MAIN_CHANGED" -eq 0 || "$restored" -eq 1 ]]') !== false,
    'never delete new module if original bootstrap restoration fails');
pr38_check(strpos($deploy, 'mv -n "$MODULE_TMP" "$MODULE"') !== false
    && strpos($deploy, 'mv -f "$MAIN_TMP" "$MAIN"') !== false
    && strpos($deploy, 'cmp -s "$MODULE"') !== false,
    'staging atomic install and module readback');
pr38_check(strpos($deploy, 'STATUS: DEPLOYED_STAGING_ONLY_READINESS_DISABLED') !== false
    && strpos($deploy, 'STAGING_GENERATOR_ENABLED: NO') !== false,
    'successful deployment does not enable writers');
pr38_check(strpos($deploy, 'WOOCOMMERCE_ORDERS_MODIFIED: NO') !== false
    && strpos($deploy, 'PRODUCTION_MODIFIED: NO') !== false,
    'staging scope/no order changes');
pr38_check(strpos($probe, 'PROBE_STATUS: PASS') !== false
    && strpos($deploy, "'PROBE_STATUS: PASS'") !== false,
    'exact-line probe status and strict success');
echo 'PASS: ' . $checks . " SANRO staging DTF readiness guarded deployment checks\n";
