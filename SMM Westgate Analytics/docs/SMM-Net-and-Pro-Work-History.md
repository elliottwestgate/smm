# Social Media Magnet .net and Pro Work History

## Purpose and status

This record consolidates the SMM website work from the current desktop project, saved deployment/test notes, and the related chats reviewed on 17 October 2026. It gives the owner and a future developer a practical account of the changes, the behavior to preserve, and the remaining work.

Deployment and validation statements below describe the recorded work at that time. They are not a fresh audit of every live page, provider account, plugin version, or historical member. Later completed steps supersede earlier staging-only and blocked-session notes. Sensitive account records, customer payment identifiers, credentials, private rosters, and raw session logs are intentionally excluded from this public summary.

## Sites and access links

| Site or area | Link | Purpose |
| --- | --- | --- |
| SMM .net | https://socialmediamagnet.net/ | University/student and professor program |
| SMM Pro | https://socialmediamagnetpro.com/ | Individual Live Campaign program |
| .net course | https://socialmediamagnet.net/smm-course/ | Curriculum and sequential levels |
| Pro course | https://socialmediamagnetpro.com/smm-course/ | Pro curriculum |
| Pro enrollment | https://socialmediamagnetpro.com/buy-smm/ | Current Live Campaign enrollment entry |
| .net account dashboard | https://socialmediamagnet.net/dashboard/ | Signed-in student/member experience |
| Pro account dashboard | https://socialmediamagnetpro.com/dashboard/ | Signed-in Pro experience |
| .net certificates | https://socialmediamagnet.net/my-certificates/ | Member status, downloads, and verification links |
| Pro certificates | https://socialmediamagnetpro.com/my-certificates/ | Pro member status, downloads, and verification links |
| .net certificate reviews | https://socialmediamagnet.net/certificate-reviews/ | Authorized professor/admin review |
| Pro certificate reviews | https://socialmediamagnetpro.com/certificate-reviews/ | Authorized administrator review |
| .net legacy analytics | https://socialmediamagnet.net/student-analytics/ | Existing student analytics portal |
| .net professor analytics | https://socialmediamagnet.net/professor-analytics/ | Existing cohort review |
| Pro legacy analytics | https://socialmediamagnetpro.com/member-analytics/ | Existing Pro analytics portal |
| Mary-Kathryn legacy example | https://mary-kathryn.com/ | Subject website used to inspect legacy analytics |
| .net WordPress administration | https://socialmediamagnet.net/wp-admin/ | Authorized site administration |
| Pro WordPress administration | https://socialmediamagnetpro.com/wp-admin/ | Authorized site administration |
| GoDaddy hosting | https://host.godaddy.com/ | Hosting, backups, staging, and PHP management |
| Project repository | https://github.com/elliottwestgate/smm | Shared project record |

Account, review, course, and administration links may require login and the appropriate role. Public certificate validation uses the individual verification link supplied with an issued certificate; private account pages are not the public verification page. Where the application adds cache-bypass parameters, follow its generated links rather than removing those parameters.

## Payment migration to Stripe

Both .net and Pro were migrated to connected Stripe live checkout, with test mode disabled at the recorded production verification. The .net student offer was verified at $119 one time, its Executive Education offer at $2,500 one time, and Pro New Live Campaign at $2,500 one time. These are recorded product mappings, not a promise that future prices cannot change.

Legacy vouchers were reviewed and migrated. The obsolete PSUA19 student voucher was retired at the owner's direction in favor of the current student price. Live Campaign sales were moved to Pro rather than retaining a separate .net Live Campaign checkout.

A Pro test transaction exposed a serious mismatch between the displayed voucher total and the amount charged. The owner submitted a full refund, which was confirmed in Stripe. Checkout was paused during investigation and restored after the discount/charge fix and sandbox verification.

The Stripe integration was patched to resolve immutable coupon IDs, paginate legacy-name lookup, and validate coupons at charge time. Invalid, expired, inactive, or wrong-product discounts are rejected rather than falling through to a full-price charge. Each invoice explicitly carries its applicable discount, excludes unrelated pending items, and is checked against the expected price/discount total before payment. Existing customer discounts are not changed as a side effect.

The saved audit records fourteen sandbox scenarios plus a final-code repeat of the $1 case. The tests covered fixed/percentage/free discounts, legacy names, invalid and wrong-product coupons, promotion codes, customer-balance protection, declined cards, and preservation of an existing customer discount. Successful amounts were checked against Stripe invoice/charge results. No additional real-card charge was submitted for that verification.

The fix is a custom patch in the WishList Member legacy Stripe integration. Vendor updates can overwrite it. Preserve the patch and repeat sandbox checks before accepting live payments after an update.

## PayPal and Payflow continuity

PayPal was retained for possible existing student renewals. The receiving merchant configuration was checked, warning messages cleared, and cancellation destinations corrected. PayPal Checkout and Payments Pro remained active in the recorded post-migration state.

