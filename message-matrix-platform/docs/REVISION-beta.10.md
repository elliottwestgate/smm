# Admin foundation revision

Prepared 10/24/2026. Version 0.1.0-beta.10. Local implementation; not yet installed or published.

## Changes

- A top-level Social Media Magnet Pro menu replaces the visible Tools entry while preserving the legacy route.
- Overview lists actual company workspaces, entitlement state, saved record counts and last update in Central Time.
- Company selection opens the selected workspace without changing its records.
- Users & Access supports adding existing users, editing company roles and removing company access. It reuses Store validation for stale revisions, seats, owner protection and assigned assets.
- Site administrators can manage a company's team even when that company is read-only. The exception is limited to administrator team management; ordinary members still require current entitlement for writes.
- Act as User uses the installed User Switching plugin's permission-checked, nonce-secured helper. It does not create an independent impersonation mechanism. Its existing switch-back control restores the administrator.
- Campaigns & Files exposes saved copy, review feedback, status, Content Focus, Vertical and Bucket, plus protected company document downloads.
- Company headers show a saved logo when available.
- Settings allows explicit selection of the shortcode workspace page. Automatic detection only succeeds for one unambiguous matching page. Existing settings keys are preserved.
- The most recent 100 administrative changes made through these controls are recorded. This is not a complete security audit log.

## Validation

- 25 admin navigation, authorization, role, owner, seat, assigned-asset, concurrency and workspace-link assertions using WordPress/database doubles.
- 41 domain checks; 11 access/concurrency checks; 30 source assertions; JavaScript interface checks.
- User Switching 1.12.2's installed source was inspected: `maybe_switch_url(WP_User)` checks switching permission and delegates to a nonce-secured URL.
- No actual account roles were changed during validation. No customer invitations were sent. Runtime WordPress UAT and upgrade verification remain pending.

## Pending development

Invitations/reinvitations, ownership transfer, full courseware entitlements and contextual references, guided onboarding, sequential completion, consolidated Publishing Calendar Pro, automatic copy/image generation, full campaign packaging and the later Pro Analytics/publishing integrations. Current existing roles Contributor and Viewer are retained for compatibility.

## Publication authorization

The repository is public. After repository ownership verification and removal of unnecessary staging-location metadata, the user explicitly authorized public publication on 10/24/2026. Publish this source revision before building or deploying; customer records, private uploads and credentials are excluded.
