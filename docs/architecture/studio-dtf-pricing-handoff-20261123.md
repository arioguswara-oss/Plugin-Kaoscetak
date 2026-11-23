# SANRO Studio: kontrak integrasi pricing DTF (handoff Codex Cloud)

Status 2026-11-23: **DECIDED**, belum diimplementasikan sebagai pricing engine DTF. Kepemilikan implementasi harga DTF dan CI berada pada Codex Cloud. Branch Hermes tidak mengubah tabel harga atau branch Codex Cloud.

## Batas integrasi yang sudah ada

- Admin menyimpan profil produk di `_sanro_studio_pricing_profile`; saat ini hanya `cotton_combed_24s` yang diterima oleh `includes/product-pricing.php`. Produk tanpa profil valid gagal tertutup di `includes/editor-config.php` dan `includes/quote.php`.
- Draft tersimpan memuat `product_id`, desain depan/belakang, `quantities` per ukuran, dan `long_sleeve`. `sanro_studio_rest_quote_draft()` membaca draft dengan owner token, memvalidasi produk, geometri, dan master gambar yang terikat, lalu memanggil `sanro_studio_quote_size_quantities()`.
- Kontrak keluaran quote: `valid: true`, `currency: IDR`, `items: {SIZE: {quantity, unit_price, subtotal}}`, `total_quantity`, dan `total_price`; semua jumlah harga adalah integer rupiah. Kegagalan memakai `valid: false` dan kode `error`.
- `sanro_studio_prepare_cart_design_contract()` dan `sanro_studio_prepare_size_cart_lines()` memerlukan quote server tersebut. Cart menggunakan harga **per unit per ukuran**, bukan total desain sebagai harga unit. `includes/cart-adapter.php` menandatangani snapshot dan memverifikasi ulang harga pada reload. `includes/checkout-bridge.php` memverifikasi total dan kepemilikan ulang sebelum order.

## Antarmuka yang perlu disepakati dengan Codex Cloud

1. Tambahkan profil DTF eksplisit pada validasi admin dan editor config, lalu pilih kalkulator server sesuai profil produk. Jangan ubah arti atau nilai profil `cotton_combed_24s`.
2. Kalkulator DTF menerima produk terkonfigurasi, desain yang sudah divalidasi, kuantitas integer per ukuran, dan varian lengan boolean. Kategori/luas cetak harus berasal dari geometri tervalidasi pada server; harga dari browser diabaikan.
3. Kembalikan bentuk quote yang sama dengan kontrak di atas. Tolak profil/ukuran/kategori tidak dikenal, kuantitas nol atau melewati batas, overflow, dan perubahan desain setelah quote. Pembulatan harus deterministik dalam rupiah integer.
4. Pertahankan pemeriksaan quote baru dan signature cart pada commit, reload session, dan checkout; jangan memakai estimasi `assets/estimate.js` sebagai otoritas. Quote yang berubah harus membatalkan cart lama.
5. Tambahkan kasus parity PHP/JS untuk *estimasi UI* saja; PHP tetap otoritatif. Uji dua sisi cetak, ukuran per varian, lengan, harga negatif/overflow, dan draft bergambar tanpa master terikat.

Semua flag buyer, draft, cart, checkout, generator DTF, dan Google Drive tetap default OFF. Aktivasi staging memerlukan persetujuan dan pemeriksaan terpisah. Tidak ada perubahan production atau database live dalam handoff ini.
