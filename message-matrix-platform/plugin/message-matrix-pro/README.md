# Admin foundation update 0.1.0-beta.10

Adds a top-level Social Media Magnet Pro admin area, company-scoped role management and saved campaign inspection. See ../../docs/REVISION-beta.10.md for validation and remaining limitations.

# Social Media Message Matrix Pro

Version 0.1.0-beta.8 · Standalone planning beta · October 20, 2026

This is the first functional WordPress build following the approved Message Matrix Pro preview. It is intended for staging evaluation. Beta 8 has been installed and its grouped review, filters, edit handoff and approval blockers checked on staging. Approval readiness, archive contents and embedded Excel export have development coverage. Full customer-journey and upload UAT remain required before production.

## What the beta provides

Company planning workspaces saved in WordPress; one login can belong to multiple companies. A compact company selector, eight planning steps, visible save status and optimistic concurrency protect work from silent overwrites. No sample company data is installed.

| Journey | Step | Saved work |
| --- | --- | --- |
| Build Your Brand & Matrix | 1. Define Your Brand | Story, Promises, Beliefs, Trust, positioning, voice, website, location and logo URL |
| | 2. Identify Your Audiences | Audience verticals, needs, geography and supporting evidence |
| | 3. Build Your Message Matrix | Topic buckets, audience × topic grid, messages, proof, serial themes and Brand Axis purpose |
| | 4. Choose Your Channel Approach | Channel-specific treatment, format and destination; approved foundation snapshots |
| Create a Campaign | 5. Plan Your Campaign | Campaign date range, channels, selected message paths, cadence and manual calendar entries |
| | 6. Create Campaign Assets | Manually authored blog, email and social copy with linked Image Instructions, calendar dates and assignments |
| | 7. Review, Approve & Package | Explicit asset approval/reopening; grouped Post Copy/Image Instructions review; a ZIP with the formatted Excel calendar and printable content/foundation documents |
| | 8. Measure & Refine | Manually recorded results and campaign refinements |

The Brand Axis remains visible as Awareness, Acquisition and Retention. It describes strategic intent; the beta does not calculate a performance score. The Message Matrix retains audience columns and topic rows. The channel approach adapts that message path to each channel.

## Install on staging

1. Back up staging WordPress files and database. Use WordPress 6.4+ and PHP 8.1+ with InnoDB support.
2. Upload the ZIP through Plugins → Add New → Upload Plugin, then activate **Social Media Message Matrix Pro**. This creates four separate `wgmm_` tables using your WordPress table prefix.
3. In Tools → Message Matrix Pro, create a complimentary test company for an existing owner email. This does not create a user, send mail or change course membership.
4. Create a private staging page containing `[message_matrix_pro]`. For non-administrator UAT, use an authenticated staging page whose visibility permits those test users; WordPress private pages are normally restricted to privileged roles. The plugin itself checks company access on every data request.
5. Exclude the workspace page and `/wp-json/wgmm/` from all page/CDN caching. The plugin sends no-cache headers, but upstream caches must also be configured. Serve over HTTPS.
6. Open the page while signed in. Select the company and complete Steps 1–4. Save, then approve the foundation. Create a campaign and calendar entries, author assets, and test approval/package download.

No production page, navigation item, existing company, Pro Analytics record, WordPress user or WishList membership is changed automatically.

## Membership and company access

The administrator can configure **new, separate WishList Message Matrix Pro level IDs**. Leave the mapping blank for complimentary administrator-provisioned UAT. Do not reuse Pro Analytics or course level IDs unless intentionally creating a bundle later.

An active mapped membership permits one self-created company workspace. Administrators can provision additional companies and complimentary company grants. The original payer's active membership controls company editing; company members do not each need a product or course membership. An inactive company remains readable to its current members, but writing and approvals are blocked. Site administrators can inspect companies; a company still needs active or complimentary entitlement for editing.

| Company role | Access |
| --- | --- |
| Owner | Plan, author, approve and manage existing users' company access |
| Strategist | Plan, author and approve |
| Contributor | Read and edit title/copy/review status of assigned draft assets |
| Reviewer | Read, approve and reopen assets |
| Viewer | Read and download |

The Account & team screen adds, changes or removes **existing site logins by email**. Five seats is the default, configurable from 1–50 including the owner. Reassign a person's assets before removing them. Company removal does not delete their WordPress account. Owner transfer, email invitation/acceptance, self-service email changes and checkout provisioning are deferred.

## Approval and version behavior

