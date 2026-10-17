# SMM Westgate Analytics Implementation Backlog

## Data contract and pilot

- Approve metric definitions, source scopes, campaign/week boundaries, timezone, quality states, retention, and revision policy.
- Validate GA4 query compatibility and Mailchimp reporting/filter behavior against authorized accounts.
- Identify a non-WordPress pilot and one real primary outcome; verify success events and tagged links.
- Inventory current Master source, reporting storage, cohort metadata, and exact grading implementation.
- Record source-report reconciliation and estimate operating costs before the production build.

## Shared service

- Implement tenants, projects, memberships, source connections, metric definitions, observations, snapshot revisions, brand briefs, experiments, and review records.
- Implement server-side provider authorization, encrypted credential storage, refresh/revoke/reconnect, and authorization on every resource.
- Add central scheduled jobs with pagination, quota controls, bounded retries, idempotency, and diagnostics. No customer-site cron dependency.
- Support GA4 and Mailchimp first, plus validated CSV import with mapping preview and provenance.
- Preserve query interval, fetched time, source definition, status, filtering, and revision with each report.

## Reporting and workflow

- Show Awareness, Acquisition, and Retention panels with weekly/campaign-to-date views.
- Keep confirmed email visits, provider clicks, and Direct/unattributed sessions separate.
- Include source-health and data-quality messages that distinguish failure from measured zero.
- Add Story/Promise/Belief/Trust brief, SWOT reflections, brand-alignment evidence, and one next experiment.
- Export data and visual results for the three progress reports and the final campaign report.

## Portal integration

- Build one shared connector package with .net/Pro configurations, not forked reporting engines.
- Scope student access to own projects, professor access to assigned cohorts, and Pro reviewer access to authorized projects.
- Verify integration does not disturb existing membership routing, profiles, progress, Stripe/PayPal behavior, or certificates.
- Version and test any approved assessment rules; retain published historical grades.
- Add a standalone customer portal before licensing beyond the two SMM sites.

## Optional AI coaching

- Limit inputs to authorized aggregates, approved methodology, and voluntarily supplied content examples.
- Ground every numerical claim in stored evidence and label causal explanations as hypotheses.
- Save prompt/rubric/model versions and evidence references; enforce output validation and usage caps.
- Keep all reports and exports functional when AI is disabled or unavailable.
- Do not automatically publish content, send email, assign official grades, or approve certificates.

## Migration and acceptance

- Export and preserve historical analytics, grades, rubrics, and Master dependencies before any retirement.
- Run two complete weekly pilot cycles and test a week with no publishing.
- Verify non-WordPress support, revoked authorization, API failure, duplicate job execution, zero denominators, timezone changes, and late data.
- Test cross-tenant and cross-professor denials and public-cache bypass.
- Make missing data ineligible for automated benchmark computation; define instructor fallbacks.
- Ship behind feature flags with a tested rollback path and backward-compatible service API.
- Document customer setup, license entitlements, export on expiry, support, restore tests, and ongoing provider maintenance.
