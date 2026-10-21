# Pro Analytics curriculum reconciliation

Published to socialmediamagnetpro.com on October 20, 2026. No .net edits.

## Scope

- 43 measurement, foundation, publishing and business action-plan pages updated.
- 7 additional legacy setup/entry pages updated; page 2800 remains a draft.
- Pro Analytics Guide created at /pro-analytics-guide/ (page 8889), linked from Account. General instructional help is unprotected; existing lesson membership restrictions remain unchanged.
- Removed obsolete weekly plugin/token setup, Universal Analytics goal-screen instructions and grading requirements from replacement business workflows.
- Removed outdated instructional screenshots from replacement measurement/setup pages. Retained the SMM foundation diagram and clearly marked historical lectures/case context. A new full screenshot walkthrough is not included in this pass.
- Added present-day provider connections, comparisons, reporting limitations, deliberate Review requests and printing guidance. Described unsupported deep GA4 analyses separately instead of implying those features exist in Pro Analytics.
- Removed the old security-question/password submission request and form embed from page 5921. Historical form records and account credentials were not changed.

## Terminology

Pro Analytics names the tool; analytics names the general practice. Brand Axis describes the conceptual Loyalty/Engagement model and environmental/competitive context. Session change and returning-to-new-user balance are directional supporting signals, not direct measurements of those concepts. Message Matrix describes audiences, content buckets and channels, organizing the response in the publishing calendar.

Legacy page IDs 2487 and 2489 also invoked obsolete templates after their updated content, causing a critical error. Added a scoped 302 redirect in WGPP_Landing to /pro-analytics/, where current access is enforced. Both redirects were verified live.

## Product deployment

Updated portal.php, company.php, reviews.php, assets/analytics.js and assets/reviews.js through the WordPress plugin editor after checking each original against repository HEAD. Asset version: 0.6.18-preview. WordPress confirmed file saves. Live Account links, Report labels/date formatting and saved Review labels verified. Existing saved narrative text is historical; no new Review was generated and no allowance was consumed for this check.

## Validation

Both changed JavaScript files passed node --check. git diff --check passed. PHP CLI is unavailable; WordPress accepted the PHP file edits and the live pages rendered successfully. Published-page verification results are recorded separately. The publishing-edition DOCX was not edited.

## Next discussion

Scheduled Report delivery (daily, weekly or monthly) is proposed, not implemented. Keep Report delivery separate from new Pro Analytics Review generation and its allowance.
