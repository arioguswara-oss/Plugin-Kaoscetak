# SANRO Studio V1 — Full Audit source & self-fix

Pembaruan status setelah audit: H04/H05/M06/M07 kini CLOSED pada kode melalui [native gap closure](studio-native-gap-closure-20261124.md). Baris OPEN di bawah adalah hasil historis pada SHA audit, bukan status ledger terbaru. Audit tidak dijalankan ulang; runtime tetap belum sign-off.

24 November2026 WIB. Gate ledger90,50% terpenuhi sebelum audit; HEAD lokal dan Draft PR52 sama pada `76b3fbbb4c91e0d35b2b33a62bdb2d52e49ec6aa`, working tree awal bersih. Audit dijalankan dalam lima kelompok yang diotorisasi. Source hasil adalah commit yang membawa laporan ini; manifest/PR body memuat exact SHA akhir dan hash paket.

**Keputusan: source self-fix tersedia, tetapi V1 belum boleh dinyatakan selesai/rilis.** Baseline perencanaan90,50% dipertahankan, delta0,00; bukan rasio fitur PASS dan bukan readiness. Gap native di bawah belum tercakup tuntas oleh estimasi historis, sehingga tabel tidak boleh dipakai sendiri untuk GO. Tidak ada kredit untuk audit, tes atau dokumentasi dan tidak ada kalibrasi bobot baru.

## 1. Functional audit terhadap keputusan Owner

Sumber spesifikasi: `docs/business-rules/kaoscetak-lite-designer-v1.md`, `studio-checkout-v1-boundary.md`. Code legacy pada plugin Kaoscetak **tidak** dianggap otomatis tersedia di native Studio.

| Kebutuhan V1 | Hasil source / evidence | Status |
|---|---|---|
| Woo product → Custom Desain → editor native | `buyer.php`, `editor-config.php`, gated entrypoint/picker; existing native bootstrap. Harga kartu kini dari minimum matriks resmi, bukan placeholder catalog Woo | IMPLEMENTED; card fix VERIFIED lokal, runtime baru NOT TESTED |
| Product backend/preset dan override independen | Cotton24s/Tunic3/4/safe areas/print zones/mockup IDs; validation/capability/Woo save boundary; profile-invalid fail-closed | IMPLEMENTED, affected80+2 checks PASS |
| Hoodie/sweatshirt dan harga lain | Harga belum diputuskan; tidak memakai fallback Cotton/Tunic | DECIDED produk terpisah, nominalOPEN; bukan fitur harga selesai |
| Depan/belakang independen, satu gambar + satu blok teks per sisi | PNG/JPEG≤10MB, centimeter geometry, multiline/color/size; parser/REST/current master bindings | SOURCE REVIEW; bukti upload lama tidak dijalankan ulang tanpa perubahan |
| Kategori A5/A4/A3/A3+, maximum/safe area; size tidak mengubah ukuran cetak | Print-area validator, server quote, order snapshot dan raster planner; kategori tidak otomatis turun saat desain diperkecil | IMPLEMENTED source, renderer tidak berubah |
| Multi-size/qty, lengan panjang dan Tunic3/4 | Server profile, cart per-size, locked allocation dan label | IMPLEMENTED; inherited fixtures/prior proof, no new business policy |
| Remove-background opsional dan alpha-aware button | Tidak ada kontrol/provider native pada buyer; source hanya mempertahankan alpha asli | OPEN M06; tidak disebut tersedia karena ada PNG support |
| Preview per warna/size | Warna lima pilihan dan dua reference mockup produk; belum ada mapping foto mockup per-color/per-size lengkap | PARTIAL M07, UI/print parity runtime NOT TESTED |
| Guest + authenticated buyer | Owner token, independent nonce/session binding; prior authenticated numeric-ID/migration checks | IMPLEMENTED; kernel snapshot guard diperkuat, session engine tidak diubah |
| Simpan permanen/Desain Saya dengan email/link aman | Draft native TTL7hari dan proof email ada; indeks koleksi permanen dan reopen lintas browser/email belum ada | HIGH OPEN H04 |
| Share on/off, preview penerima, beli/edit salinan independen | Native API/UI/fork master+preview ownership belum tersedia; legacy implementation tidak diload | HIGH OPEN H04 |
| Hapus dari koleksi tanpa merusak order lama | Tidak ada lifecycle koleksi native; cleanup expired-draft saja, kini melindungi setiap record dengan order bindings | H04 OPEN; H03 FIXED |
| Repeat5% verified-owner email + prior paid history | Challenge/CAS, native paginated order query, per-piece rounding; pricing/net/evidence | IMPLEMENTED;44 checks affected PASS; quota concurrency fixed H02 |
| Official price vs coupon/tax/fee/shipping | Native whole-IDR shipping pinned; unsupported charges/codes tetap ditolak. Repeat adalah server net price, bukan kupon generik | IMPLEMENTED guards; tax/fee/coupon policyOPEN |
| Failed checkout/retry/order pay/payment complete | Saved allocation/amount/quote/preview guard; tidak mengganti harga order terkunci dari live catalog |20 recovery +34 consistency affected PASS; gateway nyata NOT TESTED |
| Admin order: DesignID/qty/price/depan/belakang | Nonce/capability private readers; full canonical snapshot kini dibandingkan dan reader/UI diblokir jika gagal | H01 FIXED,20 targeted security checks PASS |
| Produksi setelah paid ATAU admin-ready | Eligibility terpisah dari human print approval; DTF/Drive flags tetapOFF | SOURCE REVIEW; tidak menjalankan writer nyata |
| DTF300DPI/alpha/physical size/resolution/font/memory | Bounded A3+ planner, provenance/hash and RGBA/PNG validation; unsupported glyph/complex shaping ditolak | IMPLEMENTED source; engine unchanged, RIP/physical/complete font parity NOT TESTED |
| Private Drive order/Produksi/item hierarchy + originals/preview/PDF/manifest | Ancestor/file permission and identity checks, no redirects, guarded token/CRUD, idempotent lookup and receipts | Source/prior mocks;34 affected recovery checks PASS, real OAuth/Drive NOT TESTED |
| Job status/recovery per order | Permanent post-upload integrity failures kini disimpan blocked; numeric aliases berbagi lease, fractional/scientific/overflow IDs ditolak | M02/M03 FIXED;7 focused checks PASS |
| Unicode guide dan artefak satu pesanan | Locked base/net/discount/sleeve/design/previews → PDF/JSON/index hashes |38 package/prerequisite44 repeat checks affected PASS, not real printer proof |
| Installer/upgrade/defaultOFF/ZIP | Exact Owner approval guard, source/archive/target binding, canonical member paths, bounds, fsynced journal/idempotent recovery; tidak reinstall/seed/activate | M01 FIXED; crash10 + security6 + existing preflight1 PASS; actual Hostinger NOT TESTED |

