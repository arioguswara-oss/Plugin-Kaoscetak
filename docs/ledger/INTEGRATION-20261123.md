# Jalur integrasi tunggal — koordinasi PR #48–#50

Tanggal 23 November 2026 WIB. Menggantikan urutan usulan awal pada versi terdahulu dokumen ini. Tabel #14–#47 di bawah dipertahankan sebagai snapshot historis. Tidak ada retarget, perubahan kode, merge, deploy, penutupan PR atau penghapusan branch.

Baseline resmi **71,95%**, bobot dan kontribusi hanya di [PROGRESS-BASELINE.md](PROGRESS-BASELINE.md). Delta putaran ini 0,00 poin.

## Snapshot branch dan PR

| PR/komponen | Head branch | SHA diperiksa | Base aktual | Keadaan |
|---|---|---|---|---|
| #47 bootstrap (dependency) | feature/studio-bootstrap-admin | 71955dd3d4df9669b1e667e928a64508a2d579b6 | integration/sanro-studio | Dependency bootstrap belum terintegrasi pada target |
| #48 CI/QA | work/codex-studio-ci-20261123 | e8404d61d5161e4c5d6cfe5a04b9470f8e7145fe | feature/studio-bootstrap-admin | OPEN/Draft; 5 file tests/docs |
| #49 koordinator | docs/coordinator-ledger-20261123 | 77011df8fe14c9fe25b96bddffaa63e218374d83 (sebelum pembaruan ini) | integration/sanro-studio | OPEN/Draft; dokumentasi saja |
| #50 restore/core | work/hermes-studio-restore-20261123 | 7d097fe0cd1c06a0f24a1f575063149ca677c49d | feature/studio-bootstrap-admin | OPEN/Draft; 206 file |
| Pricing profile | work/claude-sanro-studio-product-pricing-profile-20261123 | 7a732797ebe43960f3f98e8d666cc792d21454f9 | Turunan #47 (bukan PR pricing terpisah pada daftar yang dibaca) | Owner usulan: Codex Cloud; nama branch lama dipertahankan |
| Integration target | integration/sanro-studio | 0d981b634888a5389bc394b93a13a4177090cc76 | main parent 0760f565e8b1ef068c706748022d2f8b5539837a | Belum memuat plugin Studio |

Snapshot GitHub metadata, daftar changed files, komentar #48–#50 (kosong), remote branch refs, diff dan blob Git diperiksa. Label mergeable=true untuk masing-masing PR terhadap base aktual TIDAK membuktikan gabungan feature branches bebas konflik.

## Ownership dan irisan scope

| Pasangan/path | Bukti source | Keputusan integrasi |
|---|---|---|
| #48 / #50: tests/README.md | Keduanya berubah dari base #47; #48 menambah panduan offline runner, #50 menambah panduan fixture RC lama | Codex Cloud pemilik final panduan CI. Pertahankan konteks fixture yang masih valid secara terpisah; jangan overwrite seluruh README. Irisan file bukan bukti konflik tekstual. |
| Pricing / #50: includes/product-pricing.php | Blob sama dd073c09c45aacbcfd7d8558e4eff0343f1c60a4 | Sudah tercakup #50. Jangan menambah ulang atau menghitung dua kali; pemilik perubahan pricing berikutnya Codex Cloud. |
| Pricing / #50: sanro-studio.php | #50 sudah memiliki require product-pricing dan kedua hook admin profil harga dari delta pricing; keseluruhan bootstrap berbeda | Hermes satu penulis bootstrap. Pricing menyerahkan kebutuhan hook sebagai dependency, tidak mengganti bootstrap. |
| Pricing: tests/studio-product-pricing-offline.php | Ada di branch pricing, tidak ada di #50 atau #48 | Delta unik untuk dipertahankan oleh Codex Cloud setelah restore; bukan alasan cherry-pick seluruh commit pricing. |
| includes/pricing.php | Blob 468c04772bf19410d394dbde5d8f0e780dae90a4 identik pada #48, #50, pricing | Tidak ada beda tarif pada snapshot. Restore file identik bukan implementasi tarif baru. |
| #49 / #48 / #50 | Lima docs/ledger awal #49 tidak beririsan dengan changed files #48/#50; pembaruan ini menambah penunjuk STATUS.md | Work hanya docs. docs/DEVELOPMENT-LEDGER.md #48 tetap evidence source bootstrap, tanpa bobot/persen tandingan. |
| CI runner / restore test tree | #48 run-all.sh mengeksekusi seluruh root tests/*.php/js/cjs/mjs; #50 memulihkan fixture/helper dan skrip staging/legacy | Cloud harus menyesuaikan kontrak discovery setelah restore, bukan menganggap semua file executable test. Tidak menjalankan blanket runner terhadap gabungan source pada sesi ini. |

