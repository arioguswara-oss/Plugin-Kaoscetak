'use strict';
const assert = require('node:assert/strict');
const safeCartUrl = require('../assets/cart-redirect.js');
const base = 'https://kaoscetak.example/product/editor?sanro_studio_editor=1';
const cases = [
  ['absolute same-origin URL', 'https://kaoscetak.example/cart/', 'https://kaoscetak.example/cart/'],
  ['root relative cart URL', '/cart/', 'https://kaoscetak.example/cart/'],
  ['document relative cart URL', 'cart/', 'https://kaoscetak.example/product/cart/'],
  ['parent relative cart URL', '../cart/', 'https://kaoscetak.example/cart/'],
  ['same origin URL query', '/cart/?test=1', 'https://kaoscetak.example/cart/?test=1'],
  ['cross origin URL', 'https://evil.example/cart/', null],
  ['protocol relative cross origin', '//evil.example/cart', null],
  ['javascript URL', 'javascript:alert(1)', null],
  ['data URL', 'data:text/plain,bad', null],
  ['URL with username/password', 'https://user:pass@kaoscetak.example/cart', null],
  ['backslash URL', '/\\evil.example/cart', null],
  ['control character URL', '/cart/\nattack', null],
  ['empty URL', '', null],
  ['missing URL', undefined, null],
  ['too long URL', '/cart/' + 'a'.repeat(2100), null],
  ['invalid current URL', '/cart/', null, 'not-a-url'],
  ['FTP URL', 'ftp://kaoscetak.example/cart', null],
  ['HTTPS downgrade URL', 'http://kaoscetak.example/cart', null],
];
let count = 0;
for (const [title, input, expected, where = base] of cases) {
  assert.equal(safeCartUrl(input, where), expected, title);
  count++;
}
console.log(`PASS: ${count} SANRO Studio cart redirect checks`);
