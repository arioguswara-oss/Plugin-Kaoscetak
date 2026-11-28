'use strict';
// Regression for the live editor Home-link dirty-state guard, without a browser or network.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.resolve(__dirname, '../assets/buyer.js'), 'utf8');
const css = fs.readFileSync(path.resolve(__dirname, '../assets/buyer.css'), 'utf8');
const startMarker = " const homeLink=document.querySelector('#sanro-studio-buyer [data-sanro-home-link]');";
const stopMarker = ' const boundMasterRefs={};';
const start = source.indexOf(startMarker);
const stop = source.indexOf(stopMarker, start);
assert(start !== -1 && stop > start, 'Home navigation guard source boundaries changed');
const guard = source.slice(start, stop);
assert.match(source.slice(source.indexOf(' let savedId='), start), /savedRevision=0/,
  'An untouched blank editor must start clean');
assert.match(css, /sanro-appbar \.sanro-home-link\{[^}]*flex:0 0 40px[^}]*height:40px/);
assert.match(css, /sanro-appbar \.sanro-home-link:hover/);

function clickGuard({editRevision = 0, savedRevision = 0, saving = false,
  uploadingMaster = false, confirmResult = true, withLink = true} = {}) {
  let listener = null, prompts = 0, prevented = false;
  const link = withLink ? {addEventListener(type, fn) {
    assert.equal(type, 'click'); listener = fn;
  }} : null;
  vm.runInNewContext(guard, {
    document: {querySelector(selector) {
      assert.equal(selector, '#sanro-studio-buyer [data-sanro-home-link]');
      return link;
    }},
    saving, uploadingMaster, editRevision, savedRevision,
    window: {confirm() { prompts++; return confirmResult; }}
  }, {timeout: 1000});
  if (listener) listener({preventDefault() { prevented = true; }});
  return {prompts, prevented};
}

assert.deepEqual(clickGuard(), {prompts: 0, prevented: false}, 'Clean navigation should proceed without confirmation');
assert.deepEqual(clickGuard({editRevision: 1, savedRevision: 0, confirmResult: false}),
  {prompts: 1, prevented: true}, 'Cancel should keep an edited design open');
assert.deepEqual(clickGuard({editRevision: 1, savedRevision: 0, confirmResult: true}),
  {prompts: 1, prevented: false}, 'Confirmed navigation should proceed');
assert.deepEqual(clickGuard({saving: true, confirmResult: false}),
  {prompts: 1, prevented: true}, 'Pending save should be protected');
assert.deepEqual(clickGuard({uploadingMaster: true, confirmResult: false}),
  {prompts: 1, prevented: true}, 'Pending master upload should be protected');
assert.deepEqual(clickGuard({withLink: false}),
  {prompts: 0, prevented: false}, 'Pages without an editor Home link should remain unaffected');
console.log('PASS: 6 Home navigation dirty-state checks');
