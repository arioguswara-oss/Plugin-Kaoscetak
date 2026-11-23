# Aturan bisnis DTF — ekstrak sumber yang disepakati

Tanggal 23 November 2026 WIB. Ledger ini tidak membuat tarif baru.

Sumber keputusan: `docs/business-rules/kaoscetak-lite-designer-v1.md` pada main `0760f565e8b1ef068c706748022d2f8b5539837a` (blob `5a142ead51c85ee29a7c29eeffbde47c5758e4cc`).
Pembanding implementasi: `plugins/sanro-studio/includes/pricing.php` pada donor #45 `179850842be5f801e9c79aad6afc4c522e6e8e23`, #47 `71955dd3d4df9669b1e667e928a64508a2d579b6`, dan branch Claude `7a732797ebe43960f3f98e8d666cc792d21454f9`. Tabel/fungsi unit 24s ketiganya identik pada pembacaan sesi ini. Source ada tidak berarti seluruh flow integration berfungsi.

## Kategori — DECIDED

| Kategori | Batas maksimum fisik per sisi | Tambahan sisi kedua per pcs |
|---|---|---:|
| A5 | 14,8 × 21 cm | Rp15.000 |
| A4 | 21 × 29,7 cm | Rp25.000 |
| A3 | 29,7 × 42 cm | Rp35.000 |
| A3+ | 32 × 48 cm | Rp40.000 |

Kategori adalah plafon ukuran, bukan ukuran wajib. Desain diperkecil tetap memakai tarif kategori yang dipilih, tanpa turun kategori otomatis. Gambar dan teks dihitung sebagai gabungan area cetak, seluruhnya harus masuk batas kategori DAN area aman produk dari admin. Posisi dan ukuran aktual disimpan dalam cm. Ukuran cetak fisik tetap untuk satu desain lintas size pakaian. Target produksi 300 DPI; menulis metadata DPI saja tidak membuktikan resolusi sumber memadai.

## Satu sisi, dua sisi dan selisih belakang — DECIDED

Harga yang ditampilkan adalah harga kaos jadi dengan sablon satu sisi. Tidak ada komponen jual kaos polos.
Depan saja = belakang saja untuk kategori/produk/size yang sama.
Urutan kategori A5 < A4 < A3 < A3+.
Dua sisi = paket satu sisi kategori lebih besar + tambahan kategori lebih kecil; jika sama, tambahkan kategori itu. Sisi kosong tidak menambah biaya. Tidak ada tarif belakang tetap terpisah yang menimpa aturan ini.

Jika depan sudah punya harga paket dan belakang ditambahkan:
- belakang <= depan: selisih = tambahan kategori belakang;
- belakang > depan: selisih = (paket kategori belakang − paket kategori depan) + tambahan kategori depan.

Contoh hitung dari tabel Owner (Cotton Combed 24s size M, lengan pendek):

| Depan | Belakang | Total/pcs | Selisih dari harga depan saja |
|---|---|---:|---:|
| A3+ | kosong | Rp90.000 | Rp0 |
| kosong | A3+ | Rp90.000 | — |
| A3+ | A5 | Rp105.000 | Rp15.000 |
| A5 | A3+ | Rp105.000 | Rp40.000 |
| A4 | A4 | Rp100.000 | Rp25.000 |
| A3+ | A3+ | Rp130.000 | Rp40.000 |

Contoh adalah turunan aritmetika, bukan keputusan tarif tambahan baru. Simetri depan/belakang harus dipertahankan. Dua sisi kosong tidak sah untuk custom.

## Harga paket Cotton Combed 24s — DECIDED; fungsi source IMPLEMENTED

Nominal rupiah per pcs, sudah satu sisi.

| Size | A5 | A4 | A3 | A3+ |
|---|---:|---:|---:|---:|
| S | 62.000 | 72.000 | 82.000 | 87.000 |
| M | 65.000 | 75.000 | 85.000 | 90.000 |
| L | 68.000 | 78.000 | 88.000 | 93.000 |
| XL | 72.000 | 82.000 | 92.000 | 97.000 |
| 2XL | 77.000 | 87.000 | 97.000 | 102.000 |
| 3XL | 80.000 | 90.000 | 100.000 | 105.000 |
| 4XL | 83.000 | 93.000 | 103.000 | 108.000 |
| 5XL | 85.000 | 95.000 | 105.000 | 110.000 |

Lengan panjang: **+Rp10.000 per pcs pakaian**, ditambahkan sekali setelah perhitungan sisi, bukan per sisi atau per lengan. Fungsi source menambahkan satu kali; M A3+/A3+ lengan panjang = Rp140.000. Ini model pakaian lengan panjang; bukan harga sablon area lengan baru.

Tunik Oversize lengan 3/4 memiliki tabel tersendiri, bukan fallback kaos 24s:

| Size | A5 | A4 | A3 | A3+ |
|---|---:|---:|---:|---:|
| M | 85.000 | 95.000 | 105.000 | 110.000 |
| L | 90.000 | 100.000 | 110.000 | 115.000 |
| XL | 95.000 | 105.000 | 115.000 | 120.000 |

Preset otomatis tersedia setelah instalasi dan tetap dapat diubah admin; override harus bertahan upgrade, terisolasi per produk. Hoodie/sweatshirt adalah produk terpisah dengan tabel terpisah. Harga tidak tersedia harus memblokir kombinasi custom, bukan meminjam 24s. Validasi quote/qty/warna/size final milik server WooCommerce; payload harga buyer tidak dipercaya. Repeat order 5% per pcs termasuk satuan dengan verifikasi riwayat pelanggan adalah DECIDED; flow terbaru belum otomatis VERIFIED.

## Belum diputuskan Owner / perlu sumber tambahan

| Bagian | Status | Perlakuan |
|---|---|---|
| Nominal hoodie/sweatshirt | OPEN eksplisit pada source keputusan | Jangan isi/fallback 24s; admin harus punya harga sah |
| Tarif produk lain di luar tabel yang disepakati | OPEN bila belum ada tabel Owner | Profil/tarif independen; jangan mengarang |
| Tambahan lengan panjang untuk tunik 3/4 atau profil lain | Belum ditemukan keputusan dalam sumber ini | Jangan otomatis menyebarkan +10.000 ke semua produk |
| Tarif khusus sablon lengan sebagai area cetak tambahan | Tidak ditemukan keputusan | +10.000 lengan panjang bukan tarif cetak lengan |
| Paket diskon kuantitas atau surcharge belakang di luar formula di atas | Tidak ditemukan keputusan | Tidak diterapkan tanpa sumber/keputusan baru |
| Retensi desain/file terkait order setelah pemilik menghapus koleksi | OPEN eksplisit pada source keputusan | Tidak membuat job hapus |
| Aktivasi otomatis raster DTF di hosting | Sumber bisnis awal: file cetak dibuat di komputer produksi; source baru hanya kandidat default OFF | Jangan menganggap kandidat sebagai print-ready atau mengaktifkan generator/Drive; perubahan kebijakan perlu keputusan Owner |

Tidak perlu meminta ulang persetujuan tarif A5/A4/A3/A3+, tambahan sisi, atau +10.000 24s yang sudah jelas. Di luar cakupan ini tetap gunakan keputusan Owner yang dapat ditunjukkan sumbernya. Tidak melakukan tes checkout, produksi, order, atau runtime pada tugas dokumentasi.
