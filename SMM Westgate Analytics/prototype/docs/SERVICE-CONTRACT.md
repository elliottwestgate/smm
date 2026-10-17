# Service contract v1 — Pro foundation

## Authentication and ownership

The trusted portal signs every request. Canonical bytes are UTF-8:

```
METHOD\nPATH\nTENANT\nPRINCIPAL\nUNIX_TIMESTAMP\nNONCE\nSHA256_BODY
```

Use HMAC-SHA256, hex encoding, exact request bytes, a 32-character lowercase hexadecimal random nonce, and the X-Westgate-Tenant/Principal/Timestamp/Nonce/Signature headers. Requests expire after a 120-second clock window; accepted nonces are retained for 240 seconds. TLS is mandatory between portal and service. A portal key is a privileged credential able to assert principals in its own tenant; never put it in browser code. Portal administrators are trusted. Server clocks must be synchronized.

The principal is the stable WordPress user ID within the tenant. No endpoint accepts a project owner override. Queries always constrain both tenant and principal. The .net adapter must use a separate tenant and later explicitly authorized cohort/reviewer relations; it must not reuse a Pro key.

## Routes

| Route | Behavior |
| --- | --- |
| GET /v1/project | Own campaign and revision, or null/revision 0 |
| PUT /v1/project | Create/update own campaign using expected revision; conflict returns 409 |
| GET /v1/report | Own metric-availability panels; null weekly and campaign-to-date results until ingestion exists |
| GET /v1/reflections | Up to 100 latest own reflection entries |
| POST /v1/reflections | Append own Sunday-start week reflection overlapping campaign dates |

Project fields: name, HTTPS website, timezone (IANA name), start_date, end_date, primary_action, story, promise, belief, trust, revision. Sunday–Saturday is the Pro course default. Partial opening/closing weeks, timezone changes after observations, finalized periods and revisions will be resolved in the observation layer before ingestion.

Reflection fields: week_start, evidence, alignment, swot, experiment, success_measure. Entries are learner-provided statements, not independently verified analytics or AI outputs.

## Next data-layer requirements

Each observation must preserve source, metric-definition version, exact interval/timezone, dimensions and filters, fetched time, quality status, revision, and provider query metadata. Separate stocks, additive flows, distinct counts and rates. Query cumulative distinct users for the complete interval; never sum weekly unique users. Missing, suppressed, failed and stale observations remain distinguishable from measured zero. Never combine Mailchimp clicks with GA4 sessions as if they were the same measure.

The foundation intentionally has no endpoint for arbitrary metric ingestion. Implement adapters with validation and permission checks before storing observations. Use provider-backed weekly and cumulative queries, not an AI calculation layer. GA4 engagement does not measure the theory's strategic Engagement axis. Email opens and clicks require bot/privacy metadata. Direct is unattributed rather than inferred email. Returning visits are not proof of customer loyalty.

Official Pro grades remain pending the policy decision recorded in HANDOFF.md. Student/professor controls will follow after Pro; no automatic grades or certificate approval are authorized by this API.

## Required email adapters

Both Constant Contact and Mailchimp are first-release requirements. Aladdin uses Constant Contact. Project setup stores `email_provider` as `constant_contact` or `mailchimp`; the foundation validates the selection but does not authorize or fetch from either provider yet.

Adapters must expose connection health, campaign/activity IDs, dated sending and engagement observations, audience snapshots, pagination, revision metadata and source/filter definitions. Distinguish campaigns sent during a week from engagement occurring during that week; provider lifetime campaign totals are not weekly activity. Do not sum per-campaign unique recipients as a campaign-wide distinct audience. Unsupported bot filters and unavailable historical audience counts must be explicit.

Constant Contact source references checked 17 October 2026:
- https://v3.developer.constantcontact.com/api_guide/email-reporting/email-reporting-overview
- https://v3.developer.constantcontact.com/api_guide/email-reporting/email-reporting-campaign-stats
- https://v3.developer.constantcontact.com/api_guide/email-reporting/email-reporting-campaign-activities-stats

Constant Contact reporting distinguishes campaign and campaign-activity statistics. Validate list membership scope, engagement dates, filtering and historical availability against the pilot account before finalizing metric equivalence. Treat Mailchimp audience/list and Constant Contact list/segment scopes as provider-specific selections.
