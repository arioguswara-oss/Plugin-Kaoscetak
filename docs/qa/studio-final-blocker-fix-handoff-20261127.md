# SANRO Studio V1.1.0 — final blocker fix / ChatGPT Work handoff

27 November 2026 WIB · repository `arioguswara-oss/Plugin-Kaoscetak` · branch `work/studio-mobile-checkout-integration-20261123` · PR #52.

## 1. Progres

**GO candidate handoff. NO-GO Final Release.** Baseline resmi tetap **96,60% → 96,60% (Δ 0,00)**. Checkpoint GitHub awal `f05ac3a2512e16aba2f91f7b8c0a7cbe5872921d` dibaca bersama ledger, CURRENT dan runtime evidence. Installed staging tetap **`bf8ba02c4dbb073bc140085d0e6c703ffbe79beb`**, digest **`ec1ea86b6b21fd01b8014169164f74dceb45b48178c90fc8857d4addfc740d1d`**, berdasarkan evidence ChatGPT Work; sesi ini tidak melakukan instalasi, SSH, credential, order atau pembayaran.

## 2. Tabel tahap

| Tahap SANRO-7-v1 | Bobot | Completion historis | Kontribusi | Status perubahan |
| --- | ---: | ---: | ---: | --- |
| Fondasi/core | 25% | 95% | 23,75 | Bukti sebelumnya dipakai |
| Backend produk/admin | 10% | 95% | 9,50 | Mapping fix lokal PASS; acceptance kandidat baru pending |
| Frontend buyer | 15% | 96% | 14,40 | UI source tidak berubah; Guest Checkout/width live tetap terbuka |
| Harga server/quote | 10% | 96% | 9,60 | Native quote dan mapping tetap authoritative |
| Simpan/ownership/share | 10% | 98% | 9,80 | Otorisasi native dipertahankan |
| Checkout/produksi/Drive | 20% | 97% | 19,40 | Mixed boundary disiapkan; order #56 direuse; DTF/Drive OFF |
| QA/rilis/mobile | 10% | 100% | 10,00 | Kredit historis sudah di cap; bukan semua gate revisi PASS |
| Subtotal + saldo Owner | — | — | 96,45 + 0,15 | **96,60%** |

Koreksi defect yang sudah dikreditkan pada tahap admin tidak memperoleh kredit baru. Persentase development terpisah dari acceptance kandidat dan readiness Final Release.

## 3. Implementasi dan root cause

### Mapping produk

Root cause **direproduksi dari kode aktual**: `sanro_studio_save_product_print_zones()` menyimpan default native form ke `_sanro_studio_print_zones`; detektor legacy pada `bf8ba02` menganggap metadata nonkosong itu sebagai mapping, dan status tanpa lifecycle langsung `active`. Pada metadata hasil writer yang sama, fungsi source lama menghasilkan `mapped=true/status=active`; source koreksi menghasilkan `mapped=false/status=unmapped`. [Reproduksi](../../artifacts/studio-v1/final-blocker-fix-20261127/catalog-root-cause.json). **Metadata live #107 persis belum dibaca**, sehingga evidence ini membuktikan jalur sebab pada source; tidak mengklaim pembacaan metadata #107 yang tidak dilakukan.

- Produk baru ditandai `unmapped` melalui hook `wp_insert_post` untuk auto-draft native dan `woocommerce_new_product` untuk pembuatan/duplikasi/import WooCommerce. Update produk lama tidak mengubah membership. Produk baru tidak dapat mewarisi membership aktif dari duplikat.
- Produk tanpa lifecycle hanya menjadi legacy mapped jika **validator konfigurasi lengkap yang sama dengan editor** lulus. Default zone/category, profil kosong, atau konfigurasi parsial tidak cukup.
- Validator admin `allow_inactive=true` melewati lifecycle saja, memungkinkan deteksi legacy tanpa recursion. Validator buyer menolak status unmapped/disabled/archived/invalid dan tetap memeriksa konfigurasi penuh.
- Selector Tambah & Atur memakai detektor yang telah diperbaiki; Add tetap tindakan POST Administrator, product-bound nonce dan capability. Mapping mulai disabled; aktivasi memerlukan konfigurasi lengkap. Archive/restore tetap metadata Studio; tidak menghapus WooCommerce, desain, order atau artefak.

