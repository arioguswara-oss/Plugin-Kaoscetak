# Kaoscetak Lite Designer 0.3.2 — paket kelanjutan

Owner meminta paket rilis biasa yang meneruskan plugin terpasang, dengan
tambahan roadmap disertakan. Paket 0.4.0 OWNER-TEST sebelumnya tidak dipakai
untuk jalur ini. Pekerjaan kembali ke PR #12; tidak ada pemasangan atau merge.
Implementasi terbit: `8faecb48788dcb96dae686160acf8b54b3249519`; tree identik
dengan commit lokal teruji `2bcb75a`.

## Baseline live dan pelestarian pengaturan

WordPress Connector `plugin_list`, dibaca pada 18 November 2026, melaporkan
**Kaoscetak Lite Designer 0.3.2-rc.1 aktif** di https://kaoscetak.id. Identitas
plugin tetap `kaoscetak-lite-designer/kaoscetak-lite-designer.php` dan root ZIP
`kaoscetak-lite-designer/`. Pembaruan tidak membuat plugin kedua.

Tidak ada nilai Client ID/Secret, refresh token, folder atau konfigurasi live
yang dibaca/dicetak/disalin. Konfigurasi Drive mengikuti checkpoint Owner.
Kunci opsi lama tetap digunakan. Tidak ada migrasi, reset konfigurasi atau
penulisan opsi otomatis pada bootstrap/aktivasi/deaktivasi. Tes tambahan
`tests/test_upgrade_settings.php` memuat plugin sesungguhnya dengan fixture
konfigurasi yang sudah ada, menggagalkan setiap percobaan menulis opsi atau
melakukan jaringan, lalu memeriksa bootstrap/aktivasi/deaktivasi/reaktivasi.
**15 assertions PASS**; ini bukan pengujian OAuth/Drive live.

## Isi kelanjutan roadmap

- Undo/Redo metadata 30 langkah per sisi, grouping teks/slider/drag, hapus layer
  bisa dibatalkan. File/bitmap/token tidak ikut riwayat; ganti file/background
  preview memulai ulang riwayat agar master Drive tidak mundur.
- Snap/garis bantu dan tombol tengah di batas kategori/area aman, tanpa mengubah
  kategori, ukuran fisik atau tarif. Toggle tersedia.
- DPI efektif memakai resolusi asli dan ukuran aktual cm; legacy unknown tetap
  unknown, tidak memakai resolusi thumbnail.
- Toolbar kontekstual ramah sentuh; garis editing tidak ikut thumbnail order.
- Dukungan Media mockup per produk/warna/sisi, <=200 KB, 600×710 px dan origin
  sama. **Aset foto resmi belum tersedia**, Canvas tetap fallback.
- Proteksi sumber/Drive/cart/checkout dari PR #12 tetap disertakan. Classic
  Checkout diperlukan. Opsi tambahan untuk pelanggan terpisah, bawaan OFF;
  admin dapat memakai kontrol tersebut langsung.

## Evidence aktual

| Check | PASS | FAIL |
| --- | ---: | ---: |
| Native PHP 8.4.24 lint | 31 file | 0 |
| Pricing smoke + `tests/test_*.php` | 14 suite | 0 |
| JS/CJS syntax | 4 file | 0 |
| Actual editor Node tests dengan DOM/Canvas/Image doubles | 21 tes | 0 |
| Tes pelestarian konfigurasi upgrade | 15 assertions dalam suite ke-14 | 0 |
| Full `PHP_BIN=… sh tests/run-source.sh` | exit 0 | 0 |
| Shell syntax / diff | PASS | 0 |
| Rebuild dan hash ZIP vs source | identik, 20 file | 0 |
| PHP/JS hasil ekstraksi ZIP | PASS | 0 |

Runtime WordPress/Flatsome, rendering browser, checkout/payment/Drive dan HP
untuk versi baru ini belum diuji. Penamaan versi rilis tidak berarti gate
runtime sudah VERIFIED. Owner memilih pemeriksaan langsung setelah memasang;
staging tidak dijadikan prasyarat tambahan dalam penyerahan arsip ini.

## Artefak

- [kaoscetak-lite-designer-0.3.2.zip](../../releases/kaoscetak-lite-designer-0.3.2/kaoscetak-lite-designer-0.3.2.zip)
- [Manifest](../../releases/kaoscetak-lite-designer-0.3.2/manifest.json)
- [SHA256SUMS](../../releases/kaoscetak-lite-designer-0.3.2/SHA256SUMS)
- [Upgrade dan rollback](../../releases/kaoscetak-lite-designer-0.3.2/UPGRADE-0.3.2.md)
- [Perintah dan hasil](../../releases/kaoscetak-lite-designer-0.3.2/evidence/execution.json)
- [Baseline plugin live read-only](../../releases/kaoscetak-lite-designer-0.3.2/evidence/live-baseline.json)

ZIP 80.169 byte; SHA256
`40532af089dd743e2e0ab301f9d8b26be596f30edc60e9839d97d11807987cb6`.
Tidak ada kredensial, fixture transport, koneksi SMTP/WhatsApp baru, deployment
otomatis atau uninstall script di paket. Saat memasang, gunakan **Ganti plugin
saat ini**; pertahankan database serta konfigurasi Drive yang sudah ada.