## Dependency source penting

PR #50 memulihkan source dari **#45 SHA 179850842be5f801e9c79aad6afc4c522e6e8e23**, BUKAN donor kumulatif #46 SHA e01bdc55a21b4f0b2c471300dfc58ac482641a07. Commit PR #46 bukan otomatis hilang: branch donor tetap disimpan. Hermes harus memilih delta native yang diperlukan; jangan restore massal lagi atau menganggap #50 sudah memuat seluruh hardening #46.

Diff source #45 → #46 menunjukkan perubahan lanjutan pada:
- assets/buyer.js, assets/checkout-auth.js, includes/buyer.php;
- includes/checkout-batch-lock.php, checkout-bridge.php, draft-rest.php, draft.php, order-preview.php, storage-policy.php;
- root tests/test_sanro_draft_rest.php dan sembilan tests/staging-one-order-*.

Contoh dependency yang masih perlu dinilai: quote metadata order item (34d43eb), preview revision/binding (35393c5, 7e907df, 4bc1caa), preflight seluruh size lines (6ec389e), kedua sisi produksi lengkap (bc8acaa), checkout menolak binding belum terintegrasi (6a195ed), HTTP 409 stale preview revision (49cb791). Ini daftar source untuk review Hermes; tidak ada cherry-pick/aplikasi patch pada sesi Work.

Pricing bergantung konfigurasi produk, tabel harga server dan quote/cart milik core. CI #48 hanya terbukti pada base bootstrap: private draft/master/checkout tidak tersedia pada snapshot #48. PR #50 menghadirkan source dependency itu tetapi belum membuktikan runtime atau semua kontrak PASS.

## Urutan integrasi yang ditetapkan

Urutan berikut adalah proposal untuk pelaksanaan SETELAH persetujuan merge Owner; tidak satu pun merge dilakukan sekarang.

| Urutan | Kandidat / pemilik | Prasyarat dan hasil |
|---|---|---|
| 0 | #49 ledger / Work | Tetapkan pointer baseline tunggal dan ownership sekarang; boleh direview paralel karena hanya docs. |
| 1 | #47 bootstrap | Fondasi ke integration/sanro-studio; tidak membuka frontend atau checkout. |
| 2 | #50 restore/core / Hermes | Setelah bootstrap, siapkan delta langsung terhadap integration terbaru, selesaikan irisan bootstrap, review delta keamanan #46 dan FAIL kontrak inti. Fitur tetap default OFF. |
| 3 | Pricing profile / Cloud | Setelah restore, bawa hanya delta pricing/test yang belum ada; hindari duplikasi product-pricing dan hook. Buat PR langsung ke integration, bukan PR bertumpuk baru. |
| 4 | #48 CI / Cloud | Setelah kontrak tree restore+pricing jelas, siapkan delta langsung ke integration; pertahankan tests/README, batasi discovery offline dan penuhi dependency fixture. Evaluasi FAIL inti bersama Hermes. |
| 5 | Kandidat fitur terpadu / Hermes + Cloud | Setelah source konsisten dan check proporsional PASS, tentukan langkah implementasi frontend/Woo berikutnya. Staging/E2E/order membutuhkan izin sesuai scope; tidak dilakukan sekarang. |