## 2. Security & data integrity — findings

Severity adalah dampak source/operasional yang ditinjau, bukan klaim exploit production. **Critical: tidak ada yang dikonfirmasi dalam review ini**, bukan jaminan seluruh sistem bebas Critical. High5 (3 fixed/2 open), Medium7 (5 fixed/2 open), Low1 fixed.

| ID | Severity | Temuan / perubahan | Status / evidence |
|---|---|---|---|
| H01 | High | Snapshot item hanya diperiksa version/DesignID/copied design_hash; warna/lengan/teks/geometry bisa berbeda dengan locked record sambil label hash tetap. Kini dibandingkan dengan seluruh canonical saved snapshot; private admin reader dan UI menolak payload tidak konsisten. Legacy hanya diberi kompatibilitas sleeve_label yang memang dahulu belum ada | FIXED; six tamper variants + admin fail-closed + original accepted dalam20 checks |
| H02 | High | Mail quota get/set tidak atomik dan IP-only dapat dilewati paralel/berganti IP. Kini insert/CAS leases melindungi IP dan recipient-HMAC quota; failure melepaskan lease, tidak kirim email | FIXED; lock contention/release,8 allowed, recipient bound acrossIPs, malformed sender; actual DB/cache runtime belum diuji |
| H03 | High | Cleanup mempercayai status draft saja; record expired dengan order_id/items/quote yang tersisa dapat dihapus setelah import/corruption. Kini semua binding tersebut ditahan meski status salah | FIXED;9 cleanup assertions termasuk3 protected records; tidak ada record real dihapus |
| H04 | High | Native permanent collection, share/revoke/fork/delete lifecycle belum diimplementasikan; legacy source tidak boleh dihitung sebagai native completion | OPEN functional release blocker; pekerjaan developer, bukan meminta keputusan Owner yang sama lagi |
| H05 | High | Quota upload per-draft dan TTL metadata belum memberi aggregate private-storage capacity control atau GC file orphan. Cleanup tidak boleh diasumsikan menghapus master/preview bytes; order retention masihOPEN | OPEN availability/operations blocker. Jangan menghapus master/order lama untuk menutupi gap; perlu capacity budget + GC aman non-order sebelum writer/public scale |
| M01 | Medium | ZIP member alias seperti includes//a.php, ./ atau trailing slash dapat lolos lalu dinormalisasi saat ekstraksi; archive/manifest dibaca sebelum size guard. Kini path harus canonical, bytes/hash types ketat, bounded regular no-follow reads, decode hanya stored/deflate; shared loader juga dipakai recovery CLI | FIXED;6 new security tests + preflight1 + crash CLI10 PASS |
| M02 | Medium | Setelah perform/upload berhasil, snapshot/receipt error langsung return dan meninggalkan job running; watchdog dapat mengulang kegagalan permanen tanpa alasan | FIXED; persisted blocked/code dan no transient retry |
| M03 | Medium | Numeric aliases pada order ID bisa mendapat root lease berbeda untuk WC order yang sama. Fractional/scientific input juga ditruncate | FIXED; canonical positive integer key, overflow/fractions rejected;7 job regressions combined |
| M04 | Medium | Native product picker menampilkan Woo placeholder price meski checkout memakai matriks SANRO | FIXED; minimum official configured price/“Mulai”, invalid/unavailable matrix memberi label nonnumerik;2 price regressions |
| M05 | Medium | Diagnostic configured=true tidak membuktikan private directory aman/usable, beberapa writer subflags tidak terlihat, marker source bukan attestation file | FIXED read model: safe/read/write/private-permissions booleans, raster/master/legacy writer flags, int64/FreeType, typed build marker dan marker_only label;8 health checks PASS, tidak membocorkan path |
| M06 | Medium | Remove-background native optional control/provider tidak ada | OPEN; implementasi/provider configuration belum tersedia, jangan mengaktifkan service berbayar otomatis |
| M07 | Medium | Mockup mapping per-color/per-size belum lengkap | OPEN; reference front/back ada, complete matrix belum; no false print-preview parity claim |
| L01 | Low | Label/comment masih menyebut text-only/staging walau image bootstrap dan native checkout sudah ada | FIXED label/comment; tidak diberi kredit progres/tes UI palsu |

