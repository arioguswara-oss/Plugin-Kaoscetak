# Kaoscetak Lite Designer V1 — keputusan Owner (DECIDED)

## Interaksi
- Produk WooCommerce -> tombol Custom Desain -> editor layar penuh HP -> simpan/keranjang -> checkout.
- Sisi depan dan belakang independen; masing-masing maksimum 1 gambar PNG/JPEG <=10 MB dan 1 blok teks multi-baris yang dapat mengatur font/warna/ukuran/posisi.
- Remove background bersifat opsional (klik), nonaktif bila konten gambar telah transparan menurut data alpha, bukan ekstensi.
- Preview kaos per warna dan size; 1 desain untuk banyak size dan qty dengan **ukuran cetak fisik tetap** antar-size.

## Cetak
- Kategori ukuran per sisi: A5=14,8 x 21 cm; A4=21 x 29,7 cm; A3=29,7 x 42 cm; A3+=32 x 48 cm.
- Ukuran kategori adalah **batas maksimum**, bukan ukuran wajib. Pelanggan memilih kategori, harga tidak turun otomatis bila desain diperkecil.
- Gambar dan blok teks dihitung sebagai satu kesatuan area cetak; semua harus berada dalam batas kategori dan area aman baju yang ditetapkan admin. Simpan posisi/ukuran aktual dalam cm. Acuan produksi 300 DPI.

## Harga
- Kaoscetak tidak menjual kaos polos; tampilkan harga jadi termasuk sablon 1 sisi, bergantung produk/size/kategori.
- Cetak depan saja = belakang saja untuk kategori yang sama.
- Dua sisi: paket 1 sisi **kategori lebih besar** + tambahan kategori lebih kecil; jika sama tambahkan kategori tersebut.
- Tambahan A5 Rp15.000, A4 Rp25.000, A3 Rp35.000, A3+ Rp40.000.
- Cotton Combed 24s, harga Rp ribu dengan urutan [A5,A4,A3,A3+]:
  - S 62,72,82,87; M 65,75,85,90; L 68,78,88,93; XL 72,82,92,97.
  - 2XL 77,87,97,102; 3XL 80,90,100,105; 4XL 83,93,103,108; 5XL 85,95,105,110.
- Lengan panjang +Rp10.000 menurut tabel Owner.
- Tunik Oversize lengan 3/4: M 85,95,105,110; L 90,100,110,115; XL 95,105,115,120 (Rp ribu).
- Produk lain menggunakan tabel harga terpisah yang bisa diatur admin.
- Repeat order diskon 5% per pcs termasuk satuan, dengan verifikasi riwayat pelanggan. Validasi harga dan qty di WooCommerce server-side.

## Penyimpanan dan produksi
- File sumber disimpan utuh di Google Drive privat; hosting hanya metadata, thumb dan referensi. Target preview/thumb sekitar 30-50 KB selama tetap jelas.
- User tidak perlu login untuk mencoba editor, menyimpan, berbagi, checkout. Pengelolaan desain permanen memerlukan email terverifikasi/link aman.
- Desain privat secara default. Pemilik dapat aktifkan/nonaktifkan share; penerima boleh preview, beli, edit **salinan** mandiri. Pemilik boleh menghapus dari koleksinya; order lama mengikuti kebijakan retensi yang belum diputuskan.
- Folder produksi sesuai nomor order dibuat setelah pembayaran dikonfirmasi ATAU siap produksi. Isi/referensi file asli, mockup ringan, PDF panduan produksi dengan nomor order/produk/warna/qty per size/kategori/ukuran aktual/posisi/teks/preview. PDF **bukan** file siap cetak. File cetak dibuat di komputer produksi.
- Di admin order WooCommerce tampil preview dan status/link Drive privat jika tersedia; jangan tampilkan link palsu jika belum dibuat.

## Quality gate
- ZIP v0.1.0 sebelumnya menampilkan `Kaoscetak Desain -> Edit Post` dan bukan editor yang dijanjikan. Ini insiden; **jangan ulangi**.
- `DECIDED` bukan `IMPLEMENTED` atau `VERIFIED`.
- Jangan mengklaim final sebelum alur editor, cart, price, produksi, security, responsive dan aktivasi WP/WooCommerce benar-benar teruji; Drive baru VERIFIED sesudah uji akun nyata.

## Preset harga dan pemisahan produk
- DECIDED: setelah instalasi, tabel harga resmi yang sudah diberikan Owner tersedia otomatis dan tetap dapat diedit melalui admin. Perubahan admin harus bertahan saat upgrade plugin.
- Custom kaos reguler, custom hoodie, dan custom sweatshirt adalah produk WooCommerce berbeda; masing-masing memiliki tabel harga independen. Jangan menggunakan harga reguler sebagai fallback hoodie/sweatshirt.
- Tabel yang telah disetujui: Cotton Combed 24s, lengan panjang +Rp10.000, dan Tunik Oversize 3/4.
- Harga hoodie dan sweatshirt belum disampaikan Owner. Status nominal **OPEN**, jangan membuat asumsi; blokir pemesanan custom untuk kombinasi yang belum memiliki harga sah.
- Preset dapat dipilih per produk; harga khusus produk boleh mengesampingkan preset tanpa mengubah produk lainnya.
