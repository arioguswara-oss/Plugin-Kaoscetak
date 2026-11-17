# 0.4.0-owner-test.1 — current Owner test candidate

On 18 November 2026 Rio explicitly requested combining the additional roadmap
features and testing directly on kaoscetak.id instead of staging. See
OWNER-TEST-0.4.0.md. Source checks and controlled synthetic tests do not certify
live runtime. The additional public UX option defaults OFF; admin can exercise
it first. No installation, deployment or merge was performed by this task.

The following 0.3.2 notes are historical and still describe core compatibility
limits unless superseded above or in the current Owner test instructions.

# 0.3.2-rc.1 — historical DRAFT QA notes

Continues actual v0.3.1 checkpoint 4381a5e. Not a new plugin. No automatic
activation, option migration, price reset or production deployment. Automatic
draft deletion is disabled by default while retention policy is OPEN; existing
cleanup hooks also no-op unless an explicitly approved policy opts in. Keep public
mode OFF until owner-authorized Google OAuth/upload and actual store QA pass.

Implemented: unchanged per-product official price tables, mug 11 oz/24 colours,
front/back image + multiline text, cm union bounds, proportional image controls,
colour/size preview, per-size quantities, private save/load/fork, email ownership,
opt-in/revocable sharing, verified repeat pricing, native Classic cart/checkout,
immutable order snapshots and small PDF production guides. Production preparation
is queued through WooCommerce Action Scheduler (WP-Cron fallback); payment
status hooks do not make Drive HTTP requests. Failed jobs show order errors for
manual retry. Actual cron/queue delivery remains an owner QA gate.

This RC fixes cart unlock on email verification, replayed additions, partial
save writes, missing native add validation hooks, stale master/colour/product/
quantity/area checks, concurrent design writes and production locks, folder
recovery after lost responses, UI safe-zone/product measurement mismatch,
source alpha inspection, upload resume/timeouts and keyboard dialog controls.

Drive storage: browser sends original bytes directly to a per-file resumable
URL in 1 MiB chunks, with status probe/retry. No OAuth access/refresh token is
returned to the browser. Treat upload URLs as private bearer capabilities:
Google controls their lifetime; the app rejects confirmation after two hours,
but does NOT claim it can revoke/shorten Google's session lifetime. Metadata,
private permissions and bounded image header (max 256 KiB) are inspected on
server; master bytes are never stored/rendered on WordPress. Browser thumbnails
are validated <=60 KiB each. Background removal affects preview and flags the
operator; original masters remain intact.

OAuth/account consent, direct upload CORS, exposed Range header, real provider
retry and Google permissions are NOT VERIFIED with a real account. Never enable
public checkout based only on synthetic transport tests. Folder IDs in settings
must be accessible to drive.file scope; app-created folders work without a broad
Drive scope. Arbitrary existing folders may need Picker authorization, which
is not implemented. No extra service is deployed.

Compatibility: Classic Checkout is the tested RC path. Store API rejects carts
containing KLD designs with a clear Classic-required error pending a complete
Blocks order-metadata implementation. Other WooCommerce carts stay native.
Unsupported extra variation attributes fail visibly. Flatsome/Child/Lumise,
production cache/SMTP, real Android 4 GB/Safari and PageSpeed >=90 are NOT VERIFIED.
Canvas silhouettes and colour codes remain simulations, not approved fabric photos.

Install ONLY after separate owner approval: verify SHA256SUMS, back up files,
DB/HPOS and plugin options, retain exact v0.2.0 live ZIP, use WordPress Upload
Plugin replacement for kaoscetak-lite-designer/. No Composer/npm on target.
Inspect in admin preview first without Drive. Do not disable/delete Lumise,
switch Flatsome, change gateways or publish the editor to all buyers.

Rollback: deactivate this plugin and replace with retained reviewed v0.2.0.
Preserve designs, order snapshots and Drive files. No uninstall deletion occurs.
Do not restore an old database over newer orders. Do not delete shared masters.
