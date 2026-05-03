<?php

 // require config.php which holds BASE_PATH and BASE_URL
require_once __DIR__ . '/../config/config.php';
// Require the main file which handles SESSION and COOKIE management , LOGOUT,LOGGED_IN, VISITORS ETC
require_once INIT_PATH . '/init.php';
// Define the security constant if it hasn't been defined yet.
if (!defined('APP_INIT')) {
    error_log('Attempted direct access to secure utility file: ' . $_SERVER['REQUEST_URI']);
    http_response_code(403); 
    $redirect_url =  ERROR_URL . "?code=403&message= " . urlencode("Access Denied.");
    header("Location: $redirect_url");
    die('403 Access Denied.'); 
}
// Autoload and secure session helper (registers autoloader and starts session)
require_once SERVER_PATH . '/secure_session_csrf_cookie.php';
// Require optimized tracking
require_once __DIR__ . '/../core/OptimizedTracking.php';

// NEW: Use the Cart class
use internal_logic\Cart;
use external_server\Connect;
use core\OptimizedTracking;

// Instantiate DB connection via autoloaded Connect class
$conn = new Connect();

// Initialize OptimizedTracking for analytics
OptimizedTracking::init();
// NEW: Instantiate the Cart class, passing the authenticated user ID
$cart = new Cart($authenticatedUserId);

// If user just logged in and has a session cart, migrate it now so subsequent
// operations use the DB-backed cart. This is a safe, idempotent call.
if ($cart->isLoggedIn() && !empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cart->migrateSessionCartToDb($cart->getAuthenticatedUserId());
}


// -- Cart action handlers ------
// Variable to collect error messages
$error_message = '';

// helper to detect AJAX
function is_ajax()
{
    // existing detection by header
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return true;
    }

    // NEW: also accept an explicit ajax flag from POST/GET/FormData
    if (!empty($_REQUEST['ajax']) && (string)$_REQUEST['ajax'] === '1') {
        return true;
    }

    return false;
}

// SAFETY: if an AJAX POST contains product_id but not the submit name, treat it as add-to-cart
if (is_ajax() && isset($_POST['product_id']) && !isset($_POST['add-to-cart']) && !isset($_POST['remove_product']) && !isset($_POST['edit_quantity']) && !isset($input['update_cart'])) {
    // Validate product_id is a positive integer
    if (filter_var($_POST['product_id'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])) {
        $_POST['add-to-cart'] = '1';
    } else {
        // Handle invalid input
        exit('!!! Invalid product ID');
    }
}

