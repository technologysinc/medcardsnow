=== MCN Timeline Steps ===
Contributors: Med Cards Now
Requires at least: 5.6
Tested up to: 6.6
Requires PHP: 7.0
Requires Plugins: elementor
Stable tag: 1.0.0
License: GPLv2 or later

A highly customizable horizontal / vertical timeline & process-steps widget for Elementor.

== Installation ==
1. In WordPress admin go to Plugins > Add New > Upload Plugin.
2. Choose mcn-timeline-steps.zip and click Install Now, then Activate.
   (Elementor must already be installed and active.)
3. Edit any page with Elementor. In the widget panel search for
   "MCN Timeline Steps" and drag it onto the page.

== Using the widget ==
CONTENT > Steps
  Add/remove/reorder steps. For each step choose a marker type:
    - Number : auto-numbered (1,2,3…) or type your own number/text
    - Icon   : pick any Elementor / Font Awesome icon
    - Image  : upload a custom image
  Each step has a Title, Description, an optional Title link, and an
  optional per-step marker color override (toggle "Custom marker colors").

CONTENT > Layout
  - Orientation: Horizontal or Vertical
  - Stack on mobile: horizontal timelines turn vertical on phones
  - Show connector line: toggle the connecting line on/off
  - Content alignment (responsive)

STYLE tabs (everything below is adjustable):
  Marker        : size, corner radius, number typography, icon size,
                  background + color (normal & hover), border, box-shadow
  Connector Line: color, thickness, style (solid/dashed/dotted/double)
  Title         : typography, color, hover color, spacing
  Description   : typography, color, max width
  Items & Spacing: horizontal spacing, vertical gap, marker-to-content gap,
                  item background, border, radius, content padding

== Brand defaults ==
Marker background #1D6FD6 · marker text #FFFFFF · line #E4EBF3
title #0F2545 · description #51637A. Change any of these in the Style tab.

Tip: the icon pack uses currentColor, so icons automatically match the
marker "Number / Icon Color" you set.

== Changelog ==
= 1.0.0 =
* Initial release.
