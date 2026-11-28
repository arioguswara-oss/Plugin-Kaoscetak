# SANRO Studio V1.1.0 · acceptance staging 26 November 2026

**NO-GO untuk Final Release dan rekomendasi merge PR #52.** Dua P1 lama lulus di browser. Alur desain buyer mengungkap tiga regresi yang diperbaiki pada source PR. Patch memerlukan verifikasi pada kandidat staging berikutnya.

HEAD sebelum review `851d11bb7a9b2cbee0c30673c07a7d487d47dc29`. Staging aktif V1.1.0 dari `964e428442e21cbae05bd336b81f8aef82cac424`, tree digest `f8243694f32da6cbb54ce7186b32f91e0c78cef828d8616b32d27e9fabbfbf73` menurut mandatory HTTPS gate dan readback [bukti instalasi CLI](studio-lumise-final-correction-20261125.md). Patch hasil acceptance `2bd19b3a734e4edea25142b757dc1155e9954f5d` ada pada branch PR dan belum terpasang. Screenshot langsung dari Cloud Browser [tersedia di artefak](../../artifacts/studio-v1/acceptance-964e428-20261126/).

## 1. Administrator

| Skenario | Hasil staging |
|---|---|
| Simple #53 tambah, aktif, nonaktif, arsip, pulihkan | **PASS.** Asset admin `1.1.0-3a381ae940f5`, nonce terisi tanpa menyalin nilainya ke evidence. Status setelah rangkaian uji kembali Aktif, serupa visibilitas awal legacy. Listing sekarang dipetakan eksplisit. |
| Edit dan simpan mockup simple | **PASS.** #74/#75 serta preview masing-masing muncul kembali setelah simpan. Kedua field dikembalikan kosong setelah uji. |
| Variable #76 tambah, aktif, nonaktif, arsip, pulihkan | **PASS.** Parent WooCommerce sintetis tetap Published dengan Catalog visibility Hidden. Di akhir uji listing Studio kembali Arsip agar fixture tidak tampil ke buyer. |
| Edit matrix variable | **PASS.** Profil SANRO Tunik, area 32×48 cm depan dan 30×40 cm belakang. Enam baris dengan ID 77–82 untuk Putih/Hitam × M/L/XL dan media #74/#75 pada semua baris terbaca lagi setelah simpan. Harga dasar Woo Rp55.000 per variasi dipakai hanya untuk purchasability fixture. |

## 2. Buyer dan harga SANRO

- **PASS simple setelah koreksi kategori lewat UI.** Upload lokal dua PNG sintetis, master privat depan/belakang dan pembacaan ulang binding lulus dengan pilihan A4 di kedua sisi. Hitam, pratinjau M, S × 1 dan M × 2. Quote server Rp97.000 + 2 × Rp100.000 = Rp297.000. Cart WooCommerce menunjukkan ukuran, kuantitas dan total identik. Cart kosong sebelum uji dan kembali kosong setelah kedua baris sintetis dihapus.
- **PARTIAL variable.** Produk aktif muncul di katalog. Hitam, pratinjau XL, M × 1 dan XL × 2, serta gambar mockup tersendiri untuk depan dan belakang terlihat. Estimasi Rp275.000 untuk A5 depan sesuai tarif profil Tunik SANRO (Rp85.000 + 2 × Rp95.000). POST pembuatan draft dibalas HTTP 429. Quote server dan cart variable live **NOT TESTED**. [Kontrak quote, cart dan order variable yang sudah PASS](studio-variable-product-e2e-20261125.md) tetap berlaku sebagai bukti terpisah.
- **FAIL Desain Saya pada staging.** Panel terbuka, tetapi tombol simpan ke koleksi dari desain yang sudah memiliki master gagal `Area preview belum siap.` Koleksi berisi hasil simpan, Buka Desain dan Pesan Lagi belum terverifikasi.

## 3. Self-fix dan uji terarah

