# Checkout V1 — keputusan dan batas implementasi

Sumber bisnis: [keputusan Owner V1](kaoscetak-lite-designer-v1.md). Dokumen ini tidak mengubah keputusan nominal atau menciptakan kebijakan baru.

| Komponen | Status bisnis | Perilaku development |
|---|---|---|
| Harga desain | DECIDED | Server menghitung produk/size/kategori lebih besar + sisi kecil + lengan sesuai profile. Tidak menerima harga browser |
| Cotton Combed24s | DECIDED | Preset delapan size, lengan panjang +Rp10.000 |
| Tunic Oversize3/4 | DECIDED | Preset M/L/XL dari sumber; lengan3/4 tetap. Tidak menerima surcharge lengan panjang pada profile ini |
| Tabel per produk | DECIDED | Admin sah boleh override seluruh matriks profile. Invalid/parsial/profile mismatch ditolak; preset/produk lain tidak berubah; upgrade tidak menulis tabel |
| Repeat order5% | DECIDED; native implementation tersedia, runtime NOT TESTED | Owner token + kode email single-use → email pemilik terverifikasi; email checkout sama + objek prior order paid/non-refunded → diskon5% per pcs termasuk satuan. Tanpa proof tetap harga dasar; lookup gagal ditolak, tidak diam-diam menghapus diskon |
| Pajak | OPEN Owner | Tolak nonzero/invalid tax pada checkout Studio dan order terkunci. Tidak mengubah pengaturan pajak Woo/production; bukan keputusan bebas pajak permanen |
| Fee/tambahan | OPEN Owner | Tolak semua fee line, termasuk nol, sampai aturan sah tersedia |
| Kupon/promosi | OPEN Owner | Tolak semua applied code termasuk diskon nol dan order coupon; produk non-Studio tidak diubah oleh guard ini |
| Diskon lain | OPEN Owner | Tolak perubahan subtotal/discount tanpa bukti eligibility yang didukung |
| Ongkir | Native Woo server; kebijakan tarif toko tidak diubah | Snapshot whole-IDR server terpisah dari subtotal desain, cocokkan order shipping lines dan total. Tidak mengambil ongkir dari browser |
| Native variations/Blocks/multiple design | Kebutuhan V1 spesifik belum terbukti | Scope operasional kini classic/simple/one design/multi-size. Native variation ditolak sebelum editor/quote. Size/lengan adalah data desain, bukan variation_id rekaan |
| Hoodie/sweatshirt | Produk terpisah DECIDED; nominal OPEN | Tidak fallback memakai Cotton/Tunic; konfigurasi tanpa harga sah diblokir |

Harga/kuantitas/DesignID/snapshot/previews/quote hash diperiksa lagi pada pemulihan, existing-order pay dan payment-complete. Validasi signature/settlement tetap tanggung jawab gateway Woo yang dipakai toko; tidak membuat gateway baru. Kebijakan unsupported tetap fail-closed, bukan dianggap selesai secara bisnis.

## Repeat native — perilaku source yang diteruskan

Referensi repository: `plugins/kaoscetak-lite-designer/includes/repeat.php`, request-verify di `api.php`, completion di `sharing.php`, `tests/test_repeat.php` dan `tests/test_repeat_checkout.php`. Nominal5%, persamaan verified-owner/billing email dan prior paid history sudah ada; pembulatan half-up per pcs mempertahankan perilaku source lama (contoh65001→61751), bukan keputusan pajak/fee/kupon baru.

Native Studio menyimpan hanya hash challenge30 menit dan mengirim kode melalui email; nonce saja bukan owner authorization. Email verified tidak dapat diganti lewat owner token; proof copy dengan owner berbeda tidak berlaku. Tidak ada jumlah minimum/produk atau tenggat bisnis baru untuk history. Query memakai page20 objek; budget teknis3 detik/100 page habis berarti quote unavailable/fail-closed, bukan aturan hanya-N-order atau harga lebih tinggi otomatis. Pending payment memeriksa ulang actual prior order yang dipin; paid order mempertahankan commercial snapshot. Refund/failed/foreign-email/unverified-ID tidak menjadi bukti paid history.

Diskon diterapkan pada server unit price, bukan kupon/fee Woo. Pajak/fee/kupon tetapOPEN dan guard lama tetap berlaku. Item, locked quote, PDF/manifest dan admin menunjukkan base/discount/net yang sama; email/challenge/history fingerprint tidak dikirim ke manifest Drive atau browser quote.
