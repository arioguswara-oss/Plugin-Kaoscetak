# SANRO Studio — baseline aktif development

23 November 2026 WIB. Baseline checkpoint Owner **72,90%**; hasil source putaran feature completion **75,50% (+2,60 poin berbobot)**. Bobot SANRO-7-v1 tetap, tanpa kalibrasi ulang. Nilai mengukur estimasi scope IMPLEMENTED pada development, bukan kesiapan rilis/runtime VERIFIED.

| Tahap | Bobot | Sebelumnya | Sekarang | Kontribusi sebelumnya → sekarang | Delta berbobot |
|---|---:|---:|---:|---:|---:|
| 1. Fondasi/core | 25% | 75% | 75% | 18,75 → 18,75 | 0,00 |
| 2. Backend produk/admin | 10% | 79% | 81% | 7,90 → 8,10 | +0,20 |
| 3. Frontend buyer | 15% | 96% | 96% | 14,40 → 14,40 | 0,00 |
| 4. Harga server/quote | 10% | 83% | 83% | 8,30 → 8,30 | 0,00 |
| 5. Simpan/ownership/share | 10% | 98% | 98% | 9,80 → 9,80 | 0,00 |
| 6. Checkout/produksi/Drive | 20% | 48% | 60% | 9,60 → 12,00 | +2,40 |
| 7. QA/rilis/mobile | 10% | 40% | 40% | 4,00 → 4,00 | 0,00 |
| **Subtotal tujuh tahap** | **100%** | — | — | **72,75 → 75,35** | **+2,60** |
| Saldo baseline Owner historis, belum teralokasi | — | — | — | 0,15 → 0,15 | 0,00 |
| **Total sementara aktif** | — | **72,90%** | **75,50%** | **72,90 → 75,50** | **+2,60** |

Formula delta Σ(bobot × perubahan tahap / 100). Saldo 0,15 berasal dari baseline Owner 72,10 dibanding source lama 71,95 yang sudah direkonsiliasi pada checkpoint `2d945be`; tidak mengarang alokasi tahap historis. Checkpoint 72,90 sudah mencakup mobile/mockup/short preview route/blank preview/native qty compatibility. Riwayat Git dan laporan lama bukan baseline aktif kedua.

## Kredit baru, tanpa duplikasi

- Backend/admin +2 tahap = +0,20: inline preview dua sisi dengan endpoint admin berizin+nonce dan ringkasan snapshot warna/lengan/kategori pada item order. Private reader/nonce security lama adalah dependency, tidak diberi kredit ulang.
- Checkout/produksi/Drive +12 tahap = +2,40: cart replay/alokasi lengkap dan rollback validation (+2 tahap); qty checkout → snapshot item → CAS locked design → binding preview dua sisi (+4); manifest dan PDF panduan + preview package dari locked/saved amounts (+4); coordinator/uploader paket idempoten, private verification dan handoff candidate (+2). Subitem ini estimasi scope, bukan rasio tes.
- Snapshot hanya dihitung pada tahap checkout, tidak pada save/backend kedua kali. Repricing engine #51, uploader original/renderer/approval/folder lama #50, mockup/short preview checkpoint, pemulihan dependency dan jumlah tes tidak diberi kredit baru. QA tetap 40%.
- Pipeline source bertambah, tetapi Unicode PDF, kemampuan renderer DTF lengkap, live OAuth/Drive dan live checkout belum selesai/VERIFIED. Tidak mengklaim tahap6 selesai atau mencapai90%.

DECIDED: baseline/weights, scope dev, runtime OFF, tanpa merge/deploy/order DB. IMPLEMENTED: delta di atas. VERIFIED: focused lint dan flow actual modules dengan WP/Woo/HTTP sintetis serta JPEG/PDF/PNG nyata. Live browser/source baru, checkout order dan akun Drive NOT TESTED. HTTP422 staging tetap blocker runtime; bukan klaim root cause fixed.

Bukti dan batas lengkap: [studio-feature-completion-20261123.md](../qa/studio-feature-completion-20261123.md). GO review/development. NO-GO merge/deploy/production/aktivasi generator atau Drive.
