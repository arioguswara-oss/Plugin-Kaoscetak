# Optional post-release Owner setup — live OAuth/upload DEFERRED BY OWNER

This guide is for configuration after release, not an outstanding release blocker. Setup controls are complete in1.0.0; no credentials are requested now. Administrator path: SANRO Studio → Pengaturan → Google Drive. Test Connection, Disconnect and auto-export controls are provided. Explicit server safety gates remain authoritative.

# Native SANRO Studio Google Drive configuration

The plugin now includes its own confidential web OAuth provider. No external token-provider plugin is required. Connection never enables Drive/DTF/GC writers.

Use the existing private folder **SANRO Studio — Staging V1**, ID `1oKDhNC5P_96w3Ys4pdx2ir-h-KLIqejl`. No duplicate root is created. Drive Connector access is separate from runtime plugin authorization.

Owner completes all authentication in one session, directly on official Google and HTTPS staging pages; never send credentials to chat/GitHub:

1. In an Owner-controlled Google Cloud project enable Google Drive API and Google Picker API. Use OAuth Client type Web application and its project number. If consent is in Testing, the intended Owner Google account must be an allowed test user; Google's testing refresh-token lifetime may limit unattended operation.
2. Register redirect URI exactly: `https://saddlebrown-peafowl-616656.hostingersite.com/wp-admin/admin-post.php?action=sanro_studio_drive_oauth_callback` and JavaScript origin `https://saddlebrown-peafowl-616656.hostingersite.com`.
3. Prepare a Picker API key in the same project, restricted to Drive/Picker API and website referrers `https://saddlebrown-peafowl-616656.hostingersite.com/*` and `https://docs.google.com/*` per Google Picker requirements. The key is browser-visible; it is not a client secret.
4. Open SANRO Studio → Koneksi Google Drive. Enter the Client ID, Client Secret, existing folder ID above, and restricted Picker API key directly on that HTTPS form. Save. Secret is encrypted with installation-specific WordPress salt and never redisplayed.
5. Click Sambungkan Google Drive, select the intended Google account and consent to drive.file on the official Google page. Return to staging. Click Pilih folder existing melalui Google and choose the exact existing folder, then Validasi folder privat. An inaccessible folder fails closed; it never creates another root.

Client secret/refresh token stay encrypted server-side; a short-lived access token is supplied only to the authenticated administrator's Google Picker via no-cache POST. Folder permissions must all be individual users, with an owner and write access. Sharing is never expanded automatically. The app checks full permission enumeration; public/domain/group/incomplete responses fail closed.

After native connection is validated, use only existing synthetic order56 for bounded staging upload/readback/idempotency. Order56 already has authorized production-ready metadata, so no real payment or second order is needed for Drive testing. Restore temporary writer configuration afterward; storage GC remains OFF.

Primary references: https://developers.google.com/workspace/drive/api/guides/api-specific-auth and https://developers.google.com/workspace/drive/picker/guides/web-picker-sample .
