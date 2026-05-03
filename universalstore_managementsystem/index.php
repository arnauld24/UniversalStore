<?php

use external_server\Connect;
use internal_logic\Cart;
use core\OptimizedTracking;

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


require_once SERVER_PATH . '/secure_session_csrf_cookie.php';

// Include wishlist helper
require_once __DIR__ . '/wishlist_helper.php';

// Initialize OptimizedTracking for analytics
OptimizedTracking::init();
// Track page view
OptimizedTracking::trackEvent('page_view', ['page' => 'index', 'url' => $_SERVER['REQUEST_URI']]);

// Cart function to get cart item count
$cart = new Cart($authenticatedUserId);

//if user logged in
if($cart->isLoggedIn()){
  //Get cart_id for logged_in user through his user_id
  $cart_id = $cart->getOrCreateCartIdForUser($cart->getAuthenticatedUserId());
  //Get cart_item_count for display
  $cart_items_count = $cart->getCartItemCountForCart($cart_id);
}
//user not logged_in therefore visitor, display session cart_items count
else{
$cart_items_count = $cart->getGuestCartItemCount();
}



// Create a function to generate product url slugs for more secure product url

function createSlug($string){
  $string = strtolower($string); // Convert to lowercase
  $string = preg_replace('/[^a-z0-9]+/i', '-', $string); // Replace non-alphanumeric characters with hyphens
  $string = preg_replace('/^-+|-+$/', '', $string); // Remove leading and trailing hyphens
  return rtrim($string, '-'); // Remove any trailing hyphens
}

function renderRatingStars($rating){
  $rating = max(0, min(5, floatval($rating)));
  $fullStars = floor($rating);
  $halfStar = ($rating - $fullStars) >= 0.5 ? 1 : 0;
  $emptyStars = 5 - $fullStars - $halfStar;
  $html = '';
  for($i=0;$i<$fullStars;$i++){
    $html .= '<i class="fas fa-star text-warning"></i>';
  }
  if($halfStar){
    $html .= '<i class="fas fa-star-half-alt text-warning"></i>';
  }
  for($i=0;$i<$emptyStars;$i++){
    $html .= '<i class="far fa-star text-warning"></i>';
  }
  return $html;
}

