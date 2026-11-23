<?php
define('ABSPATH', __DIR__);
require dirname(__DIR__) . '/includes/draft-rest.php';
function sanro_studio_frontend_enabled() { return true; }
function verify($ok, $label) {
    if (!$ok) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    echo "PASS: $label\n";
}
verify(!sanro_studio_preview_upload_enabled(), 'preview upload disabled without explicit flags');
verify(!sanro_studio_draft_rest_enabled(), 'draft API disabled without explicit flag');
