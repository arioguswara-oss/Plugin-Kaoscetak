# SANRO Studio V1 — 1.0.0

Native buyer design editor and WooCommerce integration, independent of legacy editor plugins. Requires WordPress, WooCommerce and PHP8.1+; staging acceptance uses PHP8.3.37/LiteSpeed. This package does not contain a vendor editor core, credentials, test fixtures or staging transport.

Features: product print areas/mockup/colour/size configuration; front/back editor, text/images and private previews/masters; server quotes and multi-size cart; ownership/permanent design collection; Woo order snapshots/private previews; local DTF PNG candidates and PDF production guides; Administrator Google Drive setup.

Google Drive is optional. SANRO Studio → Pengaturan → Google Drive provides Client ID/Secret, Redirect URI, Connect/Disconnect, existing folder ID/Picker, status, Test Connection and auto export ON/OFF. Credential/token storage is encrypted and guarded by Administrator/POST/nonce/HTTPS checks. No Google request is made when unconfigured. Actual Google Cloud setup, consent and upload/readback are **DEFERRED BY OWNER** for V1; configuration remains **NOT CONFIGURED** until completed afterward.

Feature activation follows server safety gates; storage GC stays OFF unless separately authorized. Automatic Drive preference never overrides an explicit server OFF gate. Expired/unauthorized carts show recovery errors and do not present catalog fallback as accepted Studio prices. Payment gateways remain WooCommerce's responsibility; this plugin does not implement a gateway.

Read INSTALLATION.md and RELEASE-NOTES.md. Production deployment and merge require separate Owner authorization. Print candidates still require operator approval for physical print/font parity; software acceptance does not certify a physical print run.
