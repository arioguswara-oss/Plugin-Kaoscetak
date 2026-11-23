# Pembaruan Kaoscetak Lite Designer 0.3.2

Melanjutkan **0.3.2-rc.1**, yang terbaca aktif di kaoscetak.id pada 18 November
2026 melalui daftar plugin WordPress secara read-only. Ini plugin yang sama,
dengan folder `kaoscetak-lite-designer` dan file utama
`kaoscetak-lite-designer.php`. Tidak perlu membuat plugin baru atau konfigurasi
Drive baru.

## Pengaturan tetap

Pembaruan tidak mengganti OAuth Client ID/Secret, refresh token, folder master,
folder pesanan, tabel harga, status publikasi, desain atau snapshot order.
Pengaturan tersimpan di database WordPress; tidak dibundel sebagai kredensial
di ZIP. Bootstrap/aktivasi/deaktivasi diuji dengan konfigurasi tersimpan untuk
memastikan tidak ada penulisan opsi atau panggilan jaringan. Ini bukan tes
koneksi Drive produksi dan tidak membaca nilai secret live.

## Fitur tambahan

- Undo/Redo maksimal 30 langkah metadata per sisi; teks, posisi, ukuran, kategori,
  hapus layer dan gesture. File/bitmap/token tidak digandakan. Penggantian file
  atau background preview memulai ulang riwayat sisi itu.
- Snap dan garis alignment dalam batas cetak/area aman; toggle dan tombol tengah.
- DPI efektif dari piksel asli pada ukuran cetak aktual; resolusi lama yang belum
  tercatat tidak ditebak dari thumbnail. DPI tidak mengubah harga/kategori.
- Toolbar ringkas untuk gambar atau teks terpilih. Garis bantu tidak ikut thumbnail.
- Dukungan foto Media per warna/sisi/produk, same-origin PNG/JPEG/WebP 600×710 px
  <=200 KB. Aset foto resmi belum disertakan. Tanpa foto, ilustrasi tetap dipakai.
  Template acuan L: tengah X=300, ketiak Y=155, lebar badan 294 px, panjang 438 px.

Kontrol tambahan tersedia untuk admin; pilihan pengaktifan pelanggan terpisah di
WooCommerce → Kaoscetak Designer dan bawaan OFF. Tidak otomatis mengubah pilihan
editor publik yang sudah tersimpan. Classic Checkout tetap diperlukan.

## Memasang pembaruan

1. Simpan ZIP versi yang sekarang terpasang dan pastikan backup Hostinger tersedia.
2. WordPress → Plugin → Tambah Plugin → Unggah Plugin → pilih ZIP 0.3.2 →
   **Ganti plugin saat ini**. Jangan hapus/uninstall plugin lama terlebih dahulu.
3. Pastikan versi 0.3.2 dan buka produk sebagai admin. Pengaturan Drive seharusnya
   tetap tersedia; tidak perlu menekan Hubungkan/Ulangi Google Drive hanya karena
   pembaruan. Jika ada masalah koneksi, periksa error dahulu tanpa mereset secret.
4. Periksa Undo/Redo, Snap, DPI, depan/belakang, qty dan contoh L A3+/A4 Rp118.000.
   Lanjutkan simpan/keranjang/Classic Checkout, order admin, PDF/Drive dan HP.
   Order/email/file yang dibuat di live adalah tindakan nyata milik Owner.

Paket dibuat atas permintaan Owner; source tests lulus, tetapi runtime versi
baru belum dijalankan di kaoscetak.id oleh sesi ini. Jangan menyamakan nama
versi rilis dengan klaim seluruh alur live sudah VERIFIED.

## Rollback

Nonaktifkan plugin bila perlu, lalu ganti menggunakan ZIP 0.3.2-rc.1 yang benar-benar
terpasang sebelumnya. Pertahankan database/desain/order baru dan file Drive.
Jangan hapus opsi reservasi `kld_drive_artifact_*` dan jangan menimpa database
lama di atas order baru. Tidak ada uninstall/penghapusan otomatis dalam paket ini.
