# SANRO Studio V1.1.0 — koreksi akhir Simpan Desain

27 November 2026 WIB · PR #52 · **96,50% → 96,50% (Δ 0,00 poin)**

Koreksi status Simpan Desain dan Ke Keranjang **PASS** di staging. Sebelum mengganti plugin, fingerprint aset browser, versi aktif, dan kemudian digest HTTPS terautentikasi cocok dengan kandidat `0d0ea5f`. Kandidat `c082c7f5ba577f3628e6733f840d81bd0a8f3e97` terpasang melalui transaksi WordPress terkunci. Gate HTTPS pra dan pascainstalasi **PASS**, termasuk GET baru. Digest aktif `7214e0aeb492590015ae0e5f8466bc71c92f3ee09ee31be505af2c6339c0e03c` cocok dengan 89 file ZIP. Rollback tersedia, transport pemasang kembali nonaktif, dan DTF, Drive serta GC OFF.

Buyer melihat **Belum disimpan**, **Menyimpan desain**, **Tersimpan · ID-xxxxx**, atau **Gagal menyimpan desain. Coba lagi.** bila proses gagal. Tombol **Simpan gambar** muncul saat gambar masih perlu disimpan. Sesudah verifikasi, tombol dan pesan Cart lama hilang. Editor mempertahankan validasi server, bukti kepemilikan dan akses file privat sepanjang alur.

| Pemeriksaan | Hasil |
| --- | --- |
| Build dan preflight lokal | **PASS**. Dua ZIP dan manifest identik. Preflight resmi PLAN_ONLY dan lint PHP 8.4.28 PASS. |
| Regresi terarah | **PASS**. 10 Cart, 31 geometri/simpan, 10 pratinjau privat, 24 + 24 render PHP, kontrak transformasi, Design ID dan isolasi thumbnail. |
| Guest baru, simpan gambar | **PASS**. Cart tertahan saat gambar belum siap. Setelah Simpan gambar, editor menampilkan Tersimpan · ID-00102 dengan pesan Cart kosong. Edit Desain memulihkan artwork dan ID yang sama. |
| Cart, drawer dan Checkout | **PASS**. Dua thumbnail privat dirender. Biru, S × 1 Rp72.000, M × 1 Rp75.000 dan total Rp147.000 sama hingga review. Keranjang sintetis dibersihkan. |
| Android fisik pada kandidat ini | **PENDING OWNER**. Bukti fisik Owner sebelumnya tetap historis. |

Bukti order sintetis #56 dan concurrency MariaDB terisolasi dipakai ulang. Review Checkout berakhir sebelum submit. **GO staging untuk uji Owner.** Final acceptance perangkat menunggu pengujian HP fisik.

[JSON runtime](../../artifacts/studio-v1/final-save-ux-20261127/runtime-acceptance.json) · [Checksum](../../artifacts/studio-v1/final-save-ux-20261127/SHA256SUMS) · [Status simpan](../../artifacts/studio-v1/final-save-ux-20261127/verified.jpg) · [Mini Cart](../../artifacts/studio-v1/final-save-ux-20261127/mini-cart.jpg)
