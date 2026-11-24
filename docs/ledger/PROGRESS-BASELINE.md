# SANRO Studio — satu baseline resmi development

## Milestone: candidate V1 dibangun; final install terblokir — 24 November 2026

Source `c0413fdc477c1370435a20a3160fa4460f95fcf7`; paket kandidat 77 file dibangun dua kali dengan ZIP dan manifest identik. PHP CLI lint + candidate PLAN_ONLY preflight PASS; menu/admin contract 54 assertions PASS. Artefak ada di [paket c0413fd](../../artifacts/studio-v1/c0413fd/README.md), termasuk ZIP, manifest, SHA256SUMS, evidence dan request kondisional. Checksum ZIP `b0ce3263452ccc3ee7c3e6ef20bb22e25ced9c4a9463cb218977c27d339d2738`; manifest `a033a48595c8e17762f4b291b9d4a720e10dfcefbaa70ad0052c56da0d38bb4c`.

**Belum Final Release dan belum terinstal.** Fresh SSH staging identity gagal autentikasi di sandbox dan escalation resmi. Akibatnya current existing-tree digest, production isolation, web SAPI, installer journal/lock/crash state, flags/private roots, installed candidate, live admin, synthetic checkout/order, dan DTF order output NOT TESTED. Tidak ada remote mutation. OAuth/provider Drive dan private folder staging belum tersedia; konfigurasi yang dibutuhkan tercantum pada [instruksi Drive Owner](../qa/studio-drive-owner-configuration-20261124.md). Baseline resmi tetap **92,00% (+0,00)**: candidate packaging dan status UI bukan live acceptance baru.

## Milestone: admin status clarification dan release gate — 24 November 2026

Dashboard independen SANRO Studio kini membedakan kode checkout WooCommerce yang tersedia dari writer staging yang masih OFF; deskripsi preferensi native diperjelas. Panel order tetap dipertahankan. Regression contract **54 assertions PASS**, PHP lint dan `git diff --check` PASS. Baseline resmi tetap **92,00% (+0,00)** karena perubahan copy/status bukan bukti E2E baru.

Final release/install **NO-GO** saat ini: dua percobaan SSH read-only (sandbox dan escalation resmi) ke alias `kaoscetak-staging` gagal `Permission denied (publickey,password)`. Tidak ada aktivasi writer, order, file atau perubahan staging/production. Drive dan storage GC OFF. Setelah akses pulih, tetap perlu fresh staging gate, satu order sintetis unpaid, validasi DTF lokal, serta paket final terinstal dan terverifikasi. OAuth Drive/folder privat belum disediakan; kebutuhan Owner ada pada [panduan konfigurasi Drive](../qa/studio-drive-owner-configuration-20261124.md).

## Checkpoint terbaru — staging update dan buyer completion

## Checkpoint: scope correction dan function completion

Payment gateway/sandbox bukan gate SANRO Studio. Preflight satu-order telah diubah agar tidak bergantung pada jumlah gateway; runtime check mengandalkan API status WooCommerce standar. Owner Drive setup tercatat di [instruksi konfigurasi](../qa/studio-drive-owner-configuration-20261124.md). Upaya SSH terbaru gagal autentikasi, sehingga isolasi staging tidak bisa direfresh; tidak ada order/writer yang dijalankan. Lihat [hasil scope correction](../qa/studio-scope-correction-functional-completion-20261124.md). Buyer/install checks tidak diulang; baseline **92,00% → 92,00%, delta 0,00**.

## Checkpoint: integrated staging E2E gate

Owner menyetujui paket terpadu `fad47c6`. Pemeriksaan fresh read-only mengonfirmasi staging/candidate/tree digest dan private roots; semua writer/GC OFF. Eksekusi berhenti sebelum write karena tidak ada payment gateway sandbox aktif, Drive parent folder/OAuth provider tidak dikonfigurasi, dan sesi browser admin terautentikasi tidak tersedia. Tidak ada order, payment, file DTF/Drive, perubahan konfigurasi atau source. Rincian: [hasil E2E gate](../qa/studio-integrated-staging-e2e-20261124.md) dan [evidence](../../artifacts/studio-v1/fad47c6/integrated-staging-e2e-execution.json). Baseline **92,00% → 92,00%, delta 0,00**; tidak ada kredit rubrik baru.

