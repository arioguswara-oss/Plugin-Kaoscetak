# Backlog P1 — pembelajaran dari Lumise (DITUNDA)

**Status seluruh item: OPEN / usulan. Tidak termasuk scope Codex Cloud sebelum rilis inti.**
Sumber: evaluasi source Lumise v2.1.2 yang diberikan Owner dan diskusi UX. Ini bukan izin menyalin atau mendistribusikan source Lumise; repo ini publik.

## Prinsip keputusan

- Implementasi ulang secara orisinal: **ambil prinsip UX**, jangan copy kode JavaScript/PHP/CSS, aset, bundel, atau modul berlisensi Lumise.
- Setiap fitur harus dibenarkan oleh manfaat mobile, footprint JS, RAM, request, dan kompleksitas produksi. Tidak ada dependensi desain berat.
- Satu gambar + satu teks multiline per sisi tetap berlaku. Tidak ada template, clipart, multi-image, efek kompleks atau studio desain besar.
- P0 release harus lulus terlebih dahulu; P1 memerlukan acceptance criteria dan approval Owner tersendiri.

## Kandidat peningkatan

| ID | Fitur | Rencana implementasi ringan | Acceptance criteria draft | Status |
|---|---|---|---|---|
| P1-01 | **Undo / Redo** | History ringkas berupa koordinat, dimensi, kategori, teks/properti (bukan duplikasi piksel/file), stack terbatas | Undo/redo konsisten pada gambar, teks dan side switch; tidak menggandakan payload gambar/memori | OPEN |
| P1-02 | **Auto Snap / Alignment Guide** | Hitung titik tengah & batas area aman dalam koordinat cm; garis bantu sementara; opsi untuk menonaktifkan snap bila mengganggu | Snap presisi, tidak melewati area aman, dapat dipakai jari HP | OPEN |
| P1-03 | **Indikator kualitas DPI** | Estimasi DPI efektif dari piksel asli ÷ dimensi fisik cetak; peringatan non-blocking | Indikator mengikuti ukuran aktual, tidak otomatis mengubah kategori/harga; tidak menyesatkan seolah gambar upscaled membaik | OPEN |
| P1-04 | **Toolbar kontekstual** | Kontrol ringkas hanya untuk objek terpilih; ukuran sentuh layak HP | Tampilan tidak menutupi area preview, sederhana untuk pelanggan awam | OPEN |

## Kandidat terpisah yang belum diputuskan

- **Mockup kain/foto realistis** perlu asset resmi untuk setiap tipe pakaian dan warna serta pertimbangan berat download. Source v0.3.1 saat ini menggunakan ilustrasi Canvas; jangan memalsukan realismenya. Status **OPEN**.
- **Hapus background satu klik** adalah kebutuhan V1 yang telah DECIDED tetapi integrasi eksternal/cost/privasi dan pengujian file alpha perlu validasi tersendiri di P0 sesuai aturan bisnis. Ini **bukan tambahan P1 Lumise**.

## Syarat mulai mengerjakan backlog

1. Kandidat P0 sudah berhasil dalam WP/WooCommerce/Drive nyata, checkout dan admin produksi.
2. Owner memberi persetujuan eksplisit per fitur; ukur nilai tambah dan risiko.
3. Buat issue dengan tes performa sebelum/sesudah, feature flag atau rollback, serta bukti mobile.
4. Jangan memperlambat editor atau mengubah struktur data produksi yang telah diverifikasi.

Lihat [ROADMAP.md](ROADMAP.md).