- Approving the foundation creates an immutable saved snapshot of Brand, audiences, buckets, message paths and channel approaches.
- Editing any foundation field clears its current approval. Existing campaigns keep their original snapshot reference.
- New campaigns attach the current approved foundation, if one exists. Otherwise they remain unpinned. Step 5 provides an explicit action to attach the current approved foundation after approval.
- Assets can be approved only when their campaign is attached to the current approved foundation and the asset has content. Approved assets must be reopened before editing or deletion. Reopen approved assets before attaching a different campaign foundation.
- Save and approval requests carry the workspace revision. If another person or tab saves first, the request fails visibly with a conflict; download the unsaved workspace before reloading. It does not auto-merge.
- Calendar status is manually maintained and separate from asset approval. A calendar label never publishes, sends or schedules content.
- Approved packages refresh saved company data first, omit draft assets, and include the campaign foundation snapshots. They contain JSON content plus a CSV calendar; they are not a ZIP of finished images or a connection to channel publishing systems.

## Data and exports

User content is stored as plain text, with validated HTTP(S) URLs, stable record IDs and checked relationships. No AI/API credentials are stored. PDF source documents are encrypted with a site-salt-derived key; preserving the site salts is necessary to restore these documents. Company data is protected by WordPress login, REST nonce checks and company role checks. Browser output is HTML-escaped; calendar CSV fields neutralize spreadsheet formula prefixes.

The screen displays saved timestamps in Central Time. Downloads include a Central Time filename timestamp. Native date fields follow the browser's date-entry locale; handoff calendar dates use MM/DD/YYYY. Internal storage uses ISO dates and UTC timestamps. Printing uses selectable text for the current step, expands its sections and removes editable controls; use browser Print → Save as PDF. Full campaign handoff uses Step 7 downloads. No raster PDF creation is used.

Initial bounds: 30 audiences, 30 buckets, 900 Matrix cells, 2,000 channel approaches, 50 campaigns, 2,000 calendar entries and 1,000 assets. Each company document is limited to 1 MB. These are protective beta limits, not marketed plan allowances. Avoid storing image binaries; the logo is a URL field and visual work is a text brief. A future media service will handle actual assets.

Deactivation/deletion retains data. Back up and explicitly manage the four `wgmm_` tables and `wgmm_settings`/`wgmm_schema` options when retiring the beta. There is no import or restore UI in this build; workspace JSON downloads are portable review copies, not a validated restore mechanism. Add database backups to the staging test.

## Staging acceptance checklist

- [ ] Activation/migration succeeds on the target database with all four InnoDB tables and no PHP warnings.
- [ ] The authenticated shortcode works with the actual theme, caching and security plugins at desktop and mobile sizes.
- [ ] Create two companies and distinct users. Verify a nonmember cannot list/read/save/download the other company's records or foundation versions by changing REST IDs.
- [ ] Test all five roles, assigned versus unassigned assets, invalid nonce and signed-out requests.
- [ ] Test real WishList active, cancelled, expired and pending membership responses, plus complimentary access. Pro Analytics-only membership must not grant Message Matrix Pro access.
- [ ] Save Brand, audiences, buckets, Matrix cells and channel treatments; reload to confirm persistence. Edits remain on the same step.
- [ ] Approve a foundation; create a campaign; approve an asset; confirm edits/deletion are blocked until explicitly reopened. Revise the foundation and test campaign rebasing.
- [ ] Open two tabs, save one and then the other. The stale tab must keep its draft and offer download/reload without overwriting newer saved work.
- [ ] Switch companies and confirm unsaved-change warnings and correct company context.
- [ ] Test 7/30/45/60/90-day campaigns and invalid calendar dates; confirm errors are visible and unsaved work is retained.
- [ ] Test team seat allowance and member removal with assigned assets. No course access should be granted.
- [ ] Verify print layout, selectable text, expanded long copy, output title and timestamp. Verify literal formula-like XLSX text and approved-content package scope.
- [ ] Verify keyboard navigation, focus, contrast and section arrows using the real theme. Confirm downloads work in Safari/iPad; some browsers require permission for multiple downloads.
- [ ] Restore a staging database backup before production approval.

## Checks run in development

PHP 8.3 syntax checks; approval-readiness and domain checks; 11 access/concurrency/nonce checks using WordPress/database doubles; JavaScript syntax and interface checks using DOM/network doubles. Interface checks cover all nine views (eight steps plus Account), HTML escaping, save-in-place, unsaved-change warnings, stale-save recovery, authenticated requests and XLSX literal-text safety. These are not real WordPress integration, migration, browser-layout, load or accessibility tests.

## Deferred scope

