# SANRO Studio V1.1.0 — Simpan sekali dan arahan Cart

27 November 2026 WIB · PR #52 · baseline resmi 96,50% → 96,50% (Δ 0,00 poin).

Pembeli menyimpan desain dan gambar privat dengan satu tindakan, termasuk saat jumlah kaos masih nol. Status Tersimpan hanya muncul setelah file, ikatan draft, dan Design ID diverifikasi. Cart mengarahkan pembeli ke jumlah per ukuran jika kosong. Jumlah valid melanjutkan simpan dan pembuatan pratinjau sebelum Cart. Penggantian file dan geometri disimpan dalam satu perubahan atomik dan memeriksa revisi serta ikatan sebelumnya.

Empat handle sudut, rotasi dan tombol hapus tersedia pada objek terpilih. Tengah dan menu kosongkan sisi berada di mockup. Tab Desain Saya memuat desain sesi, sementara pemeriksaan diskon repeat dapat dibuka dari Produk. Mockup foto depan/belakang digunakan ketika katalog tidak memiliki mockup yang dipetakan.

| Pemeriksaan | Hasil |
| --- | --- |
| PHP render buyer dan draft CAS untuk file privat | PASS, 24 dan 35 assertions |
| JS Cart, print zone, resize/rotasi dan preview | PASS, termasuk Cart kosong, draft tanpa jumlah dan retry baca file |
| Chromium dengan PHP render dan REST simulasi | PASS pada 320, 390, 430 dan 1280 px untuk simpan/buka ulang, hapus objek, swatch, resize, jumlah dan Cart simulasi |
| Dua ZIP identik dan preflight lokal | PASS, PLAN_ONLY |
| HTTPS pra dan pascainstalasi | PASS di LiteSpeed PHP 8.3.37. Installer committed, rollback tersedia. Transport dinonaktifkan. |
| Guest Buyer staging desktop 1363 px | PASS pada produk sintetis #53. Satu klik menyimpan gambar saat jumlah nol, reopen ID-00106 dengan thumbnail, arahan jumlah memfokuskan S, resize 60% → 76%, rotasi dan drag, serta swatch Biru untuk dua sisi tanpa mengubah geometri. Cart menampilkan S × 1, 5XL × 1, thumbnail, ID-00106 dan total Rp187.000. Kedua item uji dihapus. |
| Staging live 320/390/430 px dan Android fisik Owner | Belum diuji. |

Kandidat V1.1.0 `7217663cbf3626298274ececf3c9e73a0996a4bc` aktif pada staging. Installed tree digest `1ed56bf80b26469974272d1ef2c6c08cda885669000081e88c124a9af6658444`. Predecessor `ef8df0d` cocok di gate. DTF, Drive, GC dan staging checkout hook OFF. Bukti lama thumbnail, isolasi sesi dan concurrency MariaDB dipakai ulang pada cakupannya. Tidak ada order, pembayaran, perubahan production atau merge. GO staging. NO-GO final mobile acceptance.

[Bukti runtime](../../artifacts/studio-v1/buyer-ux-20261127/runtime-acceptance.json) · [Screenshot editor staging](../../artifacts/studio-v1/buyer-ux-20261127/staging-guest-editor.jpg) · [Screenshot Cart staging](../../artifacts/studio-v1/buyer-ux-20261127/staging-guest-cart.jpg)

[Manifest](../../artifacts/studio-v1/buyer-ux-20261127/candidate-manifest.json) · [Checksum dan digest](../../artifacts/studio-v1/buyer-ux-20261127/transport-binding.json) · [Hasil Chromium](../../artifacts/studio-v1/buyer-ux-20261127/chromium-fixture.jsonl) · [Screenshot fixture 390 px](../../artifacts/studio-v1/buyer-ux-20261127/fixture-390.png)
