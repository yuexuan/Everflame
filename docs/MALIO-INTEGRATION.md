# Everflame / Malio integration

## Sources and boundaries

- Core: https://github.com/Anankke/SSPanel-Uim commit c2ab3003cc4bf70237eb6ae82b3c65f31fb1f051 (master).
- Theme: https://github.com/GeekQuerxy/SSPanel-Malio commit e79b874edeb1117cd2e4627287ade6627ff74778.
- `public/theme/malio/`: complete original static assets, plus `css/everflame.css`, `js/everflame.js` and a static copy of original staff credits.
- `resources/theme-sources/malio/templates/`: unmodified original templates for provenance and subsequent porting. This directory is outside the selectable theme root and is never executed.
- `resources/theme-sources/malio/README.upstream.md` and `.malio_config.example.php`: installation/configuration evidence only; never include the old config in the running application.
- `resources/views/malio/`: current-core template contracts with a Malio sidebar, original CSS/assets, responsive navigation adapter and billing UI disabled. Admin templates remain current Tabler templates. No old PHP business implementation was imported.

## Enable

Install/configure SSPanel using its normal documented procedure (PHP >=8.2, Composer, database, Redis, etc.). In the deployment's untracked `config/.config.php`, set `$_ENV['theme'] = 'malio';`. Existing users retain their saved theme; choose malio in user settings (or use the existing administrator user editor). Clear the normal Smarty compiled cache after deployment. No database migration or custom theme loader is needed: `src/Services/View.php` selects `resources/views/<theme>/` already.

Do not copy old salts, payment settings, database config or the old `.malio_config.php`. Phase one has no payment configuration. Product data still comes from current ProductController; purchase buttons, balance topup, order creation and invoice payment views show instructions to contact administrators. Core payment routes/code are retained; this is a theme UI restriction, not a server-side prohibition on payment API access. Administrator user/product/traffic/expiry controls are unchanged.

## Compatibility decisions

Current core uses Smarty 5.8.4, HTMX, public_setting, /user/product and /user/server. Old Malio uses i18n/I18N, malio_config, Shop, /user/shop, /user/node, old client/protocol fields and obsolete AJAX response conventions. We retained current auth/captcha/MFA/WebAuthn, subscription, node, product and form scripts instead of importing old controllers or simulating their results. Original navigation structure and purple assets are used with an independent CSS/JS bridge. No old bootstrap 4 JavaScript is loaded alongside current Bootstrap 5.

**This is an initial compatibility integration, not a completed faithful port of every original Malio screen.** Dashboard/auth/product/node content currently retains current-core markup. Original dashboard widgets, rich landing page, tutorial flow, translations, plan presentation and original auth layouts still require individual visual/template porting. Old relay/shared-account/new-user-class=-1/custom-port/automatic-renewal workflows are not enabled. Their old backend dependencies must not be imported just to display a page.

## Validation (2026-10-28)

- 98 active templates compiled with actual Smarty v5.8.4 (the locked version), using portable PHP 8.4.27. Zero compiler errors.
- All literal template includes and local /theme, /assets, /images references in active templates resolve.
- Login/register/MFA/password reset/user dashboard (including subscription)/product/server pages retain current controller variables and endpoint contracts. Compilation and source-level checks only; these are not authenticated end-to-end results.
- PHP syntax checks pass for the unchanged View loader and route file.
- No production config, credentials, database or Redis were available. Full application boot, real login/registration, subscription generation, node data, external CDN availability, browser appearance and Bootstrap CSS interaction remain unverified. No new test framework was introduced.

## Attribution and permission

Core LICENSE is unchanged. Theme README expressly requires preserving Powered by SSPANEL and all staff information, and separately discusses Theme by editXY. Both credits are retained; the original staff document is linked from /staff, while current upstream staff credits remain. There is no complete theme LICENSE in the source root. README links to external installation and authorization wikis, but the repository alone does not establish permission to redistribute or operate commercially. Verify the rightsholder's terms before deployment/distribution; do not infer that the core MIT license licenses Malio assets. Per-asset third-party notices remain intact.

## Upstream maintenance

Keep master as the upstream-only baseline. On a clean checkout:

```sh
git fetch upstream
git switch master
git merge --ff-only upstream/master
git switch dev
git merge master
```

Resolve upstream template contract changes against `resources/views/tabler/` and port them to `resources/views/malio/`; recompile templates and run the real deployment smoke tests before publishing. Everflame owns `resources/views/malio/`, `public/theme/malio/`, `resources/theme-sources/malio/` and this document. All app/src/config/database business files remain upstream. The copied admin/current templates need deliberate updates when upstream changes; there is no automatic fallback concealing missing templates.

### Docker follow-up (2026-10-28)

The separated Docker deployment is now running; see `docs/DOCKER.md`. With the real database initialized through upstream migrations, login/register GET requests and theme CSS return HTTP 200. Runtime dependency requirements pass. Authenticated user screens and original-layout fidelity remain unverified; the earlier limitations still apply to those screens.


### MySQL follow-up (2026-10-28)

Local deployment now uses MySQL 8.4.13 with a clean schema baseline. `docs/DOCKER.md` supersedes the earlier MariaDB deployment notes. The MySQL schema snapshot and configurable DB driver/port are additional Everflame-maintained compatibility files. Cookie domain normalization for localhost/host:port and empty redirect fallback were also fixed after real authentication testing; retain these small diffs when syncing upstream. Secure/HttpOnly flags are unchanged. Login POST returns HX-Redirect=/user, and authenticated dashboard, product, server and admin pages return HTTP 200 against the real MySQL database. User/project data was explicitly authorized to be discarded; the administrator was recreated with the previously supplied credentials.