Pengembangan delta pricing dan penyesuaian CI dapat berlangsung paralel di branch Cloud yang berbeda tanpa menyentuh bootstrap/editor Hermes. Urutan penerimaan tetap 1→2→3→4; jangan mencampur izin koordinasi dengan izin merge. Semua PR kode baru/yang dipersiapkan ulang menargetkan integration/sanro-studio setelah basis siap. Base #48/#50 saat ini masih #47; belum diretarget.

## QA yang dibaca, bukan dijalankan ulang

#48 melaporkan PHP/JS syntax dan bounded bootstrap/Node PASS pada e8404d6; GitHub Actions dan runtime WordPress/Woo NOT VERIFIED. #50 melaporkan lint 153 PHP tracked ditambah guard PASS dan bootstrap/restore guard PASS. Laporan #50 juga memuat:
- FAIL cart-contract.php: server quote retained;
- FAIL draft-lock.php: mismatched order quantity denied;
- FAIL native-core.php: native function inventory;
- kegagalan dependency/fixture staging atau legacy serta GD tidak tersedia;
- dua baris PASS dengan pesan SKIP GD pada admin-preview-delivery/private-preview-files: dicatat sebagai assertion terkait belum dijalankan, bukan verifikasi gambar PASS;
- inherited trailing whitespace pada skrip staging E2E.

Jangan menggolongkan semua FAIL sebagai lingkungan. Hermes menilai dan memperbaiki kontrak cart/lock/core; Cloud menilai runner, fixture, dan test contract. Source pricing terpisah memiliki laporan 9 lint PASS dan dua offline test PASS dari QA Hermes; bukan bukti pricing gabungan #50+#48 sudah PASS. Work tidak menjalankan atau memperbaiki kode/tes pada putaran ini.

GO koordinasi dan kelanjutan development oleh pemilik pada scope terpisah. NO-GO merge/deploy dan klaim integrasi final; runtime ACK worker eksternal masih belum tersedia. Semua angka tahap tetap baseline resmi.

---

## Matriks seluruh 34 PR

OPEN/CLOSED di bawah berasal dari GitHub; seluruhnya Draft, tidak ada yang merged pada snapshot. KANDIDAT ARSIP berarti hentikan pemakaian sebagai jalur kerja setelah syarat terpenuhi; bukan izin menghapus ref.

