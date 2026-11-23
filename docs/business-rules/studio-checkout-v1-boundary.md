# Checkout V1 — keputusan dan batas implementasi

Sumber bisnis: [keputusan Owner V1](kaoscetak-lite-designer-v1.md). Dokumen ini tidak mengubah keputusan nominal atau menciptakan kebijakan baru.

| Komponen | Status bisnis | Perilaku development |
|---|---|---|
| Harga desain | DECIDED | Server menghitung produk/size/kategori lebih besar + sisi kecil + lengan sesuai profile. Tidak menerima harga browser |
| Cotton Combed24s | DECIDED | Preset delapan size, lengan panjang +Rp10.000 |
| Tunic Oversize3/4 | DECIDED | Preset M/L/XL dari sumber; lengan3/4 tetap. Tidak menerima surcharge lengan panjang pada profile ini |
| Tabel per produk | DECIDED | Admin sah boleh override seluruh matriks profile. Invalid/parsial/profile mismatch ditolak; preset/produk lain tidak berubah; upgrade tidak menulis tabel |
| Repeat order5% | DECIDED nominal; implementasi eligibility belum selesai | Tidak diaktifkan sebelum riwayat pelanggan terverifikasi. Bukan diskon5% otomatis dari klaim buyer |
| Pajak | OPEN Owner | Tolak nonzero/invalid tax pada checkout Studio dan order terkunci. Tidak mengubah pengaturan pajak Woo/production; bukan keputusan bebas pajak permanen |
| Fee/tambahan | OPEN Owner | Tolak semua fee line, termasuk nol, sampai aturan sah tersedia |
| Kupon/promosi | OPEN Owner | Tolak semua applied code termasuk diskon nol dan order coupon; produk non-Studio tidak diubah oleh guard ini |
| Diskon lain | OPEN Owner | Tolak perubahan subtotal/discount tanpa bukti eligibility yang didukung |
| Ongkir | Native Woo server; kebijakan tarif toko tidak diubah | Snapshot whole-IDR server terpisah dari subtotal desain, cocokkan order shipping lines dan total. Tidak mengambil ongkir dari browser |
| Native variations/Blocks/multiple design | Kebutuhan V1 spesifik belum terbukti | Scope operasional kini classic/simple/one design/multi-size. Native variation ditolak sebelum editor/quote. Size/lengan adalah data desain, bukan variation_id rekaan |
| Hoodie/sweatshirt | Produk terpisah DECIDED; nominal OPEN | Tidak fallback memakai Cotton/Tunic; konfigurasi tanpa harga sah diblokir |

Harga/kuantitas/DesignID/snapshot/previews/quote hash diperiksa lagi pada pemulihan, existing-order pay dan payment-complete. Validasi signature/settlement tetap tanggung jawab gateway Woo yang dipakai toko; tidak membuat gateway baru. Kebijakan unsupported tetap fail-closed, bukan dianggap selesai secara bisnis.
