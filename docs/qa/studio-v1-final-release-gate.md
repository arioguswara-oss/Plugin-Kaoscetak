# V1 release gate — Owner scope revision f415ec55

This gate supersedes earlier live-Drive and payment requirements. It does not convert unperformed tests into PASS.

| Area | Required acceptance | Excluded/deferred |
|---|---|---|
| Core/admin/editor/pricing/ownership | Existing verified native features and relevant changed-source checks | Full unchanged test-suite repetition |
| Mockup dan produk lengan | Satu mockup per produk/warna/sisi, geometri tetap lintas ukuran, produk lengan terpisah, tabel harga masing-masing dan checkout campuran. [Kriteria dan status](studio-standard-mockup-and-sleeve-products-20261126.md). | Bukti checkout/pembuatan pesanan dan pembayaran dilaporkan terpisah. |
| Deteksi ukuran cetak | Kategori otomatis dari geometri cm per sisi, harga server, batas produk dan metadata order internal. [Kriteria dan bukti](studio-automatic-print-sizing-20261126.md). | Submit order/tarif live mengikuti batas uji staging. |
| Google Drive setup | Complete Administrator controls, secure storage, errors/validation, permission+nonce denial, OFF/unconfigured operation, HTTP doubles for lifecycle | Google Cloud configuration, Owner credentials/consent, actual upload/readback: DEFERRED BY OWNER |
| Guest Buyer First | For every buyer/frontend acceptance cycle, start in a fresh browser session with no WordPress authentication or previous cart. Prove signed-out SANRO Studio → WooCommerce Cart → WooCommerce Checkout without login; record a rendered thumbnail, Design ID, product, color, size, quantity and unchanged SANRO quote in the same Woo session. Verify Site Visibility and capture sanitized screenshots. | Administrator browser checks are diagnostic only and cannot satisfy buyer acceptance. Order creation and payment are separate evidence. |
| Checkout | Normal buyer valid design/cart/form and actual Woo after-validation boundary; native quote/ownership checks; existing order56 integrity/previews and existing-order customer checkout | No second order authorized; full fresh new-order creation is not claimed. Existing order56 creation evidence is reused. Gateway/payment settlement excluded |
| DTF/PDF | Prior order56 PNG/PDF/hash acceptance reused; unchanged generation code | Regeneration/physical printing/font-parity signoff |
| Release/install | Stable version, reproducible ZIP, manifest/checksums, no secrets/QA transport, exact-target installed digest/activation/private storage/flags and relevant smoke | Production/merge; GC stays OFF |

Normal buyer submission is stopped by a temporary exact-staging Administrator-only Woo after-validation guard BEFORE customer-profile/order/payment writes. It records native authorization+server quote and upstream validation codes without credentials/addresses, then unconditionally adds an error even if evidence storage fails. This proves the allowed checkout boundary; existing order56 proves creation/binding. The guard is outside the application ZIP and is deactivated after acceptance.

Release may be declared Final only after all required rows have evidence. Runtime Drive state must remain NOT CONFIGURED, actual OAuth/upload DEFERRED BY OWNER, never incorrectly labelled tested. Global historical estimates are separate from the mandatory release checklist; progress credit must follow the existing QA rubric and verified revised criteria.
