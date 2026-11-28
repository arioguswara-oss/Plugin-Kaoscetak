# SANRO Studio lifecycle fix candidate — 2026-11-28

- Source: 4772561ded172bd1d62bb88867b92646f878091e (PR #52 integration branch).
- Version: V1.1.0; package channel: candidate; not installed.
- Plugin members: 89; plugin digest: 883c11ffc3d900264610eb9a0900083bdfb7d7a32bb29903a6c35c377ca55442.
- ZIP SHA-256: f34140e754fce2c8915afcead9fbc61be3466b0f026f96f709e0124c9bd6d46a.
- Manifest SHA-256: 0a837204a8a78a209a84b3f3f705637fb29762087b95724b4124b6778dfd4935.
- Build: two clean builds were byte-identical; ZIP CRC/member verification passed.
- Runtime/install: not tested/not installed. Existing checkout transport 403 remains an external QA boundary; no guard was bypassed.
- Baseline: 96.60% unchanged.

## Targeted checks

PHP lint passed for the changed plugin files and new Store API guard fixture. Store API guard, 40 production package checks, 17 checkout/lock integration checks, order preview checks, private master binding checks and 32 batch-lock checks passed.

## Scope evidence

The build includes only the plugin package. Tests, transport utilities, credentials, and staging harnesses are excluded by the existing candidate builder.
