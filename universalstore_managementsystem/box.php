<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <!-- bootstrap CDN -->
  <link rel="stylesheet" href="assets/includes/bootstrap-5.3.7-dist/css/bootstrap.min.css">

  <!-- Font Awesome CDN -->
  <link rel="stylesheet" href="assets/includes/fontawesome-free-5.15.4-web/css/all.css">
  <link rel="stylesheet" href="assets/includes/fontawesome/fontawesome-free-6.7.2-web/css/all.css">

  <!-- custom css file -->
  <link rel="stylesheet" href="assets/css/styling.css">
    <link href="assets/imgs/apple.jpeg" rel="icon">
</head>
<body>

<main class="container my-5">
  <h3 class="mb-4">Détails de votre panier</h3>

  <div class="card shadow-sm rounded-3">
    <div class="card-body">
      <div class="row align-items-center gy-3">
        <!-- Product image -->
        <div class="col-auto col-md-2" onclick="window.location.href='<?php echo universalstore_URL; ?>/single_product?product_id='.$value['product_id'];' '?>'; ">
          <?php
            $pi = htmlspecialchars($value['product_image'] ?? '', ENT_QUOTES, 'UTF-8');
            $img_src = (preg_match('#^(https?:)?//#i', $pi)) ? $pi : (universalstore_URL . '/assets/imgs/' . $pi);
          ?>
          <img src="<?php echo $img_src; ?>" alt="product" class="img-fluid rounded" style="max-width:110px;">
        </div>

        <!-- Product details -->
        <div class="col-md-7 col-12">
          <h5 class="mb-1 font-weight-800 font-rubik" onclick="window.location.href='<?php echo universalstore_URL; ?>/single_product?product_id='.$value['product_id'];' '?>'; " style="cursor: pointer;" onmouseover="this.style.color='#353daa'," onmouseout="this.style.color='black'"><?php echo isset($value['product_name']) ? htmlspecialchars($value['product_name']) : '';?></h5>
          <p class="font-weight-600"><?php echo isset($value['product_price']) ? htmlspecialchars((int)$value['product_price']): 0 ;?><span></span> FCFA</p>
          <div class="text-decoration-line-through text-muted small">200,000 FCFA </div>
          <div class="mb-2">
              <span class="badge bg-success">save 50,000 FCFA</span>
            </div>
          <span class="badge border border-1 color-black mb-2">In Good State</span>
        </div>

        <!-- Price and actions -->
        <div class="col-md-3 col-12 text-md-end">
          <div class="d-flex flex-column align-items-md-end align-items-start">
            <div class="mb-2">
              <h4 class="mb-1 fw-bold">Subtotal Price</h4>
              <span class="fs-5 fw-bold"><?php echo htmlspecialchars($value['product_id']); ?>"><?php echo htmlspecialchars(($value['product_quantity'] * $value['product_price']) . ' FCFA', ENT_QUOTES, 'UTF-8'); ?></span>
               <div class="text-muted small">Product delivery date:</div>
               <div class="text-muted small font-weight-600"> 10 to 13 November. Offered</div>
            </div>
            <div class="d-flex gap-2 align-items-center"><small>Quantity:</small>
             <div class="custom-buttons">
                  <button type="button" class="decrement-btn">-</button>
                  <input type="number" name="quantities[<?php echo htmlspecialchars($value['product_id']); ?>]" value="<?php echo isset($value['product_quantity']) ? htmlspecialchars((int)$value['product_quantity']): 1; ?>" min="1" max="100"/>
                  <button type="button" class="increment-btn">+</button>
              </div>
              <button class="btn btn-outline-danger btn-sm remove-btn" data-product-id="<?php echo isset($value['product_id'])? htmlspecialchars( (int)$value['product_id']) : 0;?>" type="button">Supprimer</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  
    <div class=" update_cart_btn mt-4">
      <button id="update-cart-btn" type="button">Update Cart</button>
  </div>
</main>

</body>
</html>
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
require_once __DIR__ . '/server/secure_session_csrf_cookie.php';

// NEW: Use the Cart class
use internal_logic\Cart;

