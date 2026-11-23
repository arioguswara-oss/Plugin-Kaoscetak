# SANRO Studio — baseline development sementara

Tanggal 23 November 2026 WIB. Owner menetapkan baseline sementara **72,10%** untuk tugas development ini. Bobot tujuh tahap tetap SANRO-7-v1: 25/10/15/10/10/20/10. **Hasil putaran: 72,10% → 72,90%; delta +0,80 poin berbobot.** Ini estimasi scope IMPLEMENTED pada branch development, bukan release readiness atau checkout runtime VERIFIED.

## Rincian tahap dan rekonsiliasi baseline

Ledger terakhir yang tersedia pada source #50 masih merinci total 71,95%, bukan 72,10%. Owner tidak memberikan alokasi tambahan 0,15 poin per tahap. Selisih ini dipertahankan sebagai saldo baseline Owner **belum teralokasi**; tidak mengarang perubahan tahap lama atau mengganti bobot.

| Tahap | Bobot | Rincian sebelumnya | Sekarang | Kontribusi sebelumnya → sekarang | Delta berbobot |
|---|---:|---:|---:|---:|---:|
| 1. Fondasi/core | 25% | 75% | 75% | 18,75 → 18,75 | 0,00 |
| 2. Backend produk | 10% | 79% | 79% | 7,90 → 7,90 | 0,00 |
| 3. Frontend buyer | 15% | 94% | 96% | 14,10 → 14,40 | +0,30 |
| 4. Harga server/quote | 10% | 83% | 83% | 8,30 → 8,30 | 0,00 |
| 5. Simpan/ownership/share | 10% | 97% | 98% | 9,70 → 9,80 | +0,10 |
| 6. Checkout/produksi/Drive | 20% | 46% | 48% | 9,20 → 9,60 | +0,40 |
| 7. QA/rilis/mobile | 10% | 40% | 40% | 4,00 → 4,00 | 0,00 |
| **Subtotal tujuh tahap** | **100%** | — | — | **71,95 → 72,75** | **+0,80** |
| Saldo baseline sementara Owner, belum teralokasi | — | — | — | 0,15 → 0,15 | 0,00 |
| **Total sementara sesuai baseline Owner** | — | **72,10%** | **72,90%** | **72,10 → 72,90** | **+0,80** |

Formula delta tetap Σ(bobot × perubahan tahap / 100). Total sementara = baseline Owner 72,10 + delta source baru 0,80. Saldo 0,15 bukan kredit putaran ini dan tidak dihitung ulang sebagai fitur. Dibedakan dari subtotal tahap agar laporan tidak mengaku rincian historis yang belum ada.

## Dasar kredit implementasi baru, tanpa duplikasi

- Frontend +2 poin tahap = +0,30 berbobot: (1) fallback garment dengan lengan/kerah/sisi/warna yang tidak mengganti print-plane, mempertahankan merchant photo; (2) navigasi mobile preview/kontrol dan target/form yang lebih mudah dipakai. Source startup/switch-side/color dan PHP render diperiksa; visual source baru di browser belum VERIFIED.
- Simpan +1 poin tahap = +0,10 berbobot: owner-authorized short readback memilih hanya preview terikat revisi server, tanpa path/file authority dari client; expiry, stale revision, foreign owner dan corrupt-reader refusal diuji. Tidak dihitung lagi sebagai share/email atau checkout selesai.
- Checkout +2 poin tahap = +0,40 berbobot: (1) frontend menghasilkan preview sisi kosong yang diperlukan order tanpa menambah biaya/objek cetak; (2) quantity boundary menerima tipe integral native Woo tanpa membuka qty pecahan/mismatch, sebelum metadata order ditulis. Ini compatibility source, bukan E2E order PASS.
- Pricing #51 dan adapter/session/order yang sudah ada di base #50 tidak mendapat kredit ulang. Jumlah lint/tests/browser screenshot dan pemulihan source lama tidak menambah progres. QA tahap tetap 40%.
- Persen adalah estimasi scope implementation yang terbatas pada item di atas. Tidak mengklaim deploy/runtime baru; nilai tidak dinaikkan dari rasio assertion.

## Status bukti

DECIDED: baseline sementara Owner, bobot, izin development terisolasi dan staging QA, larangan deploy/merge/production/DTF/Drive. IMPLEMENTED: perbaikan mobile/mockup, short preview readback, blank-side preview dan normalisasi kuantitas order. VERIFIED: lint/tes bounded source baru; browser staging berhasil draft/master/reopen/server quote. BLOCKED: preview staging 422 dan cart yang bergantung padanya; checkout/order runtime tidak diuji.

Laporan lengkap, SHA dan hasil tes: [studio-mobile-checkout-development-20261123.md](../qa/studio-mobile-checkout-development-20261123.md). Rincian lama 71,95% dan laporan koordinasi sebelumnya tetap historis pada Git, tidak menjadi baseline aktif kedua.

GO review source/Draft PR. NO-GO merge/deploy/production atau klaim end-to-end checkout/print-ready. DTF dan Drive tidak diaktifkan.