function fetchCategoryProducts($category, $limit = 8) {
    try {
        $conn = new Connect();
        $normalized = strtolower(trim($category));
        $stmt = $conn->prepare("SELECT * FROM products WHERE LOWER(product_category) LIKE :category OR LOWER(product_category) = :exact ORDER BY product_id DESC LIMIT " . (int)$limit);
        $stmt->bindValue(':category', '%' . $normalized . '%', PDO::PARAM_STR);
        $stmt->bindValue(':exact', $normalized, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        return [];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Universal Store</title>

  <!-- bootstrap CDN -->
  <link rel="stylesheet" href="<?php echo universalstore_URL; ?>/assets/includes/bootstrap-5.3.7-dist/css/bootstrap.min.css">

  <!-- Font Awesome CDN -->
  <link rel="stylesheet" href="<?php echo universalstore_URL; ?>/assets/includes/fontawesome-free-5.15.4-web/css/all.css">
  <link rel="stylesheet" href="<?php echo universalstore_URL; ?>/assets/includes/fontawesome/fontawesome-free-6.7.2-web/css/all.css">

  <!-- custom css file -->
  <link rel="stylesheet" href="<?php echo universalstore_URL; ?>/assets/css/style1.css">
  <!-- <link rel="stylesheet" href="<?php echo universalstore_URL; ?>/assets/css/navbars.css"> -->
  <link rel="stylesheet" href="<?php echo universalstore_URL; ?>/assets/css/tailwindcss.css">
  <link href="https://ik.imagekit.io/univ35tore/universal_store/imgs/UNIVERSAL%20STORE.jpg" rel="icon">
  <style>
    :root {
      --brand-primary: #5a4ce3;
      --brand-secondary: #22c3da;
      --brand-contrast: #191f38;
      --card-bg: #ffffff;
      --soft-bg: #f5f7ff;
    }
    body { background: #f6f8ff; color: #1e2343; }
    .hero-section { position: relative; min-height: 78vh; color: white; display: flex; align-items: center; background: linear-gradient(180deg, rgba(10,21,78,0.75), rgba(10, 21, 78, 0.23)), url('https://ik.imagekit.io/univ35tore/universal_store/imgs/brand%20image.jpg?updatedAt=1774001975028') center/cover no-repeat; }
    .hero-content { padding: 3rem 1rem; max-width: 660px; }
    .hero-content h1 { font-size: 3rem; line-height:1.05; font-weight: 800; margin-bottom: 1rem; }
    .hero-content p { font-size: 1.15rem; margin-bottom: 1.5rem; color: #dbe2ff; }
    .hero-content .btn-primary { background: var(--brand-primary); border-color: var(--brand-primary); padding: 0.85rem 2rem; border-radius: 12px; font-weight: 700; }
    .hero-content .btn-secondary { margin-left: 0.75rem; padding: 0.85rem 1.8rem; border-radius: 12px; }
    .info-cards { display: grid; grid-template-columns: repeat(auto-fit,minmax(220px,1fr)); gap: 1rem; margin-top: 1.7rem; }
    .info-card { background: #ffffff; border-radius: 15px; box-shadow: 0 12px 30px rgba(25,32,70,0.09); padding: 1.2rem; border: 1px solid #edf0ff; } 
    .info-card i { font-size: 1.4rem; color: var(--brand-primary); margin-right: 0.65rem; }
    .section-heading { font-size: 2rem; font-weight: 800; color: var(--brand-contrast); margin: 2rem 0 1rem; }
    .section-subtitle { color: #6f7b9a; }
    .product-stand { background: #f7f8fa; border-radius: 8px; padding: 16px;}
    .products { background: var(--card-bg); border-radius: 10px; border: 1px solid #e3e5e8; padding: 16px; margin-bottom: 1rem; min-height: 0px; box-shadow: 0px 4px 16px rgba(0,0,0,0.08); }
    .products .product-image { width: 100%; height: 140px; object-fit: contain;  }
    .products .star, .products .ratings { margin-bottom: 8px; color: #f59e0b; }
    .products .rating-text { color: #556068; font-size: 0.8rem; margin-top: 0.45rem; }
    .products h4, .products .p-name {overflow: hidden; }
    .section-banner { justify-self:center; width:98%;position: relative; border-radius: 20px; overflow: hidden; margin: 2rem 0; height: 320px; background: linear-gradient(90deg, rgba(33,115,255,0.75), rgba(77,215,255,0.42)); }
    .section-banner img{ width:100%; height:100%; object-fit: cover; opacity:1; }
    .section-banner .overlay{ position:absolute; top:0; left:0; right:0; bottom:0; background:linear-gradient(135deg, rgba(14,20,69,0.65), rgba(21,34,85,0.45)); display:flex; align-items:center; justify-content:center; padding: 2rem; text-align:center; }
    .category-scroll-section .cat-row { margin-bottom: 1.5rem; }
    .category-scroll-section .cat-title { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.8rem; font-size: 1.5rem; color: #3a3f4f; }
    .category-scroll-section .scroll-row { display: flex; gap: 1rem; overflow-x: auto; padding-bottom: 0.5rem; scroll-snap-type: x mandatory; }
    .category-scroll-section .scroll-row::-webkit-scrollbar{ height: 2px; }
    .category-scroll-section .scroll-row::-webkit-scrollbar-thumb{ background: #7f58ff33; border-radius: 20px; }
    .category-scroll-section .scroll-card { min-width: 220px; max-width: 220px; flex: 0 0 auto; scroll-snap-align: start; background: rgba(255,255,255,0.95); border: 1px solid #e6e9f6; border-radius: 16px; padding: 12px; }
    .category-scroll-section .scroll-card .product-image { height: 180px; object-fit: contain; }
    .category-scroll-section .scroll-card .P-price { color: #1a73e8; font-weight: 700; }
    .video-section { margin: 2rem 0; }
    .video-area { position: relative; border-radius: 18px; overflow: hidden; box-shadow: 0px 8px 30px rgba(11, 19, 52, .15); }
    .video-area video { width: 100%; display: block; height: auto; cursor:pointer;}
    /* .play-overlay {cursor:pointer; position:absolute; left:50%; top:50%; transform:translate(-50%,-50%); background: rgba(0,0,0,0.5); width:68px; height:68px; border-radius:50%; display:flex; align-items:center; justify-content:center; }
    .play-overlay i { font-size: 1.8rem; color: #fff; } */
    .blog-card { background: #fff; border-radius: 14px; border: 1px solid #e7edff; box-shadow: 0 10px 25px rgba(28,34,95,.08); overflow:hidden; }
    .blog-card img { width: 100%; height: 170px; object-fit: contain; }

    /* FAQ / Bootstrap accordion fixes to avoid Tailwind collapse conflict */
    .faq-section .accordion-button { background: #fff; color: #1f2a51; }
    .faq-section .accordion-body { color: #343a40; font-size: 1rem; }
    .faq-section .collapse { display: none !important; visibility: hidden !important; }
    .faq-section .collapse.show { display: block !important; visibility: visible !important; }

    .announcement-popup { position: fixed; inset: 0; background: rgba(0,0,0,.5); display: none; align-items: center; justify-content: center; z-index: 1080; }
    .announcement-popup .popup-card { background: #fff; border-radius: 20px; max-width: 480px; width: 92%; padding: 1.4rem; text-align: center; box-shadow: 0 24px 40px rgba(15,20,47,.45); }
    .announcement-popup .popup-card h4 { margin-bottom: 0.5rem; font-weight:700; }
    .announcement-popup .popup-card .btn-close { position:absolute; top:14px; right:14px; }

    @media (max-width: 767px) { .hero-content h1 { font-size: 2.1rem; } }
  </style>
  
</head>
<nav>
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
          <a href="index" class="brand">
          <img class="logo" src="https://ik.imagekit.io/univ35tore/universal_store/imgs/UNIVERSAL%20STORE.jpg" alt="Universal Store logo" />
          <span>UNIVERSAL STORE</span>
          </a>
            <div class="search-box hiddened" style="background:white; border-radius:30px;">
            <i class="fas fa-search"></i>
            <input type="search" name="search" id="search" placeholder="search key words.." autocomplete="off"> 
        </div>
        
        <ul class="main-menu">
        <li>
            <span type="button" class="purchase-btn hiddened"><a href="categories">Start Shopping</a></span>
        </li>
             <li>
            <a href="contact"><span class="help">Contact us</span></a>
            </li>
             <li>
             <a class="nav-link position-relative cart" href="cart" aria-label="Cart">
              <i class="fas fa-shopping-bag"></i>
              <?php if(!empty($cart_items_count) && isset($cart_items_count)) {?>
              <span class="cart-item" style="font-size: 0.75rem;"><?php echo htmlspecialchars($cart_items_count, ENT_QUOTES, 'UTF-8') ?>
              <span class="visually-hidden">Items in cart</span></span>
              <?php }?>
             </a>
            </li>
            <?Php if($authenticatedUserId){?>
             <!-- Account button  -->
            <li class="nav-item dropdown">
            <a class="nav-link d-inline-flex align-items-center justify-content-center account-btn bg-white/48 hover:bg-white/58 hover:border-white" style="padding: 0px 0px;" href="#" id="navbarAccountDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account">
             <img src="<?php echo htmlspecialchars($user_data['user_avatar'] ?? 'avatar', ENT_QUOTES, 'UTF-8'); ?>" style="width: 40px; height:40px; border-radius: 30px; "class="w-8 h-8 lg:w-8 lg:h-8 border rounded-full">
                </a>
          <ul class="dropdown-menu dropdown-menu-end shadow-lg" aria-labelledby="navbarAccountDropdown" style="min-width:18rem;">
            <div class="border-bottom py-3 font-size-20 px-4 font-rubik color-smoke"><span >My Account</span></div>
            <li><a href="dashboard/profile?tab=profil" class="dropdown-item border-bottom d-flex align-items-center py-4 font-rubik font-weight-500 mb-1 "><i class="fa-regular fa-user color-light-blue me-2"></i> Profil</a></li>
            <li><a href="dashboard/profile?tab=orders"class="dropdown-item border-bottom d-flex align-items-center py-4 font-rubik font-weight-500 mb-1 "><i class="fas fa-box-open color-orange me-2"></i>Orders and help</a></li>
            <li><a href="dashboard/profile?tab=wishlists" class="dropdown-item border-bottom d-flex align-items-center py-4 font-rubik font-weight-500 mb-1 "><i class="fas fa-heart me-2"></i>Wishlists</a></li>
            <li><a href="dashboard/profile?tab=activities" class="dropdown-item border-bottom d-flex align-items-center py-4 font-rubik font-weight-500 mb-1 "><i class="fa-solid fa-clock-rotate-left color-light-red me-2"></i>Activity history</a></li>
            <li><a href="?action=logout" class="dropdown-item d-flex align-items-center py-4 font-rubik font-weight-500 logout"><i class="fa-solid fa-power-off color-light-red me-2"></i>Log Out</a></li>
          </ul>
        </li>
        <?php } else{?>
          <li>
             <a class="nav-link p-0 color-smoke" href="account/authentication/authentication" aria-label="Account"><i class="fas fa-user"></i></a>
            </li>
          <?php }?>
        </ul>
        <i class="fa-solid fa-bars toggle-navbar"></i>
    </div>

    <!-- Search row (second row on mobile) -->
    <div class="search-row ">
        <div>
            <span type="button" class="purchase-btn"><a href="<?php echo universalstore_URL; ?>/categories">Start Shopping</a></span>
        </div>
        <div class="search-box" style="background:white; border-radius:30px;">
            <i class="fas fa-search"></i>
            <input type="search" name="search" id="searchs" placeholder="search key words.." autocomplete="off"> 
        </div>
        
    </div>
            <div class="container">
            <ul class="nav-menu ">
            <!-- <li>
            <a href="#" class="bottom">Free Plan<i class="fas fa-chevron-right hidden-arrow "></i>
            <span><hr class="hidden"/></span> 
            </a>
            </li> -->
             <li>
            <a href="<?php echo universalstore_URL; ?>/category/smartphones" class="bottom" id="menuItem">Smartphones<i class="fas fa-chevron-right hidden-arrow "></i>
            <span><hr class="hidden"/></span>
            </a>
            <div class="dropdown-menu" id="dropdown-menu">
            <div class="container">
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
                        <img src="assets/imgs/dropimage22.webp" class="dropdown-img" alt="iPhone 12 pro max">
                        <p>iPhone</p>
                        </div>
                    </a>
                   </li> 
                     <li class="col-lg-4 col-md-6 col-sm-6 ">
                    <a href="<?php echo universalstore_URL; ?>/category/smartphones?category=samsung">
                        <div>
                        <img src="assets/imgs/dropimage17.webp" class="dropdown-img2" alt="Samsung Galaxy">
                        <p>Samsung Galaxy</p>
                        </div>
                    </a>
                   </li> 
                     <li class="col-lg-4 col-md-6 col-sm-6 ">
                    <a href="<?php echo universalstore_URL; ?>/category/smartphones?category=samsung">
                        <div>
                        <img src="assets/imgs/dropimage16.webp" class="dropdown-img3" alt="Samsung S23 ultra">
                        <p>Samsung</p>
                        </div>
                    </a>
                   </li> 
                     <li class="col-lg-4 col-md-6 col-sm-6 ">
                    <a href="<?php echo universalstore_URL; ?>/category/smartphones?category=pixel">
                        <div>
                        <img src="assets/imgs/dropimage26.webp" class="dropdown-img4" alt="Google Pixel 9">
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
                        <span><hr class="hidden"/></span>
                        </a>
                         <div class="dropdown-menu" id="dropdown-menu">
                        <div class="container">
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
                        <span><hr class="hidden"/></span>
                        </a>
                         <div class="dropdown-menu" id="dropdown-menu">
                        <div class="container">
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
                            <a href="<?php echo universalstore_URL; ?>/category/consoles" class="btn-see-all">See all</a>
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
                        <span><hr class="hidden"/></span>
                        </a>
                   </li>
                   <li>
                        <a href="<?php echo universalstore_URL; ?>/category/gadgets" class="bottom">Gadgets<i class="fas fa-chevron-right hidden-arrow "></i>
                        <span><hr class="hidden"/></span>
                        </a>
                   </li>
                   <li>
                        <a href="<?php echo universalstore_URL; ?>/category/apple-watches" class="bottom">Apple watches<i class="fas fa-chevron-right hidden-arrow "></i>
                        <span><hr class="hidden"/></span>
                        </a>
                   </li>
                   <li>
                        <a href="<?php echo universalstore_URL; ?>/category/others" class="bottom">Others<i class="fas fa-chevron-right hidden-arrow "></i>
                        <span><hr class="hidden"/></span>
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
<div class="container">
<div id="cart-notice-container" class="col-12 col-sm-6 col-md-12" style="position: fixed; top: 50px; left: 80%; transform: translateX(-50%); z-index: 1050; width: 100%; max-width: 350px;"></div>
  </div>
  <!--   HOME   --->
  <section id="home"aria-label="Hero">
   <div class="container py-0 px-0 mt-1 content ">
    <h5 class="color-white1 font-size-20">From 250,000 FCFA</h5>
    <h1 class="color-white1 font-rubik font-size-60">iPhone 12 Pro</h1>
    <p class="color-white1 font-size-16">Universal Store offers the best products for the most affordable prices</p>
    <button><a href="<?php echo universalstore_URL;?>/shop" class="color-black" style="text-decoration:none;">Shop Now</a></button>
    </div>

    <div class="container banner1">
      <div class="inner">
        <h5 class="color-white1 font-size-20 font-rubik font-weight-800"><i class="fa-solid fa-truck-fast me-2"></i>Free shipping</h5>
        <p class="color-white font-size-16">on all Cameroon Orders</p>
      </div>
      <div class="inner">
        <h5 class="color-white1 font-size-20 font-rubik font-weight-800"><i class="fa-solid fa-money-check me-2"></i>100% Money Back</h5>
        <p class="color-white font-size-16">You have 10 days to return</p>
      </div>
      <div class="inner1">
        <h5 class="color-white1 font-size-20 font-rubik font-weight-800"><i class="fa-solid fa-clock-rotate-left me-2"></i>Open 24/7</h5>
        <p class="color-white font-size-16">Contact us 24 hours a day</p>
      </div>
      <div class="inner1">
        <h5 class="color-white1 font-size-20 font-rubik font-weight-800"><i class="fa-regular fa-credit-card me-2"></i>100% Payment Secure</h5>
        <p class="color-white font-size-16">Your Payments are safe with us</p>
      </div>
    </div>
  </section>

  <!--   Brands   --->
  <section id="brand" aria-label="Brands">
    <div class="container">
      <div class="row g-3 align-items-center">
        <div class="col-6 col-md-3">
          <img class="img-fluid w-100" src="assets/imgs/logo apple.webp" alt="Apple" />
        </div>
        <div class="col-6 col-md-3">
          <img class="img-fluid w-100" src="assets/imgs/logo1.webp" alt="Brand 2" />
        </div>
        <div class="col-6 col-md-3">
          <img class="img-fluid w-100" src="assets/imgs/pomme.webp" alt="Brand 3" />
        </div>
        <div class="col-6 col-md-3">
          <img class="img-fluid w-100" src="assets/imgs/logos.jpeg" alt="Brand 4" />
        </div>
      </div>
    </div>
  </section>

  <!-- Hero section -->
  <section class="hero-section">
    <div class="container">
      <div class="hero-content">
        <p class="text-uppercase fw-bold" style="letter-spacing:0.12em; color:#d3daf7">Limited Time Offer</p>
        <h1>Discover Epic Deals on Phones, PCs & Tablets</h1>
        <p class="lead">Hand-picked premium products curated for you with fast delivery, 24/7 support and exclusive savings.</p>
        <a href="<?php echo universalstore_URL; ?>/shop" class="btn btn-primary mb-2">Shop Best Sellers</a>
        <button type="button" class="btn btn-outline-light" id="watchVideoBtn" >Watch Brand Video</button>
      </div>
    </div>
  </section>

  <section class="container info-cards">
    <div class="info-card d-flex align-items-start">
      <i class="fas fa-truck"></i>
      <div>
        <h5>Free Delivery</h5>
        <p>Fast shipping across the entire country with real-time tracking.</p>
      </div>
    </div>
    <div class="info-card d-flex align-items-start">
      <i class="fas fa-shield-alt"></i>
      <div>
        <h5>Secure Checkout</h5>
        <p>Encrypted payments and buyer protection for every order.</p>
      </div>
    </div>
    <div class="info-card d-flex align-items-start">
      <i class="fas fa-headset"></i>
      <div>
        <h5>24/7 Support</h5>
        <p>Customer care at your service anytime via call, chat, or email.</p>
      </div>
    </div>
    <div class="info-card d-flex align-items-start">
      <i class="fas fa-sync-alt"></i>
      <div>
        <h5>30-Day Returns</h5>
        <p>Hassle-free returns and refunds on all compatible items.</p>
      </div>
    </div>
  </section>

  <section class="section-banner">
    <img src="https://ik.imagekit.io/univ35tore/universal_store/imgs/brand%20products.png" alt="Special Sale" />
    <div class="overlay">
      <div>
        <h2 style="font-size:2.1rem; color:#fff; font-weight:800; margin-bottom:1rem;">Spring Launch Event - Up to 50% Off</h2>
        <p style="color:#f0f5ff; font-size:1.07rem; margin-bottom:1rem;">Explore latest smartphones, laptops and gaming rigs with unbeatable discount codes.</p>
        <a class="btn btn-light btn-lg" href="<?php echo universalstore_URL; ?>/categories">Shop Now</a>
      </div>
    </div>
  </section>

  <section id="featured" class="my-5" aria-label="Featured products">
    <div class="container text-center mb-4">
      <h2 class="section-heading">Featured Products</h2>
      <hr class="mx-auto">
      <p class="section-subtitle">Top-rated items picked by our store experts</p>
    </div>
    <div class="row gx-3 mx-auto container">
      <?php include_once INTERNAL_LOGIC_PATH . "/get_featured_products.php"; ?>
      <?php while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        $variants = $row['product_variant'];
        $variantsArray = explode(',', $variants);
        $defaultVariant = count($variantsArray) > 0 ? trim($variantsArray[0]) : '';
        $product_id = htmlspecialchars($row['product_id'], ENT_QUOTES, 'UTF-8');
        $product_name = htmlspecialchars(createSlug($row['product_name']), ENT_QUOTES, 'UTF-8');
        $product_color = htmlspecialchars(createSlug($row['product_color']), ENT_QUOTES, 'UTF-8');
        $product_category = createSlug($row['product_category']);
        $img_val = htmlspecialchars($row['product_image'], ENT_QUOTES, 'UTF-8');
        $img_src = (stripos($img_val, 'http') === 0 || stripos($img_val, '//') === 0) ? $img_val : universalstore_URL . '/assets/imgs/' . $img_val;
        $rating = isset($row['product_rating']) ? floatval($row['product_rating']) : 3.0;
        $reviewsCount = isset($row['product_review_count']) ? intval($row['product_review_count']) : 300;
      ?>
      <div class="col-lg-3 col-md-4 col-sm-65">
        <div class="products">          
          <?php echo generateWishlistButton($product_id, isProductInWishlist($authenticatedUserId, $row['product_id']), 'absolute top-2 right-2 z-10 bg-white rounded-full p-2 shadow-md'); ?>
         <div class="product-stand">
          <a href="<?php echo universalstore_URL; ?>/<?php echo $product_category;?>/<?php echo $product_id; ?>/<?php echo $product_name; ?>/<?php echo $product_color; ?>">
            <img class="product-image" src="<?php echo $img_src; ?>" alt="<?php echo htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8'); ?>" />
          </a>
         </div>
          <div class="star"><?php echo renderRatingStars($rating); ?></div>
          <div class="rating-text"><?php echo number_format($rating,1); ?>/5 • <?php echo number_format($reviewsCount); ?> reviews</div>
          <h4 class="p-name"><?php echo htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8'); ?></h4>
           <div class="product-variant" style="font-size: 12px; color: #666; margin-bottom: 8px;">
              <span style="background: #f0f0f0; padding: 2px 6px; border-radius: 3px; display: inline-block;">
                <?php echo htmlspecialchars($defaultVariant, ENT_QUOTES, 'UTF-8'); ?>
              </span>
            </div>
          <h5 class="P-price"><?php echo htmlspecialchars($row['product_price'], ENT_QUOTES, 'UTF-8'); ?> FCFA</h5>
          <button class="buy-btn add-to-cart-btn" data-product-csrf-token="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>" data-product-id="<?php echo $product_id; ?>" data-product-name="<?php echo htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8'); ?>" data-product-price="<?php echo htmlspecialchars($row['product_price'], ENT_QUOTES, 'UTF-8'); ?>" data-product-color="<?php echo htmlspecialchars($row['product_color'], ENT_QUOTES, 'UTF-8'); ?>" data-product-variant="<?php echo htmlspecialchars($defaultVariant, ENT_QUOTES, 'UTF-8'); ?>" data-product-image="<?php echo htmlspecialchars($row['product_image'], ENT_QUOTES, 'UTF-8'); ?>"
           data-product-category="<?php echo htmlspecialchars($row['product_category'], ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-shopping-cart"></i> Add to Cart</button>
        </div>
      </div>
      <?php } ?>
    </div>
  </section>

  <section class="category-scroll-section container">
    <div class="cat-row">
      <div class="cat-title"><h3>Top Laptops</h3></div>
      <div class="scroll-row">
        <?php foreach (fetchCategoryProducts('Hp', 8) as $row) {
          $productId = htmlspecialchars($row['product_id'], ENT_QUOTES, 'UTF-8');
          $productName = htmlspecialchars(createSlug($row['product_name']), ENT_QUOTES, 'UTF-8');
          $productColor = htmlspecialchars(createSlug($row['product_color']), ENT_QUOTES, 'UTF-8');
          $productCategory = createSlug($row['product_category']);
          $imgVal = htmlspecialchars($row['product_image'], ENT_QUOTES, 'UTF-8');
          $imgSrc = (stripos($imgVal, 'http') === 0 || stripos($imgVal, '//') === 0) ? $imgVal : universalstore_URL . '/assets/imgs/' . $imgVal;
          $rating = isset($row['product_rating']) ? floatval($row['product_rating']) : 3.0;
          $reviewsCount = isset($row['product_review_count']) ? intval($row['product_review_count']) : 300;
        ?>
        <div class="scroll-card">
          <?php //echo generateWishlistButton($productId, isProductInWishlist($authenticatedUserId, $row['product_id']), 'absolute top-2 right-2 z-10 bg-white rounded-full p-1 shadow-md text-sm'); ?>
          <a href="<?php echo universalstore_URL; ?>/<?php echo $productCategory; ?>/<?php echo $productId; ?>/<?php echo $productName; ?>/<?php echo $productColor; ?>">
            <img class="product-image" src="<?php echo $imgSrc; ?>" alt="<?php echo htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8'); ?>" />
          </a>
          <div class="star"><?php echo renderRatingStars($rating); ?></div>
          <div class="rating-text"><?php echo number_format($rating,1); ?>/5 • <?php echo number_format($reviewsCount); ?> reviews</div>
          <h5 class="p-name"><?php echo htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8'); ?></h5>
          <div class="P-price"><?php echo htmlspecialchars($row['product_price'], ENT_QUOTES, 'UTF-8'); ?> FCFA</div>
        </div>
        <?php } ?>
      </div>
    </div>
    <div class="cat-row">
      <div class="cat-title"><h3>Best Consoles</h3></div>
      <div class="scroll-row">
        <?php foreach (fetchCategoryProducts('ps', 8) as $row) { /* ... looping as above ... */ ?>
        <div class="scroll-card">
          <?php // echo generateWishlistButton($productId, isProductInWishlist($authenticatedUserId, $row['product_id']), 'absolute top-2 right-2 z-10 bg-white rounded-full p-1 shadow-md text-sm'); ?>
        <a href="<?php echo universalstore_URL; ?>/<?php echo createSlug($row['product_category']); ?>/<?php echo htmlspecialchars($row['product_id'], ENT_QUOTES, 'UTF-8'); ?>/<?php echo htmlspecialchars(createSlug($row['product_name']), ENT_QUOTES, 'UTF-8'); ?>/<?php echo htmlspecialchars(createSlug($row['product_color']), ENT_QUOTES, 'UTF-8'); ?>">
            <img class="product-image" src="<?php echo (stripos(htmlspecialchars($row['product_image'], ENT_QUOTES, 'UTF-8'),'http')===0||stripos(htmlspecialchars($row['product_image'], ENT_QUOTES, 'UTF-8'),'//')===0)?htmlspecialchars($row['product_image'], ENT_QUOTES, 'UTF-8'):universalstore_URL . '/assets/imgs/' . htmlspecialchars($row['product_image'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8'); ?>" />
          </a>
          <div class="star"><?php echo renderRatingStars(isset($row['product_rating']) ? floatval($row['product_rating']) : 3.0); ?></div>
          <div class="rating-text"><?php echo number_format(isset($row['product_rating'])?floatval($row['product_rating']):3.0,1); ?> • <?php echo number_format(isset($row['product_review_count']) ? intval($row['product_review_count']) : 300); ?> reviews</div>
          <h5 class="p-name"><?php echo htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8'); ?></h5>
          <div class="P-price"><?php echo htmlspecialchars($row['product_price'], ENT_QUOTES, 'UTF-8'); ?> FCFA</div>
        </div>
        <?php } ?>
      </div>
    </div>
    <div class="cat-row">
      <div class="cat-title"><h3>Latest Phones</h3></div>
      <div class="scroll-row">
        <?php foreach (fetchCategoryProducts('iPhone', 8) as $row) { /* ... same loop ... */ ?>
        <div class="scroll-card">
          <?php // echo generateWishlistButton($productId, isProductInWishlist($authenticatedUserId, $row['product_id']), 'absolute top-2 right-2 z-10 bg-white rounded-full p-1 shadow-md text-sm'); ?>
        <a href="<?php echo universalstore_URL; ?>/<?php echo createSlug($row['product_category']); ?>/<?php echo htmlspecialchars($row['product_id'], ENT_QUOTES, 'UTF-8'); ?>/<?php echo htmlspecialchars(createSlug($row['product_name']), ENT_QUOTES, 'UTF-8'); ?>/<?php echo htmlspecialchars(createSlug($row['product_color']), ENT_QUOTES, 'UTF-8'); ?>">
            <img class="product-image" src="<?php echo (stripos(htmlspecialchars($row['product_image'], ENT_QUOTES, 'UTF-8'),'http')===0||stripos(htmlspecialchars($row['product_image'], ENT_QUOTES, 'UTF-8'),'//')===0)?htmlspecialchars($row['product_image'], ENT_QUOTES, 'UTF-8'):universalstore_URL . '/assets/imgs/' . htmlspecialchars($row['product_image'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8'); ?>" />
          </a>
          <div class="star"><?php echo renderRatingStars(isset($row['product_rating']) ? floatval($row['product_rating']) : 3.0); ?></div>
          <div class="rating-text"><?php echo number_format(isset($row['product_rating'])?floatval($row['product_rating']):3.0,1); ?>/5 • <?php echo number_format(isset($row['product_review_count']) ? intval($row['product_review_count']) : 300); ?> reviews</div>
          <h5 class="p-name"><?php echo htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8'); ?></h5>
          <div class="P-price"><?php echo htmlspecialchars($row['product_price'], ENT_QUOTES, 'UTF-8'); ?> FCFA</div>
        </div>
        <?php } ?>
      </div>
    </div>
  </section>

  <section class="video-section container">
    <h2 class="section-heading">Our Story in 90 Seconds</h2>
    <p class="section-subtitle">See what makes Universal Store the top tech marketplace.</p>
    <div class="video-area">
      <video id="brandVideo" loop muted playsinline controls preload="metadata" poster="https://ik.imagekit.io/univ35tore/universal_store/imgs/brand%20products2.jpg">
        <source src="<?php echo universalstore_URL;?>/assets/video/brands_video.mp4" type="video/mp4" />
        Your browser does not support the video tag.
      </video>
      <!-- <div class="play-overlay" id="videoPlayOverlay"><i class="fas fa-play"></i></div> -->
    </div>
  </section>

  <section class="blog-section container my-5">
    <h2 class="section-heading">From the Blog</h2>
    <p class="section-subtitle">Stay ahead with expert reviews, trends and how-tos.</p>
    <div class="row g-4">
      <div class="col-md-4">
        <article class="blog-card">
          <img src="https://ik.imagekit.io/univ35tore/universal_store/laptops/HP%2015.6%20FHD%20Touch%20Laptop,%20Intel%20i5.jpg" alt="Tech Buying Guide" />
          <div class="p-3">
            <h5>2026 Tech Buying Guide</h5>
            <p>How to select laptops for work, school, and gaming.</p>
            <a href="#" class="text-primary">Read more <i class="fas fa-chevron-right"></i></a>
          </div>
        </article>
      </div>
      <div class="col-md-4">
        <article class="blog-card">
          <img src="https://ik.imagekit.io/univ35tore/universal_store/Consoles/Nintendo%20Switch.jpg?updatedAt=1773964450158" alt="Gaming Setup" />
          <div class="p-3">
            <h5>Build the Ultimate Gaming Setup</h5>
            <p>Top consoles, ACs and accessories for serious players.</p>
            <a href="#" class="text-primary">Read more <i class="fas fa-chevron-right"></i></a>
          </div>
        </article>
      </div>
      <div class="col-md-4">
        <article class="blog-card">
          <img src="https://ik.imagekit.io/univ35tore/universal_store/laptops/Laptop%20computer,%20Gaming.jpg" alt="Performance Tips" />
          <div class="p-3">
            <h5>Boost Device Performance</h5>
            <p>Quick optimization tips for faster processing and battery life.</p>
            <a href="#" class="text-primary">Read more <i class="fas fa-chevron-right"></i></a>
          </div>
        </article>
      </div>
    </div>
  </section>

  <section class="faq-section container my-5">
    <h2 class="section-heading">Frequently Asked Questions</h2>
    <div class="accordion" id="faqAccordion">
      <div class="accordion-item">
        <h2 class="accordion-header" id="faqOne">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapseOne">What payment methods are accepted?</button>
        </h2>
        <div id="faqCollapseOne" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
          <div class="accordion-body">We accept Visa, Orange Money, Mtn Money and other payment options.</div>
        </div>
      </div>
      <div class="accordion-item">
        <h2 class="accordion-header" id="faqTwo">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapseTwo">How fast is shipping?</button>
        </h2>
        <div id="faqCollapseTwo" class="accordion-collapse collapse" data-bs-parent="#faqAccordion"><div class="accordion-body">Standard shipment 3-7 business days. Express delivery available in 1-2 days for major cities.</div></div>
      </div>
      <div class="accordion-item">
        <h2 class="accordion-header" id="faqThree">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapseThree">Do products come with warranty?</button>
        </h2>
        <div id="faqCollapseThree" class="accordion-collapse collapse" data-bs-parent="#faqAccordion"><div class="accordion-body">Yes! All products come with at least a 12-month warranty from the manufacturer.</div></div>
      </div>
    </div>
  </section>

  <div id="announcementPopup" class="announcement-popup">
    <div class="popup-card position-relative">
      <button type="button" class="btn-close btn-close-white btn-sm btn-close-opacity-75" aria-label="Close" onclick="hideAnnouncement()"></button>
      <h4>Flash Offer!</h4>
      <p>Get 10% off on orders over 150,000 FCFA. Use code <strong>SPRING10</strong> at checkout.</p>
      <button class="btn btn-primary" onclick="hideAnnouncement()">Shop Now</button>
    </div>
  </div>

  <!--   Banner    -->
  <section id="banner" class="my-5 py-5" aria-label="Promo banner">
    <div class="container">
      <h4>MID SEASON'S SALE</h4>
      <h1>Summer Collection <br> UP to 30% OFF</h1>
      <button>Shop now</button>
    </div>
  </section>

  <!--   Phones   -->
  <section id="featured" class="my-5" aria-label="Phones">
    <div class="container text-center mt-5 py-5">
      <h3>Phones</h3>
      <hr>
      <p>Here you can check amazing Phones</p>
    </div>
    <div class="row mx-auto container-fluid">

      <?php include_once INTERNAL_LOGIC_PATH . "/get_phone.php"; ?>
      <?php  while($row = $stmt->fetch(\PDO::FETCH_ASSOC)){ 
        // fetch variant from db in other to send it as default value when a user clicks on add to cart button without selecting a variant, this is to avoid errors and make sure the user can add the product to cart without necessarily selecting a variant if there is only one variant available for that product
        $variants = $row['product_variant'];
        $variantsArray = explode(',', $variants);
        $defaultVariant = count($variantsArray) > 0 ? trim($variantsArray[0]) : '';
        $product_id = htmlspecialchars($row['product_id'], ENT_QUOTES, 'UTF-8');
        $product_name = htmlspecialchars(createSlug($row['product_name']), ENT_QUOTES, 'UTF-8');
        $product_color = htmlspecialchars(createSlug($row['product_color']), ENT_QUOTES, 'UTF-8');
        $product_category = createSlug($row['product_category']);
        ?>
      <div class="products text-center col-lg-3 col-md-4 col-sm-6 container">
          <a href="<?php echo universalstore_URL; ?>/<?php echo $product_category;?>/<?php echo $product_id ;?>/<?php echo $product_name; ?>/<?php echo $product_color; ?>">
          <?php
            $img_val = htmlspecialchars($row['product_image'], ENT_QUOTES, 'UTF-8');
            $img_src = (stripos($img_val, 'http') === 0 || stripos($img_val, '//') === 0)
                ? $img_val
                : universalstore_URL . '/assets/imgs/' . $img_val;
          ?>
          <img class="img-fluid mb-3 product-image" src="<?php echo $img_src; ?>" alt="<?php echo htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8'); ?>" />
        </a>
        <?php
        $rating = isset($row['product_rating']) ? floatval($row['product_rating']) : 3.0;
        $reviewsCount = isset($row['product_review_count']) ? intval($row['product_review_count']) : 300;
        ?>
        <div class="star"><?php echo renderRatingStars($rating); ?></div>
        <div class="rating-text"><?php echo number_format($rating, 1); ?>/5 • <?php echo number_format($reviewsCount); ?> reviews</div>
        <h4 class="p-name"><?php echo htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8');?></h4>
        <h5 class="P-price"><?php echo htmlspecialchars($row['product_price'], ENT_QUOTES, 'UTF-8'); ?> FCFA</h5>
        <button class="buy-btn add-to-cart-btn"
          data-product-csrf-token = "<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8');?>"
          data-product-id="<?php echo htmlspecialchars($row['product_id'], ENT_QUOTES, 'UTF-8'); ?>"
          data-product-name="<?php echo htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8'); ?>"
          data-product-price="<?php echo htmlspecialchars($row['product_price'], ENT_QUOTES, 'UTF-8'); ?>"
          data-product-color="<?php echo htmlspecialchars($row['product_color'], ENT_QUOTES, 'UTF-8'); ?>"
          data-product-variant="<?php echo htmlspecialchars($defaultVariant, ENT_QUOTES, 'UTF-8') ?>"
          data-product-image="<?php echo htmlspecialchars($row['product_image'], ENT_QUOTES, 'UTF-8'); ?>"
           data-product-category="<?php echo htmlspecialchars($row['product_category'], ENT_QUOTES, 'UTF-8'); ?>">Add to Cart</button>
      </div>
<?php } ?>
    </div>
  </section>

  <!--   Category quick scroll section   -->
  <section class="category-scroll-section my-5" aria-label="Category Scroller">
    <div class="container">
      <h3 class="text-center mb-4">Hot Picks by Category</h3>

      <div class="cat-row">
        <div class="cat-title"><span>PCs & Laptops</span></div>
        <div class="scroll-row">
          <?php foreach (fetchCategoryProducts('laptops-and-pcs', 8) as $row) {
              $productId = htmlspecialchars($row['product_id'], ENT_QUOTES, 'UTF-8');
              $productName = htmlspecialchars(createSlug($row['product_name']), ENT_QUOTES, 'UTF-8');
              $productColor = htmlspecialchars(createSlug($row['product_color']), ENT_QUOTES, 'UTF-8');
              $productCategory = createSlug($row['product_category']);
              $imgVal = htmlspecialchars($row['product_image'], ENT_QUOTES, 'UTF-8');
              $imgSrc = (stripos($imgVal, 'http') === 0 || stripos($imgVal, '//') === 0) ? $imgVal : universalstore_URL . '/assets/imgs/' . $imgVal;
              $rating = isset($row['product_rating']) ? floatval($row['product_rating']) : 3.0;
              $reviewsCount = isset($row['product_review_count']) ? intval($row['product_review_count']) : 300;
          ?>
          <div class="scroll-card">
      <?php  //echo generateWishlistButton($productId, isProductInWishlist($authenticatedUserId, $row['product_id']), 'absolute top-2 right-2 z-10 bg-white rounded-full p-1 shadow-md text-sm'); ?>
            <a href="<?php echo universalstore_URL; ?>/<?php echo $productCategory; ?>/<?php echo $productId; ?>/<?php echo $productName; ?>/<?php echo $productColor; ?>">
              <img class="img-fluid mb-2 product-image" src="<?php echo $imgSrc; ?>" alt="<?php echo htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8'); ?>" />
            </a>
            <div class="star"><?php echo renderRatingStars($rating); ?></div>
            <div class="rating-text"><?php echo number_format($rating, 1); ?> • <?php echo number_format($reviewsCount); ?> reviews</div>
            <h4 class="p-name"><?php echo htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8'); ?></h4>
            <div class="product-variant" style="font-size: 12px; color: #666; margin-bottom: 8px;">
              <span style="background: #f0f0f0; padding: 2px 6px; border-radius: 3px; display: inline-block;">
                <?php echo htmlspecialchars($defaultVariant, ENT_QUOTES, 'UTF-8'); ?>
              </span>
            </div>
            <h5 class="P-price"><?php echo htmlspecialchars($row['product_price'], ENT_QUOTES, 'UTF-8'); ?> FCFA</h5>
          </div>
          <?php } ?>
        </div>
      </div>

      <div class="cat-row">
        <div class="cat-title"><span>Consoles</span></div>
        <div class="scroll-row">
          <?php foreach (fetchCategoryProducts('xbox', 8) as $row) {
              $productId = htmlspecialchars($row['product_id'], ENT_QUOTES, 'UTF-8');
              $productName = htmlspecialchars(createSlug($row['product_name']), ENT_QUOTES, 'UTF-8');
              $productColor = htmlspecialchars(createSlug($row['product_color']), ENT_QUOTES, 'UTF-8');
              $productCategory = createSlug($row['product_category']);
              $imgVal = htmlspecialchars($row['product_image'], ENT_QUOTES, 'UTF-8');
              $imgSrc = (stripos($imgVal, 'http') === 0 || stripos($imgVal, '//') === 0) ? $imgVal : universalstore_URL . '/assets/imgs/' . $imgVal;
              $rating = isset($row['product_rating']) ? floatval($row['product_rating']) : 3.0;
              $reviewsCount = isset($row['product_review_count']) ? intval($row['product_review_count']) : 300;
          ?>
          <div class="scroll-card">
        <?php //echo generateWishlistButton($productId, isProductInWishlist($authenticatedUserId, $row['product_id']), 'absolute top-2 right-2 z-10 bg-white rounded-full p-1 shadow-md text-sm'); ?>
            <a href="<?php echo universalstore_URL; ?>/<?php echo $productCategory; ?>/<?php echo $productId; ?>/<?php echo $productName; ?>/<?php echo $productColor; ?>">
              <img class="img-fluid mb-2 product-image" src="<?php echo $imgSrc; ?>" alt="<?php echo htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8'); ?>" />
            </a>
            <div class="star"><?php echo renderRatingStars($rating); ?></div>
            <div class="rating-text"><?php echo number_format($rating, 1); ?>/5 • <?php echo number_format($reviewsCount); ?> reviews</div>
            <h4 class="p-name"><?php echo htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8'); ?></h4>
            <h5 class="P-price"><?php echo htmlspecialchars($row['product_price'], ENT_QUOTES, 'UTF-8'); ?> FCFA</h5>
          </div>
          <?php } ?>
        </div>
      </div>

      <div class="cat-row">
        <div class="cat-title"><span>Tablets</span></div>
        <div class="scroll-row">
          <?php foreach (fetchCategoryProducts('ipad', 8) as $row) {
              $productId = htmlspecialchars($row['product_id'], ENT_QUOTES, 'UTF-8');
              $productName = htmlspecialchars(createSlug($row['product_name']), ENT_QUOTES, 'UTF-8');
              $productColor = htmlspecialchars(createSlug($row['product_color']), ENT_QUOTES, 'UTF-8');
              $productCategory = createSlug($row['product_category']);
              $imgVal = htmlspecialchars($row['product_image'], ENT_QUOTES, 'UTF-8');
              $imgSrc = (stripos($imgVal, 'http') === 0 || stripos($imgVal, '//') === 0) ? $imgVal : universalstore_URL . '/assets/imgs/' . $imgVal;
              $rating = isset($row['product_rating']) ? floatval($row['product_rating']) : 3.0;
              $reviewsCount = isset($row['product_review_count']) ? intval($row['product_review_count']) : 300;
          ?>
          <div class="scroll-card">
          <?php //echo generateWishlistButton($productId, isProductInWishlist($authenticatedUserId, $row['product_id']), 'absolute top-2 right-2 z-10 bg-white rounded-full p-1 shadow-md text-sm'); ?>
            <a href="<?php echo universalstore_URL; ?>/<?php echo $productCategory; ?>/<?php echo $productId; ?>/<?php echo $productName; ?>/<?php echo $productColor; ?>">
              <img class="img-fluid mb-2 product-image" src="<?php echo $imgSrc; ?>" alt="<?php echo htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8'); ?>" />
            </a>
            <div class="star"><?php echo renderRatingStars($rating); ?></div>
            <div class="rating-text"><?php echo number_format($rating, 1); ?> • <?php echo number_format($reviewsCount); ?> reviews</div>
            <h4 class="p-name"><?php echo htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8'); ?></h4>
            <h5 class="P-price"><?php echo htmlspecialchars($row['product_price'], ENT_QUOTES, 'UTF-8'); ?> FCFA</h5>
          </div>
          <?php } ?>
        </div>
      </div>

    </div>
  </section>

  <!--   Other Phones    -->
  <section id="featured" class="my-5" aria-label="Our Best Phones">
    <div class="container text-center ">
      <h3>Our Best Products</h3>
      <hr>
      <p>You may also like these ones</p>
    </div>
    <div class="row gx-3 mx-auto container">
    <?php
    include_once INTERNAL_LOGIC_PATH . "/get_best_products.php";
    
    foreach($best_products as $row) {
        // fetch variant from db in other to send it as default value when a user clicks on add to cart button without selecting a variant, this is to avoid errors and make sure the user can add the product to cart without necessarily selecting a variant if there is only one variant available for that product
        $variants = $row['product_variant'];
        $variantsArray = explode(',', $variants);
        $defaultVariant = count($variantsArray) > 0 ? trim($variantsArray[0]) : '';
        $product_id = htmlspecialchars($row['product_id'], ENT_QUOTES, 'UTF-8');
        $product_name = htmlspecialchars(createSlug($row['product_name']), ENT_QUOTES, 'UTF-8');
        $product_color = htmlspecialchars(createSlug($row['product_color']), ENT_QUOTES, 'UTF-8');
        $product_category = createSlug($row['product_category']);
        $rating = isset($row['product_rating']) ? floatval($row['product_rating']) : 3.0;
        $reviewsCount = isset($row['product_review_count']) ? intval($row['product_review_count']) : 300;
     
     ?>
     
      <div class="text-center col-lg-3 col-md-4 col-sm-6 ">
        <div class="products-best">
          <a href="<?php echo universalstore_URL; ?>/<?php echo $product_category;?>/<?php echo $product_id ;?>/<?php echo $product_name; ?>/<?php echo $product_color; ?>">
          <?php
            $img_val = htmlspecialchars($row['product_image'], ENT_QUOTES, 'UTF-8');
            $img_src = (stripos($img_val, 'http') === 0 || stripos($img_val, '//') === 0)
                ? $img_val
                : universalstore_URL . '/assets/imgs/' . $img_val;
          ?>
          <img class="img-fluid product-image-best mb-3" src="<?php echo $img_src; ?>" alt="<?php echo htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8'); ?>" />
        </a>
         <div class="star"><?php echo renderRatingStars($rating); ?></div>
          <div class="rating-text text-muted text-xs"><?php echo number_format($rating, 1); ?>/5 • <?php echo number_format($reviewsCount); ?> reviews</div>
        <h4 class="p-name"><?php echo htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8');?></h4>
        <div class="product-variant" style="font-size: 12px; color: #666; margin-bottom: 8px;">
              <span style="background: #f0f0f0; padding: 2px 6px; border-radius: 3px; display: inline-block;">
                <?php echo htmlspecialchars($defaultVariant, ENT_QUOTES, 'UTF-8'); ?>
              </span>
            </div>
        <h5 class="P-price"><?php echo htmlspecialchars($row['product_price'], ENT_QUOTES, 'UTF-8'); ?> FCFA</h5>
        <button class="buy-btn add-to-cart-btn"
          data-product-csrf-token = "<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8');?>"
          data-product-id="<?php echo htmlspecialchars($row['product_id'], ENT_QUOTES, 'UTF-8'); ?>"
          data-product-name="<?php echo htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8'); ?>"
          data-product-price="<?php echo htmlspecialchars($row['product_price'], ENT_QUOTES, 'UTF-8'); ?>"
          data-product-color="<?php echo htmlspecialchars($row['product_color'], ENT_QUOTES, 'UTF-8'); ?>"
          data-product-variant="<?php echo htmlspecialchars($defaultVariant, ENT_QUOTES, 'UTF-8') ?>"
          data-product-image="<?php echo htmlspecialchars($row['product_image'], ENT_QUOTES, 'UTF-8'); ?>"
           data-product-category="<?php echo htmlspecialchars($row['product_category'], ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-shopping-cart"></i> Add to Cart</button>
      </div>
      </div>
    <?php } ?>
    </div>
  </section>

  <!--   New Arrivals   -->
  <section id="featured" aria-label="New Arrivals">
    <div class="container text-center py-2">
      <h3>New Arrivals</h3>
      <hr>
      <p>Here you can check all new Arrivals</p>
    </div>
    <div class="row mx-auto container-fluid">
    <?php include_once INTERNAL_LOGIC_PATH . "/get_new_arrivals.php";
    
    foreach($new_arrivals as $row) {
          $variants = $row['product_variant'];
          $variantsArray = explode(',', $variants);
          $defaultVariant = count($variantsArray) > 0 ? trim($variantsArray[0]) : '';
          $product_id = htmlspecialchars($row['product_id'], ENT_QUOTES, 'UTF-8');
          $product_name = htmlspecialchars(createSlug($row['product_name']), ENT_QUOTES, 'UTF-8');
          $product_color = htmlspecialchars(createSlug($row['product_color']), ENT_QUOTES, 'UTF-8');
          $product_category = createSlug($row['product_category']); 
          $img_val = htmlspecialchars($row['product_image'], ENT_QUOTES, 'UTF-8');
          $img_src = (stripos($img_val, 'http') === 0 || stripos($img_val, '//') === 0) ? $img_val : universalstore_URL . '/assets/imgs/' . $img_val;
          $rating = isset($row['product_rating']) ? floatval($row['product_rating']) : 3.0;
          $reviewsCount = isset($row['product_review_count']) ? intval($row['product_review_count']) : 300;
        ?>  
    <div class="text-center col-lg-3 col-md-4 col-sm-6 ">
        <div class="products-best product-arrival bg-card-bg shadow-lg border-1 border-gray-200 rounded-full">
          <a href="<?php echo universalstore_URL; ?>/<?php echo $product_category;?>/<?php echo $product_id ;?>/<?php echo $product_name; ?>/<?php echo $product_color; ?>">
          <?php
            $img_val = htmlspecialchars($row['product_image'], ENT_QUOTES, 'UTF-8');
            $img_src = (stripos($img_val, 'http') === 0 || stripos($img_val, '//') === 0)
                ? $img_val
                : universalstore_URL . '/assets/imgs/' . $img_val;
          ?>
          <img class="img-fluid product-image-arrivals mb-3" src="<?php echo $img_src; ?>" alt="<?php echo htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8'); ?>" />
        </a>
         <div class="star"><?php echo renderRatingStars($rating); ?></div>
          <div class="rating-text text-muted text-xs"><?php echo number_format($rating, 1); ?>/5 • <?php echo number_format($reviewsCount); ?> reviews</div>
        <h4 class="p-name"><?php echo htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8');?></h4>
        <div class="product-variant" style="font-size: 12px; color: #666; margin-bottom: 8px;">
              <span style="background: #f0f0f0; padding: 2px 6px; border-radius: 3px; display: inline-block;">
                <?php echo htmlspecialchars($defaultVariant, ENT_QUOTES, 'UTF-8'); ?>
              </span>
            </div>
        <h5 class="P-price"><?php echo htmlspecialchars($row['product_price'], ENT_QUOTES, 'UTF-8'); ?> FCFA</h5>
        <button class="buy-btn add-to-cart-btn"
          data-product-csrf-token = "<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8');?>"
          data-product-id="<?php echo htmlspecialchars($row['product_id'], ENT_QUOTES, 'UTF-8'); ?>"
          data-product-name="<?php echo htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8'); ?>"
          data-product-price="<?php echo htmlspecialchars($row['product_price'], ENT_QUOTES, 'UTF-8'); ?>"
          data-product-color="<?php echo htmlspecialchars($row['product_color'], ENT_QUOTES, 'UTF-8'); ?>"
          data-product-variant="<?php echo htmlspecialchars($defaultVariant, ENT_QUOTES, 'UTF-8') ?>"
          data-product-image="<?php echo htmlspecialchars($row['product_image'], ENT_QUOTES, 'UTF-8'); ?>"
          data-product-category="<?php echo htmlspecialchars($row['product_category'], ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-shopping-cart"></i> Add to Cart</button>
      </div>
      </div>
    <?php } ?>
    </div>
  </section>

   <!--  Offer section   -->

   <section id="featured" class="my-5 pb-5">

   <div class="container ">
   <div class="banner2">
   <div>
    <h1 class="color-white1 font-size-60">Apple 16 Pro Max</h1>
      <p class="color-white1 font-rubik">The iPhone 16 features a sleek design, advanced camera system</p>
     <p  class="color-white1 font-rubik">Having a powerful A18 chip, making it a significant upgrade in Apple's smartphone lineup.</p>
      <a href="<?php echo universalstore_URL?>/iphone/18/iphone-16-pro-max/gold-titanium"><button class="offer-btn">View Offer</button></a>
      </div>
      <img src="https://ik.imagekit.io/univ35tore/universal_store/imgs/iphone16_PNG13.png?updatedAt=1770682532714" />
   </div>
   </div>
   </section>

<!--  Best sales   --> 

<section id="featured" class="my-5 pb-5">
 <div class="d-flex mt-5 py-5 ">
 <div>
 <h2>Our Best Sales</h2>
 <hr>
  <p class="font-rubik font-size-20">The Most sell Products on our Store</p>
  </div>
  <div>
  <button class="">View All</button>
  </div>
</div>

<div class="row mx-auto container-fluid ">
<div class="col-md-4 col-sm-6 col-6 mb-3">
  <div class="products">
<div class="best-sales">
  <img class="img-fluid mb-3 " src="assets/imgs/iphone16_PNG6.png" alt="iPhone 16" />
  </div> <div class="content ">
        <div class="star">
          <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
        </div>
    <h4 class="P-name">iPhone 16</h4>
    <h5 class="P-price">500,000 FCFA</h5>
    <button class="add-cart-btn">Add to cart</button>
    </div>
</div>
</div>
<div class="col-md-4 col-sm-6 col-6 mb-3">
<div class="products">
<div class="best-sales">
  <img class="img-fluid mb-3 " src="assets/imgs/iphone16_PNG8.png" alt="iPhone 16" />
  </div> <div class="content ">
        <div class="star">
          <i class="fas fa-star" ></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
        </div>
    <h4 class="P-name">iPhone 16</h4>
    <h5 class="P-price">500,000 FCFA</h5>
    <button class="add-cart-btn">Add to cart</button>
    </div>
</div>
</div>
<div class="col-md-4 col-sm-6 col-6 mb-3">
  <div class="products">
<div class="best-sales">
  <img class="img-fluid mb-3 " src="assets/imgs/iphone16_PNG13.png" alt="iPhone 16" />
  </div> <div class="content ">
        <div class="star">
          <i class="fas fa-star" ></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
        </div>
    <h4 class="P-name">iPhone 16</h4>
    <h5 class="P-price">500,000 FCFA</h5>
    <button class="add-cart-btn">Add to cart</button>
    </div>
</div>
</div>

<div class="col-md-4 col-sm-6 col-6 mb-3">
  <div class="products">
<div class="best-sales">
  <img class="img-fluid mb-3 " src="assets/imgs/ipad013.jpeg" alt="iPad pro" />
  </div> <div class="content ">
        <div class="star">
          <i class="fas fa-star" ></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
        </div>
    <h4 class="P-name">iPad 12</h4>
    <h5 class="P-price">300,000 FCFA</h5>
    <button class="add-cart-btn">Add to cart</button>
    </div>
</div>
</div>
 
<div class="col-md-4 col-sm-6 col-6 mb-3">
  <div class="products">
<div class="best-sales">
  <img class="img-fluid mb-3 " src="assets/imgs/ipad12.jpeg" alt="iPad 11" />
  </div> <div class="content ">
        <div class="star">
          <i class="fas fa-star" ></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
        </div>
    <h4 class="P-name">iPad 11</h4>
    <h5 class="P-price">221,000 FCFA</h5>
    <button class="add-cart-btn">Add to cart</button>
    </div>
</div>
</div>

<div class="col-md-4 col-sm-6 col-6 mb-3">
  <div class="products">
<div class="best-sales">
  <img class="img-fluid mb-3 " src="assets/imgs/ipad09.jpeg" alt="iPad 10" />
  </div> <div class="content ">
        <div class="star">
          <i class="fas fa-star" ></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
        </div>
    <h4 class="P-name">iPad 10</h4>
    <h5 class="P-price">150,000 FCFA</h5>
    <button class="add-cart-btn">Add to cart</button>
    </div>
</div>
</div>  
</div>

</section>

<!--  Banner3   --> 
<section id="featured" class="my-5 pb-5 bann">
<div class="container py-0 px-0 mt-1  ">
<div class="banner3 ">
<div>
  <img src="assets/imgs/ipadpro1.jpg" /> 
</div>
<div class="content">
    <h5 class="color-white font-rubik">From 350,000 FCFA</h5>
    <h1 class="color-white1 font-size-60">iPad Pro</h1>
    <p class="color-white1 font-rubik">The iPad Pro Features 11.0″ display, Apple M1 chipset</p>
    <p  class="color-white1 font-rubik" >7538 mAh battery, 2048 GB storage, 16 GB RAM, Scratch-resistant glass</p><br>
    <button class="buy-btn1">Buy Now</button>
    </div>
</div>

</div>

</section>

  <!--  Footer   -->
  <footer class="mt-5 pt-5 font-rubik">
    <div class="container">
      <div class="row gx-4 gy-4 pt-3">
        <div class="footer-one col-lg-3 col-md-6 col-sm-12">
          <img src="https://ik.imagekit.io/univ35tore/universal_store/imgs/UNIVERSAL%20STORE.jpg" alt="Universal Store logo">
          <p class="pt-3">We provide the best products for the most affordable prices</p>
        </div>

        <div class="footer-one col-lg-3 col-md-6 col-sm-12">
          <h5 class="pb-2">Featured</h5>
          <ul class="text-uppercase link p-0 m-0">
            <li><a href="#">iPhone</a></li>
            <li><a href="#">iPad</a></li>
            <li><a href="#">Samsung</a></li>
            <li><a href="#">Redmi Xiaomi</a></li>
            <li><a href="#">Google Pixel</a></li>
          </ul>
        </div>

        <div class="footer-one col-lg-3 col-md-6 col-sm-12">
          <h5 class="pb-2">Contact Us</h5>
          <div>
            <h6 class="text-uppercase">Address</h6>
            <p>Kennedy Maison Orange, Yaoundé</p>
            <h6 class="text-uppercase">Phone</h6>
            <p><a href="tel:694-81-76-63">+237 694817663</a></p>
            <h6 class="text-uppercase">Email</h6>
            <p><a href="mailto:arnauldfonkoua@gmail.com">arnauldfonkoua@gmail.com</a></p>
          </div>
        </div>

        <div class="footer-one col-lg-3 col-md-6 col-sm-12">
          <h6 class="text-uppercase color-white1 font-weight">Social</h6>
          <ul class="social d-flex p-0 m-0 list-unstyled">
            <li><a href="#" aria-label="Facebook"><i class="fab fa-facebook-square"></i></a></li>
            <li><a href="https://www.instagram.com/arn_a_uld?igsh=NGQ2MGRrM211dWhu&utm_source=qr" aria-label="Instagram"><i class="fab fa-instagram"></i></a></li>
            <li><a href="https://chat.whatsapp.com/Ji9GrDac59dGB3Wwy7qKr3" aria-label="WhatsApp"><i class="fab fa-whatsapp" aria-hidden="true"></i></a></li>
          </ul>
        </div>
      </div>

      <div class="copyright mt-5">
        <div class="row align-items-center text-center">
          <div class="col-12 col-md-6 mb-3 mb-md-0 d-flex justify-content-center gap-3">
            <img src="https://ik.imagekit.io/univ35tore/universal_store/imgs/mtn.webp?updatedAt=1770682540718" alt="MTN" class="payment-logo" />
            <img src="https://ik.imagekit.io/univ35tore/universal_store/imgs/om.jpeg?updatedAt=1770682540781" alt="Orange Money" class="payment-logo" />
          </div>
          <div class="col-12 col-md-6">
            <p class="mb-0">Universal Store © 2025 — All Rights Reserved</p>
          </div>
        </div>
      </div>
    </div>
  </footer>
  <script>
    const BASE_URL = "<?php echo BASE_URL; ?>";
</script>
  <script src="<?php echo universalstore_URL; ?>/assets/js/nav.js"></script>
  <script src="<?php echo universalstore_URL; ?>/assets/js/error-logger.js"></script>
  <script src="<?php echo universalstore_URL; ?>/assets/includes/bootstrap-5.3.7-dist/js/bootstrap.bundle.min.js"></script>
<script>

// Initialize current CSRF token
let currentCsrfToken = '<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>';

// Function to update csrf_token on all buttons
function updateCsrfToken(newToken) {
    if (newToken) {
        currentCsrfToken = newToken;
        document.querySelectorAll('.add-to-cart-btn').forEach(button => {
            button.setAttribute('data-product-csrf-token', newToken);
        });
    }
}

// Function to check if CSRF token is not empty
function checkEmptyCsrfToken(token) {
    if (!token) {
        showCartNotice('Session Expired. Please refresh page.', true);
        return false;
    }
    return true;
}
 
// Function to check new CSRF Token
function checkNewCsrfToken(newToken, oldToken) {
    if (newToken && newToken === oldToken) {
        showCartNotice('Something happened while adding item to Cart. Please refresh page.', true);
        return false;
    }
    return true;
}

document.addEventListener('DOMContentLoaded', function() {
    // Function to show a notice
    function showCartNotice(product_image, message, product_color, product_variant, product_price, isError = false) {
        const container = document.getElementById('cart-notice-container');
        if (!container) return;

        const alert = document.createElement('div');
        alert.className = `alert ${isError ? 'alert-danger' : 'bg-light'} alert-dismissible fade show`;
        alert.role = 'alert';
        
        let alertHTML = `<h3 class="font-rubik font-weight-800">${isError ? 'Error product has not been added to cart' : ' ✔ Added To Cart'}</h3>`;
        
        if (!isError) {
            // Show product details for success
            alertHTML += `
            <div style="position:relative; display:flex; gap:15px; margin:10px 0;">
                <img src="${product_image}" style="width:70px; height:90px; object-fit:contain;">
                <div class="px-4" style="flex:1;">
                    <div class="font-rubik font-weight-800">${message}</div>
                    <div class="font-rubik color-black">${product_color} • ${product_variant}</div>
                    <p class="font-rubik font-weight-800 mt-1">${product_price}</p>
                </div>
            </div>
            <div style="margin-top:10px;">
                <a href="<?php echo htmlspecialchars(CART_URL, ENT_QUOTES, 'UTF-8'); ?>" class="btn py-1 px-5 btn-lg btn-dark">View Cart</a>
            </div>`;
        } else {
            // Show only error message for errors
            alertHTML += `<div class="font-rubik font-weight-600" style="margin:10px 0;">${message}</div>`;
        }
        
        alertHTML += `<button type="button" class="btn-close bg-light" data-bs-dismiss="alert" aria-label="Close"></button>`;
        alert.innerHTML = alertHTML;
        
        container.innerHTML = ''; // Clear previous messages
        container.appendChild(alert);

        // Auto-close after 5 seconds, or 7 seconds for errors to give user more time to read
        const timeout = isError ? 7000 : 5000;
        setTimeout(() => {
            if (container.contains(alert)) {
                // safely Close using bootstrap element
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                if (bsAlert) {
                    bsAlert.close();
                }
            }
        }, timeout);
    }

    // Function to update the cart count in the navbar
    function updateCartCount(newCount) {
        const cartLink = document.querySelector('a.cart');
        if (!cartLink) return;

        let countSpan = cartLink.querySelector('.cart-item');
        
        if (newCount > 0) {
            if (countSpan) {
                // Span exists, just update the number part
                countSpan.firstChild.textContent = newCount;
            } else {
                // Span doesn't exist, create it
                countSpan = document.createElement('span');
                countSpan.className = 'cart-item';
                countSpan.style.fontSize = '0.75rem';
                countSpan.innerHTML = `${newCount}<span class="visually-hidden">Items in cart</span>`;
                cartLink.appendChild(countSpan);
            }
        } else {
            // Cart is empty, remove the span if it exists
            if (countSpan) {
                countSpan.remove();
            }
        }
    }

    // Attach event listeners to all "Add to cart" buttons
    document.querySelectorAll('.add-to-cart-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const clickedButton = e.currentTarget;
            const csrfToken = clickedButton.getAttribute('data-product-csrf-token');

            if (!checkEmptyCsrfToken(csrfToken)) return;

            const btn = this;
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Adding...';

            const formData = new FormData();
            formData.append('product_id', btn.dataset.productId);
            formData.append('product_name', btn.dataset.productName);
            formData.append('product_price', btn.dataset.productPrice);
            formData.append('product_image', btn.dataset.productImage);
            formData.append('product_color', btn.dataset.productColor);
            formData.append('product_variant', btn.dataset.productVariant);
            formData.append('product_category', btn.dataset.productCategory);
            formData.append('product_quantity', 1); // Default to 1
            formData.append('add-to-cart', '1');
            formData.append('ajax', '1');

            const CART_URL = '<?php echo htmlspecialchars(CART_URL, ENT_QUOTES, 'UTF-8'); ?>';
            fetch(CART_URL, {
                method: 'POST',
                body: formData,
                headers: {
                  'X-Requested-With': 'XMLHttpRequest',
                  'X-CSRF-Token' : csrfToken
                 }
            })
            .then(response => response.json())
            .then(data => {
                // Check for new CSRF token
                if (!checkNewCsrfToken(data.csrf_token, csrfToken)) return;
                // Update token on success/each request
                updateCsrfToken(data.csrf_token);
                btn.disabled = false;
                btn.textContent = originalText;
                const image_url = '<?php echo ASSETS_URL . "/imgs/"; ?>';
                const image = (data.product_image && (data.product_image.startsWith('http') || data.product_image.startsWith('//'))) ? data.product_image : image_url + data.product_image;
                showCartNotice(image, data.message, data.product_color,data.product_variant,data.product_price, !data.success);
                if (data.success && typeof data.items !== 'undefined') {
                    updateCartCount(data.items); 
                }
            })
            .catch(error => {
                console.error('Error While adding Item to cart:', error);
                btn.disabled = false;
                btn.textContent = originalText;
                showCartNotice('', 'An error occurred while adding to cart. Please try again.', '', '', '', true);
            });
        });

    });
});

// Wishlist Toggle Function
function toggleWishlist(productId, buttonElement) {
    // Disable button to prevent double clicks
    buttonElement.disabled = true;
    const originalHTML = buttonElement.innerHTML;
    buttonElement.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    fetch('<?php echo INTERNAL_LOGIC_URL; ?>/wishlist_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            action: 'toggle',
            product_id: productId
        })
    })
    .then(response => {
        if (!response.ok) {
            console.error('Wishlist response error:', response.status, response.statusText);
        }
        return response.json().then(data => ({ data, status: response.status }));
    })
    .then(({ data, status }) => {
        buttonElement.disabled = false;

        if (data.success) {
            // Update button state
            const icon = buttonElement.querySelector('i');
            if (data.action === 'added') {
                // Product added to wishlist
                icon.className = 'fas fa-heart text-red-500';
                buttonElement.title = 'Remove from wishlist';
                buttonElement.setAttribute('data-in-wishlist', '1');
            } else if (data.action === 'removed') {
                // Product removed from wishlist
                icon.className = 'far fa-heart text-gray-400 hover:text-red-500';
                buttonElement.title = 'Add to wishlist';
                buttonElement.setAttribute('data-in-wishlist', '0');
            }
            
            // Show success message
            showWishlistNotification(data.message, false);
        } else {
            // Reset button on error
            buttonElement.innerHTML = originalHTML;
            console.error('Wishlist operation failed:', data);
            showWishlistNotification(data.message || 'An error occurred while updating your wishlist', true);
        }
    })
    .catch(error => {
        console.error('Error toggling wishlist:', error);
        buttonElement.disabled = false;
        buttonElement.innerHTML = originalHTML;
        showWishlistNotification('Network error occurred while updating your wishlist. Please try again.', true);
    });
}

// Show Wishlist Notification
function showWishlistNotification(message, isError = false) {
    const container = document.getElementById('wishlist-notice-container');
    
    // Create container if it doesn't exist
    let noticeContainer = container;
    if (!noticeContainer) {
        noticeContainer = document.createElement('div');
        noticeContainer.id = 'wishlist-notice-container';
        noticeContainer.style.cssText = 'position: fixed; top: 100px; right: 20px; z-index: 9999; max-width: 400px;';
        document.body.appendChild(noticeContainer);
    }

    const alert = document.createElement('div');
    alert.className = 'alert alert-dismissible fade show ' + (isError ? 'alert-danger' : 'alert-success');
    alert.role = 'alert';

    alert.innerHTML = `
        <div class="font-rubik font-weight-600" style="margin:10px 0;">${message}</div>
        <button type="button" class="btn-close bg-light" data-bs-dismiss="alert" aria-label="Close"></button>
    `;

    noticeContainer.innerHTML = '';
    noticeContainer.appendChild(alert);

    // Auto-close after 5 seconds
    const timeout = isError ? 7000 : 5000;
    setTimeout(() => {
        if (noticeContainer.contains(alert)) {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) {
                bsAlert.close();
            }
        }
    }, timeout);
}
</script>

</body>
</html>
