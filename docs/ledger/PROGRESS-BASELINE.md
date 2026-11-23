# SANRO Studio — baseline aktif development

23 November 2026 WIB. Checkpoint resmi **83,50%** pada `a143703581e003e116c5550acf1e89a3d9c7e5b9`; hasil feature-completion4 **87,50% (+4,00 poin berbobot)** pada commit yang membawa ledger ini. Bobot SANRO-7-v1 tetap. Angka adalah estimasi scope IMPLEMENTED development; bukan rasio tes, runtime VERIFIED, atau kesiapan rilis.

| Tahap | Bobot | Sebelumnya | Sekarang | Kontribusi sebelumnya → sekarang | Delta berbobot |
|---|---:|---:|---:|---:|---:|
| 1. Fondasi/core |25%|79%|85%|19,75 →21,25|+1,50|
| 2. Backend produk/admin |10%|85%|94%|8,50 →9,40|+0,90|
| 3. Frontend buyer |15%|96%|96%|14,40 →14,40|0,00|
| 4. Harga server/quote |10%|83%|93%|8,30 →9,30|+1,00|
| 5. Simpan/ownership/share |10%|98%|98%|9,80 →9,80|0,00|
| 6. Checkout/produksi/Drive |20%|93%|96%|18,60 →19,20|+0,60|
| 7. QA/rilis/mobile |10%|40%|40%|4,00 →4,00|0,00|
| **Subtotal tujuh tahap** |**100%**|—|—|**83,35 →87,35**|**+4,00**|
| Saldo baseline Owner historis, belum teralokasi |—|—|—|0,15 →0,15|0,00|
| **Total aktif development** |—|**83,50%**|**87,50%**|**83,50 →87,50**|**+4,00**|

Formula delta Σ(bobot×perubahan tahap/100). Saldo0,15 berasal dari rekonsiliasi historis72,10 vs rincian71,95 pada checkpoint2d945be; dipertahankan transparan, tidak dialokasikan secara rekaan dan tidak menghasilkan kredit baru. Riwayat Git/laporan bukan baseline aktif lain.

## Kredit implementasi putaran ini, tanpa duplikasi

- Core +6 tahap =+1,50: inventory native/module/gate yang mengikuti source sesungguhnya (+2); endpoint GET diagnostik admin dengan capability+REST nonce, PHP/SAPI/extensions/memory/flags tanpa path/secret (+2); identitas source commit/digest di paket dan REST untuk membedakan kandidat/runtime lama (+2). Builder, ZIP, dokumentasi, lint dan preflight installer tidak menghasilkan kredit tambahan atau menaikkan QA.
- Backend +9 tahap =+0,90: pemilihan preset Cotton/Tunic (+2); editor tabel harga khusus per produk dengan validasi size/empat kategori/IDR dan update atomik pada metadata produk (+5); konfigurasi invalid/profile mismatch/native variation yang tidak didukung ditolak sebelum editor/quote (+2). Tidak ada seed/override saat upgrade; produk lain tetap independen.
- Harga server +10 tahap =+1,00: matriks Tunic Owner dan lengan3/4 tetap pada quote authoritative (+6); pemakaian override sah dan revision hash pada quote/cart terkunci (+4). UI estimate dan label PDF/admin adalah dependency fitur ini, tanpa kredit frontend/produksi ganda. Tidak menghitung kembali kalkulator Cotton/#51.
- Checkout/produksi/Drive +3 tahap =+0,60: guard kupon termasuk nilai nol, fee line nol, pajak/discount dan order coupon pada payment/production (+1); persisted running-job + watchdog crash recovery dengan deadline-specific scheduler/renewal lease dan retry terbatas (+1); aksi retry admin capability+nonce setelah perbaikan, tetap gated dan idempotent (+1). Renderer/memori/font/PDF/hierarchy/upload engine yang telah ada tidak dihitung ulang.

DECIDED: spesifikasi harga yang sudah disetujui, bobot, guard default OFF dan batas tindakan Owner. IMPLEMENTED: delta source di atas. VERIFIED: targeted source smoke sesuai [laporan4](../qa/studio-feature-completion-4-20261123.md), termasuk simulasi native Woo/SQL/Drive dan PDF bytes sungguhan. Runtime Hostinger/gateway/akun Drive/cetak fisik tetap NOT TESTED untuk source baru.

Belum90%; Full Audit belum dimulai. Pajak/fee/kupon masih OPEN, bukan diputuskan menjadi0 permanen. Repeat5% DECIDED tetapi verifikasi eligibility/diskonnya belum diimplementasikan. Native variations/Blocks/mixed-design enhancement ditunda sampai ada kebutuhan V1 yang konkret; unsupported tetap fail-closed. HTTP422 runtime lama belum terbukti selesai. GO development/review kandidat; NO-GO deploy/merge/production/aktivasi writer/final release.
