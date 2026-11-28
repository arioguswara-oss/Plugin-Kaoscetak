# SANRO Studio staging installation and post-install smoke — 24 November 2026

## Result

Owner approved the exact candidate and staging tree. The official installer applied it using its filesystem lock, durable journal and rollback retention. It returned `files_replaced`, with `activation_changed=false`, `flags_changed=false`, and `order_writes=false`. No production, merge, checkout, order, payment, or database-write flow was used.

## Candidate and installed state

- Staging: `https://saddlebrown-peafowl-616656.hostingersite.com`
- Source SHA: `081a63f78b88a3139327af6a545be1993b48506f`
- ZIP SHA256: `f66cdc28e3b2b1914df94ca4b4f6e40c7a84fb2da60fc01884a231ffbd903800`
- Manifest SHA256: `7452cbc476266cd3d36454445ebacbd0bb06dd1add80a6fe91277755501b9177`
- Previous tree digest: `e58d3cd03d3dba87ce17d8121e19720ab2dc9939394130c9fcd142ce8fd7bab9`
- Installed tree digest: `8d560cdb95611da98dc4a739737dff30ab9ac35f4ec6866defb8093808fd37db`; independently matched the archive-derived digest.
- SANRO Studio `0.1.0-dev` remains active; WooCommerce `11.3.0` remains active.
- Build marker reports the approved source and plugin digest. Web PHP is `8.3.37` on LiteSpeed/LSAPI (same-day authenticated Site Health; current HTTP response confirms the version). SSH PHP CLI is `8.2.36 / cli`.
- Transaction journal phase is `committed`. The prior tree remains as the verified rollback backup. Installer lock is released.
- All DTF, Google Drive, storage GC and staging checkout writer flags remain OFF.

## Admin and read-only smoke results

- Dynamic WordPress admin-menu registration found exactly one top-level SANRO Studio root, zero SANRO entries under WooCommerce, and SANRO children Pengaturan, Status Integrasi and Storage Privat.
- WooCommerce product editor field hook and order-detail panel hook are present.
- Public editor configuration GET for staging product `53` returned HTTP 200, valid configuration, and the approved source in `X-Sanro-Source`.
- Editor page GET returned HTTP 200 with SANRO editor markup and bootstrap config. Served `buyer.js` and `buyer.css` returned HTTP 200 and matched the candidate manifest SHA256 values.
- Browser screenshot and in-browser JavaScript execution: **NOT TESTED**; no browser binary/automation was available. No orders, checkout or payment flows were exercised.

## Seven-stage progress

| Stage | Before | Now | Delta |
|---|---:|---:|---:|
| Core/foundation | 95% | 95% | 0 |
| Product/backend/admin | 94% | 94% | 0 |
| Buyer frontend | 96% | 96% | 0 |
| Server pricing/quote | 96% | 96% | 0 |
| Save/ownership/share | 98% | 98% | 0 |
| Checkout/production/Drive | 97% | 97% | 0 |
| QA/release/mobile | 40% | 40% | 0 |

The official development baseline remains **90.50% → 90.50%, delta 0.00**; staging installation evidence does not change feature weights or claim feature-completion credit. Exact execution evidence: [installation-execution.json](../../artifacts/studio-v1/081a63f/installation-execution.json).