| PR | Keadaan | Branch head (dipertahankan) | SHA | Keputusan | Alasan/syarat |
|---|---|---|---|---|---|
| [#14](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/14) | OPEN | `work/sanro-studio-lumise-core-20261118` | `4f58fadb2606` | DONOR lalu kandidat arsip | Ambil modul native yang diperlukan; runtime vendor privat bukan jalur integrasi baru. |
| [#15](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/15) | OPEN | `fix/sanro-studio-editor-config-validation-20261119` | `a4dc7a6160a9` | DONOR lalu kandidat arsip | Validasi REST, harga/cart bertanda tangan, buyer fingerprint; ambil versi terbaru donor #46. |
| [#16](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/16) | OPEN | `manual/sanro-studio-side-zone-draft-20261121` | `5996d07e9f15` | DONOR lalu kandidat arsip | Geometri per sisi dan visual print-zone admin. |
| [#17](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/17) | OPEN | `manual/sanro-studio-production-ready-admin-20261121` | `81eb703f9245` | DONOR lalu kandidat arsip | Admin siap-produksi berizin; tidak menyamakan siap dengan paid. |
| [#18](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/18) | OPEN | `manual/sanro-studio-drive-verified-folder-20261121` | `09b7d45b8cf6` | TUNDA; simpan sumber | Verifikasi folder Drive privat; flags OFF. |
| [#19](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/19) | OPEN | `manual/sanro-studio-drive-auto-provision-20261121` | `3a2e127bcb9d` | TUNDA; simpan sumber | Provision folder sesudah paid/ready; flags OFF. |
| [#20](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/20) | OPEN | `manual/sanro-studio-private-master-drive-export-20261121` | `df5473c5a122` | TUNDA; simpan sumber | Ekspor master asli, bukan PNG kandidat siap cetak; flags OFF. |
| [#21](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/21) | OPEN | `manual/sanro-studio-checkout-order-batch-lock-20261121` | `8cccecd6f9a7` | DONOR lalu kandidat arsip | Binding order/item dan lock seluruh ukuran; integrasikan sebelum uji satu order. |
| [#22](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/22) | OPEN | `manual/sanro-studio-dtf-raster-image-20261121` | `ae815744723d` | TUNDA; simpan sumber | Raster gambar 300 DPI kandidat; OFF. |
| [#23](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/23) | OPEN | `manual/sanro-studio-dtf-gd-isolated-20261121` | `90303f2cda17` | KANDIDAT ARSIP QA | Simpan bukti GD sebagai historis, bukan bukti HEAD baru. |
| [#24](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/24) | OPEN | `manual/sanro-studio-dtf-layer-composition-20261121` | `4b2ad7fc88ad` | TUNDA; simpan sumber | Kompositor gambar+teks dengan batas eksplisit; OFF. |
| [#25](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/25) | OPEN | `manual/sanro-studio-dtf-private-artifact-20261121` | `84034f349b14` | TUNDA; simpan sumber | Manifest dan private artifact writer; OFF. |
| [#26](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/26) | OPEN | `manual/sanro-studio-dtf-order-workflow-20261121` | `3eefa68842e1` | TUNDA; simpan sumber | Koordinator front/back render/persist/readback; OFF. |
| [#27](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/27) | OPEN | `manual/sanro-studio-admin-dtf-status-20261121` | `b45ebfadb5e3` | DONOR lalu kandidat arsip | Panel status admin tidak menyediakan download artifact. |
| [#28](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/28) | CLOSED | `manual/sanro-studio-wc-runtime-preflight-20261121` | `be372c3c5779` | KANDIDAT ARSIP; PR sudah CLOSED | QA runtime, belum merged; simpan bukti. |
| [#29](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/29) | CLOSED | `manual/sanro-studio-hermes-admin-browser-e2e-20261121` | `1f474225b091` | KANDIDAT ARSIP; PR sudah CLOSED | Browser admin GET/HEAD; simpan bukti. |
| [#30](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/30) | CLOSED | `manual/sanro-studio-hermes-staging-admin-session-20261121` | `ea1923221c8f` | KANDIDAT ARSIP; PR sudah CLOSED | Helper sesi privat; tidak menyalin state/token. |
| [#31](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/31) | OPEN | `manual/sanro-studio-dtf-admin-staging-targeted-20261121` | `31deb5e8e65a` | KANDIDAT ARSIP OPS bersyarat | Simpan rujukan deploy staging dan panel status; jangan jalankan installer lama. |
| [#32](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/32) | OPEN | `manual/sanro-studio-hpos-qa-order-20261121` | `c1e35d808f60` | KANDIDAT ARSIP QA bersyarat | Fixture order Rp0 #52 historis; jangan jalankan pembuat order. |
| [#33](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/33) | OPEN | `manual/sanro-studio-dtf-admin-candidate-action-20261121` | `ce31509daf42` | TUNDA; simpan sumber | Admin generate masih OFF; bukan download artifact lengkap. |
| [#34](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/34) | CLOSED | `manual/sanro-studio-dtf-admin-safe-staging-plan-20261121` | `3e281ae4e142` | KANDIDAT ARSIP; PR sudah CLOSED | PLAN-only, bukan hasil integrasi fitur. |
| [#35](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/35) | OPEN | `manual/sanro-studio-dtf-admin-disabled-deploy-20261121` | `b517532a6d0b` | KANDIDAT ARSIP OPS bersyarat | Persetujuan staging historis tidak berlaku untuk aksi baru; generator OFF. |
| [#36](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/36) | OPEN | `manual/sanro-studio-dtf-admin-readiness-20261121` | `61587761fc64` | TUNDA; simpan sumber | Readiness diagnosis saja; OFF. |
| [#37](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/37) | CLOSED | `manual/sanro-studio-dtf-readiness-stage-plan-20261122` | `a74849111987` | KANDIDAT ARSIP; PR sudah CLOSED | PLAN-only; simpan bukti. |
| [#38](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/38) | OPEN | `manual/sanro-studio-dtf-readiness-disabled-deploy-20261122` | `55f370004c58` | KANDIDAT ARSIP OPS bersyarat | Staging readiness dormant historis; flags OFF. |
| [#39](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/39) | OPEN | `manual/sanro-studio-dtf-two-side-preflight-20261122` | `859fb222236d` | TUNDA; simpan sumber | Preflight kedua sisi sebelum write; OFF. |
| [#40](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/40) | OPEN | `manual/sanro-studio-dtf-locked-snapshot-20261122` | `c9665836b81c` | TUNDA; simpan sumber | Snapshot terkunci lintas preflight/render/commit; OFF. |
| [#41](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/41) | CLOSED | `manual/sanro-studio-pr41-twofile-staging-plan-20261122` | `1b329a2f3628` | KANDIDAT ARSIP; PR sudah CLOSED | PLAN-only; simpan bukti. |
| [#42](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/42) | OPEN | `manual/sanro-studio-pr42-twofile-guarded-staging-20261122` | `aa221cde749c` | KANDIDAT ARSIP OPS bersyarat | Installer dua file spesifik staging; bukan integrasi branch. |
| [#43](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/43) | OPEN | `manual/sanro-studio-authenticated-buyer-e2e-20261122` | `c73cd667652c` | KANDIDAT ARSIP QA bersyarat | Tes buyer autentik, bukan guest-access PASS. |
| [#44](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/44) | OPEN | `manual/sanro-studio-image-e2e-reuse-text-qa-20261122` | `60f1dc07d1dd` | KANDIDAT ARSIP QA bersyarat | Reuse draft sintetis; image E2E final belum terbukti. |
| [#45](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/45) | OPEN | `fix/sanro-studio-master-text-float-guard-20261122` | `179850842be5` | DONOR lalu kandidat arsip | Guard drift x/y teks <=1e-6; jangan melemahkan CAS lainnya. |
| [#46](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/46) | OPEN | `dev/staging-one-order-gate-20261122` | `e01bdc55a21b` | PERTAHANKAN DONOR UTAMA | Snapshot kumulatif e01bdc5; preflight bukan runner atau izin order. |
| [#47](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/47) | OPEN | `feature/studio-bootstrap-admin` | `71955dd3d4df` | PERTAHANKAN JALUR AKTIF | Kandidat bootstrap/editor menuju integration; frontend hooks belum aktif. |

## Syarat arsip

Untuk setiap kandidat: buktikan commit/delta unik sudah tersimpan di donor/tag/PR, catat evidence deployment historis yang masih dirujuk, dan pastikan tidak ada task pemilik yang aktif. Untuk DONOR: arsip hanya setelah delta fungsional dan dependency sudah terintegrasi dan check proporsional PASS. Jangan menutup/menghapus apa pun pada tugas ini. Branch DTF/Drive yang ditunda tetap disimpan; penundaan bukan penghapusan backlog.

Bukti historis 143/179/182 PASS tidak menjadi PASS pada integration baru. Staging deployment terdahulu tidak membuktikan seluruh Git HEAD terpasang. Kebijakan backup otomatis Owner tetap berlaku: tidak membuka pekerjaan restore/tes restore baru.
