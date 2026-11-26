# SANRO Studio · P0 navigasi ikon keranjang

26 November 2026 WIB · PR #52 · V1.1.0 · source `782271659669581abfe5588294428c564af7c049`

## Temuan dan perubahan

Versi staging sebelumnya menghubungkan ikon header ke `/cart/`. Owner melaporkan klik dengan badge 1 mendarat di homepage. Inspeksi mengonfirmasi tujuan tautan lama. Redirect lama ke homepage belum direproduksi secara independen. Pengaturan WooCommerce menunjukkan halaman Cart #16 dan Checkout #17. Halaman checkout klasik berfungsi tanpa perubahan pengaturan.

Header bersama memakai `wc_get_checkout_url()` setelah memvalidasi halaman checkout. Jika konfigurasi tidak valid atau URL jatuh ke homepage, ikon tampil tanpa tautan. Navigasi memakai tab dan origin yang sama, sehingga sesi WooCommerce dan otorisasi desain dapat diteruskan. Tombol **Ke Keranjang** tetap membuka keranjang.

## Bukti staging

| Sumber klik | Viewport | Badge | Tujuan dan hasil |
| --- | ---: | ---: | --- |
| Editor | 1363 CSS px | 1 | `/checkout/` dan formulir native, PASS |
| Pilih Produk | 454 CSS px | 1 | `/checkout/` dan formulir native, PASS |
| Desain Saya | 454 CSS px | 1 | `/checkout/` dan formulir native, PASS |

Satu item sintetis Cotton Combed 24s #53, Biru, M × 1, harga satuan dan subtotal Rp65.000, terlihat dengan Design ID yang sama di ketiga hasil checkout. Input pengikatan checkout terisi dan tidak ada pesan error checkout yang terlihat. Design ID dan nilai token tidak dicantumkan di bukti. Tidak ada overflow horizontal pada viewport yang diperiksa. Setelah pengujian, item sintetis dihapus dan badge kembali kosong.

Viewport 454 CSS px diuji melalui zoom di Chromium. Kebijakan browser memblokir emulasi perangkat. Sentuhan di perangkat mobile fisik dan submit checkout **NOT TESTED**. Tidak ada order baru, pembayaran, atau perubahan konfigurasi WooCommerce.

## Pemeriksaan dan rilis

- **PASS**. Lint PHP, diff check, 52 pemeriksaan bootstrap cart/link, 22 render buyer, 17 kontrak checkout, 533 pemeriksaan quote dan 8 kontrak checkout campuran.
- **PASS**. ZIP dan manifest reproducible. Instalasi resmi staging committed, rollback disimpan, pre/post gate HTTPS lulus pada PHP 8.3.37 LiteSpeed. Digest isi ZIP cocok dengan digest terpasang. Studio dan WooCommerce aktif. Transport sementara sudah dihapus, private roots lulus, writer dan Storage GC OFF.
- **96,50% → 96,50%, delta 0,00 poin**. GO review staging dan PASS P0 untuk desktop serta viewport sempit. Final Release tetap NO-GO selama acceptance perangkat sentuh dan validasi live checkout campuran belum tuntas. Google Drive live OAuth tetap DEFERRED BY OWNER.

[Evidence runtime, kandidat, dan checksum](../../artifacts/studio-v1/p0-checkout-navigation-20261126).
