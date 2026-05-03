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

use external_server\Connect;
$conn = new Connect();

// mapping the shop location from the database to display on the map
  $latitude = 3.86707; // Default to Yaoundé if fetch fails
  $longitude = 11.518423;
  try{
    
    $stmt = $conn->prepare("SELECT latitude, longitude FROM map where shop_id = :shop_id");
    //bind parameter 
    $shop_id = "1";
    $stmt->bindParam(':shop_id', $shop_id, PDO::PARAM_INT);
    $stmt->execute();

  $shopLocation = $stmt->fetch(PDO::FETCH_ASSOC); // Fetch single row
  if ($shopLocation) {
        $latitude = $shopLocation['latitude'];
        $longitude = $shopLocation['longitude'];
    }
} catch (PDOException $e) {
    error_log("Map Error: " . $e->getMessage());
}

require_once TEMPLATES_PATH. '/navbar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Contact Us - Universal Store</title>

  <!-- bootstrap CDN -->
  <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/includes/bootstrap-5.3.7-dist/css/bootstrap.min.css">

  <!-- Font Awesome CDN -->
  <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/includes/fontawesome-free-5.15.4-web/css/all.css">
  <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/includes/fontawesome/fontawesome-free-6.7.2-web/css/all.css">

  <!-- custom css file -->
  <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style1.css">
  <link href="https://ik.imagekit.io/univ35tore/universal_store/imgs/UNIVERSAL%20STORE.jpg" rel="icon">

</head>
<body>

  <!-- Contact Info -->

<section id="contact">
    <div class="container mt-5">

      <div class="contact">
      <h1 class="text-center font-Roboto color-white1 font-weight-800 font-size-60 mt-5">Contact Us</h1>
      <p class="text-center font-rale font-size-20 font-weight-800 color-white">How can we help?</p>
      </div>
    </div>
    <div class="container contact-banner">
      <div class="inner col-sm-12">
        <h5 class="color-black font-size-20 font-Roboto font-weight-800 mb-4"><i class="fa-solid fa-location-dot me-4"></i>Physical Address</h5>
        <p class="color-white font-size-16">Marché Central, Yaoundé</p>
      </div>
      <div class="inner col-sm-12">
        <h5 class="color-black font-size-20 font-Roboto font-weight-800 mb-4"><i class="fas fa-phone me-4 "></i>Phone Numbers</h5>
        <p class="color-white font-size-16"><a href="tel:694-81-76-63">237-694-81-76-63</a><br>
        <a href="tel:693-73-58-30">237-693-73-58-30</a></p>
   
      </div>
      <div class="inner col-sm-12">
        <h5 class="color-black font-size-20 font-Roboto font-weight-800 mb-4"><i class="fa-solid fa-envelope me-4"></i>Email Address</h5>
        <p class="color-white font-size-16"><a href="mailto:fonkouaarnauld@gmail.com">fonkouaarnauld@gmail.com</a><br>
        <a href="mailto:arnauldfonkoua@icloud.com">arnauldfonkoua@icloud.com</a></p>
      </div>
  </div>


</section>

  <!-- Contact Form Message -->

  <section id="contact-form" class="py-5 mx-auto">
    <div class="container contact-messenger mt-5">
    <p >We’re here to help! If you have any questions, feedback, or need support, please don’t hesitate to contact us.
           You can reach us by phone at [phone number], by email at [email address], or by filling out our online contact
            form. Our team is available Monday to Friday, from 9 AM to 6 PM, and we aim to respond within 24 hours.”</p>
          </div>

    <div class="row"> 
      <div class="container col-md-5 col-sm-12 contact-message mt-5">
         <h3 class="font-Roboto color-black font-weight-800 mt-5">Send us a message</h3>
         <p class="mb-4">Got a question or want to say hello? We’d love to hear from you! Drop us a message.
           <span> We're excited to connect with you! Whether you have a question, need assistance, or just want 
            to share your thoughts, our team is here and ready to help. Reach out today and let us make your experience exceptional!</span>
         </p>

         <!-- Contact Form--->

          <form method="" action="" class="contact-form">

            <div class="form-group group-name">
            <div class="input">
            <input type="text" class="form-control" name="fname" placeholder="First Name *" required/>
            </div>
            <div  class="input">
            <input type="text" class="form-control" name="lname" placeholder="Last Name *" required/>
            </div>
            </div>

            <div class="form-group group-name">
            <div class="input">
            <input type="tel" class="form-control" name="phone" autocomplete="Your phone number *" placeholder="Your phone number *"required/>
            </div>

           <div class="input">
            <input type="email" class="form-control" name="email"  autocomplete="email" placeholder="Your Email *"/>
            </div>
            </div>

            <div class="form-group">
            <input type="email" class="form-control" name="subject" autocomplete="email" placeholder="Subject *"/>
            </div>

            <div class="form-group">
            <textarea name="addinfo" id="checkout-addinfo" class="form-control" placeholder="Message *" ></textarea>
            </div>

             <div class="form-group">
              <input type="submit" name="message" value="Send Message" class="contact-btn">
             </div>
