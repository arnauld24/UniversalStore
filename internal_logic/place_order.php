<?php
require_once __DIR__ . '/../config/config.php';
require_once SERVER_PATH . '/secure_session_csrf_cookie.php';

use internal_logic\Cart;
use external_server\Connect;
use core\Controller;
use core\AuditLogger;
use internal_logic\InvoiceEmail;
use core\utility;
use core\UserTracker;


// Force JSON response for all requests
header('Content-Type: application/json');

// Instantiate a DB connection via the autoloaded Connect class
$conn = new Connect();

// Initialize UserTracker
$userTracker = new UserTracker();

//initialize controller
$controller = new Controller();
$utility_for_data_encrytpion = new utility();
$user_id = $controller->getAuthenticatedUserId();

// Check if user is authenticated
if(!$user_id || empty($user_id)){
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required. Please log in and try again.']);
    exit;
}

// Rate limiting: 5 requests per minute per user
$rate_limit_key = 'place_order_' . $user_id;
if (!isset($_SESSION[$rate_limit_key])) {
    $_SESSION[$rate_limit_key] = ['count' => 0, 'reset' => time() + 60];
}
if (time() > $_SESSION[$rate_limit_key]['reset']) {
    $_SESSION[$rate_limit_key] = ['count' => 0, 'reset' => time() + 60];
}
if ($_SESSION[$rate_limit_key]['count'] >= 5) {
    echo json_encode(['success' => false, 'message' => 'Too many requests. Please try again later.']);
    exit;
}
$_SESSION[$rate_limit_key]['count']++;

//initialize Invoiceemail class
$invoiceEmail = new InvoiceEmail();
// helper to detect AJAX
function is_ajax(){
    if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'){
        return true;
    }
    if(!empty($_REQUEST['ajax']) && (string)$_REQUEST['ajax'] === '1'){
        return true;
    }
    return false;
}

// Get cart item from db for display
$cart = new Cart($user_id);

// Prepare display items and total using the Cart class
$display_items = $cart->getDisplayItems();
$display_total = $cart->calculateTotalCart();

