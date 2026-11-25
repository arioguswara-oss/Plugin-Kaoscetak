# SANRO Studio V1.1.0 · acceptance staging 25 November 2026

**NO-GO untuk Final Release dan rekomendasi merge PR #52.** Uji browser menemukan dua blocker fungsional pada form katalog dan validasi mockup. Acceptance visual desktop sebagian terverifikasi. Acceptance potret dan gestur ponsel masih terbuka.

Repository `arioguswara-oss/Plugin-Kaoscetak`, branch `work/studio-mobile-checkout-integration-20261123`. HEAD saat review `1b5081ca63d769217c8b662f5c3af176406fdb77`. Bukti instalasi terdahulu mencatat source `690fb0be568713328a0c6e2fd5f81b3fb457a655`, aktif V1.1.0 dengan tree digest `6240c2816723cec83803416484fb2149611b61a96c96db109e2914b45a8d96f7`. Diff plugin antara source dan HEAD kosong. Pemeriksaan browser dilakukan pada `saddlebrown-peafowl-616656.hostingersite.com`. [Bukti instalasi dan kontrak CLI](studio-variable-product-e2e-20261125.md).

## 1. Katalog Administrator

| Skenario | Hasil | Bukti |
|---|---|---|
| Tambah simple | **FAIL** | Produk sintetis #53 terlihat sebagai `Belum dipetakan`. `Tambah & atur` menghasilkan HTTP 403 dan pesan WordPress `The link you followed has expired.` Setelah reload, submit yang sama kembali ke pesan tersebut. Status produk tetap `Belum dipetakan`. |
| Edit simple | **PASS terbatas** | Posisi visual depan X pada #53 berubah 31,1 → 31,2 setelah simpan dan baca ulang. Simpan berikutnya memulihkan 31,1. |
| Aktif/nonaktif, arsip dan restore simple | **BLOCKED / NOT TESTED** | UI menampilkan aksi status setelah produk berhasil dipetakan. Kontrak dan bukti runtime CLI terdahulu dicatat terpisah. |
| Tambah/edit/aktif/nonaktif/arsip/restore variable | **BLOCKED / NOT TESTED** | Jalur tambah UI memakai form yang sama untuk kedua tipe. Pemetaan variable memerlukan attachment valid sebelum aktivasi. Fixture variable CLI sebelumnya sudah dibersihkan. Membuat parent variable tambahan pada putaran ini tidak dapat mengatasi dua prasyarat tersebut. |

**P1 A — binding nonce katalog.** `includes/studio-ui.php` menyiapkan nonce per opsi dan hidden `_wpnonce` kosong. Setelah memilih #53, browser melihat nonce pada opsi tetapi input yang akan dikirim tetap kosong. Server memeriksa `sanro_catalog_<product_id>` dan menolak POST. Source `assets/studio-admin.js` berisi `syncNonce`; runtime tidak menunjukkan hasil binding itu. URL aset yang dicantumkan halaman menggunakan `?ver=1.1.0`, sedangkan kode berubah selama beberapa kandidat dengan versi sama. Cache aset adalah dugaan yang perlu konfirmasi hash/console; pembacaan langsung aset lewat tab diblokir kebijakan browser, jadi penyebab spesifik hilangnya binding belum dipastikan. Handoff CLI: cocokkan JS yang dilayani dengan kandidat, fingerprint `studio-admin.js` dan `admin-mockup-mapping.js` pada enqueue Studio, periksa error inisialisasi, lalu verifikasi hidden nonce terisi pada seleksi dengan mouse/keyboard sebelum submit. Pertahankan validasi nonce per produk dan capability.

## 2. Fixture Media Library dan buyer variable

Dua PNG sintetis 600×710 diunggah melalui WordPress `Media → Add Media File`. Uploader mengonfirmasi attachment #74 (depan) dan #75 (belakang). Saat ID tersebut dimasukkan melalui field Mockup pada produk sintetis #53 dan produk disimpan, kedua field kembali kosong. Form tidak menunjukkan alasan penolakan yang jelas. Kedua attachment sintetis diberi nama `sanro-qa-mockup-*-20261125` dan tersedia di staging untuk uji ulang.

