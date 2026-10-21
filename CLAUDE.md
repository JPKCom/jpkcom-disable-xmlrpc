# JPKCom Disable XML-RPC – Developer Reference

## Plugin Overview

Globally disables WordPress XML-RPC: rejects requests to `xmlrpc.php` with a 403 and an XML-RPC fault body, turns off the `xmlrpc_enabled` flag, empties the method list, blocks instantiation of the XML-RPC server class, and stops the site advertising the endpoint via `X-Pingback` and `<link rel="pingback">`.

- **Text Domain:** `jpkcom-disable-xmlrpc` (used by `esc_html__()` in the fault body and the `wp_die()` notices; no header declared, defaults to slug)
- **Min PHP:** 8.3 | **Min WP:** 6.9
- **Network:** not network-only (no `Network:` header)

---

## Architecture

```
Main file (jpkcom-disable-xmlrpc.php)
├── declare(strict_types=1)
├── Plugin header
├── JPKCOM_DISABLE_XMLRPC_VERSION constant
├── init @ priority 5: boot JPKComGitPluginUpdater
├── jpkcom_disable_xmlrpc_is_xmlrpc_request()  ← defined( 'XMLRPC_REQUEST' )
├── jpkcom_disable_xmlrpc_fault_xml()          ← methodResponse fault, code 403
├── init @ priority 0                → 403 + XML-RPC fault, then exit
├── add_filter xmlrpc_enabled        → __return_false      (PHP_INT_MAX)
├── add_filter xmlrpc_methods        → __return_empty_array (PHP_INT_MAX)
├── add_filter pings_open            → __return_false      (PHP_INT_MAX)
└── add_filter wp_xmlrpc_server_class → status_header(403) + wp_die(), typed : never
```

Both helpers are `function_exists()`-guarded so a duplicated plugin copy cannot trigger a redeclare fatal, and both are called by the tests.

---

## Behaviour

| Hook | Type | Effect |
|------|------|--------|
| `init` (prio 0) | action | On an XML-RPC request: `nocache_headers()`, `status_header( 403 )`, `Content-Type: text/xml`, the fault body, `exit` |
| `xmlrpc_enabled` | filter (`PHP_INT_MAX`) | Force-disables XML-RPC |
| `xmlrpc_methods` | filter (`PHP_INT_MAX`) | Empties the registered method list |
| `pings_open` | filter (`PHP_INT_MAX`) | Stops the endpoint being advertised |
| `wp_xmlrpc_server_class` | filter | Last resort: `status_header( 403 )` + `wp_die()` — typed `: never` |

Priority 0 puts the guard ahead of the updater bootstrap at priority 5, so a refused request does no further work.

---

## Two things that are easy to get wrong here

Both were measured against WordPress 7.0.2 for 1.0.9.

**`wp_die()` cannot answer an XML-RPC request at `init`.** With `XMLRPC_REQUEST` defined, `wp_die()` dispatches to `_xmlrpc_wp_die_handler()`. That handler writes its fault only `if ( $wp_xmlrpc_server )` — and `xmlrpc.php` creates that global on line 82, *after* `init`. It also never calls `status_header()`. Up to 1.0.8 the endpoint therefore answered with a bare **HTTP 200 and an empty body** while README and this file promised a 403. Measured: `HTTP 200, 0 Bytes` before, `HTTP 403, 262 Bytes` after. So the status and body are written here directly, and the fault is emitted as a real `methodResponse` fault so XML-RPC clients get something parseable instead of an HTML error page.

**Disabling the endpoint is not the same as not advertising it.** WordPress sends `X-Pingback: <site>/xmlrpc.php` on singular views (`class-wp.php:545`) and themes emit `<link rel="pingback">` (bootscore does, in `inc/template-functions.php`). Both are gated on `pings_open()`. Without that filter the site kept publishing the address of an endpoint it refuses — measurable with this plugin active and `jpkcom-disable-comments` switched off, which is what had been masking it.

> **Side effect, deliberate:** `pings_open()` also governs `wp-trackback.php`, so trackbacks are refused too. Trackbacks are a separate mechanism from XML-RPC. The call was made on the assumption that a site turning off XML-RPC does not want trackbacks either — if that ever needs separating, filter `wp_headers` to drop `X-Pingback` instead and leave `pings_open` alone, accepting that theme-side pingback links stay.

