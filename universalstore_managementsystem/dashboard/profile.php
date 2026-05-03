<?php 

// INITIALIZATION & SECURITY
require_once __DIR__ . '/../../config/config.php';
require_once INIT_PATH . '/init.php';

// Security check to prevent direct file access.
if (!defined('APP_INIT')) {
    error_log('Attempted direct access to secure utility file: ' . $_SERVER['REQUEST_URI']);
    http_response_code(403); 
    $redirect_url =  ERROR_URL . "?code=403&message= " . urlencode("Access Denied.");
    header("Location: $redirect_url");
    exit('403 Access Denied.'); 
}

// Load session manager and Composer autoloader.
require_once SERVER_PATH. '/secure_session_csrf_cookie.php';

use core\Controller;
use external_server\Connect;
use core\logout;
use internal_logic\Cart;
use Internal_logic\getUserActiveSession; 
use internal_logic\otpVerification;
use internal_logic\OrderDisplayLogic;
use core\utility;
use internal_logic\ActivityHistoryManager;
use internal_logic\WishlistManager;


// UTILITY 
$utility_for_data_decryption = new utility();
// AUTHENTICATION & AUTHORIZATION

$authenticatedUserId = Controller::getAuthenticatedUserId();

if (!$authenticatedUserId) {
    // Capture the current URL to redirect back after login.
    $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $_SESSION['redirect_after_oauth'] = $current_url;

    header("Location: " . AUTHENTICATION_URL);
    exit;
 }

// ACTION HANDLING (e.g., Logout)
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $logout = new logout();
    $logout->logout_user(); // This method should handle the redirect and exit.
}

// PAGE ROUTING & DATA PREPARATION

// --- Tab Navigation Setup ---
$valid_tabs = ['orders', 'profil', 'wishlists', 'activities', 'others'];
$current_tab = 'profil'; // Default tab
$show_404_content = false;

if (isset($_GET['tab'])) {
    $requested_tab = strtolower($_GET['tab']);
    if (in_array($requested_tab, $valid_tabs)) {
        $current_tab = $requested_tab;
    } else {
        // Invalid tab requested, prepare to show 404 content.
        http_response_code(404);
        $show_404_content = true;
    }
}

// --- Data Fetching ---
$controller = new Controller();
$user_id = $authenticatedUserId;
$session_token = $authenticatedUserSession;
// Fetch cart item count for the authenticated user.
$cart = new Cart($authenticatedUserId);
$cart_id = $cart->getOrCreateCartIdForUser($user_id);
$cart_items_count = $cart->getCartItemCountForCart($cart_id);

// Fetch user profile data.
$user_data = $controller->userData($user_id);
if ($user_data) {
    $user_id = $user_data['user_id'];
    $fname = $user_data['user_fname'];
    $lname = $user_data['user_lname'];
    $email = $user_data['user_email'];
    $phone = $user_data['user_phone'];
    $avatar = $user_data['user_avatar'];
    $last_login = $user_data['last_login'];
    $account_creation_date = $user_data['user_account_creation_date'];
    $is_verified = $user_data['is_verified'];
} else {
    // Handle case where user data is not found for a valid user ID.
    error_log("User data not found for authenticated user ID: {$user_id}");
    $fname = $lname = $email = $last_login = $account_creation_date = 'N/A';
    $is_verified = false;
    $logout = new logout();
    $logout->logout_user(); // Force logout if user data is inconsistent
}

// Fetch user delivery address.
$delivery_address = $controller->userDeliveryAddress($user_id, $email);
  // Set delivery variables to null or empty to handle in the view.
$d_fname = $d_lname = $d_country = $d_postcode = $d_address = $d_city = null;
if ($delivery_address) {
    $user_id = $delivery_address['user_id'];
    $d_fname = $utility_for_data_decryption->decryptData($delivery_address['user_fname']);
    $d_lname = $utility_for_data_decryption->decryptData($delivery_address['user_lname']);
    $d_country = $delivery_address['user_country'];
    $d_postcode = $delivery_address['user_postcode'];
    $d_address = $utility_for_data_decryption->decryptData($delivery_address['user_address']);
    $d_city = $delivery_address['user_city'];
} 
  
// Display user active session info

$activeSessionController = new getUserActiveSession();
  $activeSession = $activeSessionController->getUserSession($user_id,$session_token);
if($activeSession){
    
    $status = $activeSession['loggedIn_status'];
    $is_verified = $activeSession['is_verified'];
    $browser = $activeSession['browser'];
    $device = $activeSession['device'];
    $timezone = $activeSession['time_zone'];
    $last_login = $activeSession['last_login'];
    $user_ip_address = $activeSession['user_ip_address'];
    $last_activity = $activeSession['last_activity_time'];   
}else{
    $activeSessionController->storeActiveSessionData($user_id,$session_token);
}

// Function to verify Email via OTP

if(isset($_GET['action']) && $_GET['action'] === 'verify_email'){
    $emailVerification = new otpVerification();
    $sendOTP = $emailVerification::generateAndSendOtp($user_id, $email,$fname,$lname);
    if($sendOTP){
        header("Location: " . VERIFY_ACCOUNT_URL);
    }else{
        if(isset($_SESSION['emailError']) && !empty($_SESSION['emailError'])){
        $otpEmail_Error = $_SESSION['emailError'] ?? 'Error occured while sending OTP to email';
        }
    }
}

// Displaying OTP CODE SEND ERROR 

// Check database if there are any orders
$orderController = new OrderDisplayLogic($authenticatedUserId); 
//now get orderId from OrderDisplayLogic class
$order_id = $orderController->getOrderId($authenticatedUserId);
// Use the function from the controller class to check if user has orders
$verifyOrders = $controller->verifyOrders($user_id, $order_id);

// Fetch user activity history
$activityManager = new ActivityHistoryManager();
$user_activities = $activityManager->getUserActivityHistory($authenticatedUserId, 50);

// Fetch user wishlist
$wishlistManager = new WishlistManager();
$user_wishlist = $wishlistManager->getUserWishlist($authenticatedUserId);
$wishlist_count = $wishlistManager->getWishlistCount($authenticatedUserId);



?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>profile - UniversalStore</title>
    
  <!-- bootstrap CDN -->
  <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/includes/bootstrap-5.3.7-dist/css/bootstrap.min.css">

  <!-- Font Awesome CDN -->
  <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/includes/fontawesome-free-5.15.4-web/css/all.css">
  <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/includes/fontawesome/fontawesome-free-6.7.2-web/css/all.css">

  <!-- custom css file -->
  <link href="https://ik.imagekit.io/univ35tore/universal_store/imgs/UNIVERSAL%20STORE.jpg" rel="icon">
  <link href="<?php echo ASSETS_URL; ?>/css/tailwind.css" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/profil.css">

  <!-- JavaScript Error Logger -->
  <script src="<?php echo ASSETS_URL; ?>/js/error-logger.js"></script>

