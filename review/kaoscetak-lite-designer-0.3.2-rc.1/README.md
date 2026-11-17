# Kaoscetak Lite Designer 0.3.2-rc.1 — DRAFT QA candidate

**[Download the actual WordPress plugin ZIP](kaoscetak-lite-designer-0.3.2-rc.1.zip?raw=true)**.
Use this file, not GitHub's repository source archive. One directory:
`kaoscetak-lite-designer/`, 18 files, 66,819 bytes. No Composer/Node/CLI on the site.

SHA-256: `ef04d7b83d0bd31be35dafb23068270e3a48af8d7b495fcb650f513fde6f2dfd`

This is a continuation of actual HEAD 4381a5e on develop/lite-designer-v1.
Frozen candidate source: 5c60e111dc29606b0b67bb3f0a0ef9af2b18aa07.
CRC, metadata versions, deterministic rebuild, and exact source/archive/installed
file bytes were checked. Manifest includes every file hash. Pattern scan found
no credential signatures; that is not an absolute security guarantee.

**DRAFT, not FINAL/production VERIFIED.** No kaoscetak.id deployment, main merge,
Lumise/Flatsome/gateway/hosting change, real payment or merchant OAuth occurred.
The GitHub QA package can be retrieved by ChatGPT Work independently of CI
artifact availability. Real Drive authorization/upload is still NOT VERIFIED.

## Executed local results

- WordPress 7.1.4, WooCommerce 11.3.0, PHP 8.3.36, Chromium 156.0.8078.79;
  GPL Twenty Twenty-One Classic theme. Actual Flatsome was unavailable.
- Full final local suite exit 0: PHP syntax, all nine existing standalone suites,
  193 real WP assertions, custom wildcard S/L variation integration, isolated
  editor/mug regressions, four real storefront browser cases and lifecycle.
- Product → original-byte synthetic upload → image/text and cm save → multi-size
  preserved cart → native validation. 390px completed a local synthetic COD
  order with exact total and frozen snapshot; no money/card/provider SDK.
- 9,437,264 original bytes resumed after forced network failure in the synthetic
  transport. Same request/session offsets, no image conversion of master bytes.
- Invalid replacement preserves the previous upload; size/qty survive closing
  and reopening. Text size changes physical dimensions; preview size does not.
  Host-theme button controls, hidden retry state and dialog focus were checked.
- Owner/share token isolation, revocation/fork, real HTTP CSRF/authorization,
  text/thumbnail spoofing, file/status/quantity/colour checks, repeat pricing,
  production folder lost-response recovery and native PDF/admin preview checked.

Google and mail are mocked/intercepted **only inside the loopback fixture**;
this verifies application contracts, not real OAuth/CORS/SMTP/Drive permissions.
See [raw evidence](evidence/) and [verification](verification.json).
Fresh GitHub CI status is documented on the Draft PR separately from local PASS.

## Screenshots (synthetic data only)

| Viewport | Editor | Canvas | Native checkout validation |
| --- | --- | --- | --- |
| 320×740 | [Editor](screenshots/editor-mobile-320.png) | [Canvas](screenshots/editor-canvas-320.png) | [Errors](screenshots/checkout-errors-320.png) |
| 390×844 | [Editor](screenshots/editor-mobile-390.png) | [Canvas](screenshots/editor-canvas-390.png) | [Errors](screenshots/checkout-errors-390.png) |
| 768×1024 | [Editor](screenshots/editor-mobile-768.png) | [Canvas](screenshots/editor-canvas-768.png) | [Errors](screenshots/checkout-errors-768.png) |
| 1440×900 | [Editor](screenshots/editor-mobile-1440.png) | [Canvas](screenshots/editor-canvas-1440.png) | [Errors](screenshots/checkout-errors-1440.png) |

[Admin without Drive](screenshots/admin-no-drive-390.png) ·
[Local synthetic COD order](screenshots/synthetic-order-390.png) ·
[Native-order PDF guide](evidence/guide.pdf).

## Required before any owner-approved live upgrade

Read the [full report, compatibility risks, worker proposal, QA and rollback](../../docs/qa-0.3.2-rc.1.md).
Retain exact live v0.2.0 ZIP and verified files/DB/HPOS/options backups; verify
this checksum. Only after Rio approves, replace this plugin using WordPress
Upload Plugin and keep public designer OFF for admin QA. Keep Lumise and
Flatsome Child active. No real payment without separate approval.

Classic product forms/cart/checkout are the RC path. KLD Store API carts are
explicitly blocked pending Blocks order integration. Real Google OAuth/upload,
CORS/permissions/session lifetime, SMTP, Flatsome/Lumise, HPOS, physical HP 4 GB
and mobile PageSpeed >=90 are NOT VERIFIED. No alternate worker is deployed;
any upload relay needs prior cost/security approval. Automatic draft cleanup is
paused until retention is approved. Legacy metadata/carts need upgrade QA.

Rollback replaces only this plugin with retained v0.2.0. Preserve all designs,
orders and Drive files; do not restore an old DB over newer orders, delete
masters, change gateways/theme, or remove Lumise as a routine rollback.
