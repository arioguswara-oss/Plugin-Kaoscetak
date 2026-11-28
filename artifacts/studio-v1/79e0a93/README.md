# Authorized staging candidate — source 79e0a93

Supersedes c0413fd for this Owner-authorized staging takeover only. Includes the runtime-discovered signed-in REST identity fix. This is not a Final Release claim.

`sanro-studio-staging-candidate.zip` is the application ZIP. `sanro-studio-staging-release.zip` is the separate, staging-only administrator transport with the same pinned candidate embedded. No shell/MCP is added. See transport-binding.json and SHA256SUMS. The utility verifies the existing tree, immutable source/archive/manifest, actual web SAPI, exact staging isolation, prior journal and retained backup, flags OFF, private roots and a shared lock before replacing any Studio bytes. Default action is read-only preflight. Owner's current instruction authorizes candidate installation and at most one synthetic order; it does not authorize production, merge or payment.

Local targeted lint/regressions and six recovery scenarios PASS. Fresh runtime gate/install/checkout/order/DTF/Drive acceptance pending. GC remains OFF. Baseline92.00%, no additional credit for this package.