</head>
<body class="font-sans antialiased bg-gray-50 min-h-screen">

    <!-- Navbar -->
    <nav class="bg-black text-white">
        <div class="mini-nav">
        <div>
            <ul class="mini-nav-content">
                <li><a href="#"><i class="fas fa-check-circle"></i>Quality guarantee</a></li>
                <li><a href="#">Fast deals (Truck)</a></li>
                <li><a href="#">Everyday deals</a></li>
                <li><a href="#">Blog</a></li>
            </ul>
            </div>
            <div>
            <img src="https://ik.imagekit.io/univ35tore/universal_store/imgs/camer.jpeg?updatedAt=1770682528717" class="flag"><strong>CM</strong>
            </div>
        </div>
    <div class="main-nav">
          <a href="<?php echo universalstore_URL; ?>/index" class="brand">
          <img class="logo" src="https://ik.imagekit.io/univ35tore/universal_store/imgs/UNIVERSAL%20STORE.jpg" alt="Universal Store logo" />
          <span>UNIVERSAL STORE</span>
          </a>
            <div class="search-box hidden">
            <i class="fas fa-search"></i>
            <input type="search" name="search" id="search" placeholder="search key words.." autocomplete="off"> 
        </div>
        
        <ul class="main-menu">
        <li>
            <span type="button" class="purchase-btn hidden text-white border border-white rounded px-3 py-2"><a href="<?php echo universalstore_URL; ?>/shop">Start Shopping</a></span>
        </li>
             <li>
            <a href="../contact.php"><span class="help text-gray-400 hover:text-white transition">Contact us</span></a>
            </li>
           
             <li>
             <a class="nav-link position-relative cart text-gray-400 hover:bg-white/60 transition" href="../cart.php" aria-label="Cart">
              <i class="fas fa-shopping-bag"></i>
            <?php if(!empty($cart_items_count) && isset($cart_items_count)) {?>
              <span class="cart-item" style="font-size: 0.75rem;"><?php echo htmlspecialchars($cart_items_count)?>
              <span class="visually-hidden">Items in cart</span></span>
              <?php }?>
             </a>
            </li>
            <?Php if($authenticatedUserId){?>
             <!-- Account button  -->
            <li class="nav-item dropdown">
               
                        <a class="nav-link d-inline-flex align-items-center justify-content-center account-btn bg-white/48 hover:bg-white/58 hover:border-white" style="padding: 0px 0px;" href="#" id="navbarAccountDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account">
                           <img src="<?php echo htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8'); ?>" class="w-8 h-8 border rounded-full">
                             </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg text-gray-900 hover:bg-gray-100" aria-labelledby="navbarAccountDropdown" style="min-width:18rem;">
                        <div class="border-bottom py-3 font-size-20 px-4 font-rubik text-gray-600"><span >My Account</span></div>
                        <li><a href="profile?tab=profil" class="dropdown-item border-bottom d-flex align-items-center py-4 font-rubik font-weight-500 mb-1 text-gray-900 hover:bg-gray-100" role="menuitem" data-tab="profil"><i class="fa-regular fa-user text-blue-500 me-2"></i> Profile</a></li>
                        <li><a href="profile?tab=orders"class="dropdown-item border-bottom d-flex align-items-center py-4 font-rubik font-weight-500 mb-1 text-gray-900 hover:bg-gray-100" role="menuitem" data-tab="orders"><i class="fas fa-box-open text-orange-500 me-2"></i>Orders and help</a></li>
                        <li><a href="profile?tab=wishlists" class="dropdown-item border-bottom d-flex align-items-center py-4 font-rubik font-weight-500 mb-1 text-gray-900 hover:bg-gray-100" role="menuitem" data-tab="wishlists"><i class="fas fa-heart me-2"></i>Wishlists</a></li>
                        <li><a href="profile?tab=activities" class="dropdown-item border-bottom d-flex align-items-center py-4 font-rubik font-weight-500 mb-1 text-gray-900 hover:bg-gray-100" role="menuitem" data-tab="activities"><i class="fa-solid fa-clock-rotate-left text-red-500 me-2"></i>Activity history</a></li>
                        <li><a href="?action=logout" class="dropdown-item d-flex align-items-center py-4 font-rubik font-weight-500 logout text-gray-900 hover:bg-gray-100"><i class="fa-solid fa-power-off text-red-500 me-2"  role="menuitem" data-tab="logout"></i>Log Out</a></li>
          </ul>
        </li>
        <?php } else{?> 
          <li class="nav-item">
             <a class="nav-link p-0 color-smoke" href="../account/authentication/authentication.php" aria-label="Account"><i class="fas fa-user"></i></a>
            </li>
          <?php }?>
        </ul>
        <i class="fa-solid fa-bars toggle-navbar"></i>
    </div>

    <!-- Search row (second row on mobile) -->
    <div class="search-row ">
        <div>
            <span type="button" class="purchase-btn text-white border border-white rounded px-2 py-2"><a href="<?php echo universalstore_URL; ?>/shop">Start Shopping</a></span>
        </div>
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="search" name="search" id="searchs" placeholder="search key words.." autocomplete="off"> 
        </div>
        
    </div>
            <div class="nav-container">
            <ul class="nav-menu ">
            <!-- <li>
            <a href="#" class="bottom">Free Plan<i class="fas fa-chevron-right hidden-arrow "></i>
            <span><hr class="hidden"/></span> 
            </a>
            </li> -->
             <li>
            <a href="<?php echo universalstore_URL; ?>/category/smartphones" class="bottom">Smartphones<i class="fas fa-chevron-right hidden-arrow "></i>
            <span><div class="nav-hover-line"></div></span>
            </a>
            <div class="dropdown-menu" id="dropdown-menu">
            <div class="dropdown-inner-container">
            <div class="left-section">
            <span class="dropdown-close"><i class="fas fa-chevron-left"></i> Back</span>
                <h1>SMARTPHONES</h1>
                <p>View all latest smartphones from iPhone, Samsung, Redmi and 
                  more get a look to your phones. The choice is yours <i class="fas fa-smile"></i> </p>
                <a href="<?php echo universalstore_URL; ?>/category/smartphones" class="btn-see-all">See all <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="right-section row">
                <h5>Categories</h5>
                <ul class="dropdown-links container">
                   <li class="col-lg-4 col-md-6 col-sm-6 ">
                    <a href="<?php echo universalstore_URL; ?>/category/smartphones?category=iPhone">
                        <div>
                        <img src="<?php echo ASSETS_URL; ?>/imgs/dropimage22.webp" class="dropdown-img" alt="iPhone 12 pro max">
                        <p>iPhone</p>
                        </div>
                    </a>
                   </li> 
                     <li class="col-lg-4 col-md-6 col-sm-6 ">
                    <a href="<?php echo universalstore_URL; ?>/category/smartphones?category=samsung">
                        <div>
                        <img src="<?php echo ASSETS_URL; ?>/imgs/dropimage17.webp" class="dropdown-img2" alt="Samsung Galaxy">
                        <p>Samsung Galaxy</p>
                        </div>
                    </a>
                   </li> 
                     <li class="col-lg-4 col-md-6 col-sm-6 ">
                    <a href="<?php echo universalstore_URL; ?>/category/smartphones?category=samsung">
                        <div>
                        <img src="<?php echo ASSETS_URL; ?>/imgs/dropimage16.webp" class="dropdown-img3" alt="Samsung S23 ultra">
                        <p>Samsung</p>
                        </div>
                    </a>
                   </li> 
                     <li class="col-lg-4 col-md-6 col-sm-6 ">
                    <a href="<?php echo universalstore_URL; ?>/category/smartphones?category=pixel">
                        <div>
                        <img src="<?php echo ASSETS_URL; ?>/imgs/dropimage26.webp" class="dropdown-img4" alt="Google Pixel 9">
                        <p>Google Pixel</p>
                        </div>
                    </a>
                   </li> 
                   
                        </ul>
                    </div>
                  </div>
                </div>
            </li> 
                     <li>
                        <a href="<?php echo universalstore_URL; ?>/category/tablets" class="bottom">Tablets<i class="fas fa-chevron-right hidden-arrow "></i>
                        <span><div class="nav-hover-line"></div></span>
                        </a>
                         <div class="dropdown-menu" id="dropdown-menu">
                        <div class="dropdown-inner-container">
                        <div class="left-section">
                        <span class="dropdown-close"><i class="fas fa-chevron-left"></i> Back</span>
                            <h1>TABLETS</h1>
                            <p>View all latest Tablets from iPad, Samsung Pad, Tablet Android and 
                              more get a look at your Tablets. For kids, childrens and adults, the choice is yours <i class="fas fa-smile"></i> </p>
                            <a href="<?php echo universalstore_URL; ?>/category/tablets" class="btn-see-all">See all <i class="fas fa-arrow-right"></i></a>
                        </div>
                        <div class="right-section row">
                            <h5>Categories</h5>
                            <ul class="dropdown-links container">
                              <li class="col-lg-4 col-md-6 col-sm-6 ">
                                <a href="<?php echo universalstore_URL; ?>/category/tablets?category=ipad">
                                    <div>
                                    <img src="https://ik.imagekit.io/univ35tore/universal_store/Tablets/Tablets--iPad.png" class="dropdown-img" alt="iPad">
                                    <p>iPad</p>
                                    </div>
                                </a>
                              </li> 
                                <li class="col-lg-4 col-md-6 col-sm-6 ">
                                <a href="<?php echo universalstore_URL; ?>/category/tablets?category=samsung">
                                    <div>
                                    <img src="https://ik.imagekit.io/univ35tore/universal_store/Tablets/Tablets--Samsung-Galaxy-Tab.png" class="dropdown-img2" alt="Samsung Galaxy Tab">
                                    <p>Samsung Galaxy Tab</p>
                                    </div>
                                </a>
                              </li> 
                                <li class="col-lg-4 col-md-6 col-sm-6 ">
                                <a href="<?php echo universalstore_URL; ?>/category/tablets?category=android">
                                    <div>
                                    <img src="https://ik.imagekit.io/univ35tore/universal_store/Tablets/Tablets--More-brands.png" class="dropdown-img3" alt="Tablet Android">
                                    <p>Tablets Android</p>
                                    </div>
                                </a>
                              </li> 
                                <li class="col-lg-4 col-md-6 col-sm-6 ">
                                <a href="<?php echo universalstore_URL; ?>/category/tablets?category=accessories">
                                    <div>
                                    <img src="https://ik.imagekit.io/univ35tore/universal_store/Tablets/Tablets--Accessories.png" class="dropdown-img4" alt="Tablets Accessories">
                                    <p>Tablets Accessories</p>
                                    </div>
                                </a>
                              </li> 
                              
                                    </ul>
                                </div>
                              </div>
                            </div>
                   </li>
                   <li>
                        <a href="<?php echo universalstore_URL; ?>/category/laptops-and-pcs" class="bottom">Laptops/PC's<i class="fas fa-chevron-right hidden-arrow "></i>
                        <span><div class="nav-hover-line"></div></span>
                        </a>
                         <div class="dropdown-menu" id="dropdown-menu">
                        <div class="dropdown-inner-container">
                        <div class="left-section">
                        <span class="dropdown-close"><i class="fas fa-chevron-left"></i> Back</span>
                            <h1>LAPTOPS & PCs</h1>
                            <p>View all latest Tablets from iPad, Samsung Pad, Tablet Android and 
                              more get a look at your Tablets. For kids, childrens and adults, the choice is yours <i class="fas fa-smile"></i> </p>
                            <a href="<?php echo universalstore_URL; ?>/category/laptops-and-pcs" class="btn-see-all">See all <i class="fas fa-arrow-right"></i></a>
                        </div>
                        <div class="right-section row">
                            <h5>Categories</h5>
                            <ul class="dropdown-links container">
                              <li class="col-lg-4 col-md-6 col-sm-6 ">
                                <a href="<?php echo universalstore_URL; ?>/category/laptops-and-pcs?category=MacBooks">
                                    <div>
                                    <img src="https://ik.imagekit.io/univ35tore/universal_store/laptops/macbook.jpg" class="dropdown-img" alt="MacBooks">
                                    <p>MacBooks</p>
                                    </div>
                                </a>
                              </li> 
                                <li class="col-lg-4 col-md-6 col-sm-6 ">
                                <a href="<?php echo universalstore_URL; ?>/category/laptops-and-pcs?category=Asus">
                                    <div>
                                    <img src="https://ik.imagekit.io/univ35tore/universal_store/laptops/asus%20laptop.jpg" class="dropdown-img2" alt="Asus Laptops">
                                    <p>Asus Laptops</p>
                                    </div>
                                </a>
                              </li> 
                                <li class="col-lg-4 col-md-6 col-sm-6 ">
                                <a href="<?php echo universalstore_URL; ?>/category/laptops-and-pcs?category=Hp">
                                    <div>
                                    <img src="https://ik.imagekit.io/univ35tore/universal_store/laptops/hp%20laptop.jpg" class="dropdown-img3" alt="Hp Laptops">
                                    <p>Hp Laptops</p>
                                    </div>
                                </a>
                              </li> 
                                <li class="col-lg-4 col-md-6 col-sm-6 ">
                                <a href="<?php echo universalstore_URL; ?>/category/laptops-and-pcs?category=Dell">
                                    <div>
                                    <img src="https://ik.imagekit.io/univ35tore/universal_store/laptops/dell%20laptops.jpg" class="dropdown-img4" alt="Dell Laptops">
                                    <p>Dell Laptops</p>
                                    </div>
                                </a>
                              </li> 
                              <li class="col-lg-4 col-md-6 col-sm-6 ">
                                <a href="<?php echo universalstore_URL; ?>/category/laptops-and-pcs?category=accessories">
                                    <div>
                                    <img src="https://ik.imagekit.io/univ35tore/universal_store/laptops/Laptops--Accessories.png" class="dropdown-img4" alt="laptops accessories">
                                    <p>Laptops Accessories</p>
                                    </div>
                                </a>
                              </li> 
                              
                                    </ul>
                                </div>
                              </div>
                            </div>
                   </li>
                   <li>
                        <a href="<?php echo universalstore_URL; ?>/category/consoles" class="bottom">Consoles
                        <i class="fas fa-chevron-right hidden-arrow "></i>
                        <span><div class="hidden"></div></span>
                        </a>
                        <div class="dropdown-menu">
                        <div class="container">
                        <div class="left-section">
                        <span class="dropdown-close"><i class="fas fa-chevron-left"></i> Back</span>
                            <h1>CONSOLES</h1>
                            <p>View all latest Consoles, you will find all your playing game consoles from ps 4 to 1, Nintendo Switch , Xbox Series and 
                              more get a look at your Consoles. Enjoy your playing time with us. <i class="fas fa-smile"></i> </p>
                            <a href="<?php echo universalstore_URL; ?>/category/consoles" class="btn-see-all">See all <i class="fas fa-arrow-right"></i></a>
                        </div>
                        <div class="right-section row">
                            <h5>Categories</h5>
                            <ul class="dropdown-links container">
                                <li class="col-lg-4 col-md-6 col-sm-6 ">
                                <a href="<?php echo universalstore_URL; ?>/category/consoles?category=ps">
                                    <div>
                                    <img src="https://ik.imagekit.io/univ35tore/universal_store/Consoles/Gaming--Playstation.png" class="dropdown-img2" alt="PlayStation">
                                    <p>PlayStation</p>
                                    </div>
                                </a>
                              </li> 
                                <li class="col-lg-4 col-md-6 col-sm-6 ">
                                <a href="<?php echo universalstore_URL; ?>/category/consoles?category=nintendo">
                                    <div>
                                    <img src="https://ik.imagekit.io/univ35tore/universal_store/Consoles/Gaming--Nintendo.png" class="dropdown-img3" alt="Nintendo Switch">
                                    <p>Nintendo</p>
                                    </div>
                                </a>
                              </li> 
                                 <li class="col-lg-4 col-md-6 col-sm-6 ">
                                <a href="<?php echo universalstore_URL; ?>/category/consoles?category=xbox">
                                    <div>
                                    <img src="https://ik.imagekit.io/univ35tore/universal_store/Consoles/Gaming--Xbox.png" class="dropdown-img4" alt="Xbox">
                                    <p>Xbox</p>
                                    </div>
                                </a>
                              </li> 
                              <li class="col-lg-4 col-md-6 col-sm-6 ">
                                <a href="<?php echo universalstore_URL; ?>/category/consoles?category=accessories">
                                    <div>
                                    <img src="https://ik.imagekit.io/univ35tore/universal_store/Consoles/Gaming--Accessories.png" class="dropdown-img4" alt="Gaming accessories">
                                    <p>Gaming Accessories</p>
                                    </div>
                                </a>
                              </li> 
                              
                                    </ul>
                                </div>
                              </div>
                            </div>
                   </li>
                   <li>
                        <a href="<?php echo universalstore_URL; ?>/category/audio" class="bottom">Audio<i class="fas fa-chevron-right hidden-arrow "></i>
                        <span><div class="nav-hover-line"></div></span>
                        </a>
                   </li>
                   <li>
                        <a href="<?php echo universalstore_URL; ?>/category/gadgets" class="bottom">Gadgets<i class="fas fa-chevron-right hidden-arrow "></i>
                        <span><div class="nav-hover-line"></div></span>
                        </a>
                   </li>
                   <li>
                        <a href="<?php echo universalstore_URL; ?>/category/apple-watches" class="bottom">Apple watches<i class="fas fa-chevron-right hidden-arrow "></i>
                        <span><div class="nav-hover-line"></div></span>
                        </a>
                   </li>
                   <li>
                        <a href="<?php echo universalstore_URL; ?>/category/others" class="bottom">Others<i class="fas fa-chevron-right hidden-arrow "></i>
                        <span><div class="nav-hover-line"></div></span>
                        </a>
                   </li>
                   <li class="mini-nav-link">
                    <a href="#"><span class="help">Need help ? Contact us<i class="fa-solid fa-phone-flip"></i></span></a>
                    </li>
                   <li class="mini-nav-link">
                        <a href="#">Quality guarantee<i class="fas fa-check-circle"></i></a>
                   </li>
                   <li class="mini-nav-link">
                        <a href="#">Fast deals (Truck)</a>
                   </li>
                   <li class="mini-nav-link">
                        <a href="#">Everyday deals</a>
                   </li>
                   <li class="mini-nav-link">
                        <a href="#">Affordable pricing<i class="fas fa-smile"></i></a>
                   </li>
                   <div class="flags">
                    <img src="https://ik.imagekit.io/univ35tore/universal_store/imgs/camer.jpeg?updatedAt=1770682528717" class="flag"><strong class="cm">CM</strong>
                    </div>
                   </ul>
            
         </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <?php if ($show_404_content): ?>
        <!-- 404 Not Found Content -->
        <div class="text-center py-10 px-4">
            <h1 class="text-5xl font-bold text-gray-800 mb-4">404</h1>
            <h1 class="text-4xl font-bold text-gray-800 mb-4">It's time to go green (or 'go outside').</h1>
            <p class="text-gray-900 max-w-2xl mx-auto leading-relaxed mb-5">
                If you're here, it's because the URL is incorrect or the page no longer exists.Or perhaps... 
                it's a sign: it's time to take a break and go touch some grass
                <br>
                <span class="font-semibold">But not this one, huh. Real grass, outside.</span>
            </p>
            <img src="<?php echo ASSETS_URL; ?>/imgs/grass.avif" alt="Touching grass" class="mx-auto mb-6" style="width:500px; height:400px;">
            
            <a href="<?php echo INDEX_URL;?>" class="mt-8 inline-block bg-gray-800 text-white font-semibold py-3 px-6 rounded-lg hover:bg-gray-700 transition duration-300">
                Search for something else
            </a>
        </div>
    <?php else: ?>
        <!-- Existing Tab Navigation and Content -->

    <!-- Tab navigation bar-->
     <nav class="flex justify-center border-b border-gray-200 mb-10 shadow-sm rounded-lg nav">
        <div id="tab-navigation" class="flex space-x-6 md:space-x-10 text-sm md:text-base font-medium text-gray-500">
            <!-- Tab 1: Orders --->
            <a href="#orders" data-tab-target="orders" class="no-underline tab-link py-4 px-3 mx-auto inline-flex items-center text-center hover:text-gray-700 transition duration-150 ease-in-out 
            <?php echo htmlspecialchars($current_tab === 'orders') ? 'active-tab text-gray-900 border-b-2 border-gray-900 font-semibold' : ''; ?>">
                Orders
            </a>
            <!-- Tab 2: profil --->
             <a href="#profil" data-tab-target="profil" class="tab-link py-4 px-1 inline-flex items-center text-center hover:text-gray-700 transition duration-150 ease-in-out
             <?php echo htmlspecialchars($current_tab === 'profil') ? 'active-tab text-gray-900 border-b-2 border-gray-900 font-semibold' : ''; ?>">
                Profil
            </a>
            <!-- Tab 3: Wishlists --->
             <a href="#wishlists" data-tab-target="wishlists" class="tab-link py-4 px-1 inline-flex items-center text-center hover:text-gray-700 transition duration-150 ease-in-out
             <?php echo htmlspecialchars($current_tab === 'wishlists') ? 'active-tab text-gray-900 border-b-2 border-gray-900 font-semibold' : ''; ?>">
                Wishlists
            </a>
            <!-- Tab 3: Acitvity history --->
             <a href="#activities" data-tab-target="activities" class="tab-link py-4 px-1 inline-flex items-center text-center hover:text-gray-700 transition duration-150 ease-in-out
             <?php echo htmlspecialchars($current_tab === 'activities') ? 'active-tab text-gray-900 border-b-2 border-gray-900 font-semibold' : ''; ?>">
                Activity History
            </a>
            <!-- Tab 4: others --->
              <a href="#others" data-tab-target="others" class="tab-link py-4 px-1 inline-flex items-center text-center hover:text-gray-700 transition duration-150 ease-in-out
             <?php echo htmlspecialchars($current_tab === 'others') ? 'active-tab text-gray-900 border-b-2 border-gray-900 font-semibold' : ''; ?>">

                Others
            </a>
        </div>
     </nav>
    <!-- Display Email OTP error--->
