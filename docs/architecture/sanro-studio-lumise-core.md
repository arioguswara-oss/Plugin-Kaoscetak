# SANRO Studio — keputusan core dan repository

Tanggal: 18 November 2026 (WIB).

Status keputusan: **DECIDED oleh Owner**. Implementasi integrasi Lumise: **belum selesai / belum VERIFIED**.

## Keputusan

- Nama produk: **SANRO Studio**.
- Core editor: **Lumise**, mempertahankan alur yang memudahkan Owner dan pelanggan.
- Repository tetap `arioguswara-oss/Plugin-Kaoscetak`; tidak perlu repository baru untuk modul tambahan.
- Pengembangan memakai branch terisolasi. Folder modul baru: `plugins/sanro-studio/`, namespace `sanro_studio_`. Fondasi 0.1.0-dev sudah dibuat: bootstrap, status dependensi, pengaturan, dan profil runtime khusus pratinjau administrator. Integrasi harga/Drive belum diaktifkan.
- Core Lumise tetap merupakan dependensi di instalasi berlisensi. Source/ZIP core, aset vendor, kredensial, dan data pelanggan tidak dimasukkan ke repository publik ini. Branding SANRO Studio diterapkan melalui modul sendiri; perubahan branding vendor mengikuti hak lisensi yang tersedia.
- Mesin harga Kaoscetak dipertahankan: tabel per produk, kategori per sisi, tambahan sisi kedua, dan repeat order terverifikasi. Owner menyatakan kalkulasi saat ini sudah benar; penggantian core tidak menjadi alasan mengubah tarif atau rumusnya.
- Pilihan ini menggantikan usulan membangun ulang seluruh core editor secara independen. KLD yang sudah tersedia menjadi sumber reuse modul milik proyek, tanpa reset opsi Google Drive atau migrasi data otomatis.

## Pembagian tanggung jawab

| Bagian | Tanggung jawab |
| --- | --- |
| Lumise | Core kanvas, pengaturan objek pada mockup, pemilihan produk, dan alur editor yang familiar |
| SANRO Studio | Profil fitur ringan, penghubung harga Kaoscetak, validasi server, pemetaan desain/produk, dan integrasi produksi |
| WooCommerce | Keranjang, checkout, pembayaran, pesanan, dan otorisasi pelanggan/admin |
| Modul Drive Kaoscetak | Master privat, status penyimpanan, serta folder/referensi produksi setelah paid atau siap produksi |

Tabel ini menetapkan sasaran tanggung jawab, bukan bukti bahwa kemampuan Lumise bawaan sudah memenuhi aturan privasi, kepemilikan, atau Drive Kaoscetak.

## Titik integrasi yang telah diperiksa di source

Pemeriksaan terbatas pada source Lumise 2.1.2 dari arsip milik Owner; tidak menjalankan core atau mengubah situs live. Source vendor tidak disalin ke repository.

- `woo_connector.php`: wrapper `apply_filters()` meneruskan filter ke WordPress dengan awalan `lumise_`.
- `includes/lumise-fronted/components/price.php`: filter `add-custom-price-limuse-data` dipanggil sebelum harga dibagi dengan kuantitas dan diterapkan ke produk keranjang. Ejaan `limuse` merupakan nama hook yang ada di source. Integrasi harus membedakan total baris dan harga satuan untuk menghindari pengalian/pembagian ganda.
- KLD: `includes/admin.php` menyediakan `kld_product_prices()` dan `kld_unit_price()`. `includes/repeat.php` menyediakan `kld_repeat_unit_price()`. Reuse harus melalui adapter tervalidasi; harga dan diskon dari browser tidak dipercaya.
- Setting komponen Lumise dapat menjadi titik penyederhanaan antarmuka. Efek terhadap ukuran bundle, jumlah request, dan waktu muat perlu diukur tersendiri.

Keberadaan hook belum membuktikan kompatibilitas runtime. Mapping ukuran, kategori depan/belakang, kuantitas per size, produk WooCommerce, dan kepemilikan desain masih perlu diimplementasikan dan diuji.

## Batas fitur dan kriteria integrasi

1. Fokus pelanggan yang membawa artwork: maksimal satu PNG/JPEG dan satu blok teks per sisi, tata posisi/ukuran pada mockup, warna, size, dan qty. Template, clipart, drawing, dan fitur berat di luar kebutuhan tidak masuk profil utama.
2. Preview checkout harus tampil; harga item dan subtotal konsisten di cart, mini-cart, dan checkout. Rumus harga yang disetujui tetap dipertahankan.
3. Simpan/buka kembali desain, edit keranjang, serta repeat order mempertahankan data desain. Perubahan dari pesanan selesai membuat salinan dan tidak mengubah snapshot pesanan asli.
4. Halaman editor bersama dapat memilih produk ketika belum ada produk. Pemakaian halaman `/custom/` yang sudah aktif harus menunggu langkah integrasi yang disetujui; dokumen ini tidak mengganti halaman tersebut.
5. Master privat di Drive, thumbnail ringan di hosting, checkout menolak master yang belum valid, dan retry produksi tidak menggandakan artefak. Penyimpanan Lumise bawaan tidak dianggap otomatis memenuhi persyaratan ini.
6. Tidak mengubah atau menonaktifkan plugin produksi, setting Drive, atau data Lumise melalui perubahan dokumentasi ini. Tidak ada ZIP baru, deploy, atau merge.

## Bukti untuk perubahan ini

HEAD PR #12 diperiksa melalui GitHub: `df3174b746161551943b9df63e1f3e003aae04e6`, masih Draft dan belum merged; sesuai HEAD lokal saat keputusan ini dicatat. Pemeriksaan source terarah mengonfirmasi nama hook dan fungsi di atas.

Keputusan awal dicatat sebagai dokumentasi saja pada commit `352bbdc`. Implementasi fondasi berikutnya beserta hasil tes dicatat di [laporan fondasi](../qa/sanro-studio-foundation-20261118.md). Belum ada paket SANRO Studio FINAL atau pemasangan ke produksi.
