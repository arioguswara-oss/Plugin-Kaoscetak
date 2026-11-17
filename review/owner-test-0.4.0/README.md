# Kaoscetak Lite Designer 0.4.0-owner-test.1

Kandidat uji Owner, 18 November 2026. Rio memilih uji langsung di kaoscetak.id.
Paket ini belum berstatus FINAL atau runtime VERIFIED. Tidak ada pemasangan
otomatis, perubahan harga, reset konfigurasi, atau penonaktifan Lumise.

## Yang ditambahkan

- Undo/Redo tata letak, teks, ukuran dan kategori: maksimal 30 langkah per sisi.
  Depan dan belakang punya riwayat terpisah. Mengetik/slider dikelompokkan agar
  tidak menghasilkan satu langkah untuk setiap huruf/piksel. Ctrl/Cmd+Z dan
  Ctrl+Y/Cmd+Shift+Z berlaku di editor; textarea mempertahankan undo bawaan.
- Snap ke tepi/tengah area cetak dan objek lain, dengan garis bantu sementara.
  Bisa dinonaktifkan. Tombol Tengah horizontal/vertikal tersedia saat memilih
  gambar atau teks. Kategori dan harga tidak berubah saat posisi disesuaikan.
- Indikator DPI efektif dari piksel file asli dibagi ukuran cetak aktual.
  >=300 baik; 150–299 cukup; <150 rendah. Ini informasi, tidak memblokir pembelian.
  File lama tanpa catatan resolusi asli menampilkan status belum diketahui,
  bukan memakai ukuran thumbnail. Detail dan kualitas cetak tetap diperiksa operator.
- Toolbar kontekstual untuk objek yang dipilih: tengah horizontal/vertikal dan
  hapus; penghapusan layer bisa di-Undo selama file asli belum diganti.
- Dukungan foto mockup per produk, warna dan sisi dari Media WordPress.
  Aset foto resmi belum disertakan. Tanpa foto yang cocok, tampil ilustrasi Canvas.

Riwayat hanya metadata; tidak menyimpan salinan bitmap atau token upload. Mengganti
file atau menjalankan hapus background memulai ulang riwayat sisi itu agar Undo
tidak mengembalikan master Drive yang sudah diganti. Hapus background tetap
berlaku pada preview dan catatan produksi; file asli tetap utuh.

## Cara uji langsung sebagai Owner

1. Simpan ZIP plugin yang sekarang terpasang dan pastikan backup Hostinger dapat
   diakses. Jangan memakai ZIP v0.1.0 sebagai rollback.
2. Login admin kaoscetak.id. Buka Plugin → Tambah Plugin → Unggah Plugin,
   pilih ZIP ini dan gunakan penggantian plugin `kaoscetak-lite-designer`.
   Pastikan versi menjadi **0.4.0-owner-test.1**. Jangan ubah Lumise/Flatsome.
3. Buka satu halaman produk custom yang sudah dikonfigurasi dan tekan Custom
   Desain. Admin mendapat kontrol tambahan langsung. Uji teks, gambar, Undo/Redo,
   pindah depan/belakang, Snap on/off, posisi tengah, dan angka DPI saat resize.
4. Pastikan contoh L A3+/A4 tetap Rp118.000. Periksa per-size qty, thumbnail,
   Design ID dan harga di Classic Cart/Checkout. Pengiriman order/email/file Drive
   pada situs ini nyata: gunakan data uji milik Owner dan metode pembayaran yang
   memang hendak diuji. Jangan menganggap tes source sebagai bukti transaksi live.
5. Di WooCommerce → Kaoscetak Designer, pilihan **Aktifkan Undo/Redo, Snap,
   indikator DPI dan toolbar baru untuk pelanggan** bawaan OFF. Aktifkan setelah
   hasil uji admin sesuai. Ini terpisah dari pilihan editor publik yang sudah ada.
6. Uji HP Android serta preview admin order, PDF dan retry Drive. Catat hasil
   aktual sebelum menyatakan fitur VERIFIED. Tidak perlu mengulang setup koneksi
   SMTP/WhatsApp/OAuth yang sudah bekerja.

Classic Checkout tetap diperlukan. Dukungan penuh WooCommerce Blocks belum ada.

## Foto produk opsional

Di Edit Produk → Kaoscetak Designer → **Foto mockup produk per warna**, pilih
foto depan/belakang melalui Media. Gunakan foto produk/warna yang benar dan
hak pakainya dimiliki Kaoscetak. Format PNG/JPEG/WebP, 600 × 710 px, maksimal
200 KB, disajikan dari origin situs yang sama. SVG, ukuran lain, file terlalu
besar dan URL CDN/origin lain tidak digunakan.

Kalibrasi template acuan L: tengah badan X=300, ketiak Y=155, lebar badan
294 px dan panjang badan 438 px. Letakkan foto agar sesuai acuan ini sebelum
diunggah. Foto mengikuti simulasi ukuran badan per produk; bukan alat ukur
produksi. Mug memakai preview bidang terbentang tersendiri. Tidak ada foto
buatan yang diklaim sebagai foto kain resmi.

## Rollback

Jika ada masalah, matikan pilihan tambahan untuk pelanggan. Bila perlu,
nonaktifkan plugin ini lalu ganti dengan ZIP versi sebelumnya yang benar-benar
terpasang sebelum uji. Simpan metadata desain, snapshot order dan master Drive.
Jangan menghapus opsi reservasi `kld_drive_artifact_*`, jangan menghapus master,
dan jangan menimpa database lama di atas order baru. Tidak ada proses uninstall
atau penghapusan data otomatis dalam kandidat ini.
