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


?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- bootstrap CDN -->
  <link rel="stylesheet" href="assets/includes/bootstrap-5.3.7-dist/css/bootstrap.min.css">

  <!-- Font Awesome CDN -->
  <link rel="stylesheet" href="assets/includes/fontawesome-free-5.15.4-web/css/all.css">
  <link rel="stylesheet" href="assets/includes/fontawesome/fontawesome-free-6.7.2-web/css/all.css">

  <!-- custom css file -->
  <link rel="stylesheet" href="assets/css/style.css">
  <link href="assets/imgs/apple.jpeg" rel="icon">
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
            <img src="assets/imgs/camer.jpeg" class="flag"><strong>CM</strong>
            </div>
        </div>
    <div class="main-nav">
          <a href="<?php echo universalstore_URL; ?>/index" class="brand">
          <img class="logo" src="assets/imgs/apple.jpeg" alt="Universal Store logo" />
          <span>UNIVERSAL STORE</span>
          </a>
            <div class="search-box hidden">
            <i class="fas fa-search"></i>
            <input type="search" name="search" id="search" placeholder="search key words.." autocomplete="off"> 
        </div>
        
        <ul class="main-menu">
        <li>
            <span type="button" class="purchase-btn hidden"><a href="<?php echo universalstore_URL; ?>/categories">Start Shopping</a></span>
        </li>
             <li>
            <a href="contact.php"><span class="help">Contact us</span></a>
            </li>
             <li>
             <a class="nav-link position-relative cart" href="cart.php" aria-label="Cart">
              <i class="fas fa-shopping-bag"></i>
              <span class="cart-item" style="font-size: 0.75rem;">5
              <span class="visually-hidden">Items in cart</span></span>
             </a>
            </li>
            <?Php if($authenticatedUserId){?>
             <!-- Account button  -->
            <li class="nav-item dropdown">
            <a class="nav-link d-inline-flex align-items-center justify-content-center account-btn" href="#" id="navbarAccountDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account">
              <i class="fas fa-user color-white1"></i></a>
          <ul class="dropdown-menu dropdown-menu-end shadow-lg" aria-labelledby="navbarAccountDropdown" style="min-width:18rem;">
            <div class="border-bottom py-3 font-size-20 px-4 font-rubik color-smoke"><span >My Account</span></div>
            <li><a href="dashboard/profile.php?tab=profil" class="dropdown-item border-bottom d-flex align-items-center py-4 font-rubik font-weight-500 mb-1 "><i class="fa-regular fa-user color-light-blue me-2"></i> Profil</a></li>
            <li><a href="dashboard/profile.php?tab=orders"class="dropdown-item border-bottom d-flex align-items-center py-4 font-rubik font-weight-500 mb-1 "><i class="fas fa-box-open color-orange me-2"></i>Orders and help</a></li>
            <li><a href="dashboard/profile.php?tab=wishlists" class="dropdown-item border-bottom d-flex align-items-center py-4 font-rubik font-weight-500 mb-1 "><i class="fas fa-heart me-2"></i>Wishlists</a></li>
            <li><a href="dashboard/profile.php?tab=activities" class="dropdown-item border-bottom d-flex align-items-center py-4 font-rubik font-weight-500 mb-1 "><i class="fa-solid fa-clock-rotate-left color-light-red me-2"></i>Activity history</a></li>
            <li><a href="?action=logout" class="dropdown-item d-flex align-items-center py-4 font-rubik font-weight-500 logout "><i class="fa-solid fa-power-off color-light-red me-2"></i>Log Out</a></li>
          </ul>
        </li>
        <?php } else{?>
          <li>
             <a class="nav-link p-0 color-smoke" href="account/authentication/authentication.php" aria-label="Account"><i class="fas fa-user"></i></a>
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
        <div class="search-box">
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
            <a href="#" class="bottom">Smartphones<i class="fas fa-chevron-right hidden-arrow "></i>
            <span><div class="hidden"></div></span>
            </a>
            <div class="dropdown-menu" id="dropdown-menu">
            <div class="container">
            <div class="left-section">
            <span class="dropdown-close"><i class="fas fa-chevron-left"></i> Back</span>
                <h1>Brand products</h1>
                <p>View all latest smartphones from iPhone, Samsung, Redmi and 
                  more get a look to your phones. The choice is yours <i class="fas fa-smile"></i> </p>
                <a href="#" class="btn-see-all">See all</a>
            </div>
            <div class="right-section row">
                <h5>Categories</h5>
                <ul class="dropdown-links container">
                   <li class="col-lg-4 col-md-6 col-sm-6 ">
                    <a href="#">
                        <div>
                        <img src="assets/imgs/dropimage22.webp" class="dropdown-img" alt="iPhone 12 pro max">
                        <p>iPhone 12 pro max</p>
                        </div>
                    </a>
                   </li> 
                     <li class="col-lg-4 col-md-6 col-sm-6 ">
                    <a href="#">
                        <div>
                        <img src="assets/imgs/dropimage17.webp" class="dropdown-img2" alt="Samsung Galaxy">
                        <p>Samsung Galaxy</p>
                        </div>
                    </a>
                   </li> 
                     <li class="col-lg-4 col-md-6 col-sm-6 ">
                    <a href="#">
                        <div>
                        <img src="assets/imgs/dropimage16.webp" class="dropdown-img3" alt="Samsung S23 ultra">
                        <p>Samsung S23 ultra</p>
                        </div>
                    </a>
                   </li> 
                     <li class="col-lg-4 col-md-6 col-sm-6 ">
                    <a href="#">
                        <div>
                        <img src="assets/imgs/dropimage26.webp" class="dropdown-img4" alt="Google Pixel 9">
                        <p>Google Pixel 9</p>
                        </div>
                    </a>
                   </li> 
                   
                        </ul>
                    </div>
                  </div>
                </div>
            </li> 
                     <li>
                        <a href="#" class="bottom">iPads<i class="fas fa-chevron-right hidden-arrow "></i>
                        <span><div class="hidden"></div></span>
                        </a>
                   </li>
                   <li>
                        <a href="#" class="bottom">Laptops/PC's<i class="fas fa-chevron-right hidden-arrow "></i>
                        <span><div class="hidden"></div></span>
                        </a>
                   </li>
                   <li>
                        <a href="#" class="bottom">Consoles<i class="fas fa-chevron-right hidden-arrow "></i>
                        <span><div class="hidden"></div></span>
                        </a>
                   </li>
                   <li>
                        <a href="#" class="bottom">Audio<i class="fas fa-chevron-right hidden-arrow "></i>
                        <span><div class="hidden"></div></span>
                        </a>
                   </li>
                   <li>
                        <a href="#" class="bottom">Gadgets<i class="fas fa-chevron-right hidden-arrow "></i>
                        <span><div class="hidden"></div></span>
                        </a>
                   </li>
                   <li>
                        <a href="#" class="bottom">Apple watches<i class="fas fa-chevron-right hidden-arrow "></i>
                        <span><div class="hidden"> </div></span>
                        </a>
                   </li>
                   <li>
                        <a href="#" class="bottom">Others<i class="fas fa-chevron-right hidden-arrow "></i>
                        <span><div class="hidden"></div></span>
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
                    <img src="assets/imgs/camer.jpeg" class="flag"><strong class="cm">CM</strong>
                    </div>
                   </ul>
            
         </div>
    </nav>

  <script src="assets/js/nav.js"></script>
<script src="assets/includes/bootstrap-5.3.7-dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>