# JPKCom Disable XML-RPC

**Plugin Name:** JPKCom Disable XML-RPC  
**Plugin URI:** https://github.com/JPKCom/jpkcom-disable-xmlrpc  
**Description:** Globally disable XML-RPC.  
**Version:** 1.0.3  
**Author:** Jean Pierre Kolb <jpk@jpkc.com>  
**Author URI:** https://www.jpkc.com  
**Contributors:** JPKCom  
**Tags:** Security, XML, RPC, API, Plugin  
**Requires at least:** 6.9  
**Tested up to:** 7.0  
**Requires PHP:** 8.3  
**Stable tag:** 1.0.3  
**License:** GPL-2.0-or-later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

Globally disable XML-RPC.


## Description

Disables the WordPress XML-RPC.


### Documentation

**API Documentation:** Complete PHPDoc-generated API documentation is available at:
[https://jpkcom.github.io/jpkcom-disable-xmlrpc/docs/](https://jpkcom.github.io/jpkcom-disable-xmlrpc/docs/)


## Installation

1. In your admin panel, go to 'Plugins' > and click the 'Add New' button.
2. Click Upload Plugin and 'Choose File', then select the Plugin's .zip file. Click 'Install Now'.
3. Click 'Activate' to use the plugin right away.


## Changelog

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
