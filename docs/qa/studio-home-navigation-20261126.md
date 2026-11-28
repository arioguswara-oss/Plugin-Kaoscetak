# SANRO Studio Home navigation — focused acceptance

26 November 2026. Implemented from PR #52 checkpoint `636b008d12d3bf0e1b3b122fc947a24c40446f19` on the existing integration branch.

## Implementation

- The shared SANRO Studio app bar renders a compact Home icon before its title and any editor back link. Its destination is `esc_url(home_url('/'))`; no site hostname is embedded in plugin code.
- Product selection and editor use the shared app bar. “Desain Saya” is inside the editor view and shares that same header. The 40 px Home control retains white contrast and focus styling on mobile and desktop.
- The editor uses its existing `editRevision`, `savedRevision`, `saving`, and `uploadingMaster` state. A fresh blank editor starts clean; changes, an active save, or master upload trigger confirmation. Cancel keeps the buyer on the editor; accept follows the normal Home link.

## Targeted verification

- PHP lint: `studio-ui.php`, `buyer.php`, `rest-cart-bootstrap-smoke.php`, and `test_sanro_buyer_render.php` — PASS.
- Shared header/cart bootstrap regression — 42 existing checks plus Home URL, order, fallback and invalid-cart assertions — PASS.
- Buyer editor render — 23 assertions, including escaped Home URL in app bar — PASS.
- Home navigation node regression — 6 clean/dirty/cancel/accept/save/upload/page-absence checks — PASS.
- Existing delegated Cart handler node regression — 9 checks — PASS.
- Buyer JS and navigation regression JS syntax — PASS.
- Candidate preflight — PASS PLAN_ONLY; PHP 8.5.4 CLI linted all candidate PHP members. Remote web SAPI, flags, and runtime were not tested.

## Reproducible candidate

Two builds from source `695b4410f394dbb862a2ef44c2d7753b852b7711` produced identical archive and manifest. ZIP SHA256: `167cc119fe4d86facddb05dfff51a651fd687ee8ae7a671c3251376435747dc9`; manifest SHA256: `58d291989ff44b90b9eef797cea908219026b2a94bfa41c20363ced0b57aae28`; plugin digest: `7e6ab118513dd2585c490d269e35bf81e0002ca3f91cb66a2da9acb7dc461a23`. Package and evidence are in [`artifacts/studio-v1/home-navigation-20261126/`](../../artifacts/studio-v1/home-navigation-20261126/).

## Gate status

Source checks and candidate build are PASS. Staging install and live mobile/desktop acceptance are NOT TESTED; the user directed that staging wait for the GDI-02 runtime capability correction. No SSH, MariaDB, credential, order, payment, production, merge, DTF, or Drive operation was performed. Release readiness remains pending the staging runtime gate; no Final Release PASS is claimed. Official functional baseline remains **96.50% → 96.50%, delta 0.00**.
