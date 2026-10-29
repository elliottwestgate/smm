# Pro Analytics 0.6.31-preview

Reported symptom: a delivery queued at 8:08 AM still showed Instagram preparation at 9:41 AM Central Time. The screenshot establishes elapsed time and stage, not live server state.

Source defect: every preparation pass starts again with website and Facebook before reaching Instagram and email. Instagram caches containing unavailable metrics expire after five minutes. Slow or interrupted later stages can therefore cause earlier sections to be fetched repeatedly. This is a supported failure path, not a confirmed diagnosis of that particular live queue.

Change: save completed website and social results in a company-and-job-scoped encrypted preparation envelope; resume at the first unfinished section. Remove the envelope on terminal completion or failure. Preserve exact periods, company authorization and existing no-resend safeguards. No credentials are in the envelope. Retry spacing reduced from 60 to 15 seconds. Provider step/total is shown when supplied. Stop waiting after 30 minutes or 120 attempts; do not silently email a partial report. Existing overdue queued work can complete if its next pass succeeds, otherwise it fails clearly and may be submitted again once.

Regression: simulate completed Facebook with unavailable metric, unfinished Instagram and email, delete website cache between passes, verify previous channels never restart, a single email sends and private preparation is removed. Verify scoped encryption, retry interval and timeout with no mail. Existing authorization, scheduled delivery, idempotency and interrupted-send tests remain applicable.

Host WP-Cron and live provider timing remain unverified. This update prevents a source-level repetition path and bounds waiting; it does not install a reliable system scheduler or redesign the emailed PDF. Test on staging and install through the normal plugin update flow.
