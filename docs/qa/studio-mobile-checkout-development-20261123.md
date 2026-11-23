# SANRO Studio — development integration 23 November 2026

## Scope dan checkpoint

Owner mengizinkan maksimal lima pekerjaan: readback/integrasi pricing, uji Cloud Browser staging, mockup/usability mobile, desain→cart→checkout development, dan lint/smoke/self-fix/Draft PR/ledger.

- Branch terisolasi: work/studio-mobile-checkout-integration-20261123.
- Base source #50: 45a7917c320403d1bc0d18caf7ee3398a3ecb416.
- Pricing #51 readback: e9cb4679247f30223c33e51ddac1b2405dff961c.
- Owner melaporkan staging terpasang SHA e876035b650692b382e0dd293a88c99acb18cfb0. Browser/WordPress inventory memverifikasi perilaku dan plugin, bukan seluruh git SHA runtime.
- Tidak mengubah branch #50/#51; tidak merge, reinstall, deploy, mengubah production kaoscetak.id atau mengaktifkan DTF/Drive. Tidak memulai Hermes/Codex worker.

## 1. Pricing terbaru: dipakai tanpa duplikasi

Diff pricing source #50/#51 menunjukkan kalkulator dan adapter Woo terbaru sudah tersedia di base #50. quote.php sudah memanggil sanro_studio_product_quote dan mengubah schema ke items/total_quantity/total_price; resolver session/cart/order ada di pricing-integration.php. Tidak melakukan cherry-pick duplikat. Scope tetap produk simple + Classic Checkout, belum variable product/Blocks atau tarif produk OPEN.

Perbaikan baru pada batas order: native Woo dapat memberikan kuantitas integral sebagai string/float; sekarang memakai normalizer adapter sebelum membandingkan alokasi quote. Integer 2, string '2', float 2.0 dan '2.0' mempertahankan metadata harga server; pecahan, qty berbeda/invalid tetap ditolak sebelum item metadata berubah. Tidak menyimpan owner token/path/master pada order.

## 2. Hasil Cloud Browser pada staging terpasang

URL yang benar-benar dibuka:
https://saddlebrown-peafowl-616656.hostingersite.com/custom-2/?sanro_studio_editor=1&product_id=24

| Langkah | Hasil yang diamati |
|---|---|
| HTTPS katalog dan editor | PASS; halaman custom-2 menampilkan produk dan kontrol editor |
| Draft teks | PASS; simpan lalu owner-bound readback berhasil |
| Harga server | PASS; M×2 Rp90.000 + L×1 Rp93.000, kategori A3+, total Rp273.000 untuk 3 pcs |
| Input gambar | PASS; file sintetis PNG RGBA 256×256 tanpa data customer |
| Upload master privat | PASS; UI menyatakan master dan posisi front terverifikasi |
| Reopen gambar/draft | PASS; UI menyatakan draft berhasil dibuka kembali |
| Harga sesudah reopen master | PASS; total server tetap Rp273.000 |
| Preview | FAIL; UI menyatakan 'Verifikasi preview front gagal (422).' |
| Tambah ke cart | BLOCKED; handler berhenti pada preview readback yang sama sebelum berpindah ke cart |
| Checkout/order/payment | NOT TESTED; tidak membuat order atau pembayaran |
| UI development baru | NOT VERIFIED dalam browser; URL file:// preview lokal ditolak kebijakan Cloud Browser. Tidak mencoba workaround/route lain untuk melewati penolakan tersebut. |

Nominal Rp237.000 yang diberikan Owner adalah evidence sebelumnya dengan pilihan lain; tidak diklaim sebagai hasil sesi browser ini. Tidak menyimpulkan situs down/bot-block dari 422. Tidak menulis token, ID draft privat, file ID atau payload pribadi ke repository.

WordPress connector read-only mengonfirmasi SANRO Studio aktif, SANRO Studio Staging QA inactive dan WooCommerce 11.3.0 aktif. Tidak mengubah setting/plugin. Generator DTF/Drive OFF mengikuti batas Owner; tidak mengeksekusi atau mengubah writer tersebut.

## 3. Mockup dan usability mobile — source IMPLEMENTED

Fallback sekarang memiliki badan, lengan, dan kerah kaos. Kerah depan/belakang berbeda, warna via CSS custom property, dan dekorasi fallback disembunyikan ketika mockup foto merchant tersedia. Bounding box print-plane dan posisi safe-area cm tidak diubah/di-clip oleh bentuk kaos. Desktop preview sticky; mobile memiliki navigasi sticky Lihat kaos/Atur desain, font form 16px dan target aksi utama 48px. Tidak mengklaim foto realistis baru.

