# SANRO Studio scope correction dan functional completion — 24 November 2026

Checkpoint awal `9c05c71`, installed candidate `fad47c6`, PR #52. Owner menghapus payment gateway/sandbox dari kriteria SANRO Studio. Sistem menggunakan status WooCommerce standar; tidak mengubah atau mengonfigurasi gateway.

## Self-fix dan source review

- Satu-order staging preflight sebelumnya mensyaratkan `payment_gateways === 0`; syarat tersebut telah dihapus karena status gateway bukan bagian dari integrasi SANRO Studio.
- Tambah regression bahwa cart/order readiness tetap valid baik tanpa field gateway maupun saat jumlah gateway berbeda.
- Runtime preflight kini memeriksa keberadaan API order WooCommerce (`WC_Order::is_paid()`, `pending`, `on-hold`), bukan status gateway. Source Studio sendiri memakai `is_paid()` dan status lifecycle WooCommerce, tidak mengikat gateway tertentu.
- Alur produksi lokal menerima order unpaid `pending`/`on-hold` yang sudah diberi metadata `Siap Produksi` oleh aksi admin. Ini tidak menandai pembayaran atau mengirim sinyal settlement.
- Instruksi konfigurasi OAuth/folder privat Drive tersedia di [panduan Owner](studio-drive-owner-configuration-20261124.md). Drive dan GC tetap OFF.

## Upaya staging dan batas hasil

SSH ke `kaoscetak-staging` mula-mula gagal `Permission denied (publickey,password)`; percobaan read-only melalui escalation resmi juga gagal. Karena isolasi staging belum dapat diverifikasi ulang pada putaran ini, tidak ada checkout writer/DTF flag yang diaktifkan, tidak ada order dibuat, dan tidak ada file staging ditulis. Tidak ada konfigurasi payment atau Drive yang disentuh. Buyer QA dan installer audit tidak diulang.

Autentikasi admin browser belum tersedia, sehingga screenshot menu admin aktual tidak dapat diambil. Kode/test kontrak menu yang ada tidak diubah. Setelah SSH pulih, satu sesi yang disetujui dapat membuat maksimal satu order unpaid lalu menguji DTF lokal; writer wajib dipulihkan OFF setelahnya.

## Verifikasi lokal terarah

PASS: 46 one-order preflight assertions, 19 checkout boundary assertions, gateway-independent cart adapter, 48 menu contract assertions, 17 order-lock/cart checks, 36 production package/admin checks, plus pending/on-hold production readiness assertions. DTF workflow: 24 fail-closed raster safety + 42 synthetic batch/private status checks PASS. Real GD/FreeType two-sided generator **NOT TESTED** because this PHP runner has no GD extension; the real smoke correctly stopped before raster output. Runtime-only staging preflight was not run locally because it requires the exact Hostinger document root. No ZIP was built because plugin production files did not change.

Progres tetap memakai rubrik terukur sebelumnya. Tanpa checkout order atau staging DTF evidence baru, skor resmi tetap **92,00%**.