</form>

    </div>

    <div class="container col-md-5 col-sm-12 mt-4 mx-auto map">
      <h3 class="text-center">Our Shop Location</h3>
      <div id="map">
        <script>
  // Pass PHP variables to JS
  const shopLat = <?php echo $latitude; ?>;
  const shopLng = <?php echo $longitude; ?>;

  function initMap() {
    const shopLocation = { lat: parseFloat(shopLat), lng: parseFloat(shopLng) };
    const map = new google.maps.Map(document.getElementById("map"), {
      zoom: 15,
      center: shopLocation,
    });
    const marker = new google.maps.Marker({
      position: shopLocation,
      map: map,
      title: "Universal Store - Marché Central, Yaoundé",
    });
  }
</script>
      <script async defer src="https://maps.googleapis.com/maps/api/js?key=AIzaSyAXKM_IikV_4EKPx5inxoueF3cp7niyJIQ&callback=initMap&language=fr&region=CM"></script>
    </div>
  </div>

    </div>
  </section>

  <!--   Related Products  -->
<section id="featured" class="my-5 pb-5">
   <div class="container mt-5 py-5 sales">
 <div>
 <h2>Trending Now </h2>
 <hr>
  <p class="font-rubik font-size-20">The most popular products on sales now</p>
  </div>
  <div>
  <button class="view-btn">View All</button>
  </div>
</div>

 
<div class="row mx-auto container-fluid ">
<div class="products col-md-4 col-sm-6 col-6 mb-3">
<div class="best-sales">
  <img class="img-fluid mb-3 " src="<?php echo ASSETS_URL; ?>/imgs/iphone16_PNG6.png" alt="iPhone 16" />
  </div> <div class="content ">
        <div class="star">
          <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
        </div>
    <h4 class="P-name">iPhone 16</h4>
    <h5 class="P-price">500,000 FCFA</h5>
    <button class="add-cart-btn">Add to cart</button>
    </div>
</div>

<div class="products col-md-4 col-sm-6 col-6 mb-3">
<div class="best-sales">
  <img class="img-fluid mb-3 " src="<?php echo ASSETS_URL; ?>/imgs/iphone16_PNG8.png" alt="iPhone 16" />
  </div> <div class="content ">
        <div class="star">
          <i class="fas fa-star" ></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
        </div>
    <h4 class="P-name">iPhone 16</h4>
    <h5 class="P-price">500,000 FCFA</h5>
    <button class="add-cart-btn">Add to cart</button>
    </div>
</div>

<div class="products col-md-4 col-sm-6 col-6 mb-3">
<div class="best-sales">
  <img class="img-fluid mb-3 " src="<?php echo ASSETS_URL; ?>/imgs/iphone16_PNG13.png" alt="iPhone 16" />
  </div> <div class="content ">
        <div class="star">
          <i class="fas fa-star" ></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
        </div>
    <h4 class="P-name">iPhone 16</h4>
    <h5 class="P-price">500,000 FCFA</h5>
    <button class="add-cart-btn">Add to cart</button>
    </div>
</div>

<div class="products col-md-4 col-sm-6 col-6 mb-3">
<div class="best-sales">
  <img class="img-fluid mb-3 " src="<?php echo ASSETS_URL; ?>/imgs/ipad013.jpeg" alt="iPhone 16" />
  </div> <div class="content ">
        <div class="star">
          <i class="fas fa-star" ></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
        </div>
    <h4 class="P-name">iPad 12</h4>
    <h5 class="P-price">300,000 FCFA</h5>
    <button class="add-cart-btn">Add to cart</button>
    </div>
</div>

<div class="products col-md-4 col-sm-6 col-6 mb-3">
<div class="best-sales">
  <img class="img-fluid mb-3 " src="<?php echo ASSETS_URL; ?>/imgs/iphone16_PNG8.png" alt="iPhone 16" />
  </div> <div class="content ">
        <div class="star">
          <i class="fas fa-star" ></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
        </div>
    <h4 class="P-name">iPhone 16</h4>
    <h5 class="P-price">500,000 FCFA</h5>
    <button class="add-cart-btn">Add to cart</button>
    </div>
</div>

<div class="products col-md-4 col-sm-6 col-6 mb-3">
<div class="best-sales">
  <img class="img-fluid mb-3 " src="<?php echo ASSETS_URL; ?>/imgs/iphone16_PNG8.png" alt="iPhone 16" />
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

</section>

  <section id="news">
    
    <div class="row py-5 my-5">
        <h1 class="color-white1 text-center">Don't Miss Our News</h1>
  
    <div class="container col-md-6 col-sm-12 ">
   <form method="" action="" >
    <div class="form-group news-form">
    <div class="news-input">
      <input type="email" name="email" placeholder="Your Email Address *" required/>
      </div>  
       <div class="news-input">
      <input type="submit" name="submit" value="SUBSCRIBE" class="subscribe-btn" />
    </div>
    </div>
</form> 
</div> 
 </div>
  </section>

<script src="<?php echo ASSETS_URL; ?>/includes/bootstrap-5.3.7-dist/js/bootstrap.bundle.min.js"></script>

<?php  require_once TEMPLATES_PATH .'/footer.php'; ?></body>
</html>