# ChatGPT Work handoff — lifecycle fix candidate

Review and install candidate source 4772561ded172bd1d62bb88867b92646f878091e only on the already authorized staging site using the existing official installer and mandatory runtime gate. Do not use a new staging site, bypass the acceptance transport, submit a real payment, or merge.

After installation, verify the active version, installed digest, operator links for locked order items, PDF guide download, private master authorization and ordinary WooCommerce checkout. Preserve the staging 403 guard: the legacy QA transport denial is not plugin acceptance evidence. Store API Studio checkout is intentionally fail-closed until a signed authorization and snapshot-lock flow exists; ordinary Store API products remain unaffected.

Candidate ZIP SHA-256: f34140e754fce2c8915afcead9fbc61be3466b0f026f96f709e0124c9bd6d46a.
Current installed source remains 7aa6eb23…; this candidate has not been installed. Owner visual acceptance remains separate.
