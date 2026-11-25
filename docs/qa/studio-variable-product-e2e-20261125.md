# SANRO Studio — dukungan produk variable end-to-end

25 November 2026. Repository `arioguswara-oss/Plugin-Kaoscetak`, PR #52, sumber `690fb0be568713328a0c6e2fd5f81b3fb457a655`. Melanjutkan katalog Administrator yang sebelumnya sudah terpasang di staging.

## 1. Status dan cakupan

Pemetaan variasi WooCommerce kini diimplementasikan dari pengaturan produk Administrator sampai server quote, candidate cart line, validasi replay/cart, dan metadata order line. Produk simple tetap didukung. Kandidat V1.1.0 sudah terpasang aktif di staging dengan mandatory gate PASS. Targeted contract dan regresi PASS. Live test variable product melalui buyer endpoint belum dapat dituntaskan karena mockup attachment sintetis tidak lolos status attachment yang diwajibkan oleh validator publik. Fixture disposable dibersihkan seluruhnya. Dengan demikian blocker implementasi P1 ditutup pada source dan kontrak terarah, sementara bukti live variable buyer/cart/order tetap PARTIAL; final visual acceptance tetap milik Owner.

## 2. Perubahan implementasi

- UI WooCommerce Administrator menerima produk simple maupun variable. Pemetaan memakai variation ID dan atribut warna/ukuran WooCommerce; pilihan yang tidak tersedia ditandai tidak tersedia. Kombinasi warna × ukuran harus lengkap sebelum produk variable dapat digunakan.
- Editor config menyajikan mapping warna, ukuran, mockup dan atribut variasi yang sudah divalidasi terhadap parent. Buyer menonaktifkan ukuran yang tidak tersedia untuk warna yang dipilih.
- Quote dihitung dari profil harga SANRO pada parent. Server menurunkan variation ID dan atributnya dari mapping tersimpan; harga maupun ID dari client tidak dipercaya.
- Candidate cart, validasi cart/replay, line WooCommerce dan order item membawa variation ID/atribut yang diotorisasi serta warna. Validasi memeriksa parent, atribut, status, purchasability dan stok. Order item menyimpan metadata variasi/warna. Repeat discount, Design ID, kebijakan harga dan metadata produksi existing dipertahankan.
- Produk simple, lifecycle katalog, status buyer, keamanan capability/nonce dan operasi order existing tidak diubah. Tidak ada penghapusan permanen produk WooCommerce sebagai aksi katalog Studio.

## 3. Targeted acceptance

Lulus: 10 pemeriksaan kontrak variable sintetis mencakup mapping parent/variasi, opsi stok habis, quote multi-size, ID variasi pada candidate cart line, penolakan parent/atribut yang salah, serta binding dan penolakan order line. `cart-adapter-smoke.php` lulus 74 pemeriksaan; `cart-contract.php` lulus; regresi katalog lifecycle simple lulus 15 assertion; quote pricing lulus 690 assertion. PHP lint untuk file mapping/quote/cart/checkout dan Node syntax untuk buyer serta admin mapping lulus; `git diff --check` lulus.

Fixture staging tersembunyi #68 dengan variasi #69–72 dibuat melalui WooCommerce API. Reader mengonfirmasi parent variable, atribut, status, dan purchasability. Acceptance buyer berhenti karena satu-satunya attachment staging yang tersedia tidak lolos `sanro_studio_valid_mockup_attachment()` (status/visibility attachment), sehingga editor config gagal-aman. Tidak ada metadata produk #53 atau #24 yang diubah. Fixture #68–72 dan attachment sintetis #73 kemudian dihapus; daftar produk/attachment kembali ke kondisi sebelum uji. Tidak ada cart, checkout, order, payment atau perubahan gateway. Detail lintas hasil ada di [runtime evidence](../../artifacts/studio-v1/variable-product-e2e-20261125/runtime-evidence.json).

## 4. Paket kandidat

Source `690fb0be568713328a0c6e2fd5f81b3fb457a655`; builder transport source `8a535d1035cc34a3df78183fbe5e6f08579b4d84`. Kandidat berisi 87 file. Candidate ZIP SHA256 `a8503ff5520164ea4d617b484031ca8e778cc1e8a42b6e6d9d1d879b8bda82c0`; manifest SHA256 `63b0eb377810fa68b120ba151ffd997efe356717e0bae50a0da231374db2fa48`; plugin digest di runtime marker `0ee7d5f177f495dd072454500d8f55eefd30cdb4890f610be9d26a8eb556e046`. Source-bound transport SHA256 `1c52686965fdaec46cf405dd3ec15b1881313188200b55f22760c67018f33ee8`; binding SHA256 `7cccd98e67e841b75fc55983892eb15df3830e57fbd241dfb5f21478f2f57de5`. Binding mem-pin predecessor manifest/archive serta digest tree staging yang diverifikasi; tidak mengganti expected digest tanpa validasi.

## 5. Instalasi dan runtime staging

SSH alias `kaoscetak-staging`. Digest tree sebelum instal `c6da001ee3b8cd22c684c44615a17aaeea19c43a386577df38778ba748fcea79`. Mandatory HTTPS Web SAPI pre-install gate PASS (HTTP 200), source/ZIP/manifest cocok, private roots PASS, WooCommerce aktif, PHP 8.3.37 LiteSpeed dan seluruh writer flags OFF. Installer resmi mengembalikan `committed`, rollback retained, tanpa perubahan flags, activation atau order writes.

Post-install gate PASS (HTTPS HTTP 200). Studio aktif versi 1.1.0, sumber `690fb0be568713328a0c6e2fd5f81b3fb457a655`; installed tree digest `6240c2816723cec83803416484fb2149611b61a96c96db109e2914b45a8d96f7`. Runtime readiness dari HTTPS menunjukkan PHP/LiteSpeed, WooCommerce, GD/Freetype dan private roots PASS; semua writer flags OFF. Transport asli v0.3.3 dipulihkan dalam keadaan inactive dan temporary application password dicabut.

## 6. Ledger resmi

Baseline development **96,50% → 96,50%, delta 0,00**; tidak mengubah rubrik atau memberi kredit kedua berdasarkan jumlah tes. Tahap 1 fondasi/core PASS retained; tahap 2 admin/katalog **PASS untuk simple dan pemetaan variable pada kode; live buyer fixture PARTIAL**; tahap 3 buyer selection/config **kontrak PASS, live variable endpoint PARTIAL**; tahap 4 harga/quote server **PASS pada kontrak multi-size, harga parent SANRO**; tahap 5 ownership/design **evidence existing retained**; tahap 6 cart/replay/order line **targeted contract PASS, tanpa order live**; tahap 7 build/install/runtime gate **PASS**, visual/browser acceptance **pending Owner**. Full Audit tidak diulang. [Ledger baseline](../ledger/PROGRESS-BASELINE.md).

## 7. Batas dan tindak lanjut

Hanya staging; production, merge, payment gateway, checkout/order live, DTF, Google Drive OAuth dan TokenEconomizer tidak disentuh. Google Drive OAuth tetap **DEFERRED BY OWNER**. Tidak ada data WooCommerce/order/desain existing yang berubah. Pekerjaan teknis tersisa untuk bukti acceptance adalah menyiapkan mockup attachment sintetis yang memenuhi validator status inherit di staging, lalu menjalankan pembacaan editor-config buyer; cart/order tetap diuji melalui isolated synthetic contract agar tidak membuat order nyata. Owner/ChatGPT Work menjalankan visual acceptance secara independen setelah pembaruan ini.
