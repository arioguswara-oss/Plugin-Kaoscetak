# Acceptance SANRO Studio · Mockup standar dan produk per model lengan

**Keputusan Owner, 26 November 2026 WIB.** Ukuran kaos menentukan variasi WooCommerce, stok, alokasi jumlah, harga per ukuran dan rincian pesanan. Desain menggunakan ukuran fisik dan posisi yang sama untuk semua ukuran pada produk, warna dan sisi yang sama. Lengan pendek dan panjang adalah produk WooCommerce tersendiri. Buyer memulai satu desain per produk dan dapat memasukkan keduanya ke satu keranjang.

## Acceptance PR #52

| Skenario | Hasil yang harus terlihat di staging | Bukti |
| --- | --- | --- |
| Pratinjau standar | Editor hanya menampilkan ukuran pada jumlah pesanan. Ubah jumlah S/L, lalu ubah warna dan sisi. Mockup mengikuti warna dan sisi. Geometri desain tetap untuk setiap ukuran pada sisi tersebut. | PASS staging. Selector hilang. S/L tidak mengubah posisi dan dimensi teks. Fixture variable mempertahankan mockup saat kuantitas M/L berubah. |
| Geometri dan interaksi | Upload gambar, drag, resize, pinch-zoom, pindah sisi dan buka ulang desain. Perubahan jumlah tidak mengubah geometri dalam cm. Server tetap menegakkan batas tiap sisi dan kategori A3+/A4. | PASS di tes geometri dan rendering preview. Pinch-zoom, drag dan resize pada perangkat sentuh aktual masih terbuka. |
| Admin dan kompatibilitas | Simpan satu pasangan Media Library depan/belakang per warna. Verifikasi variation ID dan stok per ukuran dan bahwa file gambar yang tidak lolos validator ditolak. | PASS staging. Admin menyimpan fixture #76 dan membaca kembali dua pasangan gambar #74/#75 serta enam variation ID #77–82. Tes validator attachment PASS offline. |
| Produk terpisah dan harga | Pilih lengan pendek dan lengan panjang dari katalog sebagai dua produk. Produk panjang memakai tabel harga SANRO yang diisi admin. Ubah ukuran dan jumlah. Quote dan subtotal sesuai tabel produk. | PASS offline untuk harga produk lengan pendek/panjang. Quote staging PASS dengan fixture simple cotton dan variable tunik 3/4. Fixture lengan panjang khusus belum diuji live. |
| Satu keranjang dan pesanan | Tambahkan kedua desain, termasuk ukuran yang sama pada masing-masing produk. Periksa produk, Design ID, warna, ukuran, jumlah, harga satuan dan subtotal. Verifikasi total dan alokasi pesanan serta otorisasi kedua desain. | PASS staging untuk empat baris dari dua Design ID dan review checkout Rp457.000. PASS offline untuk kontrak dan penolakan token, referensi desain atau nominal yang salah. Submit, lock campuran pada database staging dan pembuatan order belum diuji. |
| Riwayat | Buka pesanan lama dengan preview per ukuran. Pastikan preview, Design ID dan harga historis tetap terbaca. Buka ulang draft lama dan simpan preview baru untuk memakai pasangan standar. | PASS, binding preview lama, replay pesanan, dan migrasi draft privat yang masih bisa diedit. |

## Konfigurasi yang terdampak

