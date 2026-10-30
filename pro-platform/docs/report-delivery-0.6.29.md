# Support mailbox sender correction — 0.6.29-preview

Changes From and explicit Reply-To for analytics report/Review email and team
invitations to Social Media Magnet Pro <support@socialmediamagnet.net>, as requested
by the site owner after a reply to the former invitations mailbox bounced.

The legacy WGPP_Account::INVITATION_FROM constant name is retained for compatibility,
but its value is now the support mailbox. Display name remains Social Media Magnet
Pro. Sender filters remain scoped to the individual outgoing message and are removed
after success or failure. Unrelated WordPress emails retain their configured sender.

Existing queues and schedules use the current sender when next processed; no jobs,
recipients or scheduling rules are changed and previously sent emails are not resent.
No DNS, mailbox, SMTP authentication or transport settings are modified. A transport
that independently forces a From address can still override the application value;
explicit Reply-To directs replies to support. Verify an actual received message's
headers after installation. No customer email was sent during development tests.

This is a narrow sender correction on top of 0.6.28-preview. It does not resolve the
separately reported PDF layout/content deficiencies; that report revision remains pending.
