=== WebP Image Optimizer ===
Tags: webp, image optimization, compress images, performance, page speed
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

Creates smaller WebP copies of your JPEG, PNG and GIF images and serves them to browsers that support WebP. Original files are never modified.

== Description ==

* Converts every image size WordPress creates (full size + all thumbnails) to WebP.
* New uploads are converted automatically, right after the upload finishes.
* Bulk converter for your existing Media Library (pause / resume / stop, crash-safe).
* Serves WebP with <picture> tags (works on any host, with page caches and CDNs)
  or with server rewrite rules (.htaccess, Apache/LiteSpeed).
* Optional: lower WordPress' maximum upload size and JPEG thumbnail quality so
  future uploads are smaller too.
* Media Library column showing savings, with Convert / Re-convert / Remove buttons.
* WP-CLI: `wp webp-optimizer bulk|stats|status|remove`.

= Stability by design =

* Originals are only ever read, never written. Turning the plugin off instantly
  returns the site to its previous state.
* WebP files are written to a temp file, validated, then renamed into place, so
  a half-written file can never be served.
* A WebP that isn't smaller than the original is discarded.
* Memory is checked before an image is loaded; huge images are skipped instead
  of crashing PHP.
* If a conversion ever kills a PHP request (memory/time limit), the image is
  marked and skipped next time, so it can't break a bulk run or uploads twice.
* Upload conversion runs after the upload response is sent: it never slows down
  or breaks an upload.
* Animated GIF/PNG are kept as-is (so they keep animating). EXIF rotation is
  applied so photos are never shown sideways. Imagick keeps color profiles.
* Picture-tag delivery keeps the original <img> byte-for-byte, never touches
  images inside scripts, styles, comments, <noscript>, <textarea> or existing
  <picture> blocks, and returns the page unchanged on any error.
* When an image is deleted (or WordPress removes a thumbnail), its WebP copy is
  deleted too.

== Installation ==

1. Plugins > Add New > Upload Plugin, choose webp-image-optimizer.zip, Install, Activate.
2. Go to Media > WebP Optimizer.
3. Click "Start" under Bulk convert to convert your existing images.
4. If you use a page cache (WP Rocket, LiteSpeed Cache, W3TC, Cloudflare APO…),
   clear it once so cached pages pick up the WebP images.

== Frequently Asked Questions ==

= Which delivery mode should I use? =
"<picture> tags" (the default) unless you have a reason not to. It works on every
host, with Cloudflare and other CDNs, and with page caching, because every visitor
gets the same HTML. Browsers that can't show WebP use the original <img>.

"Server rewrite" keeps the same image URLs and also converts CSS background
images, but needs Apache/LiteSpeed (nginx users must add the shown config) and a
CDN that respects "Vary: Accept" (Cloudflare's free plan does not).

= Will it change my layout? =
No. The <picture> wrapper is made invisible to CSS layout (display: contents), so
images sit exactly where they did. If a theme uses a very specific CSS selector
like "figure > img", add the image's class to "Exclude images".

= Some images say "Kept original" =
The WebP would have been bigger than the original (common for tiny or already
very optimized PNGs), the image is animated, or it was too large for the
server's memory. The original is served; nothing is broken.

= Does it work with lazy-loading plugins? =
Yes. Native lazy loading needs nothing. For JavaScript lazy loaders that use
data-src / data-srcset, the WebP source mirrors those attributes; if a lazy
loader doesn't understand <picture>, the browser simply shows the original.

= What happens when I deactivate or delete the plugin? =
Deactivating removes server rules and stops <picture> output; the site serves
originals again immediately. Deleting also removes settings and status data, and
(optionally, see Advanced) every WebP file it created.

= Server requirements =
PHP 7.4+, WordPress 6.2+, and Imagick or GD with WebP support (almost all hosts).
The settings page shows which library is available.

== Developer notes ==

Filters:
* `wio_quality` (int $quality, int $attachment_id)
* `wio_skip_attachment` (bool $skip, int $attachment_id)
* `wio_enable_picture` (bool $enable) – turn off <picture> output per request
* `wio_picture_html` (string $html, string $original_img_tag)
* `wio_finish_request` (bool) – return false to stop the early response flush

Add the class `wio-skip` (or the attribute `data-wio-skip`) to any <img> to leave it alone.
Append `?nowebp` to any URL to see the page without WebP (debugging).

WebP files are stored next to the original as `name.jpg.webp`.

== Changelog ==

= 1.0.0 =
* First release.
