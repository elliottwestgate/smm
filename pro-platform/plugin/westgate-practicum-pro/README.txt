TRANSPARENT LOGOS AND DELIVERY CONTROLS - 0.6.34-preview

Company logos retain alpha transparency in the header and Brand Axis hub.
Opaque edge-connected white backgrounds are removed during PDF rendering,
without changing stored uploads. A directional arrow replaces the orange square.
Delivery history retains five finished records plus all pending work. Owners
can delete finished records, cancel pending deliveries and cancel recurring
schedules (then create a new schedule to reschedule). Already sending/accepted
emails cannot be recalled. Short-lived request hashes preserve duplicate and
rate-limit protections after history is removed.

COMPANY LOGO IN BRAND HUB - 0.6.33-preview

The emailed Analytics Report displays the company logo inside the Brand Axis
hub when a supported logo is available. The existing Brand label remains the
fallback. The header logo and all reporting data are unchanged.

EMAIL REPORT REDESIGN - 0.6.32-preview

Analytics Report attachments now use a four-page vector layout: Brand Axis and
focus cards, website performance, compact social post rankings, returning
audience/email response/profile. Embedded licensed fonts preserve typography.
Charts and text remain native PDF elements, not screenshots. Social/email
website sessions appear under Acquisition. Signed net-follower changes use
absolute differences; email rate changes use percentage points.

This changes new Analytics Report exports. Existing received attachments cannot
change. Saved Review content and allowance are unchanged. The Elliott sender
and preparation fixes remain included.

REPORT PREPARATION FIX - 0.6.31-preview

Completed website and social sections are encrypted and saved per delivery,
preventing provider cache expiry from restarting earlier sections. Preparation
steps retry after 15 seconds, show provider step counts where available and
stop clearly after 30 minutes rather than remaining queued for hours.
No incomplete timed-out report is emailed. The Elliott sender is retained.

EMAIL SENDER UPDATE - 0.6.30-preview

Pro Analytics invitations, account-verification messages and report emails use
Social Media Magnet Pro <elliott@westgatemarketing.net> as From and Reply-To.
Existing queued reports and schedules use this address when they send. Sender
filters remain scoped to these product messages; unrelated WordPress mail is unchanged.

EMAIL SENDER FIX - 0.6.29-preview

Report emails and team invitations now use Social Media Magnet Pro
<support@socialmediamagnet.net> as From and Reply-To. Existing queues and schedules
use this sender on their next send. The PDF layout revision remains pending.

DELIVERY QUEUE PROGRESS FIX - 0.6.28-preview

Adds authenticated dashboard queue advancement when WP-Cron is delayed, live delivery
history, a Check delivery now button, preparation stage and last-activity feedback.
Existing queued emails and saved schedules are retained. No need to resubmit a send.
Unattended automatic schedules still require a functioning WordPress/host scheduler.

REPORT DELIVERY - 0.6.27-preview

Adds Account > Report Delivery; manual analytics PDF and saved Pro Analytics Review
emailing, plus independent weekly, calendar-month and every-four-week analytics
schedules. No public sharing URL or public PDF file is created. Existing provider
connections, billing, team membership, reporting and saved Reviews are retained.

Release/testing details: see pro-platform/docs/report-delivery-0.6.27.md in source.

Westgate Practicum Pro 0.6.6-preview

PRIVATE PILOT UPDATE
Runs inside WordPress/PHP. No separate analytics server is required.
Includes the approved 0.2.3 dashboard/print layout plus native Google OAuth,
property verification, queued GA4 collection, review history and quota storage.
Live pilot Google, Facebook, Instagram and email reporting are connected.
Pro Analytics Review is configured; public launch still requires billing lifecycle
validation and Meta customer-access review. Reviews open with the current Brand
Matrix and dimensional Message Matrix content direction. Paid recommendations
are excluded. Post views are visibility; engagement actions are not yet supplied.

INSTALL
Upload this timestamped ZIP through WordPress Plugins and replace the existing
westgate-practicum-pro plugin. Keep 0.2.3 for rollback. Existing company workspace,
sample/imported data and personal legacy records are retained.

GOOGLE SETUP
1. Tools > Practicum Pro Preview > Company connections.
2. Site administrator: open Westgate application settings.
3. Copy the EXACT redirect URI displayed there to the Google Cloud web OAuth
   client. Use Westgate's application credentials, not Aladdin's.
4. Enter the client ID and client secret in WordPress and save.
5. Company owner: click Connect Google Analytics and authorize read-only access.
6. Enter the numeric GA4 property ID (not the G- measurement ID).
7. Verify form and phone event names; defaults are form_submission and phone_call.
   Names must match actual recorded events; this does not create events in GA4.
