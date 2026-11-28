# SANRO Studio · P0 Cart dan Guest Buyer First

26 November 2026 WIB · PR #52 · staging V1.1.0 · source `e36af5addcd16eacab9c8463c6a50bab73b94d89`

## Akar masalah

Kandidat sebelumnya mengarahkan ikon ke `/checkout/`. WooCommerce telah mengatur Cart #16 dan Checkout #17 dengan benar serta mengizinkan guest checkout. Namun Site Visibility memakai **Coming Soon**, khusus halaman toko. Pada request HTTP tanpa cookie, Studio tetap tampil tetapi `/cart/` merender template Coming Soon dengan status 200. Akses ke `/checkout/` dengan sesi kosong membalas 302 ke Cart dan berakhir pada template yang sama. Redirect persis ke homepage dalam laporan Owner belum terekam secara independen.

Ikon bersama Studio sekarang memakai `wc_get_cart_url()` dan memvalidasi halaman Cart, host, port, skema dan fallback ke homepage. WooCommerce staging diatur ke **Live** melalui Site Visibility. Pembayaran, halaman Cart/Checkout, dan opsi guest checkout tidak diubah. Setelah perubahan, request tanpa cookie membuka Cart kosong native. Respons Cart berubah dari `max-age=60` menjadi `no-cache, must-revalidate, max-age=0, private`. LiteSpeed terverifikasi sebagai Web SAPI. Header hit LiteSpeed tidak terbaca pada sampel dan cache tidak terkonfirmasi sebagai penyebab.

## Acceptance guest aktual

Cloud Chromium dimulai dari tab baru setelah membersihkan data untuk origin staging. Administrator tidak digunakan dalam sesi browser berikut. Fixture #53 diberi gambar produk sintetis Media #74 agar verifikasi thumbnail memakai file aktual. Pengujian mencakup fallback WooCommerce pada sesi sebelumnya.

| Langkah | Hasil |
| --- | --- |
| Pilih produk, input teks, simpan desain, masukkan ke keranjang | **PASS**. Sesi guest mulai dengan badge kosong. |
| Ikon badge 1 dari Editor dan Pilih Produk | **PASS**. Tautan dan halaman tujuan `/cart/` berisi satu item desain yang sama. |
| Proceed to checkout | **PASS**. `/checkout/` menampilkan form billing dan ringkasan yang sama tanpa login. Input pengikatan desain checkout terisi. |
| Produk dan harga | **PASS**. Cotton Combed 24s #53, Design ID sama, Biru, M × 1, harga satuan dan subtotal Rp65.000. |
| Thumbnail produk | **PASS**. Katalog, Cart, dan Checkout memuat aset sintetis Media #74 yang sama. |
| Submit, produksi/order, pembayaran, perangkat sentuh | **NOT TESTED** pada putaran ini. |

Item uji dibersihkan. Screenshot hanya memuat produk dan Design ID sintetis. Sesi, token, formulir billing dan data pembayaran tidak ikut dipublikasikan.

## Build, staging, dan keputusan

- **PASS**. Lint PHP, 52 tes bootstrap/URL, label dan thumbnail, 533 pemeriksaan quote, dan 8 kontrak checkout campuran.
- **PASS**. ZIP dan manifest reproducible. Gate sebelum/sesudah pada PHP 8.3.37 LiteSpeed dan private roots lulus. Digest `0941179129bba0c8fbb709a7e6863984c54d4cc4b5545c1dcc81666555eb2048` cocok dengan ZIP. Studio dan WooCommerce aktif. Rollback tersedia, transport sementara dihapus, writer dan Storage GC OFF.
- **96,50% → 96,50%, delta 0,00 poin**. **GO** untuk P0 navigasi guest di staging. **NO-GO Final Release** sampai acceptance perangkat sentuh dan checkout campuran live yang masih terbuka selesai.

[Gate permanen Guest Buyer First](studio-v1-final-release-gate.md) · [Screenshot guest, bukti runtime, dan checksum](../../artifacts/studio-v1/guest-cart-p0-20261126).
