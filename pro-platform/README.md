# Social Media Magnet Pro Analytics

## Current deployment — 0.6.5-preview

Pro Analytics runs inside socialmediamagnetpro.com on WordPress. No separate application server or customer-installed plugin is required. The plugin slug is `westgate-practicum-pro`; the dashboard uses `[westgate_practicum_pro]`.

Public navigation starts with Pro Analytics → Overview / Dashboard / Buy. Marketing page sources are in `site-content/`; their published content is stored in WordPress. Dashboard access requires a company role. Course content and analytics seats remain separate permissions.

## Membership and checkout

- WishList owner memberships: Pro Analytics Monthly `1792352584`; Pro Analytics Annual `1792353068`.
- Stripe product `prod_VSwB4n1wDXFaeC`: $99/month `price_1US0JSHNnPyJZrxc0WttK0KQ`; $990/year `price_1US0KoHNnPyJZrxcpNjG1Ltc`.
- Promo codes `SMMPROFREE` (100%) and `SMMPRO50` (50%) apply once to the first billing period, restricted to the Pro Analytics product. No expiration or global redemption limit. Both coupon IDs and customer-facing promotion codes use these values.
- Buy uses WishList Stripe registration buttons with the coupon field enabled. Both live checkout forms were opened and verified for the correct amount and recurrence. No payment was submitted.
- Each paying account can create one company workspace. Its original payer ID is recorded server-side in `_wgpp_billing_owner`; owner transfer does not change the payer.
- Active WishList membership is required for every seat in a paid company. Cancelled, pending, unconfirmed, expired, malformed or unavailable membership responses fail closed. Membership data is reused within one request only.
- Five seats include the owner. Pending invitations reserve seats. Owners invite, resend, revoke, edit roles, remove members and transfer workspace ownership. Team invitations do not enroll people in the course or create subscriptions.
- The two Pro membership levels have the union of Live Campaign, Intro and Levels 1–7 page permissions: 183 curriculum pages synchronized. The payer receives a Live Campaign Library link in the dashboard. Other course memberships are unchanged.
- Existing explicitly provisioned preview workspaces remain separate from paid companies. Aladdin remains a preview workspace; it is not silently subscribed or charged.

## Reporting

Native read-only GA4, Constant Contact, Facebook and Instagram connections use company-scoped encrypted credentials. Views, posts and email responses retain provider-specific definitions. No individual contact lists are collected.

Dates support 30/60/90/365 days and custom ranges, with None / prior period / previous year comparisons. New date results are buffered until ready. The bounded Message Magnet diagram uses session change vertically, returning/new ratio change horizontally, and goal events per 100 sessions outside the circle. Movement is relative to the company's own results, not competitors. Audience demographics and technology are differentiated; unavailable and Unknown values are preserved.

GA4 automatically excludes known bots; its excluded volume is unavailable. Additional bot screening and service-area filtering are not implemented. Recorded geography is not proof of customer residence or bot activity.

## Pro Analytics Review

Owners and analysts can deliberately request a saved Pro Analytics Review. Every company member can reopen and print completed reports without consuming another allowance. Four reviews are shared per monthly subscription-anniversary window, including annual plans. Day 29/30/31 boundaries clamp without drifting; timezone and daylight-saving transitions are retained. Preview companies use calendar months in America/Chicago.

Tools → Pro Analytics Review settings includes a site-administrator-only company allowance reset. It waives completed usage only within the current cycle, preserves reports/evidence and pending reservations, and records actor, timestamp, reason and cycle. It never changes subscription dates or future months.

The native journal serializes reservation and quota checks, makes retries idempotent, counts queued/processing jobs, releases failed jobs, and commits a finished report with its consumption in one company-meta update. Cron is supported; authenticated status polling can advance queued work. A processing job abandoned for five minutes fails and releases its reservation.

The frozen evidence includes both GA4 periods, calculated movement, exact-range available social/email reporting, company context and a bounded public website excerpt. Website capture uses safe HTTPS requests with no redirects. Connection credentials never enter saved reviews or model input.

