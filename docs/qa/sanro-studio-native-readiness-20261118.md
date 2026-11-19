# SANRO Studio — Native readiness QA (18 November 2026)

Source reference: branch `work/sanro-studio-lumise-core-20261118`, files `includes/core.php`, `tests/native-readiness-gates.php`, `tests/native-missing-component.php` fetched from GitHub connector. Tested in an isolated local PHP 8.4.24 environment using manually reconstructed copies of these files, **not a full repository checkout**. No WordPress, WooCommerce runtime, database, browser, or production system was invoked.

Commands: `php -l` for the three files, then three separate invocations of `php plugins/sanro-studio/tests/native-readiness-gates.php <scenario>`, followed by `php plugins/sanro-studio/tests/native-missing-component.php`.

| Check | Result |
| --- | --- |
| PHP syntax: core and two test files | PASS (3/3) |
| WooCommerce absent | PASS |
| Buyer feature disabled | PASS |
| Buyer feature enabled | PASS |
| Order manifest capability missing | PASS |

No failures observed in these four isolated scenarios. This is not a complete HEAD regression, native editor E2E, or checkout verification. The readiness contract continues to report `checkout_enabled=false`, `production_ready=false`, and `verified_end_to_end=false`.

**Next:** run the exact repository files in a clean checkout with PHP lint and full source suite; perform staging browser tests on isolated WordPress/WooCommerce/Flatsome with synthetic data. Do not deploy, merge, or deactivate production plugins without Owner approval.
