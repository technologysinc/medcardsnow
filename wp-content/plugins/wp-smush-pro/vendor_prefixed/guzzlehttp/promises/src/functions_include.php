<?php

namespace Smush_Vendor;

// Don't redefine the functions if included multiple times.
if (!\function_exists('Smush_Vendor\GuzzleHttp\Promise\promise_for')) {
    require __DIR__ . '/functions.php';
}