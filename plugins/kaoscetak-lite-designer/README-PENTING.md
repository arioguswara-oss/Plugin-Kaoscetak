# TAMBAHAN OWNER TEST — VERSI 0.3.0 (MUG 11 OZ)

## Mug Putih 11 oz
- Buka **Produk → Tambah Baru** atau edit produk WooCommerce yang sudah ada.
- Gunakan **Simple Product**, bukan Variable Product. Produk tidak dibuat otomatis oleh plugin.
- Isi nama **Custom Mug Putih 11 oz** dan harga reguler WooCommerce **Rp25.000** agar WooCommerce mengenali produk sebagai purchasable. Harga custom tetap dihitung ulang **di server** dengan preset plugin.
- Di kotak **Kaoscetak Designer — Harga Per Produk**, pilih **Custom Mug Putih 11 oz (harga satuan)**; centang **Aktifkan Custom Desain untuk produk ini**; klik Publish/Update.
- Saat admin login, buka halaman produk dan klik **Custom Desain Mug**.
- Editor akan menunjukkan **warna Putih saja**, **satu ukuran mug (11 oz)**, **satu kuantitas pcs**, dan **bidang cetak tetap 18 cm (lebar) × 8,5 cm (tinggi)**. Tidak ada size S–5XL, depan-belakang, atau kategori A5–A3+.
- Harga standar Rp25.000/pcs **sudah termasuk mug dan cetak**; ubah harga bawaan di **WooCommerce → Kaoscetak Designer → Harga bawaan mug**. Untuk override khusus satu produk, buka kembali halaman Edit produk setelah memilih preset dan isi **Harga khusus mug produk ini**.
- Pratinjau mug adalah **ilustrasi vektor dengan layout area cetak terbentang**, bukan foto mockup 3D yang presisi. Jangan gunakan hasil screenshot untuk menentukan potongan keliling fisik produksi.
- Saat Google Drive belum terhubung, admin tetap bisa mencoba preview, tetapi tidak bisa checkout dengan file master yang belum valid. Jangan aktifkan mode publik sebelum pengujian end-to-end.

## Upgrade dari 0.2.0
1. Cadangkan file plugin, database WordPress, dan simpan salinan ZIP versi 0.2.0 sebelum mengganti plugin.
2. Unggah ZIP v0.3.0 melalui **Plugins → Add New → Upload Plugin**, lalu pilih penggantian plugin bila WordPress meminta konfirmasi.
3. Pastikan Lumise tetap aktif, mode publik Kaoscetak tetap OFF, dan editor produk kaos lama masih bisa dibuka.
4. Uji produk mug sebagai admin, satu per satu: harga, preview, gambar, teks, gerak posisi, jumlah, dan panduan order.
5. Jika ada kendala, nonaktifkan Lite Designer yang baru lalu **pulihkan ZIP v0.2.0** dan periksa harga/preset. Jangan menghapus data order produksi.

## Batas pengujian
Tes PHP/Chromium lokal telah dilakukan. **Belum diuji pada instalasi WordPress + WooCommerce + Flatsome + Google Drive asli.** Status paket adalah READY FOR OWNER TEST, **bukan VERIFIED / FINAL**. Jangan menjalankan checkout publik sebelum tes Owner berhasil.

---

# Kaoscetak Lite Designer 0.2.0 — PAKET PENGUJIAN OWNER

**STATUS: READY FOR OWNER TEST (BUKAN FINAL / BUKAN VERIFIED DI WORDPRESS ASLI).**

Plugin mengimplementasikan editor visual frontend WooCommerce, pengaturan harga per produk, penyimpanan desain via Google Drive, privasi/share guest, diskon repeat 5%, dan panduan produksi PDF. Pengujian logika, sintaks, PDF serta browser Chromium lokal sudah dilakukan. **WordPress/WooCommerce/Flatsome dan Google Drive akun kaoscetak.id belum dapat diuji nyata di lingkungan pembuat paket.** Rio melakukan pengujian langsung. Jangan aktifkan mode publik sebelum seluruh skenario lulus.

## Instalasi dan rollback

1. **Backup dulu** website, database WooCommerce, serta konfigurasi plugin sebelum mencoba. Jangan menghapus atau menonaktifkan Lumise.
2. Instal ZIP melalui WordPress → Plugins → Add New → Upload Plugin. Bila versi 0.1.0 sudah ada, gunakan fitur replace existing plugin dari WordPress untuk folder yang sama, bukan menghapus konten desain yang sudah ada. Catat versi sebelumnya agar bisa di-rollback.
3. Aktifkan plugin; pastikan WooCommerce aktif. Menu konfigurasi: **WooCommerce → Kaoscetak Designer**.
4. Buka **produk WooCommerce uji**, pilih preset harga sesuai jenis produk dan centang **Aktifkan Custom Desain**. Harga reguler 24s, lengan panjang, dan tunik sudah terisi otomatis sesuai tabel Owner. Hoodie dan sweatshirt **belum memiliki tarif resmi**, sehingga tetap kosong sampai disetujui Owner; admin bisa mengisinya.
5. Untuk tunik, hoodie, sweatshirt: isikan **lebar dan panjang fisik pakaian dalam cm** per size, karena ukuran kaos reguler tidak dipakai sebagai fallback. Atur margin cetak jika diperlukan.
6. Saat **login admin**, buka halaman produk uji, klik **Custom Desain Kaos**. Editor visual dapat dibuka tanpa Google Drive; simpan/checkout dinonaktifkan dalam mode preview.
7. Isi Google OAuth Client ID/Secret, aktifkan Google Drive API dan Authorized Redirect URI sesuai yang ditampilkan; hubungkan lalu klik **Uji koneksi**.
8. Uji upload gambar PNG/JPEG asli, depan-belakang, teks, ukuran cetak, warna, size, harga, simpan desain, email verifikasi, Bagikan, Nonaktifkan Bagikan, Edit Salinan, add-cart dan checkout **dengan order uji**, sebelum mencentang publik.
9. Uji order simulasi berbayar/siap produksi: di detail order WooCommerce harus tampil preview dan status folder Drive privat/PDF panduan. Produksi DTF tetap dikerjakan operator lokal.
10. Jika gagal, **nonaktifkan plugin** dan pulihkan ZIP/plugin sebelumnya beserta backup bila diperlukan. Jangan hapus data order dan folder Drive secara manual tanpa memeriksa referensi file.

