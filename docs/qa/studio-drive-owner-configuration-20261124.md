# Konfigurasi Owner untuk SANRO Studio Google Drive

Drive tetap OFF sampai Owner menyediakan OAuth provider tepercaya dan satu folder privat khusus staging. Probe terakhir yang berhasil (08:15 UTC, sebelum SSH berikutnya ditolak) belum menemukan parent folder maupun filter token; kondisi itu perlu dibaca ulang saat SSH pulih.

## Yang perlu disiapkan

1. Buat atau pilih folder Drive khusus SANRO staging. Catat hanya ID folder, bukan URL share publik. Folder dan seluruh turunannya harus tanpa permission `anyone`, `domain`, atau `group`; berikan akses hanya kepada akun Owner/provider yang disetujui.
2. Sediakan OAuth provider server-side yang dapat memperbarui access token singkat dan mengembalikannya melalui filter WordPress `sanro_studio_drive_access_token`. Repository SANRO Studio tidak menyediakan OAuth flow/provider itu. Provider harus bisa membaca metadata/search folder, membuat folder turunan, dan mengunggah/membaca kembali file dalam folder staging tersebut. Gunakan izin Google API paling sempit yang mendukung operasi tersebut dan akses eksplisit ke folder induk.
3. Simpan client secret/refresh token pada secret store atau konfigurasi server privat. Jangan taruh token di browser, repository, log, option publik, atau response REST.
4. Di konfigurasi PHP staging saja, tentukan `SANRO_STUDIO_DRIVE_PARENT_FOLDER_ID` ke ID tadi dan pasang filter provider. Jangan define flag `SANRO_STUDIO_ENABLE_DRIVE_*` saat menyiapkan koneksi; biarkan writer OFF.
5. Setelah Owner mengonfirmasi akun OAuth dan folder staging, jalankan read-only verifikasi identitas folder/permission terlebih dahulu. Baru ajukan/aktifkan writer Drive terbatas untuk sesi uji berikutnya.

Alur SANRO membuat hierarki merchant/order/Produksi/item dan mengikat folder lewat `appProperties`. Drive job baru layak berjalan setelah order dibayar menurut WooCommerce atau ditandai `Siap Produksi` melalui aksi admin yang sah. Untuk tes tanpa pembayaran, gunakan hanya order sintetis yang memang diotorisasi dan biarkan GC OFF.

Jangan mengirimkan client secret, refresh token, access token, atau URL berbagi ke chat. Owner cukup memberikan target staging dan mengonfirmasi provider/folder sudah tersedia; verifikasi aktual dilakukan dengan respons metadata tersanitasi.
