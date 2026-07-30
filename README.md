# JPKCom Disable XML-RPC

**Plugin Name:** JPKCom Disable XML-RPC  
**Plugin URI:** https://github.com/JPKCom/jpkcom-disable-xmlrpc  
**Description:** Globally disable XML-RPC.  
**Version:** 1.0.9  
**Author:** Jean Pierre Kolb <jpk@jpkc.com>  
**Author URI:** https://www.jpkc.com  
**Contributors:** JPKCom  
**Tags:** Security, XML, RPC, API, Plugin  
**Requires at least:** 6.9  
**Tested up to:** 7.1  
**Requires PHP:** 8.3  
**Stable tag:** 1.0.9  
**License:** GPL-2.0-or-later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

Globally disable XML-RPC.


## Description

Disables the WordPress XML-RPC.

Requests to `xmlrpc.php` are answered with HTTP 403 and an XML-RPC fault, so clients get a parseable refusal rather than a silent empty response. The `xmlrpc_enabled` flag is off, the method list is empty, and instantiation of the XML-RPC server class is blocked as a last resort. The site also stops advertising the endpoint: neither the `X-Pingback` HTTP header nor a `<link rel="pingback">` tag is emitted any more.

**Please note:** pingbacks arrive over XML-RPC and are therefore no longer accepted. Because WordPress gates both mechanisms on the same `pings_open()` check, **trackbacks are refused as well**.


### Documentation

**API Documentation:** Complete PHPDoc-generated API documentation is available at:
[https://jpkcom.github.io/jpkcom-disable-xmlrpc/docs/](https://jpkcom.github.io/jpkcom-disable-xmlrpc/docs/)


## Installation

1. In your admin panel, go to 'Plugins' > and click the 'Add New' button.
2. Click Upload Plugin and 'Choose File', then select the Plugin's .zip file. Click 'Install Now'.
3. Click 'Activate' to use the plugin right away.


## Changelog

### 1.0.9
* Fixed: `xmlrpc.php` answered with a bare HTTP 200 and an empty body instead of the documented 403. With `XMLRPC_REQUEST` defined, `wp_die()` dispatches to `_xmlrpc_wp_die_handler()`, which writes nothing unless the global `$wp_xmlrpc_server` already exists — `xmlrpc.php` creates it after `init` — and never calls `status_header()`. The plugin now sends the 403 itself, together with a proper XML-RPC fault body so clients get a parseable answer
* Fixed: the site kept advertising the endpoint it refuses. WordPress sends `X-Pingback: <site>/xmlrpc.php` on singular views and themes emit `<link rel="pingback">`; both are gated on `pings_open()`, which this plugin did not filter. Note the side effect: `pings_open()` also governs `wp-trackback.php`, so trackbacks are now refused as well — deliberate, on the assumption that a site turning off XML-RPC does not want trackbacks either
* Changed: the request is identified by the `XMLRPC_REQUEST` constant, which `xmlrpc.php` defines before `wp-load.php` runs, instead of by `basename( $_SERVER['SCRIPT_FILENAME'] )`. The old check depended on how a given SAPI populates that variable and pushed a filesystem path through `sanitize_text_field()`, which strips tags and `%xx` sequences from it
* Changed: the guard runs at `init` priority 0, ahead of the updater bootstrap, so a refused request does no further work. `xmlrpc_enabled` and `xmlrpc_methods` moved from priority 1 to `PHP_INT_MAX`
* Added: `tests/test-hooks.php` covers the hook surface, the request detection and the fault body; CI runs it on every pull request and push to `main`

### 1.0.8
* Fixed: the update manifest no longer reports `network: true` for this plugin. The generator defaulted a missing `Network:` header to true, while WordPress' own default for a missing header is "not network-only". Metadata only — WordPress derives network-only from the plugin header via `is_network_only_plugin()`, not from the update manifest
* CI: the lint and guard workflow now also runs on pushes to `main`. It only covered pull requests, so a direct push with bypass rights skipped every check
* Changed: comments, workflow step names and CI output across the repository are now English throughout, and the developer notes in `CLAUDE.md` were translated and trimmed. No effect on the shipped plugin

### 1.0.7
* Changed: `Tested up to` raised to WordPress 7.1
* Changed: the bundled updater's runtime floor now matches the plugin's own minimum. It bailed out below WordPress 6.8 while the plugin header has required 6.9 for several releases, so the check could never fire on a supported installation
* CI: the release manifest's fallback values for `requires` and `tested` now say 6.9 and 7.1. They only apply when the README metadata cannot be read, but a stale fallback would have published a minimum the plugin no longer supports

### 1.0.6
* Added: plugin banners (`assets/banner-1544x500.avif`, `assets/banner-772x250.avif`) — a plain `#3c4955` surface with no lettering. The update manifest already advertised these two URLs, but nothing was published under them, so the plugin card in wp-admin had a broken banner

### 1.0.5
* CI: the release step no longer copies the staging directory into itself, so the ZIP has no empty `jpkcom-disable-xmlrpc/jpkcom-disable-xmlrpc/` folder
* CI: bumped the pinned GitHub Actions (checkout v7.0.1, setup-python v7.0.0, action-gh-release v3.0.2, fetch-metadata v3.1.0), still pinned to full commit SHAs
* CI: the release ZIP now excludes the development-only `tests/` and `tools/` directories
* CI: security and regression tests now run on every pull request, where a plugin has them

### 1.0.4
* Security: update packages are now verified *before* installation — the verified file is handed to WordPress instead of being downloaded a second time, so the bytes that were checked are the bytes that get installed
* Security: a missing or unfetchable SHA-256 checksum now aborts the update instead of installing unverified code (previously it silently skipped verification)
* Security: pinned every GitHub Action to a full commit SHA and added Dependabot with a 7-day cooldown, so a moved tag can no longer change the release build
* Security: tightened which download the updater claims, so sibling plugins cannot match each other's package
* Fixed: `sprintf()` calls in the updater bound named arguments to a variadic parameter, which raises `ArgumentCountError` on PHP 8.3
* Fixed: the "View Details" modal could fail with a `TypeError` when the manifest omitted `requires_plugins`
* Performance: a failed manifest fetch is now cached for an hour instead of being retried on every admin request
* Added: CI workflow on every pull request (PHP lint, named-argument check, YAML validation, action-pinning guard)

### 1.0.3
* Docs: linked the published PHPDoc API documentation

### 1.0.2
* Added secure self-hosted plugin updates via GitHub with SHA256 checksum verification
* Added an automated release workflow (builds the ZIP, generates the manifest and deploys to gh-pages on tag push)
* Raised the minimum WordPress version to 6.9 and "Tested up to" to WordPress 7.0
* Switched license metadata to the SPDX identifier `GPL-2.0-or-later` with the HTTPS license URI
* Added PHPDoc-generated API documentation, built and deployed to gh-pages on release
* Hardening: enabled `declare(strict_types=1)`, typed the callbacks, sanitized `$_SERVER` access and made the notice translatable

### 1.0.1
* Extended functionality

### 1.0.0
* Initial Release