Payflow was not canceled. The owner wanted to wait until Pro was updated and legacy renewal exposure was understood. A new Stripe checkout does not itself establish that old PayPal agreements can be terminated. Review active agreements, callback behavior, and recent activity before canceling services.

## Pro enrollment and registration

Pro public enrollment was simplified around Live Campaign. Retired public university/professor/executive marketing entry paths were redirected to the current enrollment page. Existing pages and historical member records were retained rather than indiscriminately deleted.

Nine legacy registration levels were ultimately checked as Existing Members Only; New Live Campaign remained New Members Only. This superseded an earlier partially verified note. Existing Student, Professor, and Executive Education members retained their appropriate paths while new public enrollment focused on Live Campaign.

The paid registration form incorrectly required a username even though email-based registration was intended and the visible username field was absent. Server-side email-to-username normalization was added so valid internal usernames could be created while preserving the full email address for email login. Recovery of the registration flow was verified, and the owner confirmed successful completion.

## Membership navigation and course progression

WishList confirmed that SmartNav was no longer supported. Instead of simply removing it and losing membership-aware navigation, replacement routing was implemented around the existing assignments and priorities, with configuration available through Appearance > Membership Navigation. SmartNav remained inactive.

On .net, all 144 recorded menu/priority fields were preserved through deployment. On Pro, ties between account-type and generic course-progress menus were resolved so legacy account navigation remained predictable. WishList's native content/menu protection continued to control access.

The saved Pro audit records 91 synthetic membership-routing combinations, preservation of nine legacy menu inventories, and five redirect checks. Those tests verified menu selection, not completion of every course assessment. An actual test-member session also confirmed Member Course Info and orientation access.

Staging course checks covered sequential unlocking, protected lessons, passing/failed assessments, and retakes that preserved earned access. PHP compatibility and quiz membership-update issues were corrected. Test membership changes were restored after validation. A separate fresh production student run through every course level was not recorded.

## Plugin and presentation maintenance

Plugin updates and compatibility work were staged before deployment where practical. Premium vendor updates were deferred at the owner's request until live/domain licensing could be used. The owner later reported the plugin updates complete and specifically confirmed WPBakery was updated. Theme My Login Profiles was intentionally inactive on Pro, matching .net. Blog Designer was accepted as adequate by the owner. This does not substitute for a current complete plugin inventory.

The .net homepage logo and course image were adjusted to suitable responsive sizes, the course photograph was lazy loaded, and unnecessary homepage-only spreadsheet/date-picker assets were removed. Obsolete server-domain links were corrected. A duplicate GA Google Analytics plugin was deactivated while an existing GA4 tracker was retained. That tracker cleanup did not modernize the legacy Practicum analytics integration.

Four owner-identified unwanted accounts were permanently removed after explicit confirmation. This summary makes no forensic claim about their origin. No passwords, private account addresses, or deletion screenshots are published here.

## Certificates and public validation

The owner wanted a simpler certificate option without a paid certificate-design subscription: upload finished artwork, insert the recipient details, approve earned awards, retain them in the account, and provide a downloadable PDF and a public reference URL suitable for a CV or social sharing.

Accredible's existing designs and credential history were assessed. New .net and Pro design copies were prepared separately so historical credentials were not rewritten. Dynamic recipient fields were removed from background artwork; the generated certificate supplies each person's name. Sample previews were labeled Your Name Here. Pro wording establishes the designation Certified Social Media Magnet Pro.

A shared Social Media Magnet Certificates plugin was developed and tested. The .net workflow scopes professor review by assigned university and professor, with administrator fallback for ambiguous assignments. Pro provides administrator review for individual members. Company grouping was deliberately deferred.

Members can request review, see pending/issued status, download earned certificates, and copy the public verification link. Reviewers confirm the actual final course/practicum requirements before approval; issued requests show a green completion indication. Duplicate requests/approvals do not create repeat credentials or notifications. Revocation and correction paths are supported. Earned certificates remain accessible after course membership ends, subject to account authentication and certificate status.

Level 7 access alone is not evidence of completion. The legacy analytics green check refers to connectivity and is not used as certificate-completion evidence. A trusted automatic terminal-assessment adapter was not connected; assessment-only issuance must fail closed without that evidence. Human review preserves the established completion decision.

The recorded local test suite passed thirty integration checks, including permission boundaries, duplicate prevention, revocation, immutable template versions, expired-member access, invalid uploads, and notification suppression. Sample PDFs, long-name fitting, and accented Latin names were checked. Both staging sites completed member request, reviewer approval, download, and anonymous public-validation checks using SAMPLE credentials.

The certificate functionality was then deployed live on both sites. The latest recorded versions were .net 0.1.4 and Pro 0.1.2. Requests and notifications were enabled for production. Public validation and downloads for issued credentials were tested without requiring a visitor account; member/review screens remained private.

Theme My Login's virtual dashboard required an additional integration hook. GoDaddy caching also overrode private response headers, so the .net fix adds cache bypass to relevant generated account links. Actual host/CDN bypass behavior was checked. Preserve those protections when modifying account analytics or certificate screens.

