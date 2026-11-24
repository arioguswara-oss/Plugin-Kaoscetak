# SANRO Studio staging update dan buyer completion — 24 November 2026

## Hasil

Owner menyetujui kandidat dan staging yang tepat. Plugin diganti melalui installer resmi dengan lock, journal durable dan rollback. Kandidat terbaru sudah terpasang; tidak ada perubahan aktivasi, feature flags, database pesanan, production atau merge.

| Pemeriksaan | Hasil |
|---|---|
| Persetujuan, source, ZIP, manifest, staging | PASS; seluruh hash cocok dengan persetujuan Owner |
| Tree awal | PASS; `8d560cdb95611da98dc4a739737dff30ab2dc9939394130c9fcd142ce8fd7bab9` |
| Runtime PHP web | PASS; PHP 8.3.37 LiteSpeed/LSAPI dari Site Health terautentikasi hari ini; PHP CLI 8.2.36 diperiksa terpisah |
| Identitas dan isolasi production | PASS; hanya `saddlebrown-peafowl-616656.hostingersite.com`, production tidak diakses |
| Private master/preview storage | PASS; di luar document root, mode 0700 |
| Journal, lock dan recovery | PASS; preflight bersih, apply terkunci, journal `committed`, lock bebas, rollback terverifikasi |
| Plugin dan menu | PASS; SANRO Studio 0.1.0-dev dan WooCommerce 11.3.0 aktif; satu top-level SANRO Studio independen, tidak ada SANRO submenu di WooCommerce |
| Asset frontend | PASS; buyer.js dan buyer.css dari HTTP cocok dengan SHA manifest |
| Fitur/writer flags | PASS; DTF, Drive, storage GC dan checkout writer OFF |

### Buyer QA sintetis

Playwright Chromium membuka editor staging pada desktop dan mobile. Draft QA owner-bound yang telah ada digunakan; tidak ada draft baru. Artwork sintetis depan/belakang, teks, ukuran M=2 dan L=1 dipakai.

- Simpan draft sintetis: PASS; perubahan tersimpan pada draft QA yang ada.
- Master front/back: PASS; upload dan private readback keduanya HTTP 200, PNG.
- Preview front/back: PASS; preview privat dan readback keduanya HTTP 200, JPEG.
- Reopen front/back: PASS; metadata geometri dan private master ada untuk kedua sisi.
- Quote server: PASS; HTTP 200, IDR 393.000, total quantity 3, sesuai M=2/L=1.
- HTTP 422: tidak muncul pada alur draft/master/preview yang berhasil; semua readback preview HTTP 200. Body respons historis tidak tersedia, jadi root cause historis tidak diklaim teridentifikasi.
- Browser: 0 console errors dan 0 failed requests pada post-install read-only verification.
- Empat screenshot buyer disimpan privat, mode 0600: [desktop front](/home/sanro/sanro-browser-qa/owner-private/buyer-desktop-front-staging-final.png), [desktop back](/home/sanro/sanro-browser-qa/owner-private/buyer-desktop-back-staging-final.png), [mobile front](/home/sanro/sanro-browser-qa/owner-private/buyer-mobile-front-staging-final.png), [mobile back](/home/sanro/sanro-browser-qa/owner-private/buyer-mobile-back-staging-final.png). Screenshot admin **NOT TESTED** karena sesi browser admin tidak tersedia; menu diverifikasi melalui WP-CLI live.
- Tidak ada cart, checkout, order, payment, atau production request. Semua POST yang bukan endpoint draft/master/preview QA ditolak oleh browser harness.

## Tujuh tahap dan progres

Angka resmi mempertahankan rubrik historis. Tidak tersedia bobot subfitur QA yang dapat dipakai untuk menaikkan persentase tanpa kalibrasi baru.

| Tahap | Sebelum | Sekarang | Delta | Bukti putaran ini |
|---|---:|---:|---:|---|
| 1. Fondasi/core | 95% | 95% | 0 | Installer apply, recovery, source marker PASS |
| 2. Produk/admin | 94% | 94% | 0 | Plugin aktif dan satu menu independen PASS; admin screenshot NOT TESTED |
| 3. Frontend buyer | 96% | 96% | 0 | Synthetic front/back desktop-mobile PASS |
| 4. Harga server/quote | 96% | 96% | 0 | Quote IDR, M/L quantity 3 PASS |
| 5. Simpan/ownership/share | 98% | 98% | 0 | Draft, private masters dan previews/readback PASS |
| 6. Checkout/produksi/Drive | 97% | 97% | 0 | Tidak dijalankan; writers tetap OFF |
| 7. QA/rilis/mobile | 40% | 40% | 0 | Buyer QA target PASS; checkout/payment dan admin screenshot tetap NOT TESTED |

Baseline resmi **90,50% → 90,50%, delta 0,00**. Buyer staging completion kini terverifikasi secara fungsional, tetapi tidak dihitung ulang tanpa rubrik kredit QA yang disetujui.

## Kandidat dan instalasi

- Source `fad47c6f508258d594f92daca79c337d6911b404`; package commit `7df16c433501d880d10af7c484d08c06cba3c0ed`.
- ZIP SHA256 `b83c1b03d18e0633d502aff09d9b1d40f01eb216a22850a7c990fc40c78a5723`.
- Manifest SHA256 `8664f584c4b746120c98ed50b337fc9616508f3b390eca3fa2a8429a370f8309`.
- Installed tree digest `60c8b0f3a272b024537e152fe46763c31b1beafcb36933fa2e57bb115c132273`; rollback tree retained with the exact pre-install digest.
- Exact installer and buyer evidence: [installation-execution.json](../../artifacts/studio-v1/fad47c6/installation-execution.json).

**GO:** candidate is installed and the requested buyer front/back, preview, quantity and server quote path passes on staging. **NO-GO:** merge, production, order/payment flows, writer activation, or new candidate installation remain outside this approval.