Tidak menemukan native eval/arbitrary remote URL execution pada alur yang ditinjau: option SQL memakai prepare/table allowlist, Drive target tetap Google API dan redirect0, private image types/name/hash/size constrained, JSON inline config memakai HEX escaping. Ini review source, bukan pentest runtime. Guest nonce tetap CSRF friction, owner token256bit adalah auth per-draft; source UI token di sessionStorage tidak dipakai sebagai bukti pembayaran.

## 3. Checkout/production dan HTTP422

Full saved quote/ID/size/qty/unit/subtotal/shipping/snapshot/repeat evidence diperiksa sebelum pay dan produksi. Canonical snapshot comparison menutup selisih isi item vs locked source, bukan menambahkan repricing. DTF/PDF/Drive tetap memakai paid/admin-ready identity dan local/private source. Unsupported input tetap ditolak; current catalog safe-area drift dapat memblokir produksi dan perlu operator reconciliation, bukan silently mencetak di luar area baru. Tidak mengarang pajak/fee/kupon.

Read-only staging evidence pada sesi ini:
- WordPress plugin inventory `source=live`: SANRO Studio0.1.0-dev dan WooCommerce11.3.0 aktif (7 plugins aktif). Ini bukan proof source SHA atau Classic Checkout; plugin Classic Editor tidak membuktikan Woo checkout classic.
- `GET /wp-json/sanro-studio/v1/products/24/editor-config`: HTTP200, application/json, validtrue, Cotton24s; **X-Sanro-Source tidak ada**. Current runtime SHA tidak terverifikasi.
- `GET /wp-json/`: respons bukan JSON pada percobaan ini; tidak disimpulkan akar masalah provider.
- Remote ability query plugin tidak menawarkan ability; tool hosting PHP tersedia khusus WordPress.com Atomic, tidak digunakan untuk menebak PHP-FPM Hostinger.

