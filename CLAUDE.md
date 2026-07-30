# JPKCom Disable XML-RPC – Developer Reference

## Plugin Overview

Globally disables WordPress XML-RPC: turns off the `xmlrpc_enabled` flag, empties the method list, blocks instantiation of the XML-RPC server class, and rejects direct requests to `xmlrpc.php` with a 403.

- **Text Domain:** `jpkcom-disable-xmlrpc` (used by `esc_html__()` in the `wp_die()` notices; no header declared, defaults to slug)
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
├── add_filter xmlrpc_enabled        → __return_false
├── add_filter xmlrpc_methods        → __return_empty_array
├── add_filter wp_xmlrpc_server_class → ( string $class ): never { wp_die(403) }
└── add_action init                  → block direct xmlrpc.php requests (403)
```

---

## Behaviour

| Hook | Type | Effect |
|------|------|--------|
| `xmlrpc_enabled` | filter (prio 1) | Force-disables XML-RPC |
| `xmlrpc_methods` | filter (prio 1) | Empties the registered method list |
| `wp_xmlrpc_server_class` | filter | `wp_die()` (403) — typed `: never`, never returns |
| `init` | action | If `basename($_SERVER['SCRIPT_FILENAME']) === 'xmlrpc.php'` → `wp_die()` (403) |

`$_SERVER['SCRIPT_FILENAME']` is read through `sanitize_text_field( wp_unslash() )`. The `wp_die()` notices are translatable via `esc_html__()`.

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

- **Namespace:** `JPKComDisableXmlrpcGitUpdate\JPKComGitPluginUpdater`
- **Manifest URL:** `https://jpkcom.github.io/jpkcom-disable-xmlrpc/plugin_jpkcom-disable-xmlrpc.json`
- Shared JPKCom updater (downstream copy of upstream `jpkcom-post-filter`; do not edit per-plugin). SHA256 verification, `wp_safe_remote_get()`, URL validation, race-condition lock, 24 h cache, timing-safe `hash_equals()`. Checksum verification is **mandatory**: a missing or unfetchable `checksum_sha256` aborts the update instead of installing unverified code. The verified temp file is returned from `upgrader_pre_download`, so WordPress installs exactly the bytes that were hashed (no second download). Failed manifest fetches are negatively cached for 1 h.
- Hooks: `plugins_api`, `site_transient_update_plugins`, `upgrader_process_complete`, `upgrader_pre_download`.

---

## Release Workflow

**Actions are pinned to commit SHAs.** Every `uses:` line in `.github/workflows/` references a 40-character commit SHA instead of a tag (`@v4`), with the version as a trailing comment. A tag is a movable pointer and can be repointed; a SHA cannot. Since the release workflow builds the plugin ZIP **and** the SHA256 checksum the auto-updater trusts, a compromised action would ship a tampered ZIP together with a matching checksum — the checksum secures the transport, the pinning secures the build. `.github/dependabot.yml` keeps the pins current weekly in one combined PR; when updating, always change the SHA *and* the version comment together.

**CI** (`.github/workflows/ci.yml`) runs on every pull request *and* on every push to `main` — a required status check only covers pull requests, so a direct push with bypass rights would otherwise skip the checks entirely. It runs `php -l` over all PHP files; flags invalid named arguments to internal PHP functions (catches `sprintf(format:, values:)` → `ArgumentCountError`, which `php -l` does not see); validates the YAML of every `.github` file; asserts every action is pinned to a 40-character commit SHA; and executes `tests/test-*.php` where present.

**Dependabot auto-merge** (`.github/workflows/dependabot-auto-merge.yml`) merges only `semver-patch` and `semver-minor`, and only PRs from `dependabot[bot]` in this repo — never from forks. Major updates get a comment and stay manual. Two repo settings are prerequisites, otherwise this is useless or outright dangerous: "Allow auto-merge" must be enabled, and branch protection must list `CI / Lint & Guards` as a **required status check** — without it `gh pr merge --auto` merges *immediately*, since there is nothing left to wait for. Together with `cooldown: default-days: 7` no action release is adopted during its first week.

Triggered by **pushing a `v*` tag**; the workflow creates the GitHub release automatically. Pipeline: setup PHP/Python/Pandoc/GraphViz → README metadata → slug-named ZIP → SHA256 → upload ZIP + `.sha256` → `plugin_<slug>.json` manifest → PHPDoc → deploy to `gh-pages`.

---

## Security Checklist

- `declare(strict_types=1)` in every PHP file
- `$_SERVER` access sanitized (`wp_unslash` + `sanitize_text_field`)
- `wp_die()` notices escaped/translatable (`esc_html__()`)
- Updater: SHA256 verification + URL validation (audited separately)

---

## Release Checklist

1. Bump version in: header `Version:` + `Stable tag:`, `JPKCOM_DISABLE_XMLRPC_VERSION`, `README.md`, `phpdoc.xml`
2. Add a `### x.y.z` block to `## Changelog` in `README.md`
3. Commit, tag `vx.y.z`, push the tag → the workflow builds and publishes everything