// Instantiate DB connection via autoloaded Connect class
$conn = new \external_server\Connect();

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
    $csrf_token = filter_input(INPUT_POST, 'csrf_token', FILTER_SANITIZE_STRING) ?? '';
    // Validate and sanitize product_id
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
    // Sanitize product_name
    $product_name = filter_input(INPUT_POST, 'product_name', FILTER_SANITIZE_STRING) ?? '';
    if (strlen($product_name) > 255) $product_name = substr($product_name, 0, 255);
    // Validate and sanitize product_price
    $product_price = filter_input(INPUT_POST, 'product_price', FILTER_VALIDATE_FLOAT);
    // Sanitize product_image: allow full URL (ImageKit) or fallback to filename
    $raw_img = trim(filter_input(INPUT_POST, 'product_image', FILTER_DEFAULT) ?? '');
    if (preg_match('#^(https?:)?//#i', $raw_img)) {
      $product_image = $raw_img;
    } else {
      $product_image = basename($raw_img);
    }
    $product_image = htmlspecialchars($product_image, ENT_QUOTES, 'UTF-8');
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
        $cart->addOrUpdateDbCartItem($cart_id, $product_id, $product_quantity, $product_price);
        $total = $cart->calculateTotalCart();
        $items = $cart->getCartItemCountForCart($cart_id);
        if (is_ajax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => '"' . htmlspecialchars($product_name) . '" added to cart', 'items' => $items, 'total' => $total]);
            exit;
        }
        header("Location: " . CART_URL);
        exit;
    }

    // Guest user: use session-based cart
    $cart->addToGuestCart($product_id, $product_name, $product_price, $product_image, $product_quantity);
    $total = $cart->calculateTotalCart();
    $items = $cart->getGuestCartItemCount();

    if(is_ajax()){
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => '"' . htmlspecialchars($product_name ) . '"' . ' added to cart',
            'items' => $items,
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
    if(!isset($_POST['csrf_token']) || !hash_equals( $_SESSION['csrf_token'] ,$_POST['csrf_token'] )){
        if(is_ajax()){
            header('Content-Type: application/json');
            echo json_encode(['success' => false , 'message' => ' !!! CSRF token is invalid.'] );
                exit;
            } else {
                header('HTTP/1.1 403 Forbidden');
                die('!!! CSRF token invalid.');
            }
        }

    //Rate limiting remove product request per session
    countRemoveProductRequests();

    $product_id = isset($_POST['product_id']) && is_numeric($_POST['product_id']) && $_POST['product_id'] > 0 ? (int)$_POST['product_id'] : null;

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
        $cart->removeDbCartItem($cart_id, $product_id);
        $total = $cart->calculateTotalCart();
        $items = $cart->getCartItemCountForCart($cart_id);
        if(is_ajax()){
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => htmlspecialchars($product_name,ENT_QUOTES, 'UTF-8') . ' removed from cart.',
                'total' => $total,
                'items' => $items,
                'csrf_token' => getCsrfToken()
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
        echo json_encode([
            'success' => true,
            'message' =>  htmlspecialchars( $product_name,ENT_QUOTES, 'UTF-8')  . ' removed from cart.',
            'total' => $total,
            'items' => $items,
            'csrf_token' => getCsrfToken()

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
            echo json_encode(['success' => false , 'message' => '!!! CSRF token is invalid.'] );
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
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Cart updated successfully',
                'total' => $total,
                'items' => $items,
                'subtotals' => $updated_subtotals,
                'csrf_token' => getCsrfToken()
            ]);
            exit;
        }

        // Guest: session-based update
        $updated_subtotals = $cart->updateGuestCartQuantities($input['quantities']);
        $total = $cart->calculateTotalCart();
        $items = $cart->getGuestCartItemCount();

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' =>  ' Cart updated successfully',
            'total' => $total,
            'items' => $items,
            'subtotals' => $updated_subtotals,
            'csrf_token' => getCsrfToken()
        ]);
        exit;
    } else {
         if(is_ajax()){
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => '!!! Invalid data. Attempt to update a false cart']);
        exit;
         }
            else{
                echo "!!! Invalid data. Attempt to update a false cart";
                exit;
            }
    }

}  
   
// include navbar only for normal (non-AJAX) page rendering
if (!is_ajax()) {
   require_once TEMPLATES_PATH. '/navbar.php';
}