8. Click Verify and save property. Google supplies the reporting timezone.
9. Apply the desired reporting dates. Collection queues in WordPress.
   The dashboard checks progress for up to four minutes; reapply later if needed.

The Google Analytics Data API must be enabled in Westgate's Cloud project.
Google consent/audience/test-user configuration may also be needed.
No Google Analytics Admin API is required: property ID is entered and verified.

HOST REQUIREMENTS
PHP 8+, OpenSSL AES-256-GCM, outbound HTTPS requests to Google, WordPress loopback/
WP-Cron, and MySQL advisory locks GET_LOCK/RELEASE_LOCK.
Host support and live Google consent have not been verified.
Each reporting collection step makes one bounded HTTP request.
A stalled job can be queued again after 15 minutes; failures back off five minutes.
Up to 24 range/config cache entries are retained per company.
Fresh completed reports are reused for six hours. Changing Google authorization,
property or goal definitions invalidates the cache identity.
Pending/failed reports are not presented as completed data.
Once GA4 is configured, unavailable live periods do not silently use sample data.

SECURITY
Application secret and company tokens are encrypted in WordPress storage.
They are never sent in dashboard JSON or printed reports.
Changing WordPress authentication salts requires resetting credentials/reconnecting.
Back up both site configuration and database securely.
Only company owners manage connections. Other company members share reports.

REVIEW STATUS
Saved history and the native allowance/queue implementation are present.
AI execution remains unavailable until an evaluator is configured.
Monthly allowance windows must be mapped to the company's membership billing.
Production interrupted-job recovery and full saved-review PDF export remain pending.
Native WordPress/API-double checks do not substitute for live host testing.

DATA AND PRINTING
Exact selected dates are required; new data normally ends yesterday.
Google may revise recent results. Privacy/sampling/row-limit notes are retained.
Audience sections may be unavailable without suppressing the core report.
Sources are capped at 500 rows; affected reports are flagged.
Goal event totals are unavailable if the event report is privacy/row limited.
Print / Save Snapshot uses browser printing; its earlier iPad layout was verified.
PDF export titles and ZIP deliverables include YYYY-MM-DD_HH-MM-SS_CT.
The internal plugin folder stays stable so uploads replace the existing plugin.

STUDENT AND SUBSCRIPTION
This is the Pro company experience. No student team access is introduced.
WishList/curriculum/payments/certificates/legacy analytics are unchanged.
Membership enforcement is still development preview provisioning.
Do not sell subscriptions from this preview.

ROLLBACK
Deactivate and reinstall 0.2.3 if needed. All stored records remain.
Do not deactivate legacy plugins or replace legacy member analytics pages yet.


== 0.4.0-preview: Constant Contact campaign reporting pilot ==
* Native server-side Authorization Code flow, encrypted application/company credentials,
  owner-only connection management and refresh-token rotation under the company lock.
* Separate email panel loads even while GA4 collection is pending. Uses the same
  date selectors and prior-period/previous-year comparison. Cached campaign history
  refreshes after six hours. Each background job page uses a bounded HTTP request;
  failures never publish partial totals. Pilot cap: 10,000 campaigns / 40 pages.
* Sends, delivered (sends minus bounces), per-campaign unique clicks, unsubscribes,
  open/click rate bars and a campaign-ID results table. Rates use sends.
* IMPORTANT: selects campaigns by LAST SENT date and reports their lifetime counts
  at collection, not activity strictly within the selected dates. Re-sends can move
  cohorts. Cross-campaign sums are not distinct people. No email-to-goal attribution
  is asserted. Bot/privacy effects and unverified bot filtering are disclosed.
* Campaign reporting requests only campaign_data and offline_access. The provider
  campaign_data scope ALSO allows writes. This connector implements GET report calls
  only; it has no campaign publishing, editing, sending, deletion or budget actions.
* Contact records, list size/growth, demographics and Mailchimp are not implemented.
* AI review payload remains the website analytics snapshot; email evidence is not
  silently included in evaluations. Live email reconciliation is still required.

Setup:
1. Replace the existing westgate-practicum-pro folder using this ZIP; retain existing
   Google application credentials, company grants, team assignments and snapshots.
2. In Tools > Practicum Pro Preview open Constant Contact connection > Westgate
   Constant Contact application settings. Copy the displayed callback exactly.
