/**
 * JavaScript Error Logger
 * Automatically logs JavaScript errors and unhandled promise rejections to the server
 * Include this file on all pages: <script src="path/to/error-logger.js"></script>
 * ERRORS ARE COMPLETELY SILENCED - they won't appear in browser console
 */

(function() {
    const LOG_ENDPOINT = '/PHPPROJECT/universalstore_managementsystem/internal_server/log_js_error.php';
    const MAX_RETRIES = 3;
    const RETRY_DELAY = 1000; // 1 second

    /**
     * Encrypt data using base64 encoding
     * @param {string} data - Data to encrypt
     * @returns {string} Base64 encoded data
     */
    function encryptData(data) {
        return btoa(encodeURIComponent(data));
    }

    /**
     * Send error log to server (encrypted)
     * @param {string} errorMessage - Error message
     * @param {string} errorType - Type of error (error, warning, unhandled_error, etc.)
     * @param {string} url - URL where error occurred
     * @param {number} line - Line number
     * @param {number} column - Column number
     */
    function logErrorToServer(errorMessage, errorType, url = '', line = 0, column = 0, retryCount = 0) {
        const payload = {
            js_error: errorMessage,
            js_error_type: errorType,
            js_url: url || window.location.href,
            js_line: line,
            js_column: column
        };

        // Encrypt the entire payload
        const encryptedPayload = encryptData(JSON.stringify(payload));

        fetch(LOG_ENDPOINT, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ encrypted_data: encryptedPayload }),
            mode: 'cors'
        })
        .catch(error => {
            // Silent retry - no console output
            if (retryCount < MAX_RETRIES) {
                setTimeout(() => {
                    logErrorToServer(errorMessage, errorType, url, line, column, retryCount + 1);
                }, RETRY_DELAY);
            }
            // No console.error on final failure - completely silent
        });
    }

    /**
     * Global error handler for uncaught errors
     */
    window.addEventListener('error', function(event) {
        const errorMessage = event.message || 'Unknown error';
        const errorUrl = event.filename || window.location.href;
        const errorLine = event.lineno || 0;
        const errorColumn = event.colno || 0;

        // Log to server silently - NO console output
        logErrorToServer(errorMessage, 'unhandled_error', errorUrl, errorLine, errorColumn);
    });

    /**
     * Handle unhandled promise rejections
     */
    window.addEventListener('unhandledrejection', function(event) {
        const errorMessage = event.reason ? (event.reason.message || String(event.reason)) : 'Unhandled promise rejection';

        // Log to server silently - NO console output
        logErrorToServer(errorMessage, 'unhandled_promise_rejection', window.location.href, 0, 0);
    });

    /**
     * Console error override to log console.error calls SILENTLY
     */
    console.error = function(...args) {
        const errorMessage = args.map(arg => {
            if (typeof arg === 'object') {
                return JSON.stringify(arg);
            }
            return String(arg);
        }).join(' ');

        // Log to server silently - NO console output
        logErrorToServer(errorMessage, 'console_error', window.location.href, 0, 0);
    };

    /**
     * Public API to manually log errors
     */
    window.logJSError = function(message, type = 'error', url = '', line = 0, column = 0) {
        logErrorToServer(message, type, url || window.location.href, line, column);
    };

    // Log that error logger is initialized
    console.log('JavaScript Error Logger initialized. Errors will be logged to: ' + LOG_ENDPOINT);
})();
