# Pro Analytics Report Delivery — 0.6.27-preview

Base: 0.6.26-preview, master commit 41b7032538dbf912b4bd7285252c71e806ffbd4c.
The owner supplied a WordPress Plugins screenshot confirming active 0.6.26-preview.
Deployment source files and separate navigation overlays could not be compared live
because Chrome control is unavailable. Install and verify on staging first.

## Customer flow

Open a company → Account → Report Delivery. Owners may enable up to three independent
analytics schedules: weekly, monthly, every four weeks. Choose recipient addresses,
first delivery date, Central Time and comparison (None, prior period, previous year).
Saving enables or replaces the selected frequency. Pause stops future schedule runs;
already queued emails remain visible and continue. Schedules are off by default.

Weekly reports cover the preceding Monday–Sunday even if sent later in the week.
Monthly reports cover the previous calendar month; delivery days 1–28 avoid invalid
dates. Four-week reports cover the previous 28 days, anchored to the first send date.
Calendar arithmetic preserves the local delivery time through daylight-saving changes.
An outage skips missed schedule runs rather than sending a backlog.

Owners and Analysts may email any selected date range or an existing saved Review to
one to ten addresses with an optional note. Report and Review export bars link to the
private delivery form, with applied dates or saved Review preselected. Recipients are
confirmed before queueing. Each recipient gets a separate message, including a sign-in link back to the company
dashboard. Dashboard access still requires a registered company team login; the PDF
attachment itself does not require sign-in. Viewers cannot
send or manage schedules. Saved Reviews keep their original evidence and do not
consume another Review allowance. Scheduling creates analytics reports, not new Reviews.

The account page remains on Report Delivery after saving. Delivery history lists
queued/preparing, accepted, failed, or unknown outcomes and individual recipients.
“Accepted” means the mail service accepted it; inbox delivery and bounces are not tracked.
Interrupted sends are not automatically retried, to avoid duplicate delivery.

## Report and privacy behavior

PDFs are compact, selectable text with Brand Axis first, Awareness, Acquisition,
Retention, website totals, top sources/posts, audience and measurement notes. Saved
Reviews include their summary, content focus, actions, framework and supporting data.
The attachment uses a native server-side layout rather than a screenshot of the
interactive dashboard; interactive charts/timelines are not embedded. A saved company
logo is included when a supported local PNG/JPEG and GD are available. Standard PDF
fonts use WinAnsi; unsupported Unicode glyphs are transliterated. No new dependencies.

Reporting waits for queued connected channels and both selected website periods.
Disconnected/failed optional channels are explicitly marked unavailable. No estimates
or fabricated reports are mailed. Exact-range imports work only when those dates exist.
Preparing data retries for up to 120 scheduler passes/24 hours, then fails without email.
No AI calls, contact lists or provider credentials enter delivery PDFs.

Pending PDFs are encrypted in private company metadata, scoped to company and job.
Attachments exist in memory for PHPMailer; no public uploads, URLs or temp PDFs.
Terminal records remove the encrypted PDF. History retains the last 50 terminal records.
Requests check WordPress nonce, current company membership, role and entitlement;
workers recheck access at execution and before each recipient. Expired/revoked access
stops sends. Manual submit request IDs prevent a double-click from queueing duplicates.
Twenty company send requests per hour and ten pending manual jobs limit accidental bursts.

## Hosting and acceptance checks

Uses existing WordPress mail and WP-Cron. SMTP must support WordPress's PHPMailer path;
a pre_wp_mail transport that bypasses PHPMailer is refused to prevent a PDF-less send.
Sender display is Social Media Magnet Pro, with the existing invitations sender address.
No SMTP keys or DNS changes are included. Low-traffic sites may run late; use host-driven
WP-Cron for reliable delivery times. Schedule origin binding and WordPress environment
guards prevent recurring sends from a staging clone. Manual staging sends are explicit.
Reactivating the plugin restores company wake-up events. Pausing/deactivation does not
remove existing report data or email history.

Before production installation:

1. Update the existing SMM Pro Analytics plugin on staging; confirm 0.6.27-preview.
2. Owner: Account → Report Delivery; send one report to a controlled test address.
3. Verify a received PDF opens, has the expected company/dates, selectable text and
   the correct comparison. Check saved Review delivery without increasing usage.
4. Analyst: verify manual sending; Viewer: verify no sending/schedule controls.
5. Confirm saving returns to Report Delivery and other companies stay separate.
6. Confirm staging schedules do not send. On production, explicitly save a schedule
   and verify the next Central Time run, host cron, sender/attachment and recipient results.

Automated verification: all 40 PHP/JS suites pass, including date boundaries, DST,
leap years, private PDF generation, PHPMailer attachment cleanup, revoked access,
company separation, idempotency, schedule-owner permission, interrupted-send protection
and staging/origin guards. All plugin PHP/JS syntax checks pass. Two-page sample PDF
rendered with Poppler and visually inspected; text extraction and PDF title verified.
These use WordPress/provider/mail doubles. Live mail delivery and WordPress cron UAT
remain required; no customer emails or schedules were created during development.
