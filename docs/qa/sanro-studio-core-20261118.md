# SANRO Studio 0.1.1-dev — core internal

18 November 2026. Tahap aktif **2/8**, estimasi penyelesaian tahap **40%**, total berbobot **20%**. GO untuk pengembangan lanjutan; NO-GO untuk mengganti editor produksi atau menyebut runtime penuh VERIFIED.

## Perubahan yang dijalankan

- Pemuat core dari direktori internal tetap `plugins/sanro-studio/runtime/editor/`, pada `plugins_loaded` priority 20. Tidak menerima lokasi source dari request atau option.
- Jika plugin editor lama masih dimuat, tidak memuat core kedua. Jika plugin lama tidak dimuat, source core internal dapat dimuat tanpa KLD. WooCommerce tetap diperlukan.
- Memeriksa enam fingerprint file inti dan kelengkapan source sebelum include; versi tak dikenali, simbol bentrok, paket hilang/berubah dan schema belum tersedia dilaporkan tanpa memaksakan bootstrap.
- Memeriksa keberadaan schema core lama dengan satu read; tidak mengimpor sample SQL atau memigrasikan desain. Source vendor memiliki callback `activation_redirect` yang dapat mengimpor data contoh; callback lifecycle vendor dilepas pada mode internal.
- Tool Python menyiapkan arsip privat Owner ke runtime lokal: SHA256 arsip tetap, validasi path ZIP, duplikasi, symlink dan ukuran, lisensi dipertahankan, metadata Git dibuang. Pengulangan memeriksa hasil tanpa overwrite; file tambahan/perubahan ditolak dan dipertahankan.
- Paket privat lokal berisi 386 file, 18.870.893 byte; 174 entry metadata Git dikeluarkan. Ini pengurangan metadata pengembangan, bukan bukti peningkatan kecepatan editor.
- Versi modul `0.1.1-dev`; source KLD, tarif, OAuth, Drive dan situs produksi tidak diubah. Runtime privat diabaikan Git dan tidak didistribusikan dalam repository atau ZIP publik.

## Perintah dan hasil

```sh
PHP_BIN=/workspace/scratch/72df3ab2da51/php-runtime/bin/php sh tests/run-source.sh
python3 tools/prepare-sanro-runtime.py ../lumise-source-review/lumise.zip
/workspace/scratch/72df3ab2da51/php-runtime/bin/php tests/sanro-core-boot-contract.php
/workspace/scratch/72df3ab2da51/php-runtime/bin/php tests/sanro-core-boot-contract.php missing_schema
SANRO_LUMISE_CONFIG_FILE=/workspace/scratch/72df3ab2da51/kaoscetak/plugins/sanro-studio/runtime/editor/core/includes/config.php /workspace/scratch/72df3ab2da51/php-runtime/bin/php tests/sanro-vendor-contract.php
find plugins/sanro-studio/runtime/editor -name '*.php' -type f -print0 | xargs -0 -n1 /workspace/scratch/72df3ab2da51/php-runtime/bin/php -l
git diff --check
```

| Pemeriksaan | PASS | FAIL tersisa |
| --- | ---: | ---: |
| PHP lint source publik | 40 file | 0 |
| PHP lint core privat yang disiapkan | 109 file | 0 |
| Suite regresi PHP, termasuk pricing-smoke | 16 suite | 0 |
| Sintaks JS/CJS proyek | 4 file | 0 |
| Tes Node editor KLD | 21 tes | 0 |
| Tes penyiapan paket Python | 10 tes | 0 |
| Assertion integritas/runtime compatibility baru | 23 | 0 |
| Assertion bootstrap entrypoint core asli | 9 | 0 |
| Assertion penolakan ketika schema belum tersedia | 4 | 0 |
| Assertion filter konfigurasi vendor asli | 66 | 0 |

23 assertion runtime termasuk dalam 16 suite PHP; 57 assertion fondasi sebelumnya tetap PASS. Angka assertion tidak ditambahkan ke hitungan suite/tes secara ganda.

Source core: arsip privat Owner 2.1.2, SHA256 `3f7cb449653d0a58a15f4b77a69f29a669853d13e3b9153e0db415dc8007643f`. Penyiapan pertama dan pengulangan berhasil; source vendor tidak diedit.

## Batas bukti dan sisa

Bootstrap contract benar-benar menjalankan entrypoint dan constructor core dari source asli, dengan fungsi WordPress serta kelas WooCommerce sintetis. Kedua plugin editor lama tidak dimuat. Pengujian memeriksa core path internal, pendaftaran hook `init`, pelepasan lifecycle, bootstrap berulang, satu read schema dan preservasi semua option fixture. Tidak menjalankan WordPress `init`, koneksi database nyata, session pembeli, kanvas, cart atau checkout. Karena itu kedua plugin lama **belum boleh dianggap aman dinonaktifkan pada produksi**.

Sisa tahap 2: core internal pada WordPress/WooCommerce/Flatsome penuh, session dan editor dapat dibuka/dioperasikan, serta paket instalasi tervalidasi. Modul harga, Drive, kepemilikan dan tampilan konsep masih merupakan tahap integrasi berikutnya. Hak distribusi/branding paket vendor tidak disimpulkan dari keberhasilan bootstrap.

Sesuai permintaan Owner, tampilkan screenshot backend dan buyer hasil implementasi sebelum instalasi. Dua gambar konsep yang disetujui menjadi acuan tampilan, bukan bukti runtime.

Bukti: [source-tests.txt](evidence/sanro-studio-core-20261118/source-tests.txt), [private-vendor-lint.txt](evidence/sanro-studio-core-20261118/private-vendor-lint.txt), [private-contracts.json](evidence/sanro-studio-core-20261118/private-contracts.json). [Roadmap 8 tahap](../architecture/sanro-studio-roadmap.md).
