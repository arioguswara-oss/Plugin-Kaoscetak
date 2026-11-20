'use strict';
/* Native WordPress Media Library picker; only runs on WooCommerce product edit. */
(function(){
  if (!window.wp || !wp.media) return;
  const sides = new Set(['front', 'back']);
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
      field.dispatchEvent(new Event('change', {bubbles:true}));
    });
    frame.open();
  });
})();
