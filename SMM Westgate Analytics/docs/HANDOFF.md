# SMM Westgate Analytics Project Handoff

## Product direction

The owner wants to replace the UA-era Practicum analytics on both SMM sites, reduce deployment and plugin complexity, remove the dependency on the subject website's blog-posting routine, and evolve the methodology into a valuable licensed Westgate offering.

The working proposal is a central hosted service with a shared, thin WordPress connector for .net and Pro. Customer sites use their existing GA4 tagging or native platform integration. A later standalone portal allows Westgate customers to use the product without WordPress or either SMM course site.

## Confirmed requirements

1. Preserve the curriculum's approach to attracting and growing a brand, including weekly measurement, campaign-to-date history, brand alignment, SWOT reflection, and experiments.
2. Support website platforms beyond WordPress and activity types beyond blog posts.
3. Make weekly reporting independent of publishing activity and customer-site traffic.
4. Recognize that email is difficult to attribute and often appears as Direct. Keep measured email, provider evidence, and unattributed traffic distinguishable.
5. Serve both classroom students and Pro individuals. Company grouping on Pro is not required for the first release.
6. Keep official academic and completion decisions with the professor or administrator. Optional AI assists interpretation and next steps.
7. Plan for a licensed Westgate product rather than two separate bespoke plugin implementations.

## Proposed first release

GA4, Mailchimp, validated CSV fallback, a campaign-link builder, project setup, provider connection health, weekly and cumulative reports, one primary meaningful action per project, a brand brief, an experiment/reflection log, reviewer access, and exports. Add optional AI coaching after the data contract is validated.

Automatic ingestion of all social networks, marketing publishing, email sending, billing, team/company administration, and a replacement certificate system are outside this first analytics release. Social reach and in-platform activity can initially be attached as manual evidence or imported where suitable.

## Findings to preserve

The supplied student plugin uses UA reporting, stores cumulative analytics as WordPress posts, and schedules a weekly customer-site cron task. Its New Users cell is populated using new-visitor sessions. Emails Sent counts selected campaigns. Missing data can become zero, and campaign selection is capped at twelve IDs. A failed Mailchimp request can leave the pagination loop without progress. Its date-reset path can permanently delete analytics posts. Do not run or activate this reference code merely to inspect it.

The supplied archive contains embedded Google credentials. Repository copies redact those values and omit vendor examples/tests/key files. These are findings from the supplied version, not a complete security assessment of the live installation.

The SMM Master plugin also owns profiles and templates. Previously saved source confirms these responsibilities, but the complete current Master code and grading JavaScript have not been audited for this analytics project. Separate analytics behind compatibility interfaces before retiring any Master function.

## Measurement decisions

Maintain GA4-specific definitions and a new baseline. Keep UA snapshots in a read-only historical archive. Do not compare UA and GA4 as equivalent series or promise recovery of unavailable UA data.

Choose the reporting timezone and exact campaign/week boundaries. Default to Sunday-Saturday to match the course. Mark provisional data and preserve revisions. Query cumulative distinct users for the complete interval; do not sum weekly unique users. Treat audience size as a stock. Missing, failed, stale, and suppressed observations must not silently become zero.

Use tagged email links and source evidence without claiming perfect attribution. Provider clicks can contain bots, opens can reflect privacy prefetching, and website measurement can be absent or blocked. Preserve source/filter metadata and validate API behavior against the pilot account.

The paper's Brand Axis is a strategic framework, not a directly observed GA4 coordinate system. Human evidence supports environmental/competitive explanations and internal-push/external-pull hypotheses. Returning traffic does not prove loyalty. GA4 engagement is not equivalent to the theory's Engagement axis.

## Assessment decisions still needed

The course describes class means of 80 and ten points per standard deviation. Verify the actual production formula, sample/population deviation, weights, score bounds, exclusions, cohort selection, and letter thresholds before replacing it. Freeze cohort, source data, period, and rubric version when publishing assessments.

Preserve .net instructor-controlled benchmarking. Decide whether Pro keeps comparative scoring, moves to goals/baselines, or offers both. Do not silently change the existing score policy.

## Next technical pilot

1. Select the owner's authorized GA4 property and Mailchimp audience; confirm account/property ownership, timezone, collection health, and an instrumented primary action.
2. Use isolated development credentials and least-privilege provider authorization. Confirm Google OAuth production/verification requirements before committing a release date.
3. Reconcile dated aggregate results with the source interfaces. Document processing, attribution, identity, and filter differences.
4. Demonstrate collection on a non-WordPress website and through a full week with no content publishing.
5. Run at least two complete weekly cycles, including one forced provider failure and a reconnection test.
6. Define expected user/project volume, hosting responsibilities, retention, AI usage, support, and operating costs; then produce a build estimate.

## Existing SMM work to protect

The prior rollout moved both SMM sites to Stripe and established local certificate workflows with human approval and public certificate verification. Legacy PayPal renewals must remain supported until separately resolved. SmartNav was inactive and replacement membership routing was in place. Analytics work must preserve account access, membership/progress, professor scoping, checkout, certificate approval/download, and public verification. Verify the live state again before making changes; these are handoff notes, not a new deployment audit.

Private account pages require correct cache bypass on the actual hosting/CDN stack. Previous certificate work exposed hosting cache overrides, so test private analytics responses end to end rather than assume a no-store header is sufficient.

## Access to this work from other devices

The originating conversation is a local desktop Codex thread, not a normal cloud chat search result. Remote pairing was blocked by a 403 `codex_workspace_access_denied` response while the user was in a business/team workspace. Workspace remote-access policy or account entitlement requires administrator review. No account logs or session identifiers are included. This repository provides independent access to the project materials; it does not migrate the original chat into cloud history.
