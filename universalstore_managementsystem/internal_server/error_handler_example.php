<?php
// Example file showing how to use the JavaScript error handler
require_once __DIR__ . '/secure_session_csrf_cookie.php';

// Output the JavaScript error handler in the HTML head
function includeJavaScriptErrorHandler() {
    echo getJavaScriptErrorHandler();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error Handler Example</title>
    <?php includeJavaScriptErrorHandler(); ?>
</head>
<body>
    <h1>JavaScript Error Handler Example</h1>
    <p>This page demonstrates how JavaScript errors are now logged to files instead of displaying in the browser console.</p>

    <button onclick="testError()">Test Error Logging</button>
    <button onclick="testWarning()">Test Warning Logging</button>
    <button onclick="testUnhandledError()">Test Unhandled Error</button>

    <script>
        function testError() {
            console.error('This is a test error message');
            console.error('Multiple', 'arguments', { test: 'object' });
        }

        function testWarning() {
            console.warn('This is a test warning message');
        }

        function testUnhandledError() {
            // This will trigger an unhandled error
            setTimeout(() => {
                throw new Error('This is an unhandled error test');
            }, 100);
        }

        // Test promise rejection
        function testPromiseRejection() {
            Promise.reject('This is a promise rejection test');
        }

        // Call this after a delay to test unhandled promise rejection
        setTimeout(testPromiseRejection, 1000);
    </script>
</body>
</html>