The review now uses a versioned foundation curated from the owner’s SMM Pro book (chapters 1.4, 2, 4, 5.3 and 7) and Closing the Loop curriculum. Seven required assessments cover Brand hub/narrative harmony, content leadership, acquisition spokes, retention/2+2+2 email, message rhythm/serialization, goal funnel and Infinity Loop balance. Each leads with Results / What it means / Recommended action / How to measure and ends with a small SMM basis. The SMM navy/cyan review opens with a branded company masthead, a data-driven current Brand Matrix position, directional description, and performance outlook. It omits the dashboard KPI cards. The Matrix stays open at the top. Three Develop / Reframe / Create cards connect audience verticals, topic buckets, the Brand narrative, channel roles and publishing-calendar sequences. Detailed SMM reasoning and raw results remain expandable. Paid recommendations are excluded. Default printing omits raw supporting tables unless Print supporting results is selected. Content direction precedes ranked action cards; duplicated findings are suppressed. Supporting sources, audience and platform tables remain in an expandable appendix and may be included in print; large principle lectures do not lead the report. A canonical measured-facts ledger keeps returning-user count change separate from the returning-to-new balance and preserves prior email comparisons. Model control characters are sanitized. Ranked actions identify their SMM principle. Missing creative, cadence, destination-page and funnel evidence is explicitly unverified. The model cannot substitute generic optimization advice or invent client strategy. The foundation is frozen with each new report and incomplete framework output is rejected. Older saved reports remain intact and labeled Earlier format.

Review dates use MM/DD/YYYY and creation/history timestamps use H:MM AM/PM Central Time, with America/Chicago daylight-saving conversion; raw ISO timestamps and UUIDs are no longer shown to users.

The default **Analytical review** uses explicit performance rules and labels itself accordingly. It provides an assessment, visual loop, metric changes, audience profiles, channel evidence, ranked Where / Why / What to do / How to measure actions, limitations and the Elliott / Social Media Magnet About section.

Optional **AI enhancement** is configured under Tools → Pro Analytics Review settings. The key is encrypted, never redisplayed, and only editable by site administrators. OpenAI Responses uses a strict JSON schema, fixed endpoint, bounded response size/time, no tools and `store: false`. Provider errors or malformed output fail the job and release its allowance. Website and post text are untrusted evidence, not instructions. AI enhancement is configured on the live site and an actual model-backed report has been generated.

Print / Save Pro Analytics Review opens a separate report document with the saved evidence, diagram, five action cards in the live Aladdin verification, and About section. Filenames include Central-time date and time. The print document was checked for its loop, actions, About section and absence of horizontal overflow.

## Remaining launch validation

- Complete a controlled purchase/renewal/cancellation test; no live card transaction was performed during this release.
- Meta public-customer access still depends on App Review, business/access verification and appropriate permissions. Current pilot access is not proof that arbitrary customers can authorize the app.
- Self-service subscription changes and invoices are not exposed in the analytics dashboard yet; WishList/Stripe remain the subscription authority.
- Mailchimp direct reporting, additional social providers, service-area filtering and additional bot screening remain future work.

## Validation and packaging

Run the PHP files in `tests/` with PHP 8.3 and the JavaScript files with Node. The 26 test files cover company boundaries, invitations and seats, credentials, provider identity, date buffering, movement and missing baselines, native quota/idempotency, analytical evidence, schema validation, membership denial and anniversary boundaries.

The installer ZIP contains the plugin only. WordPress pages, WishList levels/content permissions and Stripe products are separate site/account configuration. No customer analytics, connection tokens or API keys are included in source or packages. Python files under `service/` are retained reference implementations and are not a hosting requirement.

