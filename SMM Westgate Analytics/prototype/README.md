# Pro upload preview — 0.1.1-preview

A self-contained WordPress workflow preview is now available in `plugin/westgate-practicum-pro`. Upload its release ZIP, activate, then use **Tools → Practicum Pro Preview** as an administrator. No service credentials are required in the default local preview mode. Aladdin's website and Constant Contact are preselected; actual campaign dates and primary action remain owner-entered.

Local preview records are stored per user in separate WordPress metadata. This is a temporary workflow adapter for review, not a change to the planned platform-independent hosted architecture. Configuring all three service constants switches to the service backend; it does not migrate preview records automatically.

Read the plugin README.txt for installation, scope and rollback. Keep the current member analytics page and legacy plugins in place. Live GA4, Constant Contact and Mailchimp reporting, scoring and certificates are not implemented by this ZIP.

---

# Westgate Practicum — Pro-first development foundation

Status: 0.1.0-dev. First implementation slice; not a production release or live analytics replacement. No existing site, plugin, membership, grade, or certificate has been changed.

## Included

- Shared service foundation using Python's standard library and SQLite.
- One campaign per Pro user, saved independently of website platform and publishing.
- Campaign dates, reporting timezone, primary meaningful action, and Story/Promise/Belief/Trust brief.
- Awareness, Acquisition, and Retention report contract with explicit unconnected/null metrics.
- Weekly evidence, alignment, SWOT, experiment and success-measure entries.
- Pro WordPress shortcode portal, with server-to-server authentication and user-scoped access.
- Optimistic campaign revision checks, short-lived signed requests, replay protection and tenant isolation.

The pilot target is **aladdincleans.com**, connected after the Pro workflow and provider adapters are ready. Aladdin uses Constant Contact; Mailchimp is also required for the first release. No dates or primary conversion are assumed for the actual pilot. The test fixture uses illustrative dates only.

## Development service

Use Python 3.11+ with timezone data. No third-party dependencies are required for the foundation tests:

```sh
cd service
python3 -m unittest -v
```

Set `WESTGATE_TENANT_KEYS` to a JSON object mapping portal IDs to independent random secrets of at least 32 characters. Set `WESTGATE_DATABASE` to an absolute private writable database path. Keep secrets out of source control. Provision the database directory with restricted permissions (owner only); encrypted production storage and managed key rotation remain release work.

`python3 app.py` binds a development server to 127.0.0.1:8787. It is not an internet-facing production server. For staging, use a production WSGI server behind HTTPS, with request limits, rate limits, monitoring, backups and restore verification. No hosted infrastructure has been provisioned in this slice.

## WordPress staging setup

Copy `plugin/westgate-practicum-pro` into the staging WordPress plugins directory. The package uses a distinct folder and does not deactivate or modify the legacy Master or analytics plugins. Do not replace the existing member analytics page yet.

Configure these constants in the staging server's private configuration:

- `WESTGATE_ANALYTICS_URL`: dedicated HTTPS service origin, no path or query.
- `WESTGATE_ANALYTICS_TENANT`: the configured Pro tenant ID.
- `WESTGATE_ANALYTICS_KEY`: the matching private service secret.

Create a separate private development page containing `[westgate_practicum_pro]`. Administrators can use it; other staging users require the `westgate_practicum_access` capability. Grant this explicitly to selected testers only. No automatic WishList membership mapping is provided yet. Authentication derives the principal from the current WordPress user, never a form-supplied owner ID. Each portal requires its own tenant and secret. Keep tenant IDs stable because they namespace ownership.

Test actual hosting/CDN bypass for this page, logged-out requests, and separate user accounts. The plugin sets private/no-cache headers on ordinary shortcode pages; a managed hosting cache may ignore these. Page-builder placements require explicit private-route cache configuration. No production cache audit has been performed.

## Not implemented

GA4 / Constant Contact / Mailchimp authorization or collection, CSV imports, scheduler, observed metrics, date-selection UI, trend charts, exports, reviewer access, membership adapters, comparative grading, AI coaching, licensing/billing, and .net cohort workflows. Connection buttons are deliberately absent until the adapters exist. No fabricated metrics, example performance scores, or simulated connection success are shown.

The current dashboard displays metric availability only. It does not compute weekly or cumulative results yet. Reflections are append-only in this slice and are not official course submissions. Separate production-grade audit, retention, deletion/export and support workflows remain required.

## Validation completed and remaining

Nine service tests pass: provider selection, invalid setup, revision conflicts, user/tenant boundaries, unsigned requests, expiry/replay, signature method binding, null metrics, and reflection scope/week validation. Python compilation passes. PHP 8.3 syntax checks now pass for both plugin PHP files. Twelve local-preview behavior checks pass with WordPress API doubles. Actual WordPress and browser validation remains pending; this is an uploadable workflow preview, not a production analytics release.

Next: validate the PHP portal on staging; add a stored-observation data contract, revisioned date windows and provider adapters; reconcile GA4 and Constant Contact using Aladdin; validate Mailchimp separately; then add visualization, exports, and reviewer workflow. Preserve the existing Pro scoring policy until explicitly resolved. Tune .net after Pro is working.
