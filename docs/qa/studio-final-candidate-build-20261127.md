# SANRO Studio V1.1.0 — reproducible candidate build

PR #52 · integration branch `work/studio-mobile-checkout-integration-20261123` · baseline **96.60%**.

## Candidate

- Verified source HEAD: `7aa6eb23d226272bc8a523b28854ccbed64ae497` (plugin Version header and runtime constant both 1.1.0).
- Plugin tree digest: `8cc60d8c9d69e34a94dd3764df928d5557955d359739782a5791dfeee3972644`.
- ZIP: `artifacts/studio-v1/final-candidate-20261127/sanro-studio-staging-candidate.zip`.
- ZIP SHA-256: `8ed2ffc94f524231123e242dbc7c9d0add7d8ae723715f6ef6dae374bc7e22bb`.
- Manifest SHA-256: `8cafd62d8f9b345c6d00b5556d9ca51c06f13f38ff8f028d76e22b993a85bda3`.
- 89 production source files; 90 ZIP entries including the source-bound build marker. ZIP CRC, file sizes, per-file SHA-256, plugin digest, version, and build marker verified. Two builds produced the same ZIP SHA-256.

## Provenance and build method

The GitHub PR ref was checked at source commit `8c2af1f3ac0547387b0905dfe985f0ad283b7acf`. A byte-count/tree comparison found `pricing-integration.php` in that commit was incomplete from the prior Git Data transfer. The complete file was restored using base64 blob transport, with the new blob SHA independently checked against the local full file, in fast-forward commit `7aa6eb23d226272bc8a523b28854ccbed64ae497`. This correction is why the candidate source is newer than the requested starting HEAD.

The repository's candidate packaging rules were applied to the recursive GitHub tree at `7aa6eb23…`: all 89 packaged source blobs matched their GitHub tree entries; default-off policy, sorted ZIP paths, fixed timestamps, build marker, manifest schema, and SHA-256 digest construction were retained. The checked-in builder itself requires a clean local Git checkout at HEAD; because the available checkout was a different dirty worktree and direct GitHub fetch was unavailable, a temporary snapshot-aware runner applied those same packaging rules in `/tmp`. No existing worktree was changed.

## Handoff

This is a built candidate only. No staging gate/install, production deployment, merge, payment, live order, Drive, DTF, or GC action was performed. Reuse the previously passing targeted regressions. ChatGPT Work should apply its separately authorized staging procedure to this exact ZIP, run the mandatory authenticated pre-install gate, verify active 1.1.0 and installed digest `8cc60d8c…3972644`, run runtime smoke and post-install gate, retain rollback, and record runtime evidence before changing the 96.60% baseline. See `INSTALL-HANDOFF.md` in the artifact directory.
