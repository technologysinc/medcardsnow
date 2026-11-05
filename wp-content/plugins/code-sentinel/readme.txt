=== Code Sentinel ===
Contributors: codesentinel
Tags: security, debug, scanner, errors, malware, code quality
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Deep scanner that finds active and passive issues inside your plugins, themes and scripts — each with the exact file path and line — and captures live PHP & JavaScript runtime errors.

== Description ==

Code Sentinel inspects every PHP and JavaScript file (plus .htaccess and config files) in your plugins, themes, must-use plugins and uploads folder and reports problems with the **exact file path and line number**.

It looks for both:

* **Active issues** — things that are breaking (or about to break) the site right now: PHP syntax / parse errors, functions removed in modern PHP (fatal "undefined function"), duplicate function/class declarations across files ("Cannot redeclare"), missing include/require targets, output before headers / BOM, and errors captured live while WordPress runs.
* **Passive issues** — latent risks: SQL injection, XSS, file inclusion, command execution, object injection, SSRF, open redirects, weak crypto, hard-coded secrets and API keys, missing nonce/capability checks on AJAX & REST endpoints, insecure .htaccess rules, performance traps, deprecated WordPress & jQuery APIs, and malware / web-shell / obfuscation indicators.

It also verifies plugin and WordPress **core files against the official wordpress.org checksums** to detect tampered or injected files, flags available updates, and surfaces exposed backup/log/secret files.

= Key features =

* Exact `path/to/file.php:line` for every finding, with a one-click copy button and the offending code snippet.
* Comment- and string-aware matching (via PHP's tokenizer) to keep false positives low.
* Real `php -l` syntax checking when the host allows it, with a tokenizer-based fallback.
* Live runtime capture of PHP errors, warnings, notices, deprecations and fatal shutdowns, plus WordPress `_deprecated_*` / `_doing_it_wrong` notices and front-end JavaScript errors.
* Severity ranking (Critical → Info), grouping by plugin/theme, filtering and search.
* wordpress.org checksum integrity verification for plugins and core.
* CSV and JSON export; daily background scan with email alerts.
* WP-CLI: `wp code-sentinel scan --target=active_plugins --format=table`.

Findings are heuristic. A match is a place to review, not proof of a vulnerability; always confirm before changing third-party code.

== Installation ==

1. Upload the `code-sentinel` folder to `/wp-content/plugins/`, or install the zip via Plugins → Add New → Upload.
2. Activate the plugin.
3. Go to **Tools → Code Sentinel**, choose a target and click **Run scan**.
4. (Optional) In **Settings**, enable live PHP/JS error capture and the daily scan.

== Frequently Asked Questions ==

= Does it change any of my files? =
No. Code Sentinel only reads files and reports issues. It never edits plugin or theme code.

= Where is scan data stored? =
In a private, access-protected folder inside your uploads directory (`uploads/code-sentinel/`). Uninstalling removes it.

= Will the runtime error capture slow my site? =
It adds a lightweight error handler that only records issues originating in `wp-content`, de-duplicated and written once per request on shutdown. You can turn it off in Settings.

== Changelog ==

= 1.0.0 =
* Initial release.
