from pathlib import Path
from xml.sax.saxutils import escape
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, PageBreak, KeepTogether
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib import colors
from reportlab.lib.enums import TA_LEFT

ROOT=Path(__file__).resolve().parents[1]
OUT=ROOT/'docs/Westgate-Practicum-Analytics-Replacement-Scope.pdf'
OUT.parent.mkdir(parents=True,exist_ok=True)
navy=colors.HexColor('#14354B'); teal=colors.HexColor('#087F8C'); ink=colors.HexColor('#263A45'); gray=colors.HexColor('#5A6D77'); pale=colors.HexColor('#EDF5F7')
styles=getSampleStyleSheet()
styles.add(ParagraphStyle(name='TitleX',fontName='Helvetica-Bold',fontSize=28,leading=32,textColor=navy,spaceAfter=16))
styles.add(ParagraphStyle(name='DeckX',fontName='Helvetica',fontSize=13,leading=19,textColor=gray,spaceAfter=15))
styles.add(ParagraphStyle(name='H1X',fontName='Helvetica-Bold',fontSize=20,leading=24,textColor=navy,spaceAfter=14))
styles.add(ParagraphStyle(name='H2X',fontName='Helvetica-Bold',fontSize=11.5,leading=15,textColor=teal,spaceBefore=12,spaceAfter=6))
styles.add(ParagraphStyle(name='BodyX',fontName='Helvetica',fontSize=10,leading=14,textColor=ink,spaceAfter=8))
styles.add(ParagraphStyle(name='SmallX',fontName='Helvetica',fontSize=8.2,leading=11,textColor=gray,spaceAfter=5))
styles.add(ParagraphStyle(name='CellX',fontName='Helvetica',fontSize=8.7,leading=12,textColor=ink))
styles.add(ParagraphStyle(name='HeadX',fontName='Helvetica-Bold',fontSize=8.8,leading=12,textColor=colors.white))
story=[]
def p(s,style='BodyX'): story.append(Paragraph(s,styles[style]))
def h(s): p(s,'H2X')
def page(n,title):
    if story: story.append(PageBreak())
    p(f'DISCOVERY &amp; PROPOSED SCOPE  /  {n:02d}','SmallX'); p(title,'H1X')
def table(headers,rows,widths):
    data=[[Paragraph(escape(x),styles['HeadX']) for x in headers]]+[[Paragraph(escape(str(x)),styles['CellX']) for x in row] for row in rows]
    t=Table(data,colWidths=widths,repeatRows=1,hAlign='LEFT')
    t.setStyle(TableStyle([('BACKGROUND',(0,0),(-1,0),navy),('VALIGN',(0,0),(-1,-1),'TOP'),('LEFTPADDING',(0,0),(-1,-1),9),('RIGHTPADDING',(0,0),(-1,-1),9),('TOPPADDING',(0,0),(-1,-1),8),('BOTTOMPADDING',(0,0),(-1,-1),8),('ROWBACKGROUNDS',(0,1),(-1,-1),[pale,colors.white]),('LINEBELOW',(0,1),(-1,-1),.35,colors.HexColor('#D4E0E5'))]))
    story.append(t); story.append(Spacer(1,9))
def link(label,url): return '<link href="'+escape(url,{'"':'&quot;'})+'" color="#087F8C">'+escape(label)+'</link>'

