# Read-only security review — 2026-11-23

## Scope and disposition

Repository `arioguswara-oss/Plugin-Kaoscetak`; base
`feature/studio-bootstrap-admin`, commit
`71955dd3d4df9669b1e667e928a64508a2d579b6`. Review branch:
`work/codex-studio-ci-20261123`. Plugin code was read only, never changed.

**BLOCKED: requested implementations are absent.** Git's tree contains no
`includes/draft-rest.php`, `includes/master-storage.php` or
`includes/checkout-bridge.php`, under SANRO Studio or elsewhere. No substitute
implementation was imported from another branch. No vulnerability severity or
safety approval can be assigned to code that is not present.

Reproduce the inventory at the reviewed base:

```sh
git ls-tree -r --name-only 71955dd3d4df9669b1e667e928a64508a2d579b6 -- plugins/
git ls-tree -r --name-only 71955dd3d4df9669b1e667e928a64508a2d579b6 -- plugins/ | rg '(^|/)(draft-rest|master-storage|checkout-bridge)\.php$'
```

The second command returns no matches (exit 1). Absence is a review blocker,
not evidence of an exploitable endpoint or of complete protection.

| Requested target | Nonce / CSRF | permission_callback | IDOR / ownership | Path traversal | SVG |
| --- | --- | --- | --- | --- | --- |
| `includes/draft-rest.php` | BLOCKED | BLOCKED | BLOCKED | BLOCKED | BLOCKED |
| `includes/master-storage.php` | BLOCKED | BLOCKED | BLOCKED | BLOCKED | BLOCKED |
| `includes/checkout-bridge.php` | BLOCKED | BLOCKED | BLOCKED | BLOCKED | BLOCKED |

## Observations from available boundary code

These are bounded source observations, not runtime penetration-test results.

- `plugins/sanro-studio/includes/editor-config.php:40–59` registers a public
  read-only GET endpoint using `permission_callback => __return_true`.
  Lines 8–37 restrict responses to published, purchasable, opted-in products and
  construct an allowlist of public editor configuration. A nonce is not needed
  solely to read intentionally public product configuration. This callback must
  not be reused as authorization for private draft or upload mutations.
- `plugins/sanro-studio/includes/buyer.php:152–157` conditionally generates a
  draft nonce when an enabled draft API helper exists. Nonce generation does
  not demonstrate server verification or per-owner authorization. Guest WordPress
  nonces alone are not design ownership credentials.
- `plugins/sanro-studio/includes/buyer.php:203` limits the file picker to
  PNG/JPEG. HTML `accept` is a convenience, not validation. It proves neither
  server rejection of SVG/polyglots nor upload-path containment.
- `plugins/sanro-studio/includes/product-area.php:113–117` documents the native
  WooCommerce save hook's upstream nonce checks and additionally requires
  `edit_post` for the product. Upstream checks were not exercised in a real
  WooCommerce runtime; no missing-nonce vulnerability is inferred from the
  absence of a second local nonce check.

## Required follow-up once source is supplied

The following are acceptance checks, **not confirmed defects**:

1. For private REST/AJAX mutations, inspect every route permission callback and
   verify the appropriate nonce/session controls. Reject missing, invalid and
   expired credentials. Nonces must not replace ownership checks.
2. Test guest A versus guest B and user A versus user B against all design,
   master, fork, share-revoke and cart IDs. Share-read credentials must not grant
   owner mutations. Checkout must validate the session's design ownership and
   authoritative price/file readiness on the server.
3. Verify unguessable server-generated storage references, canonical path
   containment and rejection of absolute paths, traversal, encoded separators
   and symlink escapes. Never expose private masters or long-lived tokens through
   public URLs, REST output or logs.
4. Validate actual PNG/JPEG bytes and dimensions with size limits; reject SVG,
   MIME/extension mismatches and malformed/polyglot content server-side. Exercise
   direct requests that bypass the browser file picker. Escape metadata on output.
5. Exercise replay/retry and unauthorized checkout without invoking real payment
   gateways or touching production customer data.

## Validation and limits

Available offline checks: PHP lint, JavaScript syntax, stubbed bootstrap contract
and Node estimate/cart tests PASS. They do not test the missing handlers. No
real WordPress, browser security testing, Drive OAuth, master upload or payment
test was performed. See [ledger](DEVELOPMENT-LEDGER.md) and
[runner instructions](../tests/README.md).

**Security approval: NO-GO** for the requested private draft/storage/checkout
boundaries until their source is available and ownership/upload integration tests
can execute. This task produces a Draft PR for tests and documentation only.
