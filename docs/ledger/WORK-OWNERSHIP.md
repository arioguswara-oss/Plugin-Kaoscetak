# Kepemilikan pekerjaan dan pencegahan bentrok branch

Snapshot 23 November 2026 WIB. Tugas koordinator tidak menjalankan restore atau mengedit kode.

## Yang benar-benar diamati

| Lingkup | Fakta | Status |
|---|---|---|
| Tim percakapan ini | Inventory collaboration hanya /root berjalan; tidak ada child agent | VERIFIED saat diperiksa |
| Workspace ini | Clone terpisah; branch docs/coordinator-ledger-20261123 hanya docs/ledger | VERIFIED melalui diff dan status |
| PR #47 | feature/studio-bootstrap-admin, SHA 71955dd3d4df9669b1e667e928a64508a2d579b6, kandidat bootstrap/editor | Source VERIFIED; pemilik sesi eksternal belum diketahui |
| Claude | work/claude-sanro-studio-product-pricing-profile-20261123, SHA 7a732797ebe43960f3f98e8d666cc792d21454f9, turunan #47 | Ancestry VERIFIED; aktivitas runtime/ack belum terlihat |
| Donor #46 | dev/staging-one-order-gate-20261122, SHA e01bdc55a21b4f0b2c471300dfc58ac482641a07 | Snapshot source VERIFIED; bukan target tulis bersama |
| Hermes/Codex Cloud/Claude sesi lain | Tidak tersedia inventory job/sesi remote atau readback lease dari worker pada tools yang ditemukan | NOT VERIFIED |

Tidak adanya push atau assignee bukan bukti bahwa AI lain idle. Semua PR #14–#47 pada snapshot tidak memiliki assignee, sehingga assignment GitHub tidak dapat membuktikan kepemilikan worker. Branch Claude yang berbeda menghindari ref sama, tetapi perubahan pricing/bootstrap tetap dapat tumpang tindih dengan #47.

## Batas kerja yang ditetapkan koordinator

| Pekerjaan | Branch | Penulis | Batas tulis/keputusan |
|---|---|---|---|
| Ledger/koordinasi sesi ini | docs/coordinator-ledger-20261123 | /root | Hanya docs/ledger; tanpa kode/runtime |
| Bootstrap/editor utama | feature/studio-bootstrap-admin (#47) | Satu pemilik eksternal, BELUM ACK | Jangan menambah worker kedua, restore kedua, atau menulis branch ini dari sesi koordinator |
| Profil harga produk | work/claude-sanro-studio-product-pricing-profile-20261123 | Kandidat Claude berdasar nama branch/commit, BELUM ACK runtime | Usulan batas: product-pricing.php, test harga; perubahan sanro-studio.php diserialkan dengan pemilik #47 |
| Donor kumulatif/restore source | #46 dan semua PR lama | Arsip baca-saja untuk sesi ini | Tidak menjalankan restore atau worker di branch donor |
| integration/sanro-studio | Target PR tunggal | Merge Owner saja | Tidak menerima push kode langsung atau restore |
| main/production/database live | Diluar tugas | Owner berizin | Tidak disentuh |

Tabel ini adalah kebijakan koordinasi yang dicatat, BUKAN klaim penguncian Git server atau acknowledgement worker yang sudah diterima.

## Protokol satu penulis

Sebelum melanjutkan restore/editor, satu pemilik harus mengakui assignment branch dan scope: task ID, worker/session ID, branch, base SHA, daftar path, mulai, heartbeat/expiry yang jelas, dan status ACTIVE/DONE. Koordinator mencatat acknowledgment; worker lain memakai worktree/branch berbeda dan tidak mengambil scope beririsan. Lease kedaluwarsa bukan izin mengambil alih otomatis; cek tugas lama berhenti dahulu. Sebelum push periksa head terhadap SHA awal, jangan force-push. Bila berubah, hentikan push dan rekonsiliasi.

Shared path sanro-studio.php hanya punya satu penulis pada satu waktu. Branch berbeda saja tidak cukup. Restore/port ulang file editor yang sudah dimiliki #47 ditahan sampai ACK atau task lama terbukti selesai. Perubahan pricing Claude harus dikirim sebagai delta setelah bootstrap, dengan init/hook integration dimiliki penulis yang disepakati.

## Kesimpulan poin 4

**Sesi ini aman dari bentrok yang dibuat sendiri:** satu agent, branch dokumentasi terpisah, nol restore/editor/code write. **Jaminan global tidak ada AI lain menulis branch/scope yang sama: BELUM DAPAT DIVERIFIKASI.** Tidak mengaku sudah menghentikan atau mengunci Hermes/Claude/Codex eksternal.

GO dokumentasi dan review baca-saja. **NO-GO untuk memulai writer restore/editor tambahan** sampai inventory/ack worker eksternal tersedia. Tidak menghubungi pihak lain, menonaktifkan layanan, membatalkan job, atau mengubah worker pada tugas ini.

## Temuan akhir: PR #48 di luar inventaris awal #14–#47

PR [#48](https://github.com/arioguswara-oss/Plugin-Kaoscetak/pull/48) muncul saat finalisasi, branch `work/codex-studio-ci-20261123`, HEAD `e8404d61d5161e4c5d6cfe5a04b9470f8e7145fe`, base #47 `71955dd3d4df9669b1e667e928a64508a2d579b6`. Metadata dan `docs/DEVELOPMENT-LEDGER.md` dibaca: perubahan hanya tests/ dan docs/, plugin source tetap identik menurut laporan PR; bounded offline bootstrap/Node PASS dilaporkan, bukan runtime WP/Woo.

Pekerjaan QA ini berada di branch terpisah; tidak membuktikan restore/editor pada branch yang sama sedang berjalan atau sudah berhenti. Sesi Codex eksternal belum ACK ownership. Jangan menjalankan duplikat runner atau mengubah branch #48 pada tugas ini. Sesuai jalur tunggal, usulan kelanjutan QA adalah PR langsung ke integration setelah dependency bootstrap masuk; tidak melakukan retarget/merge sekarang.

Ledger #48 mengelompokkan tujuh kapabilitas dengan urutan berbeda dan tanpa persen, hanya untuk source bootstrap. Ini evidence per branch, bukan pengganti bobot tujuh tahap SANRO-7-v1 di PROGRESS-BASELINE.md. Tahap draft/master dan checkout pada #48 BLOCKED karena source belum ada di base, konsisten dengan perbedaan donor dan integration. Jangan menyalin angka 71,95% ke source bootstrap sebagai capaian VERIFIED.
