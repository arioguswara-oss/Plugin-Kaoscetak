# Pricing / CI completion evidence — 2026-11-23

## Recovery and source identity

Recovered existing work; no development or full suite was repeated during quota
recovery. Pricing branch is already pushed at
`c4dd7155dd4ba800aa47d7542465f6e849520d26` (PR #51). CI changes are committed/pushed
on the existing PR #48 branch. See its final HEAD for the runner and self-test.

Latest read-only PR #50 fetch: `1d35edac388a9317832669d5a9ca0020b2c8013d`.
`pricing-quote.php` is byte-identical to the completed PR #51 calculator at
`b39d13f`. Hermes has integrated that calculator, its quote callers and #46
hardening. Do not restore/reapply this shared calculator or claim it as a missing
adapter. `pricing-woocommerce.php` and `tests/studio-pricing-woocommerce-offline.php`
are not present on this fetched restore HEAD; they remain unique pricing deltas.
The new CI runner/self-test are also absent there.

## Completed tests (logs recovered, not rerun)

| Scope / source | Result | Evidence |
| --- | --- | --- |
| Pricing adapter branch `c4dd715` | PASS | `PASS c4dd7155dd4ba800aa47d7542465f6e849520d26`; 31 new adapter assertions plus existing 688 quote assertions, four PHP scripts and three Node cases; PHP/JS syntax gates PASS. |
| CI sparse baseline `e8404d6` + current runner/self-test overlay | PASS | `SUMMARY passed=15 failed=0 php_tests=1 node_tests=2 not_tested=0`. Includes three asset test cases and one runner contract case with failure/missing-runtime/unclassified/integration isolation assertions. |
| Detached restore `0b3004b` + three CI test files only | FAIL | `SUMMARY passed=332 failed=4 php_tests=107 node_tests=17 not_tested=27`. No plugin or existing test-source patch was present in the final run. |
| Latest restore `1d35eda` | NOT TESTED | Fetched and compared only after quota recovery; no tests rerun as instructed. Earlier restore FAILs cannot be asserted to persist unchanged here. |
| Real WordPress/browser/checkout/production | NOT TESTED | Offline objects only; no orders, deployment or live access. |
| GitHub Actions | NOT TESTED | No workflow change or GitHub CI success claim. |

Command counts include syntax and executable checks, not individual assertions.
PHP 8.3.36 CLI (read-only repository mounts), Node v24.22.1. All stdout summaries
contain checkout HEAD; overlays were uncommitted at testing time, so HEAD alone
is not claimed to identify the complete tested tree. Runner source and self-test
are saved on CI #48; pricing source was tested at its committed SHA.

## Earlier restore failure inventory (0b3004b only)

- `plugins/sanro-studio/tests/cart-contract.php`: `server quote retained` fails.
  Its fixture lacks product-design and master-binding validator functions now
  required by the real contract; the successful quote path is not reached.
- `plugins/sanro-studio/tests/draft-lock.php`: `mismatched order quantity denied`
  fails. This expected error differs from the currently restored guard path.
  Resolve source/fixture expectations without weakening authorization checks.
- `plugins/sanro-studio/tests/native-core.php`: `native function inventory detects
  provided fixtures` fails; the required checkout bridge fixture function is
  absent from that fixture's declared inventory.
- `tests/test_sanro_studio.php`: exact four-hook legacy expectation fails against
  restored native hook registrations. It needs an explicit legacy opt-in and
  fixture/menu contract review; no unverified replacement test was shipped.

These failures were surfaced, not excluded. A separate runner invocation bug
(`native-readiness-gates.php` requires a scenario argument) was fixed by running
all three documented scenarios. Real GD/private font artifact drivers are
classified NOT TESTED, not falsely passed without required configuration.

No further full-suite run was done on latest restore. Lead/Hermes must reconcile
latest failures at integration time. This handoff is development evidence, not
production release approval.
