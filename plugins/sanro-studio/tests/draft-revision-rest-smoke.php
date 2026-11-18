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
function wp_json_encode($value) { return json_encode($value); }
function rest_ensure_response($value) { return $value; }
function sanro_studio_update_draft($id, $token, $design, $expected_hash = null) {
    return ['valid' => true, 'design_id' => $id, 'expected' => $expected_hash];
}
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
$image = ['front' => ['objects' => [['type' => 'image', 'x' => 1, 'y' => 1, 'width' => 2, 'height' => 2]]]];
$frontRef = '00000000-0000-4000-8000-000000000000-front-' . str_repeat('a', 64) . '.png';
$backRef = '00000000-0000-4000-8000-000000000000-back-' . str_repeat('a', 64) . '.png';
revision_assert(!sanro_studio_draft_image_binding_allowed($image, [], '00000000-0000-4000-8000-000000000000'), 'image without private master rejected');
revision_assert(!sanro_studio_draft_image_binding_allowed($image, ['back' => $backRef], '00000000-0000-4000-8000-000000000000'), 'opposite side master rejected');
revision_assert(sanro_studio_draft_image_binding_allowed($image, ['front' => $frontRef], '00000000-0000-4000-8000-000000000000'), 'same-side private master accepted');
revision_assert(!sanro_studio_draft_image_binding_allowed($image, ['front' => 'https://example.com/file.png'], '00000000-0000-4000-8000-000000000000'), 'public image URL rejected');
revision_assert(!sanro_studio_draft_image_binding_allowed($image, ['front' => $frontRef], '00000000-0000-4000-8000-000000000001'), 'cross-draft master rejected');
revision_assert(!sanro_studio_draft_image_binding_allowed($image, ['front' => $frontRef]), 'missing draft UUID rejected');
revision_assert(!sanro_studio_draft_payload_allowed($image), 'image draft still gated until restoration integration');
echo "PASS: 11 draft REST and image-binding checks\n";