page(1,'Westgate Practicum Analytics')
p('A replacement for Social Media Magnet analytics on .net and Pro - designed to become a licensed Westgate offering.','DeckX')
p('<b>Recommendation:</b> build one centrally operated analytics service, with a small account connector for each SMM site. Customer websites need ordinary analytics tagging, not an SMM plugin, WordPress installation, or blog-posting routine.')
h('Preserve the method. Simplify the delivery.')
p('Retain the curriculum\'s Brand Awareness, Acquisition, and Retention views; cumulative practicum history; comparative review; SWOT reflection; and the Story, Promise, Belief, and Trust framework. Add true weekly results alongside cumulative results so users can see what changed and decide what to try next.')
table(['Layer','Responsibility'],[
('Measurement','GA4 and email-provider APIs collect agreed aggregate evidence. Campaign links identify attributable activity.'),
('Methodology','A repeatable weekly review connects observed performance, brand alignment, external context, and one next experiment.'),
('AI assistance','Explain changes, identify evidence gaps, and suggest experiments using the approved framework. Calculations remain deterministic.'),
('Delivery','A hosted dashboard plus thin .net/Pro connectors. A standalone customer portal can support later Westgate licensees.')],[106,398])
h('Minimum customer setup')
p('Create a project, connect Google Analytics, choose the property and website, connect an email audience, confirm the campaign start and timezone, and validate one meaningful action. Use a supplied campaign-link builder. Weekly collection and reporting then run centrally.')
h('What this review establishes')
p('A feasible replacement scope and pilot plan, based on the supplied source, theory paper, authenticated legacy screens, and selected course exercises. No replacement code has been deployed. Current Google property ownership, email access, exact production grading implementation, and operating costs still require a technical pilot.','SmallX')
p('Prepared 17 October 2026. Working product name; licensing terms, commercial name, pricing, and legal ownership review remain open.','SmallX')

page(2,'What the legacy system actually does')
p('The supplied archive identifies itself as <b>Social Media Magnet - (Website Analytics), version 3.0.1</b>. It is the customer/student website component. The separate Master plugin on SMM retrieves and presents data and also supplies profiles and course templates. It cannot safely be removed wholesale.')
table(['Verified evidence','Replacement consequence'],[
('UA API calls use data_ga and ga: metrics.','Replace the reporting adapter and metric definitions; changing a tracking ID is insufficient.'),
('Weekly WordPress cron stores cumulative HTML tables as website_analytics posts.','Store typed, versioned data centrally. Reporting must not depend on customer traffic, publishing, or WordPress cron.'),
('The extraction starts at the class start date and ends two days before execution.','Make period boundaries explicit. Preserve cumulative views while adding separate complete weekly windows.'),
('New Users is populated from sessions for New Visitor.','Use an explicitly defined GA4 user measure, not a renamed session count.'),
('Emails Sent counts selected campaign IDs; selection is capped at 12.','Separate campaigns sent from messages sent. Discover and paginate all in-scope campaigns.'),
('API failures can become zero cells. A Mailchimp pagination loop advances only on success.','Represent missing/error states separately. Bound retries and timeouts; prevent an unsuccessful request from looping indefinitely.'),
('One date validator reads the end day from the start date. Date resets can permanently delete analytics posts.','Validate dates consistently and archive history. Do not carry destructive reset behavior into the replacement.'),
('Source contains public analytics-post registration, MD5-based shared-token checks, and an HTTP development endpoint.','Redesign authorization and private storage. Findings concern the supplied version; production exploitability has not been tested.')],[249,255])
p('<b>Browser observations:</b> Mary-Kathryn has 54 published weekly records, with Week 67 dated 31 October 2021. Its View permalink returned 404; its analytics dashboard displayed a critical error. The .net portal still renders historical tables and grades. Pro\'s member analytics page displayed a critical error. These observations do not establish the error causes or verify fresh data collection.','SmallX')

page(3,'Turn the theory into a weekly method')
p('The white paper describes a Brand Hub, Message Magnet, and Infinity Loop. The centered brand draws people toward its story, promises, beliefs, and trust; messaging or landscape changes can move it out of alignment. The replacement should make that reasoning actionable, rather than reduce the model to a traffic total.')
table(['Brand Axis quadrant','Human question and supporting evidence'],[
('A - Acquisition / environmental','What prevents unfamiliar audiences from discovering or trying the brand? Record access, timing, technology, cultural, or other contextual evidence.'),
('B - Retention / environmental','What is preventing previously aware audiences from returning? Review return behavior, email engagement, audience feedback, and external circumstances.'),
('C - Retention / competitive','Why might an aware audience be choosing alternatives? Record competitor observations and audience explanations, alongside retention signals.'),
('D - Acquisition / competitive','Where are alternative messages or offerings limiting discovery? Review channel performance and documented competitive context.')],[175,329])
h('Keep measured behavior separate from interpretation')
p('GA4 engagement is a technical session measure. It is not the same as the paper\'s Engagement axis. A returning browser is not proof of loyalty, and analytics cannot identify people who have forgotten a brand or never heard of it. Environmental and competitive explanations need user research, peer review, or recorded observations.')
p('Use a <b>theory worksheet</b>: intended audience and message; Story/Promise/Belief/Trust alignment; evidence; possible quadrant; internal push versus external pull hypothesis; confidence; counter-explanation; next experiment. A user or reviewer confirms the interpretation.')
h('Preserve the educational sequence')
p('The reviewed progress exercise calls for three SWOT reports. Email analysis asks for the best and worst campaign, anomalies, and an A/B test. Closing the Loop compares intended narrative with peer perceptions. The final report combines class comparison, category results, and lessons learned. The new workspace should collect this evidence continuously and export it for those submissions.')
p('The paper\'s quadrant percentages illustrate theoretical scenarios. Do not turn 25% per quadrant or 50/50 acquisition-retention into an automatic target or validated score. Select targets appropriate to the brand and practicum, subject to the owner\'s rubric.','SmallX')