// Handle add to cart (supports AJAX)
// moved to run before any HTML/navbar output so JSON responses are pure JSON
if(isset($_POST['add-to-cart'])){

    // CSRF validation for ajax
    
     validateAjax_formCsrfToken();

    // Limiting Add to cart request per session  Rate limiting
       countAddToCartRequests();

    //csrf token validation and  sanitization
    $csrf_token = htmlspecialchars(trim($_POST['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8');    
    // Validate and sanitize product_id
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
    // Sanitize product_name
    $product_name = htmlspecialchars(trim($_POST['product_name'] ?? ''), ENT_QUOTES, 'UTF-8');    
    if (strlen($product_name) > 255) $product_name = substr($product_name, 0, 255);
    // Validate and sanitize product_price
    $product_price = filter_input(INPUT_POST, 'product_price', FILTER_VALIDATE_FLOAT);
    // Validate and sanitize product_variant 
    $product_variant = htmlspecialchars(trim($_POST['product_variant'] ?? ''), ENT_QUOTES, 'UTF-8');    
     // Validate and sanitize product_variant 
    $product_category = htmlspecialchars(trim($_POST['product_category'] ?? ''), ENT_QUOTES, 'UTF-8');    
    // Validate and sanitize product color
    $product_color = htmlspecialchars(trim($_POST['product_color'] ?? ''), ENT_QUOTES, 'UTF-8');
    // Sanitize product_image: allow full URL (ImageKit) or fallback to filename
    $raw_img = trim($_POST['product_image'] ?? '');
    if (preg_match('#^(https?:)?//#i', $raw_img)) {
      $product_image = htmlspecialchars($raw_img, ENT_QUOTES, 'UTF-8');
    } else {
      $product_image = htmlspecialchars(basename($raw_img), ENT_QUOTES, 'UTF-8');
    }
    if (strlen($product_image) > 1024) $product_image = substr($product_image, 0, 1024);
    // Validate and sanitize product_quantity
    $product_quantity = filter_input(INPUT_POST, 'product_quantity', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]) ?? 1;

    if(!$product_id || !$product_price){
        if(is_ajax()){
            header('Content-Type: application/json');
            echo json_encode(['success'=>false, 'message'=>'!!! Invalid product.']);
            exit;
        } else {
            header("Location: " . INDEX_URL);
            exit;
        }
    }

    // If user is logged in, operate on DB cart and migrate session if needed
    if ($cart->isLoggedIn()) {
        $cart_id = $cart->getOrCreateCartIdForUser($cart->getAuthenticatedUserId());
        $cart->addOrUpdateDbCartItem($cart_id, $product_id, $product_quantity, $product_color, $product_variant, $product_price);
        $total = $cart->calculateTotalCart();
        $items = $cart->getCartItemCountForCart($cart_id);

        // Track conversion event for add-to-cart (server-side fallback)
        OptimizedTracking::trackCartEvent('add', $total, $items, [$product_id]);

        if (is_ajax()) {
            header('Content-Type: application/json');
            // Rotate CSRF TOKEN
            rotateCsrfToken();
            echo json_encode(['success' => true, 'message' => htmlspecialchars($product_name ), 'product_color' =>  htmlspecialchars($product_color), 'product_variant' =>  htmlspecialchars($product_variant), 'product_image' => htmlspecialchars($product_image), 'product_price' => htmlspecialchars($product_price) . htmlspecialchars(' XAF'), 'items' => htmlspecialchars($items), 'total' => htmlspecialchars($total), 'csrf_token' => htmlspecialchars(getCsrfToken())]);
            exit;
        }
        header("Location: " . CART_URL);
        exit;
    }

    // Guest user: use session-based cart
    $cart->addToGuestCart($product_id, $product_name, $product_price, $product_image,$product_quantity, $product_color, $product_variant, $product_category);
    $total = $cart->calculateTotalCart();
    $items = $cart->getGuestCartItemCount();

    if(is_ajax()){
        header('Content-Type: application/json');
        //Rotate CSRF TOKEN
        rotateCsrfToken();
        echo json_encode([
            'success' => true,
            'message' => htmlspecialchars($product_name ) ,
            'product_color' =>  htmlspecialchars($product_color),
            'product_variant' =>  htmlspecialchars($product_variant), 
            'product_image' => htmlspecialchars($product_image), 
            'product_price' => htmlspecialchars($product_price) . htmlspecialchars(' XAF'),
            'items' => $items,
            'csrf_token' => htmlspecialchars(getCsrfToken())
        ]);
        exit;
    } else {
        header("Location: " . CART_URL);
        exit;
    }
}



// Remove product from cart

if(isset($_POST['remove_product'])){
      // GET CSRF TOKEN
      getCsrfToken();

    // CSRF validation
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        if(is_ajax()){
            header('Content-Type: application/json');
            //Rotate CSRF TOKEN
            rotateCsrfToken();
            echo json_encode(['success' => false , 'message' => ' !!! Session expired. Please Refresh page.' ,'csrf_token' => htmlspecialchars(getCsrfToken())] );  //CSRF token is invalid.
                exit;
            } else {
                header('HTTP/1.1 403 Forbidden');
                die('!!! CSRF token invalid.');
            }
        }

    //Rate limiting remove product request per session
    countRemoveProductRequests();

    $product_id = isset($_POST['product_id']) && is_numeric($_POST['product_id']) && $_POST['product_id'] > 0 ? (int)$_POST['product_id'] : null;

    // Sanitize product_color and product_variant
    $product_color = htmlspecialchars(trim($_POST['product_color'] ?? ''), ENT_QUOTES, 'UTF-8');
    $product_variant = htmlspecialchars(trim($_POST['product_variant'] ?? ''), ENT_QUOTES, 'UTF-8');

    if($product_id === null || $product_id <= 0){
        if(is_ajax()){
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => ' !!! Invalid product ID. Attempt to delete a false product' ]);
            exit;
        } else {
            echo "!!! Invalid product ID.";
            exit;
        }
    }

    if ($cart->isLoggedIn()) {
        $cart_id = $cart->getOrCreateCartIdForUser($cart->getAuthenticatedUserId());
        // Try to get product name from products table for message
        $stmt = $conn->prepare('SELECT product_name FROM products WHERE product_id = :product_id LIMIT 1');
        $stmt->execute([':product_id' => $product_id]);
        $product_name = $stmt->fetchColumn() ?: '';
        $cart->removeDbCartItem($cart_id, $product_id, $product_color, $product_variant);
        $total = $cart->calculateTotalCart();
        $items = $cart->getCartItemCountForCart($cart_id);
        
        // Track product removal from cart
        OptimizedTracking::trackCartEvent('remove', $total, $items);
        
        if(is_ajax()){
            header('Content-Type: application/json');
            // Rotate CSRF TOKEN
            rotateCsrfToken();
            echo json_encode([
                'success' => true,
                'message' => htmlspecialchars($product_name,ENT_QUOTES, 'UTF-8') . ' ' . htmlspecialchars($product_color,ENT_QUOTES, 'UTF-8') . ' ' . ' removed from cart.',
                'total' => $total,
                'items' => $items,
                'csrf_token' => htmlspecialchars(getCsrfToken())
            ]);
            exit;
        } else {
            header("Location: " . CART_URL);
            exit;
        }
    }

    // Guest session removal
    $product_name = $cart->removeFromGuestCart($product_id);
    $total = $cart->calculateTotalCart();
    $items = $cart->getGuestCartItemCount();

    if(is_ajax()){
        header('Content-Type: application/json');
        rotateCsrfToken();
        echo json_encode([
            'success' => true,
            'message' =>  htmlspecialchars( $product_name,ENT_QUOTES, 'UTF-8')  . ' ' . htmlspecialchars($product_color,ENT_QUOTES, 'UTF-8') . ' ' . ' removed from cart.',
            'total' => $total,
            'items' => $items,
            'csrf_token' => htmlspecialchars(getCsrfToken())

        ]);
        exit;
    } else {
        header("Location: " . CART_URL);
        exit;
    }
}

