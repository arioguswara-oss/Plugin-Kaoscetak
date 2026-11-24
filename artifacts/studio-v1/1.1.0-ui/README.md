# SANRO Studio1.1.0 — UI review candidate

**INSTALLED ON STAGING / VISUAL ACCEPTANCE PENDING.** SANRO Studio1.1.0 is active on staging and its installed tree matches this candidate. The Owner still needs to complete browser visual and interaction acceptance; this is not a Final Release.

The source-bound staging transport was rebuilt from installer source commit `faa1323f31230fc10ff50d54784ab1cbc051447a` after independently matching the live staging tree digest to the previously approved Final Release 1.0.0 manifest. Transport SHA256: `a4a441b60d2b3d376cbe71e336d0359f7ddda26bb37feae9c76954842b5262da`. The HTTPS diagnostic/install routes require authenticated administrator capability; POST additionally requires the WordPress REST nonce and exact candidate source. The diagnostic GET is read-only. The mandatory HTTPS gate passed and the committed transaction retained the previous tree for rollback.

Source `12b2c043cc6ccbb3e7c828949bf524ff6f2e84b1`, PR #52. Plugin ZIP and manifest are reproducible byte-for-byte across two builds. `SHA256SUMS` covers the plugin, manifest, exact-target transport and installation readback. No credentials, QA utility or staging transport is inside the plugin ZIP. Before-install desktop screenshots are in `evidence/`; after-install screenshots await Owner browser acceptance.

Changes: black/yellow buyer catalog/editor, preserved multi-size native controls, selection/resize handle and D-pad, separate collection view and reopen/reorder fork actions. Independent six-section admin navigation, product/mockup/price interfaces, draggable/keyboard visual areas and physical cm/category presets; grouped Drive setup and order previews with secured guide-PDF action. Existing pricing, ownership, private storage, DTF and Drive gates preserved. Original vector fallback garment only; merchant images are preferred.

## Installation and acceptance status

- The exact-target transport passed its authenticated HTTPS runtime gate and installed the source-bound archive with lock, journal, retained rollback and checksum validation.
- Native 1.1.0, build marker and installed tree digest were verified. Runtime readiness, public product editor config and buyer asset hashes passed; writer flags remain OFF. No order, payment, DTF regeneration or live Drive test was run.
- The temporary transport was deactivated; the prior 0.3.3 utility was restored inactive. See `installation-execution.json` and `runtime-evidence.json`.

## Owner browser acceptance

Visual and interaction acceptance remains pending. Review catalog search, front/back, upload/text, drag/resize/D-pad/center/delete, multi-size and price controls, save, collection/share controls, admin tabs/print areas/Drive states/order 56, phone touch behavior and horizontal overflow. Capture after-install screenshots. No Google credentials are needed; live OAuth/upload remains deferred by Owner.

Credentials remain encrypted/native; live OAuth/upload is deferred by Owner. Production and merge are excluded. Permission/render/area/pricing checks passed; visual acceptance is still pending.
