# SANRO Studio — P1 katalog Administrator dan instalasi staging

> **Superseded for variable-product scope:** source `690fb0be568713328a0c6e2fd5f81b3fb457a655` now implements variation mapping through quote/cart/order contracts and is installed on staging. See [Variable product E2E report](studio-variable-product-e2e-20261125.md). That report records the remaining partial live fixture evidence and does not change this report's historical results.

25 November 2026. Repository `arioguswara-oss/Plugin-Kaoscetak`, PR #52, branch `work/studio-mobile-checkout-integration-20261123`. Input checkpoint `23964c41fc56f5ee54557125fb14e3c4ecfb6fd3`.

## 1. Status dan root cause

Root cause yang tercatat di [review katalog Owner](studio-product-catalog-acceptance-20261125.md) benar: SANRO Studio sebelumnya hanya mengedit metadata produk WooCommerce terbit, tanpa lifecycle katalog Studio. Jalur buyer langsung menggunakan validasi konfigurasi; tidak ada Add/Map, status aktif independen, archive, atau restore.

Implementasi lifecycle produk simple selesai dan terpasang aktif di staging. Add/Map, konfigurasi, enable/disable, archive/restore, filter, pencarian, readiness, buyer visibility, serta pelestarian metadata dan order lulus acceptance fokus. Review historis menambahkan guard agar produk nonaktif/arsip tidak dapat membuat desain baru dari aksi fork koleksi. **Pemetaan produk variable/variasi WooCommerce belum didukung** oleh kontrak server quote/cart simple-product yang sudah ada; jenis itu ditolak sebelum add atau save. Karena instruksi saat ini meminta pemetaan variasi, bagian itu tetap **OPEN** dan rilis lengkap belum GO.

## 2. Perubahan kode

- Metadata `_sanro_studio_catalog_status` menyimpan `active`, `disabled`, atau `archived` pada produk WooCommerce yang dipetakan. Status lama yang belum memiliki metadata tetap dianggap aktif untuk kompatibilitas.
- Produk yang baru ditambahkan dari pilihan WooCommerce yang sudah ada memulai sebagai nonaktif; tidak ada produk WooCommerce duplikat yang dibuat.
- Produk hanya dapat diaktifkan jika konfigurasi Studio valid. Nonaktif/arsip membuat editor-config buyer menolak produk; arsip dapat dipulihkan ke status sebelumnya. Operasi lifecycle tidak menghapus/trash produk, variasi, konfigurasi, desain atau order WooCommerce.
- Setiap POST lifecycle memakai capability, nonce terikat ke ID, validasi ID/status produk, pemetaan ganda ditolak, status archive/restore idempotent. Lifecycle ditulis dengan metadata terpisah dan tidak mengubah status publikasi WooCommerce.
- Koleksi desain lama dapat divalidasi/dibaca saat status nonaktif agar arsip tidak menghapus akses snapshot pemilik. Fork koleksi tetap membutuhkan gate buyer aktif sehingga tidak membuat desain baru dari produk arsip.
- Variasi ditolak jelas sebelum add/config save karena integrasi quote/cart dan line authorization saat ini hanya mendukung produk simple. Tidak ada harga atau variation ID yang diterima dari klien.

## 3. Targeted acceptance dan keamanan

Fixture sintetis staging #53 diuji. Produk simple tetap `publish`; jumlah produk published tetap 2. Katalog melalui helper lifecycle ber-nonce: Add (status nonaktif), Activate, Disable, Activate, Archive, Restore. Request HTTPS editor-config menghasilkan 404 saat nonaktif/arsip dan 200 saat aktif/dipulihkan. Metadata katalog fixture dibersihkan setelah test sehingga kondisi awal kembali sama.

Halaman admin dirender pada runtime WordPress melalui WP-CLI sebagai Administrator; assertions menemukan Add/Map, pencarian, empat filter, fixture #53 dan nonce per produk. Handler POST konfigurasi native diuji dengan capability dan nonce Administrator: front-zone X berubah 31.1→31.2, dibaca kembali lewat HTTPS, lalu dipulihkan ke 31.1. Area depan/belakang, kategori A5/A4/A3/A3+, profil/harga dan delapan ukuran tetap valid. UI tidak diambil melalui browser karena konektor browser tidak tersedia; ini bukan Owner visual acceptance.

Read-only quote smoke sintetis A4/M menghasilkan Rp75.000 untuk satu unit; tidak ada cart, checkout, order atau pembayaran. Order #56 tetap pending dengan tiga item Studio dan referensi desain yang sama. Hash metadata konfigurasi produk #53, status/type dan jumlah produk sebelum/sesudah identik. Rincian tersanitasi: [catalog-acceptance.json](../../artifacts/studio-v1/admin-catalog-p1-20261125/catalog-acceptance.json).

