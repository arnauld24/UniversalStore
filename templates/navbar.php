<?php

// require config.php which holds BASE_PATH and BASE_URL
require_once __DIR__ . '/../config/config.php';
// Define the security constant if it hasn't been defined yet.
if (!defined('APP_INIT')) {
    error_log('Attempted direct access to secure utility file: ' . $_SERVER['REQUEST_URI']);
    http_response_code(403); 
    $redirect_url =  ERROR_URL . "?code=403&message= " . urlencode("Access Denied.");
    header("Location: $redirect_url");
    die('403 Access Denied.'); 
}
if(session_status() === PHP_SESSION_NONE){
  session_start();
}

use internal_logic\Cart;

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


?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- bootstrap CDN -->
  <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/includes/bootstrap-5.3.7-dist/css/bootstrap.min.css">

  <!-- Font Awesome CDN -->
  <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/includes/fontawesome-free-5.15.4-web/css/all.css">
  <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/includes/fontawesome/fontawesome-free-6.7.2-web/css/all.css">

  <!-- custom css file -->
  <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style1.css">
  <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/tailwindcss.css">
  <link href="https://ik.imagekit.io/univ35tore/universal_store/imgs/UNIVERSAL%20STORE.jpg" rel="icon">

<!-- JavaScript Error Logger -->
  <script src="<?php echo universalstore_URL; ?>/assets/js/error-logger.js"></script>

</head>
<body>
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
          <a href="<?php echo universalstore_URL; ?>/index" class="brand">
          <img class="logo" src="https://ik.imagekit.io/univ35tore/universal_store/imgs/UNIVERSAL%20STORE.jpg" alt="Universal Store logo" />
          <span>UNIVERSAL STORE</span>
          </a>
            <div class="search-box hiddened" style="background:white; border-radius:30px;">
            <i class="fas fa-search"></i>
            <input type="search" name="search" id="search" placeholder="search key words.." autocomplete="off"> 
        </div>
        
        <ul class="main-menu">
        <li>
            <span type="button" class="purchase-btn hiddened"><a href="<?php echo universalstore_URL; ?>/categories">Start Shopping</a></span>
        </li>
             <li>
            <a href="<?php echo universalstore_URL; ?>/contact"><span class="help">Contact us</span></a>
            </li>
             <li>
             <a class="nav-link position-relative cart" href="<?php echo universalstore_URL; ?>/cart" aria-label="Cart">
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
             <img src="<?php echo htmlspecialchars($user_data['user_avatar'] ?? 'avatar', ENT_QUOTES, 'UTF-8'); ?>" style="width: 40px; height:40px; border-radius: 30px; "class="w-8 h-8 lg:w-8 lg:h-8 border rounded-full">
                </a>
          <ul class="dropdown-menu dropdown-menu-end shadow-lg" aria-labelledby="navbarAccountDropdown" style="min-width:18rem;">
            <div class="border-bottom py-3 font-size-20 px-4 font-rubik color-smoke"><span >My Account</span></div>
            <li><a href="<?php echo PROFILE_URL; ?>?tab=profil" class="dropdown-item border-bottom d-flex align-items-center py-4 font-rubik font-weight-500 mb-1 "><i class="fa-regular fa-user color-light-blue me-2"></i> Profil</a></li>
            <li><a href="<?php echo PROFILE_URL; ?>?tab=orders"class="dropdown-item border-bottom d-flex align-items-center py-4 font-rubik font-weight-500 mb-1 "><i class="fas fa-box-open color-orange me-2"></i>Orders and help</a></li>
            <li><a href="<?php echo PROFILE_URL; ?>?tab=wishlists" class="dropdown-item border-bottom d-flex align-items-center py-4 font-rubik font-weight-500 mb-1 "><i class="fas fa-heart me-2"></i>Wishlists</a></li>
            <li><a href="<?php echo PROFILE_URL; ?>?tab=activities" class="dropdown-item border-bottom d-flex align-items-center py-4 font-rubik font-weight-500 mb-1 "><i class="fa-solid fa-clock-rotate-left color-light-red me-2"></i>Activity history</a></li>
            <li><a href="?action=logout" class="dropdown-item d-flex align-items-center py-4 font-rubik font-weight-500 logout "><i class="fa-solid fa-power-off color-light-red me-2"></i>Log Out</a></li>
          </ul>
        </li>
        <?php } else{?>
          <li>
             <a class="nav-link p-0 color-smoke" href="<?php echo AUTHENTICATION_URL; ?>" aria-label="Account"><i class="fas fa-user"></i></a>
            </li>
          <?php }?>
        </ul>
        <i class="fa-solid fa-bars toggle-navbar"></i>
    </div>

    <!-- Search row (second row on mobile) -->
    <div class="search-row">
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
            <a href="<?php echo universalstore_URL; ?>/category/smartphones" class="bottom">Smartphones
            <i class="fas fa-chevron-right hidden-arrow "></i>
            <span><div class="hidden"></div></span>
            </a>
            <div class="dropdown-menu">
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
                        <a href="<?php echo universalstore_URL; ?>/category/tablets" class="bottom">Tablets
                        <i class="fas fa-chevron-right hidden-arrow "></i>
                        <span><div class="hidden"></div></span>
                        </a>
                        <div class="dropdown-menu">
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
                        <a href="<?php echo universalstore_URL; ?>/category/laptops-and-pcs" class="bottom">Laptops/PC's
                        <i class="fas fa-chevron-right hidden-arrow "></i>
                        <span><div class="hidden"></div></span>
                        </a>
                        <div class="dropdown-menu">
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
                        <a href="<?php echo universalstore_URL; ?>/category/audio" class="bottom">Audio
                        <span><div class="hidden"></div></span>
                        </a><i class="fas fa-chevron-right hidden-arrow "></i>
                   </li>
                   <li>
                        <a href="<?php echo universalstore_URL; ?>/category/gadgets" class="bottom">Gadgets
                        <span><div class="hidden"></div></span>
                        </a><i class="fas fa-chevron-right hidden-arrow "></i>
                   </li>
                   <li>
                        <a href="<?php echo universalstore_URL; ?>/category/apple-watches" class="bottom">Apple watches
                        <span><div class="hidden"> </div></span>
                        </a><i class="fas fa-chevron-right hidden-arrow "></i>
                   </li>
                   <li>
                        <a href="<?php echo universalstore_URL; ?>/category/others" class="bottom">Others
                        <span><div class="hidden"></div></span>
                        </a><i class="fas fa-chevron-right hidden-arrow "></i>
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
<script>
    const BASE_URL = "<?php echo BASE_URL; ?>";
</script>
  <script src="<?php echo ASSETS_URL; ?>/js/nav.js"></script>
 
<script src="<?php echo ASSETS_URL; ?>/includes/bootstrap-5.3.7-dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>