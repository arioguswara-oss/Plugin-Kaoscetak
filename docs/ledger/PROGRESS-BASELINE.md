# SANRO Studio — baseline aktif development

23 November 2026 WIB. Checkpoint resmi **80,50%** pada `d704b32f31a7e5ce7d904826dcc3f3f282b81e94`; hasil feature-completion3 **83,50% (+3,00 poin berbobot)**. Bobot SANRO-7-v1 tidak berubah. Ini estimasi scope IMPLEMENTED pada development, bukan runtime/release readiness.

| Tahap | Bobot | Sebelumnya | Sekarang | Kontribusi sebelumnya → sekarang | Delta berbobot |
|---|---:|---:|---:|---:|---:|
| 1. Fondasi/core |25%|75%|79%|18,75 →19,75|+1,00|
| 2. Backend produk/admin |10%|83%|85%|8,30 →8,50|+0,20|
| 3. Frontend buyer |15%|96%|96%|14,40 →14,40|0,00|
| 4. Harga server/quote |10%|83%|83%|8,30 →8,30|0,00|
| 5. Simpan/ownership/share |10%|98%|98%|9,80 →9,80|0,00|
| 6. Checkout/produksi/Drive |20%|84%|93%|16,80 →18,60|+1,80|
| 7. QA/rilis/mobile |10%|40%|40%|4,00 →4,00|0,00|
| **Subtotal tujuh tahap** |**100%**|—|—|**80,35 →83,35**|**+3,00**|
| Saldo baseline Owner historis, belum teralokasi |—|—|—|0,15 →0,15|0,00|
| **Total aktif development** |—|**80,50%**|**83,50%**|**80,50 →83,50**|**+3,00**|

Formula delta Σ(bobot×perubahan tahap/100). Saldo0,15 berasal dari rekonsiliasi historis72,10 vs rincian71,95 pada checkpoint2d945be; dipertahankan transparan, bukan kredit putaran ini atau alokasi tahap rekaan. Riwayat laporan/Git tidak menjadi baseline aktif kedua.

## Dasar kredit baru tanpa duplikasi

- Core +4 tahap =+1,00: saved-order identity/quote/preview integrity dipakai bersama pada lifecycle retry, callback pembayaran dan worker produksi (+2 tahap); atomic token/expiry server leases dengan insert-only/CAS renew/release serta takeover pemilik lama ditolak (+2 tahap). Pemakaian kedua primitive di fitur lain tidak diberi kredit core kedua kali.
- Admin +2 tahap =+0,20: status package seluruh pesanan, receipt parsial/stale, retry/pending/blocked dipisah dari human print approval. Reader/URL privat/preview dan PDF Unicode lama tidak dihitung ulang.
- Checkout/produksi/Drive +9 tahap =+1,80: native shipping snapshot dan pin pembayaran (+3); provenance per sisi + zero-render reuse (+2); asynchronous bounded upload retry/recovery (+2); hashed manifest + semua-item receipts/completion seal (+2). Ini estimasi scope deliverable source, bukan rasio PASS.
- Kalkulator/tabel #51, renderer A3+/Unicode/PDF, hierarchy, uploader original, quote/order lock dasar dan mobile sebelumnya tetap dependency. Tes/packaging/setup tidak menaikkan QA; tetap40%. Tidak mengkalibrasi bobot atau mengejar90% secara administratif.

DECIDED: baseline/bobot, Development First/Full Audit90%, runtime OFF, tanpa deploy/merge/order DB. IMPLEMENTED: delta di atas. VERIFIED: focused source checks, actual GD A3+/reuse serta SQL/Woo/HTTP doubles sesuai [laporan putaran](../qa/studio-feature-completion-3-20261123.md). Real WP/gateway/Drive/print fisik NOT TESTED.

Stage6 belum100%: Blocks/variasi/mixed-design, policy pajak/fees/coupon/repeat discount dan integration akun/runtime belum tuntas. Kandidat ZIP reproducible adalah bahan review, bukan bukti E2E atau izin deploy. GO review/development; NO-GO deploy/merge/production/aktivasi writer/final release.
