# SANRO Studio — Staging QA checkpoint (18 November 2026)

## Scope and provenance
Environment: Hostinger WordPress staging at `saddlebrown-peafowl-616656.hostingersite.com`, **not** production `kaoscetak.id`.
Evidence: owner-provided screenshots of Termius SSH, WordPress admin, and buyer editor in the project conversation. This report records observed results; it is not a complete automated browser test.
Branch: `work/sanro-studio-lumise-core-20261118`. Fatal admin fix: commit `ef9c525543c8c4c1d60810e20ad7f2b290418626`.

## Verified on staging
- PHP fatal on SANRO Studio admin identified in WooCommerce fatal log: undefined `sanro_studio_dependencies()` from `includes/admin.php:32`. The corrected `admin.php` was copied to staging after backup and passed `php -l`.
- Plugin reactivated and WordPress plugin readback reported SANRO Studio active.
- Owner screenshot confirms SANRO Studio admin status/capabilities page loads without the earlier fatal.
- `SANRO_STUDIO_ENABLE_BUYER_EDITOR` added to staging `wp-config.php` as `true` (line 105 in screenshot); `php -l wp-config.php` PASS.
- Owner screenshot confirms native buyer editor page renders with front/back tabs, shirt preview, color selector, size/quantity inputs, sleeve selector, image upload, text controls. **Controls are visible; functional interaction was not verified.**
- Hermes build script reported 22-file staging ZIP and smoke-test PASS, SHA-256 `145b08026126b3f991e25531abe6ccb0857e5711b2099da365ffa6f72fae9945`. This is build evidence, not a complete staging release gate.

## Not yet verified / limitations
- Buyer editor mobile layout is cramped; desktop-style columns appear on phone.
- Actual front/back switching, text/image manipulation, upload validation, saved drafts, pricing, product readiness, and end-to-end order flow are NOT TESTED.
- Checkout, private production master storage, Google Drive production folder integration, and production release are NOT READY.
- Backend admin currently exposes a status/capabilities page, not the full editable native settings panel.

## Approved UI/UX target (DECIDED, not implemented)
Owner supplied two concept images:
1. Backend: independent top-level WordPress sidebar **SANRO Studio**, with Ringkasan, Produk & Mockup, Area Cetak, Harga, Desain Pelanggan, Pesanan, Penyimpanan & Google Drive, Pengaturan Umum; product print-area and order design previews.
2. Buyer: mobile-first flow **Pilih Produk → Atur Desain → Desain Saya**, with front/back editing, design controls, save, price, cart and private designs.

Existing admin is under `WooCommerce → SANRO Studio`; moving to independent top-level menu is DECIDED but NOT IMPLEMENTED. Concept screenshots are visual references, not proof of running functionality. Do not copy Lumise code or branding.

## Safety / release gate
- Keep checkout disabled and production `kaoscetak.id` unchanged.
- Do not deploy/merge to production without owner approval.
- Next implementation priorities: independent backend menu and native settings, product configuration, responsive mobile buyer UI, interaction QA, and later checkout/storage integration.
- **GO**: continued staging development/QA. **NO-GO**: production.
