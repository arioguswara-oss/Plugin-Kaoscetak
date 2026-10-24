# Plugin Kaoscetak

Repository privat untuk **berbagai plugin WordPress/WooCommerce khusus kaoscetak.id**. Bukan hanya satu plugin.

## Struktur

- `plugins/`: setiap plugin memiliki direktori sendiri; source dan versi tidak bercampur.
- `docs/architecture/`: prinsip dan batasan teknis lintas plugin.
- `docs/business-rules/`: keputusan resmi Owner per plugin.
- `tests/`: pedoman dan pengujian; pengujian tiap plugin dapat berada di folder plugin terkait.

## Plugin pertama

`plugins/kaoscetak-lite-designer/` — editor custom desain kaos ringan, mobile-first, terintegrasi WooCommerce.

**Status: dalam pengembangan; belum ada rilis yang dinyatakan VERIFIED.** Jangan memasang ZIP lama v0.1.0 sebagai produk final.

## Tata kelola

- Owner/final approver: Rio.
- Pengembangan: Rio dan ChatGPT langsung; tanpa Hermes/Codex kecuali diminta.
- `DECIDED` = disetujui Owner, `IMPLEMENTED` = ada kode, `VERIFIED` = lulus uji. Status tidak saling menggantikan.
- Source code dipisahkan per plugin; `main` hanya menerima artefak yang sudah ditinjau dan diperiksa sesuai tahapnya. Rilis membutuhkan bukti uji.
- Jangan ubah hosting live, Lumise, pembayaran, database, atau tema tanpa izin Owner.

Source code, pengujian dan rilis dibuat bertahap; tidak ada klaim plugin siap instalasi hanya karena struktur repository sudah tersedia.
