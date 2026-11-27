# SANRO Studio V1.1.0 — penyederhanaan editor mobile

27 November 2026 WIB · PR #52 · **96,50% → 96,50% (Δ 0,00 poin)**

Source `ef8df0da9eb1e88641659dc79da8431e9e1157cd` aktif di staging. SHA256 ZIP dan manifest cocok, dua build identik, dan preflight lokal PASS. Gate HTTPS pra dan pascainstalasi PASS, termasuk GET baru. Digest aktif `37bfc7cc5bd83ebb64dd2b69767db2e3ce416c705c81e7ba1895b5df746ee660` cocok dengan 89 file ZIP. Rollback tersedia. SANRO Studio aktif dan transport pemasang sudah dinonaktifkan.

## Implementasi

Pilihan warna memakai lingkaran dari katalog, dengan centang dan outline kuning saat terpilih serta border abu-abu pada warna putih. Kontrol lingkaran besar diganti dengan drag langsung dan tombol Tengah di bawah mockup saat objek dipilih. Empat handle sudut dan handle rotasi menjadi bagian dari garis seleksi tipis. Penggunaan tombol panah saat objek berfokus mempertahankan pergeseran presisi. CSS pinch zoom pada artwork dipertahankan.

Layout mobile merapatkan header, swatch, grid ukuran dan tombol utama. Semua ukuran dari katalog tetap tampil. Navigasi bawah terpisah dari tombol Simpan Desain dan Ke Keranjang. Formula harga, Design ID, kepemilikan dan integrasi WooCommerce tetap memakai alur yang sama.

## Bukti pengujian

| Permukaan | Hasil |
| --- | --- |
| Fresh Guest Buyer, staging desktop 1348 px | **PASS**. Lima swatch dari katalog, semua ukuran hingga 5XL dan tanpa overflow. Resize 60% → 70%, rotasi 21°, drag dan Tengah lulus. Biru tampil pada kedua sisi mockup bawaan dan perubahan warna mempertahankan geometri artwork. |
| Simpan, buka kembali, Cart dan review Checkout staging | **PASS**. ID-00103, gambar, Biru dan 5XL pulih. S × 1 Rp82.000 dan 5XL × 1 Rp105.000, total Rp187.000 sampai review. Keranjang sintetis kosong setelah uji. |
| Chromium lokal 320, 390, 430 dan 1280 px | **PASS** pada fixture yang menjalankan PHP, JS dan CSS kandidat. Delapan warna uji membungkus pada 320 px. Delapan ukuran tampil semua. Pergantian media mockup depan/belakang, handle, proporsi resize, lebar dokumen, serta akses ke Cart setelah scroll lulus. |
| Staging live 320, 390 dan 430 px | **NOT TESTED**. Browser staging yang tersedia tidak menyediakan pengaturan viewport presisi. |
| Pinch dua jari dan sentuhan HP fisik | **PENDING OWNER** pada SHA ini. |
| Regresi terarah | **PASS**. 10 Cart, 31 geometri/simpan, 24 render PHP, kontrak transformasi dan lint. |

**GO untuk staging. NO-GO untuk final mobile acceptance** sebelum viewport live dan sentuhan HP fisik terverifikasi. DTF dan Google Drive OFF. Pengujian checkout berhenti di review.

[Runtime JSON](../../artifacts/studio-v1/mobile-simplification-20261127/runtime-acceptance.json) · [Checksum](../../artifacts/studio-v1/mobile-simplification-20261127/SHA256SUMS) · [Staging desktop](../../artifacts/studio-v1/mobile-simplification-20261127/staging-desktop.jpg) · [Fixture 320](../../artifacts/studio-v1/mobile-simplification-20261127/fixture-320.png) · [Fixture 390](../../artifacts/studio-v1/mobile-simplification-20261127/fixture-390.png) · [Fixture 430](../../artifacts/studio-v1/mobile-simplification-20261127/fixture-430.png) · [Scroll 390](../../artifacts/studio-v1/mobile-simplification-20261127/fixture-390-scroll.png)
