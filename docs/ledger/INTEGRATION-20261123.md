# Jalur integrasi tunggal — keputusan koordinator 23 November 2026 WIB

Scope: PR #14–#47; dokumentasi saja. Snapshot API GitHub dan Git dibaca pada sesi ini. Tidak ada penutupan PR, penghapusan branch, restore, merge, retarget, atau deploy oleh tugas ini.

## Jalur yang dipilih

`main` → `integration/sanro-studio` sebagai satu-satunya target integrasi → feature PR langsung → setelah izin Owner, kandidat staging → setelah gate dan izin terpisah, main/production.
Anak panah adalah alur usulan, bukan operasi Git yang sudah dilakukan.

Baseline integration: `0d981b634888a5389bc394b93a13a4177090cc76`; parent main `0760f565e8b1ef068c706748022d2f8b5539837a`. Integration hanya menambah STATUS.md, belum berisi plugin SANRO Studio. Branch `integration/studio-preview-conflict` pada SHA yang sama: kandidat arsip alias duplikat, jangan dipakai sebagai jalur kedua. Tidak ada branch develop yang perlu dibuat.

1. Gunakan PR #47 (`71955dd3d4df9669b1e667e928a64508a2d579b6`) untuk bootstrap/editor asli. Public editor hooks belum diregistrasikan; jangan menyebut editor integration berfungsi.
2. Branch `work/claude-sanro-studio-product-pricing-profile-20261123` pada `7a732797ebe43960f3f98e8d666cc792d21454f9` adalah turunan #47 (ancestry Git terbukti), membawa 3 file / 90 baris tambahan untuk profil harga. Jadikan calon PR langsung tersendiri berbasis integration terbaru SETELAH #47 disetujui/diintegrasikan, hanya delta modul harga. Jangan impor seluruh bootstrap lagi; jangan mengganti branch Claude atau #47.
3. Pilih versi akhir modul native dari donor kumulatif #46 (`e01bdc55a21b4f0b2c471300dfc58ac482641a07`): draft/REST, master privat, quote/cart, checkout binding, admin preview dan production-ready. #45 adalah ancestor #46; #44 ancestor #45. Jangan merge berurutan seluruh #14–#46 dan jangan copy seluruh plugin lama di atas bootstrap baru. Review dependency, hook, constant, helper, dan test per modul terlebih dahulu. Isolasi runtime vendor internal dari jalur native.
4. Selesaikan satu alur: desain depan/belakang → save/reopen → quote server → cart → satu order sintetis staging → admin preview kedua sisi → artifact privat yang benar-benar dapat diakses admin berizin. PR #46 baru preflight, bukan bypass checkout atau runner. Unduh artifact berizin masih gap; panel status bukan bukti file dapat diakses.
5. Drive otomatis dan raster DTF 300 DPI tetap OFF dan ditunda sesudah alur inti terbukti. Setelah tiap PR selesai, PR berikutnya berangkat dari integration terbaru; tidak ada PR bertumpuk baru. Merge dan staging order/deploy tetap memerlukan izin Owner untuk tindakan tersebut.

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
