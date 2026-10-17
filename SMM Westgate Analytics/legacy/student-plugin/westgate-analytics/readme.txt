=== Social Media Magnet - (Website Analytics) ===
Author: Westgate Marketing
Author URI: https://westgatemarketing.net
Requires at least: 4.9.8
Tested up to: 5.4
Requires PHP: 7.0
License: GPLv2+

Social Media Magnet - (Website Analytics)

== Description ==

Plugin description: Google Analytics plugin designed for integration with Social Media Magnet.


== Changelog ==
Version 3.0.1
<ul>
<li>Weekly analytics post generation bug fixing & improvements.</li>
</ul>

Version 3.0.0
<ul>
<li>Auto update practicum dates from socialmediamagnet.net.</li>
<li>Changed weekly post generation logic.</li>
<li>Ability to connect website with socialmediamagnet.net using Access Token.</li>
<li>Added Mailchimp Email Sent Count.</li>
</ul>

Version 2.10.5
<ul>
<li>Reduced Mailchimp API requests on page load and while weekly analytics posts are generated.</li>
</ul>

Version 2.10.4
<ul>
<li>Added "Create Weekly Analytics Post" tab to create post by entering secret key if weekly analytics post is missing for that week.</li>
</ul>

Version 2.10.3
<ul>
<li>Changed Google App Credentials for Google Analytics Authentication</li>
</ul>

Version 2.10.2
<ul>
<li>Fixed missing table columns data for Google Analytics section in Weekly Analytics posts and fixed sorting of Campaign IDs in Mailchimp settings section.</li>
</ul>

Version 2.10.1
<ul>
<li>Modified CRON working to auto update Campaign list based on Practicum Start Date & Practicum End Date.</li>
</ul>

Version 2.10
<ul>
<li>Update MailChimp Campaign list based on Practicum Start Date & Practicum End Date.</li>
</ul>

Version 2.9
<ul>
<li>Removed temporary Weekly Analytics clear button / functionality.</li>
</ul>

Version 2.8
<ul>
<li>Added PHP coding for WP cron function to remove Weekly Analytics (*unless current Date after Practicum Start Date).</li>
<li>Updated PHP coding for WP cron function to check Practicum Start Date / current Date.</li>
<li>Added temporary PHP coding to Settings page to remove all Weekly Analytics / Sync.</li>
<li>Modified PHP coding for Settings page to add temporary Weekly Analytics clear button.</li>
<li>Modified PHP coding for Dashboard to add checks for Practicum Start Date / current Date.</li>
<li>Modified PHP coding for WP cron function to work on Sunday versus, Monday.</li>
</ul>

Version 2.7
<ul>
<li>Updated front-end CSS stylesheet to accommodate more WP Themes for viewing Website Analytics.</li>
</ul>

Version 2.6
<ul>
<li>Fixed glitch with PHP coding for WP cron function in reference to Google Analytics [ Dimensions / Metrics ]</li>
</ul>

Version 2.5
<ul>
<li>Modified PHP coding for WP cron function in reference to Google Analytics [ Dimensions / Metrics ].</li>
<li>Modified PHP coding for Dashboard in reference to Google Analytics [ Dimensions / Metrics ].</li>
<li>Updated PHP coding for Google Analytics (* Direct / Social / Organic Search / Referrals ) - Views.</li>
</ul>

Version 2.4
<ul>
<li>Fixed Weekly Analytics not showing correctly in left-side Admin Menu.</li>
<li>Updated PHP coding for Website Analytics custom post type.</li>
</ul>

Version 2.3
<ul>
<li>Added error message for no Practicum Start / End Date(s) to Dashboard.</li>
<li>Edited Dashboard to add error catch for no Practicum Start / End Date(s).</li>
<li>Added success message for saved MailChimp settings for Settings page.</li>
<li>Added success message for saved Practicum date settings for Settings page.</li>
</ul>

Version 2.2
<ul>
<li>Edited PHP coding for Email Campaign ID array to remove empty / duplicate array values.</li>
<li>Updated PHP coding for MailChimp Campaign Opens / Click Through - Views.</li>
</ul>

Version 2.1
<ul>
<li>Modified MailChimp Campaign Opens / Click Through - Views.</li>
<li>Edited settings page - MailChimp Settings to add 4 additional Email Campaign ID fields.</li>
<li>Modified PHP coding to loop through all Email Campaign ID fields.</li>
<li>Modified PHP coding to total MailChimp Campaign Opens / Click Through for Dashboard.</li>
<li>Modified PHP coding to total MailChimp Campaign Opens / Click Through for WP cron function.</li>
</ul>

Version 2.0
<ul>
<li>Edited westgate-analytics.php file to correct jQuery UI reference to work with HTTPS.</li>
<li>Removed success array statement after re-authenticating Google Analytics Authentication.</li>
</ul>

Version 1.9
<ul>
<li>Edited MailChimp settings to add Campaign ID input for (*8 weeks).</li>
<li>Edited Dashboard to accumulate data for MailChimp Campaign Opens.</li>
<li>Edited Dashboard to accumulate data for MailChimp Campaign Click Through.</li>
<li>Added PHP code to determine Week # from current date minus class start date.</li>
<li>Edited MailChimp Campaign Opens / Click Through views to add PHP for Week #.</li>
<li>Created 2 new data variables for MailChimp in order to show cumulative data.</li>
<li>Edited WP cron function to work with Campaign ID(s) based on Week #.</li>
<li>Added front-end CSS stylesheet for viewing / styling Website Analytics.</li>
</ul>

Version 1.8
<ul>
<li>Added Social Media Magnet plugin update icon.</li>
<li>Edited WP cron function to remove any website analytics before class start date.</li>
<li>Added check for FireFox browser for class start / end date (*datepicker).</li>
<li>Edited PHP code for data retrieval to allow for cumulative data instead of weekly.</li>
<li>Edited WP cron function to remove any website analytics if no class start date.</li>
</ul>