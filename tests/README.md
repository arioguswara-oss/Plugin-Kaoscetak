# Quality gates seluruh plugin

## Offline runner (SANRO bootstrap branch)

Run `tests/run-all.sh` from a Git checkout with Bash, Git, PHP CLI and Node.js
(Node 18+ for `node:test`). No Composer/npm installation is needed. The runner
lints PHP and JavaScript in `plugins/` and `tests/`, then executes classified offline PHP and
Node test scripts under `tests/` and `plugins/*/tests/`. Integration drivers and
helper modules are classified separately as described below. Discovery uses
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

## Restore-compatible offline gate (PR #48 completion)

The current runner distinguishes executable offline tests from fixtures,
private-vendor entrypoints and WordPress/browser integration drivers. It also
runs PHP and Node suites inside `plugins/*/tests/`. Fixtures and libraries receive
syntax checks only. Private vendor, real-GD/private-font and WordPress/browser
QA drivers are reported individually as `NOT TESTED`; they are never mistaken
for offline PASS or automatically connected to a site. Unclassified executable
files fail the gate until reviewed. Cached downloads, results, node_modules,
private runtime and vendor directories are excluded.

`PASS <HEAD>` certifies only the selected offline checks. `SUMMARY` on stderr
lists passed/failed commands, PHP/Node script counts and NOT TESTED driver count.
A missing runtime, syntax error or failed offline assertion returns `FAIL` and
exit 1. Newly restored tests are allowed to expose genuine failures; never hide
those failures to obtain a green summary. All test processes run with isolated
stdin so they cannot consume the runner's file inventory. The parity test uses
its real PHP subprocess and must fail if subprocess execution is blocked.

For PR #50 integration, retain BOTH this offline gate documentation and the
following restored fixture instructions when resolving README conflicts. The
fixture commands are manual integration QA and are not invoked by `run-all.sh`.

## 0.3.2 RC reproducible local fixture

`KLD_ALLOW_LOCAL_FIXTURE=1 sh tests/setup.sh`, then `sh tests/run.sh`.
Requires local Docker, Python with Playwright, Node with Playwright and Chromium.
Use `npm ci --prefix tests` for the QA-only Node dependency. The fixture binds
127.0.0.1:8893, uses synthetic data, intercepts Google/email transport and does
not certify real Google, Flatsome or production. `docker compose -f tests/compose.yml down -v`
removes only this named disposable fixture. See docs/qa-0.3.2-rc.1.md.
