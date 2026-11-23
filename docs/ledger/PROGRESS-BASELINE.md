# SANRO Studio — baseline aktif development

23 November2026 WIB. Baseline resmi Owner/checkpoint **75,50%** pada `3d989f2edd0f38c13ed3980d8b9b3cbf84104fcf`. Hasil implementasi putaran feature-completion2: **80,50% (+5,00 poin berbobot)**. Bobot SANRO-7-v1 tetap. Ini estimasi scope IMPLEMENTED pada development, bukan kesiapan rilis/runtime VERIFIED.

| Tahap | Bobot | Sebelumnya | Sekarang | Kontribusi sebelumnya → sekarang | Delta berbobot |
|---|---:|---:|---:|---:|---:|
| 1. Fondasi/core |25%|75%|75%|18,75 →18,75|0,00|
| 2. Backend produk/admin |10%|81%|83%|8,10 →8,30|+0,20|
| 3. Frontend buyer |15%|96%|96%|14,40 →14,40|0,00|
| 4. Harga server/quote |10%|83%|83%|8,30 →8,30|0,00|
| 5. Simpan/ownership/share |10%|98%|98%|9,80 →9,80|0,00|
| 6. Checkout/produksi/Drive |20%|60%|84%|12,00 →16,80|+4,80|
| 7. QA/rilis/mobile |10%|40%|40%|4,00 →4,00|0,00|
| **Subtotal tujuh tahap** |**100%**|—|—|**75,35 →80,35**|**+5,00**|
| Saldo baseline Owner historis, belum teralokasi |—|—|—|0,15 →0,15|0,00|
| **Total aktif development** |—|**75,50%**|**80,50%**|**75,50 →80,50**|**+5,00**|

Formula Σ(bobot×delta tahap/100). Saldo0,15 adalah rekonsiliasi historis baseline Owner72,10 terhadap rincian lama71,95 yang sudah dibuka pada checkpoint2d945be; bukan kredit fitur baru. Tidak mengarang alokasi historis dan tidak mengubah bobot untuk mencapai target. Laporan/source lama tetap histori, bukan baseline aktif kedua.

## Kredit implementasi baru

- Backend/admin +2 tahap =+0,20: read model kesiapan item menampilkan paid/admin-ready, locked amounts/design, preview dua sisi, kesiapan font, generator/Drive nonaktif serta tidak memberi persetujuan cetak otomatis. Tautan order privat terverifikasi merupakan bagian layout Drive di tahap6, tidak dihitung dua kali.
- Checkout/produksi/Drive +24 tahap =+4,80: lifecycle retry order yang sama dan pemulihan binding/save dengan quote tersimpan (+6 tahap); kanvas A3+ resource-safe, multiline supported-Unicode, PNG/JPEG/alpha dan validasi output nyata depan/belakang (+8); hierarchy root-order/Produksi/item dan recovery/ancestor-private verification (+5); embedded-font Unicode PDF/ActualText/pagination dan JPEG/PNG/WebP guide (+5).
- Font browser bersama adalah dependency pipeline teks, tidak menambah tahap frontend kedua kali. Existing quotes/prices #51, coordinator/renderer/artifact primitives, preview upload, cart metadata dan uploader original lama tidak diberi kredit ulang. Pengujian, font/runtime setup, dan jumlah assertions tidak menambah QA; tetap40%.
- Subitem angka adalah estimasi scope terbatas pada deliverable source tersebut. Tidak mengklaim semua fitur checkout/produksi selesai; Blocks/variasi/policy pajak-diskon kompleks, complex shaping, runtime akun nyata serta approval hasil cetak tetap terbuka.

DECIDED: baseline resmi75,50%, fixed weights, Development First/Full Audit90%, runtime writersOFF. IMPLEMENTED: delta source di atas. VERIFIED: PHP lint/focused flow/GD/FreeType/A3+/PDF bytes dan Unicode extraction nyata, dengan Woo/options/HTTP sintetis. Live source browser/checkout/gateway/Drive/physical print NOT TESTED. HTTP422 checkpoint staging belum runtime VERIFIED selesai.

[Laporan putaran dan batas](../qa/studio-feature-completion-2-20261123.md). GO review/development. NO-GO merge/deploy/production/aktivasi generator atau Drive/claim print-ready.
