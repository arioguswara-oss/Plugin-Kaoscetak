# Ownership development integration

23 November 2026 WIB. Instruksi Owner terbaru memberi ChatGPT Work scope implementasi pada branch development terisolasi; ini menggantikan batas only-docs untuk tugas ini, tanpa mengubah branch milik worker lain.

| Pemilik | Scope |
|---|---|
| ChatGPT Work | work/studio-mobile-checkout-integration-20261123: mockup/mobile/checkpoint fixes; cart replay, multi-size order snapshot/lock, preview admin dan production package/Drive handoff; tes dan ledger |
| Hermes CLI | Existing #50 restore/native/Woo branch; tidak ditulis atau dijalankan oleh Work |
| Codex Cloud | Existing #51 pricing dan #48 runner; tidak ditulis atau dijalankan oleh Work |
| Owner | Persetujuan merge/deploy; tidak diminta untuk terminal manual pada tugas ini |

Base frozen #50: 45a7917c320403d1bc0d18caf7ee3398a3ecb416. Pricing #51 readback e9cb4679247f30223c33e51ddac1b2405dff961c, source kalkulator/adapter sudah ada pada base. Tidak melakukan import duplikat atau force-push ke branch worker. Dependency PR tetap harus direkonsiliasi sebelum merge; snapshot coordination Update 2 lama bukan gambaran source terbaru.

Staging hanya diuji melalui Cloud Browser dan WordPress read-only plugin inventory; operasi customer sintetis draft/master/preview dilakukan sesuai izin QA Owner. Tidak ada order/payment, reinstall, perubahan production, deployment baru, aktivasi DTF/Drive atau layanan worker.

Owner/session runtime AI eksternal tidak diinventarisasi atau dihentikan. Branch terisolasi dan fixed base menjaga scope Work tidak menulis ref yang sama; tidak diklaim sebagai lock global.

## Dependency feature-completion

| Jalur | Dependency dan urutan review | Pemilik perubahan putaran ini |
|---|---|---|
| #48 runner / #51 pricing | Sudah tercakup pada frozen base #50; jangan cherry-pick ulang | Tidak ada perubahan ref Cloud |
| #50 restore/Woo | SHA45a7917 dipertahankan; source base untuk #52 | Tidak ada perubahan ref Hermes |
| #52 development | Parent2d945be → feature-completion commit; review di atas #50 dahulu, lalu rekonsiliasi menuju integration/sanro-studio | ChatGPT Work saja pada branch terisolasi |
| Cart → checkout → locked item | current owner/revision previews → official quote → exact size allocation → saved amounts → snapshot/preview bindings | Work |
| Produksi → Drive | paid/admin-ready + locked allocation + private previews → preflight semua paket → existing verified item folders → gated uploads; optional DTF candidate perlu seluruh renderer/artifact/batch flags | Work; runtime semua writer tetap OFF |

Readback akhir #50/#51 tidak berubah. Draft PR#52 tetap draft, tidak merge/deploy. Tidak ada mekanisme untuk melihat/menghentikan seluruh sesi AI eksternal; isolasi branch, lease nonforce pada publish dan SHA readback menjaga Work tidak menimpa branch worker. Bukan klaim global lock atau worker lain telah dihentikan.

## Feature completion2 — parent3d989f2

Work melanjutkan PR#52 untuk lifecycle checkout recovery, A3+ raster/Unicode font, hierarchy Drive order/Produksi/item, Unicode PDF dan read-only admin readiness. Tidak mengubah ref#50/#51 atau menjalankan worker eksternal.

| Dependency | Urutan/alasan |
|---|---|
| Native Woo classic lifecycle | owner/session draft → official quote → saved item metadata/amounts → CAS draft lock + quote snapshot → private preview binding; retry hanya original awaiting-payment order |
| Existing-order pay | Woo nonce/order-key dahulu; guard Studio memeriksa saved quote/alokasi/private previews sebelum payment processing |
| Production typography | Bundled licensed DejaVuSans + font hash → browser font load/physical measurement → multiline server renderer atau embedded-font PDF; missing glyph/shaping ditolak |
| DTF | paid/admin-ready + locked item/master → geometry/source resolution/memory plan → front/back RGBA300DPI → output visibility/dimensions/density → private artifact SHA/order binding; approval manusia terpisah |
| Drive | preflight seluruh paket → order root → Produksi → item → original/preview/guide/manifest/optional DTF candidate; tiap ancestor/file verified privat, recovery lookup tanpa duplikasi |
| Admin | read-only eligibility/preview/font/writer status; private links hanya sesudah verification, tanpa mengaktifkan writer |

Source+assets+tests berada pada branch Work yang sama dengan lease publish expectedSHA. Tidak migrasi/delete folder legacy. Tidak ada deploy, order DB writes atau aktivasi generator/Drive. Frozen dependency#50/#51 tetap seperti checkpoint sebelumnya; runtime proof tidak dinaikkan hanya dari source.

## Feature completion 3 — parent d704b32

