<?php

namespace core;

// This file exists so the PSR-like autoloader can locate the
// class by path (account/authentication/core/GoogleAuthController.php).
// The full implementation lives in google_oauth_handler.php which
// contains the same namespaced class. We require that file here
// to define the class when the autoloader resolves this path.

$impl = __DIR__ . DIRECTORY_SEPARATOR . 'google_oauth_handler.php';
if (file_exists($impl)) {
    require_once $impl;
}
