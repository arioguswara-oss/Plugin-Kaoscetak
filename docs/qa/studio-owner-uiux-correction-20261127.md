# SANRO Studio Owner UI/UX correction — 27 November 2026

## Runtime staging — `0d0ea5f` aktif

Paket dari HEAD `7718fc60b7cb68bc799cde26dcd27a7f9fbc19e0` dipasang melalui WordPress staging. SHA256 ZIP, manifest dan semua 89 file cocok. Gate HTTPS pra-instalasi membaca predecessor `c5ca4c4` dan jurnal committed. Satu transaksi terkunci memasang kandidat. Gate HTTPS GET baru sesudah instalasi memverifikasi digest `53afcc58aba2e51703ad3fa7a9aec004c748cf992d899bc005f57f70264c5cf1`, private roots PASS, rollback tersedia dan semua writer OFF. WordPress menunjukkan Studio 1.1.0 aktif, WooCommerce 11.3.1 aktif dan transport kembali nonaktif.

| Acceptance | Hasil |
|---|---|
| Handle sudut, rotasi dan drag | **PASS Chrome desktop 1348 × 936 CSS px.** Ukuran 60% → 91% → 52%, rotasi 0° → 32°, posisi berubah saat drag. |
| Kontrol gambar | **PASS.** Slider ukuran dan rotasi tersembunyi. Toleransi latar muncul setelah Hapus Background dipilih. |
| Simpan dan buka kembali | **PASS fungsional.** Status Tersimpan · ID-00101. Edit Desain mengembalikan gambar, rotasi 32°, posisi, ukuran 52% dan kuantitas awal. |
| Prasyarat Cart | **PASS.** Cart memicu simpan otomatis dan menahan proses saat master privat belum siap. Unggah PNG dan penyimpanan terverifikasi sebelum Cart berhasil. Validator menolak gambar yang melewati area cetak dan menerima setelah dikecilkan. |
| Mini Cart, Cart dan review Checkout | **PASS.** Guest mulai dengan 0 item. Dua artwork privat dirender di drawer WooCommerce native. ID-00101, Biru, S × 1 Rp82.000 dan M × 1 Rp85.000 konsisten sampai total Rp167.000. Dua baris QA dibersihkan. |
| Touch dan pinch | **NOT TESTED pada kandidat ini.** Bukti Owner Android fisik dari kandidat sebelumnya mencakup Home, upload, drag, pinch, dan Cart sampai Checkout. |

**Temuan UX.** Sesudah master privat terverifikasi dan tampilan menulis Tersimpan · ID-00101, pesan Cart dari upaya sebelumnya masih meminta buyer mengunggah dan menyimpan master. Klik Cart berikutnya berhasil. Status yang bertentangan ini menahan final acceptance alur simpan.

**96,50% → 96,50% (Δ 0,00 poin). GO staging. NO-GO Final Owner UI/UX Acceptance.** Konfirmasi handle baru, pinch dan alur simpan di Android fisik masih terbuka. Bukti build, isolasi sesi, thumbnail dan concurrency lama dipakai ulang sesuai cakupan. Pengujian berhenti di review Checkout. DTF dan Drive OFF.

[JSON runtime](../../artifacts/studio-v1/owner-uiux-correction-20261127/runtime-acceptance.json) · [Editor](../../artifacts/studio-v1/owner-uiux-correction-20261127/editor-handles.jpg) · [Cart ditahan](../../artifacts/studio-v1/owner-uiux-correction-20261127/cart-waits-for-master.jpg) · [Status simpan](../../artifacts/studio-v1/owner-uiux-correction-20261127/editor-saved.jpg) · [Native Mini Cart](../../artifacts/studio-v1/owner-uiux-correction-20261127/native-mini-cart.jpg) · [Checkout](../../artifacts/studio-v1/owner-uiux-correction-20261127/checkout-review.jpg).

## Scope and source

- Repository: `arioguswara-oss/Plugin-Kaoscetak`, PR #52, integration branch.
- GitHub checkpoint: `0bb6d86a3f2a0edea5f39d26ed72eedf75a064b3`.
- The preceding staging run used source `c5ca4c4`.
- Candidate version: 1.1.0. DTF, Google Drive and production remain out of scope.

## Implementation

- Added four selected-object corner resize handles and a rotation handle above the selection. Resizing uses a proportional scale and existing size input events; rotation uses existing rotation and bounds validation. Drag/pinch code and `touch-action` on the artwork are unchanged.
- Removed visible image size/rotation sliders and duplicated center/delete controls from the image panel. The panel has one “Unggah Gambar” entry point; the private production master upload remains separately named and available.
- The background tolerance control appears after “Hapus Background” is selected. Removal runs only after the explicit apply action; restore-original remains available.
- Save UI reports unsaved/saving/saved/error, displays the public Design ID, guards repeated saves, and preserves editor state on error. Cart invokes the existing save path for dirty drafts. When a new local image needs private master binding, Cart waits for the master upload step.
- No backend, ownership, collection-authentication, relationship, ID-allocation or private-file policy changes were made.

## Targeted validation

- PHP lint: `buyer.php`, `pricing-integration.php`, `public-design-id.php` — PASS.
- JavaScript syntax: `buyer.js`, `studio-ui.js`, transform contract test — PASS.
- `studio-ui-transform-node.cjs`: corner/rotation UI contract, hidden image sliders, print bounds and estimator — PASS.
- `buyer-image-preview-node.cjs`: 10 private master/preview assertions — PASS.
- `buyer-print-zone-draft-node.cjs`: 27 side-specific geometry/draft/save assertions — PASS.
- `buyer-cart-handler-node.cjs`: 9 delegated Cart assertions, including save-before-Cart — PASS.
- `public-design-id.php`: stable UUID mapping, monotonic allocation, retry uniqueness and growth — PASS (existing targeted test reused).
- `store-api-mini-cart-preview.php`: session-bound private artwork only — PASS (existing targeted security smoke reused).
- `test_sanro_buyer_render.php`: 23 assertions — PASS.
- Guest checkout and artwork/thumb evidence from `c5ca4c4`, plus Owner Android PASS for Home/upload/drag/pinch/Mini Cart/Cart/Checkout, reused for unchanged paths.

The new direct handles have source/geometry contract coverage, not a fresh rendered-device test. Fresh resize/rotation touch behavior, save/edit/reopen UI, ID continuity through a new revised checkout, and post-transform dimensions/pricing on staging remain **NOT TESTED** pending installation and Guest Buyer acceptance. No new order or payment was submitted.

## Candidate and pre-install evidence

Candidate source SHA: `0d0ea5f1795c5148e763d88281e8964f6672fbce` (PR source commit). Candidate plugin digest: `b886459711041d0e081b4e53b471e015192a5c7848aa6546793eb1e6bb38be36`. ZIP SHA256: `1690c709da2e2921e036bdefb7bfcd2442fe0eb3b080839437119fba511c3fd4`. Manifest SHA256: `f034d76e8bea7830d48f643562418b325798bd792348545d5b59f660d4d4e6a7`. Two clean builds produced identical ZIP and manifest hashes. Official local preflight: **PASS PLAN_ONLY**, local PHP 8.5.4 CLI lint PASS; remote runtime, writer flags and runtime E2E **NOT TESTED**. At packaging time the staging predecessor was `c5ca4c4`, digest `0ee4ec4ffffd0067c3fa53fbc6c2803009b5852bb796a871d6d1ec91b3dcb0f5`.

Baseline remains **96.50%** (delta 0.00); no progress credit was claimed for unverified runtime behavior.