Targeted checks: PHP lint file PHP yang berubah PASS; JS syntax PASS; katalog 14 assertions; editor-config 31; buyer render 20; admin menu/permissions/order hooks 71; product pricing 7; buyer gate 3; share clone 11; share/owner security 21; installer transaction/recovery 6 skenario; candidate-security suite 6. Kandidat ZIP dan manifest dari dua build identik; ZIP inventory 88 entri dan checksum tiap member PASS. Runner `native-feature-gap-flow.php` dicoba tetapi tidak dapat berjalan karena PHP CLI lokal tidak memiliki GD (`imagecreatetruecolor`); browser automation juga tidak tersedia. Full Audit dan tes checkout/order/DTF/Drive tidak diulang.

## 4. Build dan source-bound transport

Source aplikasi dan transport builder `3a3a3ea6606608143658a55e998f2bbbdb9be8ee`. Kandidat 1.1.0 87 file, ZIP SHA256 `5a0b02537e8e50a93d975312457140c7b3d6a129a134ad3003cd0a93ff52397d`, manifest SHA256 `ef4926df914907f7b7d1b5e42c49f2038dc0d54e52c4c0236bde187473903e4e`, plugin digest `6b943e4e73eeb8a9b3b7e9c24df4e8c500c1d9fbfc57847e48f46675755e025b`.

Sebelum transport build final, `sswr_tree()` pada instalasi staging aktual menghasilkan `e8754f97f921308822d8f2e56b7af785391d76c80ee89c0c7e1141f88a97feca`. Builder hanya menerima manifest/archive P1 V1.1.0 predecessor yang terpin exact (source `7c2d67f`), memverifikasi archive, seluruh file, marker dan kecocokan digest aktual. Transport sumber spesifik SHA256 `62be3d4ee53392c329eb8985772825346bae2e6cb00550f247b37fbdfe53a5ef`. Tidak ada expected digest yang diganti tanpa validasi.

## 5. Mandatory gate dan hasil instalasi

Transport resmi mengembalikan pre-install HTTPS gate **PASS** pada LiteSpeed Web SAPI PHP 8.3.37: staging target dan digest cocok, transaksi/rollback sebelumnya tervalidasi, private roots PASS, SANRO Studio/WooCommerce aktif, seluruh writer/checkout/DTF/Drive/GC flags OFF. Instalasi resmi tercatat committed; aktivasi, flags dan order tidak berubah. Post-install HTTPS gate PASS. Tree digest terpasang `c6da001ee3b8cd22c684c44615a17aaeea19c43a386577df38778ba748fcea79` cocok dengan ZIP + marker kandidat; SANRO Studio aktif versi 1.1.0. Buyer/admin CSS/JS HTTP 200 dan SHA cocok manifest; buyer CSS diverifikasi melalui URL cache-busted yang dipakai enqueue runtime.

Utility transport dikembalikan ke v0.3.3 dan inactive; temporary application password dicabut; file transport sementara pada server dihapus. Evidence gate dan instalasi: [runtime-evidence.json](../../artifacts/studio-v1/admin-catalog-p1-20261125/runtime-evidence.json).

## 6. Ledger resmi

Baseline resmi **96,50% →96,50%, delta 0,00**. Skor lama tidak dikalibrasi ulang dan tidak ada kredit QA/infrastruktur. Tahap Admin/Katalog kini PASS untuk lifecycle produk simple; tahap tetap **PARTIAL/OPEN** sampai kebijakan/implementasi variasi dipenuhi. Tahap visual Owner terpisah dan tetap pending. Bukti build/install tidak menaikkan baseline. [Ledger tujuh tahap](../ledger/PROGRESS-BASELINE.md).

## 7. Batas perubahan dan pekerjaan tersisa

Hanya staging; tidak menyentuh production, merge, payment gateway, Google Drive OAuth, checkout, order, DTF atau Full Audit. Product #53 dikembalikan ke metadata/config awal; order #56 tidak berubah. Owner visual/browser acceptance masih terpisah.

Untuk menutup seluruh permintaan sesuai task ini, implementasikan pemetaan variable product/variation menyeluruh pada konfigurasi, quote server, validasi cart, replay, dan order line, dengan regression khusus. Candidate saat ini secara aman menolak tipe tersebut dan **tidak mengklaim variasi sudah dikelola**.