## Checkpoint: V1 accelerated completion sprint

[Matriks status, rubrik QA, dan paket E2E satu sesi](../qa/studio-v1-completion-sprint-20261124.md). Source review tidak menemukan gap implementasi pada checkout/order WooCommerce, DTF candidate generation, atau Drive workflow; ketiganya memiliki implementasi dan fixture lokal, sedangkan integrasi staging nyata belum diuji. Menu independen sudah terdaftar secara live; screenshot admin terautentikasi belum tersedia. Paket approval terpadu yang terikat pada kandidat terpasang ada di `artifacts/studio-v1/fad47c6/integrated-staging-e2e-approval.json`; status pending, writer tetap OFF.

Rubrik QA/release tahap7 terukur kini memberi 55/100: 15 targeted regression/package, 15 install/runtime identity, 25 browser buyer PASS; admin screenshot 0/10, checkout/order 0/15, DTF runtime 0/10, Drive runtime 0/10. Tidak menghitung implementasi tahap1–6 dua kali. Tahap7 berubah40→55; total terkalibrasi **90,50→92,00% (+1,50 poin)**, termasuk saldo Owner historis0,15 tetap. Angka ini menggantikan estimasi tahap7 lama yang tidak memiliki subrubrik.

[Evidence instalasi dan QA buyer](../qa/studio-staging-update-buyer-completion-20261124.md). Kandidat Owner-approved `fad47c6` sudah terpasang di staging melalui installer terkunci, journal `committed`, rollback tepat dan terverifikasi. Plugin aktif, satu menu top-level independen, asset fingerprints, master/preview privat depan-belakang, multi-size M=2/L=1, quote IDR 393.000, dan browser desktop/mobile **PASS**. DTF, Drive, storage GC, checkout writer, order/payment dan production tetap OFF/tidak disentuh. Screenshot admin **NOT TESTED** karena sesi autentikasi browser tidak tersedia. Baseline tetap **90,50% → 90,50%, delta 0,00**; tidak ada bobot subfitur QA resmi untuk kredit persentase baru.

Checkpoint aktif: [Pre-install menu correction](../qa/studio-admin-menu-correction-20261124.md). Satu root independen SANRO Studio; Pengaturan, Status Integrasi dan Storage Privat seluruhnya di bawah root. Capability, settings dan Woo order panels dipertahankan.48 targeted checks + affected settings/bootstrap/lint PASS. Baseline tetap90,50%; kandidatf45b43b superseded dan diganti ZIP/source manifest baru yang ditunjuk artifacts/studio-v1/CURRENT.json setelah build. Tidak ada instalasi/staging mutation.

Checkpoint runtime berikutnya: [Live staging functional QA](../qa/studio-live-staging-functional-qa-20261124.md). Playwright Chromium desktop/mobile, asset fingerprint, editor layout and synthetic front/back geometry PASS. One staging-only synthetic draft and front private master upload/readback PASS; the second master was stopped by HTTP429 per-draft rate limit. Preview, complete quote and historic HTTP422 root cause remain unresolved; no cart/order/payment or production writer was used. Admin menu registration remains one independent root, but authenticated admin screenshot NOT TESTED. Official progress remains90,50%, delta0.

Investigation lanjutan pada staging membuktikan transient master quota bernilai string `"1"` saat HTTP429; source 081a63f salah memperlakukan semua non-integer sebagai kuota habis. Reset transient eksak 2026-11-24 07:31:16Z; aplikasi tidak menetapkan Retry-After. Perbaikan source ternormalisasi sudah ditargetkan diuji dan mempertahankan fail-closed/quota10; candidate install belum disetujui atau dilakukan. Preview GET saat ini memberi409 `sanro_preview_not_current`, bukan422; penyebab422 historis belum terbukti. Menu aktual satu root independen PASS; admin screenshot unavailable tanpa sesi autentikasi. Preview/quote lengkap tetap open, jadi tujuh stage dan total baseline90,50% (delta0) tidak dikreditkan ulang.

