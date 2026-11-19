# Paket runtime privat

`source.json` hanya berisi fingerprint source yang sudah diperiksa. Source core bukan bagian dari repository publik.

Pemuat core memakai direktori tetap `runtime/editor/` di dalam plugin SANRO Studio. Untuk pengembangan lokal, siapkan dari arsip Owner yang sudah tersedia:

```sh
python3 tools/prepare-sanro-runtime.py /private/path/lumise.zip
```

Tool memeriksa SHA256 arsip, path ZIP, symlink, dan fingerprint file inti, mempertahankan pemberitahuan hak cipta/lisensi, serta membuang metadata `.git`. Tool tidak memodifikasi source vendor. Hasilnya diabaikan oleh Git. Pengulangan memeriksa hasil yang sudah ada tanpa menimpa atau menghapus file. Isi runtime tidak boleh dicommit ke repo publik.

SANRO Studio memuat runtime pada `plugins_loaded` setelah plugin aktif selesai dimuat. Jika plugin editor lama masih aktif, core eksternal dipakai tanpa pemuatan ganda. Jika keduanya tidak aktif, core internal dapat dimuat dari paket ini. WooCommerce tetap wajib aktif. Bootstrap hanya memakai schema core yang sudah ada dan tidak mengimpor sample SQL. Callback aktivasi/deaktivasi vendor tidak digunakan untuk runtime internal.

Ini memungkinkan satu plugin editor aktif sambil memakai core berlisensi sebagai komponen internal. Bukan bukti bahwa desain/checkout/Drive sudah terintegrasi atau semua state vendor telah berhasil dipindahkan. Pengujian WordPress penuh masih wajib. Pengemasan distribusi, hak branding, dan kesiapan mengganti plugin lama belum dinyatakan VERIFIED; tidak ada ZIP publik berisi core vendor atau pemasangan ke produksi pada tahap ini.
