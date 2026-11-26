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