<?php if(isset($otpEmail_Error)){
 echo '<div class="otp_error" style="display:block;">' . htmlspecialchars($otpEmail_Error ?? '') . '</div>';
    unset($otpEmail_Error);
}
    ?>
     <!-- Tab Content Container -->
      <div id="tab-content-container">
        
        <!-- Content for Orders -->
        <div id="orders" class="tab-content-panel p-8 sm:p-6 <?php echo htmlspecialchars($current_tab !== 'orders') ? 'hidden' : ''; ?>">
              <!-- Display user orders if it exist  -->
        <div id="orders-container">
            <div class="flex justify-center p-10">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            </div>
        </div>
              <?php if(isset($verifyOrders) && !empty($verifyOrders)):?>
               
            
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const container = document.getElementById('orders-container');

                        fetch('<?php echo universalstore_URL ?>' + '/dashboard/orderdisplay.php')
                         .then(response => {
                            if(!response.ok) {
                                throw new Error("Network response was not ok: " +  response.statusText);
                            }
                            return response.text();
                         }).then(html=>{
                            container.innerHTML = html;
                            // Add functionality after insertion
                            // toggleOrder function
                            window.toggleOrder = function(btn) {
                                const card = btn.closest('.order-card');
                                const panel = card.querySelector('.details-panel');
                                
                                const isOpen = panel.classList.contains('open');
                                
                                // Close all others first
                                document.querySelectorAll('.details-panel').forEach(p => p.classList.remove('open'));
                                document.querySelectorAll('.rotate-icon').forEach(i => i.classList.remove('active'));

                                if (!isOpen) {
                                    panel.classList.add('open');
                                    btn.classList.add('active');
                                }
                            };

                            // Search Orders Functionality
                            const searchInput = document.getElementById('searchOrders');
                            const orderCards = document.querySelectorAll('.order-card[data-search-content]');
                            const noResultsMsg = document.createElement('div');
                            noResultsMsg.id = 'no-results';
                            noResultsMsg.className = 'text-center py-12 text-slate-500';
                            noResultsMsg.innerHTML = '<p class="text-lg font-semibold">No orders found</p><p class="text-sm">Try adjusting your search terms</p>';
                            noResultsMsg.style.display = 'none';

                            if (searchInput) {
                                searchInput.addEventListener('keyup', function() {
                                    const query = this.value.toLowerCase().trim();
                                    let visibleCount = 0;

                                    orderCards.forEach(card => {
                                        const searchContent = card.getAttribute('data-search-content').toLowerCase();
                                        const orderId = card.getAttribute('data-order-id').toLowerCase();
                                        const orderStatus = card.getAttribute('data-order-status').toLowerCase();
                                        
                                        // Search in order ID, status, and product names
                                        const matches = searchContent.includes(query) || 
                                                      orderId.includes(query) || 
                                                      orderStatus.includes(query);
                                        
                                        if (query === '' || matches) {
                                            card.style.display = 'block';
                                            card.style.opacity = '1';
                                            card.style.transition = 'opacity 0.3s ease';
                                            visibleCount++;
                                        } else {
                                            card.style.display = 'none';
                                            card.style.opacity = '0';
                                        }
                                    });

                                    // Show/hide "No results" message
                                    const ordersList = document.getElementById('orders-list');
                                    const existingMsg = document.getElementById('no-results');
                                    if (existingMsg) existingMsg.remove();
                                    
                                    if (visibleCount === 0 && query !== '') {
                                        const msg = noResultsMsg.cloneNode(true);
                                        ordersList.parentNode.insertBefore(msg, ordersList.nextSibling);
                                    }
                                });
                            }

                            window.resetSearch = function() {
                                if (searchInput) {
                                    searchInput.value = '';
                                    searchInput.dispatchEvent(new Event('keyup'));
                                    searchInput.focus();
                                }
                            };
                         })
                         .catch(error => {
                            console.error('Fetch error:', error);
                            container.innerHTML = `<p class="text-red-500 text-center">Unable to load orders at this time.</p>`;
                         });
                    });
                   
                </script>
            <?php else:?>
                <h2 class="text-2xl font-semibold mb-3 text-center">Orders History</h2>
            <p class="text-gray-600 text-2xs text-center mb-5">All your orders, track shipping products and client service available.</p>
            
          <div class="bg-card-bg p-6 md:p-8 rounded-xl shadow-md border bg-white border-gray-100">
                    <div class="flex flex-col md:flex-row justify-between items-center md:items-start space-y-6 md:space-y-0">
                        <div class="order-1 md:order-1 mt-3 text-center md:text-left md:mr-8 max-w-lg">
                            <p class="font-semibold mb-3 text-lg">View your past purchases here.</p>
                            <p class="text-gray-600 mb-6">You have no pending orders. All your orders will be available here.</p>
                            <button class="purchase-btn2 rounded-sm text-sm px-5 py-3 "><a href="<?php echo universalstore_URL; ?>/categories" style="text-decoration:none; color:white;">Purchase New articles</a></button>
                        </div> 
                        
                        <!-- Image Block -->
                        <div class="order-2 md:order-2">
                            <img class="rounded-lg w-full max-w-xs mx-auto" 
                                 src="https://ik.imagekit.io/univ35tore/universal_store/imgs/item-back.svg?updatedAt=1770682538141" 
                                 alt="No orders found illustration"
                                 onerror="this.onerror=null;this.src='https://placehold.co/150x150/f0f0f0/888888?text=No ordered items';">
                        </div> 
                        <?php endif;?>
                    </div>
                   
                </div>
                
            </div>

        <!-- Content for Profil (Active by default) -->
         <div id="profil" class="tab-content-panel p-4 sm:p-6 <?php echo htmlspecialchars($current_tab !== 'profil') ? 'hidden' : ''; ?>">
            <h2 class="text-3xl font-bold mb-8 text-center text-gray-900">Profil</h2>
            <p class="text-center text-gray-500 mb-10">(Face view)</p>

            <!-- Content cards (will be display here later with php) -->
         <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

            <!-- Card 1: Personal Information -->
             <div class="bg-card-bg p-6 md:p-8 rounded-xl shadow-sm border bg-white border-gray-100">
        <div class="flex justify-between items-start mb-6">
            <h3 class="text-xl font-semibold text-gray-800">Personal Information</h3>
            <button class="text-2xs px-3 py-1 border border-gray-300 rounded-lg hover:bg-gray-100 transition" type="button" data-bs-toggle="modal" data-bs-target="#personalInformation" data-bs-whatever="personalInformation">
                Modify
            </button>
        </div>
        <p class="text-gray-900 font-medium mb-1"><?php echo htmlspecialchars($fname .' '. $lname); ?></p>
        <?php if($is_verified == boolval(false)){?>
        <p class="text-red-600 text-sm flex items-center mb-1">
            <span class="text-red-600 text-sm flex items-center mb-1">Email not verified</span>
        </p>
        <?php   } ?>
        <p class="text-gray-600 text-sm mb-4"><?php echo htmlspecialchars($email); ?></p>
        <div class="space-y-2 text-sm">
            <?php if($is_verified == boolval(false)){?>
            <a href="?action=verify_email" class="text-primary-blue hover:text-blue-700 block transition underline">Verify Email</a>
            <?php   } ?>
            <a href="#" class="text-primary-blue hover:text-blue-700 block transition underline" type="button" data-bs-toggle="modal" data-bs-target="#editPassword" data-bs-whatever="editPassword">
                Edit password</a>
            <a href="#" class="text-red-600 hover:text-red-800 block transition underline">Delete Account</a> 
               </div>
             </div>
              <!-- Card 2: Delivery address -->
        <div class="bg-card-bg p-6 md:p-8 rounded-xl shadow-sm border bg-white border-gray-100 deliveryContainer">
            <div class="flex justify-between items-start mb-3">
                <h3 class="text-xl font-semibold text-gray-800">Delivery Address</h3>
                <button class="text-2xs px-3 py-1 border border-gray-300 rounded-lg hover:bg-gray-50 transition" type="button" data-bs-toggle="modal" data-bs-target="#deliveryAddress" data-bs-whatever="deliveryAddress">
                    Modify
                </button>
            </div>
            <?php if (!$delivery_address): ?>
            <p class="text-gray-600 italic">You have included no address</p>
            <?php else: ?>
            <p class="text-gray-700 font-semibold text-2xs mt-1 d_name"><?php echo htmlspecialchars($d_fname .' '. $d_lname); ?></p>
            <p class="text-gray-700 font-semibold text-2xs mt-1 d_postcode"><?php echo htmlspecialchars($d_postcode); ?></p>
            <p class="text-gray-700 font-semibold text-2xs mt-1 d_address"><?php echo htmlspecialchars($d_address); ?></p>
            <p class="text-gray-700 font-semibold text-2xs mt-1 d_city"><?php echo htmlspecialchars($d_city); ?></p>
            <p class="text-gray-700 font-semibold text-2xs mt-1 d_country"><?php echo htmlspecialchars($d_country); ?></p>
            <?php endif; ?>
        </div>
        <!--card3:  Payment Methods -->
        <div class="bg-card-bg p-6 md:p-8 rounded-xl shadow-sm border bg-white border-gray-100">
                <div class="flex justify-between items-start mb-6">
                <h3 class="text-xl font-semibold text-gray-800">Billing Details/ Payment Methods</h3>
                <button class="text-xs px-3 py-1 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Add
                </button>
            </div>
            <p class="text-gray-600 italic mb-4">No Credit Card, Orange Money or MTN Money added.</p>
            <a href="#" class="text-primary-blue hover:text-blue-700 text-sm transition">Manage payment Methods</a>
        </div>
        
        <!-- Card 4:Security & Login -->
        <div class="bg-card-bg p-6 md:p-8 rounded-xl bg-white shadow-sm border border-gray-100">
                <div class="flex justify-between items-start mb-6">
                <h3 class="text-xl font-semibold text-gray-800">Login and Security</h3>
                <!-- No modifier button needed here, links handle actions -->
            </div>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between items-center">
                    <span class="text-gray-800">Two Factor Authentication (2FA)</span>
                    <span class="text-red-600 font-medium">off</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-800">Active Session</span>
                    <a class="text-primary-blue font-medium cursor-pointer hover:underline" type="button" data-bs-toggle="modal" data-bs-target="#activeSession" data-bs-whatever="activeSession">Voir (1)</a>
                </div>
                <a href="#" class="text-primary-blue hover:text-blue-700 block transition pt-2">Advanced Security setting</a>
            </div>
        </div>
        

        <!-- Card 5: Communication Preferences -->
        <div class="bg-card-bg p-6 md:p-8 rounded-xl shadow-sm border bg-white border-gray-100 md:col-span-2">
            <div class="flex justify-between items-start mb-6">
                <h3 class="text-xl font-semibold text-gray-800">Communication Preferences</h3>
                <button class="text-xs px-3 py-1 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Save
                </button>
            </div>
            <div class="space-y-4">
                
                <!-- Setting 1: Promotional Emails -->
                <div class="flex justify-between items-center pb-2 border-b border-gray-100">
                    <label for="promo-email" class="text-gray-700 text-base">Promotional offer through email</label>
                    <input type="checkbox" id="promo-email" class="h-5 w-5 text-primary-blue rounded border-gray-300 focus:ring-primary-blue" checked>
                </div>
                
                <!-- Setting 2: Order Status SMS -->
                <div class="flex justify-between items-center pb-2 border-b border-gray-100">
                    <label for="order-sms" class="text-gray-700 text-base">Order notifications</label>
                    <input type="checkbox" id="order-sms" class="h-5 w-5 text-primary-blue rounded border-gray-300 focus:ring-primary-blue">
                </div>
                
                <!-- Setting 3: Newsletter -->
                <div class="flex justify-between items-center">
                    <label for="newsletter" class="text-gray-700 text-base">Subscribe to newsletter</label>
                    <input type="checkbox" id="newsletter" class="h-5 w-5 text-primary-blue rounded border-gray-300 focus:ring-primary-blue" checked>
                </div>

         </div>
        </div>
        </div>
        </div>
        
        <!-- Content for Wishlists -->
         <div id="wishlists" class="tab-content-panel p-4 sm:p-6 <?php echo htmlspecialchars($current_tab !== 'wishlists') ? 'hidden' : ''; ?>">
            <h2 class="text-xl font-semibold mb-3 text-center">Your Wishlist</h2>
            <p class="text-center text-2xs mb-10 text-gray-600">See the list of products and items you have liked</p>

            <?php if (empty($user_wishlist)): ?>
                <!-- Empty wishlist state -->
                <div class="bg-card-bg p-6 md:p-8 rounded-xl shadow-md bg-white border border-gray-100 mb-10">
                    <div class="flex flex-col md:flex-row justify-between items-center md:items-start space-y-6 md:space-y-0">
                        <div class="order-1 md:order-1 mt-3 text-center md:text-left md:mr-8 max-w-lg">
                            <p class="font-semibold mb-3 text-lg">Your wishlist is empty</p>
                            <p class="text-gray-600 mb-6">Keep track of all your favorite products and items here.</p>
                            <a href="<?php echo universalstore_URL; ?>/shop" class="inline-block bg-gray-900 text-white font-medium py-2 px-4 rounded-lg hover:bg-primary-violet transition duration-300 text-sm">
                                Start Shopping
                            </a>
                        </div>
                        <!-- Image Block -->
                        <div class="order-2 md:order-2">
                            <img class="rounded-lg w-40 max-w-xs mx-auto"
                                 src="<?php echo ASSETS_URL; ?>/imgs/sadd.jpg"
                                 alt="Empty wishlist illustration"
                                 onerror="this.onerror=null;this.src='https://placehold.co/150x150/f0f0f0/888888?text=Empty wishlist';">
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Wishlist items grid -->
                <div class="mb-6">
                    <p class="text-gray-600 text-sm"><?php echo count($user_wishlist); ?> items in your wishlist</p>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-6 md:gap-8">
                    <?php foreach ($user_wishlist as $item): ?>
                        <div class="fav-card bg-card-bg p-4 rounded-xl bg-white shadow-sm border border-gray-100 hover:shadow-2xl transition duration-300 flex flex-col justify-between">
                            <div>
                                <div class="flex justify-end">
                                    <!-- Remove from wishlist button -->
                                    <button onclick="removeFromWishlist(<?php echo $item['product_id']; ?>, this)"
                                            class="text-red-500 hover:text-red-700 transition duration-200"
                                            title="Remove from wishlist">
                                        <svg class="w-6 h-6 heart-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                                        </svg>
                                    </button>
                                </div>

                                <a href="<?php echo universalstore_URL; ?>/product/<?php echo $item['product_id']; ?>" class="block">
                                    <img src="<?php echo htmlspecialchars($item['product_image'] ?? ASSETS_URL . '/imgs/placeholder.png'); ?>"
                                         alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                         class="w-20 h-auto object-contain mb-4 rounded-lg mx-auto"
                                         onerror="this.src='<?php echo ASSETS_URL; ?>/imgs/placeholder.png'">
                                </a>

                                <a href="<?php echo universalstore_URL; ?>/product/<?php echo $item['product_id']; ?>" class="block" style="text-decoration:none;">
                                    <h4 class="text-xs font-bold text-gray-900 leading-tight hover:text-blue-600 transition">
                                        <?php echo htmlspecialchars($item['product_name']); ?>
                                    </h4>
                                </a>

                                <p class="text-gray-600 text-xs mb-2">
                                    <?php echo htmlspecialchars($item['product_main_category'] ?? ''); ?>,
                                    <?php echo htmlspecialchars($item['product_category'] ?? ''); ?>
                                </p>
                                <div class="product-variant" style="font-size: 12px; color: #666; margin-bottom: 8px;">
                                <span style="background: #f0f0f0; padding: 2px 6px; border-radius: 3px; display: inline-block;">
                                    <?php 
                                        $rawvariant = htmlspecialchars($item['product_variant'] ?? '');
                                        $variantsArray = explode(',', $rawvariant);
                                        $defaultVariant = count($variantsArray) > 0 ? trim($variantsArray[0]) : '';
                                    echo htmlspecialchars($defaultVariant ?? ''); ?>
                                </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-semibold text-gray-800">
                                        <?php echo number_format($item['product_price'], 0); ?> Fcfa
                                    </p>
                                    <?php if (!empty($item['product_special_offer']) && $item['product_special_offer'] > 0): ?>
                                        <span class="text-xs bg-red-100 text-red-600 px-2 py-1 rounded">
                                            <?php echo $item['product_special_offer']; ?> FCFA
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <!-- Rating -->
                                <?php
                                    $displayRating = isset($item['product_rating']) && $item['product_rating'] > 0 ? floatval($item['product_rating']) : 3.0;
                                    $displayReviewCount = isset($item['product_review_count']) && $item['product_review_count'] > 0 ? intval($item['product_review_count']) : 100;
                                ?>
                                <div class="flex items-center mb-2">
                                    <div class="flex text-yellow-400 text-xs">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo $i <= $displayRating ? 'text-yellow-400' : 'text-gray-300'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <span class="text-xs text-gray-600 ml-1"><?php echo number_format($displayRating, 1); ?> • <?php echo number_format($displayReviewCount); ?> reviews</span>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <button onclick="addToCart(<?php echo $item['product_id']; ?>, '<?php echo addslashes(htmlspecialchars($item['product_name'])); ?>', <?php echo $item['product_price']; ?>, '<?php echo addslashes(htmlspecialchars($defaultVariant ?? '')); ?>', '<?php echo addslashes(htmlspecialchars($item['product_category'] ?? '')); ?>', '<?php echo addslashes(htmlspecialchars($item['product_image'] ?? '')); ?>','<?php echo addslashes(htmlspecialchars($item['product_color'] ?? '')); ?>', this)"
                                        class="w-full bg-gray-900 text-white font-medium py-2 rounded-lg hover:bg-primary-violet transition duration-300 text-sm">
                                  <i class="fa-solid fa-cart-plus mr-2"></i>  Add to Cart
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
         </div>

         <!-- Content for Activity History -->
         <div id="activities" class="tab-content-panel p-4 sm:p-6 <?php echo htmlspecialchars($current_tab !== 'activities') ? 'hidden' : ''; ?>">
            <h2 class="text-xl font-semibold mb-2 text-center">Activity History</h2>
            <p class="text-center text-gray-600 mb-8">Track all your activities over the last days, weeks and months</p>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                <!-- Last Login -->
                <div class="bg-gradient-to-br from-green-50 to-green-100 p-4 rounded-lg border border-green-200">
                    <p class="text-sm text-gray-600 mb-1">Last Login</p>
                    <p class="text-lg font-semibold text-green-700"><?php echo htmlspecialchars($last_login ?? 'N/A'); ?></p>
                </div>

                <!-- Account Created -->
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-4 rounded-lg border border-blue-200">
                    <p class="text-sm text-gray-600 mb-1">Account Created</p>
                    <p class="text-lg font-semibold text-blue-700"><?php echo htmlspecialchars($account_creation_date ?? 'N/A'); ?></p>
                </div>

                <!-- Total Activities -->
                <div class="bg-gradient-to-br from-purple-50 to-purple-100 p-4 rounded-lg border border-purple-200">
                    <p class="text-sm text-gray-600 mb-1">Total Activities</p>
                    <p class="text-lg font-semibold text-purple-700"><?php echo count($user_activities); ?></p>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-4 mb-6">
                <div class="flex flex-col md:flex-row gap-4">
                    <!-- Search -->
                    <div class="flex-1">
                        <input type="text" id="activity-search" placeholder="Search activities, order IDs, payment IDs..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <!-- Type Filter -->
                    <div class="md:w-48">
                        <select id="activity-type-filter" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Activity Types</option>
                            <option value="payment">Payments</option>
                            <option value="order">Orders</option>
                            <option value="account">Account Changes</option>
                            <option value="login">Logins</option>
                            <option value="wishlist">Wishlist</option>
                        </select>
                    </div>

                    <!-- Date Range -->
                    <div class="md:w-32">
                        <input type="date" id="activity-date-from" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div class="md:w-32">
                        <input type="date" id="activity-date-to" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>

                    <!-- Clear Filters -->
                    <button id="clear-filters" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition">
                        Clear
                    </button>
                </div>
            </div>

            <!-- Activities Timeline -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <div id="activities-container">
                    <?php if (empty($user_activities)): ?>
                        <div class="text-center py-12">
                            <i class="fa-solid fa-inbox text-gray-300 mb-4" style="font-size: 3rem;"></i>
                            <p class="text-gray-600 text-lg">No activities yet</p>
                            <p class="text-gray-500 text-sm mt-2">Your activities will appear here as you interact with UniversalStore</p>
                        </div>
                    <?php else: ?>
                        <div id="activities-list" class="space-y-4">
                            <?php foreach ($user_activities as $activity):
                                $icon_info = ActivityHistoryManager::getActivityIcon($activity['activity_type']);
                                $status_badge = ActivityHistoryManager::getStatusBadge($activity['status'] ?? 'pending');
                                $description = ActivityHistoryManager::formatActivityDescription($activity);
                                $timestamp = ActivityHistoryManager::formatTimestamp($activity['timestamp']);
                            ?>
                                <div class="activity-item flex flex-col sm:flex-row sm:items-start gap-3 sm:gap-4 pb-4 border-b border-gray-100 last:border-b-0 hover:bg-gray-50 p-3 rounded transition"
                                     data-type="<?php echo $activity['activity_type']; ?>"
                                     data-timestamp="<?php echo $activity['timestamp']; ?>"
                                     data-description="<?php echo strtolower($description); ?>"
                                     data-order-id="<?php echo $activity['order_id'] ?? ''; ?>"
                                     data-payment-id="<?php echo $activity['payment_id'] ?? ''; ?>">
                                    <!-- Icon and Content Row -->
                                    <div class="flex items-start gap-3 sm:gap-4 flex-grow min-w-0">
                                        <!-- Icon -->
                                        <div class="<?php echo $icon_info['bg_color']; ?> p-2 sm:p-3 rounded-lg flex-shrink-0">
                                            <i class="fas <?php echo $icon_info['icon']; ?> <?php echo $icon_info['color']; ?> text-base sm:text-lg"></i>
                                        </div>

                                        <!-- Activity Details -->
                                        <div class="flex-grow min-w-0">
                                            <div class="flex items-center justify-between gap-2 mb-1 sm:flex-row sm:items-center sm:justify-between gap-2 mb-2">
                                                <p class="font-semibold text-gray-900 break-words text-sm sm:text-base leading-tight">
                                                    <?php echo htmlspecialchars($description); ?>
                                                </p>
                                                <span class="text-xs whitespace-nowrap <?php echo $status_badge['class']; ?> px-2 py-1 rounded-full self-start sm:self-center flex-shrink-0">
                                                    <?php echo htmlspecialchars($status_badge['label']); ?>
                                                </span>
                                            </div>

                                            <!-- Additional Details -->
                                            <div class="flex flex-wrap items-center gap-2 sm:gap-3 text-xs sm:text-sm text-gray-600">
                                                <div class="flex items-center gap-1 flex-shrink-0">
                                                    <i class="fa-regular fa-clock text-gray-400"></i>
                                                    <span><?php echo htmlspecialchars($timestamp); ?></span>
                                                </div>

                                                <?php if ($activity['activity_type'] === 'payment' && !empty($activity['payment_id'])): ?>
                                                    <span class="text-gray-400 hidden sm:inline">•</span>
                                                    <span class="break-all">ID: <?php echo htmlspecialchars(substr($activity['payment_id'], 0, 12)); ?><?php echo strlen($activity['payment_id']) > 12 ? '...' : ''; ?></span>
                                                <?php elseif ($activity['activity_type'] === 'order' && !empty( $activity['order_id'])): ?>
                                                    <span class="text-gray-400 hidden sm:inline">•</span>
                                                    <span class="break-all">Order: <?php echo htmlspecialchars(substr($activity['order_id'],0,12)); ?><?php echo strlen($activity['order_id']) > 12 ? '...' : ''; ?></span>
                                                <?php elseif ($activity['activity_type'] === 'login' && !empty($activity['device'])): ?>
                                                    <span class="text-gray-400 hidden sm:inline">•</span>
                                                    <span class="break-words"><?php echo htmlspecialchars($activity['device']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pagination -->
                        <div id="pagination-container" class="mt-6 flex justify-center items-center gap-2">
                            <button id="prev-page" class="px-3 py-2 bg-gray-200 text-gray-600 rounded-lg hover:bg-gray-300 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                                <i class="fas fa-chevron-left"></i> Previous
                            </button>
                            <span id="page-info" class="text-sm text-gray-600">Page 1 of 1</span>
                            <button id="next-page" class="px-3 py-2 bg-gray-200 text-gray-600 rounded-lg hover:bg-gray-300 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                                Next <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
          </div>
          <!-- Content for Others -->     
            <div id="others" class="tab-content-panel p-4 sm:p-6 <?php echo htmlspecialchars($current_tab !== 'others') ? 'hidden' : ''; ?>">
            <h2 class="text-xl font-semibold mb-4 text-center">Everything extra you need is found here.</h2>
            <div class="bg-card-bg p-6 bg-white rounded-xl shadow-sm">
                <p class="text-2xl font-semibold ">Other tools you will need</p>
                <p class="text-gray-600">Will be available on next system updates, we sorry for that. </p>
            </div>
          </div>

      </div>
    <?php endif; ?>
    </div>

<!------    Profil Modal         ------>
<!----- All profil modal popups most be placed here in other not to cause confusion to myself------>
<!------- DELIVERY ADDRESS MODAL ------>
<!--- Display Delivery address in cells on Modify for user who provided it--->
<?php if(!$delivery_address)?>
  <!-- Error Message -->  
    <div class="modal fade" id="deliveryAddress" tabindex="-1" aria-labelledby="deliveryAddressLabel" aria-hidden="true">
    <div id="deliveryAddress-notice-container" style="position: absolute; top: 20px; left: 50%; transform: translateX(-50%); z-index: 1060; width: 90%; max-width: 600px;"></div>
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content rounded-4">
          <div class="modal-header">
            <h1 class="modal-title font-bold fs-5" id="deliveryAddressLabel"> Delivery Address </h1>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form id="deliveryAddressForm">
                <div><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken());?>"  /> </div>
              <div class="mb-2">
                  <label for="fname" class="col-form-label font-semibold">Family name</label>
                <input type="text" name="fname" value="<?php echo htmlspecialchars($d_fname ?? '')  ;?>" class="form-control border-gray-700 py-2" placeholder="Family name" />
              </div>
              <div class="mb-2">
                  <label for="lname" class="col-form-label font-semibold">Surname</label>
                <input type="text" name="lname" value="<?php echo htmlspecialchars($d_lname ?? '')  ;?>" class="form-control border-gray-700 py-2" placeholder="Surname" />
              </div>
              <div class="form-group mb-2 font-semibold">
                  <label for="country" class="col-form-label font-semibold">Country</label>
                <select name="country" class="form-control border-gray-700 py-2" autocomplete="country" id="country">
                  <option value="<?php echo htmlspecialchars($d_country ?? '--Please choose your country--') ;?>"><?php echo htmlspecialchars($d_country ?? '--Please choose your country--') ;?></option>

                  <!-- UN Member States -->
                  <option value="AF">Afghanistan</option>
                  <option value="AL">Albania</option>
                  <option value="DZ">Algeria</option>
                  <option value="AD">Andorra</option>
                  <option value="AO">Angola</option>
                  <option value="AG">Antigua and Barbuda</option>
                  <option value="AR">Argentina</option>
                  <option value="AM">Armenia</option>
                  <option value="AU">Australia</option>
                  <option value="AT">Austria</option>
                  <option value="AZ">Azerbaijan</option>
                  <option value="BS">Bahamas</option>
                  <option value="BH">Bahrain</option>
                  <option value="BD">Bangladesh</option>
                  <option value="BB">Barbados</option>
                  <option value="BY">Belarus</option>
                  <option value="BE">Belgium</option>
                  <option value="BZ">Belize</option>
                  <option value="BJ">Benin</option>
                  <option value="BT">Bhutan</option>
                  <option value="BO">Bolivia</option>
                  <option value="BA">Bosnia and Herzegovina</option>
                  <option value="BW">Botswana</option>
                  <option value="BR">Brazil</option>
                  <option value="BN">Brunei</option>
                  <option value="BG">Bulgaria</option>
                  <option value="BF">Burkina Faso</option>
                  <option value="BI">Burundi</option>
                  <option value="CV">Cabo Verde</option>
                  <option value="KH">Cambodia</option>
                  <option value="Cameroon">Cameroon</option>
                  <option value="CA">Canada</option>
                  <option value="CF">Central African Republic</option>
                  <option value="TD">Chad</option>
                  <option value="CL">Chile</option>
                  <option value="CN">China</option>
                  <option value="CO">Colombia</option>
                  <option value="KM">Comoros</option>
                  <option value="CD">Congo (DRC)</option>
                  <option value="CG">Congo (Republic)</option>
                  <option value="CR">Costa Rica</option>
                  <option value="HR">Croatia</option>
                  <option value="CU">Cuba</option>
                  <option value="CY">Cyprus</option>
                  <option value="CZ">Czech Republic</option>
                  <option value="DK">Denmark</option>
                  <option value="DJ">Djibouti</option>
                  <option value="DM">Dominica</option>
                  <option value="DO">Dominican Republic</option>
                  <option value="EC">Ecuador</option>
                  <option value="EG">Egypt</option>
                  <option value="SV">El Salvador</option>
                  <option value="GQ">Equatorial Guinea</option>
                  <option value="ER">Eritrea</option>
                  <option value="EE">Estonia</option>
                  <option value="SZ">Eswatini</option>
                  <option value="ET">Ethiopia</option>
                  <option value="FJ">Fiji</option>
                  <option value="FI">Finland</option>
                  <option value="FR">France</option>
                  <option value="GA">Gabon</option>
                  <option value="GM">Gambia</option>
                  <option value="GE">Georgia</option>
                  <option value="DE">Germany</option>
                  <option value="GH">Ghana</option>
                  <option value="GR">Greece</option>
                  <option value="GD">Grenada</option>
                  <option value="GT">Guatemala</option>
                  <option value="GN">Guinea</option>
                  <option value="GW">Guinea-Bissau</option>
                  <option value="GY">Guyana</option>
                  <option value="HT">Haiti</option>
                  <option value="HN">Honduras</option>
                  <option value="HU">Hungary</option>
                  <option value="IS">Iceland</option>
                  <option value="IN">India</option>
                  <option value="ID">Indonesia</option>
                  <option value="IR">Iran</option>
                  <option value="IQ">Iraq</option>
                  <option value="IE">Ireland</option>
                  <option value="IL">Israel</option>
                  <option value="IT">Italy</option>
                  <option value="JM">Jamaica</option>
                  <option value="JP">Japan</option>
                  <option value="JO">Jordan</option>
                  <option value="KZ">Kazakhstan</option>
                  <option value="KE">Kenya</option>
                  <option value="KI">Kiribati</option>
                  <option value="KR">South Korea</option>
                  <option value="KW">Kuwait</option>
                  <option value="KG">Kyrgyzstan</option>
                  <option value="LA">Laos</option>
                  <option value="LV">Latvia</option>
                  <option value="LB">Lebanon</option>
                  <option value="LS">Lesotho</option>
                  <option value="LR">Liberia</option>
                  <option value="LY">Libya</option>
                  <option value="LI">Liechtenstein</option>
                  <option value="LT">Lithuania</option>
                  <option value="LU">Luxembourg</option>
                  <option value="MG">Madagascar</option>
                  <option value="MW">Malawi</option>
                  <option value="MY">Malaysia</option>
                  <option value="MV">Maldives</option>
                  <option value="ML">Mali</option>
                  <option value="MT">Malta</option>
                  <option value="MH">Marshall Islands</option>
                  <option value="MR">Mauritania</option>
                  <option value="MU">Mauritius</option>
                  <option value="MX">Mexico</option>
                  <option value="FM">Micronesia</option>
                  <option value="MD">Moldova</option>
                  <option value="MC">Monaco</option>
                  <option value="MN">Mongolia</option>
                  <option value="ME">Montenegro</option>
                  <option value="MA">Morocco</option>
                  <option value="MZ">Mozambique</option>
                  <option value="MM">Myanmar</option>
                  <option value="NA">Namibia</option>
                  <option value="NR">Nauru</option>
                  <option value="NP">Nepal</option>
                  <option value="NL">Netherlands</option>
                  <option value="NZ">New Zealand</option>
                  <option value="NI">Nicaragua</option>
                  <option value="NE">Niger</option>
                  <option value="NG">Nigeria</option>
                  <option value="MK">North Macedonia</option>
                  <option value="NO">Norway</option>
                  <option value="OM">Oman</option>
                  <option value="PK">Pakistan</option>
                  <option value="PW">Palau</option>
                  <option value="PA">Panama</option>
                  <option value="PG">Papua New Guinea</option>
                  <option value="PY">Paraguay</option>
                  <option value="PE">Peru</option>
                  <option value="PH">Philippines</option>
                  <option value="PL">Poland</option>
                  <option value="PT">Portugal</option>
                  <option value="QA">Qatar</option>
                  <option value="RO">Romania</option>
                  <option value="RU">Russia</option>
                  <option value="RW">Rwanda</option>
                  <option value="KN">Saint Kitts and Nevis</option>
                  <option value="LC">Saint Lucia</option>
                  <option value="VC">Saint Vincent and the Grenadines</option>
                  <option value="WS">Samoa</option>
                  <option value="SM">San Marino</option>
                  <option value="ST">Sao Tome and Principe</option>
                  <option value="SA">Saudi Arabia</option>
                  <option value="SN">Senegal</option>
                  <option value="RS">Serbia</option>
                  <option value="SC">Seychelles</option>
                  <option value="SL">Sierra Leone</option>
                  <option value="SG">Singapore</option>
                  <option value="SK">Slovakia</option>
                  <option value="SI">Slovenia</option>
                  <option value="SB">Solomon Islands</option>
                  <option value="SO">Somalia</option>
                  <option value="ZA">South Africa</option>
                  <option value="SS">South Sudan</option>
                  <option value="ES">Spain</option>
                  <option value="LK">Sri Lanka</option>
                  <option value="SD">Sudan</option>
                  <option value="SR">Suriname</option>
                  <option value="SE">Sweden</option>
                  <option value="CH">Switzerland</option>
                  <option value="SY">Syria</option>
                  <option value="TW">Taiwan</option>
                  <!-- Partially recognized -->
                  <option value="TJ">Tajikistan</option>
                  <option value="TZ">Tanzania</option>
                  <option value="TH">Thailand</option>
                  <option value="TL">Timor-Leste</option>
                  <option value="TG">Togo</option>
                  <option value="TO">Tonga</option>
                  <option value="TT">Trinidad and Tobago</option>
                  <option value="TN">Tunisia</option>
                  <option value="TR">Turkey</option>
                  <option value="TM">Turkmenistan</option>
                  <option value="TV">Tuvalu</option>
                  <option value="UG">Uganda</option>
                  <option value="UA">Ukraine</option>
                  <option value="AE">United Arab Emirates</option>
                  <option value="GB">United Kingdom</option>
                  <option value="US">United States</option>
                  <option value="UY">Uruguay</option>
                  <option value="UZ">Uzbekistan</option>
                  <option value="VU">Vanuatu</option>
                  <option value="VA">Vatican City</option>
                  <!-- Observer state -->
                  <option value="VE">Venezuela</option>
                  <option value="VN">Vietnam</option>
                  <option value="EH">Western Sahara</option>
                  <!-- Disputed territory -->
                  <option value="YE">Yemen</option>
                  <option value="ZM">Zambia</option>
                  <option value="ZW">Zimbabwe</option>
                </select>
              </div>
              <div class="mb-2">
                  <label for="postcode" class="col-form-label font-semibold">Postcode</label>
                <input type="text" name="postcode" value="<?php echo htmlspecialchars($d_postcode ?? '')  ;?>"class="form-control border-gray-700 py-2" placeholder="Postcode" id="postcode" />
              </div>
              <div class="mb-2">
                  <label for="address" class="col-form-label font-semibold">Street Address</label>
                <input type="text" name="address" value="<?php echo htmlspecialchars($d_address ?? '') ; ?>" class="form-control border-gray-700 py-2" placeholder="House number and Street name (Street Address)" id="address" />
              </div>
              <div class="mb-3">
                  <label for="city" class="col-form-label font-semibold">City / Town</label>
                <input type="text" name="city" value="<?php echo htmlspecialchars($d_city ?? '')  ; ?>" class="form-control border-gray-700 py-2" placeholder="Enter Town / City" id="city" />
              </div>
               <p class="text-muted small">We just need it for shipping your products and shipping rates calculation </p>
            </form>
          </div>
          <div class="modal-footer">
            <!-- <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"> Close </button> -->
            <button type="button" class="btn save-btn btn-primary" data-form-id="deliveryAddressForm">Save</button>
          </div>
        </div>
      </div>
    </div>

