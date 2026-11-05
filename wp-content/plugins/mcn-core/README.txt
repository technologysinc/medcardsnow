MCN Core - Med Cards Now site features as independent modules
===============================================================

WHAT IT IS
All site code that used to live in the child theme's functions.php now lives
here, split into 21 modules (Tools > MCN Modules). The code inside each module
is unchanged. The theme's functions.php keeps only theme styling.

WHY IT IS SAFER
- A module with an error (for example a typo after an edit) stops on its own.
  Everything else keeps running, and the admin sees which one and why.
- If a running module throws an error, the page is not taken down: the error
  is logged, a filter keeps its original value, and half-written output from
  that call is thrown away.
- Modules that depend on a broken or switched-off module are held back.
  Payments never run with half their pricing logic missing.
- MCN Core waits automatically while the old functions.php is still active,
  so installing it can never cause a "Cannot redeclare function" crash.

DEPLOY (no downtime)
1. Download a backup copy of the child theme's functions.php.
2. Plugins > Add New > Upload Plugin > mcn-core.zip > Activate.
   MCN Core sees the old functions.php and waits (admin notice). The site
   keeps running exactly as before.
3. Replace the child theme's functions.php with the slim version.
   On the next page load the modules switch on automatically.
4. Purge LiteSpeed Cache (and OPcache if available).
5. Check Tools > MCN Modules: every module should say "Running".
6. Test: magic-link login, profile form prices (CA / IL / OK), checkout.

ROLLBACK
Put the backup functions.php back. MCN Core detects the old code and stands
down by itself. (Or deactivate the plugin.)

EMERGENCY SWITCH
Add  define('MCN_CORE_SAFE_MODE', true);  to wp-config.php to stop every
module without deleting anything. Remove the line to switch them back on.

EDITING SAFELY
- Edit only files in modules/. Do not edit mcn-core.php or includes/.
- Prefer a staging copy. The Authorize.Net SDK, Dompdf and card images still
  load from the child theme folder (custom-authorize-payment/, images/).
- PHP cannot catch every error. Defining the same function twice, running out
  of memory or timing out still stop a page. WordPress recovery mode then
  pauses the plugin and emails the site admin.

CHANGES IN 1.1.0
- The state and address picked on step 1 of the profile form are saved as
  soon as the patient clicks Next. Plans, prices and checkout on later steps
  now match the state the patient chose (before, they kept using the state
  from the registration page).
- Fixed the address saver, which read the wrong form fields and stored the
  "Do you have a pet?" and a health answer as address line 1 and country.
  It also no longer writes patient addresses into debug.log.
- Checkout reads the ZIP, state and country from the correct form fields.
- The checkout shipping state no longer replaces the patient's card state.
- New: Tools > Repair Addresses. Restores addresses saved wrongly before
  this update from each patient's own form entry. Dry run first.

CHANGES IN 1.2.0
- Staging safety: on a staging copy of the site (a "staging." address or
  WP_ENVIRONMENT_TYPE "staging"), real card charges are blocked while
  Authorize.Net is in Production mode. Use Sandbox mode with sandbox keys to
  test there. (Override, not recommended:
  define('MCN_ALLOW_LIVE_PAYMENTS_ON_STAGING', true);)
- The Authorize.Net library is loaded through Composer's autoloader or, if
  that fails, the library's own autoloader. If neither works, checkout shows
  "Payment Configuration Error" instead of failing silently.
- New "Checks" section on Tools > MCN Modules: whether the payment library
  loads, which library files are present, gateway mode, staging or live.
- If a module error happens during an AJAX request (like checkout), the
  browser now gets a clear "Technical Problem" message instead of a
  misleading "card not approved".
- Checkout shows State Packages labels ("1 Year") instead of keys ("1-year").