Facebook and Instagram results retain a non-overlapping lower-view sample (up to five posts outside the top five) from the same bounded collection. Lifetime views measure visibility; engagement counts are not currently supplied. New reviews distinguish topic hypotheses from proven content effectiveness and account for post age/exposure. Brand and Matrix capitalization is normalized in generated review copy and rendered labels. Saved reviews keep their frozen period evidence; changing dates requires a new review rather than relabeling an old one.

Content cards cite exact frozen post IDs. The finalizer resolves citations to channel, date, views and excerpt; unavailable IDs fail generation and release allowance. Audiences are proposed test audiences, never inferred identities.

Review content panels use SMM navy/cyan SVG illustrations for Develop, Reframe and Create. Each post signal is a separate bullet block with channel, date, views and excerpt. Older Test labels display as Create; saved evidence is unchanged and no new evaluation is required.

The Moves to Make Next uses matching purpose-specific SMM graphics, a highlighted action location and separate bullets for recommended changes and measurement checks. Saved review text is retained; presentation updates do not generate a review or consume allowance.

Administrator review allowance adjustment accepts a target completed-review count (default zero). It counts the newest completed reports in the current cycle, preserves pending reservations and every saved report, validates the target against existing completions and allowance, and records the target in the reset audit. Aladdin was set to one counted review / three available by owner request.

Buy-page visual source: site-content/buy-pro-analytics_2026-10-18_17-48-00_CT.html. The page uses SMM SVG illustrations, a clearly labeled illustrative Matrix/KPI sample and content/action sample panels. Both original WishList subscription shortcodes are retained. Samples contain no Aladdin customer analytics.

The detailed illustrative samples and Matrix hero moved to Overview (site-content/pro-analytics-overview_2026-10-18_17-54-00_CT.html). Buy is the funnel close: concise introduction, monthly/annual checkout choices, inclusions, short setup guidance and optional links back to Overview. Source: site-content/buy-pro-analytics_2026-10-18_17-54-00_CT.html. Both original WishList Stripe SKUs and coupon support are preserved.


## Pro login and purchase landing · 10/19/2026

The live `/pro-workspace/` page (WordPress 8824) uses `[wgpp_workspace]`. It is outside the WishList course gate; the shortcode enforces authentication and existing company/paid Pro entitlement. It discloses no company data to visitors or unassigned accounts. It uses a private, noncached response and the one-column wide layout.

Both monthly and annual Pro levels now use `/pro-workspace/` for Custom After Registration and Custom After Login redirects. Course levels remain unchanged. A priority-zero legacy `/dashboard/` redirect handles entitled company seats before the course gate. The login filter uses the authenticated user argument, validates company membership/entitlement, and retains explicit invitations, callbacks, course deep links and deliberate admin destinations. Creating a company returns to the workspace setup checklist.

New purchasers see company creation, then website/profile, reporting connection and optional team steps. Returning members see Report, latest saved Review and role-appropriate tools. Viewers have no owner tools or generation call. Only an active paying Pro member sees the included Live Campaign card; team membership does not grant curriculum access. Review dates use Central Time. The landing reads local saved metadata and does not fetch GA data or generate/consume reviews.

Verified live Aladdin legacy Dashboard redirects to workspace, Report/Review links open the existing application, and the saved-review date matches history. The 19 PHP and 7 JavaScript suites pass, including role routing, denied seats/entitlements, deep-link preservation and role-specific rendering. A fresh checkout/login transaction has not been performed. The separate private Sites homepage concept remains a review draft; the public WordPress homepage was not changed.


### Courseware navigation · 10/19/2026

Public Primary Menu preserves Live Campaign (`/live-campaign/`) and adds Courseware pointing to `/dashboard/?courseware=1`, with Enroll, About and Testimonials nested underneath. The active Live Campaign member menu also points Courseware to that course Dashboard, with About, Testimonials and Enroll as sibling submenu entries. Existing membership menu assignments and course permissions are unchanged. The explicit `courseware=1` destination bypasses only the automatic Pro landing redirect; WishList still protects the course page. Default Pro login/purchase routing remains `/pro-workspace/`. Verified the signed-in course Dashboard, submenu destinations and Pro routing regression suite.


