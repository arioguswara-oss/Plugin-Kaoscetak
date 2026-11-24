# SANRO Studio staging installation attempt — 24 November 2026

## Result

Owner approved only the exact staging target, source SHA, ZIP SHA256, manifest SHA256 and existing tree digest recorded in `artifacts/studio-v1/793784f/approval-request.json`. Candidate checksums still match. **Installation was not completed** because the pinned official installer rejected its own read-only WordPress activation-state probe before entering the replacement transaction.

The staging `active_plugins` option contains seven active entries at numeric keys `[0,1,2,3,4,6,7]`. SANRO Studio and WooCommerce are present and active. Because inactive plugin removal left a sparse PHP array, `json_encode()` returns an object. `tools/studio-staging-install.py:24-25` requires the probe result to decode as a Python list and refuses with `existing_active_plugins_required`. The installer emits only a generic refusal, so the probe representation was independently inspected read-only after the refusal.

This is an installer precondition **FAIL**, not evidence that the plugins are inactive or that the site identity changed. No database normalization, probe wrapper, or modified installer was used to bypass the guard. The exact approved candidate was not installed.

## State after refusal

- Site URL remains `https://saddlebrown-peafowl-616656.hostingersite.com`.
- Installed SANRO Studio digest remains `e58d3cd03d3dba87ce17d8121e19720ab2dc9939394130c9fcd142ce8fd7bab9`; no build marker.
- No installer lock sentinel or transaction journal exists; rollback directory was empty.
- No incoming, backup, or quarantine directory remained. The temporary private transport directory, empty rollback directory, and local machine-approval file created for the approved attempt were removed.
- No order DB, other WordPress database value, production path, DTF/Drive/GC flag, or website file was changed by the refused installer attempt.
- Post-install digest/version/menu/asset/feature verification and smoke test are **NOT TESTED**, since installation did not occur.

## Safe next step

Correct the official installer probe to normalize the active plugin array before JSON serialization, preserve all current target and feature-flag checks, update the pinned tool hashes and approval package, then rebuild/rebind the candidate because its source marker is commit-bound. That changes the approved hashes; obtain a new explicit Owner approval before another installation attempt. Do not normalize the live database option as a workaround.

## Progress

Official baseline remains **90.50% → 90.50%, delta 0.00**. This gate failure does not change the seven-stage feature estimates. DTF generator, Drive and storage GC remain OFF. No install, deploy, merge, production change, order DB mutation, or E2E write occurred.
