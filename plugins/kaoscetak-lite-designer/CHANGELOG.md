# 0.3.2 — kelanjutan 0.3.2-rc.1 dan roadmap tambahan

- Melanjutkan plugin 0.3.2-rc.1 yang terverifikasi aktif di kaoscetak.id; paket rilis biasa diminta Owner. Nama folder dan opsi OAuth/Drive tetap, tanpa reset atau migrasi konfigurasi.
- Undo/Redo 30 metadata states per sisi, pengelompokan input/drag, pemulihan layer dan kategori; tidak menggandakan file, bitmap, atau token dan tidak memundurkan upload Drive.
- Snap/alignment aman dalam cm, tombol tengah dan toolbar kontekstual ramah sentuh; garis bantu tidak masuk thumbnail order.
- DPI efektif memakai piksel master asli dan dimensi cetak aktual; resolusi legacy yang tidak diketahui tidak ditebak dari thumbnail.
- Dukungan mockup foto ringan per produk/warna/sisi dari Media, dengan validasi dimensi, ukuran, MIME dan origin; foto resmi masih perlu disediakan Owner.
- Kontrol baru tersedia untuk admin; pengaktifan pelanggan terpisah dan bawaan OFF. Harga, tipe checkout dan konfigurasi lama dipertahankan.
- Baca UPGRADE-0.3.2.md untuk penggantian plugin dan rollback. Native source tests dicatat terpisah dari runtime versi baru yang belum diuji.

# 0.3.2-rc.1 — DRAFT QA candidate

- PR #12 follow-up: reserve non-autoloaded Drive IDs before creating order folders, shortcuts and guide PDFs; retry the same ID after lost responses and validate exact resource metadata before saving completion markers. Native PHP source evidence is in `docs/qa/milestone-a-native-20261118.md`; actual provider/staging gates remain separate.
- Preserve cart locks through email verification; serialize design writes and replay cart additions safely.
- Revalidate product, stock via WooCommerce, size/quantity/colour, physical bounds and private Drive master at checkout.
- Stage save validation before metadata writes; record actual combined cm dimensions.
- Correct product-specific preview measurements/margins; keep physical layouts invariant across preview sizes.
- Inspect actual source alpha in bounded browser strips; preserve original uploads, chunk/resume/retry and timeout.
- Recover order folders by app property after lost responses; atomic production lock; private folder selection.
- Add real WP/WooCommerce synthetic regression, browser flow and deterministic QA packaging.
- Classic-only RC compatibility for KLD checkout; Blocks/real Drive/Flatsome/physical phone/performance score remain unverified.

## Development / 26 Oktober 2026 — Mockup shading ringan (bukan foto FINAL)
- Preview kaos reguler 24s diberi shading kain, lipatan, jahitan leher/hem, dan beda tampilan depan-belakang lewat Canvas tanpa file master atau request tambahan.
- Katalog warna tetap memakai HEX perkiraan; bentuk hoodie, sweatshirt, tunik, dan lengan panjang tetap di renderer fallback, tidak dipalsukan dengan foto kaos pendek.
- Foto mockup realistis final belum terintegrasi; ilustrasi Canvas yang lebih rinci ini hanya pengganti sementara dan memerlukan pengujian visual HP/WooCommerce.

## 0.3.1-owner-test — Katalog warna & ketersediaan per produk
- Katalog 24 warna dengan swatch digital ringan, hanya empat warna kompatibilitas aktif secara bawaan; warna tambahan diaktifkan admin per produk.
- Preservasi nama warna lama dari v0.3.0, termasuk warna custom di field lama.
- Validasi ketersediaan server saat save, add-cart, dan checkout; warna nonaktif tetap tercatat pada order sebelumnya.
- Mug tetap Putih. Tidak mengubah harga, Drive, atau Lumise.
- Mockup foto realistis belum diintegrasikan; swatch menggunakan nilai HEX perkiraan.

# Changelog — Kaoscetak Lite Designer

## 0.3.0-owner-test — 25 Oktober 2026
- Mendukung produk terpisah **Custom Mug Putih 11 oz** dengan preset Rp25.000/pcs termasuk cetak; harga bawaan dan override harga per produk dapat diedit.
- Menggunakan bidang cetak **18 × 8,5 cm** (maksimum), satu bidang, satu warna **Putih**, satu varian ukuran mug **11 oz**, dan jumlah per pcs. Menolak payload size pakaian/kategori A5–A3+ pada mug.
- Editor mug mempunyai pratinjau vektor ringan dan layer gambar/teks dalam sentimeter; tidak memakai preview kaos reguler.
- Menampilkan label cetak mug pada item WooCommerce, panel pesanan, link berbagi, serta panduan produksi PDF tanpa label size pakaian atau kerah.
- Memperbaiki status gambar dalam mode preview admin ketika Google Drive belum terkoneksi.
- Versi ini tidak mengubah tabel harga kaos, hoodie, sweatshirt atau tunik. Hoodie/sweatshirt tetap tanpa tarif bawaan hingga Owner mengisi.
- Pengujian: 262 assertions PHP terisolasi, browser Chromium mug/kaos, PDF valid & teks diekstrak, PHP/JS lint; pengujian WordPress/WooCommerce/Drive nyata masih gate Owner.

## 0.2.0
- Editor frontend, harga per produk, guest save/share, repeat-order basis, Drive adapter, production PDF, dan audit awal. Belum diklaim FINAL.
