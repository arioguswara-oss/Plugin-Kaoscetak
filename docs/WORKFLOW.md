# Workflow kerja — Kaoscetak Lite Designer

**Owner:** Rio (final approval). **Prinsip:** pekerjaan transparan, dapat diverifikasi, tidak mengubah production tanpa izin eksplisit.

## Pembagian tugas yang berlaku sekarang

| Peran | Tanggung jawab | Batas |
|---|---|---|
| Rio — Owner | Keputusan DECIDED, scope, prioritas, approval merge/deploy/rollback | Semua perubahan harga resmi atau spesifikasi wajib disetujui |
| ChatGPT — arsitek & reviewer | Menjaga spesifikasi, mengaudit source/diff, menilai risiko, review ZIP, mengelola roadmap/issues | Tidak menganggap kode VERIFIED hanya dari laporan developer |
| Codex Cloud — developer | Implementasi source di branch/PR sesuai issue P0, unit/integration tests, perbaikan CI, dokumentasi perubahan | Tidak mengubah hosting live, tidak mengganti spesifikasi Owner, tidak menyentuh Lumise |
| ChatGPT Work — QA & release support | Uji browser WP/WooCommerce/Flatsome/mobile, validasi integrasi nyata dan instalasi terkendali jika disetujui | Tidak deploy ke live tanpa izin; laporan PASS/FAIL/BLOCKED beserta evidence |
| Hermes | Tidak dilibatkan dalam project ini tanpa instruksi Owner berikutnya | Jangan otomatis delegasikan |

Peran Codex Cloud diaktifkan atas instruksi Owner terbaru; pernyataan lama "tanpa Codex kecuali diminta" pada dokumen terdahulu berarti sudah memenuhi syarat "diminta".

## Cara kerja per issue (step-by-step)

1. **Pilih issue yang belum selesai.** Baca ROADMAP, aturan bisnis, arsitektur dan PR #2. Periksa commit/branch terbaru dan tes yang sudah terbukti; jangan mulai dari v0.1.0.
2. **Tetapkan kriteria penerimaan**, titik risiko server, dan kasus negatif. Bedakan kebutuhan DECIDED dari OPEN; fitur baru belum disetujui tidak boleh dimasukkan.
3. **Branch terisolasi dari HEAD relevan.** Jangan push langsung ke main atau mengubah branch yang sedang dipakai pelaksana lain. Docs dan code dipisah bila memungkinkan.
4. **Implementasi kecil yang reviewable.** Hindari dependency besar atau proses berat di hosting; seluruh data client dianggap tidak tepercaya.
5. **Uji lokal/CI yang sesuai.** PHP lint, JavaScript lint, unit price matrix, negative security tests, integrasi WP/WooCommerce, browser E2E bila tersedia. Laporkan semua yang tidak bisa diuji sebagai BLOCKED/NOT TESTED.
6. **Draft PR** dengan commit SHA, daftar file berubah, alasan, evidence, cara test, risiko, rollback dan isu tertinggal. Jangan menyebut "FINAL" hanya karena CI hijau.
7. **Review & QA terpisah.** ChatGPT audit; ChatGPT Work uji yang memerlukan situs/perangkat/kredensial yang berizin.
8. **Owner approval.** Rio memutuskan merge, rilis, dan perubahan production. Setelah merge, perbarui issue, roadmap dan release checklist.
9. **Paket rilis hanya setelah PASS.** ZIP versi jelas, checksum SHA-256, changelog, baseline kompatibilitas, evidence, serta rollback. Simpan ke subfolder versi di folder Drive "Plugin wordpress" jika disetujui.

## Laporan wajib Codex setelah setiap iterasi

- Branch/commit/PR yang benar-benar dibuat, issue terkait.
- Perubahan terbukti dan apa yang sengaja tidak dikerjakan.
- Tabel tes PASS / FAIL / BLOCKED / NOT RUN; sebutkan environment, perintah, output, dan evidence.
- Perbedaan terhadap spesifikasi dan risiko; bila melibatkan keputusan OPEN, berhenti dan eskalasi ke Owner.
- Next action terkecil dengan dependency jelas; **tidak ada klaim background work**.

## Aturan keselamatan

- Produksi (kaoscetak.id), database, tema Flatsome, WooCommerce, Lumise, pembayaran dan Google Drive pelanggan tidak diubah kecuali tindakan tersebut disetujui secara eksplisit.
- Tidak upload ZIP "final" dari lint saja. Jangan ulangi insiden v0.1.0: "Kaoscetak Desain → Edit Post" bukan editor pelanggan.
- Jangan unggah master customer, OAuth secrets, token, dump database, credentials, file order privat, atau source komersial Lumise ke repo publik.
- Untuk koneksi Google Drive: token/server secret tidak di browser; file master tidak publik; checkout menolak file belum tersimpan valid; retry dan idempotensi harus diuji.
- Perubahan ke daftar plugin, pengaturan cache, database, file WP, pembayaran, DNS, atau hosting live dilakukan hanya dengan approval dan rollback.
- Audit statis, WP smoke test sintetis, simulasi pembayaran, serta Drive sandbox tidak sama dengan VERIFIED pada layanan nyata.

## Rekonsiliasi branch

- Branch development yang ada: develop/lite-designer-v1, Draft PR #2 menuju main; baca HEAD sebelum pekerjaan baru.
- Branch dokumentasi ini: docs/roadmap-release-gates-20261117; tidak memuat perubahan kode plugin.
- Konflik kode atau hasil tes: jadikan sumber bukti commit/CI terbaru, diskusikan perbedaan; jangan force push atau menimpa pekerjaan lain.
- P0 selesai terlebih dahulu. Fitur rujukan Lumise tetap backlog P1 tanpa assignment developer sekarang.

Lihat [Roadmap](ROADMAP.md), [Gate Rilis](RELEASE-GATES.md), [Backlog Lumise](LUMISE-BACKLOG.md).