// Update multiple cart quantities via AJAX
$input = json_decode(file_get_contents('php://input'), true);
if(isset($input['update_cart']) && is_ajax()){

    // CSRF validation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // check if $input is passing the token to perform update cart function 
          if(!isset($input['csrf_token']) || !hash_equals( $_SESSION['csrf_token'] ,$input['csrf_token'] )){
          $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
          if (!validateCsrfToken($token)) { // if it is not valid error message are display 
           if(is_ajax()){
            header('Content-Type: application/json');
            rotateCsrfToken();
            echo json_encode(['success' => false , 'message' => '!!! Session expired. Please Refresh page.' ,'csrf_token' => htmlspecialchars(getCsrfToken())] );  //CSRF token is invalid.
                exit;
            } else {
        http_response_code(403);
        die("Invalid CSRF token.");
            }
                }
                    }
                        }
    // If CSRF Token valid 
    // Proceed with secure logic

    if(isset($input['quantities']) && is_array($input['quantities'])){
        if ($cart->isLoggedIn()) {
            $cart_id = $cart->getOrCreateCartIdForUser($cart->getAuthenticatedUserId());
            $updated_subtotals = $cart->updateDbCartQuantities($cart_id, $input['quantities']);
            $total = $cart->calculateTotalCart();
            $items = $cart->getCartItemCountForCart($cart_id);
            
            // Track cart update event
            OptimizedTracking::trackCartEvent('update', $total, $items);
            
            header('Content-Type: application/json');
            rotateCsrfToken();
            echo json_encode([
                'success' => true,
                'message' => 'Cart updated successfully',
                'total' => $total,
                'items' => $items,
                'subtotals' => $updated_subtotals,
                'csrf_token' => htmlspecialchars(getCsrfToken())
            ]);
            exit;
        }

        // Guest: session-based update
        $updated_subtotals = $cart->updateGuestCartQuantities($input['quantities']);
        $total = $cart->calculateTotalCart();
        $items = $cart->getGuestCartItemCount();
        
        // Track cart update event
        OptimizedTracking::trackCartEvent('update', $total, $items);

        header('Content-Type: application/json');
        rotateCsrfToken();
        echo json_encode([
            'success' => true,
            'message' =>  ' Cart updated successfully',
            'total' => $total,
            'items' => $items,
            'subtotals' => $updated_subtotals,
            'csrf_token' => htmlspecialchars(getCsrfToken())
        ]);
        exit;
    } else {
         if(is_ajax()){
        header('Content-Type: application/json');
        rotateCsrfToken();
        echo json_encode(['success' => false, 'message' => '!!! Invalid data. Attempt to update a false cart', 'csrf_token' => htmlspecialchars(getCsrfToken())]);
        exit;
         }
            else{
                echo "!!! Invalid data. Attempt to update a false cart";
                exit;
            }
    }

}  

// create slug function for product names to be used in URLs
function createSlug($string){
  $string = strtolower($string); // Convert to lowercase
  $string = preg_replace('/[^a-z0-9]+/i', '-', $string); // Replace non-alphanumeric characters with hyphens
  $string = preg_replace('/^-+|-+$/', '', $string); // Remove leading and trailing hyphens
  return rtrim($string, '-'); // Remove any trailing hyphens
}
   
// include navbar only for normal (non-AJAX) page rendering
if (!is_ajax()) {
   require_once TEMPLATES_PATH. '/navbar.php';
}


// Prepare display items and total using the Cart class
$display_items = $cart->getDisplayItems();
$display_total = $cart->calculateTotalCart();

