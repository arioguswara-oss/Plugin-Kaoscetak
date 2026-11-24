# SANRO Studio — pre-install admin menu correction

24 November2026 WIB. Parent2230535 / PR52. Owner DECIDED: one independent SANRO Studio top-level admin menu; no Studio duplicate beneath WooCommerce. No installation or staging change authorized/performed. Source candidate is the commit carrying this correction; the new manifest/PR/artifacts/studio-v1/CURRENT.json pin the exact SHA after clean build. Candidatef45b43b is superseded for future installation.

## Cause confirmed

The native plugin itself registers both: `includes/admin.php` → add_menu_page(sanro-studio, manage_options) and `sanro-studio.php` → add_submenu_page(woocommerce, sanro-studio-status, manage_woocommerce). `includes/storage-management.php` also placed sanro-studio-storage under WooCommerce. Same native source, not evidence of two installations. Live WordPress MCP inventory during this correction: one active SANRO Studio0.1.0-dev; SANRO Studio Staging QA inactive; no active Lumise or Kaoscetak Lite Designer in the returned inventory. This corroborates the prior read-only browser's two native links. No plugin was disabled, deleted or edited on staging.

## Final registered hierarchy

| Parent | Child / stable slug | Capability / callback |
|---|---|---|
| SANRO Studio top-level | Pengaturan / sanro-studio | manage_options; unchanged settings renderer via guarded landing |
| SANRO Studio | Status Integrasi / sanro-studio-status | manage_woocommerce; existing status callback |
| SANRO Studio | Storage Privat / sanro-studio-storage | manage_woocommerce; existing storage callback, GC defaultOFF |

Root capability is the union of existing access: manage_options users keep their settings page; manage_woocommerce-only users can reach status/storage without gaining settings access. The root landing shows settings to settings admins and integration status to Woo managers; unauthorized users receive403. WordPress may retain its standard parent landing entry for managers; no second top-level registration or Woo-parent Studio submenu exists. Root registers before children so WordPress resolves page hooks consistently. Existing page slugs and settings-save redirect remain valid; no option key/value or DB migration.

Product configuration remains on WooCommerce product editing as its integration surface, not a duplicate global Studio menu. Order-level design/private preview/production readiness/retry and per-item preview/DTF/readiness/generate hooks remain registered at their existing hooks/priorities. No order data, price rules, storage processing or writer behavior changed.

## Targeted evidence

PASS:48 actual-bootstrap menu/role/landing/settings-preservation/order-hook assertions; affected standalone bootstrap contract including GC defaultOFF; foundation/settings37 assertions plus its4 existing5-assertion prerequisites. Changed6 PHP files lint PASS; whitespace PASS. Settings save capability/nonce/method guards and redirect stay intact. No Full Audit, production pipeline retest or unrelated prior PASS rerun.

VERIFIED is offline with WordPress contracts. Live corrected-menu rendering, candidate installation and post-install E2E NOT TESTED because deployment is not authorized. Previously observed web runtimePHP8.3.37/LiteSpeed64-bit/GD2.3.3 and classic cart/checkout evidence are reused; full flags/private roots/installed-tree digest/capabilities still require actual operator verification. Generator/Drive/GC defaultOFF.

## Progress and handoff

90.50→90.50%, delta0. Seven fixed weights/stage estimates unchanged; menu correction is a defect fix, not a second feature credit. New clean-source ZIP, commit marker, manifest and SHA256SUMS replace the old approval candidate. The prior candidate stays available as historical evidence but its request is marked superseded. New request remains unapproved/conditional until runtime gates and explicit Owner authorization. GO new source/candidate review; NO-GO automatic install/deploy/merge/production/order changes/writer activation. No Jack/Hermes/Codex launched.

Kandidat pengganti terverifikasi: source `8d817c2ba92b89d03eaa4d680173925361ce0a36`; ZIP SHA256 `ca55c23ef3b13408e45d4b011a7d47be3676aec7da6e2d3e906508873ae66afd`. Manifest/checksum dan request baru: `artifacts/studio-v1/8d817c2`; pointer `artifacts/studio-v1/CURRENT.json`. Semua approval/deploy tetapfalse; runtime corrected menu NOT TESTED.
