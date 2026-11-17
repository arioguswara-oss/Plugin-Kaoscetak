# Kaoscetak Lite Designer 0.3.2-rc.1 — DRAFT QA candidate

## Recovery and scope

Actual starting HEAD: `4381a5e6b81af9266eec1168d6f8afd0b38c8db8` on
`develop/lite-designer-v1`, Draft PR #2. Reported `3870a716...` was an older
sync checkpoint, not HEAD. Existing code, price decisions, mug 11 oz, 24 colours,
canvas fabric fallback, GPL license and earlier tests were retained.

Work continues on `codex/lite-designer-v0.3.2-rc1`, with a follow-up Draft PR
against the existing development branch. PR #2, main and documentation PR #3
are preserved. PR #3's proposed roadmap/gates were read, not merged; its P1
Lumise-inspired undo/snap/DPI/context toolbar backlog is not implemented here.
Issue #1 remains open until actual service/store/device gates pass.

## Implemented and fixed

- Native frontend editor on configured product pages; no Lumise commercial code,
  assets or editor instantiation. System fonts, canvas and browser APIs only.
- One PNG/JPEG <=10 MiB plus one multiline text layer per side. Proportional
  resize/drag, keyboard position controls, remove image, colour/size preview.
- Host-theme button padding/colours are scoped out of the fullscreen editor;
  native font measurement drives proportional text size within its saved box.
- Category maximums and per-product safe margins; measured product dimensions
  used in preview and server. Actual combined image/text cm bounds persisted.
  Switching preview size does not alter physical layout. Invalid ordered sizes
  are rejected rather than silently shrinking/repositioning the design.
- Full source alpha checked in bounded browser strips, including partial alpha;
  PNG extension alone never proves transparency. Optional background removal
  flags production review and preserves the original master.
- All official regular/long-sleeve/tunic presets and per-product overrides kept;
  no bare-shirt selling component. Unknown category rejected. Unpriced
  hoodie/sweatshirt stay unavailable until owner supplies prices/dimensions.
- Guest empty nonce header corrected. JSON/token responses are no-store.
  Save validates colour/preview/layer data before writing, validates actual small
  image headers, and keeps the combined cm bounds server-authoritative.
- Critical REST cart hydration fix: without reading the native session first,
  WooCommerce's first add hook could reload the old session and erase the first
  size line. Real HTTP tests reproduced this; explicit hydration fixes it.
- Real custom wildcard variations retain native attribute option casing; native
  validation errors surface as plain messages. A real-Woo test reproduced the
  incorrect slug substitution for custom attributes before this fix.
- Same-design atomic writer lock, replay-safe cart addition, native WooCommerce
  add validation hooks, quantity/variation/stock handling and partial rollback.
  Existing buyer cart is preserved. A verified email cannot unlock a cart design.
- Checkout rechecks product/colour/size/qty/physical area and confirmed private
  masters. Pending replacement, deleted file, wrong metadata or image-header
  mismatch fails visibly. Browser-supplied prices are never authoritative.
- Owner edit/reorder creates a private independent copy. Public forks do not
  inherit verified identity/discount. Share remains opt-in, revocable and unable
  to use the private edit endpoint. No automatic data deletion before an
  approved retention policy; Drive masters/order snapshots are never deleted.
- Direct original-byte upload uses 1 MiB chunks/status probe/resume/timeout.
  No OAuth bearer/refresh token is given to browser. Private folder ID selection
  validates access and rejects public/domain permission. Confirmed folder
  reference is retained so new folder selection does not invalidate new-design
  references to an older approved folder.
- Bounded authenticated image inspection reads at most 256 KiB of header data,
  never stores or renders the full master on hosting. Original file remains in
  Drive. JPEG/PNG dimensions are limited to 26 MP before expensive browser decode.
- Native Classic order snapshots, admin preview/private Drive link, small PDF
  guide with layer/union cm dimensions. Production is queued through WooCommerce
  Action Scheduler (WP-Cron fallback), not run synchronously in a payment hook.
  Atomic production lock and app-property lookup recover an order folder even
  after its create response is lost. Failed preparation is visible for retry.

