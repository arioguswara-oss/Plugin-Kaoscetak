# Roadmap resmi — Kaoscetak Lite Designer

**Pembaruan:** 17 November 2026 · **Owner / final approver:** Rio
**Repository:** arioguswara-oss/Plugin-Kaoscetak
**Source berjalan:** develop/lite-designer-v1 · Draft PR #2 (v0.3.1 owner-test)
**Kondisi live terakhir diperiksa:** Kaoscetak Lite Designer v0.2.0 aktif; Lumise v2.1.2 tetap aktif.

> Dokumen ini menetapkan **urutan pengembangan dan gate**, bukan klaim pekerjaan sudah lulus uji. Tidak ada jadwal rilis yang dijanjikan. Status berdasarkan evidence aktual, bukan sekadar klaim pada PR.

## Prinsip dan status

- **DECIDED** = kebutuhan telah disetujui Owner; **OPEN** = usulan/tugas belum disetujui atau diselesaikan; **IMPLEMENTED** = kode tersedia; **VERIFIED** = lulus pengujian nyata; **BLOCKED** = hambatan nyata.
- Satu produk WooCommerce → tombol Custom Desain → editor frontend layar penuh mobile-first → simpan/keranjang → checkout WooCommerce. Bukan editor WordPress Edit Post.
- Masing-masing sisi depan/belakang: maksimum 1 file PNG/JPEG (10 MB) + 1 teks multiline; posisi & dimensi cm. Gambar dan teks satu bounding area cetak, independen per sisi; kategori A5/A4/A3/A3+ adalah **batas maksimum**, tidak otomatis berubah saat gambar diperkecil.
- Harga adalah **harga jadi satu sisi** per produk/size/kategori, bukan harga kaos polos; dua sisi = paket kategori terbesar + biaya tambahan kategori terkecil. Seluruh harga, quantity, hak diskon repeat-order 5%, warna tersedia dan file wajib divalidasi ulang server-side.
- Simpan master di Drive privat, bukan hosting WordPress; thumbnail, metadata, dan referensi di WordPress. Preview admin tanpa Drive boleh, **checkout publik dengan file master belum tersimpan valid harus ditolak**.
- Preview warna dan size, tetapi satu desain berlaku untuk multi-size dengan ukuran cetak fisik tetap. Produksi DTF berkas siap cetak dilakukan di komputer produksi; PDF di WordPress hanya panduan produksi.
- Kesesuaian Flatsome/WooCommerce, keamanan guest, cache, mobile RAM 4 GB sebagai target uji (bukan minimum) adalah prioritas. Jangan meniru/menyalin source Lumise.
- Keputusan lengkap: [Aturan bisnis](business-rules/kaoscetak-lite-designer-v1.md) dan [Arsitektur](architecture/README.md).

## Urutan P0 menuju rilis inti

| Tahap | Tujuan & keluaran | Kriteria keluar (gate) | Status awal |
|---|---|---|---|
| **0. Baseline** | Audit branch, PR #2, CI, perbedaan v0.2.0 live vs v0.3.1 source, kumpulkan matrix feature | Baseline + daftar gap tanpa mengubah live; CI dapat dijalankan atau blocker dicatat | OPEN; source v0.3.1 tersedia |
| **1. Editor & pricing** | Editor frontend gambar/teks per sisi; gerak/resize/area aman; warna/size/qty; tabel harga tiap produk; variasi mug terpisah | Browser editor dari produk, seluruh tabel & kombinasi dua sisi, server menolak payload manipulasi | IMPLEMENTED menurut source; VERIFIED belum |
| **2. Draft, privasi & Drive** | Design ID, save/restore guest, verifikasi pemilik email, share opt-in/revoke/fork, file master Drive privat, retry & status | Bukti private access; file asli tidak publik; checkout memblokir upload gagal; tidak ada token client | IMPLEMENTED sebagian menurut source; uji nyata BLOCKED/belum |
| **3. Pesanan & produksi** | Cart/checkout, preview admin order, status Drive, folder order setelah paid/siap produksi, PDF panduan produksi | WooCommerce E2E tanpa selisih harga; file produksi tidak menduplikasi folder; PDF memuat data akurat | IMPLEMENTED sebagian menurut source; VERIFIED belum |
| **4. QA & security** | Audit source, WP + WooCommerce + Flatsome, browser mobile, uji failure, keamanan akses dan regresi | Semua gate di [RELEASE-GATES.md](RELEASE-GATES.md) PASS atau exception diberi persetujuan Owner secara eksplisit | OPEN |
| **5. Performance** | Pangkas bundle dan request, thumbnail ringan, jaga UI responsif pada upload, ukur halaman editor mobile | Bukti metrik sebelum/sesudah, editor responsif RAM 4 GB, tanpa regresi fungsional | OPEN |
| **6. Release & transisi** | Versioned ZIP + changelog, checksum SHA-256, bukti tes, rollback, upload ke folder Drive yang disepakati | Owner menyetujui kandidat; deployment terkendali; smoke test; keputusan Lumise secara terpisah | OPEN |

**Ketergantungan:** tahap 1–3 harus stabil sebelum tahap 4–6. Pengujian performa boleh dilakukan sepanjang development, tetapi optimasi yang mengubah perilaku harus dites ulang.

## Scope yang sudah terlihat di branch, BELUM otomatis verified

- Draft PR #2 memuat klaim implementasi editor frontend, produk kaos/tunik, preview canvas, warna per produk, mug putih 11 oz, guest/share/fork, Drive/PDF.
- GitHub CI pernah dilaporkan gagal. Lint source dan tes terisolasi bukan bukti instalasi WP/WooCommerce/Flatsome lulus.
- Mockup foto realistis belum terintegrasi; statusnya OPEN (perlu aset/persembahan desain Owner), bukan alasan untuk memalsukan foto.
- Hak akses Google Drive produksi, WooCommerce Blocks dan perangkat mobile sesungguhnya belum VERIFIED.
- Jangan menonaktifkan, memodifikasi atau menghapus Lumise tanpa persetujuan Owner.

## Pascarelease inti — P1, bukan tugas Codex saat ini

Lihat [LUMISE-BACKLOG.md](LUMISE-BACKLOG.md) untuk Undo/Redo, Snap/Alignment, indikator kualitas DPI, dan toolbar kontekstual. Keempatnya **OPEN / ditunda**, tidak boleh memperlebar scope P0.

## Cara kerja & pembaruan status

1. Pilih issue tahap yang memiliki dependency terpenuhi; jangan membuat implementasi paralel yang bersentuhan dengan file sama tanpa koordinasi.
2. Codex Cloud audit HEAD terbaru, lalu ubah kode pada branch/PR terpisah; laporkan commit, diff, tes, bukti, risiko dan blocker.
3. ChatGPT audit source dan paket; ChatGPT Work melakukan browser QA terkontrol bila akses tersedia. Pengujian live membutuhkan izin tindakan spesifik dari Owner.
4. Owner menyetujui merge/rilis; tanpa itu PR tetap draft/tidak merge ke main.
5. Update tabel status, checklist issue dan RELEASE-GATES hanya berdasarkan evidence. Detail: [WORKFLOW.md](WORKFLOW.md).

**Referensi pelacakan:** [Issue induk #1](https://github.com/arioguswara-oss/Plugin-Kaoscetak/issues/1), [PR development #2](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/2).
