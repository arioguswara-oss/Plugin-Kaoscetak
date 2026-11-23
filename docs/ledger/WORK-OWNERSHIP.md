# Ownership development integration

23 November 2026 WIB. Instruksi Owner terbaru memberi ChatGPT Work scope implementasi pada branch development terisolasi; ini menggantikan batas only-docs untuk tugas ini, tanpa mengubah branch milik worker lain.

| Pemilik | Scope |
|---|---|
| ChatGPT Work | work/studio-mobile-checkout-integration-20261123: mockup/mobile, short preview route, blank preview/quantity compatibility, tes dan ledger |
| Hermes CLI | Existing #50 restore/native/Woo branch; tidak ditulis atau dijalankan oleh Work |
| Codex Cloud | Existing #51 pricing dan #48 runner; tidak ditulis atau dijalankan oleh Work |
| Owner | Persetujuan merge/deploy; tidak diminta untuk terminal manual pada tugas ini |

Base frozen #50: 45a7917c320403d1bc0d18caf7ee3398a3ecb416. Pricing #51 readback e9cb4679247f30223c33e51ddac1b2405dff961c, source kalkulator/adapter sudah ada pada base. Tidak melakukan import duplikat atau force-push ke branch worker. Dependency PR tetap harus direkonsiliasi sebelum merge; snapshot coordination Update 2 lama bukan gambaran source terbaru.

Staging hanya diuji melalui Cloud Browser dan WordPress read-only plugin inventory; operasi customer sintetis draft/master/preview dilakukan sesuai izin QA Owner. Tidak ada order/payment, reinstall, perubahan production, deployment baru, aktivasi DTF/Drive atau layanan worker.

Owner/session runtime AI eksternal tidak diinventarisasi atau dihentikan. Branch terisolasi dan fixed base menjaga scope Work tidak menulis ref yang sama; tidak diklaim sebagai lock global.
