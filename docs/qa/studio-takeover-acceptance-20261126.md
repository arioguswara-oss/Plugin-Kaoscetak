# SANRO Studio · Takeover staging dan acceptance V1.1.0

26 November 2026 WIB · PR #52 · HEAD yang diperiksa `bbb7c73` · baseline 96,50%

## Hasil akhir takeover

Kandidat `695b441` **terpasang dan aktif**. Gate HTTPS pra-instalasi dan pascainstalasi PASS melalui sesi resmi WordPress Administrator. Digest tree `26aeb020ff96684e1166fe1bd7c09d5cac4abd25c25660878652b54b9a31035e` cocok dengan ZIP. Rollback tetap tersedia, private roots PASS, writer dan GC OFF. Tidak ada kredensial baru. Kedua transport staging sementara sudah dihapus.

Percobaan pertama berhenti sebelum rename dengan `committed_tree_changed`. Jurnal web lama mencatat tree yang disupersesi oleh instalasi CLI sebelumnya. Koreksi installer `e25671f` tetap membutuhkan digest predecessor yang dipatok, jurnal independen atas predecessor aktif, backup lama yang valid, nonce, capability dan filesystem lock. Sembilan skenario filesystem dan recovery PASS, termasuk penolakan ketika bukti eksternal atau backup yang valid hilang. Kandidat aplikasi asli dipakai ulang.

| Acceptance | Hasil |
| --- | --- |
| Guest Buyer First | **PASS**. Sesi baru tanpa login dan badge kosong sebelum uji. |
| Home dan perubahan belum disimpan | **PASS**. Home katalog menuju Beranda. Batal pada prompt mempertahankan editor yang berubah. |
| Editor desktop | **PASS**. Toggle depan-belakang, teks, pointer drag dan resize, panel Desain Saya serta dua ukuran. |
| Layout | **PASS** untuk 1363, 454 dan 340 CSS px. Dua viewport sempit berasal dari zoom browser. 320/390/430 persis, touch/pinch dan upload end-to-end belum terverifikasi pada kandidat aktif. File chooser Cloud Browser timeout. Kebijakan Chromium memblokir DevTools device emulation. |
| Studio Mini Cart, Cart, Checkout | **PASS**. Artwork privat termuat di Mini Cart Studio dan Cart. ID-00001, Biru, S × 1 Rp82.000, M × 1 Rp85.000, total Rp167.000 konsisten hingga Checkout. Edit Desain membuka ID yang sama. Lanjut Belanja kembali ke katalog. Item sintetis dibersihkan. |
| Native WooCommerce drawer | **FAIL live pada 695b441**. Permintaan Store API guest mengembalikan `src` dan `thumbnail` privat dengan `srcset` katalog tersisa. Drawer menampilkan gambar katalog. `0a15c10` menghapus `srcset` dan `sizes` setelah otorisasi preview lulus. Tes bentuk object/array, cross-session dan label legacy PASS. Kandidat patch reproducible, live retest masih diperlukan. |
| Keamanan preview | Tes cross-session lokal PASS. URL preview artwork sintetis yang sama mengembalikan HTTP 404 tanpa cookie setelah baris uji dihapus. |
| Admin | **PASS** tampilan top-level menu, katalog, daftar pesanan, dan setup Drive OFF. Uji CRUD/mapping dan order preview menggunakan bukti sebelumnya. |
| Concurrency Design ID | **NOT TESTED**. Runtime SQL terisolasi belum tersedia. Tes mapping dan idempotensi dipakai sesuai cakupan. |

Perbandingan fungsional memakai alur produk, editor serta koleksi dari Lumise Classic dan acuan visual Owner. **Baseline 96,50% → 96,50% (delta 0,00 poin). GO untuk review staging kandidat terpasang. NO-GO Final Release selama artwork drawer live dan acceptance yang belum selesai.**

[Runtime JSON dan screenshot kandidat](../../artifacts/studio-v1/takeover-695b441-20261126) · [Kandidat koreksi drawer, checksum dan binding](../../artifacts/studio-v1/store-api-drawer-fix-20261127).

