# SANRO Studio 0.1.0-dev

Fondasi modul tambahan untuk **Lumise 2.1.2 + WooCommerce**, bukan pengganti biner core Lumise. Core berlisensi tetap dipasang terpisah dan tidak didistribusikan di repository ini. KLD tidak diperlukan untuk menjalankan fondasi ini.

## Yang tersedia

- Bootstrap dan namespace terpisah dari KLD; aman ketika dependensi belum aktif atau versi belum dikenali.
- Menu WooCommerce → SANRO Studio untuk administrator: status dependensi dan tautan ke halaman editor Lumise yang sudah dikonfigurasi.
- Pratinjau profil sederhana, OFF secara default dan hanya berlaku untuk administrator frontend. Filter `lumise_settings` dijalankan setelah pembacaan opsi vendor sehingga tidak ditimpa opsi tersimpan.
- Profil mempertahankan produk, unggah, teks, keranjang dan kembali sesuai komponen yang sebelumnya aktif. Template, clipart, shapes, drawing, layers, dan toolbar efek dikeluarkan dari profil pratinjau. Profil tidak menambah fitur yang sudah dinonaktifkan Owner.
- Penyimpanan opsi memakai pemeriksaan administrator, POST dan nonce. Hanya `sanro_studio_options` yang ditulis. Tidak ada activation migration, penggantian halaman editor, panggilan jaringan, atau reset setting vendor/KLD.

## Yang belum digabung

| Modul KLD | Keputusan integrasi |
| --- | --- |
| Tabel dan kalkulasi harga | Pertahankan rumus; adapter harus memetakan produk, size, qty, kategori sisi, dan total/satuan secara tervalidasi |
| Validasi area cetak dan batas objek | Gunakan aturan bisnis; penerjemahan objek/koordinat Lumise ke cm dan pemeriksaan server perlu dibuat |
| Drive dan artefak produksi | Reuse layanan setelah pemetaan master/desain; penyimpanan Lumise bawaan belum memenuhi kebijakan Drive privat |
| Design ID, verifikasi pemilik, share, salinan | Reuse aturan kepemilikan setelah pemetaan identitas Lumise/KLD; tanpa migrasi otomatis |
| Editor Canvas KLD | Tidak dimuat ke editor Lumise; dua core editor tidak digabung dalam satu halaman |

Menyembunyikan menu belum mengurangi bundle atau menegakkan maksimum satu gambar/satu teks per sisi. Belum ada adaptor harga/Drive aktif, pemangkasan bundle, ataupun bukti performa HP. Label SANRO Studio tersedia di plugin/pengaturan; branding kanvas vendor belum diubah.

## Verifikasi dan penggunaan

Jalankan `PHP_BIN=/path/to/php sh tests/run-source.sh` dari root repository. Tes fondasi memakai harness WordPress untuk perilaku hook, hak akses, nonce, preservasi opsi dan output admin. Uji source vendor tambahan dapat dijalankan dengan `SANRO_LUMISE_CONFIG_FILE=/private/path/core/includes/config.php php tests/sanro-vendor-contract.php`; source Lumise tidak disertakan.

Belum FINAL dan belum dipasang ke produksi. QA berikutnya membutuhkan WordPress, WooCommerce, Lumise 2.1.2 dan Flatsome terisolasi: aktifkan modul, buka pengaturan, nyalakan pratinjau, buka halaman editor, periksa produk/mockup, unggah/teks serta tombol simpan/keranjang, lalu matikan pratinjau dan pastikan setelan vendor tetap. Revert cukup menonaktifkan modul SANRO Studio; opsi Lumise/KLD tidak dihapus.
