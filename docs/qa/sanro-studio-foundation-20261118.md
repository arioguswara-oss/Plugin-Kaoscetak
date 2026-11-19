# SANRO Studio — fondasi 18 November 2026

Status: **IMPLEMENTED**, source/contract checks **PASS**. Editor WordPress/WooCommerce/Flatsome penuh dan performa mobile **belum VERIFIED**. Tidak ada deploy, merge, penghapusan branding vendor, atau ZIP FINAL.

## Hasil

Fondasi plugin terpisah tersedia di `plugins/sanro-studio/`, versi `0.1.0-dev`. Core Lumise 2.1.2 tetap merupakan dependensi berlisensi di luar repo. Modul KLD belum diperlukan untuk bootstrap fondasi; kode KLD, tabel harga, OAuth, dan Drive tidak diubah. Nama tampilan plugin/pengaturan buatan proyek adalah SANRO Studio. Nama teknis hook/dependensi serta atribusi source tetap dipertahankan; mengganti nama tampilan tidak mengubah lisensi core. Hak penggantian branding core Classic yang dipasang belum ditentukan dari lisensi pembelian, sehingga tidak diterapkan.

Filter `lumise_settings` menerapkan profil runtime sederhana setelah opsi vendor dibaca. Profil default OFF, hanya dapat diaktifkan administrator melalui POST/nonce, dan hanya berlaku di frontend administrator pada versi yang dikenali. Halaman admin vendor, pelanggan, dependensi hilang, dan versi lain tetap menerima setting semula. Tidak ada aset frontend baru, tetapi ini bukan bukti bundle vendor menjadi lebih kecil.

## Perintah yang dijalankan

```sh
PHP_BIN=/workspace/scratch/72df3ab2da51/php-runtime/bin/php sh tests/run-source.sh
SANRO_LUMISE_CONFIG_FILE=/workspace/scratch/72df3ab2da51/lumise-source-review/selected-source/core/includes/config.php /workspace/scratch/72df3ab2da51/php-runtime/bin/php tests/sanro-vendor-contract.php
git diff --check
```

| Pemeriksaan | PASS | FAIL tersisa |
| --- | ---: | ---: |
| PHP lint | 37 file | 0 |
| Suite regresi PHP termasuk pricing-smoke | 15 suite | 0 |
| Pemeriksaan sintaks JS/CJS | 4 file | 0 |
| Tes Node editor KLD | 21 tes | 0 |
| Assertion fondasi baru, termasuk 4 skenario dependensi/admin vendor | 57 | 0 |
| Assertion konfigurasi vendor asli | 66 | 0 |

57 assertion fondasi merupakan bagian dari 15 suite PHP; bukan suite tambahan. Pengujian vendor menjalankan kelas konfigurasi asli melalui reflection dan jembatan filter WordPress sintetis, tanpa constructor/database. Ini tidak menjalankan editor penuh, session, checkout, atau database vendor. Source vendor tidak didistribusikan di repo.

Tes vendor pertama gagal karena konstanta bootstrap `LUMISE_CORE_PATH` belum disediakan oleh harness. Harness diperbaiki dengan konstanta path/DS dan dependency privat `tmpl.php`; pengujian ulang PASS. Tidak ada kegagalan source produk yang tersisa dari pengujian ini.

Source arsip Owner: Lumise 2.1.2, SHA256 `3f7cb449653d0a58a15f4b77a69f29a669853d13e3b9153e0db415dc8007643f`. Tidak ada pemeriksaan ulang SMTP, WhatsApp, OAuth atau perubahan situs live.

Bukti: [source-tests.txt](evidence/sanro-studio-foundation-20261118/source-tests.txt), [vendor-contract.txt](evidence/sanro-studio-foundation-20261118/vendor-contract.txt).

## Batas dan langkah integrasi

- DEVELOPMENT: fondasi tersedia; adapter harga, master Drive privat, area cetak/objek, pemetaan identitas dan kepemilikan belum digabung. Tabel [kandidat reuse KLD](../../plugins/sanro-studio/README.md) membatasi apa yang dapat diintegrasikan berikutnya.
- TEST: QA instalasi dan interaksi core editor lengkap pada WordPress/WooCommerce/Lumise/Flatsome belum dijalankan. Hasil kontrak source tidak menggantikan QA ini.
- RELEASE: NO-GO untuk rilis FINAL/aktivasi pelanggan SANRO Studio. Profil admin ini tidak membuktikan persyaratan checkout publik telah dipenuhi.
- OWNER: tidak diperlukan keputusan repository baru; tidak ada permintaan deployment dalam perubahan fondasi ini.

Langkah berikutnya: uji fondasi pada lingkungan WordPress terisolasi, kemudian hubungkan adapter harga dengan pemetaan produk/size/qty/kategori tervalidasi; verifikasi harga baris dan satuan tanpa mengubah rumus yang sudah disetujui. Integrasi Drive dan kepemilikan menyusul sebelum checkout publik SANRO Studio dibuka.
