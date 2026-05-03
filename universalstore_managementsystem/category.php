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

// Breadcrumb
?>

<?php

use external_server\Connect;
use internal_logic\Cart;

// Initialize database and cart
$conn = new Connect();
$cart = new Cart($authenticatedUserId);

// Get cart count for display
if($cart->isLoggedIn()){
  $cart_id = $cart->getOrCreateCartIdForUser($cart->getAuthenticatedUserId());
  $cart_items_count = $cart->getCartItemCountForCart($cart_id);
} else {
  $cart_items_count = $cart->getGuestCartItemCount();
}

// Create slug function
function createSlug($string){
  $string = strtolower($string);
  $string = preg_replace('/[^a-z0-9]+/i', '-', $string);
  $string = preg_replace('/^-+|-+$/', '', $string);
  return rtrim($string, '-');
}

// Get the main category from URL slug (e.g., /category/smartphones)
$url_slug = isset($_GET['main_category']) ? htmlspecialchars($_GET['main_category'], ENT_QUOTES, 'UTF-8') : '';

// If no main_category, redirect to categories
if (empty($url_slug)) {
    header("Location: " . universalstore_URL . "/categories");
    exit;
}

// Convert slug back to main category name
$main_category_display = str_replace('-', ' ', $url_slug);
$main_category_display = ucwords($main_category_display);

// Verify that main category exists
try {
    $verify_stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE product_main_category = ?");
    if (!$verify_stmt) {
        error_log("Prepare failed: " . print_r($conn->errorInfo(), true));
        throw new Exception("Failed to prepare query");
    }
    
    $verify_result = $verify_stmt->execute([$main_category_display]);
    if (!$verify_result) {
        error_log("Execute failed: " . print_r($verify_stmt->errorInfo(), true));
        throw new Exception("Failed to execute query");
    }
    
    $result = $verify_stmt->fetch(\PDO::FETCH_ASSOC);
    
    if (!$result || $result['count'] == 0) {
        error_log("Category not found: " . $main_category_display);
        header("Location: " . universalstore_URL . "/categories");
        exit;
    }
} catch (Exception $e) {
    error_log("Category verification error: " . $e->getMessage());
    header("Location: " . universalstore_URL . "/categories");
    exit;
}

// Get filter parameters from URL or POST
$selected_category = isset($_GET['category']) ? htmlspecialchars($_GET['category'], ENT_QUOTES, 'UTF-8') : '';

// Handle colors - check both 'colors' and 'colors[]'
$selected_colors = array();
if (isset($_GET['colors'])) {
    if (is_array($_GET['colors'])) {
        $selected_colors = $_GET['colors'];
    } else {
        $selected_colors = array($_GET['colors']);
    }
} elseif (isset($_GET['colors[]'])) {
    if (is_array($_GET['colors[]'])) {
        $selected_colors = $_GET['colors[]'];
    } else {
        $selected_colors = array($_GET['colors[]']);
    }
}
$selected_colors = array_filter(array_map(function($c) { 
    return htmlspecialchars(trim($c), ENT_QUOTES, 'UTF-8'); 
}, $selected_colors));

// Handle variants - check both 'variants' and 'variants[]'
$selected_variants = array();
if (isset($_GET['variants'])) {
    if (is_array($_GET['variants'])) {
        $selected_variants = $_GET['variants'];
    } else {
        $selected_variants = array($_GET['variants']);
    }
} elseif (isset($_GET['variants[]'])) {
    if (is_array($_GET['variants[]'])) {
        $selected_variants = $_GET['variants[]'];
    } else {
        $selected_variants = array($_GET['variants[]']);
    }
}
$selected_variants = array_filter(array_map(function($v) { 
    return htmlspecialchars(trim($v), ENT_QUOTES, 'UTF-8'); 
}, $selected_variants));

$search_name = isset($_GET['search']) ? htmlspecialchars($_GET['search'], ENT_QUOTES, 'UTF-8') : '';
$min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 999999;
$sort_by = isset($_GET['sort']) ? htmlspecialchars($_GET['sort'], ENT_QUOTES, 'UTF-8') : 'newest';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Build the query - ALWAYS filter by main_category
$where_clauses = array("product_main_category = ?");
$params = array($main_category_display);

if (!empty($selected_category)) {
    $where_clauses[] = "product_category = ?";
    $params[] = $selected_category;
}

if (!empty($selected_colors)) {
    $placeholders = implode(',', array_fill(0, count($selected_colors), '?'));
    $where_clauses[] = "product_color IN ({$placeholders})";
    $params = array_merge($params, $selected_colors);
}

if (!empty($selected_variants)) {
    $variant_conditions = array();
    foreach ($selected_variants as $variant) {
        $variant_conditions[] = "product_variant LIKE ?";
        $params[] = "%{$variant}%";
    }
    $where_clauses[] = "(" . implode(" OR ", $variant_conditions) . ")";
}

if (!empty($search_name)) {
    $where_clauses[] = "product_name LIKE ?";
    $params[] = "%{$search_name}%";
}

$where_clauses[] = "product_price BETWEEN ? AND ?";
$params[] = $min_price;
$params[] = $max_price;

$where = implode(" AND ", $where_clauses);

// Order clause based on sort
$order = "ORDER BY product_id DESC";
if ($sort_by === 'price_low') {
    $order = "ORDER BY product_price ASC";
} elseif ($sort_by === 'price_high') {
    $order = "ORDER BY product_price DESC";
} elseif ($sort_by === 'name') {
    $order = "ORDER BY product_name ASC";
}

