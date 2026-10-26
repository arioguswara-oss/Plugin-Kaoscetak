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
