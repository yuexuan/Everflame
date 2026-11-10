# Traffic monitor range and node usage QA — 2026-11-09

final result: passed

## Visual target and evidence

- Supplied page: `docs/design/traffic-monitor-reference.png`, 1487×1058. Existing page structure retained; this iteration changes the lower panel to node usage and adds the approved custom-date interaction.
- Calendar source: the user-selected Tabler Two months demo, captured as `storage/design-previews/2026-11-09/tabler-reference.png` (1905×856). The calendar component itself is compared, not the surrounding documentation page.
- Real Smarty template with explicit sample data and the production CSS/scripts: `storage/design-previews/traffic-visual.html`. No sample usage was inserted into real accounts.
- Desktop CSS viewport1487×1058: `page-desktop.png`, `calendar-desktop.png`, `nodes-desktop.png` under the same evidence directory. Browser screenshot output1472×1047; page screenshot normalized to1487×1058 for comparison. Calendar source and implementation are compared at component level, allowing the requested purple theme, Chinese labels, shortcuts and footer.
- Mobile CSS viewport390×844: `calendar-mobile.png`, output375×812. DOM geometry independently confirms zero dialog horizontal overflow and application button bottom811 within844px viewport.
- Full-view comparison: `page-comparison.jpg`. Focused calendar comparison: `calendar-comparison.jpg`. Node-table evidence: `nodes-desktop.png`. The screenshots were opened and compared together; no full-page stitched screenshot is used for layout approval because fixed navigation duplicated during stitching.

## Findings, fixes and rechecks

1. [P2, fixed] Default current-month selection showed a completely disabled future month. The final view shows previous/current months; screenshot confirms October/November for November9. Direct date input, month/year navigation and both date endpoints remain usable.
2. [P2, fixed] On narrow screens confirmation controls required scrolling past two calendars. Footer is now sticky inside the native dialog, keeping cancel/apply visible while calendars scroll; post-fix mobile screenshot and geometry confirm this.
3. [P1, fixed] Smarty interpreted regex repetition braces in the HTML pattern, causing native validation to reject valid dates. Pattern now uses explicit digit positions. A rendered-form validity assertion was added; real browser submission navigated to `?range=yesterday&start=2026-11-08&end=2026-11-08`.
4. [Test-fixture issue, fixed] Early fixture omitted the unrelated footer modal markup but retained its initialization. Restoring the real modal markup removed the fixture-only console exception. Fresh final preview logs contain no application errors. The installed translation extension emitted unrelated errors on earlier tabs.
5. [P2, fixed during live check] A selected weekend used the calendar default red instead of the product primary. Added explicit weekend-selected/hover token bindings and bumped the stylesheet URL to20261109c. Post-deploy computed color for selected November1 is rgb(86,100,217), matching the product purple. Final live screenshot was recaptured after this correction.
6. Final comparison found no remaining actionable P0/P1/P2 mismatch in the scoped changes.

## Required visual surfaces

- Typography: existing theme font stack, clear heading/input/button hierarchy, Chinese dates and labels legible at desktop and390px mobile. No clipped text in the dialog.
- Layout: existing navigation, overview and trend arrangement retained. Additional selected-range metadata and honest history notes intentionally add height. Dual months side by side on desktop and vertically stacked on mobile; scrolling remains contained.
- Colors: existing purple primary, pale purple selected range, white surfaces and muted explanatory text. Calendar native CSS variables bind to existing theme tokens.
- Assets: existing logo and Tabler icons retained. Calendar is the real Tabler/Vanilla Calendar Pro runtime; chart is local Chart.js.
- Content: single combined-transfer curve reflects available data; node totals and percentages refer to that same period. Today/yesterday boundary descriptions explicitly differ from rolling24h. No fabricated upload/download history or node status indicators.

## Functional verification

-32 PHP checks: midnight, today/yesterday/rolling24h, calendar-month clamping, leap year, invalid/future/oversized range, node merging, sorting, zero/missing data and matching totals.
-23 DOM checks with actual pinned Tabler1.7.4 and Vanilla Calendar Pro3.4.0, including compiled native HTML form validation.
-MySQL transaction checks: raw totals, node merge, exact exclusive-midnight ownership, account isolation, future and uncharged exclusions. All test usage rolled back.
-Browser: today/yesterday descriptions, three/six-month shortcuts, disabled submission for oversized range, real valid form submission, Escape dismissal, desktop/mobile layout and current/prior month default checked.
-Previous browser timeout was resolved using the documented browser API through the existing logged-in Chrome session. In-app browser remained unreliable.

## Deployment

Visual gate passed. App replaced on2026-11-09 at22:25 Asia/Shanghai using the existing compose.yaml and compose.cloudflare.yaml configuration. Cloudflare and observer containers were left running. Final style correction deployed at22:39 Asia/Shanghai. Live image matches everflame-app:latest: sha256:b8d73b8aaddddc697c238898378116372a287b4ea794abe87ab81ff8b29b9a5c.

Rollback image: everflame-app:before-traffic-20261109. Incremental schema preparation reran idempotently; no usage history removed.

