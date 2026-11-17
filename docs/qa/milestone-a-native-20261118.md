# Milestone A — native source gate, 18 November 2026

**PASS for the source gate. GO to isolated staging QA. Release remains NO-GO.**

Full Git clone succeeded. PR #12 was open/Draft at
`6e952de37d5e7d785799a66f8cf6173d66b793a6`, three commits after requested
checkpoint `57e5624`; those changes were preserved. `git fsck --full` and the
checkpoint ancestry check passed. Work used isolated local branch
`fix/kld-milestone-a-runtime-20261118`. Git CLI push lacked HTTPS credentials,
so publishing used the authenticated GitHub Connector. Published implementation:
`1755f928f7b64d8e3aa8384dcfe61c0f3c31017e`, retaining the actual upstream parent. Native tests ran on
local commit `9fa5b9be44c58b9e8c23da41524e8b28ff3b4c12`; both source trees are exactly
`583b68ac69c42f15b83b2a5a7dddc7180a52ea03`. Only documentation/evidence follows this source commit.

This report supersedes the unavailable-PHP conclusion in
[the earlier source report](milestone-a-20261118.md). Historical results remain
in that report. Milestone A here means executable **source lint/regression**;
it does not certify the WordPress/browser/provider release gates.

## Actual commands and results

Native Debian PHP 8.4.24 CLI was unpacked into a task-owned directory, together
with libargon2; no sudo/system installation or production server was involved.
Package SHA256 values were checked against the Debian HTTPS package index and
are recorded in evidence. The wrapper executes native `php8.4 -n`, not a parser,
Wasm, or simulated PHP interpreter. Node is v24.22.1.

Reproduce source checks with any compatible native PHP CLI:

```sh
PHP_BIN=/path/to/native/php sh tests/run-source.sh
```

Executed command families (full per-command arguments/stdout/stderr in JSON):

| Command | PASS | FAIL |
| --- | ---: | ---: |
| `php -l <file>` for every tracked PHP file | 30 files | 0 |
| `php plugins/kaoscetak-lite-designer/tests/pricing-smoke.php` | 1 suite; 780 assertions | 0 |
| `php tests/test_*.php` individually | 12 suites | 0 |
| `node --check <file>` | 4 files | 0 |
| `node --test tests/test_editor_unit.cjs` | 6 tests; includes 456 official UI tariff assertions | 0 |
| `sh -n tests/run-source.sh tests/run.sh tests/setup.sh` | 1 command | 0 |
| `git diff --check` | 1 command | 0 |
| `PHP_BIN=… sh tests/run-source.sh` | complete runner, exit 0 | 0 |

Two additional Python/Playwright browser commands were attempted. Both exited 1
before page creation: Chromium aborted at `socket(): Operation not permitted`.
These are **browser environment BLOCKED**, not passing tests or an observed
application assertion failure; no browser result is counted in the source PASS.

All 13 PHP suites ran both on upstream HEAD and after the fix. Native execution
found no failures in the existing suites. A newly added fault-injection case
**did fail before the fix**, creating a duplicate shortcut when the POST had
committed but both its response and subsequent search visibility were missing.

| PHP suite | Executed result after fix |
| --- | --- |
| `plugins/kaoscetak-lite-designer/tests/pricing-smoke.php` | 780 assertions PASS |
| `tests/test_drive_security.php` | 72 total assertions PASS; the earlier 25 are included |
| `tests/test_editable_fork.php` | 16 assertions PASS |
| `tests/test_lock_security.php` | 10 assertions PASS |
| `tests/test_order_snapshot.php` | snapshot hook/immutability checks PASS |
| `tests/test_owner_verification.php` | 12 assertions PASS |
| `tests/test_price_presets.php` | 33 assertions PASS |
| `tests/test_repeat.php` | 10 assertions PASS |
| `tests/test_repeat_checkout.php` | 7 assertions PASS |
| `tests/test_rules.php` | 330 assertions PASS |
| `tests/test_server_validation.php` | 54 assertions PASS |
| `tests/test_share_clone.php` | 11 assertions PASS |
| `tests/test_share_security.php` | 21 assertions PASS |

## Drive fix and evidence boundaries

The previous implementation queried `appProperties` before creating a shortcut
or PDF. A successful create with a lost response can still be absent from a
subsequent list response, allowing another create. Local completion markers do
not cover that interval.

The fix reserves a provider-generated ID in an atomic, non-autoloaded WordPress
option **before** creating the resource. Competing workers read the winning
reservation. Shortcut IDs use `generateIds?type=shortcuts`; PDFs/order folders
use `type=files`. Retries keep that ID even after timeout, stale listing, 404,
409, or a process ending before the order marker is saved. Only an explicit
404 permits another create attempt, always with the reserved ID. Exact-ID
confirmation validates MIME, parent, non-trashed status, order/artifact key and
shortcut target. Invalid metadata, malformed JSON, incomplete/ambiguous search,
failed option persistence and non-404 lookup errors stop completion.

Existing matching resources can be adopted after scoped legacy lookup and exact
validation. Duplicate matches stop processing for admin review. The reservation
records must not be deleted during retries, rollback, or an ordinary cleanup;
they are durable idempotency state. No retention/deletion policy was introduced.
This protects creates using the reservation protocol; it cannot retroactively
prove the absence of an invisible orphan created by an older implementation.

The expanded native suite executes the real shortcut, small-PDF and folder
helpers against a stateful fake provider. It proves that lost responses plus
stale list/GET responses result in one resource, repeated POSTs carry the same
ID, recovery makes no further POST, bad metadata is rejected, and a reservation
race uses the winner. It also exercises the actual `tests/drive-fixture.php`
adapter used by the WordPress fixture. **No real Google request was made.**

Official API contracts used:

- [Drive generated IDs, including shortcut type](https://developers.google.com/workspace/drive/api/reference/rest/v3/files/generateIds)
- [Create retries with pre-generated IDs and HTTP 409](https://developers.google.com/workspace/drive/api/guides/create-file)

## Evidence and remaining work

- [Baseline native execution](evidence/milestone-a-native-20261118/baseline-execution.json)
- [New failing fault-injection case before fix](evidence/milestone-a-native-20261118/drive-before-fix.txt)
- [Final native execution with command outputs](evidence/milestone-a-native-20261118/execution.json)
- [Full source runner output](evidence/milestone-a-native-20261118/run-source.txt)
- [SHA256 of tested plugin/test files](evidence/milestone-a-native-20261118/tested-source-sha256.json)
- [Staging prerequisite probe](evidence/milestone-a-native-20261118/staging-preflight.json)
- [Actual browser launch errors](evidence/milestone-a-native-20261118/browser-launch-blocked.json)
- [Prepared staging QA procedure](staging-pr12-20261118.md)

**Development/source gate:** no remaining failure in the requested suites.
**Test gate:** this environment lacks Docker, which the existing isolated
WordPress/MariaDB fixture requires; Chromium and both Playwright bindings are
available, but launching Chromium is denied by the execution environment's
socket restriction. That fixture and actual Flatsome/Child/Lumise runtime have not been
executed for this commit. Real provider behavior and physical Android performance
remain unverified. Prior RC screenshots/ZIPs do not certify this new commit.
**Release gate:** NO-GO pending required runtime QA and Owner approval.

No GitHub Actions prerequisite, workflow/runner change, production access,
live database/theme/Lumise/gateway/SMTP/OAuth change, merge, deployment or FINAL
ZIP was performed. Existing successful SMTP/WhatsApp/Drive setup tests were
not repeated.
