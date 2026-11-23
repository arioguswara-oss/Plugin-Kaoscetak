# SANRO Studio — checkpoint siap Full Audit, belum dimulai

24 November2026 WIB. Draft PR52, branch `work/studio-mobile-checkout-integration-20261123`. Parent `5c549432baaf3be93a51f31eeb8a018bccbcbce5`; SHA checkpoint final adalah HEAD commit yang membawa file ini dan exact source_commit di manifest. Baseline resmi **90,50%**, sebelumnya89,50%, delta+1,00; bobot tetap. Tabel tunggal `docs/ledger/PROGRESS-BASELINE.md`.

**HENTIKAN fitur baru. Full Audit menunggu instruksi Owner berikutnya.** Hermes/Codex tidak dijalankan; file ini persiapan handoff saja. Tidak deploy/merge/production/order DB atau aktivasi generator/Drive.

## Hasil source putaran ini

1. **Crash recovery installer selesai pada filesystem/process boundary.** `tools/studio-install-transaction.py`: durable intent journal dengan file+directory fsync sebelum rename, exact before/candidate digests dan source/archive binding. Recovery memeriksa kenyataan folder, bukan phase saja: prepared→original no-op; old renamed/missing target→restore original; new renamed→quarantine exact candidate lalu restore; committed→no-op dan previous tetap ditahan. Replay recovery aman, termasuk recovery itu sendiri terputus. Unknown/tampered/corrupt/mismatched/locked state fail-closed dan tidak menghapus folder lama. Terminal journal diarsipkan sebelum transaksi berikutnya. `--recover` CLI tetap mewajibkan same approval/source/target/flags; zip/lint baru tidak dibutuhkan untuk mengembalikan folder lama. Tidak mengklaim dua rename sebagai satu transaksi filesystem atomik, power-loss recovery hosting belum diuji.
2. **Native account checkout diperbaiki.** Woo short numeric session ID hanya diterima jika sama dengan authenticated WP user ID; guest opaque hashes tetap kompatibel. Explicit owner-proof reopen dapat CAS-migrate draft belum terkunci dari guest ke logged-in session; tidak memigrasi order terkunci. Tidak memperluas Blocks/native variations/mixed-design.
3. **Repeat5% diintegrasikan berdasarkan source lama.** Email proof berada di metadata server, bukan artwork/client assertion; nonce+owner token → challenge hash/email30 menit → single-use CAS verified owner; alamat verified tidak dapat diganti lewat edit token dan copy berbeda owner tidak mewarisi proof. API gated defaultOFF dan mail budget8/hour/IP seperti batas source lama. Tidak ada email nyata dikirim pada sesi ini.
4. **Harga/net/evidence end-to-end.** Verified owner email == billing email dan actual prior paid/non-refunded Woo object →5% per pcs termasuk satuan; positive integer half-up sama dengan source lama65001→61751. HPOS-compatible paginated native order query, bukan direct order SQL. Technical lookup exhaustion/failure menolak quote, tidak membuat history expiry atau diam-diam mengenakan harga dasar. Checkout email changes reprice dari dasar tanpa compounding. Quote/item immutable evidence mencakup base/net/unit discount/proof; pending pay revalidates saved prior order; order sudahpaid tidak direprice retroaktif. Pajak/fee/kupon tetapOPEN/guarded.
5. **Production package/admin konsisten.** Repeat base/net/discount dari locked quote masuk manifest dan Unicode guide; generic Drive manifest hashes mengindeks PDF/preview yang sama. Admin menampilkan repeat base/net bersama dua preview privat; tidak menampilkan email/challenge/fingerprint/history IDs. Renderer/font/hierarchy/uploader tidak diubah atau dihitung ulang. Generator/Drive tetapdefaultOFF.

Source keputusan: `docs/business-rules/kaoscetak-lite-designer-v1.md`; perilaku eligibility/rounding sebelumnya pada `plugins/kaoscetak-lite-designer/includes/repeat.php`, `api.php`, `sharing.php`, `tests/test_repeat.php` dan `tests/test_repeat_checkout.php`. Tidak mengimpor dependency plugin lama atau menciptakan kebijakan bisnis baru. Native query args diverifikasi terhadap dokumentasi primer https://developer.woocommerce.com/docs/features/orders/wc-get-orders/ (page/objects/billing_email/exclude).

## Bukti targeted, jangan diulang tanpa perubahan relevan