//log js errors 
//outputJavaScriptErrorHandler();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="csrf-token" content="<?php echo htmlspecialchars(getCsrfToken()); ?>">
  <title>Cart - Universal Store</title>

  <!-- bootstrap CDN -->
  <link rel="stylesheet" href="<?php echo universalstore_URL; ?>/assets/includes/bootstrap-5.3.7-dist/css/bootstrap.min.css">

  <!-- Font Awesome CDN -->
  <link rel="stylesheet" href="<?php echo universalstore_URL; ?>/assets/includes/fontawesome-free-5.15.4-web/css/all.css">
  <link rel="stylesheet" href="<?php echo universalstore_URL; ?>/assets/includes/fontawesome/fontawesome-free-6.7.2-web/css/all.css">

  <!-- custom css file -->
  <link rel="stylesheet" href="<?php echo universalstore_URL; ?>/assets/css/style1.css">
  <link rel="stylesheet" href="<?php echo universalstore_URL; ?>/assets/css/tailwindcss.css">
    <link href="https://ik.imagekit.io/univ35tore/universal_store/imgs/UNIVERSAL%20STORE.jpg" rel="icon">
</head>
<body class="color-fade-bg">

<!-- Promo slider inserted before first section -->
<section class="promo-slider-section py-4 mt-5">
  <div class="promo-slider position-relative">
    <div class="promo-slides overflow-hidden shadow-lg" style="border-radius: 300px;" id="promoSlides" aria-live="polite" role="region" aria-label="Promotions">
      <div class="promo-track d-flex" id="promoTrack" style="transform: translateX(0%);">
        <div class="promo-slide d-flex align-items-center justify-content-between p-4 text-white">
          <div>
            <h3 class="mb-2">Purchase 5+ items</h3>
            <p class="mb-2 lead">Get 10% off your entire order. Stock up & save today!</p>
            <a href="<?php echo universalstore_URL; ?>/shop" class="btn btn-light btn-sm">Shop Now</a>
          </div>
          <img src="assets/imgs/promo1.webp" alt="promo" class="d-none d-md-block" style="width:160px;opacity:0.95;">
        </div>

        <div class="promo-slide d-flex align-items-center justify-content-between p-4 text-white">
          <div>
            <h3 class="mb-2">Weekend Flash Deal</h3>
            <p class="mb-2 lead">Extra 15% off selected accessories. Limited time only.</p>
            <a href="<?php echo universalstore_URL; ?>/shop" class="btn btn-light btn-sm">Grab Deal</a>
          </div>
          <img src="assets/imgs/promo2.webp" alt="promo" class="d-none d-md-block" style="width:160px;opacity:0.95;">
        </div>

        <div class="promo-slide d-flex align-items-center justify-content-between p-4 text-white">
          <div>
            <h3 class="mb-2">Free Shipping</h3>
            <p class="mb-2 lead">On orders above 50,000 FCFA — faster delivery to your door.</p>
            <a href="<?php echo universalstore_URL; ?>/shop" class="btn btn-light btn-sm">Browse</a>
          </div>
          <img src="assets/imgs/promo3.webp" alt="promo" class="d-none d-md-block" style="width:160px;opacity:0.95;">
        </div>

        <div class="promo-slide d-flex align-items-center justify-content-between p-4 text-white">
          <div>
            <h3 class="mb-2">Bundle & Save</h3>
            <p class="mb-2 lead">Pick any 3 accessories and get 20% off the bundle.</p>
            <a href="<?php echo universalstore_URL; ?>/shop" class="btn btn-light btn-sm">See Bundles</a>
          </div>
          <img src="assets/imgs/promo4.webp" alt="promo" class="d-none d-md-block" style="width:160px;opacity:0.95;">
        </div>

        <div class="promo-slide d-flex align-items-center justify-content-between p-4 text-white">
          <div>
            <h3 class="mb-2">Loyalty Bonus</h3>
            <p class="mb-2 lead">Earn double points on purchases this month.</p>
            <a href="<?php echo universalstore_URL; ?>/shop" class="btn btn-light btn-sm">Start Earning</a>
          </div>
          <img src="assets/imgs/promo6.webp" alt="promo" class="d-none d-md-block" style="width:160px;opacity:0.95;">
        </div>
      </div>
    </div>

    <!-- indicators -->
    <div class="promo-indicators d-flex gap-2 justify-content-center mt-3" id="promoIndicators" aria-hidden="false"></div>
  </div>
</section>

