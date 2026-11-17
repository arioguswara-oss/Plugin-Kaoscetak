# Quality gates seluruh plugin

Setiap plugin punya pengujian unit, integrasi dan checklist uji WP/WooCommerce tersendiri. Hasil syntax lint saja tidak membuktikan editor atau proses order berhasil.

Untuk Lite Designer, minimal: instal/aktivasi tanpa fatal error; editor visual dari produk; uji gambar depan/belakang, teks, warna, size, koordinat cm, harga kombinasi, qty multisize, simpan/share aman, cart/checkout, PDF panduan, status upload Drive, error/retry, dan responsive HP. Uji akses tanpa koneksi Drive hanya pada preview admin; uji publik harus menolak checkout jika file belum tersimpan aman.

Rekap uji harus menyebut platform/versi, hasil PASS/FAIL/BLOCKED dan evidence; tidak mengklaim FINAL pada batasan lingkungan yang belum diuji.

## 0.3.2 RC reproducible local fixture

`KLD_ALLOW_LOCAL_FIXTURE=1 sh tests/setup.sh`, then `sh tests/run.sh`.
Requires local Docker, Python with Playwright, Node with Playwright and Chromium.
Use `npm ci --prefix tests` for the QA-only Node dependency. The fixture binds
127.0.0.1:8893, uses synthetic data, intercepts Google/email transport and does
not certify real Google, Flatsome or production. `docker compose -f tests/compose.yml down -v`
removes only this named disposable fixture. See docs/qa-0.3.2-rc.1.md.
