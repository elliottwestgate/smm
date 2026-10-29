# Pro Analytics 0.6.33-preview

Replace the generic Brand text inside the emailed Report Brand Axis hub with the existing company logo, preserving proportions and using the existing embedded image resource. Logo dimensions are bounded to 48 x 38 PDF points and centered on the hub. Draw the hub after the schematic line so it does not cross the company mark. A missing/unsupported logo retains the Brand text fallback. Header logo, provider data, permissions, schedules and email sender are unchanged.

Validation: existing delivery-layout, report-delivery and asset-version regressions; render and inspect the V1Moto first page with its existing logo and verify the PDF still contains four selectable-text pages. No new provider collection. Install as a normal plugin update after staging verification.
