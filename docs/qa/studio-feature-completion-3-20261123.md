# SANRO Studio — feature completion 3

23 November 2026 WIB. Parent/checkpoint `d704b32f31a7e5ce7d904826dcc3f3f282b81e94`, Draft PR #52, branch `work/studio-mobile-checkout-integration-20261123`. Ledger source terakhir 80,50%; hasil putaran ini **83,50% (+3,00 poin implementasi berbobot)**. Source belum deployed.

## Lima pekerjaan

| Scope | IMPLEMENTED baru | VERIFIED dan batas |
|---|---|---|
| Checkout/pembayaran | Satu saved-order contract untuk retry, pay-existing-order, payment_complete callback dan produksi. Memeriksa semua size/qty/Design ID, pinned quote hash, snapshot desain, preview kedua sisi dan grand total. Metadata Studio tanpa Design ID tidak dianggap ordinary cart. Ongkir native Woo dari kalkulasi server disnapshot terpisah dan dibekukan pada lock | Recovery20 + shipping5 + payment/order consistency checks PASS dengan Woo/session/options doubles. Contoh Rp254.000 desain + Rp15.000 ongkir = Rp269.000; ongkir berubah ditolak. Gateway signature/auth tetap tanggung jawab gateway Woo; tidak membuat gateway atau menerima nominal browser |
| DTF | Provenance per sisi mencakup artwork, source master, font dan versi renderer. Sidecar schema2 dan basename v2 terpisah dari legacy. Artefak valid dipakai ulang sebelum planner/render sehingga retry tidak merender A3+ lagi; source berubah pada geometri yang sama ditolak. Guard memori juga berlaku pada private-byte read dan multipart upload | Actual GD/FreeType flow15 PASS termasuk retry A3+ di64MiB saat canvas baru akan ditolak. Workflow42 dan artifact31 PASS. Tidak memberi approval warna/fisik/font parity; output tetap candidate |
| Drive | Lease owner-token/expiry berbasis SQL INSERT IGNORE, CAS renew/takeover/delete; stale owner tidak bisa melepas lease pengganti. Bounded asynchronous backoff30/60/120/240/480 detik, maksimum5 retry setelah initial attempt. Retry lookup-by-identity memulihkan POST response hilang dan CRUD save gagal. Aset non-original memakai binding keyed by SHA, bukan menimpa binding versi lain | Lease contention/expired takeover/old-owner denial, response-lost recovery tanpa duplicate pada skenario uji, terminal failure stop, root hierarchy31 dan original upload24 PASS. HTTP/SQL disimulasikan; tidak mengklaim distributed exactly-once atau live Drive verified |
| PDF/manifest/admin | Manifest JSON mengindeks master, dua preview, PDF dan optional DTF candidates dengan MIME/bytes/SHA; self hash disimpan pada receipt. Semua item harus mempunyai receipt dengan fingerprint dan policy yang sama sebelum job menyatakan package_ready. Status admin membedakan recorded/pending/retry/blocked/stale dan approval cetak | Satu pesanan dua size menghasilkan8 aset terverifikasi simulasi; receipt parsial/stale tidak tampil lengkap. Existing Unicode PDF tetap dipakai, tidak diberi kredit ulang. Tautan privat tetap menggunakan remote permission verification, receipt lokal bukan bukti izin remote terkini |
| Lint/smoke/kandidat | Pemeriksaan hanya perubahan relevan; builder kandidat dari clean Git HEAD, file/blob hashes, ZIP reproducible, manifest commit/digest/default-OFF. Builder menolak source dirty dan tidak punya operasi deploy | PHP lint30 file berubah, shell/Python syntax dan whitespace PASS. Kandidat dibangun sesudah commit final, SHA/readback dicatat pada laporan akhir; belum dipasang pada WordPress |

## Desain konsistensi

`sanro_studio_saved_order_context()` tidak menghitung ulang harga katalog untuk order tersimpan. Ia memakai quote yang sudah disetujui server dan menolak perubahan saved amount/qty/shipping/snapshot. Awal checkout tetap memakai kalkulator resmi; subtotal desain tidak dicampur menjadi unit price.

Ongkir berasal dari `WC_Cart::get_shipping_total()/get_totals()` dan harus cocok dengan jumlah shipping line pada order. Pinned quote hash mencakup ongkir untuk order dengan ongkir nonzero. Order tanpa ongkir tetap kompatibel dengan hash lama. Pajak, fees dan diskon di luar policy yang disepakati ditolak, bukan diasumsikan gratis atau diterima dari client.

Hook `woocommerce_pre_payment_complete` diperiksa pada source resmi WooCommerce11.3: berjalan dalam try/catch sebelum clear awaiting-payment dan perubahan paid/status. Guard Studio read-only dapat menolak saved-order mismatch serta cancelled/refunded/trash. Callback duplicate yang valid diserahkan ke idempotensi status native Woo, tidak mencatat pembayaran kedua. Bukti source API bukan pengganti tes gateway nyata.

Lease tidak memakai `add_option` sebagai atomic create: source WordPress memakai upsert yang dapat menulis value pada duplicate key. INSERT IGNORE + owner/expiry CAS dipakai untuk insert-only acquisition. Lease baru900 detik, diperbarui sebelum mutasi remote/CRUD; timeout HTTP maksimal60 detik. Legacy lock tak berversi tidak dicuri otomatis. Tidak mengubah data lock pada hosting di putaran ini.

Manifest/receipt hanya berisi identitas order/item/desain, metadata produksi, hash, jenis/MIME/ukuran dan file IDs privat. Owner token, OAuth token, master bytes atau path privat tidak disalin ke receipt/status/admin HTML. Semua aset dibangun dari locked snapshot; sebelum dan sesudah upload snapshot diverifikasi kembali. Tanda package_ready adalah lengkap pada worker dengan remote verification saat upload, bukan izin publik atau human print approval.