Navigation refinement: both public Primary Menu and the Live Campaign member menu now have Live Campaign (`/live-campaign/`) as parent, with four direct children in order: Enroll (`/buy-smm/`), Courseware (`/dashboard/?courseware=1`), About (`/about/`), Testimonials (`/testimonials/`). Courseware is no longer a separate top-level menu. Membership menu assignments and access rules are unchanged.


Live Campaign Overview rename: existing WordPress page 2 is titled Live Campaign Overview with slug `live-campaign-overview`. Primary Menu item 172, Live Campaign member-menu item 8829, and Footer item 922 use the short label Overview. The member custom link points directly to the new URL; page-backed links follow the renamed page. Verified `/about/` redirects to `/live-campaign-overview/` and the new title/menu render live. No separate corporate About page was created.


## Homepage direction revision · 10/19/2026

Private Sites homepage proposal revised from the book's Brand Story, Promise, Beliefs and Trust foundation, Message Matrix, message harmony and campaign feedback. Hero: Make your Brand a magnet for business. Added stronger business/Brand positioning, differentiated Live Campaign application versus Pro Analytics performance review, and three sourced historical Westgate consulting examples (287% apparel four-year sales growth; $2.64M B2B software first-quarter pipeline growth; 47% powersports one-year sales growth). Source: https://westgatemarketing.net/managed-social-media/ ; university background also confirmed there and https://westgatemarketing.net/about/ . Historical consulting results are explicitly distinguished from analytics subscription outcomes. No guaranteed returns, university endorsement, automatic publishing, budget changes or competitive measurement are implied.

An equivalent isolated design preview is saved as WordPress draft 8834, https://socialmediamagnetpro.com/?page_id=8834&preview=true . This requires an authorized editor login. Source wrapper: site-content/homepage-proposal_2026-10-19_07-44-53_CT.html . The iframe isolates theme CSS, uses only sandboxed scripts, and accepts bounded resize messages only from its own frame. Live WordPress homepage 7690 remains unchanged. Verified preview screenshot, native tab switching and 60-day sample update; the deployed Sites version passed local HTML/anchor and JavaScript syntax checks.


Homepage proposal refinement: headline Grow Your Brand. Make it a Magnet.; compact Pro Analytics (left) and Live Campaign (right) Overview snapshots immediately after the hero, with links to their respective Overview pages. Existing hero buttons and all following sections retained. User selected the consistent illustrative 30-day figures: 12,000 sessions, 9,800 users, 2,160 goal events, 18.0 goal events per 100 sessions; alternate 60/90-day figures scaled consistently and labeled illustrative. Updated WordPress draft 8834 source: site-content/homepage-proposal_2026-10-19_08-01-23_CT.html. Verified headline/counts and the two-panel layout in the signed-in WordPress preview. Live homepage remains unchanged.


### Multiple company workspaces (0.6.8-preview)

A login may belong to several companies with a different role in each. Company membership and subscription entitlement remain server-validated for every workspace. The Company selector is available on Your Pro Workspace and Pro Analytics. The last selected company is only a default; rendered forms, reports, reviews and provider requests retain their original company context. Old unscoped forms from before this update must be reloaded for multi-company logins. OAuth callbacks continue to validate their recorded company/session and fail closed if the active company has changed.

Site administrators can add a complimentary company from the selector. Each explicit grant records its administrator and creation timestamp; it has five seats and four reviews per company month, no billing owner, and no expiry. Additional companies cannot be created against one existing paid subscription through the normal signup form. Invited seats remain analytics-only and invitations are still bound to the recipient email.

V1Moto was provisioned as company 8841, with the existing Elliott site login as Owner. Its public website and Instagram profile were recorded. Google, Meta and email connections require company-specific authorization; no Aladdin tokens, reports or reviews were copied. Aladdin remains company 8791, with one counted review and three available when verified.

