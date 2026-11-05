<?php

namespace Smush_Vendor;

// Don't redefine the functions if included multiple times.
if (!\function_exists('Smush_Vendor\GuzzleHttp\Psr7\str')) {
    require __DIR__ . '/functions.php';
}