## Local test evidence

Runtime: WordPress 7.1.4, WooCommerce 11.3.0, PHP 8.3.36, Chromium.
Final storefront checks use the GPL Twenty Twenty-One Classic theme.
A missing guest button during fixture re-runs was traced to WooCommerce's native
coming-soon page; the local fixture now sets explicit store visibility. No live
visibility option was changed. Native Product Blocks are outside the tested
Classic product-form path. Flatsome itself is not available locally.
Disposable loopback-only environment, synthetic products/customers/orders.
HPOS option was `no`; actual HPOS compatibility is NOT VERIFIED in this run.
Google and email transports are explicitly intercepted by fixture-only code.
No real OAuth credentials, card/SDK requests, emails or production data are used.

Commands: `KLD_ALLOW_LOCAL_FIXTURE=1 sh tests/setup.sh`, `sh tests/run.sh`,
`python3 scripts/build.py`, `pdfinfo tests/results/guide.pdf`.
No CLI/Node/Composer is required on the target WordPress website.

The final real WordPress regression has 193 passing assertions plus a real
Woo custom-wildcard S/L integration case. Full local run exited 0.
Raw logs/reports/screenshots, final assertion count and exact ZIP/hash are
recorded under the versioned review package. PASS means only the stated local
scope, not the real service. The final local suite covers:

| Check | Status / scope |
| --- | --- |
| PHP syntax + 9 existing standalone suites | PASS, PHP 8.3; official matrix/side swaps, overrides, owner/share/fork/repeat/snapshots |
| Real WordPress REST/cart/production assertions | PASS; quantities/size/category, atomic save, privacy, mail verification, file status/header, repeat 5%, folder lost-response recovery |
| Real storefront Chromium | PASS at 320×740, 390×844, 768×1024, 1440×900; guest designer → image/text → save → preserved multi-size cart → native validation |
| Completed checkout | PASS local synthetic COD only, 390px; exact order total and immutable snapshot; no real payment |
| Upload failure/resume | PASS mocked Google transport; 9,437,264 original bytes at 390px, forced mid-upload failure, same-session offset resume |
| Isolated editor regressions | PASS five shirt viewport cases + mug, opaque PNG vs alpha=254, prior browser smoke |
| Admin no-Drive/lifecycle | PASS preview/save block, guest hidden, Escape/focus; deactivate/reactivate preserves data; absent Woo returns 503 |
| Guide PDF | PASS local native PDF parser, A4; production guide only, not print-ready rendering |
| Real Google OAuth/Drive/CORS/quota | NOT VERIFIED, no merchant account authorization performed |
| Real SMTP delivery | NOT VERIFIED; only intercepted verification flow tested |
| Flatsome Child + Lumise, production cache/plugins | NOT VERIFIED; commercial theme/actual stack not available locally |
| Real Android 4 GB, Safari/Firefox, PageSpeed >=90 | NOT VERIFIED; asset sizes measured separately, no score claim |
| HPOS and PHP versions other than 8.3 runtime | NOT VERIFIED |
| Checkout Blocks | Explicit RC limitation: KLD carts fail closed with Classic-required message; other Woo carts unchanged |

## CI audit

The prior report of FAILURE was stale for the recovered HEAD: source-check run
40318238881 actually succeeded, including PHP/JS, nine unit jobs and responsive
Chromium. Job steps/log access was inspected. It did not certify production,
Drive or the new candidate. This is not evidence explaining any older failed run.

The packaging workflow now uses the versioned builder, installs the exact ZIP
in real WordPress/WooCommerce, runs native integration and browser/lifecycle
checks, compares deterministic rebuild, and publishes QA-only artifacts for
14 days. Fresh candidate CI status is recorded on the follow-up PR from its own
jobs/annotations. Billing/runner blockage is separate from local PASS; never
skip tests or claim PASS for an unstarted job.

