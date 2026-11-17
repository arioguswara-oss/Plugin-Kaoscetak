# Milestone A — PR #12 source hardening and QA

> Historical checkpoint. The PHP availability conclusion and residual retry item
> below are superseded by [native execution and retry regression evidence](milestone-a-native-20261118.md).

Checked 2026-11-17, starting 19:32 UTC / 2026-11-18 02:32 WIB.
Verified upstream branch `fix/kld-p0-cart-preview-20261118`, Draft PR #12,
HEAD `92f1c45dc4fcab07b4f951943448e9e48084c4c6` before editing. All 50
materialized text files matched their upstream Git blob SHA. Clone failed DNS;
source retrieval and commits use the authenticated GitHub connector. The local
Git snapshot has a different root commit; published commits retain the actual
upstream parent and use an expected-head lease. No force push or merge.

## Decision

**Milestone A: FAIL / incomplete gate. Release: NO-GO.** Native PHP lint and
PHP regressions could not execute. JavaScript execution and an auxiliary PHP
parser do not certify server behavior. These are development environment
blockers, separate from unavailable WordPress/WooCommerce/Flatsome staging.

No hosting, live site, production database, theme, Lumise, OAuth, SMTP, gateway
or customer record was accessed or changed. No CI workflow, runner or FINAL ZIP
was created. The old RC ZIP/hash does not contain these new source changes.

## Changes and regression coverage

- Reject coercible/malformed Design IDs (negative/fractional/array/exponent/
  overflow), including invalid IDs that previously bypassed the cart loop.
  Reject trashed or non-private designs when WordPress status is available.
- Recheck native cart product/variation ID, variation parent and selected
  attributes against the design's size/colour, including wildcard custom option
  values. Reject malformed quantity/size/colour without type errors; retain the
  100-per-size and 200-per-design bounds.
- Keep real server price recalculation and verified-email/prior-paid-order
  repeat policy. Guard malformed price arguments and cart object fields.
  Align the existing repeat-checkout test with PR #12's `kld_price_mismatch`
  error code (previous fixture still expected `kld_repeat_email_mismatch`).
- Drive master verification fails closed without explicit permission evidence,
  rejects unsupported MIME/byte count and malformed file IDs. Folder recovery
  is scoped to the selected parent as well as the order property. The isolated
  Drive adapter now returns explicit private permissions.
- Release locks using a prepared compare-and-delete on the exact stored lease;
  an expired writer cannot remove a successor between a token read and delete.
- Expand PHP pricing regression using independent official tariff fixtures,
  actual `kld_unit_price`/`kld_repeat_unit_price`, all sides/swaps/same categories,
  long sleeves, tunic table, size quantities, malformed inputs and discount gates.
  Example L A3+/A4 remains Rp118,000. No pricing formula replacement in production.
- Add synthetic PHP suites for cart/server authorization/tampering, Drive
  privacy/content/recovery and lock interleavings. Add actual-editor Node tests
  and a Docker-free `sh tests/run-source.sh` entrypoint.

Source findings are addressed by code and regression cases, **not yet verified
by execution of the PHP cases**. No remote exploit or broad security certification
is claimed.

## Executed checks

Evidence: [command-by-command JSON](evidence/milestone-a-20261118/execution.json),
[Node TAP](evidence/milestone-a-20261118/node-editor-tap.txt),
[auxiliary parser](evidence/milestone-a-20261118/php-parser.json).