### Mixed checkout tanpa order baru

Transport resmi sekarang membawa `mixed-checkout-acceptance.php`, **di luar ZIP aplikasi**. Tools → **SANRO mixed acceptance** (`/wp-admin/tools.php?page=sanro-mixed-acceptance`) menyediakan form HTTPS POST dengan capability `manage_options` + `install_plugins`, nonce terikat source, marker source terpasang, exact staging identity dan flags OFF.

1. Admin meng-arm ekspektasi dua UUID/desain publik berbeda dan dua produk berbeda (satu simple, satu variable), warna/size/qty/variation/unit IDR serta total; lease 15 menit.
2. Selama transport aktif, hook `woocommerce_after_checkout_validation` **selalu menambah error untuk seluruh cart Studio pada exact staging**, termasuk guest dan kondisi belum arm/expired/identity gagal/storage gagal. Cart Woo biasa dan production tidak diubah. Error ditambahkan sebelum evidence validation/storage. Tidak menjalankan order creation, customer profile processing, payment, atau locking draft buyer.
3. Pada hook native yang sebenarnya, pengaman memakai hasil otorisasi native, lalu mengulang `sanro_studio_pricing_checkout_design()` dan `sanro_studio_prepare_size_cart_lines()`. Membandingkan UUID, product ID, public Design ID, size, qty, variation ID/attributes, warna, harga unit dan subtotal/total cart dengan server quote. Native authorization/preview/variation checks tetap menjadi sumber kebenaran. Ekspektasi ini khusus total barang tanpa shipping/tax/coupon/fee; jika total berbeda, fail closed.
4. Receipt `add_option()` bersifat insert-only per hash ekspektasi; submit ulang tetap ditahan dan tercatat `DUPLICATE_SUBMISSION_BLOCKED`. Kegagalan/ketidakcocokan tercatat `MIXED_VALIDATION_FAILED_CLOSED`. Evidence hanya whitelist identity/quote/upstream error codes, tanpa token, alamat, artwork atau URL privat.
5. Probe admin terpisah menjalankan **fungsi produksi** `sanro_studio_pricing_commit_order_drafts()` pada dua option QA acak `sswr_mixed_probe_*` dalam transaksi InnoDB. Membuktikan stale second row menolak seluruh batch, readback dua-row CAS sukses, duplicate stale write ditolak, lalu **ROLLBACK dan verifikasi fixture hilang**. Refuse MyISAM, transaksi aktif yang sudah ada, dan kegagalan rollback. Tidak memakai tabel/order/draft asli sebagai fixture.
6. **Batas klaim:** probe itu menguji SQL locking runtime pada baris QA, bukan membuat/lock order baru atau bukti concurrent production-order E2E. Offline DB double hanya menguji alur probe; live DB probe masih wajib dilakukan Work. Reuse integritas order #56 untuk binding/artefak existing. Fungsi checkout production tidak dilonggarkan.
7. Dispatcher historis `create_unpaid`, DTF/config activation dan package regeneration pada transport baru ditolak HTTP 403. Tombol lama tidak ditampilkan. Aktivasi DTF/Drive tidak diperlukan.

## 4. Commit dan evidence

Implementation/candidate source **`ec57148f1dbbd09462c92904616c5a721487fd6e`**. Commit evidence berikutnya pada branch yang sama membawa dokumen ini; gunakan HEAD PR saat handoff sebagai checkpoint terbaru, dan tetap gunakan SHA source ini untuk identitas ZIP.

