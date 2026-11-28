# SANRO Studio — feature completion 2

23 November 2026 WIB. Baseline resmi checkpoint **75,50%**, parent `3d989f2edd0f38c13ed3980d8b9b3cbf84104fcf`, branch `work/studio-mobile-checkout-integration-20261123`, Draft PR #52. Commit yang memuat laporan ini adalah source hasil putaran ini. Tidak dipasang di staging.

## Lima pekerjaan dan hasil implementasi

| Scope | IMPLEMENTED baru | Bukti dan batas |
|---|---|---|
| Lifecycle checkout | Menyimpan quote resmi pada locked draft; retry hanya order pending/failed yang sama pada sesi Woo asli; harga dan kuantitas dibekukan; memulihkan preview save yang gagal; CAS memperbarui item IDs jika native Woo mengganti baris saat retry; mempertahankan order_awaiting_payment meski hook order-created gagal dini; guard jalur pay-existing-order | 20 recovery checks PASS, memakai modul asli dan Woo/options in-memory. Paid/refund/cancel/order sesi lain, qty berubah, saved total dimodifikasi dan preview tidak lengkap ditolak. Tidak membuat order nyata |
| DTF depan/belakang | Kanvas hingga 6000px/24MP dengan estimasi memori sebelum alokasi; A3+32x48cm pada300DPI; PNG/JPEG original (alpha PNG dipertahankan, JPEG tetap opaque); teks multi-baris dengan bundled font; Unicode yang mempunyai glyph dan tidak memerlukan complex shaping; validasi dimensi/density/alpha/visible-content/SHA/private binding; coordinator/artifact/admin limits konsisten | Render GD/FreeType nyata A3+3780x5669 dan belakang mixed image/text PASS; transparansi, output kosong ditolak, private candidate readback dan batas memori diuji. Human print/font parity approval tetap false |
| Drive per pesanan | Merchant parent → SANRO-nomor-order → Produksi → ITEM-ID. Root/Produksi tersimpan via order CRUD, item via item CRUD. Lookup/create/recovery beridentitas site/order/kind/item; private user-only permissions dan parent chain diverifikasi. File original/preview/guide/manifest/optional candidate menggunakan uploader existing | 31 folder/provision checks PASS dengan HTTP simulasi, termasuk root-save failure recovery, ancestor public/moved denial dan admin root-link denial. Original/preview/PDF/manifest berada dalam folder item di bawah Produksi. Folder legacy langsung di merchant parent tetap dapat diverifikasi tanpa migrasi/delete |
| PDF + admin readiness | Font DejaVu Sans berlisensi disertakan; PDF searchable Unicode memakai embedded TrueType, CIDToGIDMap, ToUnicode dan ActualText; line-wrap/pagination, preview JPEG/PNG/WebP; status admin memisahkan dibayar/siap, locked amounts, dua preview, font, generator/Drive OFF dan persetujuan cetak | Byte/PDF xref, Unicode/multiline, PNG/WebP conversion dan admin capability checks PASS. pdftotext mengekstrak “Kaos École Ω Привет”, line breaks serta “é Ω” dengan spasi utuh. Raster halaman PDF diperiksa; glyph tampil. Browser preview memakai font yang sama dan menunggu font sebelum pengukuran/save/preview |
| Lint/smoke/self-fix/publish | Pemeriksaan proporsional pada area berubah, runner registration, ledger, laporan dan update Draft PR yang sama | PHP lint112 file PASS; JS/shell syntax dan whitespace PASS. Tidak mengulang full audit/runner atau pemeriksaan runtime lama yang tidak berubah |

## DECIDED / IMPLEMENTED / VERIFIED

DECIDED: bobot SANRO-7-v1 tetap; baseline75,50%; 300DPI, physical size sama antar size kaos, maksimum satu image dan satu blok teks per sisi; folder setelah pembayaran terkonfirmasi ATAU persetujuan admin; PDF panduan bukan press-ready file. Acuan `docs/business-rules/kaoscetak-lite-designer-v1.md`.

IMPLEMENTED: empat delta fitur pada tabel di atas. Kredit tidak mencakup ulang kalkulator, pricing tables, ownership, preview short route, original uploader, coordinator/artifact primitives atau mobile mockup yang sudah ada.

VERIFIED: code-level integration menggunakan modul asli, opsi/order/session Woo dan HTTP doubles; master/preview/PNG/PDF/font bytes serta GD/FreeType/Poppler nyata. Bukan browser WordPress/gateway/Drive akun nyata atau persetujuan cetak fisik.

## Validasi yang relevan

Runtime lokal PHP8.4.24, Node24.22.3, GD/FreeType dan ctype. GD dipulihkan hanya di scratch runtime dari paket PHP versi cocok dengan SHA256 terverifikasi; tidak mengubah host WordPress.

