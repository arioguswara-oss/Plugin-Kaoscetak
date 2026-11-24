# SANRO Studio — pinned direct staging candidate

Source `f45b43ba191ce49ffad67070e0643918765fe719`; feature `e9fb9c4`. Immutable plugin ZIP and manifest are copied byte-for-byte from the previously verified candidate; no rebuild or repeated application test was required. Readiness documentation/artefact commit can be newer than this deliberately pinned source. Only application code at this source is proposed for installation.

- `SHA256SUMS`: ZIP and manifest checksums.
- `readiness-evidence.json`: real WordPress MCP and Cloud Browser read-only evidence.
- `approval-request.json`: concrete conditional request, **not approval**, no apply-ready approval file.
- `E2E-PLAN.md`: buyer cases and HTTP422 capture plan for the authorized browser.

Pinned tools are in repository commit `f45b43ba191ce49ffad67070e0643918765fe719`; their hashes are in the request. Keep all three tool files together. PLAN_ONLY first; never use the request JSON as an approval. Existing live-plugin digest/FPM/flags are unverified; no installation may proceed yet.

WordPress classic cart/checkout PASS; old runtime is visibly different from candidate. DTF/Drive/GC remain defaultOFF; runtime flags are not claimed verified. Jack Developer, Hermes and Codex are not dependencies or launched workers. Baseline remains90.50%.