**P1 B — status attachment efektif.** `includes/product-mockups.php::sanro_studio_valid_mockup_attachment()` mewajibkan `get_post_status($id) === 'inherit'`. WordPress menyimpan status raw `inherit`, tetapi `get_post_status()` menyelesaikan status efektif attachment dari parent dan mengembalikan `publish` untuk attachment lepas yang normal. Ini menjelaskan penolakan upload resmi. Validator itu dipakai di form mockup dan matrix. `editor-config.php` mensyaratkan matrix untuk semua pasangan warna dan size profil pada produk variable. [WordPress core get_post_status](https://developer.wordpress.org/reference/functions/get_post_status/#source).

Handoff CLI: periksa tipe `attachment` serta gambar Media Library yang valid, bedakan status raw dari status efektif, dan tetap fail closed untuk attachment/parent private, password-protected, draft, trash, atau akses publik yang tidak terverifikasi. Pertahankan URL Media Library resmi. Tambahkan regresi terhadap WordPress sungguhan untuk unattached dan parent publik, serta rejection untuk parent privat/trashed, termasuk perilaku core yang dapat membaca status sebelum trash. Kembalikan pesan gagal saat simpan dan uji readback. Gunakan matrix lengkap untuk ukuran profil (misalnya 2 warna × 8 size untuk 24s), termasuk variasi stok habis bila menguji UI availability.

| Skenario buyer | Hasil |
|---|---|
| Katalog dan buka editor simple | **PASS** di browser staging |
| Warna, ukuran, multi-size, mockup, quote dan cart variable | **BLOCKED / NOT TESTED live** karena P1 A/B; tidak ada cart baru |
| Pemetaan, quote multi-size, cart candidates dan binding order line | **PASS kontrak terdahulu** pada source `690fb0b` |
| Pembuatan order live variable | **NOT TESTED**; tidak ada order baru atau perubahan order existing |

## 3. Visual dan interaksi

Acuan: [konsep buyer](../../artifacts/studio-v1/1.1.0-ui/review-20261125/references/owner-buyer-concept.jpg) dan [konsep Administrator](../../artifacts/studio-v1/1.1.0-ui/review-20261125/references/owner-admin-concept.jpg). Bukti aktual di [folder screenshot](../../artifacts/studio-v1/acceptance-690fb0b-20261125/). Screenshot staging dipotong di bagian toolbar atas dan diperkecil untuk publikasi.

- **Buyer desktop, PARTIAL.** Header hitam Studio, pencarian, kartu katalog, aksen kuning, mockup, D-pad, aksi Simpan/Keranjang dan navigasi alat terlihat. Header/footer tema JurnalFX masih tampil di luar Studio. Produk staging masih memakai fallback ilustrasi. Konsep menampilkan katalog dengan foto produk yang berbeda.
- **Drag dan resize desktop, PASS.** Teks sintetis `QA Visual` berpindah dari posisi kotak 19,59/163,52 px ke 35,42/138,72 px. Resize lewat handle mengubah font 22 → 31 dan lebar kotak dari sekitar 104 ke 147 px. Desain tersebut dibuat di form untuk uji interaksi tanpa submit.
- **Desain Saya, PARTIAL.** Panel akses privat dan aksi koleksi tampil. `Buka koleksi sesi ini` mengembalikan HTTP 409 `sanro_collection_not_found` untuk sesi tersebut dengan pesan bertuliskan `Verifikasi preview ditolak`. Kartu terisi, Buka Desain dan Pesan Lagi belum diuji. Tidak ada permintaan email/OTP. Handoff UI: tampilkan keadaan belum memiliki koleksi dan arahkan pembeli ke aksi penyimpanan yang sesuai.
- **Administrator desktop, PARTIAL.** Sidebar gelap, tab mockup/area dan overlay bidang cetak terlihat. Daftar katalog dan selector memanjang sebelum panel konfigurasi, sehingga posisi mockup dan ukuran fisik kurang ringkas dibanding konsep. Detail order dan setting Drive mengandalkan evidence sebelumnya.
- **Layout sempit, PASS terbatas.** Probe zoom Chrome melaporkan viewport CSS 454×312, lebar dokumen 449 dan handle 44 px, tanpa overflow horizontal pada layar tersebut. Bukti ini mencakup layout sempit dalam Chrome desktop. **Portrait ponsel, touch drag/resize dan pinch-zoom NOT TESTED.** Pada kode pointer handler yang diperiksa, drag/resize melacak satu pointer. Belum ditemukan implementasi pinch dua jari pada handler tersebut. CLI perlu memverifikasi gestur di perangkat atau emulasi yang berwenang sebelum sign-off.

## 4. Gate dan tindak lanjut

Baseline resmi **96,50% → 96,50%, delta 0,00**. Fondasi dan ownership memakai bukti terdahulu. Tahap admin dan buyer tertahan P1 A/B. Tahap harga memakai bukti kontrak. Tahap cart/order memakai bukti kontrak saja untuk variable. Bukti build/instalasi tetap valid untuk kandidat yang ditinjau. Visual acceptance belum lengkap. Google Drive OAuth live **DEFERRED BY OWNER**.

1. Codex CLI perbaiki P1 A/B dan hasil form, lalu siapkan kandidat staging dengan hash aset yang bisa dicocokkan.
2. Uji lifecycle simple/variable melalui UI dan lanjutkan buyer sampai quote/cart sintetis. Verifikasi produk WooCommerce serta referensi historis ketika listing Studio diarsipkan dan dipulihkan.
3. Selesaikan acceptance desktop dan portrait/touch terhadap konsep, termasuk koleksi sintetis terisi dan pinch-zoom.

Final Release dan rekomendasi merge PR #52 menunggu bukti tersebut. Acceptance ini tidak melakukan reinstall, Full Audit, pengujian DTF/Drive, payment, order baru, perubahan order existing, production atau merge.
