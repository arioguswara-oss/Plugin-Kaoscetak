# SANRO Studio 0.1.1-dev

Target terbaru: **satu plugin editor SANRO Studio aktif**, dengan core berlisensi sebagai komponen internal dan kedua plugin editor lama dapat dinonaktifkan setelah QA. Pemuat internal 0.1.1-dev sudah tersedia; source core privat tidak didistribusikan di repository publik ini. KLD tidak diperlukan untuk bootstrap. WooCommerce tetap diperlukan. Lihat [roadmap dan progres](../../docs/architecture/sanro-studio-roadmap.md).

## Yang tersedia

- Bootstrap dan namespace terpisah dari KLD; aman ketika dependensi belum aktif atau versi belum dikenali.
- Pemuat core pada `plugins_loaded`: kompatibilitas dengan plugin lama yang masih aktif, atau memuat core dari paket internal yang sudah disiapkan tanpa mengaktifkan plugin lama. Enam fingerprint file inti diperiksa sebelum pemuatan.
- Penyiapan core privat dari arsip Owner dengan SHA256 tetap: validasi path/symlink/duplikat ZIP, metadata Git dikeluarkan, lisensi dipertahankan, pengulangan tanpa menimpa file yang berubah.
- Mode internal hanya memakai schema yang sudah ada. Callback lifecycle vendor dilepas agar aktivasi plugin lain tidak memicu impor SQL contoh. Tidak ada auto-install database atau migrasi desain.
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

Jalankan `PHP_BIN=/path/to/php sh tests/run-source.sh` dari root repository (PHP, Node.js dan Python 3.9+). Tes fondasi memakai harness WordPress untuk perilaku hook, hak akses, nonce, preservasi opsi dan output admin. Source privat dikecualikan dari runner source publik. Uji tambahan lokal:

```sh
python3 tools/prepare-sanro-runtime.py /private/path/lumise.zip
php tests/sanro-core-boot-contract.php
php tests/sanro-core-boot-contract.php missing_schema
SANRO_LUMISE_CONFIG_FILE=/private/path/core/includes/config.php php tests/sanro-vendor-contract.php
```

Bootstrap contract menjalankan entrypoint core asli, mendaftarkan hook, mengecek callback lifecycle dan preservasi opsi dengan fungsi WordPress sintetis. Contract tidak menjalankan WordPress `init`, database core, sesi pembeli, render kanvas atau checkout. Source vendor tidak disertakan.

Belum FINAL dan belum dipasang ke produksi. QA berikutnya memakai WordPress/WooCommerce/Flatsome dengan core internal yang disiapkan, kedua plugin editor lama tidak aktif, serta data sintetis/salinan terisolasi. Uji inisialisasi penuh, sesi, halaman editor, produk/mockup dan operasi desain. Harga/Drive belum diintegrasikan. Tampilkan screenshot backend dan buyer hasil implementasi sebelum instalasi produksi, sesuai permintaan Owner. Revert modul tidak menghapus opsi Lumise/KLD.