Node VM menjalankan source buyer.js sebenarnya untuk startup, switch sisi, mockup merchant vs fallback, warna dan ketetapan config area cetak. PHP renderer menghasilkan template actual; browser visual development tetap NOT VERIFIED tanpa deployment.

## 4. Preview dan checkout — source IMPLEMENTED

- Tambah GET /drafts/{id}/preview-read/{side}; header owner dan nonce tetap dibutuhkan. Endpoint memilih hanya preview_refs server yang cocok preview_design_hash revisi draft sekarang, lalu memakai pembaca storage lama yang memeriksa directory, MIME, ukuran dan SHA. Filename/path dari client tidak menjadi authority. Expired/foreign owner/stale/corrupt ditolak. Legacy read URL dipertahankan.
- Buyer memverifikasi file_id + byte base64 + MIME hasil readback pendek sebelum melanjutkan. Ini perbaikan development untuk blocker URL preview yang diamati, bukan klaim penyebab pasti 422 atau hasil staging sesudah fix.
- Sisi kosong tetap menghasilkan preview garment privat. Ia tidak menambah objek desain, kategori cetak, atau biaya sisi kedua. Ini menutup ketidakcocokan frontend yang melewati sisi kosong dengan checkout yang membutuhkan preview front/back.
- Pesan harga server menyesuaikan ketersediaan tombol cart, tidak lagi selalu mengklaim checkout belum tersedia.
- Tidak melemahkan CAS, revisi, signature, session authorization atau rate limit. Fitur tetap memakai gate default-OFF dari base.

## 5. Validasi yang benar-benar dijalankan

Lingkungan lokal: Node v24.22.3, PHP 8.4.24 CLI. PHP runtime lokal digunakan melalui wrapper yang tersedia; tidak menyalin kredensial VPS atau melakukan SSH.

| Pemeriksaan | Hasil |
|---|---|
| PHP lint 3 source berubah dan 2 tes PHP baru | PASS |
| buyer.js syntax dan startup source VM | PASS; 10 startup/side/mockup/color/area assertions |
| preview-current-route.php | PASS; 13 ownership/revision/reference/cache/legacy-route assertions |
| pricing-order-quantity.php | PASS; 12 native quantity boundary assertions |
| Pricing quote | PASS; 690 assertions |
| Pricing Woo adapter | PASS; 31 assertions; test mengulang quote prerequisite, bukan 690 tambahan unik |
| Session resolver | PASS; 8 assertions |
| Unsaved order-item binding | PASS; 27 assertions |
| Batch draft lock | PASS; 32 assertions |
| Draft REST | PASS; 37 assertions |
| Revision/master binding | PASS; 18 assertions |
| Master short read route | PASS; 20 assertions |
| Image/blank-side preview helper | PASS; 7 assertions |
| Per-side print-zone geometry | PASS; 18 assertions |
| Delegated cart click | PASS; 8 assertions |
| Node estimate/cart URL smoke | PASS; 3 cases |
| PHP buyer render | PASS; 14 assertions |
| Default-OFF restore/checkout/preview guards | PASS |
| Diff whitespace | PASS |
| CSS parser lint | NOT TESTED; postcss/tinycss2 tidak tersedia. Tidak memasang package atau mengklaim lint CSS PASS. |
| WP/Woo/browser pada source baru, GD/Drive, payment | NOT TESTED |

Bukan full audit atau GitHub Actions PASS. Tidak mengulang seluruh 184 suite historis. Hasil staging lama dan hasil source baru dipisahkan.

## Progres dan rilis

Baseline sementara Owner **72,10%** dipertahankan. Kredit source baru: frontend +0,30, preview/ownership +0,10, checkout compatibility +0,40 poin berbobot = **+0,80**, sehingga laporan sementara **72,90%**. Persen tahap adalah estimasi scope implementasi, bukan rasio assertions. Penjelasan rincian baseline +0,15 yang belum teralokasi ada di ledger; tidak mengarang alokasi tahap lama.

DECIDED: batas staging, bobot dan gate. IMPLEMENTED: source UI, endpoint preview dan perbaikan one-side/quantity. VERIFIED: tes bounded offline serta perilaku staging yang tercatat PASS. Preview/cart staging masih BLOCKED, fix source baru belum runtime VERIFIED.

GO review Draft PR dan source development. NO-GO merge/deploy/production, mengklaim checkout end-to-end PASS atau print-ready, dan aktivasi DTF/Drive. Langkah berikutnya adalah review delta dan, hanya setelah persetujuan deployment staging, memverifikasi preview pendek→cart→checkout pada source baru tanpa order/payment di luar izin.