## Bukti proporsional

| Pemeriksaan yang terdampak | Hasil |
|---|---|
| PHP lint30 file berubah; dua shell runner; Python candidate builder AST; diff whitespace | PASS |
| Order/payment/Drive recovery dan consistency | PASS34; mencakup lease, lost response, local-save failure, seluruh receipts, stale admin state, memory refusal dan retry limit |
| Native shipping snapshot | PASS5 |
| Existing same-order recovery | PASS20 |
| Actual GD/FreeType A3+ + front/back + provenance/reuse/legacy separation | PASS15; initial rendering worker768MiB, reuse64MiB |
| DTF batch / private artifacts | PASS42 /31; renderer prerequisites dieksekusi nested, tidak dihitung dua kali |
| Drive hierarchy / original uploader | PASS31 /24 |
| Saved line batch-lock / default-OFF bootstrap | PASS32 /default-OFF termasuk payment hooks |
| Production package38 + checkout17 | PASS sebagai prerequisite nested di flow consistency; tidak dijumlah ulang sebagai coverage terpisah |
| Kandidat builder menolak dirty source | PASS; packaging final memerlukan HEAD clean |
| Full local runner/GitHub Actions | NOT TESTED; tidak mengulang audit penuh |
| Browser source baru, real WP order/checkout/gateway, live OAuth/Drive, physical print | NOT TESTED |

Self-fix: callback dan produksi disatukan pada snapshot yang sama; quote dipin di order; menghindari upsert saat acquire lease; fixture renderer yang menguji rerender diubah menjadi skenario missing-side agar reuse tidak melemahkan error/partial guards; provenance ditambahkan pada trusted candidate doubles; expiry takeover dan source/layout mutation tetap ditolak. Tidak melemahkan validasi agar tes PASS.

## Kandidat integrasi staging

`tools/build-studio-candidate.py` membangun `sanro-studio-staging-candidate.zip` dan `candidate-manifest.json` dari tracked plugin source di commit yang bersih, tanpa tests, config hosting, secret, log atau order data. Manifest memuat source commit, setiap SHA256 file, plugin digest dan SHA256 ZIP. Bootstrap harus tidak mendefinisikan enable flags. File ZIP standar `sanro-studio/` siap menjadi bahan review/pengujian pada scope yang disetujui.

Kandidat ini terbatas pada alur yang didukung: Classic Checkout, simple product, satu desain/multi-size, whole-IDR native shipping, policy pajak/fee/diskon yang masih dibatasi. Bukan pernyataan seluruh backlog inti100% atau izin instalasi/deploy. Generator DTF dan Drive tetap default OFF. Build ulang identik dari HEAD menghasilkan ZIP yang sama.

## Blocker dan batas yang masih nyata

- Source baru belum deployed. HTTP422 checkpoint staging sebelumnya belum root-cause VERIFIED; tidak mengulang browser pada source lama atau mengubah order untuk mengaku E2E PASS.
- Bukti native Woo lifecycle/gateway callback/HPOS dan OAuth/permission akun Drive nyata masih diperlukan. Tidak mengubah provider token atau billing apa pun. Signature dan nominal callback gateway harus diverifikasi gateway Woo yang dipakai.
- Native shipping kini terintegrasi. Policy pajak/fees/coupon/repeat discount, Blocks/native variations/mixed-design cart tetap belum selesai; membutuhkan keputusan/spec dan implementasi tersendiri. Tidak menaikkan stage6 menjadi100%.
- Font masih coverage bundled Latin/Greek/Cyrillic dan simple Unicode layout; missing glyph/combining/complex shaping ditolak. Print fisik, RIP/white ink/mirror dan exact font parity bukan hasil validasi mesin.
- Backoff menangani hasil kegagalan yang dikembalikan worker, bukan watchdog untuk setiap jenis SIGKILL/mesin mati. Lease expiry memungkinkan event berikutnya mengambil alih; legacy lock tak berversi ditahan untuk review. Ambiguous remote matches fail closed; tidak ada klaim exactly-once di seluruh kondisi jaringan.
- Basename/artifact schema v2 tidak menghapus legacy. Retensi desain/order dan pembersihan artefak lama tetap mengikuti keputusan Owner yang belum final.

## Progres / keputusan

80,50% → **83,50%, +3,00 poin berbobot**. Core75→79 (+1,00), admin83→85 (+0,20), checkout/produksi/Drive84→93 (+1,80). Lainnya tetap. Subtotal83,35 + saldo Owner historis0,15 =83,50. Kredit hanya implementasi baru; tes, packaging, font/PDF/engine lama tidak dihitung ulang. Ledger tunggal `docs/ledger/PROGRESS-BASELINE.md`.

DECIDED: bobot tetap25/10/15/10/10/20/10, Development First, Full Audit90%, izin dev/commit/Draft PR, larangan deploy/merge/production/order DB. IMPLEMENTED: lima scope di atas. VERIFIED: focused source/GD checks dengan boundary doubles yang disebutkan. Runtime E2E/akun nyata masih NOT TESTED.

GO review development dan persiapan scope staging E2E. NO-GO merge/deploy/production/aktivasi generator atau Drive/claim final release. Tidak menjalankan Hermes/Codex/AI lain dan tidak menulis branch worker lain.

Referensi implementasi framework: https://github.com/woocommerce/woocommerce/blob/11.3.0/plugins/woocommerce/includes/class-wc-order.php dan https://github.com/WordPress/WordPress/blob/master/wp-includes/option.php (dibaca pada putaran ini).