<?php
// If cart is empty, show a nice empty state and stop showing tables/totals.
if (empty($display_items) || !is_array($display_items) || count($display_items) === 0): ?>
  <section class="cart my-5 py-5 mx-4">
    <div class="container mx-0 mb-5">
      <h2 class="font-weight-800">Your Cart</h2>
      <hr class="mx-0">
   </div>
   <div class="row  ">
    <div class="container col-lg-8 col-md-7 col-sm-12 align-items-center mb-4 justify-content-center" style="min-height:45vh;">
      <div class="cart-empty text-center p-5 rounded ">
        <div class="icon mb-3">
          <i class="fas fa-shopping-basket fa-3x"></i>
        </div>
        <h2 class="mb-2">Your cart is currently empty</h2>
        <p class="mb-4 text-muted">Browse our shop and add products to your cart. You'll see them here.</p>
        <a href="<?php echo universalstore_URL; ?>/shop" class="btn btn-lg btn-primary cart-shop-btn">Return to Shop</a>
      </div>
    </div>
     <div class="container col-lg-4 col-md-5 col-sm-12 align-items-center justify-content-center" style="min-height:20vh;">
      <div class="cart-empty text-center p-5 rounded ">
        <div class="icon mb-3">
          <i class="fa-solid fa-user fa-3x"></i>
        </div>
        <h2 class="mb-2">Enhanced your shopping with us</h2>
        <p class="mb-4 text-muted">Don't miss our new products, daily discount and discover our best products on sales. </p>
        <a href="#" class="btn btn-lg btn-primary cart-signin-btn mx-2 mb-1">Sign in to your account</a>
       <a href="#" class="btn btn-lg btn-primary cart-signup-btn mx-2">Sign up now</a>
      </div>
    </div>
    </div>
  </section>

<?php else: ?>

  <!-- cart (non-empty) -->
   <section class="cart my-5 py-5 mx-4" style="position: relative;">
  <div id="cart-loading" style="display: none;"><div class="spinner"></div></div>
  <div class="container mx-0">
      <h2 class="font-weight-800">Your Cart Details</h2>
      <hr class="mx-0">
   </div>
  <div id="cart-message" class="alert alert-success mt-4" style="display: none;"></div>
  <?php if(isset($_SESSION['cart_error'])): ?>
  <div class="alert alert-danger mt-4"><?php echo htmlspecialchars($_SESSION['cart_error']); ?></div>
  <?php unset($_SESSION['cart_error']); ?>
  <?php endif; ?>
  <?php if(isset($_SESSION['cart_message'])): ?>
  <div class="alert alert-success mt-4"><?php echo htmlspecialchars($_SESSION['cart_message']); ?></div>
  <?php unset($_SESSION['cart_message']); ?>
  <?php endif; ?>
    <div id="cart-error" class="alert alert-danger mt-4" style="display: none;"></div>

      <div class="row mt-5">
  <div class="container col-lg-7 col-md-12 col-sm-12">
    <!-- Cart error display -->
  <div id="cart-error" class="alert alert-danger mt-4" style="display: none;"></div>

  <?php foreach ($display_items as $value) { ?>
    <!-- Delivery Date -->
<?php $delivery_date = $shipping_zone_id ? $cart->getEstimatedShippingDate($value['product_id'], $shipping_zone_id) : null ; ?>
      
  <div class="card shadow-sm rounded-4 border-0 mb-4">
    <input type="hidden" name="csrf_token" id="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken()); ?>"/>
    <div class="card-body">
      <div class="row align-items-center gy-3">
        <!-- Product image -->
        <div class="col-auto col-md-2" onclick="window.location.href='<?php echo universalstore_URL; ?>/<?php echo createSlug(isset($value['product_category']) ? $value['product_category'] : ''); ?>/<?php echo createSlug(isset($value['product_id'])) ? (int)$value['product_id'] : 0; ?>/<?php echo createSlug(isset($value['product_name']) ? urlencode($value['product_name']) : ''); ?>/<?php echo createSlug(isset($value['product_color']) ? urlencode($value['product_color']) : ''); ?>';" style="cursor:pointer;">
          <?php
            $pi = htmlspecialchars($value['product_image'] ?? '', ENT_QUOTES, 'UTF-8');
            $img_src = (preg_match('#^(https?:)?//#i', $pi)) ? $pi : (universalstore_URL . '/assets/imgs/' . $pi);
          ?>
          <img src="<?php echo $img_src; ?>" alt="product" class="img-fluid rounded p-image" >
        </div>

        <!-- Product details -->
        <div class="col-md-7 col-6 p-details">
          <h5 class="mb-1 fs-5 font-weight-800 font-rubik" onclick="window.location.href='<?php echo universalstore_URL; ?>/<?php echo isset($value['product_category']) ? $value['product_category'] : ''; ?>/<?php echo isset($value['product_id']) ? (int)$value['product_id'] : 0; ?>/<?php echo isset($value['product_name']) ? urlencode($value['product_name']) : ''; ?>/<?php echo isset($value['product_color']) ? urlencode($value['product_color']) : ''; ?>';" style="cursor: pointer;" onmouseover="this.style.color='#353daa'" onmouseout="this.style.color='black'"><?php echo isset($value['product_name']) ? htmlspecialchars($value['product_name']) : '';?> <?php echo isset($value['product_color']) ? htmlspecialchars($value['product_color']) : '';?></h5>
          <div class="badge color-fade-bg color-black font-weight-600"><?php echo isset($value['product_variant']) ? htmlspecialchars($value['product_variant']) : '';?></div>
          <p class="font-weight-600"><?php echo isset($value['product_price']) ? htmlspecialchars((int)$value['product_price']): 0 ;?><span> FCFA</span> </p>
          <div class="text-decoration-line-through text-muted small">200,000 FCFA </div>
          <div class="mb-2">
              <span class="badge bg-success">save 50,000 FCFA</span>
            </div>
          <div class="border border-1 rounded-2 color-black mb-2 p-state">In Good State</div>
        </div>

        <!-- Price and actions -->
        <div class="col-md-3 col-12 text-md-end">
          <div class="d-flex flex-column align-items-md-end align-items-start">
            <div class="mb-2">
              <h5 class="mb-1 fw-bold">Subtotal Price</h5>
              <div>
              <span ></span>
              <span class="fs-8 font-weight-600" id="subtotal-<?php echo htmlspecialchars((int)$value['product_id']); ?>"><?php echo htmlspecialchars(($value['product_quantity'] * $value['product_price']) . ' FCFA', ENT_QUOTES, 'UTF-8'); ?></span>
              <span class="product-price"></span>
            </div>
            <!--- Delivery Date ---->
            <?php if($cart->isLoggedIn()):?>
          <?php if($delivery_date): ?>
               <div class="text-muted small">Product delivery date:</div>
               <div class="text-muted small font-weight-600"> <?php echo htmlspecialchars($delivery_date); ?></div>
               <?php else: ?>
                <div class="text-muted small">Product delivery date:</div>
                <div class="text-muted small font-weight-600"><a href="<?php echo htmlspecialchars(PROFILE_URL); ?>" class="color-black money-back">Provide your delivery address for calculation</a></div>
                <?php endif; ?>
                <?php else:?>
                <div class="text-muted small">Product delivery date:</div>
                <div class="text-muted small font-weight-600">Login to see delivery date</div>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2 align-items-center"><small>Quantity:</small>
             <div class="custom-buttons">
                  <button type="button" class="decrement-btn">-</button>
                  <input type="number" name="quantities[<?php echo htmlspecialchars($value['product_id']); ?>]" value="<?php echo isset($value['product_quantity']) ? htmlspecialchars((int)$value['product_quantity']): 1; ?>" min="1" max="100"/>
                  <button type="button" class="increment-btn">+</button>
              </div>
              <button class="btn btn-outline-danger remove-btn" data-product-id="<?php echo isset($value['product_id'])? htmlspecialchars( (int)$value['product_id']) : 0;?>" data-color="<?php echo htmlspecialchars($value['product_color']); ?>" data-variant="<?php echo htmlspecialchars($value['product_variant']); ?>" type="button">Delete</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php }?>
    <div class=" update_cart_btn mt-4 mb-5">
      <button id="update-cart-btn" type="button">Update Cart</button>
  </div>