Business notification delivery remains a follow-up: WordPress accepted a test message, but receipt at the intended business inbox was not confirmed. The owner deferred that investigation until after go-live. An accepted send is not proof of delivery.

Historical posts, attribution, and issued credentials were retained. Former-contributor backend permissions and future certificate branding were adjusted as directed without removing historical authorship. This was not an exhaustive review of every old PDF, video, or image.

## Hosting, staging, backups, and PHP

GoDaddy's staging workflow initially became stuck. The owner contacted hosting support, who reset the staging job/settings. Deployment then used tested files with the production database preserved, avoiding replacement of live members, payment state, and account changes with stale staging data.

Pro staging was refreshed from the newer live Pro site before the certificate work continued, following explicit owner confirmation. That refresh copied production to staging; it was not a push of old staging content onto production.

PHP compatibility work included course/profile behavior, quiz updates, a Theme My Login function guard, and compatibility checks around old certificate integration code. Both production sites and staging environments were subsequently recorded on PHP 8.3; Pro production was specifically verified as 8.3.35 in WordPress Site Health. WordPress 7.1.3 was recorded during the work.

GoDaddy on-demand backups initially returned generic failures. Existing restore points were preserved; later successful production backups were confirmed for both sites before the later certificate/PHP changes. The .net certificate backup completed on 17 October, followed by the Pro pre-PHP backup. The precise root cause of the earlier backup failures was not established. Historical plugin errors in logs were not treated as proof that a particular plugin caused the backup failures.

No backup restoration was performed merely to investigate those errors. Continue to verify a completed backup and a recovery plan before production changes.

## Practicum analytics replacement

The next project is a replacement for the UA-era Website Analytics system. The supplied plugin, theory white paper, selected course exercises on both sites, and Mary-Kathryn's legacy dashboard were reviewed. The discovery found both an obsolete API dependency and metric-definition/error-handling problems that require more than a tracking-ID change.

The recommended architecture is a hosted analytics service, optional AI coaching, and thin connectors for .net and Pro. Customer websites should use ordinary GA4 tagging and email-provider connections, without an SMM installation or publishing dependency. Preserve the teaching structure while supporting landing pages, services, products, and other activity beyond blogs.

This phase produced a scope, evidence record, sanitized legacy reference package, and implementation backlog. No replacement analytics service or GA4 adapter has been deployed. Current browser checks found critical errors on Mary-Kathryn's analytics dashboard and Pro's member analytics screen; their causes were not traced in this scoping phase.

See [the complete replacement scope](Westgate-Practicum-Analytics-Replacement-Scope.pdf), [handoff](HANDOFF.md), and [backlog](BACKLOG.md). The next milestone is a reconciled GA4/Mailchimp pilot on an authorized project and a non-WordPress platform.

## Book and repository continuity

The earlier Social Media Magnet Pro chat identified a completed publishing DOCX, but that upload was left unfinished. During this repository consolidation, the owner chose to include both available book editions, with the saved OneDrive copy marked current and the earlier chat attachment retained as the prior edition. See the [publishing index](../../publishing/README.md) for exact filenames and checksums.

This repository now provides a portable project record that can be opened on an iPad or another computer independently of the local desktop chat. It does not turn the originating Codex thread into a normal cloud chat or transfer its signed-in browser sessions.

Remote pairing was separately blocked by a 403 `codex_workspace_access_denied` response. The owner confirmed the desktop app uses a business/team workspace. The next account-access step is administrator review of Codex/Remote Control availability and the member's entitlement, not another website/plugin change.

## Remaining work and maintenance priorities

1. Resolve workspace remote-access eligibility with the business/team administrator if mobile access to the live desktop thread is needed.
2. Confirm production business-inbox delivery for certificate notifications and monitor real request/reviewer behavior.
3. Recheck plugin versions and preserve custom WishList Stripe, registration, membership-navigation, theme, and certificate compatibility patches across vendor updates.
4. Audit PayPal agreements/renewals and Payflow dependencies before canceling legacy payment services.
5. Keep issued certificates publicly verifiable while account/review data stays private, including actual hosting-cache behavior.
6. Run the analytics integration pilot, approve metric and scoring rules, and estimate implementation/operating costs before building the licensed product.
7. Preserve historical grades, source records, credentials, and course progress during migration; do not remove the Master plugin's non-analytics responsibilities prematurely.

## Evidence behind this record

Related chats were read by their recorded titles: Social Media Magnet Pro; socialmediamagnet.net Help; Continue Stripe Migration; and Branch · Continue Stripe Migration. The latter migration chats document browser-control interruptions and staging continuity; those interruptions are not evidence of a later deployment failure. The current local project continued the implementation and rollout.

Saved local audit notes used for this consolidation include live deployment verification, staging fixes/deployment notes, the Pro migration/coupon audit, the Pro navigation audit and its completed registration follow-up, certificate assessment/design/test notes, and the current project handoff. Original operational screenshots, private identifiers, account inventories, and raw notes remain local. No full transcript export is represented by this summary.