// Get total count for pagination
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM products WHERE {$where}");
if (!$count_stmt) {
    error_log("Count query prepare failed: " . $conn->errorInfo()[2]);
    $total_products = 0;
} else {
    if (!$count_stmt->execute($params)) {
        error_log("Count query execute failed: " . $count_stmt->errorInfo()[2]);
        error_log("Query: SELECT COUNT(*) as total FROM products WHERE {$where}");
        error_log("Params: " . json_encode($params));
        $total_products = 0;
    } else {
        $result = $count_stmt->fetch(\PDO::FETCH_ASSOC);
        $total_products = $result ? $result['total'] : 0;
    }
}
$total_pages = ceil($total_products / $per_page);

// Get products
$stmt = $conn->prepare("SELECT * FROM products WHERE {$where} {$order} LIMIT ? OFFSET ?");
if (!$stmt) {
    error_log("Products query prepare failed: " . $conn->errorInfo()[2]);
} else {
    $params[] = $per_page;
    $params[] = $offset;
    if (!$stmt->execute($params)) {
        error_log("Products query execute failed: " . $stmt->errorInfo()[2]);
        error_log("Query: SELECT * FROM products WHERE {$where} {$order} LIMIT ? OFFSET ?");
        error_log("Params: " . json_encode($params));
        $products = [];
    }
    else{
      $products = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}

// Get all unique subcategories for this main category
$categories_stmt = $conn->prepare("SELECT DISTINCT product_category FROM products WHERE product_main_category = ? ORDER BY product_category ASC");
$categories_stmt->execute([$main_category_display]);
$categories = $categories_stmt->fetchAll(\PDO::FETCH_ASSOC);

// Get all unique colors for this main category
$colors_stmt = $conn->prepare("SELECT DISTINCT product_color FROM products WHERE product_main_category = ? ORDER BY product_color ASC");
$colors_stmt->execute([$main_category_display]);
$colors = $colors_stmt->fetchAll(\PDO::FETCH_ASSOC);

// Get all unique variants for this main category
$variants_stmt = $conn->prepare("SELECT DISTINCT product_variant FROM products WHERE product_main_category = ? AND product_variant IS NOT NULL AND product_variant != ''");
$variants_stmt->execute([$main_category_display]);
$all_variants_raw = $variants_stmt->fetchAll(\PDO::FETCH_ASSOC);

// Parse variants - they are comma-separated
$all_variants = array();
foreach ($all_variants_raw as $row) {
    $variant_array = explode(',', $row['product_variant']);
    foreach ($variant_array as $v) {
        $v = trim($v);
        if (!empty($v) && !in_array($v, $all_variants)) {
            $all_variants[] = $v;
        }
    }
}
sort($all_variants);

// Generate stars HTML based on rating

function generateStars($rating, $count){
  $fullStars = floor($rating);
  $halfStar = ($rating - $fullStars) >= 0.5 ? 1 : 0; 
  $emptyStars = 5 - $fullStars - $halfStar;
  $html = '';
  for($i = 0; $i < $fullStars; $i++) $html .= '<i class="fas fa-star"></i>';
  if($halfStar) $html .= '<i class="fas fa-star-half-alt"></i>'; 
  for($i = 0; $i < $emptyStars; $i++ ) $html .= '<i class="far fa-star"></i>';
  $html .= ' <span style="color: #666; font-size: 12px;"> (' . number_format($rating, 1) . ') ' . $count . ' reviews</span>';
  return $html; 

}

// Get ratings for products
$ratings = [];
if (!empty($products)) {
    $product_ids = array_column($products, 'product_id');
    $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
    $reviews_stmt = $conn->prepare("SELECT product_id, AVG(rating) as avg_rating, COUNT(*) as review_count FROM product_reviews WHERE product_id IN ($placeholders) GROUP BY product_id");
    if ($reviews_stmt && $reviews_stmt->execute($product_ids)) {
        while ($review = $reviews_stmt->fetch(\PDO::FETCH_ASSOC)) {
            $ratings[$review['product_id']] = ['avg' => $review['avg_rating'], 'count' => $review['review_count']];
        }
    }
}

// Get all reviews
$all_reviews_stmt = $conn->prepare("SELECT pr.product_id, pr.user_id, pr.name, pr.email, pr.comment, pr.rating, pr.created_at, p.product_name, p.product_image, u.user_avatar FROM product_reviews pr JOIN products p ON pr.product_id = p.product_id LEFT JOIN users u ON pr.user_id = u.user_id ORDER BY pr.created_at DESC LIMIT 20");
$all_reviews_stmt->execute();
$all_reviews = $all_reviews_stmt->fetchAll(\PDO::FETCH_ASSOC);

ob_start();
?>
                <h5 class="mb-4" style="color: #130864ff; font-weight: 700;">Filters</h5>

                <!-- Search Filter -->
                <div class="filter-section">
                  <div class="filter-title">Search</div>
                  <form method="GET" class="d-flex gap-2">
                    <input type="text" name="search" class="form-control form-control-sm" 
                           value="<?php echo htmlspecialchars($search_name, ENT_QUOTES, 'UTF-8'); ?>" 
                           placeholder="Search products...">
                    <button type="submit" class="btn btn-sm" style="background: #130864ff; color: white;">
                      <i class="fas fa-search"></i>
                    </button>
                  </form>
                </div>

                <!-- Subcategory Filter -->
                <div class="filter-section">
                  <div class="filter-title">Subcategory</div>
                  <?php foreach ($categories as $cat): ?>
                    <div class="filter-option">
                      <input type="checkbox" id="cat_<?php echo createSlug($cat['product_category']); ?>" 
                             value="<?php echo htmlspecialchars($cat['product_category'], ENT_QUOTES, 'UTF-8'); ?>"
                             <?php echo $selected_category === $cat['product_category'] ? 'checked' : ''; ?>
                             onchange="filterCategory(this)">
                      <label class="font-rubik" for="cat_<?php echo createSlug($cat['product_category']); ?>">
                        <?php echo htmlspecialchars($cat['product_category'], ENT_QUOTES, 'UTF-8'); ?>
                      </label>
                    </div>
                  <?php endforeach; ?>
                </div>

                <!-- Color Filter -->
                <div class="filter-section">
                  <div class="filter-title">Color</div>
                  <?php foreach ($colors as $color): 
                    $is_color_selected = in_array($color['product_color'], $selected_colors);
                  ?>
                    <div class="filter-option">
                      <input type="checkbox" id="color_<?php echo createSlug($color['product_color']); ?>" 
                             value="<?php echo htmlspecialchars($color['product_color'], ENT_QUOTES, 'UTF-8'); ?>"
                             <?php echo $is_color_selected ? 'checked' : ''; ?>
                             onchange="filterMultiple('colors')">
                      <label class="font-rubik" for="color_<?php echo createSlug($color['product_color']); ?>">
                        <?php echo htmlspecialchars($color['product_color'], ENT_QUOTES, 'UTF-8'); ?>
                      </label>
                    </div>
                  <?php endforeach; ?>
                </div>

                <!-- Variant Filter -->
                <div class="filter-section">
                  <div class="filter-title">Variant</div>
                  <?php foreach ($all_variants as $variant): 
                    $is_variant_selected = in_array($variant, $selected_variants);
                  ?>
                    <div class="filter-option">
                      <input type="checkbox" id="variant_<?php echo createSlug($variant); ?>" 
                             value="<?php echo htmlspecialchars($variant, ENT_QUOTES, 'UTF-8'); ?>"
                             <?php echo $is_variant_selected ? 'checked' : ''; ?>
                             onchange="filterMultiple('variants')">
                      <label class="font-rubik" for="variant_<?php echo createSlug($variant); ?>">
                        <?php echo htmlspecialchars($variant, ENT_QUOTES, 'UTF-8'); ?>
                      </label>
                    </div>
                  <?php endforeach; ?>
                </div>

                <!-- Price Filter -->
                <div class="filter-section">
                  <div class="filter-title">Price Range</div>
                  <div class="price-range-slider">
                    <div class="price-inputs">
                      <input type="number" id="min_price" placeholder="Min" 
                             value="<?php echo $min_price > 0 ? $min_price : ''; ?>" 
                             class="form-control form-control-sm">
                      <input type="number" id="max_price" placeholder="Max" 
                             value="<?php echo $max_price < 999999 ? $max_price : ''; ?>" 
                             class="form-control form-control-sm">
                    </div>
                    <button onclick="filterPrice()" class="btn btn-sm mt-2" style="background: #130864ff; color: white; width: 100%;">
                      Apply Price
                    </button>
                  </div>
                </div>

                <!-- Clear Filters -->
                <a href="<?php echo universalstore_URL; ?>/category/<?php echo createSlug($main_category_display); ?>" class="clear-filters">
                  <i class="fas fa-times"></i> Clear All Filters
                </a>
<?php
$filter_content = ob_get_clean();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo htmlspecialchars($main_category_display, ENT_QUOTES, 'UTF-8'); ?> - Universal Store</title>

  <!-- bootstrap CDN -->
  <link rel="stylesheet" href="<?php echo universalstore_URL; ?>/assets/includes/bootstrap-5.3.7-dist/css/bootstrap.min.css">

  <!-- Font Awesome CDN -->
  <link rel="stylesheet" href="<?php echo universalstore_URL; ?>/assets/includes/fontawesome-free-5.15.4-web/css/all.css">
  <link rel="stylesheet" href="<?php echo universalstore_URL; ?>/assets/includes/fontawesome/fontawesome-free-6.7.2-web/css/all.css">

  <!-- custom css file -->
  <link rel="stylesheet" href="<?php echo universalstore_URL; ?>/assets/css/style1.css">
  <link rel="stylesheet" href="<?php echo universalstore_URL; ?>/assets/css/category.css">
  <link href="https://ik.imagekit.io/univ35tore/universal_store/imgs/UNIVERSAL%20STORE.jpg" rel="icon">

  <!-- Internal CSS -->

</head>
<body>

  <div class="container">
    <div id="cart-notice-container" class="col-12 col-sm-6 col-md-12" style="position: fixed; top: 50px; left: 80%; transform: translateX(-50%); z-index: 1050; width: 100%; max-width: 350px;"></div>
  </div>
  <!-- Breadcrumb -->
<div aria-label="breadcrumb" class="bg-light py-2 mt-3">
  <div class="container">
    <ol class="breadcrumb mb-0">
      <li class="no-underline hover:underline mx-3"><a class="color-black font-weight-600" style="text-decoration:none;" href="<?php echo universalstore_URL; ?>/">Home </a></li> <Strong> <i class="fas fa-arrow-right"></i> </Strong>
      <li class="no-underline hover:underline mx-3"><a class="color-black font-weight-600" style="text-decoration:none;" href="<?php echo universalstore_URL; ?>/categories">Categories</a></li> <Strong> <i class="fas fa-arrow-right"></i> </Strong>
      <li class="no-underline hover:underline mx-3"><a class="color-black font-weight-600" style="text-decoration:none;" href="<?php echo universalstore_URL; ?>/category/<?php echo createSlug($main_category_display); ?>"><?php echo htmlspecialchars($main_category_display, ENT_QUOTES, 'UTF-8'); ?></a></li> 
      <?php if (!empty($selected_category)): ?>
       <Strong><i class="fas fa-arrow-right"></i> </Strong> <li class="breadcrumb-item active mx-3" aria-current="page"><?php echo htmlspecialchars($selected_category, ENT_QUOTES, 'UTF-8'); ?></li>
      <?php endif; ?>
    </ol>
  </div>
</div>
  <!-- Category Discovery Banner -->
<!-- Category Discovery Banner with Dual Actions -->
<section class="container my-5">
    <div class="category-cta-box d-flex flex-column p-4 p-md-5 overflow-hidden position-relative">
        
        <!-- Decorative Background Element (Optional) -->
        <div class="position-absolute end-0 top-0 opacity-10 d-none d-lg-block" style="transform: rotate(15deg) translate(20px, -20px);">
             <i class="fas fa-tablet-alt fa-10x"></i>
        </div>

        <div class="row align-items-center position-relative" style="z-index: 2;">
            <div class="col-lg-7 text-content text-center text-lg-start">
                <span class="badge mb-3 text-uppercase px-3 py-2">Pro Selection</span>
                <h2 class="display-5 fw-bold mb-3 text-dark">
                    Master your workflow with <?php echo htmlspecialchars($main_category_display, ENT_QUOTES, 'UTF-8'); ?>
                </h2>
                <p class="text-muted lead mb-4">
                    Explore the latest tech trends and premium performance units. Not sure which <strong><?php echo strtolower(htmlspecialchars($main_category_display, ENT_QUOTES, 'UTF-8')); ?></strong> fits your needs? We're here to help.
                </p>
            </div>
            
            <div class="col-lg-5">
                <div class="d-flex flex-column gap-3">
                    <!-- Primary Action: View All -->
                    <a href="<?php echo universalstore_URL; ?>/category/<?php echo strtolower(htmlspecialchars($main_category_display, ENT_QUOTES, 'UTF-8')); ?>?category=<?php foreach($categories as $cat) { echo $cat['product_url_slug']; } ?>&sort=newest&page=1" 
                       class="btn-primary-custom d-flex align-items-center justify-content-center">
                        <span class="me-auto">View Latest Arrivals</span>
                        <i class="fas fa-th-large"></i>
                    </a>

                    <!-- Secondary Action: The "Interesting Thing" (e.g., Accessories or Comparison) -->
                    <a href="<?php echo universalstore_URL; ?>/category/accessories?category=accessories" 
                       class="btn-secondary-custom d-flex align-items-center justify-content-center">
                        <span class="me-auto">Essential Accessories</span>
                        <i class="fas fa-headphones-alt"></i>
                    </a>
                </div>
                
                <p class="small text-center mt-3 text-muted">
                    <i class="fas fa-shipping-fast me-1"></i> Free delivery on orders over 50,000 XAF
                </p>
            </div>
        </div>
    </div>
</section>
 

  <!-- Main Content -->
  <div class="py-5">
    <div class="container">
      <div class="row">
        <!-- Sidebar Filters for Desktop -->
        <div class="col-lg-3 mb-4 d-none d-lg-block">
          <div class="sidebar">
            <?php echo $filter_content; ?>
          </div>
        </div>

        <!-- Offcanvas for Mobile -->
        <div class="offcanvas offcanvas-start" tabindex="-1" id="filterOffcanvas" aria-labelledby="filterOffcanvasLabel">
          <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="filterOffcanvasLabel">Filters</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
          </div>
          <div class="offcanvas-body p-0">
            <div class="sidebar">
              <?php echo $filter_content; ?>
            </div>
          </div>
        </div>

        <!-- Products Area -->
        <div class="col-lg-9">
          <!-- Mobile filter button -->
          <div class="d-lg-none mb-3">
            <button class="btn btn-outline-primary w-100 d-flex align-items-center justify-content-center" type="button" data-bs-toggle="offcanvas" data-bs-target="#filterOffcanvas" aria-controls="filterOffcanvas">
              <i class="fas fa-filter me-2"></i> Filters
            </button>
          </div>

          <!-- Sort Controls -->
          <div class="sort-controls">
            <div class="results-count">
              Showing <strong><?php echo $total_products > 0 ? (($offset) + 1) : 0; ?></strong> - 
              <strong><?php echo min($offset + $per_page, $total_products); ?></strong> 
              of <strong><?php echo $total_products; ?></strong> products
            </div>
            <select onchange="changeSort(this.value)" class="form-select form-select-sm" style="max-width: 200px;">
              <option value="newest" <?php echo $sort_by === 'newest' ? 'selected' : ''; ?>>Newest</option>
              <option value="price_low" <?php echo $sort_by === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
              <option value="price_high" <?php echo $sort_by === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
              <option value="name" <?php echo $sort_by === 'name' ? 'selected' : ''; ?>>Name: A to Z</option>
            </select>
          </div>

          <!-- Products Grid -->
          <?php if ($total_products > 0): ?>
            <div class="row g-4">
              <?php foreach($products as $row): 
                $variants = $row['product_variant'];
                $variantsArray = explode(',', $variants);
                $defaultVariant = count($variantsArray) > 0 ? trim($variantsArray[0]) : '';
                $product_id = htmlspecialchars($row['product_id'], ENT_QUOTES, 'UTF-8');
                $product_name = htmlspecialchars(createSlug($row['product_name']), ENT_QUOTES, 'UTF-8');
                $product_color = htmlspecialchars(createSlug($row['product_color']), ENT_QUOTES, 'UTF-8');
                $product_category = createSlug($row['product_category']);
                $rating_data = isset($ratings[$row['product_id']]) ? $ratings[$row['product_id']] : ['avg' => 3, 'count' => 300];
              ?>
                <div class="col-lg-4 col-md-6 col-sm-6 col-12">
                  <div class="product-card">
                    <!-- Product Image -->
                    <a href="<?php echo universalstore_URL; ?>/<?php echo $product_category;?>/<?php echo $product_id ;?>/<?php echo $product_name; ?>/<?php echo $product_color; ?>" 
                       style="text-decoration: none; color: inherit;">
                      <?php
                        $img_val = htmlspecialchars($row['product_image'], ENT_QUOTES, 'UTF-8');
                        $img_src = (stripos($img_val, 'http') === 0 || stripos($img_val, '//') === 0)
                            ? $img_val
                            : universalstore_URL . '/assets/imgs/' . $img_val;
                      ?>
                      <img src="<?php echo $img_src; ?>" 
                           alt="<?php echo htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8'); ?>" 
                           class="product-image">
                    </a>

                    <!-- Product Info -->
                    <div class="product-info">
                      <div class="product-category">
                        <?php echo htmlspecialchars($row['product_category'], ENT_QUOTES, 'UTF-8'); ?>
                      </div>

                      <h5 class="product-name">
                        <?php echo htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8'); ?>
                      </h5>

                      <div class="product-color">
                        <strong><?php echo htmlspecialchars($row['product_color'], ENT_QUOTES, 'UTF-8'); ?></strong>
                      </div>

                      <div class="product-variant" style="font-size: 12px; color: #666; margin-bottom: 8px;">
                        <span style="background: #f0f0f0; padding: 2px 6px; border-radius: 3px; display: inline-block;">
                          <?php echo htmlspecialchars($defaultVariant, ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                      </div>

                      <div class="product-rating">
                        <?php echo generateStars($rating_data['avg'], $rating_data['count']); ?>    
                    </div>

                      <div class="product-price">
                        <?php echo htmlspecialchars($row['product_price'], ENT_QUOTES, 'UTF-8'); ?> FCFA
                      </div>

                      <button class="add-to-cart-btn add-to-cart-btn-category"
                              data-product-csrf-token="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8');?>"
                              data-product-id="<?php echo htmlspecialchars($row['product_id'], ENT_QUOTES, 'UTF-8'); ?>"
                              data-product-name="<?php echo htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8'); ?>"
                              data-product-price="<?php echo htmlspecialchars($row['product_price'], ENT_QUOTES, 'UTF-8'); ?>"
                              data-product-color="<?php echo htmlspecialchars($row['product_color'], ENT_QUOTES, 'UTF-8'); ?>"
                              data-product-variant="<?php echo htmlspecialchars($defaultVariant, ENT_QUOTES, 'UTF-8') ?>"
                              data-product-image="<?php echo htmlspecialchars($row['product_image'], ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fas fa-shopping-cart"></i> Add to Cart
                      </button>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
              <div aria-label="Page navigation" class="mt-5">
                <ul class="pagination">
                  <!-- Previous -->
                  <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                      <i class="fas fa-chevron-left"></i> Previous
                    </a>
                  </li>

                  <!-- Page Numbers -->
                  <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1): ?>
                      <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>"><strong>1</strong></a>
                      </li>
                      <?php if ($start_page > 2): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                      <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                      <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                          <?php echo $i; ?>
                        </a>
                      </li>
                    <?php endfor; ?>

                    <?php if ($end_page < $total_pages): ?>
                      <?php if ($end_page < $total_pages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                      <?php endif; ?>
                      <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>">
                          <?php echo $total_pages; ?>
                        </a>
                      </li>
                    <?php endif; ?>

                  <!-- Next -->
                  <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                      Next <i class="fas fa-chevron-right"></i>
                    </a>
                  </li>
                </ul>
              </div>
            <?php endif; ?>

          <?php else: ?>
            <!-- No Products Message -->
            <div class="no-products">
              <i class="fas fa-inbox"></i>
              <h4>No products found</h4>
              <p>Try adjusting your filters or search terms</p>
              <a href="<?php echo universalstore_URL; ?>/category/<?php echo createSlug($main_category_display); ?>" class="btn" style="background: #130864ff; color: white; margin-top: 20px;">
                <i class="fas fa-redo"></i> Reset Filters
              </a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
<!-- Products Header -->
<div class="container mx-auto mt-12 px-4">
  <div class="mb-4 text-end">
        <a href="javascript:history.back()" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8 md:p-12 text-center max-w-4xl mx-auto">
        <!-- Category Badge -->
        <span class="inline-block px-4 py-1.5 mb-6 text-xs font-bold tracking-widest text-red-600 uppercase bg-red-50 rounded-full">
            Collections
        </span>
        
        <!-- Main Title -->
        <h1 class="text-4xl md:text-5xl font-extrabold text-gray-900 mb-6 tracking-tight">
            <?php echo htmlspecialchars($main_category_display, ENT_QUOTES, 'UTF-8'); ?>
        </h1>
        
        <!-- Description / Subtitle -->
        <div class="text-gray-600 leading-relaxed text-lg space-y-4">
            <p>
                Explore all products in the <span class="font-semibold text-gray-900"><?php echo strtolower(htmlspecialchars($main_category_display, ENT_QUOTES, 'UTF-8')); ?></span> category and discover a curated selection designed to elevate your daily experience. 
                Whether you are searching for a professional-grade solution to streamline your workflow, a high-performance upgrade for your personal projects, or a thoughtful gift that combines utility with modern aesthetics, 
                this collection represents the intersection of innovation and reliability.
            </p>
            
            <p class="hidden md:block">
                Every item in this category has been hand-selected to meet our rigorous standards for durability and functionality, ensuring that your investment today continues to deliver value for years to come. 
                From the latest industry-leading trends to the timeless essentials that form the backbone of your toolkit, we provide the variety you need to make an informed choice.
            </p>
            
            <div class="pt-4 border-t border-gray-100 mt-6">
                <p class="italic text-gray-500">
                    Don't settle for the ordinary when you can equip yourself with the extraordinary—start your journey through the <?php echo strtolower(htmlspecialchars($main_category_display, ENT_QUOTES, 'UTF-8')); ?> category now and see how the right choice can transform the way you work, play, and live.
                </p>
            </div>
        </div>
    </div>
</div>
  <!-- Scripts -->
  <script src="<?php echo universalstore_URL; ?>/assets/includes/bootstrap-5.3.7-dist/js/bootstrap.bundle.min.js"></script>

  <script>
    const mainCategory = '<?php echo htmlspecialchars($main_category_display, ENT_QUOTES, 'UTF-8'); ?>';

    // Update CSRF token
    let currentCsrfToken = '<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>';

    function updateCsrfToken(newToken) {
        if (newToken) {
            currentCsrfToken = newToken;
            document.querySelectorAll('.add-to-cart-btn-category').forEach(button => {
                button.setAttribute('data-product-csrf-token', newToken);
            });
        }
    }

    function checkEmptyCsrfToken(token) {
        if (!token) {
            showCartNotice('Session Expired. Please refresh page.', true);
            return false;
        }
        return true;
    }

    function checkNewCsrfToken(newToken, oldToken) {
        if (newToken && newToken === oldToken) {
            showCartNotice('Something happened while adding item to Cart. Please refresh page.', true);
            return false;
        }
        return true;
    }

    // Show cart notice
    function showCartNotice(product_image, message, product_color, product_variant, product_price, isError = false) {
        const container = document.getElementById('cart-notice-container');
        if (!container) return;

        const alert = document.createElement('div');
        alert.className = `alert ${isError ? 'alert-danger' : 'bg-light'} alert-dismissible fade show`;
        alert.role = 'alert';
        
        let alertHTML = `<h3 class="font-rubik font-weight-800">${isError ? 'Error' : ' ✔ Added To Cart'}</h3>`;
        
        if (!isError) {
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
                <a href="<?php echo htmlspecialchars(CART_URL, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-lg btn-primary">View Cart</a>
            </div>`;
        } else {
            alertHTML += `<div class="font-rubik font-weight-600" style="margin:10px 0;">${message}</div>`;
        }
        
        alertHTML += `<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>`;
        alert.innerHTML = alertHTML;
        
        container.innerHTML = '';
        container.appendChild(alert);

        const timeout = isError ? 7000 : 5000;
        setTimeout(() => {
            if (container.contains(alert)) {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                if (bsAlert) {
                    bsAlert.close();
                }
            }
        }, timeout);
    }

    // Update cart count
    function updateCartCount(newCount) {
        const cartLink = document.querySelector('a.cart');
        if (!cartLink) return;

        let countSpan = cartLink.querySelector('.cart-item');
        
        if (newCount > 0) {
            if (countSpan) {
                countSpan.firstChild.textContent = newCount;
            } else {
                countSpan = document.createElement('span');
                countSpan.className = 'cart-item';
                countSpan.style.fontSize = '0.75rem';
                countSpan.innerHTML = `${newCount}<span class="visually-hidden">Items in cart</span>`;
                cartLink.appendChild(countSpan);
            }
        } else {
            if (countSpan) {
                countSpan.remove();
            }
        }
    }

    // Add to cart handler
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.add-to-cart-btn-category').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const clickedButton = e.currentTarget;
                const csrfToken = clickedButton.getAttribute('data-product-csrf-token');

                if (!checkEmptyCsrfToken(csrfToken)) return;

                const btn = this;
                const originalText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';

                const formData = new FormData();
                formData.append('product_id', btn.dataset.productId);
                formData.append('product_name', btn.dataset.productName);
                formData.append('product_price', btn.dataset.productPrice);
                formData.append('product_image', btn.dataset.productImage);
                formData.append('product_color', btn.dataset.productColor);
                formData.append('product_variant', btn.dataset.productVariant);
                formData.append('product_quantity', 1);
                formData.append('add-to-cart', '1');
                formData.append('ajax', '1');

                const CART_URL = '<?php echo htmlspecialchars(CART_URL, ENT_QUOTES, 'UTF-8'); ?>';
                fetch(CART_URL, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': csrfToken
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (!checkNewCsrfToken(data.csrf_token, csrfToken)) return;
                    updateCsrfToken(data.csrf_token);
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    const image_url = '<?php echo ASSETS_URL . "/imgs/"; ?>';
                    const image = (data.product_image && (data.product_image.startsWith('http') || data.product_image.startsWith('//'))) ? data.product_image : image_url + data.product_image;
                    showCartNotice(image, data.message, data.product_color, data.product_variant, data.product_price, !data.success);
                    if (data.success && typeof data.items !== 'undefined') {
                        updateCartCount(data.items);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                    showCartNotice('', 'An error occurred. Please try again.', '', '', '', true);
                });
            });
        });
    });

    // Filter functions
    function filterCategory(checkbox) {
        const category = checkbox.value;
        if (checkbox.checked) {
            window.location.href = `?category=${encodeURIComponent(category)}`;
        } else {
            window.location.href = window.location.pathname;
        }
    }

    function filterMultiple(filterType) {
        const checkboxes = document.querySelectorAll(`input[type="checkbox"][id^="${filterType === 'colors' ? 'color' : 'variant'}"]`);
        const selectedValues = Array.from(checkboxes)
            .filter(cb => cb.checked)
            .map(cb => cb.value);

        console.log(`Filter type: ${filterType}, Selected values:`, selectedValues);

        const params = new URLSearchParams(window.location.search);
        
        // Clear existing filters of this type
        const keysToDelete = [];
        for (let key of params.keys()) {
            if (filterType === 'colors' && (key === 'colors' || key === 'colors[]')) {
                keysToDelete.push(key);
            } else if (filterType === 'variants' && (key === 'variants' || key === 'variants[]')) {
                keysToDelete.push(key);
            }
        }
        keysToDelete.forEach(key => {
            while (params.has(key)) {
                params.delete(key);
            }
        });
        
        // Reset to page 1
        params.set('page', '1');
        
        // Build URL with new values
        let url = window.location.pathname + '?';
        
        // Add existing parameters first
        for (let [key, value] of params.entries()) {
            if (key !== 'page') {
                url += `${encodeURIComponent(key)}=${encodeURIComponent(value)}&`;
            }
        }
        
        // Add selected filter values
        selectedValues.forEach(val => {
            url += `${filterType}[]=${encodeURIComponent(val)}&`;
        });
        
        // Add page parameter
        url += 'page=1';
        
        // Remove trailing & if present
        url = url.replace(/&$/, '');
        
        console.log(`Redirecting to: ${url}`);
        window.location.href = url || window.location.pathname;
    }

    function filterPrice() {
        const minPrice = document.getElementById('min_price').value;
        const maxPrice = document.getElementById('max_price').value;
        
        const colorCheckboxes = document.querySelectorAll('input[type="checkbox"][id^="color"]');
        const selectedColors = Array.from(colorCheckboxes)
            .filter(cb => cb.checked)
            .map(cb => cb.value);
        
        const variantCheckboxes = document.querySelectorAll('input[type="checkbox"][id^="variant"]');
        const selectedVariants = Array.from(variantCheckboxes)
            .filter(cb => cb.checked)
            .map(cb => cb.value);

        let url = window.location.pathname + '?';
        if (minPrice) url += 'min_price=' + encodeURIComponent(minPrice) + '&';
        if (maxPrice) url += 'max_price=' + encodeURIComponent(maxPrice) + '&';
        
        selectedColors.forEach(color => {
            url += 'colors[]=' + encodeURIComponent(color) + '&';
        });
        
        selectedVariants.forEach(variant => {
            url += 'variants[]=' + encodeURIComponent(variant) + '&';
        });
        
        url += 'page=1';
        url = url.replace(/&$/, '');
        window.location.href = url || window.location.pathname;
    }

    function changeSort(sortValue) {
        const params = new URLSearchParams(window.location.search);
        params.set('sort', sortValue);
        params.set('page', '1');
        window.location.href = window.location.pathname + `?${params.toString()}`;
    }
  </script>
  
  <!-- Product Blog Section -->
  <div class="container-fluid py-5" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
    <div class="container">
      <div class="text-center mb-5">
        <h2 class="display-4 font-weight-bold" style="color: #130864ff;">Product Blog</h2>
        <p class="lead text-muted">Discover our latest products and insights</p>
      </div>
      
      <div class="row g-4">
        <?php 
        $blog_products = array_slice($products, 0, 6); // Show first 6 products
        foreach($blog_products as $row): 
          $variants = $row['product_variant'];
          $variantsArray = explode(',', $variants);
          $defaultVariant = count($variantsArray) > 0 ? trim($variantsArray[0]) : '';
          $product_id = htmlspecialchars($row['product_id'], ENT_QUOTES, 'UTF-8');
          $product_name = htmlspecialchars(createSlug($row['product_name']), ENT_QUOTES, 'UTF-8');
          $product_color = htmlspecialchars(createSlug($row['product_color']), ENT_QUOTES, 'UTF-8');
          $product_category = createSlug($row['product_category']);
          $rating_data = isset($ratings[$row['product_id']]) ? $ratings[$row['product_id']] : ['avg' => 3, 'count' => 300];
        ?>
          <div class="col-lg-4 col-md-6 col-sm-12">
            <div class="card h-100 shadow-md border-0 card-blog hover:shadow-md" style="border-radius: 15px; overflow: hidden; transition: transform 0.5s ease-in-out;">
              <div class="position-relative">
                <a href="<?php echo universalstore_URL; ?>/<?php echo $product_category;?>/<?php echo $product_id ;?>/<?php echo $product_name; ?>/<?php echo $product_color; ?>" 
                   style="text-decoration: none; color: inherit;">
                  <?php
                    $img_val = htmlspecialchars($row['product_image'], ENT_QUOTES, 'UTF-8');
                    $img_src = (stripos($img_val, 'http') === 0 || stripos($img_val, '//') === 0)
                        ? $img_val
                        : universalstore_URL . '/assets/imgs/' . $img_val;
                  ?>
                  <img src="<?php echo $img_src; ?>" 
                       alt="<?php echo htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8'); ?>" 
                       class="card-img-top card-blog-img" style="height: 250px; object-fit: cover; transition: transform 0.5s ease-in-out;">
                </a>
                <div class="position-absolute top-0 end-0 m-3">
                  <span class="badge bg-primary"><?php echo htmlspecialchars($row['product_category'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
              </div>
              
              <div class="card-body d-flex flex-column">
                <h5 class="card-title font-weight-bold" style="color: #130864ff;">
                  <?php echo htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8'); ?>
                </h5>
                
                <div class="mb-2">
                  <strong>Color:</strong> <?php echo htmlspecialchars($row['product_color'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
                
                <div class="mb-2">
                  <span style="background: #f0f0f0; padding: 2px 6px; border-radius: 3px; display: inline-block; font-size: 12px;">
                    <?php echo htmlspecialchars($defaultVariant, ENT_QUOTES, 'UTF-8'); ?>
                  </span>
                </div>
                
                <div class="mb-3">
                  <?php echo generateStars($rating_data['avg'], $rating_data['count']); ?>
                </div>
                
                <div class="mt-auto">
                  <div class="d-flex justify-content-between align-items-center">
                    <span class="h4 font-weight-bold" style="color: #130864ff;">
                      <?php echo htmlspecialchars($row['product_price'], ENT_QUOTES, 'UTF-8'); ?> FCFA
                    </span>
                    <button class="btn btn-primary btn-sm" style="border-radius: 20px;">
                      <i class="fas fa-shopping-cart"></i> View
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      
      <div class="text-center mt-4">
        <a href="<?php echo universalstore_URL; ?>/shop" class="btn btn-lg" style="background: #130864ff; color: white; border-radius: 25px; padding: 12px 30px;">
          <i class="fas fa-arrow-right"></i> Explore More Products
        </a>
      </div>
    </div>
  </div>
  
  <!-- Reviews Section -->
  <div class="container-fluid py-5" style="background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);">
    <div class="container">
      <div class="text-center mb-5">
        <h2 class="display-4 font-weight-bold" style="color: #130864ff;">Customer Reviews</h2>
        <p class="lead text-muted">What our customers are saying</p>
      </div>
      
      <div class="row g-4">
        <?php foreach($all_reviews as $review): ?>
          <div class="col-lg-6 col-md-6 col-sm-12">
            <div class="card h-100 shadow-sm border-0 card-review" style="border-radius: 15px; overflow: hidden; transition: transform 0.5s ease-in-out;">
              <div class="card-body">
                <div class="text-center mb-3">
                  <?php
                  $img_val = htmlspecialchars($review['product_image'], ENT_QUOTES, 'UTF-8');
                  $img_src = (stripos($img_val, 'http') === 0 || stripos($img_val, '//') === 0)
                      ? $img_val
                      : universalstore_URL . '/assets/imgs/' . $img_val;
                  ?>
                  <img src="<?php echo $img_src; ?>" alt="Product" class="img-fluid rounded" style="max-height: 100px; object-fit: cover;">
                </div>
                <div class="d-flex align-items-center mb-3">
                  <?php
                    $avatar_src = !empty($review['user_avatar']) ? htmlspecialchars($review['user_avatar'], ENT_QUOTES, 'UTF-8') : ASSETS_URL . '/imgs/default-avatar.png';
                  ?>
                  <img src="<?php echo $avatar_src; ?>" alt="User Avatar" class="rounded-circle me-3" style="width: 50px; height: 50px; object-fit: cover;">
                  <div>
                    <h6 class="mb-0 color-black"><?php echo htmlspecialchars($review['name'], ENT_QUOTES, 'UTF-8'); ?></h6>
                    <small class="text-muted"><?php echo generateStars($review['rating'], 1); ?></small>
                  </div>
                </div>
                <div class="mb-3">
                  <strong><?php echo htmlspecialchars($review['product_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                </div>
                <p class="card-text"><?php echo htmlspecialchars($review['comment'], ENT_QUOTES, 'UTF-8'); ?></p>
                <small class="text-muted">Published on <strong><?php echo htmlspecialchars(date('F j, Y', strtotime($review['created_at'])), ENT_QUOTES, 'UTF-8'); ?></strong></small>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  
  <?php require_once TEMPLATES_PATH . '/footer.php'; ?>
</body>
</html>
