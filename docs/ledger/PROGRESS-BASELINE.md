# Baseline resmi SANRO Studio — Integration Coordination Update 2

Tanggal 23 November 2026 WIB. **Baseline resmi 71,95% → 71,95%; delta 0,00 poin.** Bobot metode SANRO-7-v1 tidak diubah atau dikalibrasi ulang. File ini satu-satunya sumber angka resmi; STATUS.md dan indeks ledger hanya menunjuk ke sini.

Baseline mengukur estimasi penyelesaian scope source donor yang telah ditetapkan Owner, bukan jumlah assertion, kesiapan production, atau persentase source yang sudah merged. Integration branch saat dibaca masih 0d981b634888a5389bc394b93a13a4177090cc76 tanpa plugin Studio; feature PR tetap terpisah dan belum merged.

## Perhitungan tujuh tahap

| Tahap | Bobot tetap | Sebelum | Sekarang | Kontribusi sebelum → sekarang | Delta total |
|---|---:|---:|---:|---:|---:|
| 1. Fondasi core/native bootstrap | 25% | 75% | 75% | 18,75% → 18,75% | 0,00 |
| 2. Backend produk/mockup/area | 10% | 79% | 79% | 7,90% → 7,90% | 0,00 |
| 3. Frontend buyer | 15% | 94% | 94% | 14,10% → 14,10% | 0,00 |
| 4. Harga server/quote | 10% | 83% | 83% | 8,30% → 8,30% | 0,00 |
| 5. Simpan/ownership/share | 10% | 97% | 97% | 9,70% → 9,70% | 0,00 |
| 6. Checkout/order/produksi/Drive | 20% | 46% | 46% | 9,20% → 9,20% | 0,00 |
| 7. QA/rilis/mobile/package | 10% | 40% | 40% | 4,00% → 4,00% | 0,00 |
| **Total** | **100%** | **71,95%** | **71,95%** | **71,95% → 71,95%** | **0,00 poin** |

Formula tetap: total = Σ(bobot tahap × penyelesaian tahap / 100). Setiap delta total tahap = bobot tetap × (progres sekarang − progres sebelumnya) / 100. Semua delta tahap pada putaran ini nol; tidak ada penurunan atau reset baseline.

## Mengapa tidak menambah angka dari bukti terbaru

Readback #46–#51 dan Git diff dilakukan terhadap checkpoint update sebelumnya: #50 7d097fe → 0b3004b dan pricing 7a73279 → #51 b39d13f. Ada perubahan source nyata, dibedakan dari penyelesaian scope baru yang dapat diberi kredit:
- #50 memasang hook native/admin/private-storage/editor/classic checkout pada bootstrap baru. Ini kemajuan penggabungan source, tetapi modul fungsi beserta hook tersebut sudah ada pada donor #45 yang menjadi sumber baseline. Seluruh folder plugins/sanro-studio sama dengan donor #45 kecuali file bootstrap. Restore companion KLD/ops/scripts/prototypes dan callback lama tidak dihitung sebagai fitur SANRO baru.
- #51 menambah pricing-quote.php dan dua fungsi kontrak yang benar-benar baru sebagai source, tetapi kemampuan pokok tarif resmi, geometri, satu/dua sisi, lengan sekali, quote multisize dan cart contract sudah ada di quote.php/cart-contract.php donor. Fungsi baru belum dipanggil bridge #50; schema keluaran belum sama. Tidak menggandakan kredit tahap harga atau checkout untuk API paralel.
- product-pricing.php dan pricing.php identik antara donor/#50/#51; tests/run-all.sh dan studio-assets.test.cjs di #51 identik dengan #48. Tidak diberi kredit dua kali.
- 176 lint, 184 PASS/1 EPERM, parity alternatif 400/400 dan 688 assertion memperkuat evidence offline pada scope masing-masing. Tes/dependency dipulihkan bukan unit fitur baru. Tidak menggunakan rasio PASS untuk mengubah persentase tahap.
- Tidak ada capability tahap yang terbukti selesai melampaui capaian yang sudah masuk baseline lama. Karena itu angka dipertahankan; bukan mensyaratkan deploy/E2E untuk semua kredit implementasi. Implementasi baru yang menyelesaikan gap jelas dapat dinilai pada source/check proporsional tanpa menunggu production.

