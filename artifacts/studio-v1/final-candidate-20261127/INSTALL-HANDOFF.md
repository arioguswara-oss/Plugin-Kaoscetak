# SANRO Studio V1.1.0 — candidate handoff

Repository `arioguswara-oss/Plugin-Kaoscetak`, PR #52, branch `work/studio-mobile-checkout-integration-20261123`.

- Candidate source: `7aa6eb23d226272bc8a523b28854ccbed64ae497`
- Plugin version: `1.1.0`
- Candidate archive: `sanro-studio-staging-candidate.zip`
- Archive SHA-256: `8ed2ffc94f524231123e242dbc7c9d0add7d8ae723715f6ef6dae374bc7e22bb`
- Plugin tree digest: `8cc60d8c9d69e34a94dd3764df928d5557955d359739782a5791dfeee3972644`
- Source files: 89; ZIP members: 90 including `SANRO-STUDIO-BUILD.json`
- Runtime/default status: not tested by this build; Drive, DTF, GC and deployment remain OFF/not authorized.

## ChatGPT Work next step

Use this exact artifact and manifest for the separately authorized staging installation flow. Re-run the mandatory authenticated pre-install runtime gate for this candidate; install only on staging; then verify active version, installed tree digest against the manifest, smoke behavior, post-install gate, and rollback retention. Record fresh runtime evidence and keep the official baseline at 96.60% until acceptance produces qualifying evidence. Do not merge or deploy production.

The previous checkpoint's targeted regression PASS results are reused and were not repeated for this package-only task. The candidate build itself verified every included source file against the GitHub tree for source commit `7aa6eb23…` and two builds produced the same archive SHA-256. No staging, production, order, payment, Drive, DTF or GC state was changed.
