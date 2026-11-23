# SANRO Studio — development integration

23 November 2026 WIB. **Progres sementara: 72,10% → 72,90%, +0,80 poin**. Bobot tujuh tahap tetap. Satu sumber angka dan rekonsiliasi saldo baseline Owner 0,15 yang belum teralokasi: [docs/ledger/PROGRESS-BASELINE.md](docs/ledger/PROGRESS-BASELINE.md).

Source development terisolasi berbasis #50 SHA 45a7917c320403d1bc0d18caf7ee3398a3ecb416, dengan pricing terbaru #51 e9cb4679247f30223c33e51ddac1b2405dff961c sudah tercakup pada base. Tidak mengulang import pricing atau instalasi #50.

IMPLEMENTED baru: bentuk fallback kaos depan/belakang, navigasi mobile, short preview readback owner/revision-bound, preview blank side tanpa biaya tambahan, dan native order quantity normalization.

Cloud Browser staging: editor/draft/master/reopen/server quote PASS, total Rp273.000 untuk M×2 + L×1 A3+. Preview readback 422; cart berhenti di dependency tersebut. Checkout/order/payment NOT TESTED. Fix source baru belum deployed atau browser runtime VERIFIED.

[Laporan hasil, test dan batas](docs/qa/studio-mobile-checkout-development-20261123.md). WordPress read-only mengonfirmasi SANRO Studio aktif, QA lama inactive. Tidak mengubah plugin/settings atau runtime flag.

GO review development. NO-GO merge/deploy/production/DTF/Drive. Riwayat koordinasi sebelumnya adalah snapshot lama.
