# SANRO Studio · Final staging takeover V1.1.0

27 November 2026 WIB · PR #52 · Baseline resmi **96,50% → 96,50%**, delta **0,00 poin**.

## Instalasi yang terverifikasi

- Source `d6defee4f14f87274e24242b8732796460010f82` dipasang satu kali melalui transport WordPress Administrator. Sebelum instalasi, transport lama nonaktif dan binding-nya berbeda. Transport diganti dengan paket yang checksum-nya cocok. Gate mencocokkan predecessor `274df9212cf63dc9df128bc9cde507418c93a4a3b3a97c09c48b1cbc602cd0fc` dan jurnal sebelum aksi instalasi dengan filesystem lock.
- Gate HTTPS sebelum instalasi dan GET baru sesudah instalasi **PASS** pada PHP 8.3.37 / LiteSpeed. Transaksi committed, private storage PASS, rollback tersedia dan writer, DTF, Drive serta GC OFF. Digest tree terpasang **`20f5ab23e2852957d9f9b894ca3e0e3bb664fd28ebb20e87abcc7182e1a05f7d`** sama dengan 89 file terverifikasi dari ZIP. Transport dinonaktifkan dan sesi admin ditutup untuk pengujian guest.
- Saat sesi dipulihkan, WordPress masih menampilkan Studio 1.1.0 aktif, tetapi transport terlihat aktif kembali. Asal perubahan, binding transport dan digest setelah perubahan itu belum terverifikasi. Autentikasi ulang timeout dan browser kembali ke login. Instalasi lanjutan ditahan sampai gate autentikasi menunjukkan state terkini dan instalasi paralel dapat disingkirkan.

## Acceptance yang dijalankan pada d6defee

| Cakupan | Hasil |
|---|---|
| Guest Buyer First, Home, Edit Desain | **PASS**. Dimulai tanpa login dan keranjang kosong. Home menuju beranda staging. Edit Desain membuka ID-00098 dan dua ukuran semula. |
| Editor desktop dan multi-size | **PASS** untuk desain teks dua sisi pada 1348 CSS px, Biru, S × 1 Rp77.000 dan M × 1 Rp80.000. |
| Mini Cart Studio, Cart dan Checkout | **PASS**. Artwork privat termuat pada Mini Cart Studio dan Cart. Checkout guest menampilkan dua baris, desain yang sama dan Rp157.000. Item sintetis dibersihkan dan keranjang kosong. |
| Native WooCommerce Mini Cart | **FAIL** pada d6defee. Dua gambar yang terlihat memuat katalog dari `srcset` meskipun `src` menunjuk artwork privat. |
| Screenshot | Drawer native dan ringkasan Checkout berhasil ditangkap dan ditampilkan selama pengujian. File JPEG sementara hilang ketika sesi dipulihkan. Bukti DOM dicatat dalam JSON. [Screenshot historis](../../artifacts/studio-v1/takeover-695b441-20261126). |
| Keamanan preview | Otorisasi silang sesi dan item asing **PASS** pada tes kontrak PHP. Browser gagal membuka preview setelah penghapusan item, namun kode HTTP spesifik belum terverifikasi pada putaran ini. |
| UI/UX mobile dan checkout campuran | Viewport 320/390/430 persis, upload, pinch dan mixed-cart di batas after-validation **NOT TESTED** pada putaran ini. Bukti admin, order sintetis #56 dan artefak produksi dari checkpoint sebelumnya digunakan kembali. |

## Koreksi dan bukti lokal

- WooCommerce 11.3.1 menggunakan `images[0].thumbnail_srcset` pada drawer native. Commit `c5ca4c442cf9ac1b1bad8c48290f4165b00ddb35` mengganti field tersebut dengan artwork privat yang sama setelah otorisasi sesi. Lint PHP, kontrak Store API object/array, perlindungan sumber input dan otorisasi silang sesi **PASS**. [Sumber WooCommerce 11.3.1](https://github.com/woocommerce/woocommerce/blob/11.3.1/plugins/woocommerce/client/blocks/assets/js/blocks/mini-cart/frontend.ts).
- Concurrency Design ID **PASS** pada MariaDB 11.8.9 embedded/InnoDB tanpa TCP atau Unix socket, memakai allocator PHP kandidat aktual, schema kandidat dan koneksi database terpisah. Delapan alokasi serentak satu UUID menghasilkan satu ID dan satu baris. Dua belas UUID baru unik. Tiga belas pembacaan serentak mengembalikan ID yang sama tanpa mengubah AUTO_INCREMENT (21 → 21).
- Kandidat koreksi `c5ca4c442cf9ac1b1bad8c48290f4165b00ddb35` dibuat dari file runtime yang dicocokkan dengan Git blob SHA pada source tree terpatok. Dua build identik, dan hash anggota ZIP **PASS**. Expected candidate tree digest `0ee4ec4ffffd0067c3fa53fbc6c2803009b5852bb796a871d6d1ec91b3dcb0f5`. [Paket, manifest, binding, log dan bukti](../../artifacts/studio-v1/native-thumbnail-srcset-20261127).

**NO-GO Final Release.** Koreksi drawer belum terverifikasi live. Lanjutkan dari gate HTTPS Administrator setelah binding transport dan ketiadaan instalasi paralel terverifikasi. Ulangi acceptance yang terdampak. Uji berhenti di halaman Checkout. Tidak ada pesanan baru, pembayaran, aktivasi DTF/Drive, perubahan production atau merge.