page(4,'A clear measurement contract')
p('Use versioned GA4 definitions and a fresh baseline. Legacy UA results remain an explicitly labeled archive; they are not directly comparable to GA4 results. Proposed technical fields below require compatibility checks against the pilot property. [G1-G3]')
table(['View','Proposed measures and rules'],[
('Awareness','totalUsers, newUsers, sessions, and web-only screenPageViews. Show actual names and measurement version.'),
('Acquisition','Sessions by sessionDefaultChannelGroup, sessionSourceMedium, and sessionCampaignName. Keep Email, Direct, Organic Social, Paid Social, Organic Search, Referral, and remaining channels visible.'),
('On-site engagement','engagedSessions and engagementRate; optionally engagement duration. Treat these as behavior signals, not brand sentiment.'),
('Meaningful actions','Selected keyEvents and sessionKeyEventRate:event_name. Confirm the action fires on success and the key-event definition exists.'),
('Return behavior','Query newVsReturning with compatible metrics and show the returning segment. Never calculate returning users as total minus new users.'),
('Email','Campaign count, messages sent, delivery/bounces, unique clicking recipients, unsubscribes, and subscribed-audience size at capture. Retain opens as contextual evidence with privacy/filter caveats.'),
('Business outcome','Choose one primary action per project: confirmed subscription, enquiry, booking, download, or purchase. Distinguish action completion from durable retention.')],[104,400])
h('Email attribution: confirmed, indicative, and unknown')
p('The owner correctly notes that email often appears in Direct when attribution is lost. Tag outbound email links with source, medium=email, campaign, and content identifiers. Show tagged email sessions, provider click evidence, and Direct/unattributed sessions separately. A forwarded tagged link may retain its original label; tagging does not prove the current sender or recipient identity. [G4, M1-M3]')
p('Do not assign all Direct visits to email, or assume provider clicks equal GA4 sessions. Tracking restrictions, scanners, consent, blocked scripts, and repeated clicks can create differences. Never put subscriber addresses or personal identifiers in campaign URLs.')
h('Period and calculation rules')
p('Default to the course\'s Sunday-Saturday week in the GA4 property timezone. Issue a provisional report after close; refresh after the processing delay and record revisions. Query cumulative distinct users for the full interval instead of adding weekly users. Rates use their own denominators; zero denominators produce N/A. Growth from zero shows the absolute change. Audience size is a stock, not an additive weekly count. [G3, G5]','SmallX')

