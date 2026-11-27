# SANRO Studio Owner UI/UX correction — 27 November 2026

## Scope and source

- Repository: `arioguswara-oss/Plugin-Kaoscetak`, PR #52, integration branch.
- GitHub checkpoint: `0bb6d86a3f2a0edea5f39d26ed72eedf75a064b3`.
- Current staging evidence is from installed source `c5ca4c4`; this correction is not installed.
- Candidate version: 1.1.0. DTF, Google Drive and production remain out of scope.

## Implementation

- Added four selected-object corner resize handles and a rotation handle above the selection. Resizing uses a proportional scale and existing size input events; rotation uses existing rotation and bounds validation. Drag/pinch code and `touch-action` on the artwork are unchanged.
- Removed visible image size/rotation sliders and duplicated center/delete controls from the image panel. The panel has one “Unggah Gambar” entry point; the private production master upload remains separately named and available.
- The background tolerance control appears after “Hapus Background” is selected. Removal runs only after the explicit apply action; restore-original remains available.
- Save UI reports unsaved/saving/saved/error, displays the public Design ID, guards repeated saves, and preserves editor state on error. Cart invokes the existing save path for dirty drafts. When a new local image needs private master binding, Cart waits for the master upload step.
- No backend, ownership, collection-authentication, relationship, ID-allocation or private-file policy changes were made.

## Targeted validation

- PHP lint: `buyer.php`, `pricing-integration.php`, `public-design-id.php` — PASS.
- JavaScript syntax: `buyer.js`, `studio-ui.js`, transform contract test — PASS.
- `studio-ui-transform-node.cjs`: corner/rotation UI contract, hidden image sliders, print bounds and estimator — PASS.
- `buyer-image-preview-node.cjs`: 10 private master/preview assertions — PASS.
- `buyer-print-zone-draft-node.cjs`: 27 side-specific geometry/draft/save assertions — PASS.
- `buyer-cart-handler-node.cjs`: 9 delegated Cart assertions, including save-before-Cart — PASS.
- `public-design-id.php`: stable UUID mapping, monotonic allocation, retry uniqueness and growth — PASS (existing targeted test reused).
- `store-api-mini-cart-preview.php`: session-bound private artwork only — PASS (existing targeted security smoke reused).
- `test_sanro_buyer_render.php`: 23 assertions — PASS.
- Guest checkout and artwork/thumb evidence from `c5ca4c4`, plus Owner Android PASS for Home/upload/drag/pinch/Mini Cart/Cart/Checkout, reused for unchanged paths.

The new direct handles have source/geometry contract coverage, not a fresh rendered-device test. Fresh resize/rotation touch behavior, save/edit/reopen UI, ID continuity through a new revised checkout, and post-transform dimensions/pricing on staging remain **NOT TESTED** pending installation and Guest Buyer acceptance. No new order or payment was submitted.

## Candidate and release gate

Candidate source SHA: `bcd6bf330599d1cbbc381a2f4999cb0c500bdda1` (PR source commit). Candidate plugin digest: `51c54994028fa1dc7950673260a7b4df831b6d71ab34d1e5a14a5fa4b6cea713`. ZIP SHA256: `9d586f6888bac6101a59502378d07e84dff68ea5e2d01bd261469a5003c91ca2`. Manifest SHA256: `809a1f289e9a8375f46ae2a30f71bc756d6db78ebf8cd6e73b8dcd16dccced59`. Two builds from the identical clean source produced identical ZIP and manifest hashes. Official local preflight: **PASS PLAN_ONLY**, local PHP CLI lint PASS; remote runtime, writer flags and runtime E2E NOT TESTED. Installed staging digest from prior source `c5ca4c4` is `0ee4ec4ffffd0067c3fa53fbc6c2803009b5852bb796a871d6d1ec91b3dcb0f5`; it does not match/verify this candidate because this revision has not been installed. Staging installation and authenticated HTTPS gates were not rerun. The current installed staging digest/source still refer to the prior candidate and do not verify this UI revision. Final Release remains **NO-GO** until the new candidate passes its mandatory staging acceptance.

Baseline remains **96.50%** (delta 0.00); no progress credit was claimed for unverified runtime behavior.