1. `buyer.js` semula mendeklarasikan `hasLocalImage` di blok `try` pertama, lalu mengaksesnya sesudah blok berakhir. Runtime menunjukkan `hasLocalImage is not defined` setelah server menyimpan draft. Scope diangkat ke handler dan CTA hanya muncul saat file lokal masih menunggu upload.
2. Ukuran awal default 60% hanya dibatasi area aman visual, sehingga gambar masih bisa melampaui kategori A5. Upload master melewati transfer lalu gagal saat commit geometri. Memilih A4 untuk kedua sisi membuat binding berhasil pada fixture yang sama. Patch membatasi ukuran awal pada kategori yang dipilih tanpa menaikkan harga, mengukur sisi yang benar dan memeriksa lebar dan tinggi gabungan teks/gambar sebelum upload. Pemeriksaan server tetap berlaku.
3. Desain Saya menyembunyikan `.sanro-layout`. Ukur preview dari panel tersebut menghasilkan lebar/tinggi nol. Patch membuka layout hanya selama pengukuran sinkron, kemudian memulihkan status tersembunyi dan posisi sebelumnya lewat `finally`.
4. `draft-rest.php` semula menolak seluruh integer yang WordPress kembalikan sebagai string. Patch memakai normalisasi yang sudah dipakai oleh kuota upload master dan mempertahankan cap 20 serta penolakan nilai rusak. Ini menutup cacat tipe pada source. Nilai penghitung staging dan penyebab spesifik 429 belum dibaca, sehingga respons itu belum dinyatakan pulih.

**PASS lokal** JS syntax dan diff check. Uji Node mencakup 27 assertion geometri/simpan/reveal layout, 10 preview dan master, 15 startup/mapping, 9 handler cart. Regresi kuota PHP sudah ditambahkan dan **NOT RUN** karena PHP CLI tidak tersedia. Chromium lokal tidak berjalan karena binary target tidak tersedia. Evidence portrait dari harness CLI sebelumnya dipertahankan sebagai evidence terpisah.

## 4. Visual dan isolasi tema

Acuan visual [buyer](../../artifacts/studio-v1/1.1.0-ui/review-20261125/references/owner-buyer-concept.jpg) dan [admin](../../artifacts/studio-v1/1.1.0-ui/review-20261125/references/owner-admin-concept.jpg). Benchmark fungsional mengikuti [Lumise Classic](https://lumise.com/docs/classic/). Harga mengikuti profil SANRO.

- **PASS desktop navigasi dan interaksi pointer.** Katalog dan editor menampilkan header hitam, aksi kuning, pencarian/produk, mockup per sisi, D-pad dan navigasi alat. Drag teks mengubah posisi sekitar (22,41; 177,34) → (47,17; 160,47) px. Handle meningkat dari 22 ke 30 px font. Admin menampilkan sidebar gelap dan tab konfigurasi yang sejalan dengan konsep. Katalog staging memakai ilustrasi sintetis dan panel produk lebih panjang daripada konsep. Kartu Desain Saya terisi masih terbuka.
- **PASS isolasi tema pada halaman diperiksa.** Katalog/editor Studio menyembunyikan header/footer JurnalFX. Homepage staging dan halaman cart WooCommerce tetap memakai tema situs. Toolbar admin WordPress dipotong dari bukti gambar.
- **PARTIAL visual mobile.** Probe Cloud Browser CSS 454×312 menunjukkan lebar dokumen 449 px dan handle resize 44 px. Viewport sempit hasil zoom desktop. Portrait aktual staging, drag/resize dengan input sentuh dan pinch dua jari **NOT TESTED**. Handler yang diperiksa memakai satu pointer dan belum ditemukan pinch kanvas. Approval Owner menunggu bukti portrait/touch dan desain koleksi berhasil.

## 5. Progres dan gate

| Tahap ledger | Bobot | Nilai resmi | Status acceptance |
|---|---:|---:|---|
| Fondasi/core | 25% | 95% | Bukti sebelumnya dipakai |
| Backend produk/admin | 10% | 94% | PASS live simple/variable |
| Frontend buyer | 15% | 96% | PARTIAL, variable draft 429 dan patch simpan menunggu runtime |
| Harga/quote | 10% | 96% | PASS live simple, variable estimasi dan kontrak PASS |
| Simpan/ownership/share | 10% | 98% | Master simple PASS, Desain Saya gagal pada source terpasang |
| Checkout/produksi/Drive | 20% | 97% | Bukti checkout/order terdahulu dipakai, Drive live DEFERRED BY OWNER |
| QA/rilis/mobile | 10% | 100% | Nilai historis rubrik dipertahankan, acceptance mobile dan release NO-GO |

Subtotal rubrik 96,35 + saldo historis Owner 0,15 = **96,50% → 96,50%**, delta **0,00** poin. Patch belum memperoleh kredit acceptance baru. Checkout, pembayaran, order existing, DTF dan Drive live tidak dijalankan di putaran ini.

Sebelum rekomendasi merge, verifikasi patch di staging terkait Simpan dan Desain Saya, jalankan tes PHP kuota dan quote/cart variable, lalu selesaikan portrait dan gestur dua jari pada perangkat yang mendukung. Setelah semua gate lulus, minta sign-off visual Owner untuk kandidat yang sama.
