# Report Delivery queue progress — 0.6.28-preview

Base: 0.6.27-preview, published commit 895c7bcbbb27598bb7d40393afb887ff523b6366.
The owner's screenshot shows a manual V1Moto delivery still queued 15 minutes after
submission, with its original default preparation message. Live WP-Cron execution
could not be inspected because Chrome controls remain unavailable. This is evidence
of no visible worker progress, not proof of a provider or mail failure.

The Report Delivery account panel now advances already confirmed queued work through
an authenticated, nonce-protected company-scoped POST while the page is open and
visible. A Check delivery now button permits an explicit progress request. Polls
update status, stage, last activity, attempts and individual recipient results without
reloading the form. Dates are displayed MM/DD/YYYY. No send is newly queued by this
endpoint; accepted/failed/unknown jobs cannot be automatically resent. Existing jobs,
recipients, schedules, encrypted PDF payloads and lease protections are preserved.

Website, Facebook, Instagram and email collection advance in bounded stages. Retry
backoff and worker leases remain in force; a timed-out browser request does not
assume the worker failed. The dashboard can check again without creating a new job.
The pending report from 0.6.27 should be allowed to continue after this update; do
not resubmit it merely to trigger processing.

Unattended scheduled reports still require working WordPress/host cron. This patch
adds a dashboard fallback for confirmed work, not a replacement background scheduler.
A scheduled production send, actual inbox receipt and host cron timing still need UAT.
Staging recurring-send suppression and copied-site origin protection remain unchanged.

Validation: 41 PHP/JS test suites pass, including new authenticated progress checks,
Viewer denial, cross-company denial, GET rejection, safe status response fields,
date formatting, live result rendering and duplicate-send protection. No customer
emails were sent during these tests; mail/provider requests used doubles.
