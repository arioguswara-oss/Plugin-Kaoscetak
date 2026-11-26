'use strict';
(function () {
  var tokenFieldId = 'sanro_studio_checkout_token';
  var draftPrefix = 'sanro-studio-draft:';
  var missingMessage = 'Sesi desain tidak ditemukan di tab ini. Kembali ke editor, simpan desain, lalu ulangi checkout.';

  function authorize() {
    var fields = Array.from(document.querySelectorAll('[data-sanro-checkout-token]'));
    if (!fields.length) {
      var legacy = document.getElementById(tokenFieldId);
      if (legacy) fields.push(legacy);
    }
    fields.forEach(function(field) { field.value = ''; });
    return fields.map(authorizeDesign).every(Boolean);
  }

  function authorizeDesign(field) {
    var product = field.getAttribute('data-product-id') || '';
    var design = field.getAttribute('data-design-id') || '';
    if (!/^[1-9][0-9]*$/.test(product) ||
        !/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i.test(design)) {
      return false;
    }
    try {
      var token = window.sessionStorage.getItem('sanro-studio-checkout:' + design);
      var stored = JSON.parse(window.sessionStorage.getItem(draftPrefix + product) || 'null');
      if (!token && stored && stored.id === design) token = stored.token;
      if (typeof token === 'string' && /^[a-f0-9]{64}$/.test(token)) {
        field.value = token;
        return true;
      }
    } catch (_error) {}
    return false;
  }

  function showMissingSession() {
    var help = document.querySelector('.sanro-studio-checkout-help');
    if (help) {
      help.textContent = missingMessage;
      help.setAttribute('role', 'alert');
    }
    var form = document.querySelector('form.checkout');
    if (form) {
      var notice = form.querySelector('.sanro-studio-auth-error');
      if (!notice) {
        notice = document.createElement('div');
        notice.className = 'woocommerce-error sanro-studio-auth-error';
        notice.setAttribute('role', 'alert');
        form.insertBefore(notice, form.firstChild);
      }
      notice.textContent = missingMessage;
    }
  }

  function clearMissingSession() {
    var form = document.querySelector('form.checkout');
    var notice = form && form.querySelector('.sanro-studio-auth-error');
    if (notice) notice.remove();
  }

  function verifyBeforeCheckout() {
    if (authorize()) {
      clearMissingSession();
      return true;
    }
    showMissingSession();
    return false;
  }

  document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('form.checkout');
    if (!form) return;

    // Native form submissions must not bypass the same browser-side gate.
    form.addEventListener('submit', function (event) {
      if (!verifyBeforeCheckout()) {
        event.preventDefault();
        event.stopImmediatePropagation();
      }
    }, true);

    if (window.jQuery) {
      var $ = window.jQuery;
      $(document.body).on('checkout_place_order', function () {
        return verifyBeforeCheckout();
      });
      // Woo checkout may rebuild its fragments after shipping/payment updates.
      $(document.body).on('updated_checkout', function () {
        if (authorize()) clearMissingSession();
      });
    }
    authorize();
  });
})();
