# SANRO Studio — baseline aktif development

24 November 2026 WIB. Checkpoint **87,50%** pada `c04653aae4b524fe8283228abafc43b2cb1e8541`; hasil putaran kuota terbatas **89,50% (+2,00 poin berbobot)** pada commit yang membawa ledger ini. Bobot SANRO-7-v1 tetap. IMPLEMENTED source development, bukan VERIFIED runtime atau persentase tes.

| Tahap | Bobot | Sebelumnya | Sekarang | Kontribusi sebelumnya → sekarang | Delta berbobot |
|---|---:|---:|---:|---:|---:|
| 1. Fondasi/core |25%|85%|93%|21,25 →23,25|+2,00|
| 2. Backend produk/admin |10%|94%|94%|9,40 →9,40|0,00|
| 3. Frontend buyer |15%|96%|96%|14,40 →14,40|0,00|
| 4. Harga server/quote |10%|93%|93%|9,30 →9,30|0,00|
| 5. Simpan/ownership/share |10%|98%|98%|9,80 →9,80|0,00|
| 6. Checkout/produksi/Drive |20%|96%|96%|19,20 →19,20|0,00|
| 7. QA/rilis/mobile |10%|40%|40%|4,00 →4,00|0,00|
| **Subtotal tujuh tahap** |**100%**|—|—|**87,35 →89,35**|**+2,00**|
| Saldo Owner historis belum teralokasi |—|—|—|0,15 →0,15|0,00|
| **Total aktif development** |—|**87,50%**|**89,50%**|**87,50 →89,50**|**+2,00**|

Formula delta Σ(bobot×perubahan tahap/100). Saldo0,15 adalah rekonsiliasi historis72,10 vs71,95 pada2d945be; tidak dialokasikan ulang. Ini satu baseline aktif; laporan lama tetap riwayat.

## Kredit putaran kuota terbatas

Core +8 tahap =+2,00 untuk **implementasi replacement installer**, bukan ZIP/preflight/dokumentasi: validasi identitas WordPress staging dan active plugin dengan writer OFF (+2); approval terikat source/archive/digest target dengan re-probe sebelum mutation (+2); penggantian existing directory di bawah lock dan digest guard tanpa reinstall/activation/seed/config overwrite (+2); rollback pada rename/post-install hash failure, folder lama ditahan di luar document root (+2). Tidak mengklaim10% core selesai atau membulatkan ke90. Installer berada di tools agar hanya dipanggil operator setelah approval; tidak mengubah lifecycle plugin atau mengaktifkan kode pada WordPress.

Tes9, lint2 Python, checkpoint dan rebuild ZIP **tidak** menambah kredit. QA tetap40%. Semua fitur aplikasi sebelumnya tetap pada nilai checkpointc04653a, tidak dihitung ulang. Uji filesystem sintetis bukan apply atau FPM/runtime E2E. Gangguan OS di antara dua rename masih perlu pemulihan dari folder lama yang ditahan; bukan transaksi filesystem crash-atomic.

DECIDED: Development First, tanpa Full Audit sesi ini, tanpa deploy/merge/worker lain; kebijakan bisnis Owner tetap. IMPLEMENTED: installer guarded replacement + rollback source. VERIFIED: sembilan targeted synthetic tests; **NOT TESTED** Hostinger/FPM/real WP CLI/apply/runtime baru. Lihat [checkpoint handoff](../qa/studio-hermes-handoff-20261124.md).

Target90% belum tercapai; tidak memaksakan sisa0,50 melalui tes/dokumentasi. HTTP422 runtime lama belum terbukti selesai; pajak/fee/kupon OPEN dan repeat5% eligibility belum diimplementasikan. Generator/Drive default OFF. GO source review dan handoff; NO-GO deploy/merge/production/order DB/writer activation/final release. Full Audit **tidak dimulai**.

## Kredit checkpoint sebelumnya (historis, bukan baseline lain)

### Feature completion4, tanpa penghitungan ulang

- Core +6 tahap =+1,50: inventory native/module/gate yang mengikuti source sesungguhnya (+2); endpoint GET diagnostik admin dengan capability+REST nonce, PHP/SAPI/extensions/memory/flags tanpa path/secret (+2); identitas source commit/digest di paket dan REST untuk membedakan kandidat/runtime lama (+2). Builder, ZIP, dokumentasi, lint dan preflight installer tidak menghasilkan kredit tambahan atau menaikkan QA.
- Backend +9 tahap =+0,90: pemilihan preset Cotton/Tunic (+2); editor tabel harga khusus per produk dengan validasi size/empat kategori/IDR dan update atomik pada metadata produk (+5); konfigurasi invalid/profile mismatch/native variation yang tidak didukung ditolak sebelum editor/quote (+2). Tidak ada seed/override saat upgrade; produk lain tetap independen.
- Harga server +10 tahap =+1,00: matriks Tunic Owner dan lengan3/4 tetap pada quote authoritative (+6); pemakaian override sah dan revision hash pada quote/cart terkunci (+4). UI estimate dan label PDF/admin adalah dependency fitur ini, tanpa kredit frontend/produksi ganda. Tidak menghitung kembali kalkulator Cotton/#51.
- Checkout/produksi/Drive +3 tahap =+0,60: guard kupon termasuk nilai nol, fee line nol, pajak/discount dan order coupon pada payment/production (+1); persisted running-job + watchdog crash recovery dengan deadline-specific scheduler/renewal lease dan retry terbatas (+1); aksi retry admin capability+nonce setelah perbaikan, tetap gated dan idempotent (+1). Renderer/memori/font/PDF/hierarchy/upload engine yang telah ada tidak dihitung ulang.