page(5,'The member and reviewer experience')
table(['Stage','Member experience'],[
('Connect once','Guided project setup, property/audience selection, timezone, primary outcome, campaign dates, brand brief, and collection-health check.'),
('See the week','Three familiar category panels; weekly and campaign-to-date switch; trend, source, period, freshness, and data-quality status on every measure.'),
('Understand it','What changed? Which evidence supports that observation? What else could explain it? Which brand element or strategic question needs attention?'),
('Plan one improvement','Choose one hypothesis, content/channel action, target measure, timeframe, and expected result. A blog post is only one possible activity.'),
('Reflect and submit','Record what was done, attach campaign links or peer feedback, compare with the previous hypothesis, and submit the weekly review.'),
('Review and export','Professor or administrator reviews evidence and comments. Export category charts, weekly data, and reflection history for progress and final reports.')],[108,396])
h('.net: preserve classroom structure')
p('Map university, professor, section, practicum dates, and membership to explicit access permissions. Students see their own projects and permitted aggregate comparisons; professors see assigned cohorts; administrators manage the system. Keep comparative benchmarking available, with the instructor controlling the official rubric and grade publication.')
p('The course describes a mean of 80 and 10 points per standard deviation. Before reimplementation, verify the actual Master JavaScript calculations, population/sample deviation, caps, weights, exclusions, and letter boundaries. A proposed compatibility formula is 80 + 10 x (value - mean) / deviation, but it must not be adopted as the verified production formula.')
p('Freeze cohort membership, eligible data, rubric version, and reporting period for published assessments. Missing connections are not zero performance. A zero deviation or insufficient valid cohort produces no comparative score until the instructor\'s fallback rule applies. Do not silently change grades when a delayed API report arrives.')
h('Pro: individual strategy and administrator review')
p('Default to the individual\'s baseline, agreed goals, and trend history. Keep optional comparable-group benchmarks configurable rather than compare unrelated businesses by raw volume. Removing or changing Pro\'s existing comparative scoring requires an explicit product decision. Company groups can be added later, outside this first release.')
p('AI output remains feedback. Official academic grades, completion approval, membership changes, and certificate eligibility remain under their established human-controlled workflows.','SmallX')

page(6,'Simple deployment, reusable product core')
p('<b>Proposed product architecture:</b> one hosted service owns collection, scheduled jobs, data normalization, reporting, and optional AI. Two small WordPress connectors provide account integration for .net and Pro. Later customers may use the standalone hosted portal with no WordPress dependency.')
table(['Component','Minimum responsibility'],[
('Hosted application','Projects, metric definitions, weekly reports, role-scoped access, brand briefs, reflection history, and exports.'),
('Collection workers','Read-only GA4 queries and email adapters. Central scheduler, bounded retries, pagination, quota handling, idempotent jobs, and reconnection prompts.'),
('Private data store','Tenant/project ownership; encrypted provider credentials; aggregate observations; snapshot revisions; audit and review records.'),
('SMM connector plugin','Map the signed-in member and authorized reviewer to service access. Render or link reports and synchronize only essential project/cohort membership.'),
('Customer website','Existing native GA4 integration, Google tag, or Tag Manager plus explicit outcome instrumentation. No SMM posting hooks or analytics-post records required.'),
('Westgate licensing','Entitlement checks, project/seat limits, feature flags, support diagnostics, and a read-only/export path when a license lapses. Commercial billing can follow the pilot.')],[115,389])
h('Deployment contract')
p('One versioned service release and one connector package configured for each portal. Keep core code shared. Use backward-compatible API versions, staged database migrations, feature flags, and a rollback package. Reporting jobs must run without customer-site visits. Do not add another per-customer PHP plugin to maintain.')
h('Minimum security and operating controls')
p('Google authorization uses read-only analytics access, server-side OAuth state validation, offline refresh, and revocation handling. Review production OAuth verification requirements early. Never request customer passwords or put shared secrets in URLs. Email integrations use the least access their provider supports; do not assume every provider offers read-only OAuth. [G6-G8]')
p('Tenant identity is derived from trusted authorization, not a browser-supplied user ID. Enforce project permissions on every request. Exclude passwords, subscriber lists, raw addresses, and unnecessary user-level events from the reporting store and AI inputs. Private responses require cache bypass and no-store behavior, validated against the actual hosting/CDN setup.')
p('Operational responsibility remains: hosting, backups with restore tests, job monitoring, API-change maintenance, credential rotation, and support. The service reduces customer deployment work; it does not eliminate product operations.','SmallX')

