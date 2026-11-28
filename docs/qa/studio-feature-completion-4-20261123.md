# SANRO Studio — feature completion4

23 November 2026 WIB. Draft PR52, parent `a143703581e003e116c5550acf1e89a3d9c7e5b9`. Hasil development **83,50 →87,50% (+4,00 poin)** dengan bobot tetap. SHA hasil adalah commit yang membawa laporan ini; ZIP/manifest dari clean HEAD menyediakan identitas exact tanpa circular self-reference. [Ledger resmi tujuh tahap](../ledger/PROGRESS-BASELINE.md).

## Lima pekerjaan dan hasil nyata

1. **Checkout policy IMPLEMENTED, keputusan OPEN dipertahankan.** Semua applied coupon termasuk diskon nol dan fee line termasuk nol ditolak di Studio cart; nonzero/invalid tax/discount juga ditolak. Coupon codes di order terkunci ditolak saat payment/production. Native shipping server/frozen total tetap terpisah dari subtotal desain. Keranjang non-Studio tidak diubah oleh policy guard. Repeat5% belum diaktifkan tanpa verified eligibility; tidak membuat kebijakan tarif pajak/fee/promosi sendiri.
2. **Catalog/pricing IMPLEMENTED.** Dua preset Owner Cotton/Tunic, editor override matriks produk dengan capability boundary dan validasi lengkap sebelum update metadata. Override terikat profile; mismatch/partial/unknown/unsupported native variation fail-closed. Tabel preset dan produk lain tidak ditulis saat update/upgrade. Quote authoritative dan UI estimate memakai profil yang sama; Tunic M/L/XL fixed3/4, revisi quote dan sleeve label diteruskan ke cart/locked order/guide/admin. Product24 staging GET200 mengandung `product-type-simple`, tanpa `variations_form`; tidak mengarang variation map/hoodie/sweatshirt price.
3. **Production recovery IMPLEMENTED.** Persist running run_id/attempt + schedule watchdog sebelum remote mutation; scheduler failure tersimpan blocked sebelum upload. Watchdog memeriksa exact run/lease, menghormati active/replaced worker, mengikuti renewal dengan deadline-specific unique arguments dan memulihkan crash secara bounded. Final interrupted attempt menjadi blocked. Root lease di-refresh pada preflight/item/asset boundary; aksi retry admin scoped nonce/capability hanya queue worker gated yang sama. Memory planner, A3+300DPI RGBA, provenance reuse dan bundled Unicode font dari checkpoint sebelumnya dipertahankan; renderer tidak diubah atau diuji ulang tanpa alasan, tidak mendapat kredit baru. Missing glyph/complex shaping/unsupported geometry tetap ditolak. PDF sleeve label mengikuti saved quote; human print approval tetapfalse.
4. **Persiapan Hostinger IMPLEMENTED sebagian; runtime NOT TESTED.** Inventory mengganti label `not_integrated` yang sudah usang dengan module_loaded/E2E_not_verified dan actual WC gate. Endpoint GET admin capability+nonce melaporkan PHP/SAPI/extensions/memory/gates tanpa private path/secret/order data; REST source header dan ZIP source marker membedakan source baru/lama. Transport preview tidak lagi menyamarkan HTML/non-JSON422 sebagai JSON parsing error. Preflight kandidat plan-only memeriksa URL staging, SHA/member/marker, lint PHP dan menolak target production. Tidak ada apply/reinstall/seed/flag overwrite/deploy. HTTP422 lama **belum dinyatakan selesai**, PHP-FPM/config remote belum dibuktikan. [Paket staging](studio-feature4-staging-packet.md).
5. **Validasi proporsional, commit/PR/ZIP.** Syntax changed source, smoke terkait di bawah dan reproducible clean-HEAD builder. Tidak melakukan full runner/audit, GitHub Actions, browser QA ulang source lama, real Drive API atau perubahan database order. Bukan implementasi90%.

## Bukti dan reproducibility

Lokal PHP8.4.24; GD/FreeType+ctype diaktifkan eksplisit untuk fixture yang memerlukan gambar/font. WordPress/Woo/SQL/scheduler/HTTP adalah doubles; file preview dan PDF bytes nyata. Tidak ada klaim WordPress integration runtime atau signature gateway nyata.