Directory: **`artifacts/studio-v1/final-blocker-fix-20261127/`**.

| Artefak | Nama / SHA256 |
| --- | --- |
| ZIP aplikasi | `sanro-studio-staging-candidate.zip` · `e5cd30b1de03d7929ce653882da2b541bca0f6673a5bd14cb69cb90a37d4d3fa` |
| Manifest | `candidate-manifest.json` · `5135d37cc50026bd7fb78b7e0052c2d6cab923e301c1c7f1545820ca4720a74d` |
| Transport HTTPS terpisah | `sanro-studio-staging-release.zip` · `7b9465c922023f00a74b90345e6b15e9a1cbcdb8ee9d929e50f7d651bc17be98` |
| Binding predecessor | `transport-binding.json` · installed source `bf8ba02`, digest `ec1ea86b…40d1d` |
| Expected tree setelah instalasi | `3c73a6874497d367d167d740f3fdbd034146df583044cc653b99aaaa4aa11a06` — **expected, belum installed** |
| Checksums / hasil | `SHA256SUMS`, `package-verification.json`, `local-preflight.json`, `targeted-regression.json`, `catalog-root-cause.json` |

Builder resmi `tools/build-studio-candidate.py` menghasilkan dua ZIP dan manifest identik; `tools/build-studio-web-transport.py` menghasilkan dua transport identik, mengikat predecessor manifest/archive/digest. Preflight **PASS PLAN_ONLY**, PHP CLI lint PASS; bukan Web SAPI/runtime PASS. ZIP aplikasi: 89 source files + build marker; CRC/hash/ukuran semua member PASS. Tools, QA transport, tests dan fixtures tidak masuk ZIP aplikasi. Private-key/common-token signature scan PASS; tidak mengklaim pendeteksian semua format secret.

## 5. Pengujian dan instruksi acceptance Work

### Regresi lokal yang benar-benar dijalankan

| Tes | Hasil |
| --- | --- |
| `tests/studio-catalog-lifecycle.php` | **43 PASS** termasuk writer default native, lifecycle simple/variable, auto-draft, duplikat, legacy, capability dan nonce |
| `tests/test_sanro_editor_config.php` | **38 PASS** memakai validator asli |
| `tests/studio-order-product-mix-offline.php` | **8 mixed PASS**, dependency fixture quote **533 PASS** |
| `plugins/sanro-studio/tests/variable-product-e2e-contract.php` | **10 PASS**, parent/attributes/stock/multi-size/metadata |
| `tests/studio-mixed-acceptance-guard.php` | **29 PASS**, authorization doubles; positive/duplicate/cross-design/product/qty/variation/price/owner/expiry/nonce/capability/isolation |
| `tests/studio-mixed-lock-probe.php` | **6 PASS DB double** menjalankan fungsi CAS produksi; rollback/error/engine/transaction refusal |
| Admin permissions/order hooks; buyer render | **71 / 24 PASS** |
| Existing normal checkout boundary contract | **PASS offline** |
| Candidate security/preflight unit tests | **7 PASS** |
| PHP lint source/tests/transport, Python syntax, git diff check | **PASS** |

Tidak ada JS berubah, sehingga JS lint tidak applicable. Bukti 576 assertions sebelumnya dipakai untuk fitur yang tidak berubah; angka di atas overlap dan tidak dijumlahkan sebagai kredit progress baru. Full Audit, MariaDB startup, SSH dan tes fitur unrelated tidak dijalankan.

### A. Instalasi Work melalui HTTPS resmi

