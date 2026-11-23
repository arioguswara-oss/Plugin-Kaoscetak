# Quality gates seluruh plugin

## Offline runner (SANRO bootstrap branch)

Run `tests/run-all.sh` from a Git checkout with Bash, Git, PHP CLI and Node.js
(Node 18+ for `node:test`). No Composer/npm installation is needed. The runner
lints PHP and JavaScript in `plugins/` and `tests/`, then executes every PHP and
Node (`.js`, `.cjs`, `.mjs`) test script under `tests/`. Test scripts in this
directory must be standalone executable tests, not helper modules. Discovery uses
Git's tracked and non-ignored untracked inventory; do not keep downloaded vendor
code or unrelated fixtures under this test directory.

Detailed output goes to stderr; stdout is exactly one final line, `PASS <HEAD>`
or `FAIL <HEAD>`. Exit status is 0 or 1 respectively. Missing PHP/Node, empty
PHP/Node coverage, lint errors and test failures fail the run. HEAD identifies the
checkout, not a guarantee that the working tree is clean.

The bootstrap PHP test uses WordPress stubs. The three Node cases cover the
official price examples, front/back symmetry, invalid quantities and safe cart
destinations. These checks do not establish WordPress/WooCommerce runtime,
browser, payment, storage or security integration correctness. Nothing in the
runner installs or deploys a plugin or connects to a live website.

Setiap plugin punya pengujian unit, integrasi dan checklist uji WP/WooCommerce tersendiri. Hasil syntax lint saja tidak membuktikan editor atau proses order berhasil.

Untuk Lite Designer, minimal: instal/aktivasi tanpa fatal error; editor visual dari produk; uji gambar depan/belakang, teks, warna, size, koordinat cm, harga kombinasi, qty multisize, simpan/share aman, cart/checkout, PDF panduan, status upload Drive, error/retry, dan responsive HP. Uji akses tanpa koneksi Drive hanya pada preview admin; uji publik harus menolak checkout jika file belum tersimpan aman.

Rekap uji harus menyebut platform/versi, hasil PASS/FAIL/BLOCKED dan evidence; tidak mengklaim FINAL pada batasan lingkungan yang belum diuji.