No campaign agent, AI generation, image generation, Excel parsing, autonomous scheduling, social/email publishing, Pro Analytics data connection, checkout/billing automation, public sales pages, email invitations, company ownership transfer, cross-product imports or production deployment. AI generation should follow validated foundations, approved campaign boundaries, explicit user review and a separate credential/service layer. The original workbook's formulas and import mapping must be reviewed before import is enabled.

Source mapping follows the reviewed curriculum's Brand Overview → audience × topic Message Matrix → channel interactions → editorial calendar. The current Excel workbook has not been formula-validated. Dates, audience counts and campaign lengths remain flexible rather than copying conflicting legacy instructions.

## Beta 2: Brand foundation sources and product identity

Step 1 now begins with Strategy Canvas & Brand Dyno. It explains the Canvas as the starting strategic foundation and Dyno as organizational alignment/delivery context, links to both offerings, shows document status and offers company-private PDF uploads up to 20 MB. Owners and Strategists can attach sources; all company members can download attached source files.

When Pro Analytics is installed on the same WordPress site, Step 1 lists existing source documents from Pro Analytics companies the signed-in user can access. The user explicitly chooses a company document to copy into the current Matrix company. There is no automatic name-based company mapping and no change to the original upload. Copying rechecks source-company access. Staging showed Aladdin’s Strategy Canvas already uploaded in Pro Analytics; Brand Dyno was not uploaded.

PDF bytes are encrypted in a separate private `wgmm_sources` table, not public Media Library attachments. Copies are independent. Replacements retain earlier files for approved foundation references. Source history is capped at 100 MB per company in this beta. Uploads and imports use a nonce, company roles and revision checks. A replacement clears current foundation approval. An attached source also requires a written implications summary in Step 1 before foundation approval. Saved foundation versions include file references and hashes. Download requires sign-in, a nonce and matching company access.

Attaching is not automatic interpretation: this planning beta does not extract the PDF text, call an AI model or generate Brand fields. The user reviews the documents, records their implications and completes the Brand draft. Automated grounded drafting remains a later feature.

The Message Matrix Pro logo is included locally. It uses the product family’s blue palette with an audience/topic cell symbol and connected message loops. The Social Media Magnet Pro byline is rendered as page text.

Product structure: Live Campaign is the program; Message Matrix Pro is the tool to plan and build a Live Campaign; courseware teaches the method; Pro Analytics measures results. No production navigation changes are included.

Additional UAT required: upload/replacement PDF, existing Pro Analytics source copy with correct company, private download and denied cross-company download, expired nonce, stale-revision upload, old foundation reference retention, WordPress/PHP upload limits and source-history limit. Confirm the new logo and both source panels at desktop/mobile widths. Beta 2 has passed syntax and mocked-client/domain/access/encryption checks; staging deployment and full upload UAT are still pending when packaged.

Calendar exports: Step 5 includes a campaign-filtered schedule, formatted Excel workbook, printable Calendar and Campaign Plan. Workspace JSON is labeled as a backup. PDF uses browser printing and selectable text.

Excel calendar: native formatted .xlsx with a left-to-right daily timeline, weekly groups, channel/audience rows, Brand Axis colors, frozen headings, Campaign Plan and Content Details. CSV download removed.

## Beta 8: Review, Approve & Package

Step 7 groups each post with its Image Instructions, planned date, channel and Brand Axis purpose. Campaign and readiness filters, counts and a list of calendar items missing drafts make unfinished work visible. The Brand/Message Matrix context shows the audience, topic, message, proof and channel approach. Human review still confirms all claims, advice, permissions and destinations.

Owners, Strategists and Reviewers approve Post Copy and Image Instructions separately. Server-side checks require nonempty content, a linked calendar item, a matching Brand Axis purpose, and the campaign attached to the current approved foundation. Image Instructions must link to a content draft. Reopening is always permitted for authorized reviewers even when readiness checks fail. Approval does not change calendar publication status.

A single timestamped ZIP contains the formatted horizontal Excel calendar, an HTML content document with Post Copy and Image Instructions kept together, an HTML saved Brand foundation, and a plain-text readme. The HTML opens in a browser and prints as selectable text. Only approved posts whose linked Image Instructions are all approved and whose readiness checks pass enter the content document. Unlinked instructions are excluded. A text-only post is allowed and visibly identified. The Excel calendar retains the full selected campaign plan, including unfinished slots and their calendar statuses; it is not an approved-publication manifest. Source PDFs and finished image files are not embedded.

This build does not automatically approve the Aladdin foundation or test assets, send email, schedule or publish content. The correct Strategy Canvas attachment and owner confirmation remain pending before campaign approval.