// Prepare display items and total using the Cart class
$display_items = $cart->getDisplayItems();
$display_total = $cart->calculateTotalCart();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
  <title>Cart - Universal Store</title>

  <!-- bootstrap CDN -->
  <link rel="stylesheet" href="assets/includes/bootstrap-5.3.7-dist/css/bootstrap.min.css">

  <!-- Font Awesome CDN -->
  <link rel="stylesheet" href="assets/includes/fontawesome-free-5.15.4-web/css/all.css">
  <link rel="stylesheet" href="assets/includes/fontawesome/fontawesome-free-6.7.2-web/css/all.css">

  <!-- custom css file -->
  <link rel="stylesheet" href="assets/css/styling.css">
    <link href="assets/imgs/apple.jpeg" rel="icon">
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
     
  <div class="card shadow-sm rounded-4 border-0 mb-4">
    <div class="card-body">
      <div class="row align-items-center gy-3">
        <!-- Product image -->
        <div class="col-auto col-md-2" onclick="window.location.href='<?php echo universalstore_URL; ?>/single_product?product_id='.$value['product_id'];' '?>'; ">
          <?php
            $pi2 = htmlspecialchars($value['product_image'] ?? '', ENT_QUOTES, 'UTF-8');
            $img_src2 = (preg_match('#^(https?:)?//#i', $pi2)) ? $pi2 : (universalstore_URL . '/assets/imgs/' . $pi2);
          ?>
          <img src="<?php echo $img_src2; ?>" alt="product" class="img-fluid rounded" style="max-width:100px;">
        </div>

        <!-- Product details -->
        <div class="col-md-7 col-12">
          <h5 class="mb-1 font-weight-800 font-rubik" onclick="window.location.href='<?php echo universalstore_URL; ?>/single_product?product_id='.$value['product_id'];' '?>'; " style="cursor: pointer;" onmouseover="this.style.color='#353daa'" onmouseout="this.style.color='black'"><?php echo isset($value['product_name']) ? htmlspecialchars($value['product_name']) : '';?></h5>
          <p class="font-weight-600"><?php echo isset($value['product_price']) ? htmlspecialchars((int)$value['product_price']): 0 ;?><span></span> FCFA</p>
          <div class="text-decoration-line-through text-muted small">200,000 FCFA </div>
          <div class="mb-2">
              <span class="badge bg-success">save 50,000 FCFA</span>
            </div>
          <span class="badge border border-1 color-black mb-2">In Good State</span>
        </div>

        <!-- Price and actions -->
        <div class="col-md-3 col-12 text-md-end">
          <div class="d-flex flex-column align-items-md-end align-items-start">
            <div class="mb-2">
              <h5 class="mb-1 fw-bold">Subtotal Price</h5>
              <div>
              <span ></span>
              <span class="fs-8 font-weight-600" id="subtotal-<?php echo htmlspecialchars($value['product_id']); ?>"><?php echo htmlspecialchars(($value['product_quantity'] * $value['product_price']) . ' FCFA', ENT_QUOTES, 'UTF-8'); ?></span>
              <span class="product-price"></span>
            </div>
               <div class="text-muted small">Product delivery date:</div>
               <div class="text-muted small font-weight-600"> 10 to 13 November. Offered</div>
            </div>
            <div class="d-flex gap-2 align-items-center"><small>Quantity:</small>
             <div class="custom-buttons">
                  <button type="button" class="decrement-btn">-</button>
                  <input type="number" name="quantities[<?php echo htmlspecialchars($value['product_id']); ?>]" value="<?php echo isset($value['product_quantity']) ? htmlspecialchars((int)$value['product_quantity']): 1; ?>" min="1" max="100"/>
                  <button type="button" class="increment-btn">+</button>
              </div>
              <button class="btn btn-outline-danger remove-btn" data-product-id="<?php echo isset($value['product_id'])? htmlspecialchars( (int)$value['product_id']) : 0;?>" type="button">Delete</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php }?>
    <div class=" update_cart_btn mt-4">
      <button id="update-cart-btn" type="button">Update Cart</button>
  </div>
  </div>
  
  <div class="container col-lg-4 col-md-12 col-12 mt-5 pt-3 sub-cart  ">
     <h3 class="">Cart Totals</h3>
     <div class="py-5 m-2">
      <div class="sub-total mb-3">
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
<script src="assets/js/numberInput.js"></script>
<script src="assets/js/carts.js"></script>
<script src="assets/js/slide.js"></script>
<script src="assets/includes/bootstrap-5.3.7-dist/js/bootstrap.bundle.min.js"></script>

<?php include_once TEMPLATES_PATH . '/footer.php';?>
</body>
</html>


