# SANRO Studio V1.1.0 — Uji integrasi berdasarkan benchmark Lumise

28 November 2026 WIB. Target staging existing, kandidat `7aa6eb23d226272bc8a523b28854ccbed64ae497`.

| Gate | Hasil |
|---|---|
| Buyer guest dua sisi | PASS. Artwork sintetis diunggah pada kedua sisi, Hitam, S × 1 + M × 2, Rp327.000. |
| Edit dari keranjang | PASS. Design ID ID-00112 tetap sama. Teks QA di sisi belakang dapat dibuka ulang. M berubah menjadi 3. Tetap dua baris, Rp437.000. |
| Classic Checkout | PASS sampai review. Design ID, Hitam, S × 1 dan M × 3 cocok, Rp437.000. Tanpa checkout POST. |
| Preview aktual | PASS untuk unduhan preview keranjang JPEG 640 × 689. File produksi order belum dinilai dari bukti ini. |
| Woo variation ID | NOT RUN. Fixture aktif #53 tipe Simple; fixture variable #76 tetap di Arsip. |
| HPOS write dan produksi | BLOCKED. Dispatcher transport mengembalikan 403 untuk aksi legacy order/produksi. Probe PR32 productless tidak menjalankan hook checkout Studio. Preview serta PDF Panduan #56 tetap bukti order lama. |

Keranjang uji kembali kosong. Draft sintetis dipertahankan sebagai bukti. Tidak ada order baru, pembayaran, atau perubahan pada konfigurasi produk/release. Baseline 96,60%, Δ 0,00 poin.

Jalur lanjutan memerlukan persetujuan menyiapkan runner QA terpisah khusus URL staging yang sama dengan kemampuan membatasi satu order synthetic tanpa gateway, memverifikasi pemblokiran email/webhook/fulfillment sebelum write, menjalankan hook checkout Studio/WooCommerce dan melakukan readback HPOS melalui aplikasi. Default 403, plugin Studio, dan tampilan buyer dipertahankan. Runner harus tersedia dan diverifikasi sebelum dispatch. **NO-GO Final Release** hingga uji order baru dan file yang dipakai operator tertutup.

[Bukti runtime](../../artifacts/studio-v1/final-candidate-20261127/lumise-benchmark-e2e-20261128.json)

## Preflight runner setelah persetujuan Owner

Izin untuk satu order sintetis dan runner sementara di staging existing diterima. WordPress Connector mengonfirmasi WooPayments nonaktif, Studio/WooCommerce aktif serta transport staging nonaktif. Hostinger Reach dan Jetpack masih aktif. Daftar webhook native WooCommerce kosong pada bukti admin sebelumnya. Bukti itu belum mencakup seluruh jalur email, webhook, antrean, dan fulfillment yang dapat dijalankan saat order dibuat.

Transport existing tetap menolak aksi order/produksi. Runner PR32 membuktikan jalur CRUD HPOS tanpa produk, sehingga tidak memenuhi uji checkout Studio. Jalur eksekusi terisolasi yang bisa memverifikasi efek keluar dan tetap menjalankan hook native Studio belum tersedia melalui akses saat ini. Tidak ada runner yang dipasang, checkout POST, order, pembayaran, atau perubahan konfigurasi. **Gate HPOS dan produksi BLOCKED. NO-GO Final Release.**

[Bukti preflight](../../artifacts/studio-v1/final-candidate-20261127/order-runner-preflight-20261128.json)