<!--- Modal For Personal Information --->
   <!-- Error Message -->    
    <div class="modal fade" id="personalInformation" tabindex="-1" aria-labelledby="personalInformationLabel" aria-hidden="true">
        <div id="personalInfo-notice-container" style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 1060; width: 90%; max-width: 600px;"></div>
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content rounded-4">
          <div class="modal-header mb-4">
            <h1 class="modal-title font-bold fs-5" id="personalInformationLabel"> Personal Information </h1>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form id="personalInfoForm">
                <div><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken());?>" /> </div>
              <div class="mb-4">
                <input type="text" name="fname" value="<?php echo htmlspecialchars($fname ?? '')  ;?>" class="form-control border-gray-700 py-3" placeholder="Name" />
              </div>
              <div class="mb-4">
                <input type="text" name="lname" value="<?php echo htmlspecialchars($lname ?? '')  ; ?>" class="form-control border-gray-700 py-3" placeholder="Surname" />
              </div>
              <div class="mb-4">
                <input type="email" name="email" value="<?php echo htmlspecialchars($email ?? '')  ; ?>" class="form-control border-gray-700 py-3" placeholder="Email*" id="email" />
              </div>
              <div class="input-group mb-3 custom-phone-input">
                <button id="countryCodeDisplay" class="btn btn-outline-secondary dropdown-toggle country-code-btn hover:bg-gray-100 hover:text-gray-900" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    +237
                </button>
                <ul class="dropdown-menu" aria-labelledby="countryCodeDisplay">
                    <li><a class="dropdown-item font-semibold" data-code="+237">+237</a></li>
                    </ul>
                <input type="tel" 
                    id="phoneNumberInput" 
                    class="form-control py-3" 
                    placeholder="telephone number - optional" 
                    aria-label="Telephone number">
            </div>
            </form>
          </div>
          <div class="modal-footer">
            <!-- role="button" tabindex="0" data-bs-toggle="modal" data-bs-target="#moneyBackModal" style="cursor:pointer;" aria-controls="cartItemModal" aria-label="Open Money Back details"<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"> Close </button> -->
            <button type="button" class="btn save-btn btn-primary" data-form-id="personalInfoForm">Save</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit password modal -->

    <!-- Error Message -->    
    <div id="editPassword-notice-container" style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 1060; width: 90%; max-width: 600px;"></div>
    <div class="modal fade" id="editPassword" tabindex="-1" aria-labelledby="editPasswordLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content rounded-4">
          <div class="modal-header mb-4">
            <h1 class="modal-title font-bold fs-5" id="editPasswordLabel"> Personal Information </h1>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form id="editPasswordForm">
                <div><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken());?>" /> </div>
              <div class="mb-4">
                <input type="password" name="currentPassword" class="form-control border-gray-700 py-3" placeholder="Current password*" />
              </div>
              <div class="mb-4">
                <input type="password" name="NewPassword" class="form-control border-gray-700 py-3" class="form-control border-gray-700 py-3" placeholder="New Password*" />
                <p class="text-muted small mt-2">At least 8 characters, including 1 uppercase, 1 lowercase and 1 number. Because you never know.</p>
              </div>
              <div class="mb-4">
                <input type="password" name="ConfirmNewPassword" class="form-control border-gray-700 py-3" placeholder="Confirm Password*" />
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <!-- role="button" tabindex="0" data-bs-toggle="modal" data-bs-target="#moneyBackModal" style="cursor:pointer;" aria-controls="cartItemModal" aria-label="Open Money Back details"<button type="button" class="btn btn-secondary" data-bs-dismiss="modal"> Close </button> -->
            <button type="button" class="btn save-btn btn-primary" data-form-id="editPasswordForm">Save</button>
          </div>
        </div>
      </div>
    </div>


    <!--- ACtive session modal --->
