# Message Matrix Pro development

- Use this directory as the canonical project source. Preserve the existing plugin slug `message-matrix-pro` and its data tables.
- Commit each tested development revision. Publish the commit to the authorized repository before deploying or distributing an installable build.
- Development and UAT target staging only. Do not change production without a separate release instruction.
- Reconcile installed differences before deploying. Do not overwrite the maintained Pro Analytics project or import an older Matrix installer over newer work.
- No customer data, credentials, uploads, private source documents or environment-specific authentication material belong in this repository.
- Source visibility must be authorized. The user explicitly authorized publishing the recovered Matrix source and admin revision to the public elliottwestgate/smm repository on 10/24/2026. Customer records, private uploads and credentials remain excluded.
- Run the PHP domain, access, source and admin tests plus the JavaScript interface checks for the relevant changes. These use doubles; distinguish their results from actual WordPress UAT.
- Preserve per-company authorization, optimistic revision checks, owner protection, seat limits, existing data and source-document encryption.
- Use Vertical, Bucket, Content Focus and Publishing Calendar Pro consistently. Brand and Matrix are capitalized in user-facing text.
- Retain historical field names in stored documents unless an explicit tested migration is provided.
- Company logos belong on Brand pages where the company has a saved logo; fall back to the company name.
- Automatic generation, membership provisioning, Westgate account handshake and direct publishing must not be labeled connected until implemented and tested.