Checkpoint staging terbaru: [instalasi resmi dan post-install smoke](../qa/studio-staging-installation-20261124.md). Owner-approved exact candidate applied with lock, committed journal and retained rollback; installed tree digest `8d560cdb95611da98dc4a739737dff30ab9ac35f4ec6866defb8093808fd37db`. Active SANRO Studio/WooCommerce, one SANRO root, Woo product/order hooks, editor config and served asset hashes PASS. DTF, Drive, GC and checkout writer remain OFF. Screenshot/browser JS, buyer E2E and order/payment remain NOT TESTED. No production/merge/order mutation. Feature baseline remains90,50%, delta0; staging installation **PASS**.

Checkpoint operasi staging terbaru: [installation gate refusal](../qa/studio-installation-attempt-20261124.md). Owner approved the exact candidate; source/ZIP/manifest/existing-tree hashes match. SSH identity, staging boundary, LiteSpeed SAPI, private storage, writer flags, and clean journal/lock state PASS. Official installer refused the sparse-key `active_plugins` JSON shape before replacement; no plugin/database/production change occurred. Candidate remains unchanged and uninstalled. Fixing installer serialization changes pinned hashes and requires new Owner approval. Installation **NO-GO**; baseline remains90,50%, delta0.

24 November 2026: Codex CLI fetched PR #52 HEAD `e5c7d1e53edbcee28db496678946da187ec9bd99` in an isolated worktree. Reverification: 48 menu assertions, bootstrap/GC default-OFF, foundation/settings37, four prerequisite fixtures, six PHP lints, SHA256SUMS, and 78-member candidate PLAN_ONLY preflight PASS. Candidate ZIP/hash unchanged; progress remains90,50% (delta0). Live corrected menu and candidate E2E remain NOT TESTED; deployment remains unauthorized.

24 November2026 WIB. Checkpoint final native feature gap closure melanjutkan audit HEAD `591bf1b91bbbd5a29088f0123a5b0906d11f5cec`. **90,50% →90,50%, delta0,00**. Empat gap native H04/H05/M06/M07 telah ditutup pada kode dan tes lokal terarah. Bobot SANRO-7-v1 dan saldo historis tetap; scope ini sebelumnya sudah masuk estimasi historis, sehingga tidak diberi kredit kedua atau kalibrasi baru. Runtime/E2E belum sign-off.

Checkpoint terbaru: [Direct staging readiness](../qa/studio-direct-staging-readiness-20261124.md). Real Cloud Browser + WordPress MCP preflight membuktikan entry/editor lama dan classic cart/checkout; asset runtimeccf75da12b01 berbeda dari kandidat9573ce5cdcb9. ZIP immutable sourcef45b43b kini disimpan beserta manifest/checksum/approval request di artifacts/studio-v1/f45b43b. Authenticated Site Health: PHP8.3.37/LiteSpeed/int64/GD2.3.3/memory1536M teramati; kapabilitas lengkap/flags/private paths/tree digest dan E2E kandidat tetapNOT TESTED; no admin credit/progress increase. Jack/Hermes bukan dependency; approval request bukan izin deploy.

| Tahap | Bobot | Sebelumnya | Sekarang | Kontribusi sekarang | Delta berbobot |
|---|---:|---:|---:|---:|---:|
| 1. Fondasi/core |25%|95%|95%|23,75|0,00|
| 2. Backend produk/admin |10%|94%|94%|9,40|0,00|
| 3. Frontend buyer |15%|96%|96%|14,40|0,00|
| 4. Harga server/quote |10%|96%|96%|9,60|0,00|
| 5. Simpan/ownership/share |10%|98%|98%|9,80|0,00|
| 6. Checkout/produksi/Drive |20%|97%|97%|19,40|0,00|
| 7. QA/rilis/mobile |10%|40%|40%|4,00|0,00|
| **Subtotal tujuh tahap** |**100%**|—|—|**90,35**|**0,00**|
| Saldo Owner historis belum teralokasi |—|—|—|0,15|0,00|
| **Total baseline development** |—|**90,50%**|**90,50%**|**90,50**|**0,00**|

