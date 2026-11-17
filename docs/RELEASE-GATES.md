# Release gates — Kaoscetak Lite Designer

**Status awal 17 November 2026: NOT VERIFIED.** Centang checklist hanya jika evidence aktual tersedia; bukan karena source telah dibuat atau lint PASS. Sertakan versi plugin, WP, WooCommerce, Flatsome, PHP, browser/perangkat, tanggal, commit, screenshot/log nonrahasia dan hasil PASS/FAIL/BLOCKED untuk setiap gate.

## Gate A — Instalasi & akses editor

- [ ] ZIP aman: struktur plugin benar, tidak ada secret, file debug, database dump, atau source vendor tidak berizin; audit dependencies dan ukuran paket.
- [ ] Instal/aktivasi plugin di WordPress + WooCommerce + Flatsome tanpa fatal/error; menu admin dapat dipakai sesuai role.
- [ ] Dari halaman produk terkonfigurasi, tombol **Custom Desain** membuka **editor frontend fullscreen**, bukan halaman Edit Post.
- [ ] Editor responsif di HP dan desktop; navigasi kembali tidak menghilangkan data tanpa peringatan; tidak mengganggu checkout biasa/Lumise.
- [ ] Tidak ada request/library besar yang dimuat pada halaman produk yang tidak menggunakan editor tanpa alasan terukur.

## Gate B — Gambar, teks, kategori & preview

- [ ] Depan dan belakang independen; maksimal **1 PNG/JPEG <=10 MB + 1 teks multiline** masing-masing. File invalid, spoof MIME, terlalu besar, dan kombinasi berlebih ditolak server.
- [ ] Gambar dapat dipindah dan di-resize proporsional; teks dapat diubah font dasar, warna, ukuran, dan posisi.
- [ ] Kategori A5 14,8×21; A4 21×29,7; A3 29,7×42; A3+ 32×48 cm adalah batas maksimal, bukan kewajiban; tidak auto-turun jika desain diperkecil.
- [ ] Bounding box gabungan gambar + teks di dalam kategori pilihan dan safe print zone admin, sesuai posisi/ukuran aktual cm yang tersimpan.
- [ ] Preview warna/size sesuai produk, tidak mengubah ukuran fisik desain antar-size; validasi ketersediaan warna di server.
- [ ] Alpha channel sebenarnya menentukan status transparansi, bukan ekstensi; remove background opsional, tidak otomatis memproses file berat di hosting.
- [ ] Resolusi source dipertahankan untuk produksi; panduan acuan 300 DPI tidak dijadikan klaim kualitas pasti.

## Gate C — Harga, quantity & order

- [ ] Test matrix Cotton Combed 24s S–5XL × A5/A4/A3/A3+; lengan panjang +Rp10.000; tunik M/L/XL sesuai tarif Owner, per produk dan size.
- [ ] Depan saja = belakang saja pada kategori sama; contoh L A3 + A5 = Rp103.000, A5 + A3 juga Rp103.000; kategori sama = paket 1 sisi + tambahan kategori sama.
- [ ] Quantity terpisah untuk tiap size, satu desain fisik; total cart/checkout/order sesuai harga validasi server, tidak menerima harga dari browser.
- [ ] Diskon repeat order 5%/pcs termasuk order qty satu, diberikan hanya jika kepemilikan dan syarat riwayat terverifikasi.
- [ ] Produk lain punya konfigurasi tarif sendiri; produk tanpa tarif valid tidak boleh memakai harga produk lain secara diam-diam.
- [ ] Mug 11oz yang sudah ada di source diuji sebagai tipe produk tersendiri, tanpa mencampurkan kategori A5–A3+ pakaian; keputusan harga akhir perlu konfirmasi Owner bila berbeda dari spesifikasi resmi.
- [ ] Cart, checkout sesuai mode aktif situs (classic/blocks jika digunakan), pembayaran dan order WooCommerce tidak terganggu; harga tetap benar setelah refresh/retry.

## Gate D — Guest, ownership, share