Validation: all 20 PHP test files, seven JavaScript test files, PHP syntax checks and JavaScript syntax checks passed. Live verification covered creating the free company, changing companies, retaining company settings after save, and reading Aladdin’s existing report/reviews. No review was generated during this verification.


### Company setup and Mailchimp (0.6.9-preview)

Open company now opens the selected company's Report directly, anchored past the site banner. Empty reports identify the company and link to setup. Account opens with Company details first, followed by Connections. The workspace shows the setup sequence and an explicit Continue setup action. Shared Google, Constant Contact and Meta application settings remain in the WordPress administration preview, rather than the customer-facing account page.

Email provider is chosen on initial workspace setup and in Company details: Mailchimp, Constant Contact or no email reporting. V1Moto is set to Mailchimp; Aladdin's existing Constant Contact grant determines its default provider. Choosing a provider does not copy or remove another provider's credentials.

Mailchimp verification accepts the company owner's API key, reads account and audience metadata, and stores the temporary selection encrypted for ten minutes. The same owner then explicitly connects one offered audience. The connection uses encrypted company-specific credentials and fixed Mailchimp GET endpoints only. Keys carry full Mailchimp account access even though this connector only reads aggregate reports. Neither keys nor contact records enter reports or Pro Analytics Reviews. Live V1Moto authentication awaits the owner's key; no live Mailchimp figures have been claimed or generated.

Report history is paged in bounded requests, filters by the selected audience before caching, and is not published until pagination completes. Failed/rate-limited requests do not expose partial totals. Sent-date ranges use the company reporting timezone; response counts are lifetime campaign counts, with unique opens and unique subscriber clicks, and rates use emails sent to remain consistent with existing reporting. Bot filtering is not claimed. A Mailchimp account without an audience, unavailable count fields, over 10,000 campaign reports, or an incomplete audience list requires follow-up rather than estimated totals.

Validation includes 21 PHP test files, seven JavaScript test files, syntax checks, key/server validation, credential isolation, audience isolation, pagination, failed requests, and live setup/provider-save navigation.


### Compact company switcher

The initial workspace page retains the full company selection/setup controls. Once a company is opened, its Report shows a collapsed company-and-role switcher beside the primary tabs inside the sticky navigation. Expanding the switcher reveals only the company choice and Open company action, with an administrator link back to company setup. A switch returns to the selected company's Report and collapses the control. The report no longer has the large selector panel above it.

Live validation covered V1Moto → Aladdin → V1Moto, automatic collapse after switching, contrast, report loading and a 60-day date change. The reporting script explicitly targets the date-controls form so adding a company form before it does not affect date handling.


## Source reconciliation — 0.6.24-preview

BizGift fixes are maintained in the plugin source: immutable encrypted PDF chunks keep writes below common packet limits; existing inline encrypted documents remain readable; renaming changes metadata without rewriting PDF bytes or frozen Review sources. The Review request step becomes a white, disabled Request ready control, with cancellation and date changes resetting it. Provider updates do not re-enable a completed request. Asset URLs use content hashes so a shared version filter cannot serve stale JavaScript or CSS.

Production file-editor changes are prohibited by `AGENTS.md`. Build only committed source with `python pro-platform/build_plugin.py --output /absolute/output.zip`. The package contains a commit and SHA256 manifest. Reconcile independent target overlays before staging or deployment; uploading this release is a separate operation from merging its source.

## Admin navigation — 0.6.25-preview

The plugin is named SMM Pro Analytics and owns a top-level WordPress admin menu with Dashboard and Review Settings. Legacy Tools routes remain registered for bookmarks but are hidden from the Tools menu. New form returns use the top-level admin routes. All 0.6.24 document and Review fixes are retained.

## Company administration landing — 0.6.26-preview

The WordPress admin Dashboard now opens a company administration index, independent of the last selected customer workspace. It lists company owners, member counts, current-user access, and explicit front-side Report, Reviews and Manage Account links. It does not load provider analytics or generate Reviews. Existing company access controls remain enforced.
