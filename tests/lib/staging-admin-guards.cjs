'use strict';
/**
 * Shared fail-closed rules for authenticated SANRO Studio staging browser QA.
 * Deliberately does NOT load credentials by itself or access a browser.
 */
const fs = require('node:fs');
const path = require('node:path');

const HOST = 'saddlebrown-peafowl-616656.hostingersite.com';
const ORIGIN = 'https://' + HOST;
function assertSafeStorageState(statePath) {
  if (typeof statePath !== 'string' || !path.isAbsolute(statePath)) {
    throw Error('BLOCKED: private SANRO_STAGING_ADMIN_STATE absolute path required');
  }
  const p = fs.lstatSync(statePath);
  if (!p.isFile() || p.isSymbolicLink() || (p.mode & 0o077) !== 0 ||
      (process.getuid && p.uid !== process.getuid())) {
    throw Error('BLOCKED: admin storageState must be owner-only regular file');
  }
  const dir = fs.lstatSync(path.dirname(statePath));
  if (!dir.isDirectory() || dir.isSymbolicLink() || (dir.mode & 0o077) !== 0 ||
      (process.getuid && dir.uid !== process.getuid())) {
    throw Error('BLOCKED: storageState parent must be owner-only directory');
  }
  const parsed = JSON.parse(fs.readFileSync(statePath, 'utf8'));
  if (!parsed || !Array.isArray(parsed.cookies) || parsed.cookies.length < 1 ||
      parsed.cookies.some(c => {
        const d = String(c && c.domain || '').replace(/^\./, '').toLowerCase();
        return d !== HOST;
      }) || (parsed.origins && (!Array.isArray(parsed.origins) ||
        parsed.origins.some(o => !o || o.origin !== ORIGIN)))) {
    throw Error('BLOCKED: storageState must contain staging-host cookies only');
  }
  return statePath;
}
function safeStagingUrl(value) {
  try {
    const url = new URL(value);
    if (url.origin !== ORIGIN ||
        url.pathname.endsWith('/admin-post.php') ||
        url.pathname.endsWith('/admin-ajax.php')) return false;
    // GET actions can mutate WordPress too: admit only edit/view or no action.
    for (const field of ['action','action2','doaction','doaction2']) {
      const action = url.searchParams.get(field);
      if (action !== null && !['edit','view'].includes(action)) return false;
    }
    return true;
  } catch (_) { return false; }
}
function requestAllowed(method, value) {
  return (method === 'GET' || method === 'HEAD') && safeStagingUrl(value);
}
function safeOrderId(raw) {
  if (typeof raw !== 'string' || !/^[1-9][0-9]{0,8}$/.test(raw)) return null;
  const n = Number(raw);
  return Number.isSafeInteger(n) ? n : null;
}
function redactedIssue(error) {
  // Never print a URL, admin nonce, cookie, order id or customer information.
  const reason = String(error && error.code || '');
  if (reason === 'AUTH_REQUIRED') return 'BLOCKED: Staging admin session expired or unavailable';
  if (reason === 'ORDER_NOT_FOUND') return 'BLOCKED: Synthetic staging test order not accessible';
  if (reason === 'PANEL_MISSING') return 'BLOCKED: Candidate DTF admin panel not deployed/enabled';
  if (reason === 'INVALID_STATE') return 'BLOCKED: Private admin browser state invalid';
  return 'FAIL: Staging browser QA assertion or navigation failed';
}
module.exports = {HOST, ORIGIN, assertSafeStorageState, safeStagingUrl,
  requestAllowed, safeOrderId, redactedIssue};