3. Create a separate Constant Contact developer application with Authorization Code
   flow and that redirect URI. For this Aladdin pilot a private application suffices;
   a multi-customer offering requires the appropriate provider application setup.
   Do not change the existing Aladdin Weekly Reporting application.
4. Save API key (client ID) and client secret in WordPress, then the owner connects
   Aladdin and approves the provider campaign/offline permissions. Keep secrets out
   of chat, exports and screenshots. No real credentials are included in this ZIP.
5. Apply dates. Leave the site active for WordPress scheduling. Compare the campaign
   cohort counts with the provider, accounting for date/timezone, last-send semantics,
   lifetime responses, re-sends, bot handling and rate rounding before sign-off.
6. Disconnect removes local grants/cache; revoke the app in Constant Contact as well.

Exact callback normally:
https://socialmediamagnetpro.com/wp-admin/admin-post.php?action=wgpp_cc_callback
Use the displayed value if WordPress computes a different site URL.

Developer references (checked 2026-10-17):
https://developer.constantcontact.com/api_guide/server_flow.html
https://developer.constantcontact.com/api_guide/scopes.html
https://developer.constantcontact.com/api_guide/email-reporting/email-reporting-campaigns-summary


== 0.4.0-preview ==
The authenticated company dashboard advances one email report page per poll,
in addition to scheduled collection. Company locks prevent overlapping worker
claims. This removes the exclusive dependency on the host cron runner. No new
permission or reauthorization is required. Existing failed jobs retain five-minute
backoff. Interrupted processing retains the existing fifteen-minute recovery window.
Validated live on Aladdin, 2026-10-17: four campaigns last sent Sep 17-Oct 16,
19,972 sends / 19,795 delivered / 8,766 opens / 170 clicks / 177 bounces /
80 unsubscribes match Constant Contact's campaign rows and headline totals.
Prior cohort Aug 18-Sep 16. Tests: 28 native email checks plus existing suites.


== 0.4.0-preview: Front-side company workspace ==
* Pro Analytics page uses [westgate_practicum_pro]. Published on Pro at
  /pro-analytics/ with an Open Pro Analytics card on the existing /dashboard/.
* This page's WishList shell is unprotected so analytics-only users can reach it;
  the shortcode and authenticated AJAX enforce company membership/roles. Existing
  course pages, levels, drip rules and practicum are not changed. Noindex/nocache
  and no-referrer headers apply. Invitation query strings are removed from browser
  history before normal head scripts run. Host page cache exclusion still requires
  deployment verification, particularly for anonymous invitation pages.
* Owner-only Team area: send invitation, resend (rotates link), revoke pending link,
  change Analyst/Viewer role, remove access. Owner cannot be removed or demoted.
  Pending invitations reserve seats; five includes owner; seven-day expiry;
  one-minute resend cooldown; hashed one-use tokens; recipient email binding;
  per-email/per-company locks. Existing accounts explicitly accept while signed in.
* New invited staff create a WordPress Subscriber account on the invitation page.
  No WishList registration flow or course-level assignment is called by this plugin.
  Existing course members reuse their account without changed course progress.
  Live new-user onboarding, site login hooks/WishList behavior, actual invitation
  deliverability, recipient acceptance and cross-role browser UAT remain unverified.
  No invitation emails were sent during deployment.
* Brand settings are company-wide: website, raster logo upload, social URLs for
  Facebook, Instagram, LinkedIn, YouTube, TikTok, Reddit and X. Owner-only.
  PNG/JPG/WebP up to 2MB/4000px; original uploads are WordPress media assets and
  should be public brand imagery, not confidential documents. Uploaded logo appears
  inside the Brand circle. Profile links do not authorize providers or filter sources.
* Subscription enforcement remains preview provisioning; paid WishList entitlement
  and review-cycle adapters are still required before commercial launch.
* Legacy company member-save endpoint no longer bypasses invitations/seat reserves.

== 0.4.1-preview ==
Date preset fields update immediately. Changing dates cancels stale analytics requests and invalidates the prior snapshot. Authenticated dashboard polls advance one GA4 report per request, without depending on WordPress cron. Report requests time out with a retry message.

== 0.4.2-preview ==
Responsive infinity loop uses fixed drawing coordinates, preventing resize feedback. Email campaign list replaced by combined totals and comparison changes; plain-language measurement notes. Includes 0.4.1 date and collection fixes.

== 0.4.3-preview ==
Show three largest traffic sources plus Other sources, comparing the same source groups across both periods. Preserve full evidence for analysis. Duplicate or unavailable cron scheduling no longer falsely fails a Google job; authenticated polling continues. Includes combined email totals and responsive loop fixes.

