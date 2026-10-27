=== Medcardsnow ===
Contributors: M Nasir Rasheed
Tags: medical, payments, authorize.net, signup, login, shortcode
Requires at least: 5.0
Tested up to: 6.6
Stable tag: 2.8.1
License: GPLv2 or later

Shortcode: [omc_medical_card]
Shortcodes: [omc_thank_you], [omc_payment_error] — place these on your
"/thank-you" and "/payment-error" pages.

== What this plugin does ==
Multi-step signup → login → medical questionnaire → plan/duration →
review → payment via Authorize.Net SIM (Server Integration Method).

== Payment mode ==
Go to OMC Medical Card → Settings in wp-admin and check "Go LIVE" once
your real Authorize.Net API Login ID and Transaction Key are entered.
Until then, every transaction runs against Authorize.Net's TEST gateway
and no real money moves, regardless of what the merchant account's
credentials are. Current status is shown on the settings page.

== Security notes ==
- The amount charged is always computed on the server from the saved
  application (state, plan, duration, add-ons, coupon). The price is
  never trusted from the browser.
- The Authorize.Net Transaction Key never leaves the server.
- Payment/application logs are written outside the publicly served
  wp-content tree (wp-content/uploads/omc-logs/, access-denied via
  .htaccess) and card data is scrubbed before anything is written.
- The applicant's medical questionnaire (conditions, allergies, DOB,
  signature, consent) is saved to the database as soon as they reach
  the review step and is included in the admin notification email on
  successful payment.

== Known limitation ==
- The ID-document upload field on the medical form is not yet wired up
  to any storage — it's collected in the browser but not uploaded.
  Add a dedicated, size/type-validated upload handler before relying
  on it.
- Authorize.Net has marked the SIM integration method (used here) as
  deprecated in favor of Accept Hosted / Accept.js. SIM still works
  today but has no committed end-of-life date from Authorize.Net —
  plan to migrate before they announce one.

== Changelog ==
= 2.8.1 =
- SAVE10 coupon now gives 10% off (was 100% off — confirmed as unintended).

= 2.8.0 =
- Price is now computed and enforced server-side (closed a "pay any
  amount you want" vulnerability in the fingerprint endpoint).
- The medical questionnaire is now persisted to the database and
  emailed to the admin on successful payment (previously discarded).
- Added a Live/Test mode toggle in the admin settings instead of a
  hardcoded test gateway URL.
- Removed the public "Debug Payment" button that was shipping to every
  visitor in production.
- Payment logs moved outside the web-servable directory and scrubbed
  of card-like data.
- Switched the payment-integrity hash comparison to a constant-time
  check (hash_equals).
- Registration password is no longer run through sanitize_text_field()
  (which could silently alter it); added an 8-character minimum.
- Added a Last Name field to registration; login/registration failures
  now return a generic message instead of confirming which part failed.
- Removed the unused, fully-commented-out Accept.js payments.js file.
