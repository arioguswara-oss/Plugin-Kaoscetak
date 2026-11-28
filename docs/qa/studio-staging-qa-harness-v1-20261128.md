# SANRO Studio staging QA harness status — 28 November 2026

## Existing harness reused

| Path | Reused capability | Current evidence/status |
|---|---|---|
| `tools/staging-web-release/` | Exact staging host/document-root/SAPI binding, signed package gate, capability/nonce, lock, journal and rollback | Present in source; not installed or activated in this cycle |
| `tools/staging-web-release/mixed-checkout-acceptance.php` | Revalidates the two-design Studio quote and ownership; Classic checkout boundary stops before order/payment; isolated database CAS duplicate/stale probe | CAS rollback was previously verified; browser mixed submit and duplicate submit remain untested |
| `plugins/sanro-studio/includes/checkout-policy.php` | Real `woocommerce_store_api_cart_errors` policy for Studio lines | Hook/source guard present; live Store API submission not tested |
| `tests/staging-hpos-order-metadata-readonly.php` | HPOS readback without writes | Existing order #56 preview and first guide PDF were read successfully in the separate admin follow-up |
| `ops/hostinger/sanro-studio-pr32-qa-order.php` | Exact staging, HPOS-only, zero-charge/productless synthetic CRUD fixture; plan/readback path | Useful for HPOS persistence isolation, but it does not exercise Studio's buyer checkout or order lock |
| `tests/staging-hpos-qa-order-policy-smoke.php` and prior candidate evidence | Synthetic fixture safeguards and prior 50 targeted Studio regressions | Reused; not rerun |

## Additional implementation

Added `tests/staging-qa-harness-contract.php`, a local-only source-contract test. It verifies staging pinning, capability/nonce controls, the currently enforced 403 acceptance boundary, Classic mixed guard, Woo Store API policy registration, HPOS read-only path, CLI fixture safeguards, and plugin-only installer packaging. It does not bootstrap WordPress or contact staging.

Added a deterministic QA bundle builder and a separate three-file QA ZIP. The QA bundle is not a WordPress plugin and is excluded from the V1.1.0 installer ZIP.

## Acceptance matrix

| Acceptance | Result |
|---|---|
| Exact staging/capability/nonce/installer separation contract | PASS, 15 focused offline assertions |
| Existing pagination positive fixture and 50 regressions | PASS, evidence reused |
| Preview and first production-guide PDF from existing HPOS order #56 | PASS, existing admin follow-up evidence reused |
| Private master runtime readback | NOT TESTED in this cycle |
| Frozen configuration under a new order snapshot | NOT TESTED live; offline regression evidence reused |
| Repeat discount with qualifying staging paid history | NOT TESTED live; offline regression evidence reused |
| Classic mixed buyer submission | NOT TESTED live; existing transport guard is a no-order validation path |
| Store API fail-closed live submission | NOT TESTED; actual Woo REST checkout can persist a draft/order and is not invoked |
| Duplicate browser submission | NOT TESTED live; isolated CAS probe PASS is a different assertion |
| HPOS order write using actual Studio checkout hooks | BLOCKED by current staging transport policy |

## Actual blocker / next authorized path

The candidate's exact-host transport UI declares new orders disabled and `admin_init` returns HTTP 403 for every acceptance POST. Its historical one-order/production helper is therefore unreachable. The existing PR32 CLI fixture is productless and deliberately does not run Studio checkout. Neither path can truthfully prove Studio order-locking and mixed/variable checkout persistence together.

Do not remove the 403, forge a request, use the Store API as a side door, or run the CLI writer to claim buyer-checkout evidence. ChatGPT Work needs an officially approved disposable staging site/database or an explicit revised staging policy for one synthetic unpaid order, including a verified no-email/no-outbound-webhook arrangement and an order retention/cleanup decision. Until then, write/checkout runtime gates remain open.

No application source, candidate ZIP, staging configuration, order, payment, or production state was changed. Official baseline remains 96.60%.

## Review independen setelah checkpoint 0af2e43

- **Artefak PASS.** SHA-256 cocok dengan `SHA256SUMS`. Arsip memiliki tiga entri dan dua payload cocok dengan manifest. 15/15 dipakai ulang. Checker memakai pola source dan tidak membuktikan execution order hook, konfigurasi mail/webhook, atau lifecycle WooCommerce.
- **Runtime tanpa checkout POST.** Buyer fixture #53 membuka sisi Belakang dan panel Desain Saya melalui sesi browser yang login. WooCommerce Features menampilkan HPOS sebagai penyimpanan order otoritatif dengan compatibility sync nonaktif. Daftar Webhooks WooCommerce kosong. Status ini belum membuktikan pemblokiran email atau seluruh trafik keluar.
- **Visual lokal PASS.** HTML editor dirender dari ZIP kandidat `7aa6eb2` dengan fixture, aset paket asli dan blokir trafik luar. Pada 320/390/430/1280 CSS px, preview, Simpan, Keranjang dan navigasi bawah berada dalam viewport tanpa overflow horizontal. Exact mobile live tetap terbuka. Bukti Android Owner dipakai ulang untuk penerimaan konsep.
- **Jalur disposable.** Dokumentasi Hostinger menyediakan hPanel → Website → Kelola → Staging apabila paket mendukung. Buat staging QA terpisah dari sumber staging yang sudah disetujui dan gunakan data sintetis. WordPress Connector mengembalikan `INVALID_ARGUMENT` pada daftar clone untuk host yang ada. Slot/paket hPanel dan kontrol egress belum diverifikasi. Salinan hanya layak dipakai sesudah URL/DB terpisah, payment nyata nonaktif, email ditangkap lokal dan webhook/fulfillment eksternal terblokir. ZIP harness adalah alat kontrak offline dan tidak perlu dipasang ke WordPress.
- **NO-GO Final Release.** 403 lama dipertahankan. Checkout POST, order, pembayaran dan perubahan konfigurasi nihil. Pengujian write/mixed/duplicate dan produksi menunggu disposable staging resmi serta keputusan retensi. Baseline tetap 96,60%.

[Bukti review independen](../../artifacts/studio-v1/staging-qa-harness-v1-20261128/independent-review.json)
