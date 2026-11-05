<?php

namespace Smush_Vendor;

// Don't redefine the functions if included multiple times.
if (!\function_exists('Smush_Vendor\GuzzleHttp\uri_template')) {
    require __DIR__ . '/functions.php';
}