<?php
/**
 * REST draft-update revision precondition regression.
 * Isolated: no WordPress boot, database, network, or production writes.
 */
define('ABSPATH', dirname(__DIR__, 3) . '/');
class WP_Error {
    public $code;
    public $data;
    public function __construct($code, $message, $data = []) {
        $this->code = $code;
        $this->data = $data;
    }
}
class RevisionRequest {
    private $params;
    public function __construct($params) { $this->params = $params; }
    public function get_param($key) { return $this->params[$key] ?? null; }
    public function get_header($key) { return 'owner-secret'; }
}
function sanro_studio_draft_payload_allowed($design) { return is_array($design); }
function sanro_studio_update_draft($id, $token, $design, $expected_hash = null) {
    return ['valid' => true, 'design_id' => $id, 'expected' => $expected_hash];
}
function sanro_studio_rest_draft_response($result) { return $result; }
require_once __DIR__ . '/../includes/draft-rest.php';
function revision_assert($ok, $label) {
    if (!$ok) { fwrite(STDERR, "FAIL: {$label}\n"); exit(1); }
}
$base = ['id' => 'draft', 'design' => []];
$missing = sanro_studio_rest_update_draft(new RevisionRequest($base));
revision_assert($missing instanceof WP_Error && $missing->code === 'sanro_revision_required', 'missing revision rejected');
$invalid = sanro_studio_rest_update_draft(new RevisionRequest($base + ['expected_design_hash' => 'wrong']));
revision_assert($invalid instanceof WP_Error && $invalid->code === 'sanro_revision_required', 'invalid revision rejected');
$hash = str_repeat('a', 64);
$valid = sanro_studio_rest_update_draft(new RevisionRequest($base + ['expected_design_hash' => $hash]));
revision_assert(($valid['expected'] ?? null) === $hash, 'valid revision passed to update');
$badDesign = sanro_studio_rest_update_draft(new RevisionRequest(['id' => 'draft', 'design' => 'invalid', 'expected_design_hash' => $hash]));
revision_assert($badDesign instanceof WP_Error && $badDesign->code === 'sanro_invalid_design', 'invalid design rejected');
echo "PASS: 4 draft REST revision checks\n";