## Uji penerimaan yang harus dilakukan Owner

- Editor FULLSCREEN, bukan menu WordPress "Edit Post"; tombol muncul dari halaman produk.
- Satu gambar PNG/JPEG maksimal 10 MB dan satu blok teks per sisi; cek geser, pembesaran/pengurangan proporsional, penggeseran area cetak; ukuran dalam cm.
- Kaos reguler size **L A3 depan + A5 belakang = Rp103.000** dan kombinasi sisi terbalik harus sama.
- Satu sisi A5 size S reguler = **Rp62.000**, dan lengan panjang size S A5 = **Rp72.000**.
- Satu desain beberapa size/qty. Item keranjang tersimpan per size, tidak ada qty negatif/desimal atau harga yang salah.
- Harga tiap produk terpisah; hoodie/sweatshirt yang belum diberi harga tidak dapat checkout.
- Upload master langsung ke Drive harus valid; **uji kemungkinan hambatan browser CORS/OAuth**, khususnya HP Android. Server WordPress tidak menerima gambar produksi ukuran penuh.
- Email verifikasi benar-benar terkirim; desain privat bawaan; Bagikan opt-in dan revoke; pembeli dari link hanya mengedit **salinan**. Salinan tidak mengubah desain asli.
- Repeat order 5% per pcs termasuk satuan, hanya setelah verifikasi email dan sebelumnya ada order yang sudah dibayar. Uji email yang salah dan perubahan email checkout.
- Order PDF menunjukkan jenis produk, warna, jumlah per size, kategori, posisi dan ukuran aktual cm, catatan background, mockup depan/belakang. File itu **panduan**, bukan file resolusi tinggi siap cetak.
- Folder pesanan dibuat setelah pembayaran dikonfirmasi ATAU penetapan siap produksi; retry tidak boleh membuat folder duplikat.
- Periksa WordPress HPOS, Flatsome, payment gateway, responsive Android RAM 4 GB, dan performa browser pada ukuran gambar mendekati 10 MB.

## Batasan yang belum VERIFIED

- WordPress, WooCommerce, Flatsome dan database situs asli belum dapat dipakai untuk uji integrasi pembuat paket.
- Google Drive OAuth, resumable upload dari browser (termasuk CORS), izin privat dan error retry belum diuji memakai akun nyata.
- Hasil tes desktop Chromium tidak sama dengan uji Android sebenarnya; target 4 GB RAM adalah **target uji**, bukan minimum sistem.
- Pengaturan pakaian di luar kaos 24s memerlukan ukuran fisik per size yang benar; preview siluet hanyalah simulasi produksi.
- Draft yang dibuang hanya menghapus metadata; master Drive tidak dihapus otomatis untuk mencegah kehilangan arsip order/salinan. Kebijakan retensi/GC aman masih OPEN.
- Tidak ada klaim bahwa plugin sudah siap menerima pelanggan publik sampai Owner meluluskan pengujian end-to-end.

## Aturan tetap

- Desain maksimal A5 14,8×21, A4 21×29,7, A3 29,7×42, A3+ 32×48 cm per sisi. Kategori adalah batas maksimum, pilihan pelanggan tidak otomatis berubah.
- Harga sudah termasuk kaos dan sablon satu sisi. Dua sisi = harga sisi terbesar + biaya tambahan sisi lebih kecil (sama kategori = tambahan kategori itu).
- Master produksi PNG/JPEG di Drive privat; metadata dan thumbnail kecil di WordPress, rendering cetak DTF di komputer produksi.
- Jangan pernah membagikan token OAuth, token edit pemilik, atau link Drive privat kepada pelanggan.


## Warna produk (mulai 0.3.1)

Di **Products → Edit Product → Kaoscetak Designer → Warna yang tersedia untuk produk ini**, centang hanya warna yang sedang ready. Klik **Update**. Warna tidak dicentang tidak tampil pada editor pelanggan dan ditolak saat tambah ke keranjang maupun checkout meski sudah berada di keranjang sejak sebelum dinonaktifkan. Status warna berlaku **per produk**. Katalog warna ditanam ringan sebagai teks dan kode warna; kode HEX hanya estimasi tampilan digital, **bukan** jaminan kesesuaian kain Knitto atau ketersediaan stok bahan. Produk mug 11 oz tetap Putih. Warna lama yang pernah diisi dengan tanda pemisah `|` dipertahankan. Warna yang sudah dicatat pada order selesai tidak dihapus. Warna mockup belum foto realistis; mockup vektor masih digunakan sampai aset foto disetujui dan integrasi editor selesai.