<div class="px-auto mx-auto mb-4">
 <h4> Your advantage at each purchase: </h4>
</div>
   <div class="container banner-cart mb-5 rounded-4 shadow-sm p-4">
    
      <div class="inner">
        <h5 class="color-black font-size-20 font-rubik font-weight-800"><i class="fa-solid fa-truck-fast me-2"></i>Free shipping</h5>
        <p class="color-black font-size-14">Standard Fee sheeping  offered</p>
      </div>
      <div class="inner money-back-trigger" role="button" tabindex="0" data-bs-toggle="modal" data-bs-target="#moneyBackModal" style="cursor:pointer;" aria-controls="cartItemModal" aria-label="Open Money Back details">
        <h5 class="color-black font-size-20 font-rubik font-weight-800"><i class="fa-solid fa-wallet me-2"></i><a class="color-black money-back">100% Money Back</a></h5>
        <p class="color-black font-size-14">Free return under 15 days</p>
      </div>
      <div class="inner1">
        <h5 class="color-black font-size-20 font-rubik font-weight-800"><i class="fa-solid fa-clock-rotate-left me-2"></i><a href="contact.php" class="color-black money-back">Open 24/7</a></h5>
        <p class="color-black font-size-14">Client service at your disposal</p>
      </div>
      <div class="inner1 warranty-trigger" role="button" tabindex="0" data-bs-toggle="modal" data-bs-target="#warrantyModal" style="cursor:pointer;" aria-controls="cartItemModal" aria-label="Open Warranty details">
        <h5 class="color-black font-size-20 font-rubik font-weight-800"><i class="fa-solid fa-award me-2"></i><a class="color-black money-back">100% Payment Secure</a></h5>
        <p class="color-black font-size-14">2-month commercial guarantee</p>
      </div>
    </div>

  </div>
  
  <div class="container col-lg-4 col-md-12 col-12 pt-3 sub-cart">
     <h3 class="mb-3">Cart Summary</h3>
     <?php foreach ($display_items as $value) { ?>
     <div class="py-3">
      <div class="sub-cart-items mb-3" data-product-id="<?php echo htmlspecialchars($value['product_id']); ?>">
        <div class="d-flex mb-3">
        <div class="border border-1 rounded cartCount" style="height:fit-content;">
          <div class="image-wrapper" style="position:relative; display:inline-block;">
            <span class="cart-count-badge" aria-hidden="true"><?php echo isset($value['product_quantity']) ? (int)$value['product_quantity'] : 1; ?></span>
            <?php
              $pi2 = htmlspecialchars($value['product_image'] ?? '', ENT_QUOTES, 'UTF-8');
              $img_src2 = (preg_match('#^(https?:)?//#i', $pi2)) ? $pi2 : (universalstore_URL . '/assets/imgs/' . $pi2);
            ?>
            <img src="<?php echo $img_src2; ?>" alt="product" class="img-fluid mx-1" style="width:30px; height:50px; display:block;"/>
          </div>
        </div>
        <div class="flex-column gap-5">
        <span class="ms-2 mb-1 wrap font-weight-600 font-rubik"><?php echo isset($value['product_name']) ? htmlspecialchars($value['product_name']) : '';?> <?php echo isset($value['product_color']) ? htmlspecialchars($value['product_color']) : '';?> <?php echo isset($value['product_variant']) ? htmlspecialchars($value['product_variant']) : '';?></span>

        <div class="font-weight-600 ms-2 mt-2"><small>Delivery</small></div>
    </div>    
    </div>
    <div class="flex-column">
      <p><?php echo isset($value['product_price']) ? htmlspecialchars((int)$value['product_price']): 0 ;?><span></span> FCFA</p>
      <div class="font-rubik p-offer"><small>Offered</small></div>
      </div>
    </div>
    <?php }?>
    <div class="sub-total mb-4">
     <h4>Subtotal</h4>
      <p><?php echo isset($display_total) ? htmlspecialchars($display_total) . ' FCFA' : '0 FCFA'; ?></p>
  </div>
  <div class="total mb-4">
     <h4>Total</h4>
      <p><?php echo isset($display_total) ? htmlspecialchars($display_total) . ' FCFA' : '0 FCFA'; ?></p>
  </div>
  <div class="checkout">
      <form method="post" action="checkout.php">
     <input type="hidden" name="product_id" value="<?php echo isset($value['product_id'])? htmlspecialchars( (int)$value['product_id']) : 0;?>"/>
      <button type="submit" class="checkout-btn" name="checkout" value="Proceed To checkout" >Proceed To checkout</button>
      </form>
  </div>
  </div>
  </div>

     </section>

