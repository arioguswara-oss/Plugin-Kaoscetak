# SANRO Studio · Deteksi otomatis ukuran cetak

26 November 2026 WIB. Revisi Owner pada PR #52 melanjutkan mockup standar per produk, warna dan sisi serta pemisahan produk lengan pendek dan panjang.

| Acceptance | Verifikasi |
| --- | --- |
| Editor buyer | Dropdown kategori dihapus. Ukuran fisik dan estimasi mengikuti bounding box semua objek pada setiap sisi. Resize, perpindahan dan rotasi memakai geometri yang sama saat menyimpan. |
| Penentuan kategori | Server mengukur gabungan gambar dan teks, termasuk rotasi terhadap pusat objek. Pilih tier pertama yang memuat lebar dan tinggi hasil ukur. Depan dan belakang independen. Sisi kosong bernilai null. |
| Batas dan harga | Objek yang keluar dari area produk atau desain yang tidak muat pada batas kategori maksimum ditolak. Server mengabaikan kategori dari draft lama dan menggunakan konfigurasi produk. Harga tabel, tarif sisi tambahan, ukuran, variasi dan repeat discount tetap mengikuti kebijakan SANRO. |
| Admin | Batas kategori A5/A4/A3/A3+ tersedia di **Area Cetak**. Tarif dasar per ukuran dan tarif sisi tambahan tersedia di **Harga**. Nilai awal mengikuti aturan yang sudah berlaku. |
| Order dan produksi | Kategori hasil quote masuk snapshot privat order dan manifest produksi. Ringkasan buyer hanya menampilkan informasi pesanan yang relevan, termasuk Design ID, warna dan ukuran. Pesanan historis mempertahankan kategori dan tarif yang sudah terkunci. |

Tes terarah PHP/JS mencakup ambang kategori, penolakan dimensi, akumulasi dua objek, rotasi, resize dalam editor, preview privat, tarif default dan tarif admin, metadata order, PDF serta raster GD sintetis. Smoke staging memeriksa editor dan quote dengan data sintetis. Pengujian checkout dibatasi sebelum submit. Production dan merge berada di luar scope.

## Bukti terarah

- PHP. 32 pemeriksaan bounding box, 533 pemeriksaan quote, 31 pemeriksaan adapter WooCommerce dan 8 pemeriksaan kontrak checkout campuran. Tes metadata order, variasi, replay checkout dan harga admin lulus.
- JavaScript. 372 pemeriksaan estimator dan tes render resize/rotasi, serialisasi draft tanpa kategori, serta preview privat lulus.
- Produksi sintetis. PNG GD dengan rotasi gambar/teks serta raster A3+ dan PDF Unicode lulus. Writer staging tetap OFF sesuai konfigurasi yang sudah tercatat.
- Paket ZIP dibangun dua kali dengan hasil identik dan preflight lokal PASS. Kandidat V1.1.0 `c5d1184` terpasang aktif setelah gate resmi PASS pada LiteSpeed PHP 8.3.37. Digest terpasang `6fc942196e86c298480d0ba0ab236c6e0c66dbfae5431743c657d394b6f579ec` cocok dengan isi ZIP. [Kandidat ZIP dan checksum](../../artifacts/studio-v1/automatic-print-sizing-20261126).

## Smoke staging

Pengujian menggunakan satu kaos cotton ukuran S pada produk sintetis #53.

| Perubahan desain | Dimensi hasil ukur | Estimasi |
| --- | --- | ---: |
| Teks depan awal. Belakang kosong. | 12,4 × 5,8 cm | Rp62.000 |
| Ukuran teks depan diperbesar. | 27,6 × 12,7 cm | Rp82.000 |
| Teks depan diputar 36°. | 29,8 × 26,5 cm | Rp87.000 |
| Teks belakang ditambahkan. | Belakang 5,4 × 4,5 cm | Rp102.000 |

Harga server dan subtotal keranjang sama-sama Rp102.000. Ringkasan keranjang menampilkan Design ID, warna dan ukuran. Draft dapat dibuka kembali dan preview privat depan/belakang terverifikasi. Saat desain diperbesar keluar dari area produk, estimasi ditahan. Empat batas dimensi dan tarif sisi tambahan muncul di admin. Penyimpanan perubahan admin diuji pada fixture PHP. Keranjang uji dibersihkan, transport sementara dihapus, writer dan Storage GC OFF.

**96,50% → 96,50%, delta 0,00 poin.** GO review staging. Final Release masih mengikuti acceptance perangkat sentuh dan validasi checkout campuran yang belum tuntas. Submit, order baru dan pembayaran berada di luar smoke ini.