| Pemeriksaan | Hasil / cakupan |
|---|---|
| Profile/policy/admin source | PASS80: seluruh60 kombinasi kategori Tunic, fixed sleeve, revision/override/permission/profile mismatch/simple guard, coupon/fee/tax/discount, private diagnostic/source header/OFF |
| Tunic native checkout | PASS4 + prerequisite17: M1+L2 A5=Rp265.000, locked quote, metadata3/4 kedua item; actual checkout modules, Woo double |
| Watchdog/recovery | PASS11 + consistency34: active/replaced/expired run, bounded final crash, admin permission/payment/lease, unavailable scheduler sebelum network |
| Production package prerequisites | PASS38; canonical checkout17 adalah dependency yang sama, bukan total assertion tambahan setiap dipanggil |
| Shipping frozen order | PASS6 + prerequisite17: native shipping, tamper tax/shipping/fractional rejection, zero coupon order rejection |
| Same-order checkout recovery | PASS20 + prerequisite17; source lifecycle dasar tidak diubah di putaran ini |
| Pricing Cotton/adapter | PASS690 quote +31 native adapter; standalone690 dan690 yang dipanggil adapter adalah suite yang sama, tidak dijumlahkan dua kali |
| Product/config/bootstrap/core | PASS7 product save,26 editor config, bootstrap inventory/default OFF/admin denial, native-core/missing/staging gates dan3 readiness scenarios |
| JS display/transport | PASS9: Tunic estimate/fixed sleeve, old Cotton, safe JSON/non-JSON422/409 error, body secret tidak ditampilkan |
| Candidate validator | PASS synthetic Python suite: valid PLAN_ONLY, production/authority-confusion URL/unsafe flag/tampered hash refused; tidak remote apply |
| Lint/whitespace | PASS pada changed PHP/JS/Python dan git diff --check; lint tool menggunakan PHP8.4, bukan bukti PHP7.4 atau FPM |
| ZIP clean source | Builder dijalankan sesudah final commit; semua member hashes/marker dan dua build byte-identical harus PASS sebelum kandidat dilaporkan |
| Hostinger new-source E2E / old HTTP422 root cause | NOT TESTED / NOT VERIFIED |
| Real WC gateway settlement, Drive OAuth/uploads, physical DTF/RIP | NOT TESTED; generator/Drive default OFF |

Satu pemanggilan awal `native-readiness-gates.php` tanpa argumen ditolak harness (`Unknown scenario`, exit2). Diperbaiki dengan tiga scenario resmi; semuanya PASS. Fixture gagal awal profile menggunakan nama meta safe-area salah; diperbaiki ke `_sanro_studio_safe_areas`, kemudian matrix PASS. Tidak ada FAIL source yang tersisa pada smoke yang dijalankan. Riwayat ini bukan tambahan fitur/progres.

Jalankan file di `plugins/sanro-studio/tests/feature4-{pricing-policy.php,tunic-checkout.php,watchdog.php,assets.cjs}`, `checkout-shipping-flow.php`, `checkout-recovery-flow.php`; Node untuk `.cjs`, PHP dengan GD/ctype untuk paket. `python tests/test_studio_candidate_preflight.py`. Source legacy quote/adapter: `tests/studio-pricing-woocommerce-offline.php`. Reproduce kandidat melalui tools yang tercantum dalam staging packet; tidak membutuhkan terminal Owner atau akses live.

## Blocker, scope dan langkah berikutnya

- **OPEN Owner:** kebijakan pajak/fee/kupon dan eligibility terverifikasi untuk repeat5%; guarded, bukan diam-diam diputuskan. Konfigurasi produk lain membutuhkan harga yang sah; hoodie/sweatshirt tidak diberi fallback.
- **Runtime:** HTTP422 lama dan PHP-FPM/private-dir/flag Hostinger baru perlu readback source-bound setelah apply yang disetujui. Getter/source header membantu investigasi tetapi bukan root-cause fix yang sudah VERIFIED.
- **Wajib sebelum release:** scoped staging E2E classic/simple/multi-size, real gateway validation, real private Drive account/permission/upload recovery, font/print parity dan Full Audit saat implementasi90%. Kandidat tidak memberi izin apply atau order writes.
- **Enhancement ditunda:** native variations tanpa catalog requirement, Blocks, mixed-design, font shaping kompleks; fail-closed. Tidak memperluas V1 untuk mengejar angka.

Urutan lanjutan: selesaikan policy/eligibility V1 berdasarkan sumber keputusan sah; validasi kandidat pada runtime staging setelah persetujuan terpadu; perbaiki temuan runtime; saat90% hentikan fitur baru dan mulai Full Audit. Tidak membuat angka90 dari jumlah tes atau dokumentasi.

GO review source/kandidat dan development; NO-GO merge/deploy/production/real order/payment mutation/aktivasi generator-Drive/final release. Ownership Work hanya PR52; branch sumber Hermes/Cloud tidak disentuh, sesi eksternal tidak diklaim berhenti.
