<?php
/** Sprint 152: static security contract for guarded Hostinger two-file DTF APPLY. */
$source = @file_get_contents(dirname(__DIR__) . '/ops/hostinger/sanro-studio-pr42-twofile-guarded-stage.sh');
if (!is_string($source) || $source === '') {
    fwrite(STDERR, "FAIL: PR42 guarded staging source missing\n");
    exit(2);
}
$count = 0;
function check152($ok, $description) {
    global $count;
    if (!$ok) {
        fwrite(STDERR, "FAIL: PR42 staging security contract: $description\n");
        exit(1);
    }
    $count++;
}
check152(strpos($source, 'set -Eeuo pipefail') !== false
    && strpos($source, 'umask 077') !== false, 'strict shell and private files');
check152(strpos($source, "'id-dci-web1631'") !== false
    && strpos($source, "'u606139982'") !== false
    && strpos($source, 'saddlebrown-peafowl-616656.hostingersite.com') !== false,
    'hard scoped staging host/user/docroot');
check152(strpos($source, 'SANRO_STUDIO_QA_ENV:-') !== false
    && strpos($source, "'staging'") !== false && strpos($source, 'EUID') !== false,
    'must be non-root Hostinger staging');
check152(strpos($source, 'SANRO_STUDIO_STAGE_DEPLOY_MODE:-plan') !== false
    && strpos($source, '!= \'plan\'') !== false
    && strpos($source, '!= \'apply\'') !== false,
    'default PLAN and exactly one permitted APPLY mode');
check152(strpos($source, 'SANRO_STUDIO_APPROVE_PR42_STAGE_APPLY:-') !== false
    && strpos($source, 'PR42_STAGING_TWOFILE_OFF_APPROVED') !== false,
    'separate PR42-specific owner approval required');
check152(strpos($source, 'SANRO_STUDIO_EXPECTED_SHA:-') !== false
    && strpos($source, '^[a-f0-9]{40}$') !== false
    && strpos($source, '"$HEAD" != "$SHA"') !== false,
    'exact candidate commit pin required');
check152(strpos($source, 'manual/sanro-studio-pr42-twofile-guarded-staging-20261122') !== false
    && strpos($source, 'clone -q --single-branch') !== false
    && strpos($source, 'fetch -q --no-tags origin') !== false
    && strpos($source, 'maintenance.auto=false') !== false
    && strpos($source, '--depth') === false
    && strpos($source, '--deepen') === false,
    'isolated candidate checkout');
check152(strpos($source, 'manual/sanro-studio-dtf-admin-staging-targeted-20261121') !== false
    && strpos($source, '31deb5e8e65a212e1e883b745187db1b685236e1') !== false,
    'immutable PR31 baseline reference');
check152(strpos($source, 'historical PR31 baseline branch moved') !== false
    && strpos($source, 'refs/remotes/origin/$BASE_BRANCH') !== false,
    'baseline SHA drift fails closed');
check152(strpos($source, 'dtf-image-render.php dtf-layer-render.php dtf-admin-order-status.php') !== false
    && strpos($source, 'unchanged dependency drifted') !== false,
    'unchanged DTF dependency integrity verified');
check152(strpos($source, 'for f in dtf-private-artifacts.php dtf-order-workflow.php; do') !== false
    && strpos($source, 'current staging file differs from approved PR31 baseline') !== false,
    'precisely two live modules and no silent overwrite');
check152(strpos($source, 'php -l "$SOURCE/plugins/sanro-studio/includes/$f"') !== false,
    'candidate PHP syntax checked before commit');
check152(strpos($source, 'SANRO_STUDIO_PR38_PROBE_PHASE=after') !== false
    && strpos($source, 'sanro-studio-pr38-runtime-probe.php') !== false,
    'existing WordPress HPOS runtime probe retained');
check152(strpos($source, "'HPOS_RUNTIME: ENABLED'") !== false
    && strpos($source, "'LEGACY_PREVIEW_STATUS_GENERATOR_HOOKS: REGISTERED'") !== false
    && strpos($source, "'READINESS_HOOK: REGISTERED'") !== false,
    'existing WooCommerce HPOS hooks preserved');
check152(strpos($source, "'READINESS_ENABLED: NO'") !== false
    && strpos($source, "'GENERATOR_ENABLED: NO'") !== false
    && strpos($source, "'DTF_RASTER_PRIVATE_BATCH: OFF'") !== false
    && strpos($source, "'DRIVE_WRITERS: OFF'") !== false,
    'readiness/generator/private writer/Drive all disabled');
check152(strpos($source, '"$MODE" == \'plan\'') !== false
    && strpos($source, 'READY_FOR_SEPARATE_PR42_STAGING_APPROVAL') !== false,
    'PLAN exits before any file write');
check152(strpos($source, 'ROLLBACK="$(mktemp -d "$STATE/pr42-rollback.XXXXXXXX")"') !== false
    && strpos($source, 'install -m 0600 "$INCLUDES/$f" "$ROLLBACK/$f"') !== false,
    'rollback is limited to only two private file snapshots');
check152(strpos($source, "trap on_exit EXIT") !== false
    && strpos($source, "trap 'exit 130' INT") !== false
    && strpos($source, 'ROLLBACK_TWO_FILE: RESTORED_VERIFIED') !== false,
    'rollback attempted on controlled failed/cancelled apply');
check152(strpos($source, 'flock -n 9') !== false
    && strpos($source, 'before_main') !== false
    && strpos($source, 'before_private') !== false
    && strpos($source, 'before_workflow') !== false,
    'lock and live target drift detection before commit');
check152(strpos($source, 'mv -f -- "$private_tmp"') !== false
    && strpos($source, 'mv -f -- "$workflow_tmp"') !== false
    && strpos($source, 'WRITE_STARTED=1') !== false,
    'two-file only replacement under rollback guard');
check152(strpos($source, '|| [[ "$before_main" != "$(sha256sum "$MAIN")" ]]; then') !== false
    && strpos($source, '|| "$before_main" != "$(sha256sum "$MAIN")"; then') === false,
    'post-APPLY bootstrap digest compared as Bash condition, not executed as a command');
check152(strpos($source, 'probe after "$QA/after.log"') !== false
    && strpos($source, 'SUCCESS=1') !== false
    && strpos($source, 'DEPLOYED_STAGING_ONLY_DTF_TWOFILE_DISABLED') !== false,
    'postflight verifies OFF flags before claiming deployment');
check152(!preg_match('/\b(?:wp\s+db|wp\s+option|wp\s+post|mysql|mysqldump|rsync)\b/i', $source)
    && !preg_match('/\b(?:update_option|wc_create_order|wp_insert_post)\s*\(/i', $source),
    'no direct database, order, or batch activation commands');
echo 'PASS: ' . $count . " SANRO PR42 two-file staging guarded APPLY policy checks\n";
