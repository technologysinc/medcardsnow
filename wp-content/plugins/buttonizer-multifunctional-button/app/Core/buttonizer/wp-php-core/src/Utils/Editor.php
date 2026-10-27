<?php
/**
 * Editor language helper.
 *
 * Maps WordPress locale to the Buttonizer editor language code.
 *
 * @package Buttonizer\Core\Utils
 *
 * @license proprietary
 * Modified by buttonizer on 13-October-2026 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace Buttonizer\Core\Utils;

class Editor
{
    public static function getEditorLanguage()
    {
        switch (get_locale()) {
            case "nl_NL":
            case "nl_BE":
                return "nl";

            case "it_IT":
                return "it";

            case "pt_BR":
                return "pt_br";

            case "ro_RO":
                return "ro_ro";

            case "tr_TR":
                return "tr_tr";

            case "es_ES":
                return "es";

            default:
                return 'en';
        }
    }
}

