<?php 

// INITIALIZATION & SECURITY
require_once __DIR__ . '/../config/config.php';
require_once INIT_PATH . '/init.php';

if (!defined('APP_INIT')) {
    error_log('Attempted direct access to secure utility file: ' . $_SERVER['REQUEST_URI']);
    http_response_code(403);
    header("Location: " . ERROR_URL . "?code=403");
    exit;
}

require_once SERVER_PATH . '/secure_session_csrf_cookie.php';

use core\Controller;
use internal_logic\OrderStatusManager;

// Check authentication
$authenticatedUserId = Controller::getAuthenticatedUserId();

if (!$authenticatedUserId) {
    header("Location: " . AUTHENTICATION_URL);
    exit;
}

// Initialize OrderStatusManager
$statusManager = new OrderStatusManager();

// Get all orders
$allOrders = [];
try {
    $allOrders = $statusManager->getUserOrders($authenticatedUserId);
} catch (Exception $e) {
    error_log('Error fetching orders: ' . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management - Universal Store</title>
    <link href="<?php echo ASSETS_URL; ?>/css/tailwind.css" rel="stylesheet">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <style>
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-on_hold { background: #dbeafe; color: #1e40af; }
        .status-shipped { background: #fef3c7; color: #92400e; }
        .status-delivered { background: #dcfce7; color: #166534; }
        .status-cancelled { background: #fee2e2; color: #7f1d1d; }
    </style>
</head>
<body class="bg-slate-50">
    <div class="max-w-6xl mx-auto p-6">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-slate-800">Order Management</h1>
            <p class="text-slate-500 mt-2">Manage and update order statuses. Delivery notifications will be sent automatically.</p>
        </div>

        <!-- Alerts -->
        <div id="successAlert" class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700" style="display: none;"></div>
        <div id="errorAlert" class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700" style="display: none;"></div>

        <!-- Orders Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full">
                <thead class="bg-slate-100 border-b">
                    <tr>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-slate-700">Order ID</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-slate-700">Customer</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-slate-700">Date</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-slate-700">Total</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-slate-700">Current Status</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-slate-700">Update Status</th>
                        <th class="px-6 py-3 text-left text-sm font-semibold text-slate-700">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($allOrders)): ?>
                        <?php foreach ($allOrders as $order): ?>
                            <tr class="border-b hover:bg-slate-50">
                                <td class="px-6 py-4 text-sm font-mono text-slate-800">
                                    <?php echo htmlspecialchars(substr($order['order_id'], 0, 15), ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-700">
                                    <?php echo htmlspecialchars($order['user_fname'] . ' ' . $order['user_lname'], ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600">
                                    <?php echo htmlspecialchars(date('M d, Y', strtotime($order['order_date'])), ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td class="px-6 py-4 text-sm font-semibold text-slate-800">
                                    <?php echo htmlspecialchars(number_format((float)$order['order_cost'], 0, '.', ',') . ' XAF', ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="status-badge status-<?php echo htmlspecialchars($order['order_status'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $order['order_status'])), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <select class="status-select px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                            data-order-id="<?php echo htmlspecialchars($order['order_id'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-current-status="<?php echo htmlspecialchars($order['order_status'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <option value="">-- Change Status --</option>
                                        <option value="on_hold" <?php echo $order['order_status'] === 'on_hold' ? 'selected' : ''; ?>>On Hold</option>
                                        <option value="shipped" <?php echo $order['order_status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                        <option value="delivered" <?php echo $order['order_status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                        <option value="cancelled" <?php echo $order['order_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <button class="update-btn px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold transition-all"
                                            data-order-id="<?php echo htmlspecialchars($order['order_id'], ENT_QUOTES, 'UTF-8'); ?>">
                                        Update
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-slate-500">
                                No orders found
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const successAlert = document.getElementById('successAlert');
        const errorAlert = document.getElementById('errorAlert');

        function showAlert(type, message) {
            if (type === 'success') {
                successAlert.textContent = message;
                successAlert.style.display = 'block';
                errorAlert.style.display = 'none';
            } else {
                errorAlert.textContent = message;
                errorAlert.style.display = 'block';
                successAlert.style.display = 'none';
            }
            setTimeout(() => {
                if (type === 'success') {
                    successAlert.style.display = 'none';
                }
            }, 5000);
        }

        // Update button click handler
        document.querySelectorAll('.update-btn').forEach(button => {
            button.addEventListener('click', function() {
                const orderId = this.getAttribute('data-order-id');
                const selectElement = document.querySelector(`.status-select[data-order-id="${orderId}"]`);
                const newStatus = selectElement.value;
                const currentStatus = selectElement.getAttribute('data-current-status');

                if (!newStatus) {
                    showAlert('error', 'Please select a status');
                    return;
                }

                if (newStatus === currentStatus) {
                    showAlert('error', 'Status is unchanged');
                    return;
                }

                // Confirm status change
                if (!confirm(`Update order status from "${currentStatus}" to "${newStatus}"?`)) {
                    return;
                }

                // Send update request
                const formData = new FormData();
                formData.append('order_id', orderId);
                formData.append('status', newStatus);
                formData.append('csrf_token', csrfToken);

                this.disabled = true;
                this.textContent = 'Updating...';

                fetch('<?php echo INTERNAL_LOGIC_URL; ?>/update_order_status.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', `Order status updated to "${newStatus}". Delivery email sent!`);
                        // Reload page after 2 seconds
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        showAlert('error', data.message || 'Failed to update status');
                        this.disabled = false;
                        this.textContent = 'Update';
                    }
                })
                .catch(error => {
                    showAlert('error', 'Error: ' + error.message);
                    this.disabled = false;
                    this.textContent = 'Update';
                });
            });
        });
    </script>
</body>
</html>
