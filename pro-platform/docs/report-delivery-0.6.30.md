# Pro Analytics 0.6.30-preview

Requested sender: Social Media Magnet Pro <elliott@westgatemarketing.net>.

Applies to team invitations, account email-change verification and manual/scheduled Report or saved Review delivery. Both From and Reply-To use the requested mailbox. Existing queues resolve the sender at send time. No recipient, schedule, company access or report-content changes. Unrelated WordPress email is unaffected.

Validation: account mail, report delivery and asset-version regression suites; PHP syntax on changed PHP files; release ZIP manifest hashes. Mail tests use doubles and send no real messages. Host transport may independently override the From address; actual delivery and reply behavior require a received email after installation.

Install as an update to the existing plugin, staging first, preserving settings. This release does not implement the pending emailed PDF redesign.