Gunakan sesi Administrator yang sudah sah; tidak perlu credential baru. Periksa fresh authenticated GET gate transport resmi terhadap exact source predecessor `bf8ba02`, digest, private roots, PHP Web SAPI, writer OFF, activation dan journal/lock. Hentikan jika ada perbedaan atau instalasi paralel. Gunakan ZIP **transport** terpisah untuk jalur resmi; jangan menginstalnya sebagai aplikasi SANRO Studio. Install kandidat hanya setelah mandatory pre-gate lulus. Jalankan GET baru mandatory post-gate dan cocokkan marker source `ec57148…`, version 1.1.0 serta **expected digest penuh** di tabel. Catat actual installed digest hanya setelah cocok; simpan rollback. Jangan gunakan aksi historis order/DTF. Transport baru tetap aktif hanya sepanjang acceptance mixed karena memblokir submit Studio.

### B. Acceptance katalog admin

1. Baca metadata nonsensitif fixture #107 melalui jalur resmi yang tersedia: status lifecycle/profil dan ada/tidaknya zone/category; jangan dump semua metadata. Snapshot status Woo dan Studio yang akan dipakai.
2. Publish kembali fixture #107 yang masih Draft (atau synthetic fixture baru yang diizinkan). Sebelum mapping, pastikan **Belum dipetakan**, tersedia pada selector Tambah & Atur, tidak tampil di katalog buyer dan editor-config buyer ditolak. Coba juga native new form untuk fixture baru; hanya menjalankan Add/Atur harus menjadi opt-in.
3. Add & Atur → disabled. Isi konfigurasi incomplete dan coba activate: harus ditolak. Lengkapi mockup/area/pricing/size, simpan/readback, activate → buyer terlihat. Disable → hilang, archive → Woo product tetap ada/published, restore → prior disabled, activate → kembali terlihat. Edit link Woo membuka original ID yang sama.
4. Ulangi alur membership pada synthetic variable fixture dengan mapping variation ID dan attributes sah. Pertahankan existing parent/variation relationships. Setelah uji, kembalikan #76 ke arsip dan #107 ke Draft sesuai keadaan awal runtime evidence; produk tetap disimpan.
5. Untuk legacy tanpa lifecycle, gunakan fixture synthetic yang memang legacy dan valid. Jika tool resmi memungkinkan snapshot/restore metadata exact, uji absence status pada fixture itu saja lalu pulihkan; jangan menghapus status produk nyata. Jika jalur itu tidak tersedia, catat legacy runtime **NOT TESTED**, bukan inferensi dari offline.

### C. Guest Buyer First dan mixed after-validation

Gunakan browser guest baru (tanpa cookie WP dan cart kosong), serta sesi Admin terpisah untuk arm/probe. Buat dua desain synthetic dengan #53 simple dan #76 variable yang diaktifkan sementara. Verifikasi dua public Design ID berbeda, metadata warna/size/qty/variation, thumbnail privat, harga server dan native Cart → Checkout. Reuse screenshot history hanya untuk bagian yang tidak berubah.

Pada Checkout, ambil **atribut** `data-design-id` dan `data-product-id` dari input `[data-sanro-checkout-token]`; **jangan membaca/menyalin value token**. Public ID/size/warna/qty berasal dari ringkasan. Variation ID dan attributes harus berasal dari mapping/server response yang sudah terotorisasi. Arm expectation lewat Tools → SANRO mixed acceptance sebagai Admin; contoh struktur berikut sengaja memiliki placeholder invalid sampai diganti dengan sesi uji baru:

```json
{
  "designs": [
    {"design_id":"UUID_VARIABLE_DARI_DATA_ATTRIBUTE","public_id":"ID-001XX","product_id":76,"type":"variable","lines":[{"size":"M","qty":1,"unit_idr":85000,"variation_id":77,"color":"#ffffff"}]},
    {"design_id":"UUID_SIMPLE_DARI_DATA_ATTRIBUTE","public_id":"ID-001YY","product_id":53,"type":"simple","lines":[{"size":"S","qty":1,"unit_idr":72000,"variation_id":0,"color":"#ffffff"}]}
  ],
  "total_idr":157000
}
```

