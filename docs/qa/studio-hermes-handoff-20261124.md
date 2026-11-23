# Checkpoint hemat kuota — SANRO Studio / PR52

24 November2026 WIB. Base aplikasi terverifikasi `c04653aae4b524fe8283228abafc43b2cb1e8541`; parent commit putaran ini sama. SHA handoff final adalah HEAD PR52 yang membawa file ini; ambil dari GitHub, jangan menebak dari nama branch. Baseline resmi **89,50%**, tabel tunggal `docs/ledger/PROGRESS-BASELINE.md`. Belum90%, Full Audit tidak dimulai. Persiapan Hermes bukan izin menjalankannya pada sesi Work ini.

## Resume tanpa mengulang

1. Fetch PR52 `work/studio-mobile-checkout-integration-20261123`; cocokkan HEAD, baca AGENTS.md bila ada, pastikan clean. Gunakan worktree sendiri dari HEAD tersebut. Jangan mengerjakan #50/#51 atau force-push; jika Work belum menyerahkan ownership, jangan tulis ref PR52 yang sama. Publish hanya expected-SHA nonforce.
2. Source base #50 frozen45a7917c320403d1bc0d18caf7ee3398a3ecb416; pricing#51e9cb4679247f30223c33e51ddac1b2405dff961c sudah tercakup. Jangan cherry-pick ulang, mengulang instalasiPR50, menjalankan worker lain atau menganggap branch integration sudah merge.
3. Periksa gap wajib V1 yang belum selesai di bawah. Implementasi dahulu, tes hanya yang terpengaruh. Jangan mengulang quote690, A3+/font, cart recovery, PDF/Drive fixtures yang sudah PASS tanpa perubahan relevan. Jangan membuka Full Audit dari checkpoint ini atau menaikkan angka demi mencapai90.

## Yang sudah selesai dan bukti yang dipakai

Checkpointc04653a: Cotton/Tunic/server overrides, locked multi-size checkout/shipping/payment integrity, current preview route, admin private previews, memory/provenance/font/PDF, private Drive hierarchy/upload/retry/sealed receipts, watchdog/admin recovery, private diagnostic dan source-bound ZIP. Detail PASS sebelumnya ada pada laporan feature-completion4; tidak perlu audit ulang.

Putaran kuota ini menambahkan `tools/studio-staging-install.py`:
- default PLAN_ONLY, validasi kandidat menggunakan preflight yang sudah ada;
- WP CLI probe fixed code dengan skip plugins/themes; homeURL staging tepat, bukan production, single site dan existing Studio+Woo aktif; MU/config writer flags harusOFF, nilai selainbooleanfalse ditolak;
- approval exact action/URL/source/plugin/archive/existing-tree digest + bukti PHP-FPM verified, kemudian re-probe sebelum write;
- menolak custom plugin layout, WordPress subdirectory, symlink, target berubah, konflik lock, rollback overlap/different-filesystem/nonprivate/in-document-root;
- hanya swap existing plugin directories, tidak menjalankan plugin install/activate/seed/SQL/order operation dan tidak menulis MU flags/private directories/product metadata;
- exception pada rename atau post-install hashes mengembalikan folder lama; folder rollback ditahan di luar document root. Setiap rename atomik, keseluruhan dua-rename **bukan crash-atomic**. Jika proses/OS mati antara rename, hentikan apply berikutnya dan pulihkan existing folder dari rollback yang ditahan melalui operator/Hermes yang diotorisasi, jangan meminta Owner menjalankan terminal.

`python tests/test_studio_staging_install.py`: **9 test PASS** (filesystem/mocked WP process only). Syntax kedua file Python PASS. Tidak menjalankan WP CLI asli, menghubungi staging, apply, deploy atau mengubah order. Tidak ada pemeriksaan aplikasi lama diulang. Installer belum diuji terhadap layout/PHP-FPM Hostinger; fieldapproval bukan pengganti bukti FPM.

## Batas V1 dan pekerjaan berikutnya (maksimal5)

1. Tutup fitur repeat-order5% hanya setelah mengikat eligibility pada riwayat pelanggan yang sah sesuai keputusan bisnis; nominal DECIDED, mekanisme verifikasi belum diimplementasikan. Jangan mengarang pembulatan, pajak, fee atau kupon. Kebijakan yang belum ada tetap OPEN/fail-closed.
2. Selesaikan kebutuhan operasional core yang masih nyata, termasuk recovery installer akibat hard process interruption sebelum mengklaim crash-atomic. Native variations/Blocks/mixed-design tetap deferred kecuali ada kebutuhan katalog V1 konkret. Produk staging24 diamati simple; jangan memperluas scope.
3. Siapkan satu paket approval staging source-bound setelah source siap: exact SHA/archive digest, konfigurasi PHP-FPM/flags/private dirs dan scope pesanan sintetis. Tidak ada approval baru pada checkpoint ini. Generator/Drive tetapOFF; produksi kaoscetak.id/order pelanggan tidak tersentuh.
4. Sesudah approval Owner, operator yang diotorisasi boleh menjalankan plan/apply yang sesuai. Verifikasi `X-Sanro-Source` dan private `/readiness` dahulu, lalu reproduksi HTTP422 dan E2E editor→draft→master→preview→server quote→cart→checkout→admin. HTTP422 pada source lama bukan bukti sumber baru gagal atau berhasil; root cause belum VERIFIED. New source belum di-deploy.
5. Pada bukti implementasi90%, berhenti menambah fitur dan siapkan Full Audit di sesi berikutnya. Gateway sandbox/Drive OAuth/cetak fisik adalah bukti terpisah, belum diuji nyata. Jangan mengaktifkan writer atau membuat order tanpa scope approval.

## Reproduce kandidat dan installer, jangan dieksekusi ke hosting sekarang

Build ZIP dari clean **final HEAD**, bukan memakai kandidatc04653a untuk sourcebaru:

```bash
python tools/build-studio-candidate.py --out /tmp/studio-final-candidate
python tests/test_studio_staging_install.py
```

Installer CLI memerlukan `--zip`, `--manifest`, `--php`, `--wp`, `--wp-root`, `--document-root`, `--rollback-root`; tanpa `--apply` hanya plan. Semua path harus ditemukan operator dari konfigurasi aktual, jangan menyalin path produksi/menebak. Tidak menyediakan command hosting lengkap karena lokasi belum diverifikasi. Apply memerlukan `--apply --approval <file>`; schema exact ada di fungsi `approve()`. **Tidak membuat approval file sendiri atas nama Owner.**

Simpan/review manifest terbaru yang memuat exact finalcommit dan archiveSHA. Runtime Owner-reported terakhir e876035b650692b382e0dd293a88c99acb18cfb0 tetap source lama, tidak disamakan dengan HEAD baru.

GO: review/handoff/development yang diotorisasi. NO-GO: merge/deploy/production/live order DB/aktivasi generator-Drive/final release. Tidak ada sesi AI eksternal diinventarisasi atau dihentikan; isolasi branch bukan global worker lock.
