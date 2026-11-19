# Prinsip arsitektur

- WordPress, WooCommerce dan Flatsome tetap menjadi sistem penjualan utama.
- Arah pengembangan terbaru adalah **SANRO Studio dengan core berlisensi sebagai komponen internal**, sesuai keputusan Owner 18 November 2026: satu plugin editor aktif, sehingga kedua plugin lama nantinya dapat dinonaktifkan setelah QA. Lihat [keputusan core](sanro-studio-lumise-core.md) dan [roadmap/progres](sanro-studio-roadmap.md). KLD 0.3.2 tetap menjadi baseline harga dan integrasi yang sudah tersedia.
- SANRO Studio menyederhanakan komponen editor dan membatasi pemuatan aset ke halaman yang membutuhkan. Menyembunyikan menu saja belum membuktikan pengurangan beban.
- Browser mengerjakan preview ringan. Hosting mengelola metadata, thumbnails, nonce/otorisasi dan pemeriksaan server-side.
- Upload master desain PNG/JPEG maksimum 10 MB per sisi ke Google Drive **melalui alur aman**; jangan bocorkan OAuth credential atau membuat file pelanggan publik.
- Metode upload langsung yang aman belum dianggap VERIFIED sebelum pengujian integrasi; jangan mengandalkan asumsi bahwa WordPress dapat memberi token Drive pada browser pelanggan tanpa risiko.
- Checkout publik ditutup bila master file belum valid disimpan; preview admin dapat berjalan tanpa koneksi Drive.
- Folder order Drive dibuat sesudah pembayaran terkonfirmasi ATAU order berstatus siap produksi. Retry tidak boleh membuat duplikat.
- Tidak memproses/rasterisasi file DTF resolusi tinggi di hosting. Pekerja produksi menyiapkan file cetak di komputer produksi.
- Plugin lain di repository harus tetap terisolasi, dengan nama/namespace, dependensi, pengujian, dan rilisnya sendiri.
