<?php
/**
 * Sprint 145 regression: real newline output is mandatory for Bash grep -Fqx.
 * Source inspection only: no WordPress bootstrap, DB, browser, or file writes.
 */
$root = dirname(__DIR__);
$probe = file_get_contents($root . '/ops/hostinger/sanro-studio-pr35-runtime-probe.php');
$runner = file_get_contents($root . '/ops/hostinger/sanro-studio-pr35-disabled-generator-stage.sh');
if (!is_string($probe) || !is_string($runner)) {
    fwrite(STDERR, "FAIL: staging probe/runner source unreadable\n");
    exit(2);
}
$count = 0;
function line_check($ok, $name) {
    global $count;
    if (!$ok) {
        fwrite(STDERR, "FAIL: staging probe line contract: $name\n");
        exit(1);
    }
    $count++;
}
foreach ([
    'SANRO_STAGING_RUNTIME: VERIFIED',
    'WOOCOMMERCE_HPOS: ENABLED',
    'LEGACY_ITEM_PREVIEW_HOOK: REGISTERED',
    'DTF_READONLY_ADMIN_PANEL: REGISTERED',
] as $label) {
    line_check(strpos($probe, 'echo "' . $label . '\n";') !== false,
        $label . ' must emit an actual PHP newline, not a single-quoted literal');
}
line_check(strpos($probe, "echo 'PROBE_STATUS: PASS' . \"\\n\";") !== false,
    'terminal status line has an actual newline');
line_check(strpos($runner, "staging runtime probe line missing") !== false,
    'failed exact-line probe is reported rather than silently exiting');
line_check(strpos($runner, "'PROBE_STATUS: PASS'") !== false
    && strpos($runner, "'DTF_GENERATOR_HOOKS: ABSENT'") !== false
    && strpos($runner, "'DTF_ADMIN_GENERATE: OFF'") !== false,
    'all mandatory preflight lines remain enforced');
line_check(strpos($runner, "SANRO_STUDIO_STAGE_DEPLOY_MODE:-plan") !== false
    && strpos($runner, "PR35_STAGING_MODULE_ONLY_APPROVED") !== false,
    'PLAN default and explicit APPLY approval still required');
echo 'PASS: ' . $count . " SANRO staging runtime probe newline and fail-closed checks\n";
