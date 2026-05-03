# Invoice Email on Delivery - Implementation Guide

## Overview
Your system now has complete invoice email functionality:
- ✅ **Initial invoice email** sent when order is placed (already in place_order.php)
- ✅ **Final delivery invoice email** sent when order status changes to "delivered"

## System Components

### 1. **OrderStatusManager.php** (internal_logic/)
Handles all order status updates with automatic email notifications:
```php
use internal_logic\OrderStatusManager;

$statusManager = new OrderStatusManager();
$statusManager->updateOrderStatus($orderId, 'delivered');
// This automatically sends the delivery invoice email
```

### 2. **update_order_status.php** (internal_logic/)
API endpoint for updating orders (called by the admin UI):
- Accepts POST requests with order_id and status
- Validates CSRF tokens
- Sends appropriate emails automatically
- Returns JSON response

### 3. **order_management.php** (universalstore_managementsystem/)
Admin dashboard to manage and update order statuses:
- View all orders with current status
- Change status via dropdown and Update button
- Automatic email notifications when delivered
- Access at: `/universalstore_managementsystem/order_management`

## Database Changes

Add this column to your `orders` table (if not already present):
```sql
ALTER TABLE orders ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
```

## How It Works

### Email Flow:
```
Order Placed
    ↓
place_order.php sends INITIAL invoice email
    ↓
[Later] Admin updates status to 'delivered'
    ↓
order_management.php → update_order_status.php
    ↓
OrderStatusManager detects status change
    ↓
Automatically sends FINAL invoice email to customer
```

## Usage Examples

### Option 1: Using Admin UI (Recommended for manual updates)
1. Go to `/universalstore_managementsystem/order_management`
2. Find the order in the table
3. Select "Delivered" from the dropdown
4. Click "Update" button
5. Invoice email is sent automatically ✅

### Option 2: Programmatic Usage
```php
use internal_logic\OrderStatusManager;

$statusManager = new OrderStatusManager();

// This will automatically send the delivery email
$success = $statusManager->updateOrderStatus($orderId, 'delivered');

if ($success) {
    echo "Order updated and email sent!";
}
```

### Option 3: Integrate with existing code
If you have an existing order update process, add this:
```php
use internal_logic\OrderStatusManager;

// After updating order status in your code:
$statusManager = new OrderStatusManager();
$statusManager->updateOrderStatus($orderId, $newStatus);
```

## Email Behavior

### Initial Email (On Order Placement)
- **Sent by**: place_order.php
- **When**: Immediately after order is placed
- **To**: Customer email
- **Content**: Order receipt + invoice preview
- **Subject**: "Order Invoice #[ORDER_ID]"

### Final Email (On Delivery)
- **Sent by**: OrderStatusManager (update_order_status.php)
- **When**: When status changes to "delivered"
- **To**: Customer email
- **Content**: Full invoice with delivery confirmation
- **Subject**: "Order Delivered - Final Invoice #[ORDER_ID]"

## Security Features

✅ CSRF token validation on all status updates
✅ Authentication check (admin only)
✅ Input sanitization and validation
✅ Error logging for troubleshooting
✅ Secure order verification

## Testing

1. **Test Initial Email**:
   - Place a test order through checkout
   - Check email inbox for initial invoice

2. **Test Delivery Email**:
   - Go to `/universalstore_managementsystem/order_management`
   - Change any order status to "Delivered"
   - Check email inbox for delivery invoice

3. **Check Logs**:
   ```bash
   tail error_logs/php_errors.log
   ```

## Status Options

- `on_hold` - Order received, awaiting payment/processing
- `shipped` - Order is in transit
- `delivered` - Order delivered to customer (triggers final invoice email)
- `cancelled` - Order cancelled

## Customization

### To modify email templates:
Edit `InvoiceEmail.php` - methods:
- `sendInitialInvoiceEmail()` - Initial order email
- `sendFinalInvoiceEmail()` - Delivery confirmation email

### To add more status transitions with emails:
Edit `OrderStatusManager.php` - method `sendStatusChangeEmail()`

Example to add "shipped" notification:
```php
if ($newStatus === 'shipped' && $oldStatus !== 'shipped') {
    // Send shipped notification email
    // $this->sendShippedEmail(...);
}
```

## Troubleshooting

### Emails not sending?
1. Check PHP error logs: `error_logs/php_errors.log`
2. Verify email configuration in config.php
3. Check SMTP settings if configured
4. Ensure PHPMailer is installed

### Order status not updating?
1. Verify database `updated_at` column exists
2. Check CSRF token is valid
3. Check user is authenticated
4. Review error logs for details

### Invoice shows wrong data?
1. Verify order data in database
2. Check InvoiceGenerator.php is reading correct columns
3. Test invoice view: `/universalstore_managementsystem/invoice?order_id=[ID]`

## Summary

Your invoice email system is now complete:
- ✅ Automatic initial invoice on order placement
- ✅ Automatic final invoice when order delivered
- ✅ Admin UI for easy order management
- ✅ Secure with CSRF and auth checks
- ✅ Beautiful, professional invoice PDFs
- ✅ Email notifications at each stage
