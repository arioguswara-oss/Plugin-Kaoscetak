# SANRO Studio — 8 tahap dan progres

Keputusan Owner 18 November 2026: gunakan dua konsep visual yang telah disetujui untuk backend dan buyer; target satu plugin SANRO Studio aktif dengan core berlisensi internal, sehingga kedua plugin editor lama dapat dinonaktifkan setelah QA. Harga resmi Kaoscetak dipertahankan. Screenshot hasil implementasi sebenarnya harus ditampilkan sebelum instalasi produksi. Konsep visual tidak dianggap screenshot implementasi.

## Checkpoint koordinasi terbaru — 18 November 2026

> **Acuan laporan operasional terbaru (Kordinasi 2).** Tabel 35% di bawah bagian ini adalah snapshot audit berbobot yang lebih lama, **bukan** status koordinasi terkini. Jangan menggunakannya untuk mereset progres saat berganti chat. Angka 90% belum direkonsiliasi dengan formula bobot audit lama dan **bukan** release readiness.

- **Keseluruhan dilaporkan: 90%** (tetap dari checkpoint Kordinasi 2).
- **Fondasi core: 73% → 75% (+2 poin)** pada putaran staging readiness.
- Per tahap dari laporan checkpoint Owner:

| Tahap | Progres laporan terbaru | Klasifikasi |
| --- | ---: | --- |
| Fondasi core | 75% | IMPLEMENTED sebagian; runtime nyata belum VERIFIED |
| Backend produk | 79% | IMPLEMENTED sebagian |
| Frontend buyer | 94% | IMPLEMENTED sebagian |
| Integrasi harga | 83% | IMPLEMENTED sebagian |
| Simpan/share | 97% | IMPLEMENTED sebagian |
| Checkout/Drive | 46% | IMPLEMENTED sebagian; checkout production belum dibuka |
| QA/rilis | 40% | VERIFIED sebagian; staging/browser belum lulus penuh |

Commit referensi yang dilaporkan Owner: `52671846` (ready_for_staging), `a4d1a13f` (staging test opt-in), `1e0170fb` (isolasi runtime legacy), `bf0f5914` (simulasi WooCommerce untuk readiness). Periksa SHA dan HEAD branch aktual sebelum menganggap seluruhnya VERIFIED.

**Gate:** staging mensyaratkan komponen native, WooCommerce dimuat, dan fitur buyer diaktifkan secara eksplisit. Staging-ready tidak berarti production-ready. Checkout dan production tetap tertutup. Tidak boleh deaktivasi plugin lama di production, merge, deploy, atau mengubah database live tanpa persetujuan Owner.

**Prioritas selanjutnya:** (1) rekonsiliasi evidence dan HEAD, (2) PHP lint/tes unit HEAD tanpa GitHub Actions berbayar, (3) staging terisolasi dengan data sintetis, (4) validasi draft → cart → checkout server-side, (5) screenshot backend dan buyer implementasi nyata sebelum instalasi.

**Aturan pembaruan:** persentase hanya naik berdasarkan implementasi baru yang memiliki bukti commit dan pengujian; laporkan DECIDED, IMPLEMENTED, VERIFIED secara terpisah. Bila angka koordinasi dan audit berbeda, pertahankan keduanya dengan label metodologi, bukan mengganti diam-diam.

---

## Snapshot audit berbobot terdahulu (historis; bukan checkpoint terbaru)

Persentase di bawah adalah estimasi pekerjaan berbobot, bukan persentase tes atau bukti kesiapan rilis.