| Command / check | Count | Actual result |
| --- | ---: | --- |
| `node --check <file>` | 4 JS/CJS files | PASS, exit 0 |
| `node tests/test_editor_unit.cjs` | 6 tests | PASS, 0 failures; Node v22.22.1 |
| Actual editor price helper | 456 official tariff assertions within the 6 tests | PASS; UI only, not server pricing |
| `php -l <file>` | 30 files requested, 0 executed | BLOCKED, exit 127 / PHP executable missing |
| `php plugins/kaoscetak-lite-designer/tests/pricing-smoke.php` | 1 suite requested, 0 executed | BLOCKED, `php: command not found` |
| `php tests/test_*.php` (individual commands in JSON) | 12 suites requested, 0 executed | BLOCKED, PHP executable missing |
| `sh tests/run-source.sh` | runner | JS passes, then exits 127: `BLOCKED: PHP CLI unavailable` |
| Auxiliary JS PHP parser | 30 files in 8.3 grammar; 12 plugin files also 7.4 grammar | No syntax/duplicate-function errors; **not `php -l`** |
| `git diff --check`; `sh -n tests/run-source.sh` | 2 checks | PASS |

Environment attempts: PHP/php8.x absent; Docker socket permission denied;
`sudo -n` denied by no-new-privileges. No privilege or sandbox settings changed.
GitHub retrieval of an alternative PHP-Wasm runtime returned empty oversized
file content/transport errors, so no substitute PHP execution is claimed.
The auxiliary parser is isolated outside the repository, pinned to
`glayzzle/php-parser@94f6b40fa502e7e8e07d0dc2f8a7f13be65cd98c`.

No server suite has a valid before/after runtime result in this environment.
The new cases and full existing suite must run with native PHP before accepting
these changes. All blocked commands and their actual errors remain in evidence.

## Security review boundaries

| Area | Source finding / disposition | Runtime status |
| --- | --- | --- |
| REST permissions, owner/share separation | Public routes authenticate edit capability; owner/share hashes remain distinct; malformed IDs hardened | PHP authorization suites pending |
| Nonce / CSRF | Admin and guest owner/share forms retain nonce plus capability/token checks; editor guest nonce omitted | Node nonce test PASS; WordPress flow pending |
| IDOR / design status | Wrong-ID tokens remain rejected; private/trash status hardened; no token returned in cart | PHP suite pending |
| Price / checkout tampering | Server reprices; Classic checkout compares expected price; cart identity and variation rechecked | PHP suites and real totals pending |
| Upload / private masters | PNG/JPEG <=10 MiB; bounded image header/dimensions; missing/public permission evidence rejected | Mock HTTP suite pending; real Drive pending |
| XSS / output | Existing escaping and preview bounds reviewed; malformed cart thumbnail ID rejected | Actual editor escaping PASS; native thumbnail rendering pending |
| SQL injection / locking | Internal option keys use prepared SQL with serialized expected value; release race hardened | Interleaving suite pending |
| Admin order | Existing edit-order capability and nonce retained | Actual admin role/order rendering pending |
| Rate limits | Existing IP-HMAC transient limits retained; counter is not an atomic distributed limiter | Concurrency/load behavior not verified |
| Drive retry/idempotency | Order-folder lost-response recovery retained and parent-scoped; persistent completion markers retained | Real service/retry tests pending |

Residual review item: shortcut/PDF creation can duplicate an artifact if Google
commits a create but its response is lost before the local completion marker.
Folder recovery does not by itself certify all artifact creation. Do not claim
exactly-once Drive production from this source review.

## Required next gates

1. Provide an authorized non-production PHP CLI runtime and execute
   `sh tests/run-source.sh`; fix any failures and record actual assertion counts.
2. Run the existing WordPress/Woo synthetic integration, including actual cart
   session/variation behavior, total calculations, order snapshots and lifecycle.
   Docker, Playwright and Chromium are unavailable here; no production fallback.
3. Keep **Classic Checkout required**. The explicit `kld_classic_required` Store
   API rejection remains. No Blocks compatibility is claimed; the added synthetic
   callback test is not Blocks runtime QA.
4. Staging must exercise Flatsome Child + Lumise, cart/checkout thumbnails and
   visible Design ID, owner verification/fork, HPOS as configured, private Drive
   confirmations/CORS and production retry recovery with approved test services.
5. Obtain the next Owner release gate before activation/deployment/checkout.