Harga/variation contoh adalah kandidat kasus berdasarkan bukti sebelumnya, **bukan bypass quote**; ganti agar tepat sama dengan quote/mapping server saat uji. Pastikan source identity terpasang cocok dan arm menunjukkan expectation yang benar. Isi form checkout dengan data synthetic sah; tidak ada shipping/tax/coupon/fee untuk kasus ini. Submit normal Woo hanya ketika transport guard aktif. Harus berhenti dengan `sanro_staging_mixed_boundary`, Admin evidence `MIXED_AUTHORIZATION_PASS_STOPPED_BEFORE_ORDER`, periksa upstream codes dan detail quote/metadata. Retry harus tetap error dan receipt duplicate. Jika session validation menolak sebelum hook, catat blocker dan jangan klaim PASS. Jangan memanggil `create_order`, Store API submit, atau payment endpoint.

Untuk negative ownership/substitution runtime, modifikasi hanya payload atau session uji synthetic melalui tool resmi; error wajib tetap muncul dan evidence tidak boleh PASS. Offline negative checks tetap dicatat terpisah bila runtime injection tidak tersedia.

Klik **Run isolated atomic lock probe (rollback)** sebagai Admin. Capture result `ISOLATED_DATABASE_CAS_PASS` dengan `rollback_verified=true`; catat bahwa ini probe DB pada QA option rows, bukan real order lock. Jika engine/transaction/capability berbeda, NO-GO untuk gate itu; jangan mengganti izin, engine atau layanan DB. Reuse order #56 dan artefak produksi yang sudah PASS; jangan mengubahnya.

### D. Responsive staging dan cleanup

Pada sumber terpasang yang sama, verifikasi guest viewport CSS **320×700, 390×844, 430×932**. Catat `window.innerWidth`, overflow, screenshot editor/panel, Home/Cart/Checkout navigation, controls resize/rotate dan status simpan/public ID. Reuse Owner PASS Home/upload/drag/pinch/Mini Cart/Cart/Checkout sebagai history; hasil mobile source baru hanya PASS jika benar-benar dijalankan. Tidak ada redesign atau fitur tambahan.

Export sanitized evidence dan screenshot, bandingkan jumlah order sebelum/sesudah **tidak bertambah**, payment tidak terjadi dan real draft tidak locked oleh pengaman. Kosongkan guest cart, kembalikan fixture statuses; cleanup hanya synthetic data yang dibuat sendiri. Tombol **Remove acceptance evidence and arm** membersihkan option QA transport setelah export. **Deactivate transport setelah acceptance**; guard all-Studio di staging aktif selama transport aktif bahkan sesudah arm dihapus/expired. Verifikasi transport nonaktif, Studio/Woo tetap aktif, DTF/Drive/GC OFF. Update CURRENT dengan actual installed source/digest serta status gate per bukti.

## 6. Blocker tersisa

- Runtime koreksi Add & Atur pada kandidat baru (termasuk legacy absent-status bila tersedia).
- Mandatory fresh authenticated pre/post HTTPS gates serta actual installed identity kandidat baru.
- Fresh guest mixed after-validation evidence, duplicate/negative case dan live isolated DB CAS rollback probe.
- Fresh Guest Checkout review; exact staging widths 320/390/430; Owner current-device acceptance yang masih terbuka.

Pembayaran out of scope; order #56/artefak produksi dipakai ulang; OAuth/upload Drive deferred by Owner. Tidak ada blocker SSH yang perlu diulang dalam handoff ini.

## 7. GO / NO-GO

**GO — kandidat V1.1.0 siap untuk instalasi dan acceptance ChatGPT Work melalui jalur HTTPS resmi.**

**NO-GO — Final Release, merge, production dan publikasi release** sampai mandatory runtime evidence di atas selesai. Scope V1.1.0 tetap frozen; tidak ada Canvas Recolor, SDM-V1, SANRO Design Maker, executor lain, credential baru atau additional cost.
