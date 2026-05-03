<?php

namespace internal_logic;

use Exception;
use PDO;
use external_server\Connect;
use PDOException;
use Ramsey\Uuid\Uuid;
use DateTime;
use Throwable;
try{
class Cart {
    private PDO $conn;
    private ?string $authenticatedUserId;

    public function __construct(?string $authenticatedUserId = null) {
        $this->conn = new Connect();
        $this->authenticatedUserId = $authenticatedUserId;
        // The cart is now restored in init.php via visitorManager->loadVisitorCartFromDb()
        // so no action is needed here.
    }

    public function isLoggedIn(): bool {
        return !empty($this->authenticatedUserId);
    }

    public function getAuthenticatedUserId(): ?string {
        return $this->authenticatedUserId;
    }

    public function getOrCreateCartIdForUser(string $userId): string {
        // Try to find existing cart
        $stmt = $this->conn->prepare('SELECT cart_id FROM carts WHERE user_id = :user_id LIMIT 1');
        $stmt->execute([':user_id' => $userId]);
        $cart_id = $stmt->fetchColumn();
        if ($cart_id) return $cart_id;

        // Create new cart_id and record
        $cart_id = Uuid::uuid4()->toString();
        $now = date('Y-m-d H:i:s');
        $ins = $this->conn->prepare('INSERT INTO carts (cart_id, user_id, created_at, updated_at) VALUES (:cart_id, :user_id, :created_at, :updated_at)');
        $ins->execute([':cart_id' => $cart_id, ':user_id' => $userId, ':created_at' => $now, ':updated_at' => $now]);
        return $cart_id;
    }

    public function getCartItemCountForCart(string $cart_id): int {
        $stmt = $this->conn->prepare('SELECT SUM(quantity) FROM cart_items WHERE cart_id = :cart_id');
        $stmt->execute([':cart_id' => $cart_id]);
        $count = $stmt->fetchColumn();
        return (int)($count ?: 0);
    }

    public function addOrUpdateDbCartItem(string $cart_id, int $product_id, int $quantity, ?string $color = null, ?string $variant = null, ?float $price_at_time = null): void {
       
    try{
        // If no price provided, fetch current price
        if ($price_at_time === null) {
            $price_at_time = $this->getProductPriceFromDatabase($product_id);
        }
        // Check existing
        $stmt = $this->conn->prepare('SELECT id, quantity FROM cart_items WHERE cart_id = :cart_id AND product_id = :product_id AND color = :color AND variant = :variant LIMIT 1');
        $stmt->execute([':cart_id' => $cart_id, ':product_id' => $product_id, ':color' => $color, ':variant' => $variant]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $newQty = max(1, (int)$row['quantity'] + (int)$quantity);
            $upd = $this->conn->prepare('UPDATE cart_items SET quantity = :quantity WHERE id = :id');
            $upd->execute([':quantity' => $newQty, ':id' => $row['id']]);
        } else {
            $ins = $this->conn->prepare('INSERT INTO cart_items (cart_id, product_id, quantity, price_at_time, color, variant) VALUES (:cart_id, :product_id, :quantity, :price_at_time, :color,:variant)');
            $ins->execute([':cart_id' => $cart_id, ':product_id' => $product_id, ':quantity' => $quantity, ':price_at_time' => $price_at_time, ':color' => $color, ':variant' => $variant]);
        }
        // update time of column updated_at from cart  
        $now = date('Y-m-d H:i:s');
        $stmt = $this->conn->prepare('UPDATE carts SET updated_at = :now WHERE cart_id = :cart_id');
        $stmt->execute([':cart_id' => $cart_id, ':now' => $now]);
    }
    catch(Throwable $e){
        error_log("Error while adding or updating cart info in db:" . $e->getMessage(). $e->getTraceAsString());

    }
    }

    public function removeDbCartItem(string $cart_id, int $product_id, ?string $color = null, ?string $variant = null): void {
    try{    
    $del = $this->conn->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id AND product_id = :product_id AND color = :color AND variant = :variant');
        $del->execute([':cart_id' => $cart_id, ':product_id' => $product_id, ':color' => $color, ':variant' => $variant]);

        // update time of column updated_at from cart  
        $now = date('Y-m-d H:i:s');
        $stmt = $this->conn->prepare('UPDATE carts SET updated_at = :now WHERE cart_id = :cart_id');
        $stmt->execute([':cart_id' => $cart_id, ':now' => $now]);
    }catch(Throwable $e){
        error_log("Error while removing cart info from db:" . $e->getMessage(). $e->getTraceAsString());
    }
    }

    public function updateDbCartQuantities(string $cart_id, array $quantities): array {
       try{ 
    $updated_subtotals = [];
        foreach ($quantities as $product_id => $quantity) {
            $product_id = filter_var($product_id, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
            $quantity = filter_var($quantity, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
            if (!$product_id || !$quantity) continue;
            $upd = $this->conn->prepare('UPDATE cart_items SET quantity = :quantity WHERE cart_id = :cart_id AND product_id = :product_id');
            $upd->execute([':quantity' => $quantity, ':cart_id' => $cart_id, ':product_id' => $product_id]);
            $price = $this->getProductPriceFromDatabase($product_id);
            $updated_subtotals[$product_id] = $price * $quantity;
        }
    
        // update time of column updated_at from cart  
        $now = date('Y-m-d H:i:s');
        $stmt = $this->conn->prepare('UPDATE carts SET updated_at = :now WHERE cart_id = :cart_id');
        $stmt->execute([':cart_id' => $cart_id, ':now' => $now]);
    }catch(Throwable $e){
        error_log("Error while updating cart quantities in db:" . $e->getMessage(). $e->getTraceAsString());
        die;
    }
        return $updated_subtotals;
    
    }

    public function calculateTotalCartDb(string $cart_id): float {
        $stmt = $this->conn->prepare('SELECT ci.product_id, ci.quantity, ci.price_at_time, p.product_price FROM cart_items ci LEFT JOIN products p ON p.product_id = ci.product_id WHERE ci.cart_id = :cart_id');
        $stmt->execute([':cart_id' => $cart_id]);
        $total = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $price = $row['price_at_time'] ?: ($row['product_price'] ?? 0);
            $qty = (int)$row['quantity'];
            $total += $qty * $price;
        }
        // Keep session total in sync for templates
       // $_SESSION['total'] = $total;
        return $total;
    }

    public function migrateSessionCartToDb(string $userId): void {
        if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) return;
        $cart_id = $this->getOrCreateCartIdForUser($userId);
        foreach ($_SESSION['cart'] as $item) {
            $pid = isset($item['product_id']) ? (int)$item['product_id'] : 0;
            $qty = isset($item['product_quantity']) ? (int)$item['product_quantity'] : 1;
            if ($pid <= 0) continue;
            // Convert price to float, color and variant to string
            $price = isset($item['product_price']) ? (float)$item['product_price'] : null;
            $color = isset($item['product_color']) ? (string)$item['product_color'] : null;
            $variant = isset($item['product_variant']) ? (string)$item['product_variant'] : null;
            $this->addOrUpdateDbCartItem($cart_id, $pid, $qty, $color, $variant, $price);
        }
        // Clear session cart to avoid duplicates
        unset($_SESSION['cart']);
        unset($_SESSION['total']);
    }

    public function getProductPriceFromDatabase(int $product_id): float {
        try {
            $stmt = $this->conn->prepare("SELECT product_price FROM products WHERE product_id = :product_id");
            $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $stmt->execute();
            return (float)($stmt->fetchColumn() ?: 0);
        } catch (Exception $e) {
            return 0.0;
        }
    }

    public function calculateTotalCart(): float {
        // If user is logged in, use DB-backed cart total
        if ($this->isLoggedIn()) {
            $cart_id = $this->getOrCreateCartIdForUser($this->authenticatedUserId);
            return $this->calculateTotalCartDb($cart_id);
        }

        $total = 0;
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['total'] = 0;
            return 0;
        }

        foreach ($_SESSION['cart'] as $item) {
            $product_id = isset($item['product_id']) && is_numeric($item['product_id']) && $item['product_id'] > 0 ? (int)$item['product_id'] : 0;
            if ($product_id <= 0) continue;
            $price = $this->getProductPriceFromDatabase($product_id);
            $qty = isset($item['product_quantity']) && is_numeric($item['product_quantity']) && $item['product_quantity'] > 0 ? (int)$item['product_quantity'] : 1;
            $total += $qty * $price;
        }
        // $_SESSION['total'] = $total;
        return $total;
    }

    public function getDisplayItems(): array {
        $display_items = [];
        if ($this->isLoggedIn()) {
            $cart_id = $this->getOrCreateCartIdForUser($this->authenticatedUserId);
            // Fetch cart items from DB with product details
            $stmt = $this->conn->prepare('SELECT ci.product_id, ci.quantity AS product_quantity, COALESCE(ci.price_at_time, p.product_price) AS product_price, p.product_name, ci.color, ci.variant, p.product_image, p.product_category FROM cart_items ci JOIN products p ON p.product_id = ci.product_id WHERE ci.cart_id = :cart_id');
            $stmt->execute([':cart_id' => $cart_id]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $display_items[] = [
                    'product_id' => (int)$row['product_id'],
                    'product_name' => $row['product_name'] ?? '',
                    'product_price' => (float)$row['product_price'],
                    'product_image' => $row['product_image'] ?? '',
                    'product_quantity' => (int)$row['product_quantity'],
                    'product_color' => (string)$row['color'],
                    'product_variant' => (string)$row['variant'],
                    'product_category' => $row['product_category'] ?? ''
                ];
            }
        } else if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
            $display_items = array_values($_SESSION['cart']);
        }
        //display visitor cart from database
        return $display_items;
    }

    // Guest cart methods
    public function addToGuestCart(int $product_id, string $product_name, float $product_price, string $product_image, int $product_quantity = 1, string $product_color = '', string $product_variant = '', string $product_category = ''): void {
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        // if item already in cart increase quantity
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]['product_quantity'] += $product_quantity;
        } else {
            // add new item
            $_SESSION['cart'][$product_id] = [
                'product_id' => $product_id,
                'product_name' => htmlspecialchars($product_name, ENT_QUOTES, 'UTF-8'),
                'product_price' => $product_price,
                'product_image' => htmlspecialchars($product_image, ENT_QUOTES, 'UTF-8'),
                'product_quantity' => $product_quantity,
                'product_color' => htmlspecialchars($product_color, ENT_QUOTES, 'UTF-8'),
                'product_variant' => htmlspecialchars($product_variant, ENT_QUOTES, 'UTF-8'),
                'product_category' => htmlspecialchars($product_category, ENT_QUOTES, 'UTF-8')
            ];
        }
        $this->persistGuestCartToDb();
    }

    public function removeFromGuestCart(int $product_id): ?string {
        if (!isset($_SESSION['cart'][$product_id])) {
            return null;
        }
        $product_name = $_SESSION['cart'][$product_id]['product_name'];
        unset($_SESSION['cart'][$product_id]);
        $this->persistGuestCartToDb();
        return $product_name;
        
    }

    public function updateGuestCartQuantities(array $quantities): array {
        $updated_subtotals = [];
        foreach ($quantities as $product_id => $quantity) {
            $product_id = filter_var($product_id, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
            $quantity = filter_var($quantity, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
            if ($product_id && $quantity && isset($_SESSION['cart'][$product_id])) {
                $_SESSION['cart'][$product_id]['product_quantity'] = max(1, $quantity);
                $price = $this->getProductPriceFromDatabase($product_id);
                $updated_subtotals[$product_id] = $quantity * $price;
            }
        }
        $this->persistGuestCartToDb();
        return $updated_subtotals;
    }

    public function getGuestCartItemCount(): int {
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            return 0;
        }
        return array_sum(array_column($_SESSION['cart'], 'product_quantity'));
    }

    /**
     * Saves the current guest's session cart to the database and updates the total.
     * This should be called whenever the guest cart is modified.
     */
    private function persistGuestCartToDb(): void
    {
        // Always recalculate total after a modification.
        $this->calculateTotalCart();

        // Only persist to DB if it's a guest and we have a visitor_id in the session.
        if ($this->isLoggedIn() || !isset($_SESSION['visitor_id'])) {
            return;
        }

        try {
            $visitor_id = $_SESSION['visitor_id'];
            $cart_array = $_SESSION['cart'] ?? [];

            // Convert session associative array to json_string
            $json_cart_data = json_encode($cart_array);

            // Store in database visitor table (data column)
            $stmt = $this->conn->prepare("UPDATE visitors SET data = :cart_data WHERE visitor_id = :visitor_id");
            $stmt->execute([':cart_data' => $json_cart_data, ':visitor_id' => $visitor_id]);
        } catch (PDOException $e) {
            error_log("Error saving visitor cart to database: " . $e->getMessage());
        }
    }

    //Function to get zone id from db 
    public function getShippingZoneId(?string $country = null, ?string $city = null , ?string $address = null , ?string $postCode = null): ?int {
        try{

            // clean and uppercase codes for consistent lookup
            $country = strtoupper(trim($country ?? '')) ;
            $city = strtoupper(trim($city ?? ''));
            $address = strtoupper(trim($address ?? ''));
            $postCode = strtoupper(trim($postCode ?? ''));
            // Find a match based on Country AND Postal Code
            if(!empty($country) && !empty($postCode)){
            $stmt = $this->conn->prepare("SELECT zone_id FROM shipping_zone_rates WHERE country = :country AND city = :city AND address = :address AND postcode = :postcode LIMIT 1");
            $stmt->execute([':country' => $country, ':city' => $city, ':address' => $address, ':postcode' => $postCode]);
            $zone_id = $stmt->fetchColumn();
            if($zone_id){
                return (int)$zone_id;
            }
        }
        //  Fallback: Find a match based on address and city
          if(!empty($city) && !empty($address) ){
        $stmt = $this->conn->prepare("SELECT zone_id FROM shipping_zone_rates WHERE address = :address AND city = :city LIMIT 1");
            $stmt->execute([':address' => $address, ':city' => $city]);
            $zone_id = $stmt->fetchColumn();
            if($zone_id){
                return (int)$zone_id;
        }
    }
        //final fallback: try to find a match based on country only

          if(!empty($country)){
            $stmt = $this->conn->prepare("SELECT zone_id FROM shipping_zone_rates WHERE country = :country AND city is NULL AND address is NULL AND postcode is NULL LIMIT 1");
            $stmt->execute([':country' => $country]);
            $zone_id = $stmt->fetchColumn();
            if($zone_id){
                return (int)$zone_id;
            }
        }
         //  Default: No zone found (International or default zone fallback)
        return null;
        }catch (Throwable $e) { // Catch both PDOException and general Exceptions
        error_log("Error fetching zone id: " . $e->getMessage());
        return null;
    }
}
    
// Shipping Date Methods
    public function getEstimatedShippingDate(int $product_id, ?int $zone_id): ?string{
    try{
       // retrieve shipping info from db
       //retrieve product prepare time info
       $stmt = $this->conn->prepare("SELECT product_prep_days , product_prep_days_max FROM products WHERE product_id = :product_id LIMIT 1");
       $stmt->execute([':product_id' => $product_id]);
       $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row){
            $prep_days = (int)$row['product_prep_days'];
            $prep_days_max = (int)$row['product_prep_days_max'];
        }else{
            //default values if not found
            $prep_days = 2;
            $prep_days_max = 5;
        } 
    // Now fetch shipping rates from db 
    $stmt = $this->conn->prepare("SELECT shipping_days_min, shipping_days_max FROM shipping_zone_rates WHERE zone_id = :zone_id LIMIT 1");
    $stmt->execute([':zone_id' => $zone_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if($row){
        $shipping_days_min = (int)$row['shipping_days_min'];
        $shipping_days_max = (int)$row['shipping_days_max'];
    }else{
        //default values if not found
        $shipping_days_min = 3;
        $shipping_days_max = 7;
    }
    //Calculate Delivery Range dates
    $min_delivery_days = $prep_days + $shipping_days_min;
    $max_delivery_days = $prep_days_max + $shipping_days_max;

    // Create DateTime objects
    $start_date = new DateTime();
    $end_date = new DateTime();
    // Add days to start and end date
    $start_date->modify("+{$min_delivery_days} days");
    $end_date->modify("+{$max_delivery_days} days");
    //format the output 
    $formatted_start = $start_date->format('j F'); // e.g 5 november
    $formatted_end = $end_date->format('j F'); // e.g 9 november

    // Handle cases where the dates fall in different months
    if($start_date->format('F') === $end_date->format('F')){
        //Both dates in the same month: eg  "5 to 9 november"
        $delivery_display = $start_date->format('j') . ' to ' . $end_date->format('j F');
    }
    else{
        // Dates span different months: "30 October to 5 November"
        $delivery_display = $start_date->format('j F') . ' to ' . $end_date->format('j F');
    }
    return $delivery_display;
    }catch (Throwable $e) { // Use Throwable for the most robust catch-all
        error_log("Error calculating shipping date: " . $e->getMessage());
        return null;
    }

}

    /**
     * Calculate estimated delivery range for an order containing multiple products.
     * Strategy: product preparation is assumed to occur in parallel so the order
     * preparation window is the maximum prep window among the items. Shipping
     * days are taken from the zone. The final delivery range is prep_range + shipping_range.
     *
     * @param array $items Array of products. Each element may be either an int product_id
     *                     or an associative array containing at least ['product_id' => int]
     *                     Optionally may include ['product_prep_days'=>int,'product_prep_days_max'=>int]
     * @param int|null $zone_id Shipping zone id (may be null)
     * @return array|null Returns associative array with keys:
     *                    - min_days (int)
     *                    - max_days (int)
     *                    - start_date (Y-m-d)
     *                    - end_date (Y-m-d)
     *                    - display (human readable string)
     *                    or null on error / no items
     */
    public function getEstimatedDeliveryRangeForOrder(array $items, ?int $zone_id): ?array
    {
        try {
            if (empty($items)) return null;

            // Normalize items to extract unique product IDs and any inline prep-days data
            $productIds = [];
            $inlinePreps = [];// product_id => ['min'=>int,'max'=>int]
            foreach ($items as $it) {
                if (is_array($it) && isset($it['product_id'])) {
                    $pid = (int)$it['product_id'];
                    $productIds[] = $pid;
                    if (isset($it['product_prep_days']) || isset($it['product_prep_days_max'])) {
                        $inlinePreps[$pid] = [
                            'min' => isset($it['product_prep_days']) ? (int)$it['product_prep_days'] : null,
                            'max' => isset($it['product_prep_days_max']) ? (int)$it['product_prep_days_max'] : null,
                        ];
                    }
                } elseif (is_int($it) || ctype_digit((string)$it)) {
                    $productIds[] = (int)$it;
                }
            }

            $productIds = array_values(array_filter(array_unique($productIds), fn($v) => $v > 0));
            if (empty($productIds)) return null;

            // Fetch prep days for products in one query for efficiency
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $stmt = $this->conn->prepare("SELECT product_id, product_prep_days, product_prep_days_max FROM products WHERE product_id IN ($placeholders)");
            $stmt->execute($productIds);
            $dbPreps = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $pid = (int)$row['product_id'];
                $dbPreps[$pid] = [
                    'min' => isset($row['product_prep_days']) ? (int)$row['product_prep_days'] : null,
                    'max' => isset($row['product_prep_days_max']) ? (int)$row['product_prep_days_max'] : null,
                ];
            }

            // Compute overall prep min/max (assume parallel preparation -> take maximum)
            $overallPrepMin = 0;
            $overallPrepMax = 0;
            foreach ($productIds as $pid) {
                $min = $inlinePreps[$pid]['min'] ?? ($dbPreps[$pid]['min'] ?? null);
                $max = $inlinePreps[$pid]['max'] ?? ($dbPreps[$pid]['max'] ?? null);
                // If either is null, use sensible defaults
                if ($min === null) $min = 2;
                if ($max === null) $max = 5;
                // Use max across products (parallel prep)
                $overallPrepMin = max($overallPrepMin, (int)$min);
                $overallPrepMax = max($overallPrepMax, (int)$max);
            }

            // Fetch shipping days for the zone (single lookup)
            $stmt = $this->conn->prepare("SELECT shipping_days_min, shipping_days_max FROM shipping_zone_rates WHERE zone_id = :zone_id LIMIT 1");
            $stmt->execute([':zone_id' => $zone_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $shippingDaysMin = (int)$row['shipping_days_min'];
                $shippingDaysMax = (int)$row['shipping_days_max'];
            } else {
                $shippingDaysMin = 3;
                $shippingDaysMax = 7;
            }

            // Final aggregated days
            $minDeliveryDays = $overallPrepMin + $shippingDaysMin;
            $maxDeliveryDays = $overallPrepMax + $shippingDaysMax;

            $startDate = new DateTime();
            $endDate = new DateTime();
            $startDate->modify("+{$minDeliveryDays} days");
            $endDate->modify("+{$maxDeliveryDays} days");

            if ($startDate->format('F') === $endDate->format('F')) {
                $display = $startDate->format('j') . ' to ' . $endDate->format('j F');
            } else {
                $display = $startDate->format('j F') . ' to ' . $endDate->format('j F');
            }

            return [
                'min_days' => (int)$minDeliveryDays,
                'max_days' => (int)$maxDeliveryDays,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'display' => $display,
                'prep' => ['min' => $overallPrepMin, 'max' => $overallPrepMax],
                'shipping' => ['min' => $shippingDaysMin, 'max' => $shippingDaysMax]
            ];
        } catch (Throwable $e) {
            error_log('Error calculating delivery range for order: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Clear all items from user's cart
     */
    public function clearCart(?string $user_id = null): bool {
        try {
            // Use provided user_id or authenticated user
            $userId = $user_id ?? $this->authenticatedUserId;
            
            if (!$userId) {
                error_log('Cannot clear cart: No user ID provided');
                return false;
            }

            // Get cart_id for user
            $stmt = $this->conn->prepare('SELECT cart_id FROM carts WHERE user_id = :user_id LIMIT 1');
            $stmt->execute([':user_id' => $userId]);
            $cart_id = $stmt->fetchColumn();

            if (!$cart_id) {
                // No cart exists for user, nothing to clear
                return true;
            }

            // Delete all items from cart
            $deleteStmt = $this->conn->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id');
            $result = $deleteStmt->execute([':cart_id' => $cart_id]);

            // Update cart's updated_at timestamp
            $updateStmt = $this->conn->prepare('UPDATE carts SET updated_at = :updated_at WHERE cart_id = :cart_id');
            $updateStmt->execute([
                ':cart_id' => $cart_id,
                ':updated_at' => date('Y-m-d H:i:s')
            ]);

            if ($result) {
                error_log('Cart cleared for user: ' . $userId);
                return true;
            } else {
                error_log('Failed to clear cart for user: ' . $userId);
                return false;
            }

        } catch (PDOException $e) {
            error_log('Database error while clearing cart: ' . $e->getMessage());
            return false;
        } catch (Exception $e) {
            error_log('Error clearing cart: ' . $e->getMessage());
            return false;
        }
    }
}

}catch(PDOException $e){
    http_response_code(405);
    error_log("Database error in cart class: " . $e);
     die();
}catch(Exception $e){
    http_response_code(405);
    error_log("An error occured in cart class: " . $e);
     die();
}