== 0.4.4-preview ==
Move worldwide location rankings into collapsed additional data. GA4 reporting exposes safe collection stage and failure details; extends report timeout to 25 seconds and retries temporary failures twice before stopping. Full live 60/90-day verification required.

== 0.4.5-preview ==
Buffer analytics and email results until both are ready, then replace the view together. Keep previous report visible with explicit loading/old-date notice; disable print/review during selection changes. Includes service-area definition fields (filtering not active), location collapse and collection diagnostics.

== 0.4.6-preview ==
Expandable performance-over-time charts load only on demand, separately from snapshot collection. Daily up to 31 days, ISO Monday-Sunday weeks through 180 days, monthly thereafter. Three bounded authenticated Google requests collect interval traffic/users, returning users and configured goal events, with company-scoped six-hour cache. Users are queried at interval granularity, not summed from days. Shows selected period only. Campaign markers, geographic filtering and additional bot screening are not connected.

Traffic quality status is explicit in the snapshot and print view: known bots excluded automatically by GA4 for native connections (unverified for imports), additional screening not connected, service-area filtering not applied. Email bot filtering is unverified. Never claim remaining visits are verified humans or provide an invented exclusion count.

== 0.4.7-preview ==
Compact 12px traffic-quality status strip replaces the large heading and paragraphs. Longer explanations remain accessible under Filtering details and are expanded for print.

== 0.4.8-preview ==
Compact timeline values table: MM/DD/YY dates, one date per daily row, abbreviated headers, right-aligned tabular numbers, subtle striped rows and two-decimal goal rates. Weekly and monthly rows retain both range boundaries.

== 0.4.9-preview ==
Report disclosures use dark blue headings with white text, plus/minus state indicators, hover and keyboard focus treatment. Filtering details retains compact sizing.

== 0.5.0-preview: Additive workspace navigation ==
Report / Review / Account tabs retain the existing report, infinity loop, headline metrics, timeline, sources, audience, goals, demographics and email totals. Report shortcuts navigate to existing sections without replacing them. Account contains Company, Connections, Users & Invitations and Billing. Existing owner permissions remain authoritative; direct social APIs and subscription billing are labeled not connected. Report printing excludes account information and review controls. Native tab keyboard navigation supports arrows, Home and End. SMM blog classification uses the /blog/ path; /blog and /blog/ are blog indexes, not counts of published posts. Expanded content/goal/source reports remain subsequent work.

== 0.5.1-preview ==
Compare None reports current totals only, without requesting the second date range, percentage changes, or directional movement. Timeline and printing remain available; movement review requires a comparison. Tabs, dates and report shortcuts stay sticky below the WordPress admin bar. Account navigation persists through URL fragments; saving Brand settings returns to Account > Company. Development sample/import controls are now admin-area only. Disclosure labels override theme color conflicts and use down/up arrows at the right.

== 0.5.2-preview: Account and invitation experience ==
Your login tab supports display-name editing and email-change requests. Owner approval sends verification to the requested address; confirmation requires the same signed-in member and a valid single-use token. Existing email stays unchanged until verification. Owners can edit member names/roles, revoke company access, resend pending/expired invitations and transfer company ownership to an existing active member after current-password confirmation. Previous owner becomes Analyst; WordPress roles/course access are not reassigned.
Invitation emails use the Social Media Magnet Pro sender name, identify the inviting person/company/role and include an optional personal message. Resend permits editing the message and invalidates the old link. Signup pre-fills and locks the token-bound invited email server-side, with Show/Hide password. Team actions return to Account > Users & Invitations. Uniform CSS chevrons replace font-dependent disclosure glyphs.
No real invitations or ownership transfers are performed by local tests. Live mail delivery and the shared WordPress/WishList identity journey still require verification.

== 0.6.1-preview: Facebook Page connection pilot ==
Meta business app configuration, User-token Facebook Login for Business authorization, exact state/session/company/app binding, encrypted credentials, one-page discovery per request, explicit Page selection and local disconnect. Only GET API endpoints are allowed; granted permissions must be limited to the Meta Pages business_management dependency, Page listing/read engagement/insights plus basic login defaults. business_management can read and write business assets at the provider; this connector remains restricted to fixed-host GET reporting endpoints. No posts, messages, ads or budget changes.
Expandable reporting probes page_total_media_view_unique as a daily metric. Availability is not assumed; unsupported/denied metrics show an error, never a made-up zero. Peak daily unique viewers is not deduplicated period reach. Reports collect in bounded 30-day chunks, cache six hours per company/Page/date range, and remain separate from GA4 conversion reporting. Meta day boundaries can differ from GA4 timezone. Date ranges up to one year per period are supported. No total reach, paid/organic split or post ranking is claimed.
Requires live Meta app settings and Page authorization before use. Public access may require App Review, business and Tech Provider access verification, privacy/deletion information. Local fixture tests do not prove live metric availability or Meta approval.