Saldo0,15 tetap rekonsiliasi historis72,10 vs71,95 checkpoint2d945be. Tabel adalah baseline estimasi scope historis, **bukan persentase jumlah fitur wajib yang sudah PASS**. Audit sebelumnya menemukan gap nativeH04/H05/M06/M07 yang kini ditutup pada kode; khususnya nilai historis tahap5 tidak boleh dibaca sebagai bukti koleksi/share native sudah lengkap. Tanpa rubrik subfitur resmi, tidak mengarang kalibrasi baru atau menyembunyikan gap dengan kenaikan QA. Matriks audit menjadi syarat GO yang lebih kuat daripada angka ini.

## Checkpoint aktif: native feature gap closure

[Hasil implementasi dan evidence](../qa/studio-native-gap-closure-20261124.md). H04/H05/M06/M07 **CLOSED pada kode**: native permanent collection/share/revoke/fork/reopen/delete; aggregate storage quota dan protected orphan GC; local alpha-aware BG removal/original restore; merchant colour/size/front-back mapping dan per-size order previews. Status VERIFIED tetap terbatas pada tes PHP/GD/filesystem/DOM offline; real WP/Hostinger/browser/SMTP/Drive tidak diklaim PASS.

Stage5 historis98% tidak pernah menjadi bukti koleksi native sudah lengkap. Penutupan gap memperbaiki kesesuaian scope yang telah dikreditkan, bukan mengubah rubrik atau menghitungnya dua kali. Subtotal90,35 + saldo historis0,15 =90,50; baseline ini satu-satunya angka resmi.

PASS terarah:64 native functional/security,13 variant checkout/admin integrity (+17 fixture prerequisites),11 route,31 merchant controls,15 buyer DOM,10 image-preview,18 zone geometry,9 cleanup, default-OFF bootstrap dan asset fingerprint. Lint sumber berubah dan paket/hash kandidat diverifikasi sebelum dilaporkan. Chromium nyata NOT TESTED karena binary tidak tersedia; harness disiapkan, beralih metode setelah dua kegagalan. Tidak mengulang Full Audit.

[Audit sebelumnya](../qa/studio-full-audit-20261124.md) tetap merupakan evidence historis, bukan daftar OPEN terkini. Tax/fee/coupon, harga produk yang belum disetujui dan retensi order tetapOPEN; fail-closed. Master privat dipertahankan dalam bridge yang sudah ada selama Drive OFF, tidak ada klaim upload Drive nyata.

Blocker sign-off: Hostinger FPM/current SHA/CORS/private dirs/cache, SMTP, full buyer→checkout/admin E2E pada kandidat baru, gateway/Drive/print. HTTP422 runtime NOT VERIFIED. Generator/Drive/GC defaultOFF, tidak ada deploy/merge/production/order mutation atau worker Hermes/Codex.

**GO** persiapan kandidat/preflight; **NO-GO** final release/automatic apply/deploy/merge/writer activation. [Paket E2E staging](../qa/studio-audit-staging-packet-20261124.md); [Checkpoint Hermes](../qa/studio-hermes-handoff-20261124.md).

Kandidat pengganti terverifikasi: source `8d817c2ba92b89d03eaa4d680173925361ce0a36`; ZIP SHA256 `ca55c23ef3b13408e45d4b011a7d47be3676aec7da6e2d3e906508873ae66afd`. Manifest/checksum dan request baru: `artifacts/studio-v1/8d817c2`; pointer `artifacts/studio-v1/CURRENT.json`. Semua approval/deploy tetapfalse; runtime corrected menu NOT TESTED.

Fix transient Hostinger sudah dikomit (`fad47c6f508258d594f92daca79c337d6911b404`) dan candidate baru dibuat di `artifacts/studio-v1/fad47c6`: ZIP `b83c1b03d18e0633d502aff09d9b1d40f01eb216a22850a7c990fc40c78a5723`, manifest `8664f584c4b746120c98ed50b337fc9616508f3b390eca3fa2a8429a370f8309`. Targeted REST counter/preview/master tests dan PLAN_ONLY preflight PASS. Approval file tetap `approved:false`; runtime complete buyer QA menunggu approval untuk candidate baru. Baseline90,50%, delta0.