- **Produk & Mockup.** Pilih satu pasangan gambar per warna. Pada produk variable, pasangkan setiap ukuran ke variasi WooCommerce dengan warna yang sesuai. Produk tanpa pasangan per warna memakai mockup dasar sisi terkait. Konfigurasi lama dibaca tanpa migrasi massal. Pasangan **Semua ukuran** diprioritaskan. Jika tidak ada, pasangan pertama untuk warna tersebut dipakai hingga admin memilih gambar lain.
- **Harga.** Tambahkan atau petakan dua produk WooCommerce dengan nama dan mockup yang membedakan lengan pendek/panjang. Pilih **Lengan panjang** pada produk yang sesuai, lalu isi tabel khusus produk sesuai harga yang disetujui Owner. Harga jenis lengan bawaan per potong dihapus. Tunik mempertahankan profil 3/4.
- **Desain dan checkout.** Draft lengan panjang lama pada produk pendek tetap dapat dibuka. Buyer diminta membuat desain baru dari produk lengan panjang untuk memesan. Checkout memverifikasi otorisasi, alokasi, stok dan harga untuk masing-masing desain. Pesanan historis tetap membaca snapshot dan preview yang terikat. Cart studio yang digunakan untuk acceptance hanya berisi produk Studio.

Gunakan produk, mockup dan desain sintetis untuk uji staging. Rekam quote/cart dua design ID dan satu ukuran yang berulang pada kedua produk. Uji submit hanya hingga boundary staging yang sudah disetujui. Pisahkan bukti validasi checkout dari bukti pembuatan pesanan dan status pembayaran. Acceptance akhir tetap terbuka sampai bukti browser dan runtime kandidat tersedia. Google Drive live tetap ditunda sesuai keputusan Owner.

## Bukti staging

Kandidat **V1.1.0** source `094a511b7b315358dd6641bbca7ee9c4664619a9` dipasang pada staging melalui gate resmi. PHP 8.3.37 LiteSpeed, private storage, transaksi committed, rollback tersedia, digest terpasang `3658b899ec3854b1b4436ab1f275ff5fa6c7c1da519fa858874ab673adafa4fa` cocok dengan ZIP. WordPress tetap melaporkan SANRO Studio dan WooCommerce aktif setelah transport sementara dihapus. Storage GC dan writer terjaga OFF. [Paket, checksum, dan bukti runtime](../../artifacts/studio-v1/standard-mockup-20261126).

| Fixture | Rincian | Quote IDR |
| --- | --- | ---: |
| #53 · Cotton Combed 24s simple | S × 2 @ Rp62.000, L × 1 @ Rp68.000 | 192.000 |
| #76 · Tunik variable 3/4 | M × 1 @ Rp85.000, L × 2 @ Rp90.000 | 265.000 |
| Keranjang dan halaman review checkout | Dua Design ID dan empat baris, termasuk L untuk kedua produk | 457.000 |

Form checkout teramati dengan dua field otorisasi untuk dua desain dan total yang sama. Pengujian berhenti di halaman review. Keranjang kembali kosong. Produk sintetis #76 kembali diarsipkan di Studio, dengan produk dan variasi WooCommerce tetap tersedia untuk QA. Scope OAuth Google Drive tetap ditunda Owner.

## Status tujuh tahap

| Tahap | Baseline | Status revisi |
| --- | ---: | --- |
| 1. Fondasi/core | 95% | Baseline dipertahankan |
| 2. Backend produk/admin | 94% | PASS, UI pasangan mockup dan mapping produk variable |
| 3. Frontend buyer | 96% | PASS, selector dan mockup di desktop; perangkat sentuh aktual terbuka |
| 4. Harga | 96% | PASS, harga produk terpisah offline serta quote live simple/variable |
| 5. Desain dan kepemilikan | 98% | PASS, simpan live serta isolasi Design ID; riwayat preview offline |
| 6. Checkout/order/produksi | 97% | PASS, mixed cart dan review form. Submit/lock mixed live belum diuji. Bukti DTF lama tetap dirujuk. |
| 7. Paket/staging | 100% | PASS, build reproducible, instalasi dan gate. Final visual sign-off terbuka. |

Baseline resmi **96,50% → 96,50%, delta 0,00 poin**. Kandidat **GO untuk review staging**. Final Release dan rekomendasi merge **NO-GO** sampai acceptance visual perangkat sentuh dan verifikasi yang masih terbuka ditutup dalam batas mandat.
