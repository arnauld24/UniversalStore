<?php
// Require Composer autoload (project uses Composer as the primary autoloader)
$vendor = __DIR__ . '/vendor/autoload.php';
if (!file_exists($vendor)) {
    echo "ERROR: vendor/autoload.php missing. Run composer install or composer dump-autoload." . PHP_EOL;
    exit(1);
}
require $vendor;
// trigger autoload
$exists = class_exists('external_server\\Connect', true);
var_export($exists);
echo PHP_EOL;
// If loaded, show the file path where the class was defined (reflect)
if ($exists) {
    $r = new ReflectionClass('external_server\\Connect');
    echo $r->getFileName() . PHP_EOL;
}
