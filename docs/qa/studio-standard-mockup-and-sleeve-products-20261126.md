# Acceptance SANRO Studio · Mockup standar dan produk per model lengan

**Keputusan Owner, 26 November 2026 WIB.** Ukuran kaos menentukan variasi WooCommerce, stok, alokasi jumlah, harga per ukuran dan rincian pesanan. Desain menggunakan ukuran fisik dan posisi yang sama untuk semua ukuran pada produk, warna dan sisi yang sama. Lengan pendek dan panjang adalah produk WooCommerce tersendiri. Buyer memulai satu desain per produk dan dapat memasukkan keduanya ke satu keranjang.

## Acceptance PR #52

| Skenario | Hasil yang harus terlihat di staging | Bukti source/offline |
| --- | --- | --- |
| Pratinjau standar | Editor hanya menampilkan ukuran pada jumlah pesanan. Ubah jumlah S/L, lalu ubah warna dan sisi. Mockup mengikuti warna dan sisi. Geometri desain tetap untuk setiap ukuran pada sisi tersebut. | PASS, UI DOM dan mapping PHP |
| Geometri dan interaksi | Upload gambar, drag, resize, pinch-zoom, pindah sisi dan buka ulang desain. Perubahan jumlah tidak mengubah geometri dalam cm. Server tetap menegakkan batas tiap sisi dan kategori A3+/A4. | PASS, batas geometri dan rendering preview. Pinch-zoom viewport serta drag/resize pada perangkat aktual masih perlu verifikasi. |
| Admin dan kompatibilitas | Simpan satu pasangan Media Library depan/belakang per warna. Verifikasi variation ID dan stok per ukuran dan bahwa file gambar yang tidak lolos validator ditolak. | PASS, mapping varian serta validasi attachment. Penyimpanan pada halaman admin aktual perlu verifikasi. |
| Produk terpisah dan harga | Pilih lengan pendek dan lengan panjang dari katalog sebagai dua produk. Produk panjang memakai tabel harga SANRO yang diisi admin. Ubah ukuran dan jumlah. Quote dan subtotal sesuai tabel produk. | PASS, pricing PHP/JS, harga per produk, alokasi multi-size dan varian. |
| Satu keranjang dan pesanan | Tambahkan kedua desain, termasuk ukuran yang sama pada masing-masing produk. Periksa produk, Design ID, warna, ukuran, jumlah, harga satuan dan subtotal. Verifikasi total dan alokasi pesanan serta otorisasi kedua desain. | PASS, kontrak checkout offline dan penolakan token, referensi desain atau nominal yang salah. Runtime checkout campuran, commit lock pada database staging dan pembuatan order belum diuji. |
| Riwayat | Buka pesanan lama dengan preview per ukuran. Pastikan preview, Design ID dan harga historis tetap terbaca. Buka ulang draft lama dan simpan preview baru untuk memakai pasangan standar. | PASS, binding preview lama, replay pesanan, dan migrasi draft privat yang masih bisa diedit. |

## Konfigurasi yang terdampak

- **Produk & Mockup.** Pilih satu pasangan gambar per warna. Pada produk variable, pasangkan setiap ukuran ke variasi WooCommerce dengan warna yang sesuai. Produk tanpa pasangan per warna memakai mockup dasar sisi terkait. Konfigurasi lama dibaca tanpa migrasi massal. Pasangan **Semua ukuran** diprioritaskan. Jika tidak ada, pasangan pertama untuk warna tersebut dipakai hingga admin memilih gambar lain.
- **Harga.** Tambahkan atau petakan dua produk WooCommerce dengan nama dan mockup yang membedakan lengan pendek/panjang. Pilih **Lengan panjang** pada produk yang sesuai, lalu isi tabel khusus produk sesuai harga yang disetujui Owner. Harga jenis lengan bawaan per potong dihapus. Tunik mempertahankan profil 3/4.
- **Desain dan checkout.** Draft lengan panjang lama pada produk pendek tetap dapat dibuka. Buyer diminta membuat desain baru dari produk lengan panjang untuk memesan. Checkout memverifikasi otorisasi, alokasi, stok dan harga untuk masing-masing desain. Pesanan historis tetap membaca snapshot dan preview yang terikat. Cart studio yang digunakan untuk acceptance hanya berisi produk Studio.

Gunakan produk, mockup dan desain sintetis untuk uji staging. Rekam quote/cart dua design ID dan satu ukuran yang berulang pada kedua produk. Uji submit hanya hingga boundary staging yang sudah disetujui. Pisahkan bukti validasi checkout dari bukti pembuatan pesanan dan status pembayaran. Acceptance akhir tetap terbuka sampai bukti browser dan runtime kandidat tersedia. Google Drive live tetap ditunda sesuai keputusan Owner.
