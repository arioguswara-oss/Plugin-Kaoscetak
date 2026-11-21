'use strict';
/* Native WordPress Media Library picker; only runs on WooCommerce product edit. */
(function(){
  if (!window.wp || !wp.media) return;
  const sides = new Set(['front', 'back']);
  function setPreview(side, rawUrl) {
    const preview = document.getElementById('sanro-mockup-preview-' + side);
    if (!preview) return;
    if (!rawUrl) {
      preview.removeAttribute('src');
      preview.hidden = true;
      return;
    }
    let url;
    try { url = new URL(rawUrl, window.location.href); } catch (_) { return; }
    if (!['https:', 'http:'].includes(url.protocol)) return;
    preview.src = url.href;
    preview.hidden = false;
  }
  document.addEventListener('click', function(event){
    const choose = event.target.closest('.sanro-choose-mockup');
    const clear = event.target.closest('.sanro-clear-mockup');
    const button = choose || clear;
    if (!button || !sides.has(button.dataset.sanroSide)) return;
    const field = document.getElementById('_sanro_studio_mockup_' + button.dataset.sanroSide);
    if (!field) return;
    event.preventDefault();
    if (clear) {
      field.value = '';
      setPreview(button.dataset.sanroSide, null);
      field.dispatchEvent(new Event('change', {bubbles:true}));
      return;
    }
    const frame = wp.media({
      title: 'Pilih mockup SANRO Studio (' + button.dataset.sanroSide + ')',
      button: {text: 'Gunakan mockup'},
      library: {type: 'image'},
      multiple: false
    });
    frame.on('select', function(){
      const selected = frame.state().get('selection').first();
      if (!selected) return;
      const image = selected.toJSON();
      if (!image || !Number.isSafeInteger(image.id) || image.id <= 0 ||
          image.type !== 'image') return;
      field.value = String(image.id);
      const sizes = image.sizes || {};
      const thumbnail = (sizes.thumbnail && sizes.thumbnail.url) ||
        (sizes.medium && sizes.medium.url) || image.url;
      setPreview(button.dataset.sanroSide, thumbnail);
      field.dispatchEvent(new Event('change', {bubbles:true}));
    });
    frame.open();
  });
})();
