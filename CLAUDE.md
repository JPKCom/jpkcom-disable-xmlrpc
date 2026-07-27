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
| `JPKCOM_DISABLE_XMLRPC_VERSION` | `'1.0.4'` | Plugin version (sync with header/README/phpdoc.xml) |

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

**Supply-chain: GitHub Actions sind auf Commit-SHAs gepinnt.** Alle `uses:`-Zeilen in `.github/workflows/` referenzieren einen 40-stelligen Commit-SHA statt eines Tags (`@v4`), mit der Version als Kommentar dahinter. Grund: ein Tag ist ein beweglicher Zeiger und lässt sich umhängen, ein SHA nicht. Da dieser Workflow die Plugin-ZIP **und** die SHA256-Summe erzeugt, der der Auto-Updater vertraut, würde eine kompromittierte Action ein manipuliertes ZIP samt passender Prüfsumme ausliefern — die Prüfsumme sichert den Transportweg, das Pinning den Build. `.github/dependabot.yml` hält die Pins wöchentlich aktuell (ein gesammelter PR). Beim Aktualisieren immer SHA *und* Versionskommentar zusammen ändern.

**CI & Dependabot-Auto-Merge.** Zwei zusätzliche Workflows:

- `.github/workflows/ci.yml` — läuft auf jedem `pull_request`. Prüft: `php -l` über alle PHP-Dateien; ungültige benannte Argumente an internen PHP-Funktionen (fängt die Klasse `sprintf(format:, values:)` → `ArgumentCountError`, die `php -l` nicht sieht); YAML-Validität aller `.github`-Dateien; und dass jede Action auf einem 40-stelligen Commit-SHA gepinnt ist (beide YAML-Formen, `uses:` und `- uses:`).
- `.github/workflows/dependabot-auto-merge.yml` — merged Dependabot-PRs automatisch, aber nur `semver-patch` und `semver-minor`. Major-Updates bekommen stattdessen einen Kommentar und bleiben manuell. Greift nur bei PRs von `dependabot[bot]` aus diesem Repo, nie aus Forks.

> **Zwei Repo-Einstellungen sind Voraussetzung, sonst ist der Auto-Merge wirkungslos oder gefährlich:**
> 1. **„Allow auto-merge"** muss in den Repo-Settings aktiv sein.
> 2. Der Branch-Schutz muss den CI-Job als **Required status check** führen (`CI / Lint & Guards`). Fehlt das, merged `gh pr merge --auto` **sofort** — es gibt dann nichts, worauf es warten müsste, und die CI wäre reine Dekoration.

Zusammen mit `cooldown: default-days: 7` in der `dependabot.yml` heißt das: kein Action-Release wird in seiner ersten Woche übernommen, patch/minor laufen danach automatisch durch (sofern CI grün), major bleibt eine bewusste Entscheidung.


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
