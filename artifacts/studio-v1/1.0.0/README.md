# SANRO Studio V1 Final Release —1.0.0

Installed/active on authorized staging; source4f5d31ee2a1244674a8a7111cd43f3e695bbf218. Scope: Owner revision f415ec55, docs/qa/studio-v1-final-release-gate.md. Production/merge not authorized.

ZIP: sanro-studio-1.0.0.zip. Verify SHA256SUMS before installation. Reproduce with `python tools/build-studio-candidate.py --release --out /tmp/sanro-release` at the clean immutable source above. Two builds and manifests matched exactly. File inventory/build identity are in release-manifest.json; installed tree independently matches ZIP. Installation and configuration notes plus release notes are inside the plugin ZIP. No credential, test fixture, temporary QA utility or staging transport is bundled.

Drive setup feature IMPLEMENTED/VERIFIED; current configuration NOT CONFIGURED and export OFF. Actual Google Cloud/OAuth consent/upload/readback are DEFERRED BY OWNER, not tested or blockers.

Checkout acceptance: fresh synthetic buyer draft, front/back private previews, S/M/L server/cart/form total240000IDR and normal Place-order validation PASS. A temporary exact-target guard blocked BEFORE creation of any new order/payment/customer-profile update. Only upstream error was payment (no gateway methods configured), outside Studio development. Existing unpaid order56 creation/binding/preview evidence is reused. No claim of a second fresh order or payment success. Staging transport/checkout guard was deactivated after acceptance; final native cart remained valid afterward.

DTF/PDF/hash evidence for order56 reused without regeneration. Print/font parity operator approval remains separate. GC and temporary DTF/Drive writers OFF. Native features/private storage remain active. Read checkout-acceptance.json and runtime-evidence.json for precise evidence boundaries.