Facebook pilot setup:
1. Westgate owns the Meta business app; Aladdin remains owner of its Facebook Page.
2. Configure Facebook Login for Business with User access tokens and business_management, pages_show_list, pages_read_engagement, read_insights. Disclose the broader business-asset permission and obtain authorization before adding it.
3. Register the exact redirect URI shown in Account > Connections > Facebook Page connection > Westgate Facebook application settings. Add the Pro site app domain and the appropriate privacy/deletion information in Meta.
4. Enter app ID, login configuration ID, Graph version and app secret directly into that WordPress form. Never put the secret in chat.
5. Connect Facebook as an app-role pilot user with analytics access to Aladdin's Page. Load available Pages, choose Aladdin and select Use this Page.
6. Open Report > Facebook. Reconcile the returned daily viewer metric and date coverage against Meta before using it in recommendations. Unsupported metrics remain unavailable. Broader production access requires completing the Meta requirements shown in its app dashboard.

0.6.1-preview: Live Aladdin pilot authorized and selected successfully. Meta Pages business_management dependency is disclosed; GET-only endpoint restrictions remain in place. Discovery distinguishes zero returned Pages from rejected analytics tasks. Insights time-navigation links are accepted only when every requested daily value is present; incomplete pages still fail without publishing partial data. A new cache format avoids reusing the earlier parsing failure. Live 30-day current/prior collection returned full daily coverage; totals remain unreconciled against Meta Business Suite.

== 0.6.2-preview: Facebook performance measures ==
Replaces the peak daily viewers pilot with end-of-period followers, net follower change and percentage, additive period views with optional period comparison, and the top five posts published in the selected range ranked by lifetime post views through collection. Meta deprecated Page fans and impressions history; the replacements retain their proper followers/views labels. A follower change needs both opening and closing boundaries. Views require every daily value. Missing metrics remain unavailable independently. Fixed-host GET published_posts pagination collects up to 500 posts / 20 pages, ranks only available view counts and flags incomplete rankings. No provider paging URL is followed. This reads Page posts without publishing or changing them.
Validated live on Aladdin 10/18/26: 30 days 09/18/26–10/17/26 returned 1,845 followers (+8 net), 1,073 views vs 2,774 (-61.3%), and 18 ranked posts. 60-day Compare None returned +12 followers, 3,847 views and 28 ranked posts across pagination. Counts have not yet been reconciled manually against Meta Business Suite.

Post table refinements: left-aligned excerpts, shortened to 90 characters, wrapping within a fixed-width layout, with a visible Views column. Links open the full Facebook post.

== 0.6.6-preview: Instagram reporting and social layout ==
Adds optional instagram_basic and instagram_manage_insights to the existing Meta User-token flow, an owner-only linked-Instagram connection, separate company-bound Instagram cache and lazy Report section. Uses the User grant, never a copied Page token, for professional-account discovery and Instagram reporting. The selected account is verified against the authorized Page. Reauthorization, Page changes and disconnect clear dependent Instagram identity and reports. No publishing, comments, messages or ads scopes are added.
Instagram reports current followers at collection, selected-period follows minus unfollows (native FOLLOWER/NON_FOLLOWER buckets), additive account views and optional previous-period/year views. Account insight requests use nonoverlapping UTC timestamp boundaries in bounded 30-day chunks. Top five published-in-range Instagram posts rank lifetime views; media pagination stops at the date boundary or a marked 20-page cap. Missing counts remain unavailable. Meta token metadata verifies app identity, User token type, validity and actual token/data-access expiry. Verified nonexpiring tokens receive a bounded 90-day local authorization window; provider revocation remains effective on API requests.
Social summaries use two cards, with Previous period or Previous year beneath Total views. Post columns are Rank, Views, Date, Post, with narrow numeric/date columns, left-aligned 90-character excerpts and links to full posts.
Validated live on @aladdincleans 10/18/26: 09/18/26–10/17/26 returned 1,001 followers at collection, -3 net followers, 2,212 views vs 3,375 (-34.5%), and 20 posts ranked by lifetime views. Facebook remains connected to Aladdin Cleaning & Restoration. Counts have not yet been manually reconciled against Meta Business Suite. Public customer access still requires Meta's app review/access requirements.
