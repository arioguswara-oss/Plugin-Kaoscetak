# OWNER DIRECTIVE — Google Drive V1 scope revision

Status: DECIDED, supersedes earlier mandatory live Drive release gates.

Required: SANRO Studio → Pengaturan → Google Drive; OAuth Client ID/Secret; Redirect URI; Connect/Disconnect; production folder selection; connection status; Test Connection; automatic export enable/disable; secure credential storage; configuration validation and error handling. Verify implementation and relevant tests without actual credentials. Application must operate normally with Drive unconfigured or disabled.

Actual Google Cloud configuration, Owner authentication, OAuth consent, actual upload and readback are DEFERRED BY OWNER and are not mandatory release gates. Configuration: NOT CONFIGURED until Owner configures it after release. Feature: IMPLEMENTED / VERIFIED only according to evidence. Do not request credentials now.

Preserve prior evidence. Update acceptance criteria, release gates and ledger; recalculate progress only from verified implementation under revised scope. Complete remaining relevant acceptance, build Final Release and install on authorized staging. No production deployment or merge. Development First; Feature Completion First; Approval by Exception; GitHub First.

At intake PR #52 HEAD 2db5a9a1a8c5126e6dbfd423497c768b7b985609 reports installed candidate 8846097f359bc56dec1c5638a5b04c9a31ab884c and baseline 94%. Native OAuth implementation reported; exact required controls and tests still need evidence review. Normal buyer Place-order acceptance remains open. This directive alone does not award progress or certify release.
