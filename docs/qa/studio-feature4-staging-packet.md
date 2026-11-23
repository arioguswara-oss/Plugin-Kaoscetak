# Kandidat integrasi staging — bahan review, belum izin apply

Target tunggal `https://saddlebrown-peafowl-616656.hostingersite.com`. Production kaoscetak.id dilarang. Source baru belum di-deploy; runtime/source lama tidak membuktikan kandidat ini.

## Identitas dan preflight reproducible

Build hanya dari clean HEAD PR52. ZIP standar memiliki satu root `sanro-studio`, source marker `SANRO-STUDIO-BUILD.json`, tanpa tests/config/logs/secret. Manifest eksternal memuat source commit, digest daftar file, SHA/bytes setiap source member, build-marker hash dan archiveSHA. Marker tidak memuat archiveSHA agar tidak circular. Source commit dari manifest adalah otoritas paket; SHA marker bukan verifikasi file runtime yang sudah berubah.

```bash
python tools/build-studio-candidate.py --out /tmp/studio-candidate
python tools/studio-candidate-preflight.py --zip /tmp/studio-candidate/sanro-studio-staging-candidate.zip --manifest /tmp/studio-candidate/candidate-manifest.json --target-url https://saddlebrown-peafowl-616656.hostingersite.com --php /path/to/php
```

Preflight **PLAN_ONLY**: validasi target, archive/member/source marker, file modes, size limits, CLI PHP>=7.4 dan lint temporary extraction. Tidak remote access/apply/plugin activation/product seeding/order writes. PHP CLI lokal8.4.24 tidak membuktikan PHP-FPM Hostinger; minimum7.4 masih deklarasi kompatibilitas, bukan matriks versi yang sudah diuji. PHP-FPM/GD/FreeType/ctype/json/fileinfo/memory harus dibaca lewat diagnostic setelah apply yang disetujui. Tidak menaikkan QA karena ZIP atau syntax check.

## Kontrak apply yang harus disetujui, bukan eksekusi putaran ini

- Persetujuan satu paket menyebut manifest source commit, plugin digest, archiveSHA dan URL staging; jangan gunakan nama branch bergerak sebagai bukti source.
- Baca konfigurasi target terlebih dahulu: folder plugin aktif, PHP-FPM, status Woo, private-dir writable dan di luar public document root, explicit gate editor/draft/master/preview/WC pricing, legacy QA price writer OFF. Jangan tampilkan path/secret di laporan umum.
- Generator DTF dan seluruh gate Drive tetap OFF. Jika ternyata ON atau konfigurasi tidak diketahui, hentikan; jangan menimpa MU flags/credential untuk memaksakan hasil.
- Kelak gunakan penggantian atomik folder plugin **yang sudah aktif**, rollback folder pada kegagalan; pertahankan product metadata/private directories. Apply implementation belum dijalankan maupun dibuktikan di Hostinger. Dilarang mengulang `sanro-studio-pr50-staging-apply.sh`, `wp plugin install --force --activate`, seed produk atau menulis database order sebagai bagian instalasi.
- Setelah apply, baca `X-Sanro-Source` pada REST dan GET `/wp-json/sanro-studio/v1/readiness?product_id=24` dengan admin `manage_woocommerce` + REST nonce. Jangan menyimpan nonce/token dalam bukti publik. `unpackaged`/SHA berbeda = STOP.

## HTTP422 dan E2E setelah persetujuan terintegrasi

Development memakai route private short `.../drafts/{id}/preview-read/{front|back}` yang memetakan current revision ke ref di server; nonce+owner authorization, MIME/hash/no-store tetap berlaku. Route itu berasal dari checkpoint sebelumnya dan tidak dihitung lagi sebagai fitur baru. Source saat ini tidak mengembalikan422 dari callback tersebut. Belum ada bukti response/source runtime yang menghubungkan422 lama ke WAF/PHP/aplikasi: jangan menuduh Hostinger atau menyatakan akar masalah selesai.

Transport baru memisahkan non-JSON dari JSON application rejection tanpa membocorkan body/token; Studio REST menyertakan source header. Tidak mengirim secret melalui query/path alternatif atau menonaktifkan perlindungan untuk melewati422. Setelah apply sah, catat source+status+content-type+kode aplikasi yang aman, lalu triage dari bukti itu.

Urutan uji: HTTPS/mobile editor → simpan draft → original upload → preview current revision depan/belakang → server quote multi-size → native cart replay/edit qty → classic checkout shipping → satu pesanan sintetis → gagal/bayar-ulang pada order sama → DesignID/amount/qty/admin private preview. Persetujuan harus mencakup pesanan sintetis yang dibutuhkan; tidak menyentuh order pelanggan/settlement nyata. Gateway sandbox dan OAuth/Drive/physical-print verification terpisah; writer tetap OFF pada paket uji awal.

Status: source review GO; apply/merge/deploy/production/real order mutation/writer activation NO-GO. Baseline resmi90,50%; paket ini persiapan, Full Audit belum dimulai dan menunggu instruksi berikutnya dan tidak ada permintaan approval deploy otomatis.


## Checkpoint road90 terbaru

Lihat `studio-hermes-handoff-20261124.md` dan manifest clean HEAD PR52. Installer kini memiliki journal persisten dan `--recover`; gunakan manifest/approval yang sama dengan transaksi terputus, bukan source baru yang tidak terikat journal. Plan menampilkan recovery_required; apply baru ditolak sampai recovery selesai. Recovery memerlukan seluruh pemeriksaan staging/flags/approval yang sama, tidak ada bypass otomatis. Source baru belum di-deploy. Paket approval staging belum disetujui; jangan membuat approval atas nama Owner.