<?php  if($activeSession){?>
    <div class="modal fade activeSession" id="activeSession" tabindex="-1" aria-labelledby="activeSessionLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content rounded-4">
      <div class="modal-header border-0">
        <h3 class="modal-title moneyback-modal-title" id="activeSessionLabel">Session Information</h3>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="close"></button>
      </div>

      <div class="modal-body">
        <div class="container-fluid">
          <div class="row gy-3 align-items-center">
            <!-- Illustration column -->
    
            <!-- Text column -->
            <div class="col-12 ">
                <div class="flex justify-between items-center border-bottom mb-3">
                  <h6 class="font-semibold">Status:</h6>
                  <p class="text-muted">Active (<span class="text-primary-blue">logged In</span>)</p>
                </div>

                <div class="flex justify-between border-bottom items-center mb-3">
                <?php if($is_verified == boolval(true)):?>
                  <h6 class="mb-1 font-semibold ">Email:</h6>
                  <p class="mb-0 text-primary-blue">verified</p>
                  <?php else: ?>
                     <h6 class="mb-1 font-semibold ">Email:</h6>
                  <p class="mb-0 text-red-600">Not verified</p>
                  <?php endif;?>
                </div>

                <div class="flex justify-between items-center border-bottom mb-3">
                  <h6 class="mb-1 font-semibold">Connected on</h6>
                </div>
                <div class="flex justify-between items-center border-bottom mb-3">
                  <h6 class="mb-1 font-semibold">Browser:</h6>
                  <p class="mb-0 text-muted"> <?php echo htmlspecialchars($browser); ?></p>
                </div>
                <div class="flex justify-between items-center border-bottom mb-3">
                  <h6 class="mb-1 font-semibold">Device:</h6>
                  <p class="mb-0 text-muted"> <?php echo htmlspecialchars($device); ?></p>
                </div>

                <div class="flex justify-between items-center border-bottom mb-3">
                  <h6 class="mb-1 font-semibold">Location/Timezone:</h6>
                  <p class="mb-0 text-muted"><?php echo htmlspecialchars($timezone); ?></p>
                </div>

                <div class="flex justify-between items-center border-bottom mb-3">
                  <h6 class="mb-1 font-semibold">Login time:</h6>
                  <p class="mb-0 text-muted"><?php echo htmlspecialchars($last_login); ?></p>
                </div>

                <div class="flex justify-between items-center border-bottom mb-3">
                  <h6 class="mb-1 font-semibold">IP Address:</h6>
                  <p class="mb-0 text-muted"><?php echo htmlspecialchars($user_ip_address); ?></p>
                </div>

                <div class="flex justify-between items-center mb-3">
                  <h6 class="mb-1 font-semibold">Last Activity:</h6>
                  <p class="mb-0 text-muted"><?php echo htmlspecialchars($last_activity); ?></p>
                </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php }?>

