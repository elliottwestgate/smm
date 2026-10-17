# SMM Westgate Analytics

Discovery and proposed replacement for the legacy Social Media Magnet Practicum analytics used by socialmediamagnet.net and socialmediamagnetpro.com. The product is intended to become a platform-independent, licensed Westgate offering.

## Current status

The initial scope is complete as of 17 October 2026. Replacement software has not been built or deployed. The legacy code here is reference material, not a supported installation package.

The proposed design uses one hosted analytics service and a small shared WordPress connector for the two SMM portals. Customer websites connect through GA4 and their email provider. Reporting does not require an SMM plugin, WordPress, blog posts, or customer-site cron jobs.

## Start here

- [Replacement scope PDF](docs/Westgate-Practicum-Analytics-Replacement-Scope.pdf): nine-page scope covering methodology, metrics, deployment, AI, migration, and acceptance gates.
- [Project handoff](docs/HANDOFF.md): confirmed requirements, decisions still needed, and the next technical pilot.
- [Implementation backlog](docs/BACKLOG.md): ordered work packages and acceptance criteria.
- [Evidence and sources](research/SOURCES.md): courseware, source audit, browser observations, and vendor references.
- [Supplied white paper](reference/SMM%20White%20Paper%20AMTP%20Conference%20Submission%20for%202020.pdf): the theoretical basis for the curriculum.
- [Sanitized legacy source](legacy/student-plugin/westgate-analytics/): readable customer-site plugin source.
- [Sanitized reference archive](reference/westgate-analytics-3.0.1-sanitized-reference.zip): retained runtime/vendor material for historical inspection.
- [Sanitization manifest](reference/sanitization-manifest.json): credential redactions and omitted vendor test/example files.

## Core requirements

Keep Brand Awareness, Acquisition, and Retention reporting and the Story, Promise, Belief, and Trust framework. Support both individual weekly periods and campaign-to-date results. Retain professor-controlled classroom benchmarking on .net; decide the updated Pro score model explicitly before publishing new scores.

Treat email attribution as incomplete. Show confirmed tagged email visits, provider click evidence, and Direct/unattributed traffic separately. Do not assume all Direct visits came from email or that email clicks equal website sessions.

Use AI for optional, evidence-citing analysis and coaching. Collection, calculations, access, and published assessment rules remain deterministic. Official grades and completion/certificate approvals remain human controlled.

## Reference code and credentials

The supplied ZIP identifies its plugin as version 3.0.1, despite the original archive filename ending in 3.0. It is the customer/student website plugin, not the full SMM Master plugin. The Master plugin also supplies profiles and course templates; it cannot be removed wholesale during migration.

Embedded Google credential values have been replaced with placeholders. Vendor examples, tests, and key files are omitted from the reference archive. The owner's original local ZIP remains unchanged and is not included here. The owner should review and revoke or rotate any still-valid legacy Google credentials before reuse. No live credentials, account logs, browser sessions, student roster, or full chat transcript belong in this folder.

Third-party notices remain with their respective source files and inside the reference archive. Inclusion here does not establish a license for the proposed new Westgate product. Commercial terms and ownership review remain a separate decision.

## Rebuild the scope document

Use Python with the dependencies in `tools/requirements.txt`, then run `python tools/build_scope.py` from this folder. The script writes `docs/Westgate-Practicum-Analytics-Replacement-Scope.pdf`. Rendering and visual review are required before distributing a revised PDF.

## Next milestone

Select an authorized GA4 property and Mailchimp audience, reconcile the report values with their source interfaces, and demonstrate scheduled reporting on a non-WordPress website for at least two complete weekly cycles. Estimate development and operating costs after that integration spike.