<?php endif; ?>
<!-- Money Back details modal -->
<style>
  /* Small, self-contained styles to ensure the modal visual matches the attachment and is responsive */
  @media (max-width: 575.98px) {
    .moneyback-modal-title { font-size: 1.05rem; }
  }
  .cart-item-icon { width:44px; height:44px; display:flex; align-items:center; justify-content:center; font-size:1.25rem; color:#2b6cb0; }
  .cart-back-item { gap:12px; }
  /* Cart summary image badge */
  .image-wrapper { position: relative; display: inline-block; }
  .cart-count-badge {
    position: absolute;
    top: -6px;
    left: -6px;
    transform: translate(0, 0);
    background: #dc3545;
    color: #fff;
    border-radius: 999px;
    padding: 3px 7px;
    font-size: 0.65rem;
    line-height: 1;
    font-weight: 700;
    box-shadow: 0 1px 3px rgba(0,0,0,0.25);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
  }
</style>

<div class="modal fade" id="moneyBackModal" tabindex="-1" aria-labelledby="moneyBackModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content rounded-4">
      <div class="modal-header border-0">
        <h3 class="modal-title moneyback-modal-title" id="moneyBackModalLabel">Free return within 15 days</h3>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>

      <div class="modal-body">
        <div class="container-fluid">
          <div class="row gy-3 align-items-center">
            <!-- Illustration column -->
            <div class="col-12 col-md-6 text-center">
              <img src="assets/imgs/item-back.svg"  class="rounded-lg w-full max-w-xs mx-auto" src="../assets/imgs/not-found-block.svg" alt="Money Back Illustration" style="max-height: 200px;"/>
              <!-- Inline, responsive illustration -->
              
            </div>

            <!-- Text column -->
            <div class="col-12 col-md-6">
              <div class="d-flex mb-3 cart-back-item">
                <div class="cart-item-icon bg-light rounded-circle me-3">
                  <i class="fa-regular fa-thumbs-down" aria-hidden="true"></i>
                </div>
                <div>
                  <h6 class="mb-1 color-black">Not such a big fan of your item?</h6>
                  <p class="mb-0 text-muted">Yes, no, yes, no, maybe yes, finally no... 
                    you have 30 days to change your mind, no justification needed.</p>
                </div>
              </div>

              <div class="d-flex mb-3 cart-back-item">
                <div class="cart-item-icon bg-light rounded-circle me-3">
                  <i class="fa-regular fa-clock" aria-hidden="true"></i>
                </div>
                <div>
                  <h6 class="mb-1 color-black">Hyyper easy returns</h6>
                  <p class="mb-0 text-muted">Contact us via your Universal Store account to start a return procedure.</p>
                </div>
              </div>

              <div class="d-flex cart-back-item">
                <div class="cart-item-icon bg-light rounded-circle me-3">
                  <i class="fa-solid fa-wallet" aria-hidden="true"></i>
                </div>
                <div>
                  <h6 class="mb-1 color-black">And you get your money back</h6>
                  <p class="mb-0 text-muted">As soon as the returned item is received, you will get your refund within 3 working days.</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Warranty details modal -->
<div class="modal fade" id="warrantyModal" tabindex="-1" aria-labelledby="warrantyModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content rounded-4">
      <div class="modal-header border-0">
        <h3 class="modal-title moneyback-modal-title" id="warrantyModalLabel">2-Month Commercial Warranty</h3>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>

      <div class="modal-body">
        <div class="container-fluid">
          <div class="row gy-3 align-items-center">
            <!-- Illustration column -->
            <div class="col-12 col-md-6 text-center">
              <img src="assets/imgs/warranty-modal.svg"  class="rounded-lg w-full max-w-xs mx-auto" alt="Warranty Illustration" style="max-height: 200px;"/>
              <!-- Inline, responsive illustration -->
            </div>

            <!-- Text column -->
            <div class="col-12 col-md-6">
              <div class="d-flex mb-3 cart-back-item">
                <div class="cart-item-icon bg-light rounded-circle me-3">
                  <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                </div>
                <div>
                  <h6 class="mb-1 color-black">What does the warranty cover?</h6>
                  <p class="mb-0 text-muted">The warranty covers any manufacturing defects or malfunctions that occur under normal use within the warranty period.</p>
                </div>
              </div>
              <div class="d-flex mb-3 cart-back-item">
                <div class="cart-item-icon bg-light rounded-circle me-3">
                  <i class="fa-solid fa-tools" aria-hidden="true"></i>
                </div>
                <div>
                  <h6 class="mb-1 color-black">How to claim the warranty?</h6>
                  <p class="mb-0 text-muted">To claim the warranty, please contact our customer service with your order details and a description of the issue.</p>
                </div>
              </div>
              <div class="d-flex cart-back-item">
                <div class="cart-item-icon bg-light rounded-circle me-3">
                  <i class="fa-solid fa-clock" aria-hidden="true"></i>
                </div>
                <div>
                  <h6 class="mb-1 color-black">Warranty Duration</h6>
                  <p class="mb-4 text-muted">The warranty is valid for 2 months from the date of purchase.</p>
                </div>
              </div>
              <div class="d-flex cart-back-item"> 
                <div class="cart-item-icon bg-light rounded-circle me-3">
                  <i class="fa-solid fa-exclamation-triangle" aria-hidden="true"></i>
                </div>
                <div>
                  <h6 class="mb-1 color-black">What is not covered?</h6>
                  <p class="mb-4 text-muted">The warranty does not cover damages caused by accidents, misuse, or unauthorized repairs.</p>
                </div>
              </div>
              <div class="d-flex cart-back-item"> 
                <div class="cart-item-icon bg-light rounded-circle me-3">
                </div>
                <div>
                  <hr>
                  <p>* Warranty exclusions apply. To learn more about the conditions and exclusions of the commercial guarantee, <a href="#">go here.</a>
                    This commercial guarantee is in addition to the applicable legal warranties of conformity and hidden defects. To learn
                     more about these legal warranties, <a href="#">go here.</a>*</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>


<script>
  // Make trigger keyboard-friendly
  (function() {
    const triggers = document.querySelectorAll('.warranty-trigger');
    triggers.forEach(el => {
      el.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          el.click();
        }
      });
    });
  })();

  // Make trigger keyboard-friendly
  (function() {
    const triggers = document.querySelectorAll('.money-back-trigger.cart-trigger');
    triggers.forEach(el => {
      el.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          el.click();
        }
      });
    });
  })();
</script>

<script src="<?php echo universalstore_URL; ?>/assets/js/numberInput.js"></script>
<script src="<?php echo universalstore_URL; ?>/assets/js/error-logger.js"></script>
<script src="<?php echo universalstore_URL; ?>/assets/js/carts.js"></script>
<script src="<?php echo universalstore_URL; ?>/assets/js/slide.js"></script>
<script src="<?php echo universalstore_URL; ?>/assets/includes/bootstrap-5.3.7-dist/js/bootstrap.bundle.min.js"></script>

<?php include_once TEMPLATES_PATH . '/footer.php';?>
</body>
</html>


