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

?>

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
            <p>Marché Central, Yaoundé</p>
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

  <script src="<?php echo ASSETS_URL; ?>/includes/bootstrap-5.3.7-dist/js/bootstrap.bundle.min.js"></script>