<!--- Script to send Form --->
<!--- We are going to refine script for multiple form submissions--->
<script>

// ===== GLOBAL VARIABLES =====
let currentPage = 1;
const itemsPerPage = 15;
let filteredActivities = [];

// Function for error/ success notice
function showNotification(containerElement, message, isError = false) {
    // If no container provided, create a temporary one
    if (!containerElement || !(containerElement instanceof Element)) {
        containerElement = document.getElementById('global-notification-container');
        if (!containerElement) {
            containerElement = document.createElement('div');
            containerElement.id = 'global-notification-container';
            containerElement.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; width: 300px;';
            document.body.appendChild(containerElement);
        }
    }

    const notification = document.createElement('div');
    notification.classList.add('alert', isError ? 'alert-danger' : 'alert-success', 'alert-dismissible', 'fade', 'show');
    notification.role = 'alert';
    notification.innerHTML = `${message} <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
    containerElement.innerHTML = ''; //clear previous messages
    containerElement.appendChild(notification);

    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        const bsAlert = bootstrap.Alert.getOrCreateInstance(notification);
        if(bsAlert){
            bsAlert.close();
        }
    }, 5000);
}

// Function to update csrf_token
function updateCsrfToken(newToken) {
    const csrfToken = document.querySelectorAll('input[name="csrf_token"]');
    if (newToken) {
        csrfToken.forEach(token => token.value = newToken);
    }
    return false;
}

// Function to check if CSRF token is not empty
function checkEmptyCsrfToken(token) {
    if (!token) { // noticeContainer is not available here, so we can't use it.
        alert('Session Expired. Please refresh page.');
        return false;
    }
    return true;
}

// Function to check new CSRF Token
function checkNewCsrfToken(newToken, oldToken) {
    if (newToken && newToken === oldToken) { // noticeContainer is not available here.
        alert('Something happened while processing your request. Please refresh page.');
        return false;
    }
    return true;
}

// ===== WISHLIST & CART FUNCTIONS =====
function removeFromWishlist(productId, buttonElement) {
    // if (!confirm('Remove this item from your wishlist?')) {
    //     return;
    // }
    Swal.fire({
        title: 'Are you sure?',
        text: "This item will be removed from your wishlist.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, remove it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if(result.isConfirmed){

    // Disable button to prevent double clicks
    buttonElement.disabled = true;
    buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    fetch('<?php echo INTERNAL_LOGIC_URL; ?>/wishlist_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            action: 'remove',
            product_id: productId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove the item from DOM
            buttonElement.closest('.fav-card').remove();

            // Update wishlist count if it exists
            const countElements = document.querySelectorAll('.wishlist-count');
            countElements.forEach(el => {
                const currentCount = parseInt(el.textContent) || 0;
                if (currentCount > 0) {
                    el.textContent = currentCount - 1;
                }
            });

            // Show success message
            showNotification(null, data.message, false);
        } else {
            showNotification(null, data.message, true);
            buttonElement.disabled = false;
            buttonElement.innerHTML = '<svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification(null, 'An error occurred while removing the item.', true);
        buttonElement.disabled = false;
        buttonElement.innerHTML = '<svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>';
    }); 
        }
    });
}

function addToCart(productId, productName, productPrice, productVariant, productCategory, productImage,productColor, buttonElement) {
    // Disable button to prevent double clicks
    buttonElement.disabled = true;
    const originalText = buttonElement.textContent;
    buttonElement.textContent = 'Adding...';

    // Get CSRF token from a form or hidden input
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

    // Create FormData to send as form data (not JSON)
    const formData = new FormData();
    formData.append('add-to-cart', '1');
    formData.append('product_id', productId.toString());
    formData.append('product_name', productName);
    formData.append('product_price', productPrice.toString());
    formData.append('product_quantity', '1');
    formData.append('product_variant', productVariant);
    formData.append('product_category', productCategory);
    formData.append('product_image', productImage);
    formData.append('csrf_token', csrfToken);
    formData.append('product_color', productColor);

    fetch('<?php echo universalstore_URL; ?>/cart.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        buttonElement.disabled = false;
        buttonElement.innerHTML = `<i class="fa-solid fa-cart-plus"></i> ${originalText}`;
        if (data.success) {
            // check new csrf token
            if (!checkEmptyCsrfToken(data.csrf_token) || !checkNewCsrfToken(data.csrf_token, csrfToken)) {
                return;
            }
            // Update CSRF token
            updateCsrfToken(data.csrf_token);
            // Create a temporary notification container if none exists
            let notificationContainer = document.getElementById('global-notification-container');
            if (!notificationContainer) {
                notificationContainer = document.createElement('div');
                notificationContainer.id = 'global-notification-container';
                notificationContainer.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; width: 300px;';
                document.body.appendChild(notificationContainer);
            }

            showNotification(notificationContainer, '✔ ' + data.message + ' added to cart' || 'Product added to cart successfully!', false);

            // Update cart count if element exists
            const cartCountElements = document.querySelectorAll('.cart-item');
            cartCountElements.forEach(el => {
                const currentCount = parseInt(el.textContent) || 0;
                el.textContent = currentCount + 1;
            });
        } else {
            // check new csrf token
            if (!checkEmptyCsrfToken(data.csrf_token) || !checkNewCsrfToken(data.csrf_token, csrfToken)) {
                return;
            }
            // Update CSRF token
            updateCsrfToken(data.csrf_token);
            // Create a temporary notification container if none exists
            let notificationContainer = document.getElementById('global-notification-container');
            if (!notificationContainer) {
                notificationContainer = document.createElement('div');
                notificationContainer.id = 'global-notification-container';
                notificationContainer.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; width: 300px;';
                document.body.appendChild(notificationContainer);
            }

            showNotification(notificationContainer, '!!! ' + data.message + ' Failed to add product to cart.' || 'Failed to add product to cart.', true);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        buttonElement.disabled = false;
        buttonElement.textContent = originalText;
        // check new csrf token
            if (!checkEmptyCsrfToken(data.csrf_token) || !checkNewCsrfToken(data.csrf_token, csrfToken)) {
                return;
            }
            // Update CSRF token
            updateCsrfToken(data.csrf_token);
        // Create a temporary notification container if none exists
        let notificationContainer = document.getElementById('global-notification-container');
        if (!notificationContainer) {
            notificationContainer = document.createElement('div');
            notificationContainer.id = 'global-notification-container';
            notificationContainer.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; width: 300px;';
            document.body.appendChild(notificationContainer);
        }

        showNotification(notificationContainer, 'An error occurred while adding to cart.', true);
    });
}

// ===== ACTIVITY HISTORY FILTERING & PAGINATION =====
function initializeActivityFilters() {
    const searchInput = document.getElementById('activity-search');
    const typeFilter = document.getElementById('activity-type-filter');
    const dateFrom = document.getElementById('activity-date-from');
    const dateTo = document.getElementById('activity-date-to');
    const clearFilters = document.getElementById('clear-filters');

    // Check if elements exist before adding listeners
    if (!searchInput || !typeFilter || !dateFrom || !dateTo || !clearFilters) {
        console.warn('Activity filter elements not found in DOM');
        return;
    }

    // Add event listeners
    searchInput.addEventListener('input', filterActivities);
    typeFilter.addEventListener('change', filterActivities);
    dateFrom.addEventListener('change', filterActivities);
    dateTo.addEventListener('change', filterActivities);
    clearFilters.addEventListener('click', clearAllFilters);

    // Initialize with all activities
    const allActivities = document.querySelectorAll('.activity-item');
    filteredActivities = Array.from(allActivities);
    updatePagination();
}

function filterActivities() {
    const searchInput = document.getElementById('activity-search');
    const typeFilter = document.getElementById('activity-type-filter');
    const dateFrom = document.getElementById('activity-date-from');
    const dateTo = document.getElementById('activity-date-to');

    if (!searchInput || !typeFilter || !dateFrom || !dateTo) return;

    const searchTerm = searchInput.value.toLowerCase();
    const typeFilterValue = typeFilter.value;
    const dateFromValue = dateFrom.value;
    const dateToValue = dateTo.value;

    const allActivities = document.querySelectorAll('.activity-item');

    filteredActivities = Array.from(allActivities).filter(activity => {
        const type = activity.dataset.type;
        const description = activity.dataset.description || '';
        const orderId = activity.dataset.orderId || '';
        const paymentId = activity.dataset.paymentId || '';
        const timestamp = activity.dataset.timestamp;

        // Type filter
        if (typeFilterValue && type !== typeFilterValue) {
            return false;
        }

        // Search filter
        if (searchTerm) {
            const searchableText = `${description} ${orderId} ${paymentId}`.toLowerCase();
            if (!searchableText.includes(searchTerm)) {
                return false;
            }
        }

        // Date range filter
        if (dateFromValue || dateToValue) {
            const activityDate = new Date(timestamp);
            if (dateFromValue) {
                const fromDate = new Date(dateFromValue);
                if (activityDate < fromDate) return false;
            }
            if (dateToValue) {
                const toDate = new Date(dateToValue);
                toDate.setHours(23, 59, 59, 999); // End of day
                if (activityDate > toDate) return false;
            }
        }

        return true;
    });

    currentPage = 1;
    updatePagination();
}

function clearAllFilters() {
    const searchInput = document.getElementById('activity-search');
    const typeFilter = document.getElementById('activity-type-filter');
    const dateFrom = document.getElementById('activity-date-from');
    const dateTo = document.getElementById('activity-date-to');

    if (searchInput) searchInput.value = '';
    if (typeFilter) typeFilter.value = '';
    if (dateFrom) dateFrom.value = '';
    if (dateTo) dateTo.value = '';

    const allActivities = document.querySelectorAll('.activity-item');
    filteredActivities = Array.from(allActivities);
    currentPage = 1;
    updatePagination();
}

function updatePagination() {
    const totalItems = filteredActivities.length;
    const totalPages = Math.ceil(totalItems / itemsPerPage);
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;

    // Hide all activities
    document.querySelectorAll('.activity-item').forEach(activity => {
        activity.style.display = 'none';
    });

    // Show filtered activities for current page
    const activitiesToShow = filteredActivities.slice(startIndex, endIndex);
    activitiesToShow.forEach(activity => {
        activity.style.display = 'flex';
    });

    // Update pagination controls
    const prevBtn = document.getElementById('prev-page');
    const nextBtn = document.getElementById('next-page');
    const pageInfo = document.getElementById('page-info');

    if (!prevBtn || !nextBtn || !pageInfo) return;

    prevBtn.disabled = currentPage === 1;
    nextBtn.disabled = currentPage === totalPages;
    pageInfo.textContent = `Page ${currentPage} of ${totalPages || 1}`;

    // Update button event listeners
    prevBtn.onclick = () => {
        if (currentPage > 1) {
            currentPage--;
            updatePagination();
        }
    };

    nextBtn.onclick = () => {
        if (currentPage < totalPages) {
            currentPage++;
            updatePagination();
        }
    };
}

    document.addEventListener('DOMContentLoaded', function() {
       //const profilModal = document.getElementById('profilModal');
        // Select ALL elements with the class 'save-btn'
        const saveButtons = document.querySelectorAll('.save-btn');

        // We now Loop through each save button and attach the listener
        saveButtons.forEach(saveButton => {
        saveButton.addEventListener('click', function(event){
            event.preventDefault();

            //  Determnine context for each form: Get the ID of the form associated with THIS button
            // since the button is inside the footer modal we are going to find its form/modal container.
            const formId = saveButton.getAttribute('data-form-id');
            const form = document.getElementById(formId);

            if(!form){
                showNotification(noticeContainer, 'Error: Could not find the associated form.', true);
                return;
            }
            // Now lets call a generic handler function, passing the form object
            handleFormSubmission(form, saveButton);

             });
        });

        // Initialize activity history filters and pagination
        initializeActivityFilters();

        function initializeActivityFilters() {
            const searchInput = document.getElementById('activity-search');
            const typeFilter = document.getElementById('activity-type-filter');
            const dateFrom = document.getElementById('activity-date-from');
            const dateTo = document.getElementById('activity-date-to');
            const clearFilters = document.getElementById('clear-filters');

            // Add event listeners
            searchInput.addEventListener('input', filterActivities);
            typeFilter.addEventListener('change', filterActivities);
            dateFrom.addEventListener('change', filterActivities);
            dateTo.addEventListener('change', filterActivities);
            clearFilters.addEventListener('click', clearAllFilters);

            // Initialize with all activities
            const allActivities = document.querySelectorAll('.activity-item');
            filteredActivities = Array.from(allActivities);
            updatePagination();
        }

        function filterActivities() {
            const searchTerm = document.getElementById('activity-search').value.toLowerCase();
            const typeFilter = document.getElementById('activity-type-filter').value;
            const dateFrom = document.getElementById('activity-date-from').value;
            const dateTo = document.getElementById('activity-date-to').value;

            const allActivities = document.querySelectorAll('.activity-item');

            filteredActivities = Array.from(allActivities).filter(activity => {
                const type = activity.dataset.type;
                const description = activity.dataset.description || '';
                const orderId = activity.dataset.orderId || '';
                const paymentId = activity.dataset.paymentId || '';
                const timestamp = activity.dataset.timestamp;

                // Type filter
                if (typeFilter && type !== typeFilter) {
                    return false;
                }

                // Search filter
                if (searchTerm) {
                    const searchableText = `${description} ${orderId} ${paymentId}`.toLowerCase();
                    if (!searchableText.includes(searchTerm)) {
                        return false;
                    }
                }

                // Date range filter
                if (dateFrom || dateTo) {
                    const activityDate = new Date(timestamp);
                    if (dateFrom) {
                        const fromDate = new Date(dateFrom);
                        if (activityDate < fromDate) return false;
                    }
                    if (dateTo) {
                        const toDate = new Date(dateTo);
                        toDate.setHours(23, 59, 59, 999); // End of day
                        if (activityDate > toDate) return false;
                    }
                }

                return true;
            });

            currentPage = 1;
            updatePagination();
        }

        function clearAllFilters() {
            document.getElementById('activity-search').value = '';
            document.getElementById('activity-type-filter').value = '';
            document.getElementById('activity-date-from').value = '';
            document.getElementById('activity-date-to').value = '';

            const allActivities = document.querySelectorAll('.activity-item');
            filteredActivities = Array.from(allActivities);
            currentPage = 1;
            updatePagination();
        }

        function updatePagination() {
            const totalItems = filteredActivities.length;
            const totalPages = Math.ceil(totalItems / itemsPerPage);
            const startIndex = (currentPage - 1) * itemsPerPage;
            const endIndex = startIndex + itemsPerPage;

            // Hide all activities
            document.querySelectorAll('.activity-item').forEach(activity => {
                activity.style.display = 'none';
            });

            // Show filtered activities for current page
            const activitiesToShow = filteredActivities.slice(startIndex, endIndex);
            activitiesToShow.forEach(activity => {
                activity.style.display = 'flex';
            });

            // Update pagination controls
            const prevBtn = document.getElementById('prev-page');
            const nextBtn = document.getElementById('next-page');
            const pageInfo = document.getElementById('page-info');

            prevBtn.disabled = currentPage === 1;
            nextBtn.disabled = currentPage === totalPages;
            pageInfo.textContent = `Page ${currentPage} of ${totalPages || 1}`;

            // Update button event listeners
            prevBtn.onclick = () => {
                if (currentPage > 1) {
                    currentPage--;
                    updatePagination();
                }
            };

            nextBtn.onclick = () => {
                if (currentPage < totalPages) {
                    currentPage++;
                    updatePagination();
                }
            };
        }

        // Wishlist functions
        function removeFromWishlist(productId, buttonElement) {
            if (!confirm('Remove this item from your wishlist?')) {
                return;
            }

            // Disable button to prevent double clicks
            buttonElement.disabled = true;
            buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            fetch('<?php echo INTERNAL_LOGIC_URL; ?>/wishlist_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'remove',
                    product_id: productId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the item from DOM
                    buttonElement.closest('.fav-card').remove();

                    // Update wishlist count if it exists
                    const countElements = document.querySelectorAll('.wishlist-count');
                    countElements.forEach(el => {
                        const currentCount = parseInt(el.textContent) || 0;
                        if (currentCount > 0) {
                            el.textContent = currentCount - 1;
                        }
                    });

                    // Show success message
                    showNotification(null, data.message, false);
                } else {
                    showNotification(null, data.message, true);
                    buttonElement.disabled = false;
                    buttonElement.innerHTML = '<svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification(null, 'An error occurred while removing the item.', true);
                buttonElement.disabled = false;
                buttonElement.innerHTML = '<svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>';
            });
        }

        function addToCart(productId, buttonElement) {
            // Disable button to prevent double clicks
            buttonElement.disabled = true;
            const originalText = buttonElement.textContent;
            buttonElement.textContent = 'Adding...';

            fetch('<?php echo universalstore_URL; ?>/cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: 'add',
                    product_id: productId,
                    quantity: 1
                })
            })
            .then(response => response.json())
            .then(data => {
                buttonElement.disabled = false;
                buttonElement.textContent = originalText;

                if (data.success) {
                    showNotification(null, data.message, false);

                    // Update cart count if element exists
                    const cartCountElements = document.querySelectorAll('.cart-item');
                    cartCountElements.forEach(el => {
                        const currentCount = parseInt(el.textContent) || 0;
                        el.textContent = currentCount + 1;
                    });
                } else {
                    showNotification(null, data.message, true);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                buttonElement.disabled = false;
                buttonElement.textContent = originalText;
                showNotification(null, 'An error occurred while adding to cart.', true);
            });
        }

        // Now lets create a Generic Submission Handler Function
        function handleFormSubmission(form, saveButton){ 
            // Determine the correct notice container ID based on the form ID
            let noticeContainer = null;
            // Get the modal that contains the form
            const modalElement = form.closest('.modal');
            let noticeContainerId;

            if (form.id === 'deliveryAddressForm') {
        noticeContainerId = 'deliveryAddress-notice-container';
    } else if (form.id === 'personalInfoForm') {
        noticeContainerId = 'personalInfo-notice-container';
    }else if(form.id === 'editPasswordForm'){
        noticeContainerId = 'editPassword-notice-container';
    } else {
        console.error("Unknown form ID:", form.id);
        // Try to find a generic notice container if the form ID is unknown
        const genericNoticeContainer = form.closest('.modal').querySelector('[id$="-notice-container"]');
        if (genericNoticeContainer) {
            showNotification(genericNoticeContainer, 'An unexpected error occurred. Unknown form.', true);
        }
        return;
    }
            noticeContainer = document.getElementById(noticeContainerId);

            if (!noticeContainer) {
                console.error('Internal system error: Missing notification area for ID:', noticeContainerId);
                alert('An internal error occurred. Please refresh the page.');
                return; 
            }

            if(form.id === 'deliveryAddressForm'){
                const fname = form.querySelector('input[name="fname"]').value;
                const lname = form.querySelector('input[name="lname"]').value;
                const country = form.querySelector('select[name="country"]').value;
                const postcode = form.querySelector('input[name="postcode"]').value;
                const address = form.querySelector('input[name="address"]').value;
                const city = form.querySelector('input[name="city"]').value;
                const csrfTokenEl = form.querySelector('input[name="csrf_token"]');
                const currentCsrfToken = csrfTokenEl ? csrfTokenEl.value : '';

                if(!checkEmptyCsrfToken(currentCsrfToken)) return;

                if(!fname || !lname || !country || !postcode || !address || !city){
                    showNotification(noticeContainer, 'Please fill in all required fields.', true);
                    return;
                }

                const randomId = crypto.randomUUID();
                const formData = new FormData();
                formData.append('fname', fname);
                formData.append('lname', lname);
                formData.append('country', country);
                formData.append('postcode', postcode);
                formData.append('address', address);
                formData.append('city', city);
                formData.append('delivery_id', randomId);
                formData.append('ajax', '1');

                executeFetch(formData, saveButton, currentCsrfToken, modalElement, noticeContainer);

            } else if (form.id === 'personalInfoForm') {
                const fname = form.querySelector('input[name="fname"]').value;
                const lname = form.querySelector('input[name="lname"]').value;
                const email = form.querySelector('input[name="email"]').value;
                const csrfTokenEl = form.querySelector('input[name="csrf_token"]');
                const currentCsrfToken = csrfTokenEl ? csrfTokenEl.value : '';

                if(!checkEmptyCsrfToken(currentCsrfToken)) return;

                function getFullPhoneNumber() {
                    const codeElement = document.getElementById('countryCodeDisplay');
                    const countryCode = codeElement ? codeElement.textContent.trim() : '';
                    const numberInput = document.getElementById('phoneNumberInput');
                    const phoneNumber = numberInput ? numberInput.value.trim() : '';
                    if (countryCode && phoneNumber) {
                        return countryCode + phoneNumber;
                    }
                    return phoneNumber; 
                }

                const fullPhoneNumber = getFullPhoneNumber();

                if (!fname || !lname || !email) {
                    showNotification(noticeContainer, 'Please fill in all required personal information fields.', true);
                    return;
                }

                const formData = new FormData();
                const personal_info_flag = crypto.randomUUID();
                formData.append('fname', fname);
                formData.append('lname', lname);
                formData.append('email', email);
                formData.append('phone', fullPhoneNumber);
                formData.append('personal_info_flag', personal_info_flag);
                formData.append('ajax', '1');

                executeFetch(formData, saveButton, currentCsrfToken, modalElement, noticeContainer);
            }else if(form.id === 'editPasswordForm'){
                const currentPassword = form.querySelector('input[name="currentPassword"]').value;
                const newPassword = form.querySelector('input[name="NewPassword"]').value;
                const confirmNewPass = form.querySelector('input[name="ConfirmNewPassword"]');
                const csrfToken = form.querySelector('input[name="csrf_token"]').value;
                const currentCsrfToken = csrfToken ? csrfToken : '';

                if(!checkEmptyCsrfToken(currentCsrfToken)) return;
                if(!currentPassword || !newPassword || !confirmNewPass.value){
                    showNotification(noticeContainer,"Please fill in all required fields.", true);
                    return;
                }
                if(newPassword !== confirmNewPass.value){
                    showNotification(noticeContainer, "New password and Confirm password don't match.", true);
                    return;
                }
                const formData = new FormData();
                const password_flag = crypto.randomUUID();
                formData.append('currentPassword', currentPassword);
                formData.append('newPassword', newPassword);
                formData.append('confirmPassword', confirmNewPass.value);
                formData.append('password_flag', password_flag);
                formData.append('ajax', '1');

                executeFetch(formData, saveButton, currentCsrfToken, modalElement, noticeContainer);

        }
    }
    // Function to handle the actual fetch request and response processing
        function executeFetch(formData, saveButton, currentCsrfToken, modalElement, noticeContainer){
             const formId = saveButton.getAttribute('data-form-id');
            const form = document.getElementById(formId);
            const originalText = saveButton.textContent;
            saveButton.disabled = true; //disable button to prevent multiple clicks
            saveButton.textContent = 'Saving...';
            const PROFILE_LOGIC = '<?php echo INTERNAL_LOGIC_URL;  ?>';
            fetch( PROFILE_LOGIC + '/profile_logic.php', {
                method: 'POST',
                body:formData,
                headers: {
                    'X-Requested-With' : 'XMLHttpRequest',
                    'X-CSRF-Token' : currentCsrfToken
                }
            })
            .then(response => {// Handle non-200 responses that my profile_logic.php returns (e.g., 403 Forbidden, 500 Server Error)
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                // check for new csrfToken
                if(data.csrf_token && data.csrf_token !== currentCsrfToken){
                    updateCsrfToken(data.csrf_token);
                }else{
                     showNotification(noticeContainer, 'Something happened while processing your request. Please refresh page.', true);
                     return;
                }
                saveButton.disabled = false; //re-enable button
                saveButton.textContent = originalText; //restore button text
                if(data.success){
                  

                   if(form.id === "deliveryAddress"){
                   const deliveryContainer = document.querySelector('.deliveryContainer');
                   if(deliveryContainer){
                   deliveryContainer.querySelector('.deliveryContainer .d_name').textContent = data.fname + " " + data.lname;
                   deliveryContainer.querySelector('.deliveryContainer .d_postcode').textContent = data.postcode;
                   deliveryContainer.querySelector('.deliveryContainer .d_address').textContent = data.address;
                   deliveryContainer.querySelector('.deliveryContainer .d_city').textContent = data.city;
                   deliveryContainer.querySelector('.deliveryContainer .d_country').textContent = data.country;
                   }else{
                    console.error("Critical Error: Delivery container '.deliveryAdd' not found.");
                   } 
                }
                   // Close modal after success
                    const bsModal = bootstrap.Modal.getInstance(modalElement);
                    if(bsModal){
                        bsModal.hide();
                    }
                    showNotification(noticeContainer, data.message, false);
                    // Reload page after a short delay to allow notification to display
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification(noticeContainer, data.message, true);
                    if(data.csrf_token && data.csrf_token !== currentCsrfToken){
                    updateCsrfToken(data.csrf_token);
                }else{
                     showNotification(noticeContainer, 'Something happened while adding item to Cart. Please refresh page.', true);
                     return;
                }
                }
            })
            .catch(error =>{
                console.error('Error:', error);
                showNotification(noticeContainer, 'An error occurred while processing your request. Please try again later.', true);
                saveButton.disabled = false;
                saveButton.textContent = originalText;
            });
       }
    });


</script>

    <!-- Footer -->
    <footer class="mt-5 pt-5 bg-black text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-20">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 pt-3">
                <div class="footer-one mb-5 mt-5">
                <img src="https://ik.imagekit.io/univ35tore/universal_store/imgs/UNIVERSAL%20STORE.jpg" alt="Universal Store logo">
                    <p class="text-gray-300">We provide the best products for the most affordable prices</p>
                </div>

                <div class="footer-one mb-5 mt-5">
                    <h5 class="pb-2 font-semibold text-white">Featured</h5>
                    <ul class="text-uppercase space-y-1 text-gray-300">
                        <li><a href="#" class="hover:text-white transition">iPhone</a></li>
                        <li><a href="#" class="hover:text-white transition">iPad</a></li>
                        <li><a href="#" class="hover:text-white transition">Samsung</a></li>
                        <li><a href="#" class="hover:text-white transition">Redmi Xiaomi</a></li>
                        <li><a href="#" class="hover:text-white transition">Google Pixel</a></li>
                    </ul>
                </div>

                <div class="footer-one mb-5 mt-4">
                    <h5 class="pb-2 font-semibold text-white">Contact Us</h5>
                    <div class="space-y-2">
                        <div>
                            <h6 class="text-uppercase font-semibold text-white">Address</h6>
                            <p class="text-gray-300">Marché Central, Yaoundé</p>
                        </div>
                        <div>
                            <h6 class="text-uppercase font-semibold text-white">Phone</h6>
                            <p><a href="tel:694-81-76-63" class="text-gray-300 hover:text-white transition">+237 694817663</a></p>
                        </div>
                        <div>
                            <h6 class="text-uppercase font-semibold text-white">Email</h6>
                            <p><a href="mailto:arnauldfonkoua@gmail.com" class="text-gray-300 hover:text-white transition">arnauldfonkoua@gmail.com</a></p>
                        </div>
                    </div>
                </div>

                <div class="footer-one social mb-5 mt-5">
                    <h6 class="text-uppercase font-semibold text-white">Social</h6>
                    <ul class="flex space-x-3 mt-2">
                        <li><a href="#" aria-label="Facebook" class="text-gray-300 hover:text-white transition"><i class="fab fa-facebook-square"></i></a></li>
                        <li><a href="https://www.instagram.com/arn_a_uld?igsh=NGQ2MGRrM211dWhu&utm_source=qr" aria-label="Instagram" class="text-gray-300 hover:text-white transition"><i class="fab fa-instagram"></i></a></li>
                        <li><a href="https://chat.whatsapp.com/Ji9GrDac59dGB3Wwy7qKr3" aria-label="WhatsApp" class="text-gray-300 hover:text-white transition"><i class="fab fa-whatsapp"></i></a></li>
                    </ul>
                </div>
            </div>

            <div class="copyright mt-5 pt-3 border-t border-gray-700">
                <div class="flex flex-col md:flex-row justify-between items-center text-center">
                    <div class="flex justify-center gap-3 mb-3 md:mb-0">
                        <img src="https://ik.imagekit.io/univ35tore/universal_store/imgs/mtn.webp?updatedAt=1770682540718" alt="MTN" class="h-8" />
                        <img src="https://ik.imagekit.io/univ35tore/universal_store/imgs/om.jpeg?updatedAt=1770682540781" alt="Orange Money" class="h-8" />
                    </div>
                    <div>
                        <p class="mb-0 text-gray-300">Universal Store © 2025 — All Rights Reserved</p>
                    </div>
                </div>
            </div>
        </div>
    </footer>
<script src="<?php echo ASSETS_URL; ?>/js/profile.js"></script>
<script src="<?php echo ASSETS_URL; ?>/includes/bootstrap-5.3.7-dist/js/bootstrap.bundle.min.js"></script>
<script 
  src="https://cdn.jsdelivr.net/npm/sweetalert2@11" 
  integrity="sha384-QjoPbdj/93O7LUz0wqTxepA3tIabUD3jzfZX+x5QLvqFtHBzSw4eYFLSVthB+EDT" 
  crossorigin="anonymous">
</script>
</body>
</html>