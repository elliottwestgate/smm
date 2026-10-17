# SMM Westgate Analytics Evidence and Sources

## Supplied materials

- `reference/SMM White Paper AMTP Conference Submission for 2020.pdf`: 32-page paper titled The Brand Axis: Strategic Impacts of Loyalty and Engagement within the Social Media Magnet Theory. Figure 5 and pp. 11-15 explain the axes; quadrant discussions follow; pp. 29-31 describe the remedy and curriculum intent.
- `research/white-paper-extracted.txt`: convenience text extraction. Refer to the PDF for diagrams and typography; extracted encoding is imperfect.
- `legacy/student-plugin/westgate-analytics/`: sanitized first-party source from the supplied archive. Main header reports 3.0.1. The ZIP is the subject/student website component, distinct from the Master plugin on SMM.
- `reference/westgate-analytics-3.0.1-sanitized-reference.zip`: reference archive with runtime libraries and notices retained, credentials redacted, and vendor examples/tests/key files omitted.

## Selected courseware

The text files in `courseware/` preserve the selected authenticated lesson/exercise text reviewed on 17 October 2026. They do not contain a student roster or private report values.

- [.net Progress Reports lesson](https://socialmediamagnet.net/?page_id=1294) -> `course-net-progress.txt`.
- [Pro Progress Reports lesson](https://socialmediamagnetpro.com/?page_id=1294) -> `course-pro-progress.txt`.
- [.net Progress Reports exercise](https://socialmediamagnet.net/?page_id=1412) -> `course-net-exercise.txt`.
- [.net Email Analysis exercise](https://socialmediamagnet.net/?page_id=1819) -> `course-net-email-exercise.txt`.
- [.net Closing the Loop exercise](https://socialmediamagnet.net/?page_id=1893) -> `course-net-closing-loop.txt`.
- [.net Final Report](https://socialmediamagnet.net/?page_id=1980) -> `course-net-final-report.txt`.

The scope reflects these selected pages, not a complete curriculum review. In particular, existing references to Direct as email, UA destination goals, open-based evaluation, WordPress-specific setup, and blog-only activity need coordinated revision after the measurement contract is approved.

## Browser observations

On 17 October 2026, authenticated read-only inspection found 54 published weekly records at Mary-Kathryn, the latest listed as Week 67 dated 31 October 2021. Its report permalink returned 404, and its Website Analytics dashboard displayed a critical error. The .net Student Analytics portal rendered historical data and grades. Pro Member Analytics displayed a critical error. No root cause was established and fresh collection was not verified. The audit did not run cron manually or modify those sites.

Previously saved Master entrypoint and professor analytics template were inspected locally for architecture context. They show profile/template dependencies and university/professor/section scoping. Full current Master source and grading JavaScript still require inspection before implementation. Those unrelated operational source copies are not published here.

## Vendor documentation

Checked 17 October 2026. These sources explain API semantics and constraints, not pilot integration success.

- [GA4 API dimensions and metrics](https://developers.google.com/analytics/devguides/reporting/data/v1/api-schema)
- [GA4 runReport](https://developers.google.com/analytics/devguides/reporting/data/v1/rest/v1beta/properties/runReport)
- [GA4 response metadata](https://developers.google.com/analytics/devguides/reporting/data/v1/rest/v1beta/ResponseMetaData)
- [GA4 quotas](https://developers.google.com/analytics/devguides/reporting/data/v1/quotas)
- [GA4 user metrics](https://support.google.com/analytics/answer/12253918)
- [Default channel groups](https://support.google.com/analytics/answer/9756891)
- [GA4 data freshness](https://support.google.com/analytics/answer/11198161)
- [Google server-side OAuth](https://developers.google.com/identity/protocols/oauth2/web-server)
- [Google API scopes](https://developers.google.com/identity/protocols/oauth2/scopes)
- [Sensitive-scope verification](https://developers.google.com/identity/protocols/oauth2/production-readiness/sensitive-scope-verification)
- [GA4 Measurement Protocol](https://developers.google.com/analytics/devguides/collection/protocol/ga4)
- [UA migration and historical data](https://support.google.com/analytics/answer/14196937)
- [Mailchimp campaign reports](https://mailchimp.com/developer/marketing/api/reports/get)
- [Mailchimp Apple privacy FAQ](https://mailchimp.com/help/apple-privacy-faq/)
- [Mailchimp bot filtering](https://mailchimp.com/help/about-bot-activity/)
- [OpenAI remote connection guidance](https://learn.chatgpt.com/docs/remote-connections), for the separate workspace-access handoff note.