- `python tests/test_studio_installer_crash.py`: **10 tests PASS**, subprocess benar-benar `os._exit` pada prepared/old rename/new rename/committed/quarantine/restore; replay, wrong source/corrupt journal/foreign target dan CLI missing-target recovery memakai fake WP executable + synthetic approval, bukan approval Owner atau hosting.
- `python tests/test_studio_staging_install.py`: **9 tests PASS**, normal installer/refusal/rollback setelah transaction refactor.
- `plugins/sanro-studio/tests/authenticated-cart-session.php`: **10 checks PASS**; guest hash compatibility, native authenticated ID, wrong user/owner, CAS migration dan locked refusal.
- `plugins/sanro-studio/tests/repeat-eligibility-flow.php`: **44 checks PASS**; email/expiry/single-use/token/copy/CAS/mail failure/rate limits, no client proof, paginated paid history/refund/status/email/query failure, half-up, native cart/email change/no compounding, locked evidence/tampering/pending eligibility/paid snapshot, real PDF/preview bytes, sealed manifest/PDF hashes dan admin private images. WordPress/Woo/SQL/mail adalah doubles. Prerequisite17 checkout checks bukan assertion tambahan berulang.
- `plugins/sanro-studio/tests/repeat-route-security.php`: **11 checks PASS**; defaultOFF, independent opt-ins, POST/nonce boundary dan owner proof terpisah. Tidak ada real SMTP/order query.
- `tests/studio-bootstrap-offline.php`: PASS setelah module/hook baru.
- Changed-source lint: **16 PHP,1 JS,4 Python PASS**; git diff --check PASS. PHP lokal8.4.24 +GD/FreeType/ctype; tidak mengklaim PHP7.4/FPM Hostinger.
- Kandidat ZIP dibuat sesudah clean final commit, semua member/hash/source marker diverifikasi; exact archiveSHA berada di manifest dan PR body. Tidak mengulang Cotton690, full runner, A3+render/font, Drive HTTP fixtures lama atau audit menyeluruh.

Command PHP untuk fixture gambar: pilih executable aktual lalu aktifkan GD/ctype bila runtime wrapper `-n`; di workspace Work sesi ini `/workspace/scratch/72df3ab2da51/php-runtime/bin/php`, extensions di sibling `root/usr/lib/php/20240924/{gd,ctype}.so`. Jangan menganggap path ini ada di VPS Hermes; temukan php lokalnya melalui tools. Node untuk syntax buyer.js; Python untuk installer tests.

## Resume dan batas ownership

Fetch PR52 dan cocokkan exact latest HEAD/manifest, baca AGENTS.md jika ada, gunakan clean isolated worktree. Frozen base #50 `45a7917c320403d1bc0d18caf7ee3398a3ecb416`; #51 pricing `e9cb4679247f30223c33e51ddac1b2405dff961c` sudah termasuk, jangan cherry-pick lagi. Tidak menyentuh ref Hermes/Cloud. Handoff runtime worker hanya setelah Owner mengotorisasi; jangan menulis branch PR52 bersamaan dengan Work. Publish nonforce expected-SHA; beda HEAD harus direkonsiliasi dahulu. Tidak ada klaim global AI session lock.

Reproduce ZIP dari clean checkpoint:

```bash
python tools/build-studio-candidate.py --out /tmp/studio-audit-candidate
```

Installer: default PLAN_ONLY; future apply/recovery harus exact manifest/source/archive/existing digest + Owner approval dan actual staging/PHP-FPM/flags evidence. `--recover` memakai manifest transaksi terputus yang sama; mempertahankan nonce/activation/config/order restrictions, bukan bypass. Jangan membuat approval file sendiri. Jangan mengulang PR50 reinstall/seed/activate script. Semua candidate source refs baru belum live.

## Paket berikutnya, hanya setelah instruksi

1. **Full Audit source/integration terarah** atas checkpoint ini, tanpa fitur baru. Prioritaskan new journal transaction/ownership/auth/eligibility/order snapshots dan batas kapasitas; lanjut evaluasi V1 lama berdasarkan bukti yang telah ada, bukan mengulang semua PASS.
2. **Satu approval staging terintegrasi** yang menyebut exact final SHA/plugin digest/archiveSHA/URL dan scope synthetic order. Sebelum itu readback staging/config/akses yang tersedia secara read-only; Owner-reported runtime lamae876035b650692b382e0dd293a88c99acb18cfb0 bukan source checkpoint ini. Tidak ada izin deploy/order mutation pada sesi ini.
3. Setelah approval, validasi PHP-FPM/flags/private dirs/source header, lalu HTTP422 dan E2E guest+logged-in editor→draft→email→master/preview→cart→checkout/retry/admin. SMTP, gateway sandbox, Drive OAuth/private permissions/upload recovery dan physical print/RIP tetap bukti nyata terpisah; writer defaultOFF sampai persetujuan khusus.

Blocker yang masih terbuka: HTTP422 source lama/root cause dan E2E source baru; real SMTP/deliverability/FPM/filesystem hosting, gateway/Drive/print validation; pajak/fee/kupon OPEN. Technical history budget3s/100page adalah unavailable/fail-closed, bukan keputusan bisnis. Native variations/Blocks/mixed-design/shaping kompleks ditunda sesuai scope. GO checkpoint siap review/Full Audit yang diperintahkan; NO-GO deploy/merge/production/order writes/writer activation/final release. Audit belum dijalankan.
