/* Administrator-only Google Picker. No secret or refresh token is sent to the browser. */
(() => {
  'use strict';
  const button = document.getElementById('sanro-drive-pick');
  const status = document.getElementById('sanro-drive-pick-status');
  if (!button || !status || typeof sanroDrivePicker === 'undefined') return;
  let pickerReady;
  const load = () => {
    if (!pickerReady) pickerReady = new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = 'https://apis.google.com/js/api.js';
      script.onerror = reject;
      script.onload = () => window.gapi.load('picker', { callback: resolve, onerror: reject });
      document.head.appendChild(script);
    });
    return pickerReady;
  };
  button.addEventListener('click', async () => {
    button.disabled = true;
    status.textContent = 'Membuka pemilih folder Google…';
    try {
      await load();
      const response = await fetch(sanroDrivePicker.url, {
        method: 'POST', credentials: 'same-origin', cache: 'no-store',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'sanro_studio_drive_picker', nonce: sanroDrivePicker.nonce })
      });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error('oauth');
      const { token, key, appId, parentId } = result.data;
      const view = new google.picker.DocsView(google.picker.ViewId.FOLDERS)
        .setIncludeFolders(true).setSelectFolderEnabled(true).setMimeTypes('application/vnd.google-apps.folder');
      if (parentId) view.setFileIds([parentId]);
      new google.picker.PickerBuilder().addView(view).setOAuthToken(token)
        .setDeveloperKey(key).setAppId(appId).setOrigin(window.location.origin)
        .setCallback((data) => {
          if (data.action === google.picker.Action.PICKED) {
            const selected = data.docs && data.docs[0];
            status.textContent = selected && selected.id === parentId
              ? 'Folder existing dipilih. Klik Validasi folder privat untuk memverifikasi izin di server.'
              : 'Folder berbeda dari konfigurasi. Pilih folder existing yang tercatat pada pengaturan.';
          } else if (data.action === google.picker.Action.CANCEL) status.textContent = 'Pemilihan dibatalkan.';
        }).build().setVisible(true);
      status.textContent = 'Pilih folder produksi existing pada Google Picker.';
    } catch (_) {
      status.textContent = 'Pemilih folder belum tersedia. Periksa koneksi OAuth, Google Picker API dan batas referrer API key.';
      pickerReady = undefined;
    } finally { button.disabled = false; }
  });
})();
