# 0.1.1-preview validation

- PHP 8.3.6: both plugin files pass syntax checks.
- Twelve local-preview behavior checks pass using WordPress API doubles: campaign save, both provider selections, stale revision, null metrics, reflection save/reload, Sunday validation, cross-user boundaries, invalid dates and anonymous denial.
- Nine hosted-service contract tests passed in the preceding foundation build; local preview does not need that service.
- ZIP has one plugin root folder with the bootstrap PHP, local adapter, CSS and installation README.
- Actual WordPress activation, hosting/CDN cache bypass, theme styling and browser testing are pending owner upload.

Preview data remains separate from legacy analytics. The new plugin does not register activation/deactivation mutations, cron jobs, page creation, membership changes or legacy deactivation. Deactivate to roll back; preview records remain.