page(7,'Where AI adds practical value')
p('Keep collection, arithmetic, attribution, benchmarking, and permissions deterministic. AI provides an optional weekly coaching draft; reporting must work without it.')
table(['AI role','Required boundary'],[
('Weekly analyst','Cite the report period and metric evidence for every numerical claim. Compare like periods and surface missing sources before suggesting explanations.'),
('Brand alignment coach','Apply the owner-approved Story, Promise, Belief, and Trust rubric to the brand brief and voluntarily supplied content examples.'),
('Experiment planner','Suggest one manageable test, its primary measure, evidence needed, and review date. Label causal explanations as hypotheses.'),
('Reflection assistant','Ask focused SWOT and Closing-the-Loop questions. Help users explain their reasoning instead of producing an unexamined academic submission.'),
('Reviewer assistant','Summarize submitted evidence and flag incomplete or inconsistent reports. Reviewer retains control of assessment and approval.')],[123,381])
h('Example weekly review - invented numbers, not site results')
p('<b>Observation:</b> tagged email sessions increased from 20 to 32; confirmed sign-ups stayed at 4. Provider click evidence is unavailable this week. <b>Hypothesis:</b> the email attracted more visits, but the landing-page promise may not be clear enough. <b>Alternative:</b> repeat visitors or incomplete tracking could explain the change. <b>Next test:</b> clarify one call-to-action to match the brand promise, then compare sign-up rate over another complete reporting week.')
h('A bounded agent, not unattended account automation')
p('Limit tools to authorized aggregate reports, the approved theory rubric, and recommendation drafts. Version prompts, record evidence and model metadata, validate numbers, and cap usage. Retrieved content cannot authorize actions.')
p('The assistant cannot invent measurements, diagnose individual loyalty, present quadrant placement as fact, issue grades, send marketing emails, or publish changes. Action-taking features require a separate scope.')
h('What measurement still cannot promise')
p('APIs cannot repair absent historical tracking. GA4 still needs correct tagging and consent handling. Measurement Protocol supplements collection; it does not replace tagging. Social reach and in-platform engagement need separate provider data or manual evidence. [G9]')
p('Privacy prefetching can inflate Mailchimp opens; bots can inflate clicks. Prefer outcomes and available filtered evidence. Record filter settings and verify API availability during the pilot. [M1-M3]','SmallX')

page(8,'First release and migration gates')
table(['Phase','Deliverable and exit condition'],[
('1. Define and prove','Approve metric dictionary and rubric choices. Connect one GA4 property and one email audience in a sandbox. Reconcile dated results with source reports; validate permissions and OAuth readiness.'),
('2. Build the product core','Hosted weekly reports, source health, history, campaign-link builder, brand brief, one experiment log, exports, and job diagnostics. GA4 plus Mailchimp first; validated CSV fallback.'),
('3. Integrate both portals','One shared connector package with .net and Pro configurations. Validate student, assigned professor, Pro member, and administrator access without changing existing progress or certificates.'),
('4. Pilot and migrate','Pilot an authorized real project and a different website platform. Run at least two complete weekly cycles. Import selected legacy snapshots into a read-only archive; compare the new reports to their current sources.'),
('5. License-ready release','Document onboarding, tenant isolation, entitlement behavior, version support, backups, usage costs, and customer export/deletion. Add a standalone portal before selling beyond SMM.')],[117,387])
h('Required acceptance checks')
p('A website that publishes nothing still produces the next scheduled report. A non-WordPress website works through GA4 without an SMM installation. Revoked access shows Reconnect, never zero. An API error cannot hang a worker. Re-running a job cannot duplicate a week. Timezone and practicum-boundary cases reconcile.')
p('Every displayed metric identifies its period, definition version, source, and quality state. Cumulative unique users are queried correctly. Email and Direct remain distinct. Source-report reconciliation documents any identity, filtering, attribution, or processing differences. Failed data does not enter a published grade as zero.')
p('Cross-tenant and cross-professor access tests fail closed. Private reports are not cached publicly. User-supplied URLs and CSVs are validated; imports support review and rejection. Numeric AI claims match their evidence, and reporting/export still works when AI is disabled.')
h('Preserve before retiring')
p('Export existing weekly posts, relevant master data, course rubrics, and known historical reports before any change. Do not invoke date resets that delete old posts. Preserve historical grades and assessment versions. Keep the current Master plugin\'s non-analytics responsibilities until they are explicitly separated and regression-tested.')
p('UA history cannot be imported into GA4; retain whatever local snapshots or prior exports remain. Do not promise recovery of data that Google no longer provides. New GA4 history begins with available collection. Roll out behind a feature switch with a tested return to the previous display. [G10]','SmallX')