User restored an expired login session. Authenticated HTTPS verification then passed:
- /user/traffic displays real node names, descending usage, percentages and a matching period total in the trend and node table.
- Today shows2026-11-09 00:00 through the current time, hourly.
- Selecting Yesterday in the real dialog and applying navigated to range=yesterday; displayed interval2026-11-08 00:00 through2026-11-09 00:00.
- Custom November1–9 displayed November1 00:00 through current time on November9.
- Live calendar screenshot: storage/design-previews/2026-11-09/live-calendar.png. Browser console contained no application errors.
- Production-side read-only reports for today/yesterday/24h/7d/30d/3m/6m all matched node totals to trend totals. Agent heartbeat, desired-state, usage and observer probe requests continued to return200 after deployment; application log showed no fatal exceptions or500 responses during checks.
- Local preview server was stopped after verification. No authentication workaround or test usage was retained.

---

## Prior iteration record (2026-11-08; does not approve current changes)

# Traffic monitor design QA — 2026-11-08

final result: passed

## Visual target and state

- Selected source: `docs/design/traffic-monitor-reference.png` (1487 × 1058 pixels).
- Browser-rendered implementation: `storage/design-previews/2026-11-08/traffic-fixture-final.png` (1487 × 1058 pixels).
- CSS viewport 1487 × 1058; deviceScaleFactor 1. The final CDP capture matches the reference dimensions with no density scaling. An earlier high-level browser capture was 1472 × 1047 and was replaced for the final comparison.
- Full comparison: `storage/design-previews/2026-11-08/traffic-comparison-final.jpg`.
- Focused summary/chart comparison: `storage/design-previews/2026-11-08/traffic-comparison-focus.jpg`.
- State: light theme, seven-day range, 2026-11-08. Visual component fixture uses sample account totals and seven chronological daily samples, rendered by the real Smarty template and local Chart.js. It does not write test data to the database. Actual authenticated account remains an honest empty-history state.
- Actual mobile evidence: `storage/design-previews/2026-11-08/traffic-mobile-final.jpg`, 390 CSS pixels wide. Long table scroll is contained within its panel; document width equals viewport width.

## Findings and comparison history

1. Initial full-view comparison found chart text smaller than the selected mock's hierarchy. Increased chart font to 14px, metadata/range controls to 14px, page heading to 30px and summary values to 38px; retained the existing product font stack. Post-fix full and focused captures show clear labels, values and aligned controls.
2. [P2, fixed] Existing fixed footer obscured chart controls in the mobile full-page capture. This screen now uses a footer in document flow. Its inherited negative row margins also caused four pixels of page overflow; scoped row margins removed that overflow. Post-fix mobile capture shows the complete chart controls and footer below the content, with no document overflow.
3. Final full-view and focused comparisons: no remaining actionable P0/P1/P2 findings.

## Required fidelity surfaces

- Typography: existing Inter/Segoe UI/Microsoft YaHei stack; 14px primary explanatory text, 16px summary labels, 21px section titles, 30px page heading and 38px values. Mobile values step down to 30px. Wrapping and table alignment checked.
- Layout: existing 250px sidebar and 70px top bar; three equal summary columns on desktop and one column on mobile; 20–22px panel gaps; responsive chart and range links. Additional billing explanation and seven daily rows require scrolling, which is intentional.
- Colors: existing `--tblr-primary`, `--ef-bg`, `--ef-surface`, `--ef-text`, `--ef-muted`, `--ef-border` tokens. The chart uses the product's existing purple/indigo primary rather than introducing the mock's brighter violet. Native progress elements use the same primary.
- Assets: supplied EverFlame logo and existing Font Awesome/Tabler icon fonts; no generated substitute or handcrafted chart drawing. Chart rendering, axes, fill, legend and tooltip are Chart.js 4.5.1.
- Copy/data: preview badge removed for the functional page. Real historical storage supports only total transmission, so the line represents that total and the lower table shows daily/hourly usage. Historical upload/download splitting and per-node usage were intentionally not fabricated. Account summary values remain billed amounts, explicitly distinguished from raw transmission.

## Functional verification

- Authenticated node listing restored before and after app container recreation; grouping data comes from the new controller, not temporary template-only deployment.
- Sidebar entry opens the authenticated traffic route.
- 24-hour/7-day/30-day controls navigate successfully and return 24/7/30 points and matching table rows. Narrow-screen navigation also checked.
- Chart.js component fixture renders a smooth line, shaded area, axes and a working data-point tooltip. Real account empty-history state shows the appropriate message and table values.
- Application console checked; no application errors. One installed translation extension emitted an unrelated extension error on the fixture tab.
- Four isolated PHPUnit tests (18 assertions) cover midnight/month boundaries, missing versus zero usage, invalid ranges and malformed data. PHP syntax and JavaScript syntax checks pass.

## Remaining limits

- This account has no historical traffic samples; there is no claim that a real node has reported traffic during QA.
- Source screenshot covers desktop only. Mobile is an adaptation to the existing product shell, independently inspected at 390px.
- No P3 changes are required for handoff.
