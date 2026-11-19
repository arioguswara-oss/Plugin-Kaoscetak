'use strict';
/**
 * Resolve a WooCommerce cart destination without allowing an off-site redirect.
 * WooCommerce can return an absolute URL or a root/document-relative path.
 * This helper has no navigation side effects and is Node-testable.
 */
(function (root) {
  function safeCartUrl(rawUrl, currentUrl) {
    if (typeof rawUrl !== 'string' || !rawUrl || rawUrl.length > 2048
        || /[\u0000-\u001f\u007f\\]/.test(rawUrl)) return null;
    try {
      const current = new URL(currentUrl);
      const target = new URL(rawUrl, current);
      if (!['http:', 'https:'].includes(target.protocol)
          || target.origin !== current.origin
          || target.username || target.password) return null;
      return target.href;
    } catch (_error) { return null; }
  }

  if (root) root.SanroStudioSafeCartUrl = safeCartUrl;
  if (typeof module === 'object' && module.exports) module.exports = safeCartUrl;
})(typeof window === 'object' ? window : null);
