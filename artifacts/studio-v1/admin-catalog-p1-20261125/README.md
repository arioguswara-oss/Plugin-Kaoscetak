# SANRO Studio P1 catalog lifecycle — staging evidence

Source milestone `3a3a3ea6606608143658a55e998f2bbbdb9be8ee` (includes the catalog lifecycle and the follow-up guard preventing a new design fork from an inactive product); PR #52. The candidate is SANRO Studio 1.1.0 and is installed/active on staging only.

## Candidate and installation

- Candidate SHA256: `5a0b02537e8e50a93d975312457140c7b3d6a129a134ad3003cd0a93ff52397d`.
- Manifest SHA256: `ef4926df914907f7b7d1b5e42c49f2038dc0d54e52c4c0236bde187473903e4e`.
- Plugin digest: `6b943e4e73eeb8a9b3b7e9c24df4e8c500c1d9fbfc57847e48f46675755e025b`.
- Verified installed tree digest: `c6da001ee3b8cd22c684c44615a17aaeea19c43a386577df38778ba748fcea79`.
- Source-bound transport SHA256: `62be3d4ee53392c329eb8985772825346bae2e6cb00550f247b37fbdfe53a5ef`.
- Pre-install and post-install authenticated HTTPS runtime gate: PASS for source `3a3a3ea`, LiteSpeed PHP 8.3.37, predecessor digest revalidated, private roots PASS, all protected writer flags OFF, rollback retained.
- SANRO Studio 1.1.0 remains active. Temporary transport restored to 0.3.3 and inactive; temporary application password revoked; remote temporary ZIPs removed.

## Focused acceptance

Synthetic WooCommerce product #53 was used. Admin page output included Add/Map, search, all four filters and product-bound nonce. The native Studio product-save handler persisted front print-zone X 31.1 → 31.2 → 31.1; readback confirmed area, categories, size profile and server pricing remained valid. Catalog lifecycle ran Add (new mapping starts disabled), Activate, Disable, Activate, Archive, Restore. HTTPS editor-config returned 404 when disabled/archived and 200 while active/restored. Fixture metadata hash, Woo product status/count, and existing order #56 status/item/design-reference digest matched before and after. Read-only server quote smoke: IDR 75,000 for synthetic A4/M, no cart or checkout.

The admin template and handlers were invoked under WP-CLI with WordPress Administrator identity because no browser automation connector was available. This is runtime application evidence, not Owner visual acceptance; Owner retains the separate browser review. A follow-up guard was installed after the lifecycle run: inactive products cannot be used to fork new designs from saved collections; read access to the saved collection remains enabled.

## Targeted regression

PHP syntax PASS for changed PHP files; `node --check` PASS for `studio-admin.js`; catalog lifecycle 14 assertions PASS; editor config 31 PASS; buyer render 20 PASS; admin menu/permissions/order hooks 71 PASS; product pricing 7 PASS; buyer gate 3 PASS; share clone 11 PASS; share/owner security 21 PASS; installer transaction/recovery 6 scenarios PASS; candidate-security suite 6 PASS; reproducible candidate builds byte-identical; ZIP inventory and member hashes PASS. The broader `native-feature-gap-flow.php` runner was attempted but PHP CLI lacks GD (`imagecreatetruecolor`); browser automation is not connected.

## Scope note

Variable WooCommerce products are explicitly rejected before Studio catalog add/configuration because the existing authoritative quote/cart adapters support simple products only. Variation mapping is therefore **not implemented** and remains an open requirement if the Owner requires variable products. No unsafe or client-priced variation path was introduced. No production, merge, checkout/order/payment mutation, Drive OAuth or Full Audit.
