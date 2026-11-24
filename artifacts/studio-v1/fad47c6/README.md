# SANRO Studio staging buyer counter fix candidate

- Source: `fad47c6f508258d594f92daca79c337d6911b404`
- ZIP SHA256: `b83c1b03d18e0633d502aff09d9b1d40f01eb216a22850a7c990fc40c78a5723`
- Manifest SHA256: `8664f584c4b746120c98ed50b337fc9616508f3b390eca3fa2a8429a370f8309`
- Plugin digest: `6d65fde1ceda80b1551da51817ccef14f0d618f1d5dffdcfcb412a8c129cc2bb`
- Existing installed tree digest: `8d560cdb95611da98dc4a739737dff30ab9ac35f4ec6866defb8093808fd37db`
- Target: `https://saddlebrown-peafowl-616656.hostingersite.com`
- Status: exact Owner-approved candidate installed on the approved staging site; post-install runtime and buyer functional QA PASS. See `installation-execution.json`.
- Fix: normalize only bounded nonnegative decimal transient counters returned as strings; malformed/exhausted values still fail closed and the ten-per-hour limit is unchanged.
- Scope excludes production, merge, order/payment and feature-flag changes. DTF, Drive, storage GC and checkout writer remain OFF; checkout/order/payment flows were not exercised.
- `approval-request.json` records Owner authorization and exact installed hashes. Image-backed front/back masters, private previews, multi-size quote, and desktop/mobile browser verification PASS. HTTP 422 did not recur; the historical response body was not retained, so its original root cause is not asserted. Four screenshots remain private under Hermes `owner-private/`.