// Check if this is a place order request (AJAX or regular form)
if(isset($_POST['place_order']) || isset($_REQUEST['place_order']) || isset($_POST['fname'])){

    // check csrf token
    try {
        validateAjax_formCsrfToken();
    } catch (Exception $e) {
        rotateCsrfToken(); // rotate token on failure
        error_log('CSRF token validation failed: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Session expired. Please refresh and try again.', 'csrfToken' => getCsrfToken()]);
        exit;
    }

    try{
        // Initialize audit logger
        $auditLogger = new AuditLogger($user_id);

        // Secure input validation and sanitization
        $csrf_token    = htmlspecialchars(trim($_POST['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8');
        $order_id = uniqid('order_', true) . bin2hex(random_bytes(2)); // More secure order ID generation
        $fname    = htmlspecialchars(trim($_POST['fname'] ?? ''), ENT_QUOTES, 'UTF-8');
        $lname    = htmlspecialchars(trim($_POST['lname'] ?? ''), ENT_QUOTES, 'UTF-8');
        $country  = htmlspecialchars(trim($_POST['country'] ?? ''), ENT_QUOTES, 'UTF-8');
        $address  = htmlspecialchars(trim($_POST['address'] ?? ''), ENT_QUOTES, 'UTF-8');
        $city     = htmlspecialchars(trim($_POST['city'] ?? ''), ENT_QUOTES, 'UTF-8');
        $postcode = preg_replace('/\D/', '', $_POST['postcode'] ?? ''); // keep only digits
        $phone    = htmlspecialchars(trim($_POST['phone'] ?? ''), ENT_QUOTES, 'UTF-8');
        $email    = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: '';
        $addinfo  = htmlspecialchars(trim($_POST['addinfo'] ?? ''), ENT_QUOTES, 'UTF-8');

        // Check if delivery address was extracted from DOM (second condition - user has saved delivery info)
        $has_delivery_data = !empty($_POST['del_fname']) || !empty($_POST['del_email']);
        
        if ($has_delivery_data) {
            // Condition 2: Use delivery address from DOM extraction
            $del_fname = htmlspecialchars(trim($_POST['del_fname'] ?? ''), ENT_QUOTES, 'UTF-8');
            $del_lname = htmlspecialchars(trim($_POST['del_lname'] ?? ''), ENT_QUOTES, 'UTF-8');
            $del_country = htmlspecialchars(trim($_POST['del_country'] ?? ''), ENT_QUOTES, 'UTF-8');
            $del_address = htmlspecialchars(trim($_POST['del_address'] ?? ''), ENT_QUOTES, 'UTF-8');
            $del_city = htmlspecialchars(trim($_POST['del_city'] ?? ''), ENT_QUOTES, 'UTF-8');
            $del_postcode = preg_replace('/\D/', '', $_POST['del_postcode'] ?? '');
            $del_phone = htmlspecialchars(trim($_POST['del_phone'] ?? ''), ENT_QUOTES, 'UTF-8');
            $del_email = filter_var($_POST['del_email'] ?? '', FILTER_VALIDATE_EMAIL) ?: '';
            
            error_log('Place order: Using delivery address from DOM (Condition 2)');
        } else {
            // Condition 1: No delivery address displayed, use billing address as delivery address
            $del_fname = $fname;
            $del_lname = $lname;
            $del_country = $country;
            $del_address = $address;
            $del_city = $city;
            $del_postcode = $postcode;
            $del_phone = $phone;
            $del_email = $email;
            
            error_log('Place order: Using billing address as delivery address (Condition 1)');
        }

        // Secure session total retrieval with validation
        $order_cost = isset($display_total) && is_numeric($display_total) ? (float)$display_total : 0;

        $order_date = date('Y-m-d H:i:s');
        $order_status = "on_hold";

        // Phone Pattern
        $pattern = '/^(?:6(?:5[5-9]|6[0-9]|7[0-9]|8[0-9]|9[0-9])|2(?:2[0-9]|3[0-9]))[0-9]{6}$/';
        // alphabetic Pattern
        $pattern_alphabetic = '/^[a-zA-Z\s]+$/';
        $pattern_numeric = '/^[0-9]+$/';
        $pattern_alphanumeric = '/^[a-zA-Z0-9.,\/\s]+$/';

        // Determine whether billing or delivery data is present.
        $billing_present = !empty($fname) && !empty($lname) && !empty($country) && !empty($address) && !empty($city) && !empty($postcode) && !empty($phone) && !empty($email);
        $delivery_present = isset($del_fname) && isset($del_lname) && isset($del_country) && isset($del_address) && isset($del_city) && isset($del_postcode) && isset($del_phone) && isset($del_email) && !empty($del_fname) && !empty($del_lname) && !empty($del_country) && !empty($del_address) && !empty($del_city) && !empty($del_postcode) && !empty($del_phone) && !empty($del_email);

        // If billing is missing but delivery is present, use delivery values as the active billing values for validation and storage
        if (!$billing_present && $delivery_present) {
            $fname = $del_fname;
            $lname = $del_lname;
            $country = $del_country;
            $address = $del_address;
            $city = $del_city;
            $postcode = $del_postcode;
            $phone = $del_phone;
            $email = $del_email;
            // mark billing_present so validation proceeds
            $billing_present = true;
        }

        // checking at least one complete set exists (billing or delivery copied into billing)
        if ($billing_present) {
    // Get user_id from deleivery_address table in db to compare with current user_id 
    // This is to enable a user enter same phone number and email if he already has an order
if (preg_match($pattern_alphabetic, trim($fname))) {
if (preg_match($pattern_alphabetic, trim($lname))) {
if (preg_match($pattern_alphabetic, trim($country))) {
if (preg_match($pattern_alphanumeric, trim($address))) {
if (preg_match($pattern_alphabetic, trim($city))) {
if (preg_match($pattern_numeric, $postcode)) {
if (preg_match($pattern_alphanumeric, trim($addinfo)) || empty($addinfo)) {

if(preg_match($pattern, filter_var($phone, FILTER_SANITIZE_NUMBER_INT))){
$full_phone_number = '+237' . $phone;
$phone = $full_phone_number;

if(trim($full_phone_number)){
$stmt = $conn->prepare("SELECT user_phone FROM orders WHERE user_phone = :phone AND user_id != :user_id");
$stmt->bindParam(':phone',$phone, PDO::PARAM_STR);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
$stmt->execute();

if($stmt->rowCount() > 0){
echo json_encode(['success' => false, 'message' => '"'.htmlspecialchars($phone).'"' . ' ~ Already Exists for another user']);
exit;
}else{
if(filter_var($email, FILTER_VALIDATE_EMAIL)){
$stmt = $conn->prepare("SELECT user_email FROM orders WHERE user_email = :email AND user_id != :user_id");
$stmt->bindParam(':email', $email, PDO::PARAM_STR);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
$stmt->execute();

if($stmt->rowCount() > 0){
echo json_encode(['success' => false, 'message' =>'"'.htmlspecialchars($email).'"' . ' ~ Already Exists for another user']);
exit;
} else{
//Placing orders 
if(isset($display_total) && is_numeric($display_total) && $display_total > 0){
    
    try{

        // Encrypt sensitive data before storage
        $encrypted_email = $utility_for_data_encrytpion->encryptData($email);
        $encrypted_phone = $utility_for_data_encrytpion->encryptData($phone);
        $encrypted_address = $utility_for_data_encrytpion->encryptData($address);
        $encrypted_fname = $utility_for_data_encrytpion->encryptData($fname);
        $encrypted_lname = $utility_for_data_encrytpion->encryptData($lname);

        // Begin transaction
        $conn->beginTransaction();
        // Compute estimated delivery range and zone for this order
        try {
            $zone_id = $cart->getShippingZoneId($country, $city, $address, $postcode);
            $deliveryRange = $cart->getEstimatedDeliveryRangeForOrder($display_items, $zone_id);
        } catch (Throwable $e) {
            error_log('Error computing delivery range during place_order: ' . $e->getMessage());
            $deliveryRange = null;
        }

        $stmt = $conn->prepare("INSERT INTO orders (order_id,user_id,user_fname,user_lname,user_country,user_address,user_city,user_postcode,user_phone,user_email,additional_info,order_date,order_cost,order_status,estimated_delivery_start_date,estimated_delivery_end_date)
VALUES(:order_id,:user_id,:fname,:lname,:country,:address,:city,:postcode,:phone,:email,:addinfo,:order_date,:total,:order_status,:estimated_start,:estimated_end)");

        $stmt->bindParam(':order_id', $order_id, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $user_id,PDO::PARAM_STR);
        $stmt->bindParam(':fname', $encrypted_fname,PDO::PARAM_STR);
        $stmt->bindParam(':lname', $encrypted_lname,PDO::PARAM_STR);
        $stmt->bindParam(':country', $country,PDO::PARAM_STR);
        $stmt->bindParam(':address', $encrypted_address,PDO::PARAM_STR);
        $stmt->bindParam(':city', $city,PDO::PARAM_STR);
        $stmt->bindParam(':postcode', $postcode,PDO::PARAM_STR);
        $stmt->bindParam(':phone', $encrypted_phone,PDO::PARAM_STR);
        $stmt->bindParam(':email', $encrypted_email,PDO::PARAM_STR);
        $stmt->bindParam(':addinfo', $addinfo,PDO::PARAM_STR);
        $stmt->bindParam(':order_date', $order_date, PDO::PARAM_STR);
        $stmt->bindParam(':total', $order_cost,PDO::PARAM_STR);
        $stmt->bindParam(':order_status', $order_status,PDO::PARAM_STR);
        // Bind estimated delivery dates if available
        $estimated_start = $deliveryRange['start_date'] ?? null;
        $estimated_end = $deliveryRange['end_date'] ?? null;
        $stmt->bindParam(':estimated_start', $estimated_start, PDO::PARAM_STR);
        $stmt->bindParam(':estimated_end', $estimated_end, PDO::PARAM_STR);

        $orderInsertOk = $stmt->execute();
        $stmt->closeCursor();
        if (!$orderInsertOk) {
             if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            throw new Exception('Failed to insert order.');
        }

        // Log order placement
        $auditLogger->logOrderPlacement($order_id, 'SUCCESS', "Order placed for user {$user_id}", [
            'order_cost' => $order_cost,
            'item_count' => count($display_items),
            'delivery_zone' => $zone_id ?? 'default'
        ]);

        // Also insert user delivery addresses to delivery_address table
       if($orderInsertOk){ 
         try{
            // Encrypt delivery address data before storage
            $encrypted_del_fname = $utility_for_data_encrytpion->encryptData($del_fname);
            $encrypted_del_lname = $utility_for_data_encrytpion->encryptData($del_lname);
            $encrypted_del_email = $utility_for_data_encrytpion->encryptData($del_email);
            $encrypted_del_phone = $utility_for_data_encrytpion->encryptData($del_phone);
            $encrypted_del_address = $utility_for_data_encrytpion->encryptData($del_address);

            // Check if record exists
            $checkStmt = $conn->prepare("SELECT COUNT(*) FROM delivery_address WHERE user_id = :user_id AND user_email = :email");
            $checkStmt->execute([':user_id' => $user_id, ':email' => $email]);
            $exists = $checkStmt->fetchColumn() > 0;

            if ($exists) {
                // Update existing record
                $stmt = $conn->prepare("UPDATE delivery_address SET user_fname = :fname, user_lname = :lname, user_postcode = :postcode, user_address = :address, user_city = :city, user_country = :country, user_phone = :phone WHERE user_id = :user_id AND user_email = :email");
            } else {
                // Insert new record
                $stmt = $conn->prepare("INSERT INTO delivery_address (user_id, user_email, user_fname, user_lname, user_postcode,user_phone, user_address, user_city, user_country) VALUES (:user_id, :email, :fname, :lname, :postcode, :phone, :address, :city, :country)");
            }
            $stmt->execute([
                ':fname' => $encrypted_del_fname,
                ':lname' => $encrypted_del_lname,
                ':postcode' => $postcode,
                ':address' => $encrypted_del_address,
                ':city' => $city,
                ':country' => $country,
                ':user_id' => $user_id,
                ':email' => $encrypted_del_email,
                ':phone' => $encrypted_del_phone
            ]);
        }catch(PDOException $e){
             if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            rotateCsrfToken();
            error_log("Error occurred while updating user Delivery Address: " .$e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error saving delivery address. Please try again.', 'csrfToken' => htmlspecialchars(getCsrfToken())]);
            exit;
        }
       }
         // Process cart items
        $order_items_success = true;
        if (isset($display_items) && !empty($display_items)) {
            // Prepare statement to fetch product prep days
            $prepStmt = $conn->prepare("SELECT product_prep_days, product_prep_days_max FROM products WHERE product_id = :product_id LIMIT 1");
            foreach ($display_items as $key => $value) {
                $product = $display_items[$key];
                $product_id = isset($product['product_id']) && is_numeric($product['product_id']) ? (int)$product['product_id'] : 0;
                $product_name = isset($product['product_name']) && is_string($product['product_name']) ? htmlspecialchars($product['product_name'], ENT_QUOTES, 'UTF-8') : '';
                $product_image = isset($product['product_image']) && is_string($product['product_image']) ? htmlspecialchars($product['product_image'], ENT_QUOTES, 'UTF-8') : '';
                $product_price = isset($product['product_price']) && is_numeric($product['product_price']) && $product['product_price'] > 0 ? (float)$product['product_price'] : 0;
                $product_quantity = isset($product['product_quantity']) && is_numeric($product['product_quantity']) && $product['product_quantity'] > 0 ? (int)$product['product_quantity'] : 1;
                $product_color = isset($product['product_color']) && is_string($product['product_color']) ? htmlspecialchars($product['product_color'], ENT_QUOTES, 'UTF-8'): '';
                $product_variant = isset($product['product_variant']) && is_string($product['product_variant']) ? htmlspecialchars($product['product_variant'], ENT_QUOTES, 'UTF-8'): '';

                // fetch prep days for this product (use sensible defaults if missing)
                $item_prep_min = 2;
                $item_prep_max = 5;
                if ($product_id > 0) {
                    if (!$prepStmt->execute([':product_id' => $product_id])) {
                        $order_items_success = false;
                        break;
                    }
                    $prepRow = $prepStmt->fetch(PDO::FETCH_ASSOC);
                    if ($prepRow) {
                        $item_prep_min = isset($prepRow['product_prep_days']) ? (int)$prepRow['product_prep_days'] : $item_prep_min;
                        $item_prep_max = isset($prepRow['product_prep_days_max']) ? (int)$prepRow['product_prep_days_max'] : $item_prep_max;
                    }
                }

                // store each item in the order_items table (include prep-day columns)
                $stmt1 = $conn->prepare("INSERT INTO order_items (order_id,product_id,product_name,product_price,product_quantity,product_image,product_color,product_variant,user_id,order_date,item_prep_day_min,item_prep_day_max)
                VALUES(:order_id,:product_id,:product_name,:product_price,:product_quantity,:product_image,:product_color,:product_variant,:user_id,:order_date,:item_prep_min,:item_prep_max)");
                $stmt1->bindParam(':order_id', $order_id, PDO::PARAM_STR);
                $stmt1->bindParam(':product_id', $product_id, PDO::PARAM_INT);
                $stmt1->bindParam(':product_name', $product_name, PDO::PARAM_STR);
                $stmt1->bindParam(':product_price', $product_price, PDO::PARAM_STR);
                $stmt1->bindParam(':product_quantity', $product_quantity, PDO::PARAM_INT);
                $stmt1->bindParam(':product_image', $product_image, PDO::PARAM_STR);
                $stmt1->bindParam(':user_id', $user_id, PDO::PARAM_STR);
                $stmt1->bindParam(':order_date', $order_date, PDO::PARAM_STR);
                $stmt1->bindParam(':product_color', $product_color, PDO::PARAM_STR);
                $stmt1->bindParam(':product_variant', $product_variant, PDO::PARAM_STR);
                $stmt1->bindParam(':item_prep_min', $item_prep_min, PDO::PARAM_INT);
                $stmt1->bindParam(':item_prep_max', $item_prep_max, PDO::PARAM_INT);

                if (!$stmt1->execute()) {
                    $order_items_success = false;
                    break;
                }
            }
        } else {
            // No items to process: treat as failure
            $order_items_success = false;
        }

        if (!$order_items_success) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            rotateCsrfToken();
            echo json_encode(['success' => false, 'message' => 'Order failed to save items', 'csrfToken' => htmlspecialchars(getCsrfToken())]);
            exit;
        }

        // All operations succeeded, commit transaction
        $conn->commit();

        // Track successful order placement
        $userTracker::trackOrderEvent($order_id, (float)$order_cost, (int)count($display_items), 'placed/on_hold');

        // Return estimated delivery dates to the client when available
        rotateCsrfToken(); // rotate token on success
        $response = ['success' => true, 'message' => 'Order placed successfully. Redirecting to payment... ', 'order_id' => htmlspecialchars($order_id, ENT_QUOTES, 'UTF-8'), 'csrfToken' => htmlspecialchars(getCsrfToken())];
        if (!empty($deliveryRange) && is_array($deliveryRange)) {
            $response['estimated_delivery_start_date'] = $deliveryRange['start_date'];
            $response['estimated_delivery_end_date'] = $deliveryRange['end_date'];
            $response['estimated_delivery_display'] = $deliveryRange['display'];
        }
        echo json_encode($response);

        // send invoice email to user with order details and estimated delivery dates
        try {
            error_log("Sending invoice with orderID '$order_id' to user $$fname $lname at email '$email' after successful order placement.");
            $invoiceEmail->sendInitialInvoiceEmail($order_id, $email, $fname.$lname);
        } catch (Throwable $e) {
            error_log('Error sending invoice email after order placement: ' . $e->getMessage());
            //log errors if email sending fails but do not fail the order placement because of email issues
        }
        // Delete cart after successful placement of order 
        //we shall perform it once payment is validated
       // header('Location: ' . universalstore_URL . '/payment.php?order_id=' . urlencode($order_id));
        exit;
    }catch (Throwable $e) {
        // Rollback transaction on error
        if($conn->inTransaction()){
        $conn->rollBack();
        }
        rotateCsrfToken(); // rotate token on failure
        error_log('Error during order placement transaction: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error processing order. Please try again.', 'csrfToken' => htmlspecialchars(getCsrfToken())]);
        exit;
    }
} else {
    rotateCsrfToken(); // rotate token on failure
echo json_encode(['success' => false, 'message' => 'No session total found', 'csrfToken' => htmlspecialchars(getCsrfToken())]);
exit;
}
}
} else {
echo json_encode(['success' => false, 'message' => '"'. htmlspecialchars($email) .'"'. " ~ This is not a Valid Email"]);
exit;
}
}
} else {
echo json_encode(['success' => false, 'message' => 'Invalid Phone number. Enter a correct Phone number']);
exit;
}
} else {
echo json_encode(['success' => false, 'message' => 'Invalid Phone number format ~ must be a valid Cameroonian phone number']);
exit;
}
} else {
echo json_encode(['success' => false, 'message' => '"Additional Info" requires to:' . 'Enter valid characters ~ Only Alphanumerical characters.']);
exit;
}
} else {
echo json_encode(['success' => false, 'message' => 'Enter valid Postcode ~ Numbers Only']);
exit;
}
} else {
echo json_encode(['success' => false, 'message' => '"Town / City" requires to:' .'Enter Only ~ Alphabetical characters.']);
exit;
}
} else {
echo json_encode(['success' => false, 'message' =>'"Address" requires to:' . 'Enter Only ~ AlphaNumerical characters.']);
exit;
}
} else {
echo json_encode(['success' => false, 'message' => 'Enter valid Country Name']);
exit;
}
} else {
echo json_encode(['success' => false, 'message' => '"Last Name" requires to:' .'Enter Only ~ Alphabetical Characters.']);
exit;
}
} else {
echo json_encode(['success' => false, 'message' => '"First Name" requires to:' . 'Enter Only ~ Alphabetical Characters.']);
exit;
}
} else {
echo json_encode(['success' => false, 'message' => 'Please fill all the required fields']);
exit;
}

} catch(PDOException $e){
    rotateCsrfToken(); // rotate token on failure
    error_log('PDO Database Error in place_order: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred. Please try again.', 'csrfToken' => htmlspecialchars(getCsrfToken())]);
    exit;
} catch(Exception $e){
    rotateCsrfToken(); // rotate token on failure
    error_log('General Exception in place_order: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred. Please try again.', 'csrfToken' => htmlspecialchars(getCsrfToken())]);
    exit;
} catch(Throwable $e){
    rotateCsrfToken(); // rotate token on failure
    error_log('Throwable error in place_order: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A system error occurred. Please try again.', 'csrfToken' => htmlspecialchars(getCsrfToken())]);
    exit;
}
} else {
    rotateCsrfToken(); // rotate token on failure
    error_log('Invalid request to place_order: place_order flag not set');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request', 'csrfToken' => htmlspecialchars(getCsrfToken())]);
    exit;
}


?>
