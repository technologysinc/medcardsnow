=== TableCraft – Advanced Tables for Elementor ===
Tags: elementor, tables, acf, google sheets, data table, responsive table
Requires at least: 6.3
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build customizable, responsive Elementor tables with design presets, search, sorting, pagination, ACF Repeater data, and public Google Sheets CSV feeds.

== Description ==

TableCraft adds a dedicated TableCraft Table widget to Elementor.

Features:
* Six preloaded table templates: Pricing plans, Feature comparison, Product catalog, Team / business directory, Event schedule, and Inventory list.
* Nine ready-to-use design presets: Modern, Minimal, Striped, Pricing, Comparison, Compact, Dark, Glass, and Enterprise.
* Editable manual/static table data: customize any preloaded template by editing its column headings and properties, then replace its sample rows with your own cell data. Leave custom row content empty to retain the template examples.
* ACF integration for ordinary post fields (single-row output) and ACF Repeater fields (multiple rows), with per-column field mapping.
* Google Sheets CSV integration for sheets shared as anyone-with-link viewer or published to the web.
* Search, sortable columns, pagination, row count, sticky headers, and mobile stacked-card mode.
* Styling controls for header/body/accent colors, borders, cell padding, font sizes, and corner radius.
* Server-side caching for Google Sheets data.
* Semantic table markup, keyboard-operable sorting, and safe output escaping.

== Installation ==

1. Upload the `tablecraft-elementor` folder to `/wp-content/plugins/`, or upload the ZIP from Plugins > Add New > Upload Plugin.
2. Activate TableCraft and make sure Elementor is active.
3. Edit a page with Elementor and search for "TableCraft Table".
4. Configure columns, data source, features, and styling.

== ACF setup ==

1. Install and activate Advanced Custom Fields.
2. For standard fields, choose ACF Fields (single row) and enter each ACF field name in the column's Data key / ACF sub-field setting.
3. For repeating records, create an ACF Repeater, add sub-fields, choose ACF Repeater (multiple rows), enter the Repeater field name, and map each column to its matching sub-field name.
4. Leave the ACF post ID empty to use the current post, or provide a specific post ID.

== Google Sheets setup ==

1. Open the spreadsheet in Google Sheets.
2. Share it as "Anyone with the link – Viewer", or use File > Share > Publish to web and choose CSV.
3. Paste the spreadsheet URL into the TableCraft widget. For a published CSV URL, paste the full published URL.
4. Enable "Use first row as headers" if the first row contains your column names.
5. Optionally enable column formatting to apply alignment, width, badge, or link display settings from the Table columns repeater.
6. Adjust the cache duration as required.

This release supports public/viewable CSV feeds only. It does not implement Google OAuth for private spreadsheets. Do not publish confidential information.

== Frequently Asked Questions ==

= Does TableCraft require Elementor Pro? =
No. It registers a standard Elementor widget and does not require Elementor Pro.

= Does ACF integration support repeater fields? =
Yes. It reads ACF Repeater rows and maps configured columns to sub-field names. ACF must be active.

= Can I connect a private Google Sheet? =
Not in this version. The sheet must be viewable by anyone with the link or published as a CSV feed. OAuth support can be added in a future release.

= Are table features dependent on a third-party JavaScript library? =
No. Search, sorting, and pagination are implemented in a small vanilla JavaScript file.

== Changelog ==

= 1.0.1 =
* Fixed preloaded templates so edited column headings are reflected in the table.
* Added an always-visible editable row-content field for all static templates.
* Preserved existing template sample rows when custom row content is left empty.

= 1.0.0 =
* Initial release.
