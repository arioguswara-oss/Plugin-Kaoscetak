# Kaoscetak Lite Designer

**Status: PLANNED/DECIDED SPEC — belum VERIFIED.** Ini folder plugin pertama dalam repository multi-plugin.

Sasaran: editor **frontend**, dibuka dari tombol **Custom Desain** pada produk WooCommerce. Mobile-first, ringan, tidak membuat editor seperti Canva/Lumise. Jangan mengganti editor dengan halaman WordPress **Edit Post**.

V1: per sisi depan/belakang maksimal 1 gambar PNG/JPEG (10 MB) dan 1 blok teks multibaris, preview ukuran/warna kaos, posisi serta dimensi dalam cm, kategori cetak A5/A4/A3/A3+, harga produk-size-cetak yang valid di server, banyak size/qty dalam satu desain, penyimpanan dan sharing privat dengan email verifikasi.

Hosting hanya metadata dan thumbnail ringan; master PNG/JPEG di Google Drive privat; PDF produksi berisi instruksi dan mockup, **bukan** berkas cetak resolusi tinggi. Buka preview editor admin meskipun Drive belum terhubung, tetapi blokir checkout publik apabila master file belum tersimpan valid.

**Gate sebelum ZIP:** audit kode, uji aktivasi WP + WooCommerce, buka editor dari produk, uji upload, warna, size, depan-belakang, aturan harga, simpan/share, cart/checkout, keamanan dan PDF. Integrasi Drive baru VERIFIED setelah uji nyata. ZIP versi lama v0.1.0 tidak membuktikan gate ini.