**Why not `$_SERVER['SCRIPT_FILENAME']`.** `xmlrpc.php` defines `XMLRPC_REQUEST` on line 13, before `wp-load.php`, so the constant is set for every request that reaches the endpoint and does not depend on how a SAPI populates `$_SERVER`. 1.0.8 additionally pushed the path through `sanitize_text_field()`, which strips tags and `%xx` sequences — it alters a filesystem path rather than securing it.

---

## Constants

| Constant | Value | Purpose |
|----------|-------|---------|
| `JPKCOM_DISABLE_XMLRPC_VERSION` | matches the header `Version:` | Plugin version (sync with header/README/phpdoc.xml) |

---

## File Structure

```
jpkcom-disable-xmlrpc/
├── jpkcom-disable-xmlrpc.php     ← Main: header, constant, filters/actions, updater bootstrap
├── includes/
│   └── class-plugin-updater.php  ← GitHub auto-updater (namespace: JPKComDisableXmlrpcGitUpdate)
├── .github/workflows/release.yml ← Build ZIP, manifest, PHPDoc, deploy to gh-pages (on tag push)
├── phpdoc.xml                    ← phpDocumentor config
├── README.md                     ← Public readme (source for the WP plugin modal)
├── CLAUDE.md                     ← This file
├── LICENSE                       ← GPL-2.0-or-later
└── .gitignore
```

---

## Plugin Updater

**Family updates (updater 1.5.0).** WordPress only runs the code of active plugins, so the updater copy inside an inactive JPKCom plugin never runs and that plugin was never offered an update. Since 1.5.0 every copy registers in a shared registry (`$GLOBALS['jpkcom_git_updater_registry']`); the copy with the highest `UPDATER_VERSION` is elected leader (ties: the first registered) and serves every installed JPKCom plugin, active or inactive. A plugin counts as JPKCom when its Plugin URI is `https://github.com/JPKCom/<slug>` and it sits in the canonical `<slug>/<slug>.php` layout. The leader provides the update rows with icons, "View details" and the SHA-256 gate (`upgrader_pre_download` at priority `PHP_INT_MAX - 100`, which also hashes a package an earlier filter supplied instead of trusting it). All other 1.5+ copies stay passive; a plugin whose pre-1.5 copy is loaded keeps serving itself and the leader leaves it alone.
- Package URLs are removed before the update transient is stored (`pre_set_site_transient_update_plugins`) and re-injected on read. A request in which no 1.5+ copy is loaded therefore finds an empty package and core refuses the update ("Update package not available") instead of installing an unverified ZIP.
- Version-control checkouts (`.git`, `.svn`, `.hg`, `.bzr`; core's `automatic_updates_is_vcs_checkout` filter applies) and renamed folders (e.g. `<slug>-main`) are told about a new release but never get a package (core shows "Automatic update is unavailable for this plugin", and the gate refuses an update through a stale row with core's `no_package` error): an update would wipe the working tree, or unpack into `<slug>/` and orphan the active plugin.
- Installs without a plugin name (`wp plugin install <url>`, the details modal) are claimed by the asset's file name `<slug>.zip`, as in 1.4, so every URL GitHub serves for a release asset (`releases/latest/download/`, any case, `www.`, `http`, a query string) must match the manifest checksum.
- Manifests are fetched while rendering update data only for users with `update_plugins`, in cron and in WP-CLI; an interactive request fetches at most 5 uncached manifests, and a host breaker (`jpk_git_update_host_fail`, 15 min) stops further reads after a transport error, a 429/5xx or a fetch slower than 3 s. Installs, updates and the details modal always may fetch. The manifest cache (`jpk_git_update_<md5(slug)>`, shared with older copies) lives 24–30 h, spread per plugin.
- Multisite: at least one JPKCom plugin with updater 1.5+ must be network-active or active on the main site. A plugin that is active only on a subsite cannot serve updates (nothing is offered; nothing unverified is installed).
- Covered by `tests/test-updater-family.php`, identical in every JPKCom plugin (170 checks, red against 1.4.0). Measured live on WordPress 7.1.3, single site and multisite.