Tidak ada literal422 pada callback source native saat ini. Preview/master callbacks memetakan400/403/404/409/410 dan memakai short current-bound read routes. 422 historis mungkin berasal dari source lama, plugin/proxy/security layer atau payload path; **semua ini hipotesis, belum root cause**. Tidak mereplay owner secret atau menulis draft/order baru. Karena kandidat tidak di-deploy, tidak boleh mengklaim runtime422 selesai.

## 4. Installer/release readiness

Review57 native PHP files,325 named functions: tidak ada duplicate native function atau missing static __DIR__ requires. Native PHP tidak ditemukan menggunakan fungsi/konstruksi PHP8-only yang diperiksa; ini bukan eksekusiPHP7.4. Local runtime8.4.24/GD/FreeType/ctype saja. WordPress/Woo versions/PHP-FPM nyata, host FS fsync/rename semantics, server limits/SMTP/cache/cron belum diukur pada kandidat.

Installer hanya plan sampai exact approval; metadata source marker adalah deklarasi build, installer post-write hashes adalah bukti byte saat apply, bukan remote attestation berkelanjutan. SHA binding tidak menggantikan Owner authorization. Journal recovery tetap menggunakan manifest transaksi yang sama; bounded loader sekarang berlaku juga saat target hilang. Tidak ada perubahan activation/config/products/orders, apply/rollback hosting atau worker invocation.

ZIP plugin-only mencakup font/license/assets/native modules dan build identity; tests/config/logs/symlink tidak diikutkan. Tool installer/transaction berada di repo pinned commit, bukan di ZIP plugin. Kandidat dari clean final commit dibangun dua kali dan diverifikasi sebelum dilaporkan; archive/manifest SHA256 dan source/plugin digest dicatat di PR body + SHA256SUMS eksternal.

## 5. Self-fix verification dan batas bukti

| Check | Hasil |
|---|---|
| audit-integrity-security.php | PASS20; prerequisite repeat44/checkout17 tidak dijumlahkan ulang |
| audit-job-terminal-state.php | PASS7; whole-order errors + canonical lease IDs; no HTTP |
| audit-storage-health.php | PASS8; actual temp filesystem termasuk symlink/perms/malformed path; no WP config mutation |
| draft-cleanup.php | PASS9; actual SQL doubles, no live deletion |
| test_studio_candidate_security.py | PASS6 tests; normalized aliases/types/sparse oversize/symlink; local temp only |
| test_studio_candidate_preflight.py | PASS1 suite with valid/production/wrong flags/hash refusal |
| test_studio_installer_crash.py | PASS10 process/CLI recovery tests; rerun karena loader/recovery entry changed |
| completion-consistency.php | PASS34 plus nested package38/checkout17; rerun karena changed snapshot/admin/job boundary |
| checkout-recovery-flow.php | PASS20 plus shared prerequisite17; same trust-boundary change |
| feature4-pricing-policy.php | PASS80 existing affected +2 card regression checks |
| studio-bootstrap-offline.php | PASS; full native load/gates after new lease dependency |
| Changed-source lint | PHP15/JS1/Python4 PASS; git diff --check PASS |
| Real hosting/new-source/browser E2E/SMTP/gateway/Drive/print | NOT TESTED; no inference from mocks |

Awal regression admin gagal karena guard ditempatkan pada reader yang berbeda; self-fix memisahkan reader return data dari UI notice dan hasil akhirPASS. Environment sempat terputus sebelum command patch berjalan, lalu pulih; HEAD diverifikasi tetap bersih sebelum melanjutkan. Tidak ada hasil disconnected command diklaim berhasil.

Audit source/self-fix lima kelompok telah dijalankan; **full runtime sign-off masih BLOCKED**, bukan “semua V1 PASS”. Tidak menjalankan ulang Cotton690, unchanged A3+/font render atau full CI hanya untuk menaikkan angka. Tidak ada confirmed source FAIL yang tersisa pada check yang dijalankan; H04/H05/M06/M07 tetap terbuka.

## Keputusan dan paket lanjut

GO source self-fix review dan read-only preflight; **NO-GO final release dan instalasi tanpa approval/config proof**. H04/H05 harus ditutup/dibatasi secara eksplisit sebelum klaim seluruh V1 siap. Paket E2E Hostinger di `studio-audit-staging-packet-20261124.md` adalah persiapan, bukan izin. No deploy/merge/production/order database changes; generator/Drive defaultOFF; Codex/Hermes tidak dijalankan. Checkpoint Hermes memuat next work tepat dari final HEAD tanpa mengulang PASS.