- [ ] Guest boleh preview, simpan sementara, dan checkout tanpa registrasi/password.
- [ ] Permanen ownership hanya setelah email terverifikasi dan link owner ber-token acak aman.
- [ ] Desain private default; link publik hanya aktif bila pemilik memilih Bagikan; dapat revoke.
- [ ] Penerima share hanya preview, checkout atau fork salinan mandiri; tidak dapat mengubah desain sumber.
- [ ] Token pemilik dan share terpisah, tidak bocor di HTML/response/log publik; permission, nonce, CSRF, enumeration, IDOR dan replay diuji.
- [ ] Kebijakan penghapusan koleksi tidak menghapus arsip produksi/order yang masih wajib disimpan; masa retensi **OPEN**.

## Gate E — File master privat & produksi

- [ ] Upload master asli ke Google Drive privat lewat perantara aman tanpa mengirim token rahasia ke browser; tidak melewatkan file besar ke PHP hosting.
- [ ] Upload status dan retry jelas; kegagalan upload tidak menggantung editor; checkout publik ditolak jika master belum valid.
- [ ] Browser membuat thumbnail/mockup ringan (target 30–50 KB jika tetap jelas), bukan PDF/PNG high-res cetak pada hosting.
- [ ] Folder order baru dibuat setelah paid / siap produksi; retry/event ganda tidak membuat folder duplikat.
- [ ] Detail admin order menampilkan preview depan-belakang, status file, dan link Drive privat **hanya jika tersedia**.
- [ ] PDF panduan berisi nomor order, produk, warna, qty per size, kategori, ukuran cm aktual, posisi gambar dan teks, preview, catatan background; **bukan file cetak DTF siap mesin**.
- [ ] Test koneksi Drive nyata, izin akun, gagal OAuth, quota/network error, retry/idempotensi dan akses privat; tanpa itu status tetap BLOCKED/NOT VERIFIED.

## Gate F — Security, performance & reliability

- [ ] Audit PHP/JS source, validasi semua REST payload dan sanitasi output; role/nonce/ownership/guest-token yang sesuai.
- [ ] Uji upload invalid, oversized, 0-byte, MIME spoof, malicious text, illegal coords, concurrent save, stale token dan duplicate checkout.
- [ ] Tidak mengekspos file master secara publik dari WP atau Drive, tidak menyimpan token OAuth di frontend / repository.
- [ ] Responsif pada HP RAM 4 GB sebagai **target uji**, tanpa freezing saat upload; mobile editor diukur nyata, bukan homepage.
- [ ] Catat Lighthouse/PageSpeed mobile (FCP, LCP, CLS, TBT, JS/transfer) dan perbandingan baseline; target Performance >=90 / LCP <=2,5 s adalah **target aspiratif**, bukan syarat minimum perangkat atau jaminan.
- [ ] Error handling, status, retry dan observability admin bekerja; tidak ada perubahan pada plugin lain atau payment live.
- [ ] CI relevan lulus; failures tidak ditutup hanya dengan melewati/mematikan tes.

## Gate G — Release

- [ ] Changelog dan versi plugin sinkron, ZIP diuji dari paket yang sama (bukan checkout source berbeda).
- [ ] SHA-256 ZIP, daftar file, dependency matrix, bukti QA dan daftar known limitations tersedia.
- [ ] Backup/rollback teruji secara prosedural (cara restore versi lama tersedia); rollback ZIP harus dapat diakses.
- [ ] Owner menyetujui instalasi/update live dan rencana waktu yang aman; snapshot/backup sebelum perubahan.
- [ ] Smoke test setelah instalasi: editor product, cart/checkout, admin order, error logs dan plugin lain tidak regresi.
- [ ] ZIP final diletakkan di Google Drive folder "Plugin wordpress" yang disepakati, dengan versi, changelog dan evidence.
- [ ] Keputusan menonaktifkan Lumise hanya dilakukan terpisah setelah Lite Designer lulus dan Owner eksplisit menyetujui.

## Definisi kelulusan

- **PASS**: tes pada environment yang dinyatakan, evidence lengkap dan konsisten.
- **FAIL**: hasil berbeda dari kriteria; wajib diperbaiki atau diterima sebagai pengecualian oleh Owner yang eksplisit.
- **BLOCKED / NOT RUN**: tidak ada akses/metode/layanan; **bukan PASS**.
- **VERIFIED**: semua gate wajib telah PASS dalam environment yang relevan. Static lint, browser mock dan simulasi saja tidak memenuhi klaim VERIFED live.

Lihat [Roadmap](ROADMAP.md), [Workflow](WORKFLOW.md), [Business rules](business-rules/kaoscetak-lite-designer-v1.md).
