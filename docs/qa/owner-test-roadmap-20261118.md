# Roadmap tambahan — paket uji Owner 0.4.0-owner-test.1

**Keputusan Owner, 18 November 2026:** gabungkan roadmap tambahan dan uji
langsung di kaoscetak.id; Owner tidak ingin melakukan staging. Keputusan ini
menggantikan penundaan P1 sampai setelah rilis inti untuk pekerjaan ini.
Kandidat disiapkan untuk uji Owner; tidak ada pemasangan live, merge atau deploy
oleh sesi ini. Paket belum FINAL atau runtime VERIFIED.

Source berasal dari PR #12 HEAD `2a60284d4989680c5c839af967debf97390139cf`.
Branch terpisah: `feature/kld-owner-test-roadmap-20261118`.
Implementasi terbit: `ba57511e5d7b8e53032edc67abee3d6685c47544`.
Tes berjalan pada commit lokal `a70b566`; tree keduanya identik:
`fddc6beff7174ad75a94092b11af9c29f28241d1`. Commit evidence berikutnya hanya
menambahkan dokumen/paket yang sesuai dengan source ini.

## Pemetaan roadmap

| Tambahan | Implementasi | Bukti saat ini |
| --- | --- | --- |
| Undo/Redo | Riwayat metadata 30 langkah per sisi; pengelompokan typing/slider/drag; reset setelah penggantian master atau background preview | Tes handler editor, isolasi depan/belakang, bounded history dan pemisahan upload PASS |
| Auto Snap / alignment | Tengah/tepi kategori, titik objek lain, batas aman badan pakaian; toggle on/off dan tombol tengah | Regresi batas geometri, gesture/cancel, toggle dan dimensi/harga tetap PASS |
| Indikator DPI | Piksel file asli ÷ dimensi aktual cm; metadata header server disimpan dan dikembalikan hanya lewat load terautentikasi | Regresi resolusi asli vs thumbnail, resize dan legacy unknown PASS |
| Toolbar kontekstual | Kontrol hanya saat gambar/teks dipilih; tengah horizontal/vertikal dan hapus; touch targets 44 px | Handler seleksi, alignment, hapus/Undo dan pengecualian garis bantu dari thumbnail PASS |
| Mockup foto realistis | Pemilih Media per produk/warna/sisi, template terkalibrasi 600×710, <=200 KB, MIME/origin tervalidasi, cache dua foto | Normalizer PHP dan loader Canvas dengan doubles PASS; **aset foto resmi belum tersedia**, ilustrasi tetap fallback |

Riwayat menyimpan posisi, ukuran, kategori dan teks. Tidak ada bitmap, file,
token, sesi upload atau kemampuan Drive dalam riwayat. Undo tidak mencoba
memulihkan master yang telah diganti; riwayat dimulai lagi saat file diganti
atau preview background diubah. Hapus gambar/teks dapat dibatalkan selama
resource gambar sisi itu masih sama. Pilihan Tidak Cetak menyimpan tata letak
lokal agar dapat dikembalikan, tetapi tidak mengirim layer nonaktif untuk dicetak.

Tambahan diaktifkan untuk admin tanpa mengubah opsi situs. Opsi publik
`kld_ux_enabled` bawaan OFF; Owner dapat mengaktifkannya lewat pengaturan setelah
uji admin. Tidak ada reset harga, perubahan mode pembayaran, SMTP, OAuth, tema,
Lumise, retensi atau penghapusan data. Master tetap PNG/JPEG privat di Drive.

## Tes yang benar-benar dijalankan

Native PHP 8.4.24 CLI, Node v24.22.1; tidak bergantung GitHub Actions.

| Perintah/check | PASS | FAIL |
| --- | ---: | ---: |
| `php -l` semua PHP source/test | 30 file | 0 |
| Pricing smoke + seluruh `tests/test_*.php` | 13 suite | 0 |
| `node --check` seluruh JS/CJS | 4 file | 0 |
| `node --test tests/test_editor_unit.cjs` | 21 tes | 0 |
| `PHP_BIN=… sh tests/run-source.sh` | exit 0 | 0 |
| Shell syntax + `git diff --check` | PASS | 0 |
| Rebuild ZIP dua kali | SHA256 identik | 0 |
| Isi ZIP vs SHA256 setiap file source | 20 file identik | 0 |
| PHP/JS dari ZIP yang diekstrak | 12 lint PHP + sintaks editor PASS | 0 |

Harga source tetap diuji 780 assertions, tarif UI 456 assertions, Drive 72
assertions. Suite fork bertambah menjadi 19 assertions, preset/mockup menjadi
50 total assertions. Kegagalan pertama pada fixture baru adalah stub
`sanitize_text_field` yang belum disediakan; stub test diperbaiki dan seluruh
suite dijalankan ulang hingga PASS. Tidak ada kegagalan tersisa pada runner ini.

Node memakai actual editor closure dengan DOM/Canvas/Image doubles. Ini menguji
handler dan state, tetapi **bukan bukti rendering browser atau WooCommerce live**.
Browser lokal sebelumnya gagal launch karena socket restriction; tidak mengulang
metode launch yang sama atau mengklaim browser PASS. WordPress/Flatsome dan HP
nyata untuk versi ini belum dijalankan. Owner akan mengisi bukti dari uji live.

## Ukuran dan batas performa

| Aset | Sebelum | Sesudah | Gzip sebelum → sesudah |
| --- | ---: | ---: | ---: |
| editor.js | 41.083 byte | 50.991 byte | 13.875 → 17.045 byte |
| editor.css | 6.673 byte | 7.655 byte | 2.075 → 2.283 byte |

Tidak ada dependency frontend baru. Foto opsional hanya dimuat setelah editor
dibuka, dua referensi foto pada cache per warna aktif; tiap foto maksimum 200 KB.
Angka gzip adalah pengukuran file lokal, bukan metrik RAM/LCP/performa HP.

## Paket dan langkah Owner

- [ZIP OWNER-TEST](../../review/owner-test-0.4.0/kaoscetak-lite-designer-0.4.0-owner-test.1.zip)
- [Manifest](../../review/owner-test-0.4.0/manifest.json)
- [SHA256SUMS](../../review/owner-test-0.4.0/SHA256SUMS)
- [Perintah dan hasil](../../review/owner-test-0.4.0/evidence/execution.json)
- [Runner output](../../review/owner-test-0.4.0/evidence/run-source.txt)
- [Panduan uji langsung dan rollback](../../review/owner-test-0.4.0/README.md)

ZIP: 80.731 byte, 20 entries, root `kaoscetak-lite-designer/`.
SHA256: `ab70701d17308777e34075806671dbe02ade37cfc108a840db0ff8840924606a`.
Perbaikan anti-duplikasi Drive dari PR #12 ikut di dalam paket.

**GO untuk menyerahkan paket uji Owner.** Belum FINAL. Buka produk sebagai admin,
uji empat tambahan terlebih dahulu lalu alur simpan/keranjang/Classic Checkout,
order admin/Drive/PDF, share/verifikasi dan HP. Pertahankan ZIP sebelumnya dan
backup Hostinger untuk rollback; jangan menimpa database lama di atas order baru.
Foto realistis baru dapat dinyatakan terintegrasi setelah aset resmi yang cocok
dipilih dan tampilan aktualnya diperiksa. Tidak ada foto resmi yang dikarang.