## Remaining risks and OPEN decisions

Google's resumable session URL is a file-scoped bearer capability with a
Google-controlled lifetime. The app rejects confirmation after two hours; that
is not a claim of Google-session revocation or a guaranteed short lifetime.
Keep the URL out of analytics/logs. Real CORS including PUT, Content-Range,
Range exposure and status queries must pass before public use. Existing folder
IDs require drive.file access; arbitrary folders may need Google Picker, which
is not implemented. Default app-created private folders avoid broad Drive scope.

If direct CORS or strict session-lifetime policy requires a worker, seek Rio's
approval first. Proposal only: an upload relay with a dedicated app identity,
folder-limited credentials, per-design/side single-use signed tickets with short
expiry/byte/type limits, origin checks, rate/quota limits, streaming to private
Drive, callback attestation and redacted audit logs. Original bytes bypass PHP
hosting; Google bearer/session URL stays behind relay. Planning allowance
USD 5–15/month plus existing Google storage/egress, subject to vendor pricing and
traffic review; this is not a quote or purchase. No worker, account or DNS was
created and no cost was committed.

Existing live v0.2.0 metadata/active carts and legacy orders were not migrated
or inspected. Missing legacy file attestations may fail closed and require
authorized confirmation/re-addition; review archives before upgrading.

Locks protect KLD same-design commands; unrelated native/third-party Woo writes
retain WooCommerce's underlying session concurrency semantics. Browser decoding
is bounded to 26 MP, but real low-memory device performance is unverified.
Owner links are bearer links; no-referrer/no-cache applies, but operators must
redact verification/owner query keys from access/analytics logs. Permanent
retention/garbage collection stays OPEN and automatic cleanup is off by default.

## ChatGPT Work QA / owner-approved upgrade and rollback

1. Obtain Rio's approval for the exact live upload/activation and each write test.
   Take files/DB/HPOS/options backup; verify restore access and retain live v0.2.0
   ZIP. Confirm current checkout type, PHP/WP/Woo/Flatsome versions and cache.
2. Verify candidate SHA-256. Use WordPress Upload Plugin replacement for the same
   root directory. Keep editor public mode OFF; keep Lumise and Flatsome Child
   active, and payment/hosting/cache settings unchanged.
3. Use a specifically authorized test product as admin: no-Drive preview, image
   and text per side, safe areas, every official price example, sizes/quantities,
   colour availability and canonical Woo product/cart behavior.
4. With separately authorized merchant OAuth, use a private app-accessible
   folder. Inspect direct requests, CORS/preflight/Range, token exposure, original
   downloaded byte/hash equality, 10 MiB limit, cancellation/retry, expired/revoked
   credentials, quota/network failure, and block checkout for unconfirmed files.
5. Verify real email delivery, guest ownership, opt-in share/revoke, independent
   fork and repeat identity/paid-order eligibility; recipient cannot edit source
   or fetch private masters. Redact all owner/share/session credentials in evidence.
6. Only after explicit test-order approval, use an agreed sandbox/native method
   without a real charge. Check line prices/qty, shipping/tax, snapshots, queued
   production/retry, admin image/private folder link and PDF guide completeness.
7. Test actual Flatsome/Lumise coexistence, HPOS, desktop and real HP 4 GB. Record
   editor-specific mobile Lighthouse/PageSpeed; do not substitute homepage score.
8. Record PASS/FAIL/BLOCKED with screenshots/logs; ask Rio before enabling public
   use, merging main or changing Lumise. Any unresolved P0 gate keeps DRAFT.
9. Roll back by deactivating this plugin and reinstalling retained v0.2.0.
   Keep all designs/orders/Drive files. Do not restore an old DB over new orders,
   delete masters or change theme/gateways. Retention changes need a separate
   approved policy. ChatGPT Work can copy the QA package to the agreed
   "Plugin wordpress" Drive folder after review; this development task does not
   perform a merchant OAuth flow or declare a final public release.