| Tahap | Hasil yang dituju | Bobot | Penyelesaian tahap | Kontribusi |
| --- | --- | ---: | ---: | ---: |
| 1 | Kebutuhan dan tampilan backend/buyer disepakati | 10% | 100% | 10% |
| 2 | Fondasi dan core internal tanpa plugin editor lama aktif | 25% | 40% | 10% |
| 3 | Backend produk, mockup, warna, size, dan area cetak | 10% | 35% | 3.5% |
| 4 | Frontend buyer sesuai konsep | 15% | 30% | 4.5% |
| 5 | Integrasi harga Kaoscetak dan validasi server | 10% | 45% | 4.5% |
| 6 | Simpan/buka desain, kepemilikan, share, pesan ulang | 10% | 15% | 1.5% |
| 7 | Cart, checkout, preview pesanan, Drive dan panduan produksi | 10% | 0% | 0% |
| 8 | QA, optimasi HP, perpindahan data, screenshot dan paket instalasi | 10% | 10% | 1% |

**Total estimasi berbobot: 35%** (jumlah kontribusi tiap tahap). Audit ulang 18 November 2026 berdasarkan file aktual di Draft PR #14. Angka ini merupakan estimasi capaian pengembangan, **bukan** persentase QA atau kesiapan rilis.

### Dasar penilaian
- Tahap 1: konsep backend dan buyer telah disetujui Owner; screenshot implementasi masih wajib sebelum produksi.
- Tahap 2: bootstrap core internal, manifest/fingerprint, pemeriksaan konflik dan bootstrap sintetis tersedia. WordPress init, sesi, database dan kanvas nyata belum terbukti.
- Tahap 3: form admin area aman dan profil harga per produk, validator geometri tersedia; mockup dan katalog konfigurasi lengkap belum.
- Tahap 4: buyer frontend opt-in, preview depan/belakang, satu gambar dan teks per sisi, warna, kuantitas, drag dan estimasi tersedia; belum ada simpan/checkout atau E2E mobile.
- Tahap 5: tabel 24s, harga dua sisi, lengan panjang, kuantitas dan quotation server tersedia; harga WooCommerce checkout belum terintegrasi dan profil produk lain belum lengkap.
- Tahap 6: draft metadata privat dengan token pemilik dan fungsi create/read/update tersedia; belum ada API aman, verifikasi email, share/revoke, repeat order terintegrasi.
- Tahap 7: SANRO Studio belum terhubung dengan checkout, Drive dan produksi. Implementasi KLD terpisah tidak otomatis dihitung.
- Tahap 8: ada bukti source lint/unit/bootstrap sintetis; belum ada staging penuh, screenshot hasil implementasi, uji HP atau ZIP rilis.

**Batas bukti:** Dokumen QA terdahulu merekam hasil PASS pada source saat itu; commit terbaru tetap memerlukan regresi ulang. Audit source tidak membuktikan hak distribusi ulang atau rebranding core pihak ketiga. Izin/lisensi harus diverifikasi sebelum distribusi paket.


## Tahap aktif: 2

Tersedia: bootstrap plugin, status/pengaturan admin, profil pratinjau, pemuat runtime internal, pemeriksaan fingerprint, kompatibilitas tanpa double-load, penyiapan arsip privat tanpa metadata Git, pemisahan callback aktivasi vendor, serta pengujian bootstrap core asli dengan plugin lama tidak dimuat.

Belum selesai: WordPress `init`/session/database lengkap, render dan operasi kanvas dengan hanya SANRO Studio aktif, paket instalasi privat yang tervalidasi, dan kesiapan integrasi modul KLD. Bootstrap dengan stub WordPress tidak sama dengan runtime penuh. Belum ada penggantian atau deaktivasi plugin produksi.

Langkah teknis berikutnya: jalankan core internal pada WordPress/WooCommerce/Flatsome terisolasi dengan data sintetis/salinan dan uji sesi serta editor; lanjutkan penyesuaian backend/buyer sesuai konsep. Keterbatasan lingkungan tes tidak menghentikan adapter dan pengujian source yang masih bisa dikerjakan.

Setiap laporan memuat tahap aktif, progres tahap/total, tindakan nyata, PASS/FAIL, commit/PR dan sisa pekerjaan. GO untuk melanjutkan pengembangan; NO-GO untuk menganggap plugin final atau menonaktifkan editor produksi sebelum integrasi dan QA lulus.
