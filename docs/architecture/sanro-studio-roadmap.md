# SANRO Studio — 8 tahap dan progres

Keputusan Owner 18 November 2026: gunakan dua konsep visual yang telah disetujui untuk backend dan buyer; target satu plugin SANRO Studio aktif dengan core berlisensi internal, sehingga kedua plugin editor lama dapat dinonaktifkan setelah QA. Harga resmi Kaoscetak dipertahankan. Screenshot hasil implementasi sebenarnya harus ditampilkan sebelum instalasi produksi. Konsep visual tidak dianggap screenshot implementasi.

Persentase di bawah adalah estimasi pekerjaan berbobot, bukan persentase tes atau bukti kesiapan rilis.

| Tahap | Hasil yang dituju | Bobot | Penyelesaian tahap |
| --- | --- | ---: | ---: |
| 1 | Kebutuhan dan tampilan backend/buyer disepakati | 10% | 100% |
| 2 | Fondasi dan core internal tanpa plugin editor lama aktif | 25% | 40% |
| 3 | Backend produk, mockup, warna, size, dan area cetak | 10% | 0% |
| 4 | Frontend buyer sesuai konsep | 15% | 0% |
| 5 | Integrasi harga Kaoscetak dan validasi server | 10% | 0% |
| 6 | Simpan/buka desain, kepemilikan, share, pesan ulang | 10% | 0% |
| 7 | Cart, checkout, preview pesanan, Drive dan panduan produksi | 10% | 0% |
| 8 | QA, optimasi HP, perpindahan data, screenshot dan paket instalasi | 10% | 0% |

**Total estimasi: 20%** (`10% × 100% + 25% × 40%`). Sebelumnya sekitar 13%, dengan tahap 2 baru 10%. Source KLD yang sudah tersedia tetap dipakai sebagai baseline tahap integrasi; tidak dibangun ulang tanpa alasan.

## Tahap aktif: 2

Tersedia: bootstrap plugin, status/pengaturan admin, profil pratinjau, pemuat runtime internal, pemeriksaan fingerprint, kompatibilitas tanpa double-load, penyiapan arsip privat tanpa metadata Git, pemisahan callback aktivasi vendor, serta pengujian bootstrap core asli dengan plugin lama tidak dimuat.

Belum selesai: WordPress `init`/session/database lengkap, render dan operasi kanvas dengan hanya SANRO Studio aktif, paket instalasi privat yang tervalidasi, dan kesiapan integrasi modul KLD. Bootstrap dengan stub WordPress tidak sama dengan runtime penuh. Belum ada penggantian atau deaktivasi plugin produksi.

Langkah teknis berikutnya: jalankan core internal pada WordPress/WooCommerce/Flatsome terisolasi dengan data sintetis/salinan dan uji sesi serta editor; lanjutkan penyesuaian backend/buyer sesuai konsep. Keterbatasan lingkungan tes tidak menghentikan adapter dan pengujian source yang masih bisa dikerjakan.

Setiap laporan memuat tahap aktif, progres tahap/total, tindakan nyata, PASS/FAIL, commit/PR dan sisa pekerjaan. GO untuk melanjutkan pengembangan; NO-GO untuk menganggap plugin final atau menonaktifkan editor produksi sebelum integrasi dan QA lulus.