| Dependency baru | Urutan / ownership |
|---|---|
| Saved-order integrity | Source quote + native shipping → pinned order hashes → all-size amount/Design ID/snapshot/preview checks → retry/pay/callback/production. Work hanya di#52 |
| DTF v2 | Locked-side artwork/master/font hash → versioned candidate → verify/reuse sebelum planner → no render pada retry yang sudah valid. Legacy tetap disimpan |
| Drive worker | insert-only/CAS lease → whole-order preflight → hierarchy/assets → verified per-item receipts → seluruh item cocok → package_ready; kegagalan sementara dijadwalkan dengan retry limit |
| PDF/manifest/admin | Per-asset SHA index + policy/order fingerprints → receipt seluruh pesanan; stale/partial/retry tidak dianggap lengkap, print approval tetapfalse |
| Kandidat staging | Clean committed tree → plugin-only ZIP + commit/file/archive manifest. Bahan review; belum deploy dan tidak mengubah flag |

Tidak menulis #50/#51 atau menginisiasi Hermes/Codex/AI lain. Tidak mengklaim global runtime lock atas sesi AI eksternal. Ref#52 dipublish nonforce dengan expected SHA; dependency base tetap45a7917 sesuai checkpoint, tanpa merge.

## Feature completion4 — parent a143703

| Dependency | Implementasi/urutan |
|---|---|
| Catalog → quote | Preset Owner Cotton/Tunic → opsional tabel override terikat profile → validasi simple product/safe area → quote server/revision/sleeve label → cart → saved quote/order item. Harga live tidak mengganti order terkunci |
| Checkout policy | Cart Studio → tolak applied coupon/fee/tax/discount yang belum didukung → shipping native snapshot → saved-order/payment guard; keputusan pajak/fee/kupon belum dibuat |
| Running production | Root lease → persist run_id/attempt → jadwalkan watchdog sebelum network → preflight/heartbeat/asset receipts → complete. Silent crash → cek run+lease → bounded retry; active/renewed/replaced worker tidak diambil alih |
| Recovery admin | manage_woocommerce + order nonce + package gates + eligibility/integrity → queue worker yang sama; tidak menyatakan pembayaran sah atau mengaktifkan flag |
| Staging candidate | Clean commit → ZIP+build identity+manifest → local plan-only validator → review Owner → kelak deployment terpisah, runtime source/flags readback → E2E sintetis yang disetujui |

PR52 masih Draft dan ref Work saja yang boleh diperbarui. HEAD sebelum publish a143703, base45a7917; branch sumber Hermes/Cloud tidak disentuh dan source pricing tidak diimpor ulang. Tidak ada AI tambahan dipanggil. Tidak memiliki visibilitas seluruh sesi eksternal, sehingga tidak mengklaim global lock. Jika expected-SHA berbeda, hentikan publish dan rekonsiliasi dahulu.

Round4 hanya GET produk24 staging (HTTP200, product-type-simple, tanpa variations_form). Tidak melakukan draft/order/payment writes atau deploy; hasil browser putaran sebelumnya tetap bukti historis pada source lama. Runtime yang Owner laporkan e876035 tidak disamakan dengan kandidat baru.

## Checkpoint kuota terbatas24 November2026

Work melanjutkan daric04653a hanya pada PR52: implementasi installer existing-plugin dengan approval/source/target guards dan rollback;9 synthetic tests, tanpa WP CLI runtime/apply. Handoff `docs/qa/studio-hermes-handoff-20261124.md` siap, **Hermes tidak dijalankan**. Untuk melanjutkan kelak, worker terotorisasi menggunakan isolated worktree dari final HEAD; jangan menulis ref PR52 bersamaan dengan Work. Baseline89,50%, tidak Full Audit/deploy/merge/production/order writes. Frozen #50/#51 tetap, tidak impor ulang.

## Road90 — checkpoint siap Full Audit

Work saja pada PR52, parent5c54943. Implementasi journal crash recovery, authenticated Woo session/migration, verified repeat5% dan konsistensi locked discount ke PDF/manifest/admin. Baseline resmi90,50%; tidak ada perubahan bobot, branch#50/#51, deploy atau order DB. Full Audit belum dimulai; Hermes/Codex tidak dipanggil. Checkpoint Hermes yang sama diperbarui sebagai titik lanjut tunggal; jangan mengulang installer/quote/font/renderer lama tanpa perubahan. Release/runtime proof tetap terpisah dari source implementation.

## Full Audit & self-fix24 November2026

Gate90,50%/parent76b3fbb terverifikasi. Work menjalankan lima kelompok audit source dan self-fix pada branch PR52 saja; source/option/fixture/ZIP verification lokal serta plugin inventory/public config staging read-only. Tidak mengaktifkan writer, menjalankan worker atau menulis order. Baseline tidak dinaikkan untuk audit/tes; H04/H05/M06/M07 remain open dan release NO-GO. Checkpoint audit dan paket staging baru menjadi referensi terbaru; source SHA akhir terikat manifest/PR. Published ref memakai expected parentSHA; tidak mengklaim sesi AI eksternal berhenti.


## Final native gap closure — 24 November2026 WIB

ChatGPT Work hanya menulis isolated branch PR52 dari expected parent591bf1b. Scope: native collection/storage/BG/mockup, affected order-preview integration, targeted tests dan candidate/checkpoint. Tidak ada Full Audit baru, AI tambahan, Hermes/Codex, branch#50/#51 writes atau global external-agent lock claim. Base#50 tetap45a7917 dan pricing#51e9cb467 sudah termasuk; jangan import ulang. Publikasi nonforce dengan expected SHA; hentikan bila ref bergerak. Runtime production/order database/writers tidak disentuh. Baseline tetap90,50%; gap-code CLOSED tidak sama dengan staging VERIFIED.
