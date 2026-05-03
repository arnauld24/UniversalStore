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
require_once TEMPLATES_PATH. '/navbar.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Order</title>

  <!-- bootstrap CDN -->
  <link rel="stylesheet" href="assets/includes/bootstrap-5.3.7-dist/css/bootstrap.min.css">

  <!-- Font Awesome CDN -->
  <link rel="stylesheet" href="assets/includes/fontawesome-free-5.15.4-web/css/all.css">
  <link rel="stylesheet" href="assets/includes/fontawesome/fontawesome-free-6.7.2-web/css/all.css">

  <!-- custom css file -->
  <link rel="stylesheet" href="assets/css/styles.css">
<link href="assets/imgs/apple.jpeg" rel="icon">

</head>
<body>


  <!-- Order -->
   <section class="orders my-5 py-5 mx-4">
<div class="container mx-0">
    <h2 class="font-weight-800 mx-auto text-center">Your Order</h2>
    <hr class="mx-auto">
 </div>
    <div class="row mt-0">
<div class="container col-lg-12 col-md-12 col-sm-12">
<table class="mt-5 pt-5 " >
    <tr>
    <th>Product</th>
    <th>Date</th>
    </tr>
   
   <tr class="order-info" onclick="window.location.href='single_product.php';">
        <td>
            <div class="product-info my-4">
                <div class="image" onclick="window.location.href='single_product.php'; ">
            <img src="assets/imgs/iphone16_PNG6.png" />
                </div>
                <div class="mx-4">
                <p class="font-size-18 font-weight-800 font-rubik " onclick="window.location.href='single_product.php'; " style="cursor: pointer;" onmouseover="this.style.color='#353daa'" onmouseout="this.style.color='black'">iPhone 16</p>
                <small>150,000<span></span>FCFA</small>
                    </div>
            </div>
        </td>
        <td>
            <span>2025-08-25</span>
        </td>

     </tr>
 <tr class="order-info" onclick="window.location.href='single_product.php';">
        <td>
            <div class="product-info my-4">
                <div class="image" onclick="window.location.href='single_product.php'; ">
            <img src="assets/imgs/iphone16_PNG13.png" />
                </div>
                <div class="mx-4">
                <p class="font-size-18 font-weight-800 font-rubik " onclick="window.location.href='single_product.php'; " style="cursor: pointer;" onmouseover="this.style.color='#353daa'" onmouseout="this.style.color='black'">iPhone 16</p>
                <small>250,000<span></span>FCFA</small>
                    </div>
            </div>
        </td>
        <td>
            <span>2025-07-25</span>
        </td>

     </tr>

</table>
</div>
   </section>





<script src="assets/includes/bootstrap-5.3.7-dist/js/bootstrap.bundle.min.js"></script>
<?php  require_once TEMPLATES_PATH .'/footer.php'; ?></body>
</html>