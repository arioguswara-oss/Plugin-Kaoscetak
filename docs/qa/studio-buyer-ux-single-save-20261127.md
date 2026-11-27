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
| Autentikasi WordPress staging | Terhenti setelah menunggu. WordPress connector masih mengonfirmasi Studio 1.1.0 aktif, transport nonaktif. |
| Gate HTTPS, WooCommerce Guest Buyer kandidat baru dan HP fisik Owner | Belum diuji |

Kandidat source `7217663cbf3626298274ececf3c9e73a0996a4bc`. Staging terakhir terverifikasi pada `ef8df0da9eb1e88641659dc79da8431e9e1157cd`. Bukti lama untuk thumbnail, isolasi sesi dan concurrency MariaDB tetap berlaku pada cakupannya. Kandidat baru belum dipasang dan belum memperoleh bukti runtime staging. NO-GO untuk final acceptance. Tidak ada order, pembayaran, perubahan production atau merge. Kandidat tidak mengaktifkan DTF atau Drive.

[Manifest](../../artifacts/studio-v1/buyer-ux-20261127/candidate-manifest.json) · [Checksum dan digest](../../artifacts/studio-v1/buyer-ux-20261127/transport-binding.json) · [Hasil Chromium](../../artifacts/studio-v1/buyer-ux-20261127/chromium-fixture.jsonl) · [Screenshot fixture 390 px](../../artifacts/studio-v1/buyer-ux-20261127/fixture-390.png)
