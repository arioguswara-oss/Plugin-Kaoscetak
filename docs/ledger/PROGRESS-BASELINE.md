# SANRO Studio — satu baseline resmi development

24 November2026 WIB. Sebelumnya **89,50%** pada `5c549432baaf3be93a51f31eeb8a018bccbcbce5`; sekarang **90,50% (+1,00 poin berbobot)** pada commit yang membawa ledger ini. Bobot SANRO-7-v1 tidak berubah. Persentase adalah estimasi scope IMPLEMENTED source, bukan rasio tes atau kesiapan rilis/runtime.

| Tahap | Bobot | Sebelumnya | Sekarang | Kontribusi sebelumnya → sekarang | Delta berbobot |
|---|---:|---:|---:|---:|---:|
| 1. Fondasi/core |25%|93%|95%|23,25 →23,75|+0,50|
| 2. Backend produk/admin |10%|94%|94%|9,40 →9,40|0,00|
| 3. Frontend buyer |15%|96%|96%|14,40 →14,40|0,00|
| 4. Harga server/quote |10%|93%|96%|9,30 →9,60|+0,30|
| 5. Simpan/ownership/share |10%|98%|98%|9,80 →9,80|0,00|
| 6. Checkout/produksi/Drive |20%|96%|97%|19,20 →19,40|+0,20|
| 7. QA/rilis/mobile |10%|40%|40%|4,00 →4,00|0,00|
| **Subtotal tujuh tahap** |**100%**|—|—|**89,35 →90,35**|**+1,00**|
| Saldo Owner historis belum teralokasi |—|—|—|0,15 →0,15|0,00|
| **Total resmi development** |—|**89,50%**|**90,50%**|**89,50 →90,50**|**+1,00**|

Formula delta Σ(bobot×perubahan tahap/100). Saldo0,15 tetap merupakan rekonsiliasi historis72,10 vs71,95 checkpoint2d945be, tidak dibagi ulang. Laporan lama/Git adalah riwayat, bukan baseline aktif kedua.

## Kredit baru tanpa duplikasi

- Core +2 tahap =+0,50: journal intent persisten/fsync dan recovery proses terputus yang aman/idempotent (+1); native Woo authenticated short user-ID binding serta CAS owner-proof migration guest→login untuk draft belum terkunci (+1). Penggantian folder normal/rollback exception dari checkpoint lama tidak dihitung kembali.
- Harga server +3 tahap =+0,30: email challenge owner-only/CAS/single-use, verified-email checkout match, paid-history objek Woo tervalidasi dan paginated lookup (+2); aturan5% per pcs dengan half-up yang sudah ada di source, fresh cart recomputation dan server quote (+1). UI verifikasi dan proof ownership adalah dependency fitur ini, tidak mendapat kredit frontend/simpan terpisah.
- Checkout/produksi/Drive +1 tahap =+0,20: bukti repeat/net/base per ukuran dipin bersama quote dan item, diperiksa saat lock/pay, direkonsiliasi pada manifest/PDF/admin; paid snapshot tidak dihitung ulang dari katalog atau diskon saat produksi. Tidak menghitung kembali renderer, font, private hierarchy, uploader atau hashing engine sebelumnya.
- Tes, ZIP, dokumentasi dan checkpoint tidak menaikkan angka. QA tetap40%; admin label dan UI kontrol repeat tidak dihitung ganda.

DECIDED:5% per pcs termasuk satuan dan verified customer history; pajak/fee/kupon tetapOPEN. Detail eligibility/rounding yang dipakai berasal dari source lama `includes/repeat.php`, `api.php`, `sharing.php` dan tes repeat di plugin Kaoscetak, bukan aturan promosi baru.

IMPLEMENTED: delta di atas. VERIFIED:10 actual-process crash/recovery tests,9 installer tests,10 authenticated/session checks,44 repeat checkout/production checks,11 endpoint/nonce/OFF checks, bootstrap serta changed-source lint. WC/mail/SQL adalah doubles; PDF/preview bytes dan filesystem/process exit nyata. Prerequisite17 checkout checks dipanggil fixture, bukan kredit/total tambahan berulang.

**Ambang90% tercapai; penambahan fitur dihentikan. Full Audit BELUM DIMULAI dan menunggu instruksi Owner berikutnya.** [Checkpoint/paket lanjut](../qa/studio-hermes-handoff-20261124.md).

NOT TESTED: WordPress/Hostinger source baru, PHP-FPM/apply/recovery hosting, SMTP email nyata, gateway/Drive akun nyata, physical print. HTTP422 runtime lama belum VERIFIED selesai. Generator/Drive defaultOFF. GO source review dan checkpoint siap Full Audit; NO-GO deploy/merge/production/order DB/writer activation/final release. Hermes/Codex tidak dijalankan.