- **Namespace:** `JPKComDisableXmlrpcGitUpdate\JPKComGitPluginUpdater`
- **Manifest URL:** `https://jpkcom.github.io/jpkcom-disable-xmlrpc/plugin_jpkcom-disable-xmlrpc.json`
- Shared JPKCom updater (downstream copy of upstream `jpkcom-post-filter`; do not edit per-plugin). SHA256 verification, `wp_safe_remote_get()`, URL validation, race-condition lock, 24 h cache, timing-safe `hash_equals()`. Checksum verification is **mandatory**: a missing or unfetchable `checksum_sha256` aborts the update instead of installing unverified code. The verified temp file is returned from `upgrader_pre_download`, so WordPress installs exactly the bytes that were hashed (no second download). Failed manifest fetches are negatively cached for 1 h.
- Hooks: `plugins_api`, `site_transient_update_plugins`, `pre_set_site_transient_update_plugins` (priority `PHP_INT_MAX`), `upgrader_process_complete`, `deleted_plugin`, `upgrader_pre_download` (priority `PHP_INT_MAX - 100`).

---

## Release Workflow

**Actions are pinned to commit SHAs.** Every `uses:` line in `.github/workflows/` references a 40-character commit SHA instead of a tag (`@v4`), with the version as a trailing comment. A tag is a movable pointer and can be repointed; a SHA cannot. Since the release workflow builds the plugin ZIP **and** the SHA256 checksum the auto-updater trusts, a compromised action would ship a tampered ZIP together with a matching checksum — the checksum secures the transport, the pinning secures the build. `.github/dependabot.yml` keeps the pins current weekly in one combined PR; when updating, always change the SHA *and* the version comment together.

**CI** (`.github/workflows/ci.yml`) runs on every pull request *and* on every push to `main` — a required status check only covers pull requests, so a direct push with bypass rights would otherwise skip the checks entirely. It runs `php -l` over all PHP files; flags invalid named arguments to internal PHP functions (catches `sprintf(format:, values:)` → `ArgumentCountError`, which `php -l` does not see); validates the YAML of every `.github` file; asserts every action is pinned to a 40-character commit SHA; and executes `tests/test-*.php` where present.

**Dependabot auto-merge** (`.github/workflows/dependabot-auto-merge.yml`) merges only `semver-patch` and `semver-minor`, and only PRs from `dependabot[bot]` in this repo — never from forks. Major updates get a comment and stay manual. Two repo settings are prerequisites, otherwise this is useless or outright dangerous: "Allow auto-merge" must be enabled, and branch protection must list `CI / Lint & Guards` as a **required status check** — without it `gh pr merge --auto` merges *immediately*, since there is nothing left to wait for. Together with `cooldown: default-days: 7` no action release is adopted during its first week.

Triggered by **pushing a `v*` tag**; the workflow creates the GitHub release automatically. Pipeline: setup PHP/Python/Pandoc/GraphViz → README metadata → slug-named ZIP → SHA256 → upload ZIP + `.sha256` → `plugin_<slug>.json` manifest → PHPDoc → deploy to `gh-pages`.

---

## Security Checklist

- `declare(strict_types=1)` in every PHP file
- No `$_SERVER` in the decision path — the `XMLRPC_REQUEST` constant cannot be spoofed by a request
- Notices escaped and translatable (`esc_html__()`); the fault body is literals plus one escaped string
- The endpoint is no longer advertised (`pings_open`), so it is not discoverable from the site itself
- Updater: SHA256 verification + URL validation (audited separately)

---

## Tests

`tests/test-hooks.php` runs standalone: it stubs the WordPress functions the main file touches at load time, requires the plugin, then calls the two helpers directly. It asserts hook names and priorities, that `XMLRPC_REQUEST` (and *not* `SCRIPT_FILENAME`) decides, and that the fault body parses as XML with `faultCode` 403. 15 cases; 6 of them fail against 1.0.8. CI runs it on every pull request and push to `main`.

```bash
php tests/test-hooks.php   # exit 0 = green
```

The `init` callback itself is deliberately not invoked — it terminates the request. That is why the decision and the body live in named helpers: everything except the three header calls is reachable from a test.

---

## Release Checklist

1. Bump version in: header `Version:` + `Stable tag:`, `JPKCOM_DISABLE_XMLRPC_VERSION`, `README.md`, `phpdoc.xml`
2. Add a `### x.y.z` block to `## Changelog` in `README.md`
3. Run `php tests/test-hooks.php`
4. Commit, tag `vx.y.z`, push the tag → the workflow builds and publishes everything
