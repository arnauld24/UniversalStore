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

use external_server\Connect;
use internal_logic\Cart;

// Initialize database and cart
$conn = new Connect();
$cart = new Cart($authenticatedUserId);

// Create slug function
function createSlug($string){
  $string = strtolower($string);
  $string = preg_replace('/[^a-z0-9]+/i', '-', $string);
  $string = preg_replace('/^-+|-+$/', '', $string);
  return rtrim($string, '-');
}

// Get all main categories with their product count
try {
    $stmt = $conn->prepare("
        SELECT 
            product_main_category,
            COUNT(*) as product_count,
            GROUP_CONCAT(DISTINCT product_image) as sample_images
        FROM products
        WHERE product_main_category IS NOT NULL 
        AND product_main_category != ''
        GROUP BY product_main_category
        ORDER BY product_main_category ASC
    ");
    
    if (!$stmt) {
        error_log("Prepare failed in categories.php: " . print_r($conn->errorInfo(), true));
        throw new Exception("Failed to prepare categories query");
    }
    
    $exec_result = $stmt->execute();
    if (!$exec_result) {
        error_log("Execute failed in categories.php: " . print_r($stmt->errorInfo(), true));
        throw new Exception("Failed to execute categories query");
    }
    
    $main_categories = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    if (empty($main_categories)) {
        error_log("No main categories found in database - product_main_category column may be empty");
    }
} catch (Exception $e) {
    error_log("Error fetching main categories: " . $e->getMessage());
    $main_categories = array();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Categories - Universal Store</title>

  <!-- bootstrap CDN -->
  <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/includes/bootstrap-5.3.7-dist/css/bootstrap.min.css">

  <!-- Font Awesome CDN -->
  <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/includes/fontawesome-free-5.15.4-web/css/all.css">
  <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/includes/fontawesome/fontawesome-free-6.7.2-web/css/all.css">

  <!-- custom css file -->
  <link rel="stylesheet" href="<?php echo ASSETS_URL?>/css/style1.css">
  <link rel="stylesheet" href="<?php echo ASSETS_URL?>/css/categories.css">
  <link href="https://ik.imagekit.io/univ35tore/universal_store/imgs/UNIVERSAL%20STORE.jpg" rel="icon">
</head>
<body>

  <!-- Hero Section -->
  <section class="hero-section">
    <div class="hero-content">
      <h1>Explore Categories</h1>
      <p>Discover our extensive range of products organized by category</p>
      <p style="font-size: 14px; opacity: 0.8; margin-top: 20px;">
        Browse and find exactly what you're looking for
      </p>
    </div>
  </section>

  <!-- Main Container -->
  <div class="container-custom">
    <!-- Categories Section -->
    <section class="categories-wrapper">
      <div class="section-title">
        <h2>Shop by Category</h2>
        <p>Select a category to view all available products</p>
      </div>

      <?php if (!empty($main_categories)): ?>
        <div class="categories-grid">
          <?php foreach ($main_categories as $category): 
            $main_cat_slug = createSlug($category['product_main_category']);
            $images = explode(',', $category['sample_images']);
            $main_image = trim($images[0]);
            // Resolve image URL
            $main_image_clean = htmlspecialchars($main_image, ENT_QUOTES, 'UTF-8');
            $img_src = (preg_match('#^(https?:)?//#i', $main_image_clean)) ? $main_image_clean : (universalstore_URL . '/assets/imgs/' . $main_image_clean);
          ?>
            <a href="<?php echo universalstore_URL; ?>/category/<?php echo $main_cat_slug; ?>" style="text-decoration: none; color: inherit;">
              <div class="category-card">
                <div class="category-image-wrapper">
                  <img src="<?php echo $img_src; ?>" alt="<?php echo htmlspecialchars($category['product_main_category'], ENT_QUOTES, 'UTF-8'); ?>">
                  <div class="category-image-count">
                    <i class="fas fa-box"></i> <?php echo $category['product_count']; ?> products
                  </div>
                </div>

                <div class="category-content">
                  <h3 class="category-name">
                    <?php echo htmlspecialchars($category['product_main_category'], ENT_QUOTES, 'UTF-8'); ?>
                  </h3>

                  <div class="category-stats">
                    <div class="stat-item">
                      <div class="stat-number"><?php echo $category['product_count']; ?></div>
                      <div class="stat-label">Products</div>
                    </div>
                  </div>

                  <p class="category-description">
                    Explore our collection of <?php echo strtolower(htmlspecialchars($category['product_main_category'], ENT_QUOTES, 'UTF-8')); ?>. 
                    Find the perfect product that matches your needs and preferences.
                  </p>

                  <div class="category-link">
                    <span>Browse Category</span>
                    <i class="fas fa-arrow-right"></i>
                  </div>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty-state">
          <i class="fas fa-inbox"></i>
          <h3>No Categories Available</h3>
          <p>Categories will appear here as products are added</p>
        </div>
      <?php endif; ?>
    </section>

    <!-- Features Section -->
    <section class="features-section">
      <div class="section-title" style="margin-bottom: 40px;">
        <h2>Why Choose Universal Store</h2>
      </div>

      <div class="features-grid">
        <div class="feature-item">
          <div class="feature-icon">
            <i class="fas fa-shipping-fast"></i>
          </div>
          <h4 class="feature-title">Fast Delivery</h4>
          <p class="feature-description">Quick and reliable shipping to your doorstep within estimated timeframe</p>
        </div>

        <div class="feature-item">
          <div class="feature-icon">
            <i class="fas fa-shield-alt"></i>
          </div>
          <h4 class="feature-title">Secure Payment</h4>
          <p class="feature-description">Multiple payment options with secure transaction processing</p>
        </div>

        <div class="feature-item">
          <div class="feature-icon">
            <i class="fas fa-headset"></i>
          </div>
          <h4 class="feature-title">24/7 Support</h4>
          <p class="feature-description">Our customer service team is always ready to assist you</p>
        </div>

        <div class="feature-item">
          <div class="feature-icon">
            <i class="fas fa-redo"></i>
          </div>
          <h4 class="feature-title">Easy Returns</h4>
          <p class="feature-description">Hassle-free return process within 15 days of delivery</p>
        </div>
      </div>
    </section>

    <!-- FAQ & Facts Section -->
    <section class="faq-facts-section">
      <div class="section-title" style="margin-bottom: 40px;">
        <h2>Questions & Facts</h2>
        <p>Everything you need to know about shopping with us</p>
      </div>

      <div class="faq-container">
        <!-- Left Column: FAQ -->
        <div class="faq-column">
          <h3 class="faq-title"><i class="fas fa-question-circle"></i> Frequently Asked Questions</h3>
          
          <div class="accordion" id="faqAccordion">
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1" aria-expanded="true" aria-controls="faq1">
                  How long does delivery take?
                </button>
              </h2>
              <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                  Delivery times depend on your payment speed and location. Orders are typically processed within 1-2 business days after payment confirmation. Standard delivery takes 3-7 days, while expedited shipping can arrive within 24-48 hours.
                </div>
              </div>
            </div>

            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2" aria-expanded="false" aria-controls="faq2">
                  What payment methods do you accept?
                </button>
              </h2>
              <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                  We accept multiple payment options including MTN Mobile Money, Orange Money, and credit/debit cards. All transactions are secured with encryption technology to protect your information.
                </div>
              </div>
            </div>

            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3" aria-expanded="false" aria-controls="faq3">
                  Can I return items?
                </button>
              </h2>
              <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                  Yes! You can return items within 15 days of delivery. The item must be unused and in original packaging. Return shipping costs are covered by us for defective products.
                </div>
              </div>
            </div>

            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4" aria-expanded="false" aria-controls="faq4">
                  Do you offer warranties?
                </button>
              </h2>
              <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                  All our products come with manufacturer's warranty. Electronics typically have 1-2 year warranties. We also offer extended warranty options at checkout for additional peace of mind.
                </div>
              </div>
            </div>

            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5" aria-expanded="false" aria-controls="faq5">
                  Is my personal information secure?
                </button>
              </h2>
              <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                  Absolutely! We use industry-standard SSL encryption and secure CSRF tokens to protect all your data. Your payment information is never stored on our servers and is processed securely.
                </div>
              </div>
            </div>

            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq6" aria-expanded="false" aria-controls="faq6">
                  How can I track my order?
                </button>
              </h2>
              <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">
                  Once your order is shipped, you'll receive a tracking number via email and SMS. You can use this to track your package in real-time on our portal or your dashboard account.
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column: Facts -->
        <div class="facts-column">
          <h3 class="facts-title"><i class="fas fa-lightbulb"></i> Quick Facts</h3>
          
          <div class="facts-grid">
            <div class="fact-card">
              <div class="fact-number">2024</div>
              <div class="fact-text">Founded in Cameroon</div>
              <div class="fact-icon"><i class="fas fa-store"></i></div>
            </div>

            <div class="fact-card">
              <div class="fact-number">10K+</div>
              <div class="fact-text">Happy Customers</div>
              <div class="fact-icon"><i class="fas fa-smile"></i></div>
            </div>

            <div class="fact-card">
              <div class="fact-number">5K+</div>
              <div class="fact-text">Products Available</div>
              <div class="fact-icon"><i class="fas fa-box"></i></div>
            </div>

            <div class="fact-card">
              <div class="fact-number">100%</div>
              <div class="fact-text">Authentic Products</div>
              <div class="fact-icon"><i class="fas fa-check-circle"></i></div>
            </div>

            <div class="fact-card">
              <div class="fact-number">24/7</div>
              <div class="fact-text">Customer Support</div>
              <div class="fact-icon"><i class="fas fa-headset"></i></div>
            </div>

            <div class="fact-card">
              <div class="fact-number">99.8%</div>
              <div class="fact-text">Delivery Success Rate</div>
              <div class="fact-icon"><i class="fas fa-truck"></i></div>
            </div>
          </div>

          <div class="trust-badges">
            <div class="badge-item">
              <i class="fas fa-lock"></i>
              <span>Secure Checkout</span>
            </div>
            <div class="badge-item">
              <i class="fas fa-certificate"></i>
              <span>Verified Seller</span>
            </div>
            <div class="badge-item">
              <i class="fas fa-award"></i>
              <span>Best Prices</span>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>

  <script src="assets/includes/bootstrap-5.3.7-dist/js/bootstrap.bundle.min.js"></script>

  <?php require_once TEMPLATES_PATH . '/footer.php'; ?>
</body>
</html>