| Pemeriksaan | Hasil |
|---|---|
| PHP lint112 file native plugin/tests | PASS |
| Buyer JS syntax, dua shell runner syntax, diff whitespace | PASS |
| Checkout recovery / partial preview save / pay-existing-order preflight | PASS20; nested prerequisite checkout17 tidak dihitung dua kali |
| Production package/admin/upload, Unicode PDF dan PNG/WebP | PASS38 dengan GD; jumlah mencakup xref object validation, bukan ukuran progres |
| Actual A3+ front/back output + private artifact lifecycle | PASS12, GD dan FreeType nyata; proses test memory_limit768M |
| Unicode PDF embedded font + ActualText + PNG guide | PASS; pdftotext exact Unicode/spaces PASS; halaman diraster dan diperiksa |
| Drive hierarchy, remote private verification, root CRUD retry dan links | PASS31, HTTP simulasi |
| DTF artifact persistence / batch / admin status yang terdampak | PASS31 /40 /32; renderer prerequisite24+18 dieksekusi nested, tidak dijumlah ulang |
| Legacy Drive folder verification/admin link compatibility | PASS33 |
| Pricing adapter (locked quote branch berubah) / session resolver | PASS31 adapter +690 prerequisite quote /8 resolver |
| Buyer font/preview/master binding / startup regression | PASS9 /10 |
| Bootstrap default-OFF restore | PASS; loading plugin tidak mendefinisikan feature flags |
| Full local CI / GitHub Actions | NOT TESTED; focused commands dijalankan langsung dan runner diperbarui |
| Browser source baru / WordPress checkout/gateway / live OAuth/Drive upload / physical print | NOT TESTED |

Self-fix: memperbaiki pemulihan Woo awaiting-payment sebelum hook preview yang mungkin gagal; mengganti fixture PNG lama yang seluruhnya transparan dengan artwork terlihat agar tidak melemahkan validasi output kosong; memperbarui fixture yang sebelumnya menolak multiline/RGB; menambahkan ActualText untuk menjaga spasi hasil ekstraksi PDF; memperbaiki benturan nama status admin dan dependency helper pada Node fixture. Semua focused failures tersebut terselesaikan. Tidak menghapus guard untuk membuat test PASS.

## Batas, blocker dan operasional

1. **Source belum deployed.** HTTP422 pada checkpoint staging sebelumnya belum root-cause VERIFIED. Live WordPress/Woo lifecycle, gateway dan HPOS browser memerlukan source staging yang disetujui dan izin order sintetis; tidak dicoba lewat database live pada putaran ini.
2. Generator DTF dan Drive tidak diaktifkan. Semua flag runtime tetap seperti checkpoint; tidak menyentuh config/mu-plugin/hosting. Flags true hanya ada di proses test lokal terisolasi. Tidak menjadwalkan atau mengunggah apa pun ke akun Drive nyata.
3. Render A3+ memerlukan memori worker yang cukup dan GD/FreeType. Guard memperkirakan canvas12bytes/pixel + source6bytes/pixel +32MiB + pemakaian PHP saat ini. Test menggunakan768MiB; host yang kecil akan menolak sebelum alokasi. Master dan output tetap bounded (10MiB original,32MiB PNG output). Bukan klaim semua host mampu A3+.
4. Font bundled mencakup banyak Latin/Greek/Cyrillic. Missing glyph, combining marks dan script yang memerlukan shaping/bidi ditolak; CJK/Arabic/Indic/emoji di luar coverage tidak diganti diam-diam. Unicode bukan janji semua aksara. Override font server lama harus privat tepercaya dan identik dengan font bundled agar coverage/paritas tidak berubah; exact browser/server font parity dan hasil fisik belum disetujui.
5. JPEG mempertahankan background opaque; kode tidak mengaku melakukan remove-background otomatis. Validasi mesin300DPI/ukuran/alpha/visible-content tidak membuktikan warna tinta, ketajaman fisik, separasi white-ink, RIP/mirror atau kesiapan press. `quality_approved`, `font_parity_approved`, dan print approval tetapfalse.
6. Folder baru per order tidak memigrasikan folder legacy. Per-item files pada Produksi mengisolasi alokasi size. Drive/OAuth/permissions akun nyata masih NOT TESTED, tautan admin hanya dibuat sesudah GET verification; read-only link tidak mengaktifkan writer.
7. Checkout tetap classic/simple/satu desain, tanpa diskon/coupon/tax/shipping policy kompleks, Blocks atau native variations. Locked quantity tidak dapat diubah selama retry; untuk desain/alokasi baru harus memakai draft baru. Expired draft/session dan legacy locked record tanpa quote snapshot tidak dipulihkan dengan asumsi harga baru.

## Progres dan keputusan

**75,50% →80,50%, +5,00 poin implementasi berbobot.** Backend/admin81→83 (+0,20); checkout/produksi/Drive60→84 (+4,80); tahap lain tetap. Subtotal80,35 + saldo Owner historis0,15 =80,50. Ledger tunggal: `docs/ledger/PROGRESS-BASELINE.md`. Persentase adalah estimasi scope source, tidak berasal dari rasio PASS atau otomatis mencapai target karena instruksi Owner.

Ownership tetap Work pada branch PR#52; #50/#51 tidak ditulis, tidak menjalankan Hermes/Codex/AI lain. Publish memakai lease expectedSHA dan nonforce. Tidak dapat membuktikan/menutup seluruh sesi AI eksternal; tidak mengklaim global lock.

GO review/development berikutnya. NO-GO merge/deploy/production/aktivasi generator atau Drive/claim release print-ready. Berikutnya: integrasi policy checkout yang masih terbatas; tambahkan renderer shaping bila diperlukan; lalu uji staging/order/gateway dan akun Drive nyata dalam scope yang disetujui. Full audit tetap pada90% implementasi.