| Tahap | Penilaian bukti terbaru |
|---|---|
| 1. Fondasi core/native bootstrap | Hook native dipasang ulang di bootstrap #50; modul/callback sudah ada di donor #45. Runtime native belum VERIFIED. |
| 2. Backend produk/mockup/area | Profil/admin/settings dipulihkan; product-pricing identik, belum ada profil tunik/override baru. |
| 3. Frontend buyer | Wiring buyer opt-in dipulihkan; implementasi editor selain bootstrap identik dengan donor #45. |
| 4. Harga server/quote | Kontrak baru #51 IMPLEMENTED; aturan inti/quote multisize sudah ada. Adapter #50↔#51 belum tersambung. |
| 5. Simpan/ownership/share | Dependency draft/master dipulihkan; tidak ada implementasi native baru share/email/repeat yang terbukti. |
| 6. Checkout/order/produksi/Drive | Hook classic opt-in dipasang; hardening #46 dan satu alur order/artifact terpadu belum selesai. |
| 7. QA/rilis/mobile/package | Evidence offline bertambah; dependency lama dipulihkan. Tidak menyamakan test count dengan fitur/rilis baru. |

## DECIDED / IMPLEMENTED / VERIFIED

- **DECIDED:** bobot tujuh tahap, tarif kategori/sisi/lengan, ownership Hermes/Cloud/Work dan jalur integration tunggal.
- **IMPLEMENTED pada feature branch:** bootstrap native opt-in #50 0b3004b; kontrak quote dan validasi cart-line read-only #51 b39d13f. Belum tergabung menjadi satu alur. Flags OFF tidak berarti source belum diimplementasikan.
- **VERIFIED melalui inspeksi Work:** HEAD/diff/blob/overlap/schema dan hasil hitung ledger. Evidence eksekusi offline berasal dari laporan committed worker pada SHA yang disebutkan; Work tidak menjalankan ulang PHP/JS/E2E.
- **Belum VERIFIED:** gabungan native Woo/editor/cart/order, master gambar/admin artifact nyata, variasi/pajak/kupon/stok di runtime, ACK sesi worker eksternal. GitHub Actions tidak disertifikasi dari hasil lokal.

## Aturan kredit berikutnya

Gunakan bobot tetap; setiap kenaikan harus menunjuk gap fitur yang selesai, commit/diff dan pemeriksaan proporsional. Pisahkan penyelesaian source, pengujian offline, integrasi dan runtime; jangan mengalikan tes/PR/restorasi file menjadi progres. Jangan menghitung callback, tabel tarif atau runner yang sama pada dua branch. Jangan menaikkan angka hanya agar target 90% tercapai.

Selisih terhadap 90% adalah **18,05 poin berbobot**. Lima pekerjaan di INTEGRATION-20261123.md adalah arah penutupan gap, bukan janji otomatis mencapai 90% atau alokasi persen baru. Setelah deliverable benar-benar selesai, nilai kontribusi dengan bobot ini; full audit baru saat implementasi mendekati 90%.

## Riwayat yang dipertahankan

71,95% ditetapkan dari rincian tahap 75/79/94/83/97/46/40, lalu ditegaskan Owner pada NEXT. Source historis docs/architecture/sanro-studio-roadmap.md donor #45 memuat rincian tersebut. Angka 89,4%, 90% atau audit delapan tahap 35% di arsip adalah HISTORIS/SUPERSEDED, bukan baseline aktif dan tidak dipakai untuk perhitungan update ini. Tidak mengubah keputusan lama atau melakukan kalibrasi ulang.

GO koordinasi dan kelanjutan development source terisolasi oleh pemilik. NO-GO merge/deploy/production, order baru atau klaim rilis final pada tugas ini.