page(9,'Decisions, boundaries, and evidence')
h('Recommended defaults to make the pilot concrete')
p('Start with GA4 and Mailchimp because they match the reviewed system. Support generic CSV import for other email providers; add native adapters when demand is known. Use one project per member initially, optional AI coaching, and private account reports. Public certificate verification remains separate from analytics access.')
p('Retain .net instructor-controlled benchmarking. Decide Pro\'s score model before publishing new scores. A brand\'s baseline and purpose should remain visible alongside any class or peer comparison. The licensed offering should sell the Westgate methodology and guided improvement process, with reliable reporting as its foundation.')
h('Decisions needed before a build commitment')
p('1. Which owned project/property and email audience can be used as the pilot, and which non-WordPress platform should prove independence?<br/>2. Which existing grading rules must be preserved exactly, and which may be revised for GA4 and Pro?<br/>3. Should the first Westgate license include a standalone portal, or should that follow the two SMM integrations?')
p('Cost and schedule depend on these decisions, account/OAuth readiness, user/project volumes, connector access, reporting frequency, and AI usage. Produce a build estimate after the integration spike; no vendor spend or delivery date is committed here.','SmallX')
h('Reviewed first-party and supplied evidence')
p('Supplied 32-page AMTP white paper, especially pp. 5-15 and 29-31; supplied westgate-analytics source v3.0.1, especially cron, API, AJAX, and post-type files; previously saved Master entrypoint and professor analytics template; authenticated Mary-Kathryn weekly list/dashboard; .net student analytics and Pro member analytics. Selected course pages: 7.7 Progress Reports (both sites), Progress Reports exercise, Email Analysis, Closing the Loop, and Final Report (.net). This is a targeted discovery review, not a full course or security audit.','SmallX')
refs=[
('G1','GA4 API dimensions and metrics','https://developers.google.com/analytics/devguides/reporting/data/v1/api-schema'),
('G2','GA4 reporting API and response metadata','https://developers.google.com/analytics/devguides/reporting/data/v1/rest/v1beta/properties/runReport'),
('G3','Understand GA4 user metrics','https://support.google.com/analytics/answer/12253918'),
('G4','Default channel groups','https://support.google.com/analytics/answer/9756891'),
('G5','GA4 data freshness','https://support.google.com/analytics/answer/11198161'),
('G6','Server-side OAuth','https://developers.google.com/identity/protocols/oauth2/web-server'),
('G7','Google API scopes','https://developers.google.com/identity/protocols/oauth2/scopes'),
('G8','Sensitive scope verification','https://developers.google.com/identity/protocols/oauth2/production-readiness/sensitive-scope-verification'),
('G9','GA4 Measurement Protocol','https://developers.google.com/analytics/devguides/collection/protocol/ga4'),
('G10','UA migration and historical data','https://support.google.com/analytics/answer/14196937'),
('M1','Mailchimp campaign reporting API','https://mailchimp.com/developer/marketing/api/reports/get'),
('M2','Mailchimp Apple privacy FAQ','https://mailchimp.com/help/apple-privacy-faq/'),
('M3','Mailchimp bot filtering','https://mailchimp.com/help/about-bot-activity/'),
]
for key,label,url in refs: p(key+'  '+link(label,url),'SmallX')
p('Web documentation checked 17 October 2026. API feasibility and exact field/filter behavior remain subject to the pilot.','SmallX')

def footer(c,doc):
    c.setStrokeColor(colors.HexColor('#D4E0E5')); c.line(54,44,558,44)
    c.setFont('Helvetica',8); c.setFillColor(gray); c.drawString(54,30,'WESTGATE  /  PRACTICUM ANALYTICS  /  PROPOSED SCOPE')
    c.drawRightString(558,30,str(doc.page))
doc=SimpleDocTemplate(str(OUT),pagesize=(612,792),leftMargin=54,rightMargin=54,topMargin=43,bottomMargin=58,title='Westgate Practicum Analytics - Replacement Scope',author='Westgate Marketing')
doc.build(story,onFirstPage=footer,onLaterPages=footer)
print(OUT)
