<?php
/**
 * Admin dashboard page template.
 *
 * Renders the React SPA mount point with a loading animation.
 * Used by BaseAdmin::page() to render the plugin's admin page.
 *
 * @package Buttonizer\Core\Admin
 *
 * @license proprietary
 * Modified by buttonizer on 13-October-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace Buttonizer\Core\Admin;

use Buttonizer\Core\PluginConfig;

class AdminTemplate
{
    /**
     * Render the admin page template.
     */
    public static function render()
    {
        $iconUrl = esc_url(plugins_url("/assets/images/icon-animated.svg", PluginConfig::pluginFile()));

        echo '<style>
  .buzzy-animation {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    width: 250px;
    animation: bzanim 2s 0s infinite
  }

  .buzzy-animation img {
    width: 150px;
    height: 150px
  }

  .buzzy-animation span {
    display: block;
    margin-top: 20px;
    font-weight: 500;
    font-size: 17px;
    color: #333;
    font-family: Averta, BlinkMacSystemFont, Segoe UI, Roboto, Oxygen, Ubuntu, Cantarell, Fira Sans, Droid Sans, Helvetica Neue, sans-serif
  }

  @keyframes bzanim {
    0% {
      opacity: .4
    }

    50% {
      opacity: 1
    }

    100% {
      opacity: .4
    }
  }
</style>

<noscript>You need to enable JavaScript to run Buttonizer.</noscript>

<div id="root">
  <div class="buzzy-animation"><img src="' . $iconUrl . '" /> <span>Loading...</span></div>
</div>';
    }
}

