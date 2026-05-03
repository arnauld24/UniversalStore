<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "PHP: " . PHP_VERSION . PHP_EOL;

require __DIR__ . '/vendor/autoload.php';

echo "Checking class_exists('external_server\\Connect')...\n";
var_dump(class_exists('external_server\\Connect'));

try {
    if (class_exists('external_server\\Connect')) {
        $c = new \external_server\Connect();
        echo "Instantiated Connect, PDO attributes: ";
        var_export($c->getAttribute(PDO::ATTR_ERRMODE));
        echo PHP_EOL;
    } else {
        echo "Class not found, cannot instantiate.\n";
    }
} catch (Throwable $e) {
    echo 'Exception: ' . get_class($e) . ': ' . $e->getMessage() . PHP_EOL;
}

echo "Done\n";