## Preflight awal sebelum autentikasi

## Kandidat dan runtime

Checksum ZIP dan manifest kandidat `695b441` cocok. Pemeriksaan ulang CRC, inventaris, hash setiap anggota, marker dan 88 file runtime mengonfirmasi paket yang diterbitkan identik byte-for-byte dengan file plugin pada HEAD `bbb7c73`. Marker paket tetap `695b441`. Kandidat tidak dibangun ulang. [Paket](../../artifacts/studio-v1/home-navigation-20261126).

| Bukti | Status |
| --- | --- |
| Kandidat V1.1.0 `695b441` | **PASS**. ZIP SHA256 `167cc119fe4d86facddb05dfff51a651fd687ee8ae7a671c3251376435747dc9`. Digest tree yang dihitung dari paket `26aeb020ff96684e1166fe1bd7c09d5cac4abd25c25660878652b54b9a31035e`. |
| Staging aktif | **PASS** untuk identifikasi aset predecessor `644739c`. Empat fingerprint aset yang dilayani cocok dengan manifest predecessor. Studio 1.1.0 aktif menurut konektor. Digest terakhir yang terdokumentasi melalui readback adalah `6b39138c7a8312eb8bf8d720bbafdf1221d44dac2f50bbf01b1fd734f4a18f3e`. |
| Pre-install HTTPS, instalasi, post-install HTTPS | **BLOCKED**. Browser mencapai layar login WordPress. Konektor dapat memverifikasi status plugin, tetapi gate installer memerlukan sesi admin dengan capability dan nonce WordPress. |
| Revisi Home | **PASS** 6 pemeriksaan Node untuk status desain bersih, perubahan yang belum disimpan, pembatalan, konfirmasi, simpan dan upload. Bukti live kandidat baru **NOT TESTED**. |
| Design ID concurrency pada database terisolasi | **NOT TESTED**. Tes mapping/idempotensi tersedia di laporan revisi Owner. Runtime SQL terisolasi yang berizin belum tersedia untuk membuktikan concurrency. |

Transport WordPress resmi berhasil dibuat dari paket kandidat yang sudah ada dan terikat ke digest pendahulu. Transport belum diaktifkan dalam checkpoint ini. Tidak ada Application Password, perubahan staging, order, pembayaran, aktivasi DTF/Drive, akses production atau merge.

## Review buyer dan admin

Cloud Browser menggunakan sesi guest baru. Pada desktop 1363 CSS px, katalog, pemilihan produk #53, header, pergantian Depan/Belakang, delapan kontrol ukuran, input upload dan panel Desain Saya terlihat. Tidak ada horizontal overflow pada katalog/editor yang ditinjau. Screenshot guest berisi permukaan buyer predecessor. [Katalog dan editor](../../artifacts/studio-v1/takeover-695b441-20261126).

Alur pemilihan produk, editor dan koleksi dibandingkan dengan dokumentasi fungsional Lumise Classic. Desain asli SANRO tetap mengacu pada konsep Owner. Pengujian viewport kandidat 320, 390, 430 px dan desktop, tombol Home, konfirmasi perubahan, upload, drag/resize/pinch, Design ID pendek, thumbnail kedua Mini Cart, dan Cart → Checkout ditahan sampai kandidat aktif. Bukti guest terdahulu dari `644739c` mencakup viewport 320/390 px dan checkout hingga review. [Lumise Classic](https://lumise.com/docs/classic/) · [Bukti predecessor](studio-final-cart-visual-20261126.md).

Bukti admin katalog, mapping mockup dan area cetak, order preview dan Drive setup digunakan kembali. Pemeriksaan ulang pada kandidat baru **NOT TESTED**. [Admin dan acceptance](studio-release-closure-20261126.md) · [Scope Drive setup](studio-v1-owner-drive-scope-revision.md).

**96,50% → 96,50% (delta 0,00 poin). NO-GO Final Release.** Tahap penentu berikutnya adalah sesi admin WordPress staging yang sah untuk gate, lalu acceptance guest atas build yang telah diverifikasi terpasang.
