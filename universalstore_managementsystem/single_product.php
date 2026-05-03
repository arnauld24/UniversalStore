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

use external_server\Connect;
use core\OptimizedTracking;
// Autoload and secure session helper (registers autoloader and starts session)
require_once SERVER_PATH . '/secure_session_csrf_cookie.php';
// Require optimized tracking
require_once __DIR__ . '/../core/OptimizedTracking.php';


// Instantiate DB connection via autoloaded Connect class
$conn = new Connect();

// Initialize OptimizedTracking for analytics
OptimizedTracking::init();


if(isset($_GET['product_id']) && !empty($_GET['product_id']) && isset($_GET['category']) && !empty($_GET['category']) ){

    // Validate and sanitize product_id
  $product_id = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
  $category = htmlspecialchars(trim($_GET['category'] ?? ''),ENT_QUOTES, 'UTF-8'); 

  if($product_id === false || $product_id === null || $category === false || $category === null){
      header("Location: " . INDEX_URL);
      exit();
  }
  
  $stmt = $conn->prepare("SELECT * FROM products WHERE product_id = :product_id ");
  $stmt->bindParam(":product_id", $product_id, PDO::PARAM_INT);
  $stmt->execute();
  
  if($stmt->rowCount() == 0){
      header("Location: " . INDEX_URL);
      exit();
  }
  
  // Track product view
  OptimizedTracking::trackProductView($product_id, '', $category, 0, $authenticatedUserId ?? null);
} else {
    // If no product_id is given
    header("Location: " . INDEX_URL);
    exit();
}

// creeate slug for clean URL display
function createSlug($string){
  $string = strtolower($string); // Convert to lowercase
  $string = preg_replace('/[^a-z0-9]+/i', '-', $string); // Replace non-alphanumeric characters with hyphens
  $string = preg_replace('/^-+|-+$/', '', $string); // Remove leading and trailing hyphens
  return rtrim($string, '-'); // Remove any trailing hyphens
}

// --- Product review handling ---
$review_notice = null;
$review_error = false;
$reviews = [];
$reviewStats = ['average' => 0, 'count' => 0];

if (isset($product_id)) {
    // Load review stats
    $statsStmt = $conn->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS review_count FROM product_reviews WHERE product_id = :product_id");
    $statsStmt->execute([':product_id' => $product_id]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    if ($stats) {
        $reviewStats['average'] = $stats['avg_rating'] ? round((float)$stats['avg_rating'], 1) : 0;
        $reviewStats['count'] = (int)($stats['review_count'] ?? 0);
    }

    $reviewsStmt = $conn->prepare("SELECT pr.*, u.user_avatar FROM product_reviews pr LEFT JOIN users u ON pr.user_id = u.user_id WHERE pr.product_id = :product_id ORDER BY pr.created_at DESC");
    $reviewsStmt->execute([':product_id' => $product_id]);
    $reviews = $reviewsStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Detect AJAX requests (XHR or explicit ajax flag)
function is_ajax_request(): bool {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return true;
    }
    if (!empty($_REQUEST['ajax']) && (string)$_REQUEST['ajax'] === '1') {
        return true;
    }
    return false;
}

function json_response(array $data): void {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}


// Star rating function 
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

// Handle review form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_review'])) {
    $csrf_token = trim($_POST['csrf_token'] ?? '');
    if (!validateCsrfToken($csrf_token)) {
        $review_notice = ['success' => false, 'message' => 'Invalid CSRF token. Please refresh and try again.'];
        $review_error = true;
        if (is_ajax_request()) {
            json_response(['success' => false, 'message' => $review_notice['message'], 'csrf_token' => htmlspecialchars(getCsrfToken())]);
        }
    } else {
        $input_product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
        $rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT, ["options" => ["min_range" => 1, "max_range" => 5]]);
        $comment = trim($_POST['review_comment'] ?? '');
        $name = trim($_POST['review_name'] ?? '');
        $email = trim($_POST['review_email'] ?? '');

        if (!$input_product_id || $input_product_id !== $product_id) {
            $review_notice = ['success' => false, 'message' => 'Invalid product.'];
            $review_error = true;
        } elseif (!$rating || $rating < 1 || $rating > 5) {
            $review_notice = ['success' => false, 'message' => 'Please select a rating between 1 and 5.'];
            $review_error = true;
        } elseif (empty($comment)) {
            $review_notice = ['success' => false, 'message' => 'Please write a review comment.'];
            $review_error = true;
        } elseif (empty($name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $review_notice = ['success' => false, 'message' => 'Please provide a valid name and email.'];
            $review_error = true;
        } else {
            // Limit lengths
            $name = htmlspecialchars(substr($name, 0, 100), ENT_QUOTES, 'UTF-8');
            $email = htmlspecialchars(substr($email, 0, 150), ENT_QUOTES, 'UTF-8');
            $comment = htmlspecialchars(substr($comment, 0, 1000), ENT_QUOTES, 'UTF-8');
            $user_id = !empty($authenticatedUserId) ? $authenticatedUserId : null;

            if(!empty($user_id)){
            $insert = $conn->prepare("INSERT INTO product_reviews (product_id, user_id, name, email, comment, rating, created_at) VALUES (:product_id, :user_id, :name, :email, :comment, :rating, NOW())");
            $insert->bindValue(':product_id', $product_id, PDO::PARAM_INT);
            if ($user_id === null) {
                $insert->bindValue(':user_id', null, PDO::PARAM_NULL);
            } else {
                $insert->bindValue(':user_id', $user_id, PDO::PARAM_INT);
            }
            $insert->bindValue(':name', $name, PDO::PARAM_STR);
            $insert->bindValue(':email', $email, PDO::PARAM_STR);
            $insert->bindValue(':comment', $comment, PDO::PARAM_STR);
            $insert->bindValue(':rating', $rating, PDO::PARAM_INT);

            if ($insert->execute()) {
                // Rotate CSRF token after successful submission
                rotateCsrfToken();

                // Fetch new stats
                $statsStmt = $conn->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS review_count FROM product_reviews WHERE product_id = :product_id");
                $statsStmt->execute([':product_id' => $product_id]);
                $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
                $newAvg = $stats['avg_rating'] ? round((float)$stats['avg_rating'], 1) : 0;
                $newCount = (int)($stats['review_count'] ?? 0);

                $newReview = [
                    'name' => $name,
                    'email' => $email,
                    'comment' => $comment,
                    'rating' => $rating,
                    'created_at' => date('Y-m-d H:i:s'),
                    'user_avatar' => $user_data['user_avatar'] ?? ''
                ];

                if (is_ajax_request()) {
                    json_response([
                        'success' => true,
                        'message' => 'Thank you! Your review has been submitted.',
                        'review' => $newReview,
                        'reviewStats' => ['average' => $newAvg, 'count' => $newCount],
                        'csrf_token' => htmlspecialchars(getCsrfToken())
                    ]);
                }

                // Redirect to prevent form resubmission
                $_SESSION['review_notice'] = ['success' => true, 'message' => 'Thank you! Your review has been submitted.'];
                header('Location: ' . $_SERVER['REQUEST_URI'] . '#product-reviews');
                exit;
            } else {
                $review_notice = ['success' => false, 'message' => 'Unable to save your review at this time.'];
                $review_error = true;
            }
            }else{
               if (is_ajax_request()) {
                $redirect_url = AUTHENTICATION_URL;
                    json_response([
                        'success' => false,
                        'message' => 'Only Verified users can submit reviews. Please Login...',
                        'csrf_token' => htmlspecialchars(getCsrfToken()),
                        
                    ]);
                }
            }
        }

        if (is_ajax_request() && $review_error) {
            json_response(['success' => false, 'message' => $review_notice['message'], 'csrf_token' => htmlspecialchars(getCsrfToken())]);
        }
    }
}

// Load flash message if set
if (isset($_SESSION['review_notice'])) {
    $review_notice = $_SESSION['review_notice'];
    unset($_SESSION['review_notice']);
}

// Ensure $user_data is defined (may not exist for guests)
if (!isset($user_data) || !is_array($user_data)) {
    $user_data = [];
}

//require navbar at the end in other to avoid header issues
require_once TEMPLATES_PATH. '/navbar.php';
?>
 <?php while($row = $stmt->fetch(PDO::FETCH_ASSOC)){ ?>

<?php
// Track product view
// OptimizedTracking::trackProductView($row['product_id'], $row['product_name'], $category, $row['product_price'], $authenticatedUserId ?? null);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo isset($row['product_name']) ? htmlspecialchars($row['product_name'], ENT_QUOTES, 'UTF-8') :""; ?> - Universal Store</title>

  <!-- bootstrap CDN -->
  <link rel="stylesheet" href="<?php echo universalstore_URL; ?>/assets/includes/bootstrap-5.3.7-dist/css/bootstrap.min.css">

  <!-- Font Awesome CDN -->
  <link rel="stylesheet" href="<?php echo universalstore_URL; ?>/assets/includes/fontawesome-free-5.15.4-web/css/all.css">
  <link rel="stylesheet" href="<?php echo universalstore_URL; ?>/assets/includes/fontawesome/fontawesome-free-6.7.2-web/css/all.css">

  <!-- custom css file -->
  <link rel="stylesheet" href="<?php echo universalstore_URL; ?>/assets/css/style1.css">
  <link rel="stylesheet" href="<?php echo universalstore_URL; ?>/assets/css/tailwindcss.css">
  <link href="https://ik.imagekit.io/univ35tore/universal_store/imgs/UNIVERSAL%20STORE.jpg" rel="icon">
  
</head>
<body style=" background: #f6f8ff6e; color: #1e2343; ">
  <section id="banner4">
<div class="container py-5 px-5 mt-0">
      <h1 class="color-white1 font-rubik font-size-60 text-center py-5">Products Details</h1>
</div>
  </section>

<!-- Add a container where the "Product added" message will appear (after banner) -->
<div id="cartAlertContainer" class="container mt-3"></div>

  <!-- Single Product -->

<section class="single-product my-5 pt-0 m-2">
<div class="row mt-0">
  <div class="mb-4 text-start px-5 mx-2">
    <a href="javascript:history.back()" class="btn btn-sm btn-outline-primary"> <i class="fas fa-arrow-left"></i> Back</a>
  </div>
  
<div class="container col-lg-5 col-md-12 col-sm-12 product-img bg-card-bg bg-white shadow-md border border-gray-100 rounded-xl">
  <?php
    $img1 = htmlspecialchars($row['product_image'] ?? '', ENT_QUOTES, 'UTF-8');
    $img2 = htmlspecialchars($row['product_image2'] ?? '', ENT_QUOTES, 'UTF-8');
    $img3 = htmlspecialchars($row['product_image3'] ?? '', ENT_QUOTES, 'UTF-8');
    $img4 = htmlspecialchars($row['product_image4'] ?? '', ENT_QUOTES, 'UTF-8');

    $img1_src = (stripos($img1, 'http') === 0 || stripos($img1, '//') === 0) ? $img1 : universalstore_URL . '/assets/imgs/' . $img1;
    $img2_src = ($img2 && (stripos($img2, 'http') === 0 || stripos($img2, '//') === 0)) ? $img2 : ($img2 ? universalstore_URL . '/assets/imgs/' . $img2 : '');
    $img3_src = ($img3 && (stripos($img3, 'http') === 0 || stripos($img3, '//') === 0)) ? $img3 : ($img3 ? universalstore_URL . '/assets/imgs/' . $img3 : '');
    $img4_src = ($img4 && (stripos($img4, 'http') === 0 || stripos($img4, '//') === 0)) ? $img4 : ($img4 ? universalstore_URL . '/assets/imgs/' . $img4 : '');
  ?>
  <div class="mb-3" id="imageZoom" style="--url: url('<?php echo $img1_src; ?>'); --zoom-x:0%; --zoom-y:0%; --display:none">
    <img class="img-fluid img pb-1" src="<?php echo $img1_src; ?>" id="mainImg">
  </div>
  <div class="small-img-group">
    <div class="small-img-col">
      <img src="<?php echo $img1_src; ?>" width="100%" class="small-img">
    </div>
    <div class="small-img-col">
      <?php if ($img2_src): ?><img src="<?php echo $img2_src; ?>" width="100%" class="small-img"><?php endif; ?>
    </div>
    <div class="small-img-col">
      <?php if ($img3_src): ?><img src="<?php echo $img3_src; ?>" width="100%" class="small-img"><?php endif; ?>
    </div>
    <div class="small-img-col">
      <?php if ($img4_src): ?><img src="<?php echo $img4_src; ?>" width="100%" class="small-img"><?php endif; ?>
    </div>
  </div>
</div>

<div class="container bg-card-bg border rounded-xl bg-white shadow-md border-gray-100 col-lg-6 col-md-12 col-sm-12 single-content">
  <h6 class="mb-3"><span class="color-dark mb-3">Category /</span> <?php echo isset($row['product_category']) ? htmlspecialchars($row['product_category'],ENT_QUOTES, 'UTF-8') :""; ?></h6>
  
  <h1 class="py-4"><?php echo isset($row['product_name']) ? htmlspecialchars($row['product_name'],ENT_QUOTES, 'UTF-8') :""; ?></h1>
  <h5>Product ID: <?php echo isset($row['product_id']) ? (int)$row['product_id']: 0; ?></h5>
  <h4><?php echo isset($row['product_price']) ? (int)$row['product_price']: 0; ?> FCFA</h4>
  <h5 class="font-rubik font-weight-600 py-1"> Color: <?php echo isset($row['product_color']) ? htmlspecialchars($row['product_color'],ENT_QUOTES, 'UTF-8') :""; ?></h5>
  <select id="variant" name="product_variant"  class="border border-gray-700 py-2 px-4 rounded-xl outline-none shadow-sm cursor-pointer"> 
    <!-- <option value="" disabled selected>-- Please Select an option --</option>  -->
    <?php 
     //  Get the string from database row
     $variantString = isset($row['product_variant']) ? $row['product_variant'] : '';
     if(!empty($variantString)){
      // split the string by comma to get individual variants
      $variants = explode(',', $variantString);
      // Loop through each variant and create a single option element
      foreach ($variants as $item) {
        // Trim to remove spaces if there are any
        $cleanItem = trim($item);
        ?>
    <option value="<?php echo htmlspecialchars($cleanItem, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($cleanItem, ENT_QUOTES, 'UTF-8'); ?></option>
  <?php
  }
     } 
    ?> 
  </select>
   <form id="addToCartForm" method="post" action="<?php echo universalstore_URL; ?>/cart">
<input type="hidden" name="csrf_token" id="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>" />
<input type="hidden" name="product_id"    value="<?php echo isset($row['product_id']) ? (int)$row['product_id']: 0; ?>" />
<input type="hidden" name="product_image" value="<?php echo htmlspecialchars($row['product_image'], ENT_QUOTES, 'UTF-8'); ?>" />
<input type="hidden" name="product_name"  value="<?php echo isset($row['product_name']) ? htmlspecialchars($row['product_name'],ENT_QUOTES, 'UTF-8') :""; ?>" />
<input type="hidden" name="product_price" value="<?php echo isset($row['product_price']) ? (int)$row['product_price']: 0; ?>" />
<input type="hidden" name="product_color" value="<?php echo isset($row['product_color']) ? htmlspecialchars($row['product_color'],ENT_QUOTES, 'UTF-8') :""; ?>" />
<input type="hidden" name="product_category" value="<?php echo isset($row['product_category']) ? htmlspecialchars($row['product_category'],ENT_QUOTES, 'UTF-8') :""; ?>" />
<input type="hidden" name="product_quantity" id="product_quantity" value="1" />

  <h4 class="mt-5 mb-5 font-weight-800">Product Details</h4>
  <span class="font-rubik font-size-20"><?php echo isset($row['product_description']) ? htmlspecialchars($row['product_description'],ENT_QUOTES, 'UTF-8') :""; ?></span><br><br><br>
  <hr>
  <div class="content mx-3">
  <div class="number-input">
    <span class="decrement" onclick="decrement()">-</span>
    <input type="number" id="inputNumber" value="1" name="product_quantity" min="1" max="100" />
    <span class="increment" onclick="increment()">+</span>
</div>

  <button class="buy-btn mb-5" type="submit" name="add-to-cart">Add To Cart</button>
  </div>
  <hr>
</form>
</div>

  
</div>
</section>

<!--   Product  Reviews  -->

<section id="product-reviews" class="my-5">
  <div class="container">
    <hr class="mx-auto" style="width:100%; height: 0.5px; background-color: #ccc; border: none;"><br>
    <h2 class="text-center">Product Reviews</h2>
      <hr class="mx-auto">
    <div id="reviewNoticeContainer">
      <?php if (!empty($review_notice)): ?>
        <div class="alert <?php echo $review_notice['success'] ? 'alert-success' : 'alert-danger'; ?> mt-4">
          <?php echo htmlspecialchars($review_notice['message'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="row mt-4">
      <div class="col-md-4">
        <div id="reviewStats" class="border rounded-xl p-3 mb-4 bg-card-bg shadow-md bg-white border-gray-100">
          <h5>Average Rating</h5>
          <div class="d-flex align-items-center">
            <div class="me-2">
              <?php
              $avgRating = $reviewStats['average'] ?? 0;
              $fullStars = (int) floor($avgRating);
              $halfStar = ($avgRating - $fullStars) >= 0.5;
              for ($i = 0; $i < 5; $i++) {
                  if ($i < $fullStars) {
                      echo '<i class="fas fa-star" style="color:#f7c04a"></i>';
                  } elseif ($i === $fullStars && $halfStar) {
                      echo '<i class="fas fa-star-half-alt" style="color:#f7c04a"></i>';
                  } else {
                      echo '<i class="far fa-star" style="color:#f7c04a"></i>';
                  }
              }
              ?>
            </div>
            <div>
              <strong id="reviewAverageValue"><?php echo number_format($avgRating, 1); ?></strong> / 5
              <div class="text-muted"><span id="reviewCount"><?php echo (int)($reviewStats['count'] ?? 0); ?></span> review<span id="reviewCountPlural"><?php echo ((int)($reviewStats['count'] ?? 0) === 1 ? '' : 's'); ?></span></div>
            </div>
          </div>
        </div>

        <div class="bg-card-bg border rounded-xl p-3 shadow-md border-gray-100 bg-white mb-3">
          <h5 class="mb-3">Leave a review</h5>
          <form id="reviewForm" method="post" action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8'); ?>#product-reviews">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>" />
            <input type="hidden" name="product_id" value="<?php echo (int)$product_id; ?>" />
            <input type="hidden" name="add_review" value="1" />

            <div class="mb-3">
              <label class="form-label" for="review_rating">Rating</label>
              <div id="review_rating">
                <?php for ($i = 5; $i >= 1; $i--): ?>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="rating" id="rating_<?php echo $i; ?>" value="<?php echo $i; ?>" <?php echo ($i === 5 ? 'checked' : ''); ?> />
                    <label class="form-check-label" for="rating_<?php echo $i; ?>"><?php echo $i; ?> <i class="fas fa-star" style="color:#f7c04a"></i></label>
                  </div>
                <?php endfor; ?>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label" for="review_name">Name</label>
              <input id="review_name" name="review_name" type="text" class="form-control" value="<?php echo htmlspecialchars($user_data['user_fname'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required />
            </div>
            <div class="mb-3">
              <label class="form-label" for="review_email">Email</label>
              <input id="review_email" name="review_email" type="email" class="form-control" value="<?php echo htmlspecialchars($user_data['user_email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required />
            </div>
            <div class="mb-3">
              <label class="form-label" for="review_comment">Comment</label>
              <textarea id="review_comment" name="review_comment" rows="4" class="form-control" required></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Submit Review</button>
          </form>
        </div>
      </div>

      <div class="col-md-8">
        <div id="reviewList">
        <?php if (empty($reviews)): ?>
          <p class="text-muted font-weight-800 bg-card-bg bg-white shadow-md rounded-xl text-center py-5 my-5">No reviews yet. Be the first to review this product.</p>
        <?php else: ?>
          <?php foreach ($reviews as $rev): ?>
            <div class="bg-card-bg border rounded-xl bg-white shadow-md border-gray-100 p-3 mb-3 ">
              <div class="d-flex justify-content-between">
                <div>
                  <img src="<?php echo htmlspecialchars($rev['user_avatar'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="w-8 h-8 rounded-full border border-gray-100" alt="Avatar">
                  <strong><?php echo htmlspecialchars($rev['name'] ?? 'Anonymous', ENT_QUOTES, 'UTF-8'); ?></strong>
                  <div class="text-muted small"><?php echo htmlspecialchars(date('F j, Y', strtotime($rev['created_at'])), ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <div>
                  <?php
                    $r = (int)($rev['rating'] ?? 0);
                    for ($ii = 1; $ii <= 5; $ii++) {
                      if ($ii <= $r) {
                        echo '<i class="fas fa-star" style="color:#f7c04a"></i>';
                      } else {
                        echo '<i class="far fa-star" style="color:#f7c04a"></i>';
                      }
                    }
                  ?>
                </div>
              </div>
              <p class="mt-2 mb-0"><?php echo nl2br(htmlspecialchars($rev['comment'] ?? '', ENT_QUOTES, 'UTF-8')); ?></p>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <div class="mb-4 text-end px-5 mx-5">
    <a href="javascript:history.back()" class="btn btn-sm btn-outline-primary"> <i class="fas fa-arrow-left"></i> Back</a>
  </div>
</section>

<!--   Related Products  -->
<section id="featured" class="category-scroll-section my-5 pb-5">
 <div class="container mt-5 py-5 text-center">
 <div>
 <h2>Related Products</h2>
 <div class="single-hr">
 <hr class="mx-auto hr">
</div>
  </div>
</div>
    <!--  Display related Products for based on category --->
 <?php include_once INTERNAL_LOGIC_PATH . '/get_related_products.php'; 
      
      
      $relatedProducts = getRelatedProducts($category, $product_id);
      if (!empty($relatedProducts) && is_array($relatedProducts)) {
      ?>
  <div class="container">
<div class="cat-row">
   <div class="scroll-row">
      <?php
          foreach ($relatedProducts as $related_products) {
              $variants = isset($related_products['product_variant']) && is_string($related_products['product_variant']) ? $related_products['product_variant'] : '';
              $variantsArray = $variants !== '' ? explode(',', $variants) : [];
              $defaultVariant = count($variantsArray) > 0 ? trim($variantsArray[0]) : '';
              $rating = isset($related_products['product_rating']) ? floatval($related_products['product_rating']) : 3.0;
              $reviewsCount = isset($related_products['product_review_count']) ? intval($related_products['product_review_count']) : 300;
              $product_name = htmlspecialchars($related_products['product_name'] ?? 'Unknown Product', ENT_QUOTES, 'UTF-8');
              $product_color = htmlspecialchars($related_products['product_color'] ?? '', ENT_QUOTES, 'UTF-8');
              $product_id =  htmlspecialchars($related_products['product_id'], ENT_QUOTES, 'UTF-8');
              $related_image = $related_products['product_image'] ?? '';
              if (preg_match('#^(https?:)?//#i', $related_image)) {
                  $img_src = htmlspecialchars($related_image, ENT_QUOTES, 'UTF-8');
              } else {
                  $img_src = $related_image !== '' ? htmlspecialchars(universalstore_URL . '/assets/imgs/' . $related_image, ENT_QUOTES, 'UTF-8') : universalstore_URL . '/assets/imgs/default.png';
              }
            ?>
    <div class="scroll-card">
    <!-- <div class="products-best product-arrival bg-card-bg shadow-lg border-1 border-gray-200 rounded-full">   -->
  <a href="<?php echo universalstore_URL; ?>/<?php echo createSlug($category);?>/<?php echo $product_id; ?>/<?php echo createSlug($product_name); ?>/<?php echo createSlug($product_color); ?>">
        <img class="img-fluid mb-2 product-image" src="<?php echo $img_src; ?>" alt="<?php echo $product_name; ?>" />
      </a>
    
       
    <h4 class="P-name"><?php echo $product_name; ?></h4>
    <h5 class="product-color" style="position:relative; top:-5px;font-size: 12px; color:#666;"><strong><?php echo $product_color; ?></strong></h5>
    <div class="product-variant" style="font-size: 12px; color: #666; margin-bottom: 8px;">
              <span style="background: #f0f0f0; padding: 2px 6px; border-radius: 3px; display: inline-block;">
                <?php echo htmlspecialchars($defaultVariant, ENT_QUOTES, 'UTF-8'); ?>
              </span>
            </div>
            <div class="star"> <?php echo renderRatingStars($rating); ?> </div>
         <div class="rating-text"><?php echo number_format($rating,1); ?>/5 • <?php echo number_format($reviewsCount); ?> reviews</div>
    <h5 class="P-price"><?php echo htmlspecialchars($related_products['product_price'], ENT_QUOTES, 'UTF-8') . ' FCFA' ;?></h5>
    <button class="add-cart-btn p-2 px-3">Add to cart</button>
    </div> 

      <?php }
      ?>
    </div>
    </div>
    </div>
    </div>
      <?php
}else{
    echo '<p class="text-muted font-weight-800 bg-card-bg bg-white shadow-md rounded-xl text-center py-5 my-5">No related products found.</p>';
}
?>

</section>

<script src="<?php echo universalstore_URL; ?>/assets/js/numberInput.js"></script>
<script src="<?php echo universalstore_URL; ?>/assets/js/error-logger.js"></script>
<script src="<?php echo universalstore_URL; ?>/assets/includes/bootstrap-5.3.7-dist/js/bootstrap.bundle.min.js"></script>

<script>
// Intercept add-to-cart form and send via AJAX so user stays on the page
document.addEventListener('DOMContentLoaded', function() {

    // Track product view with JavaScript
    if (window.userTracker) {
        window.userTracker.trackProductView(
            <?php echo json_encode($row['product_id']); ?>,
            <?php echo json_encode($row['product_name']); ?>,
            <?php echo json_encode($category); ?>,
            <?php echo json_encode($row['product_price']); ?>
        );
    }

  //function to check csrf_token value
  function checkCsrfToken(Token){
    if(!Token || Token.length === 0){
      console.error('CSRF token is missing or empty.');
      showCartNotice('An error occurred while adding to cart', null, true);
      window.scrollTo({
      top: 0,
      behavior: 'smooth'
      });
      return false;
  }
}
// Function to check new Csrf Token
function CheckNewCsrfToken(newToken, oldToken){
  if(newToken && newToken.length > 0){
    if(newToken === oldToken){
      console.error('CSRF Token did not change, potential issue.');
      showCartNotice('An error occurred while adding to cart', null, true);
      window.scrollTo({
      top: 0,
      behavior: 'smooth'
      });
      return false;
    }
    return true; // Token is valid and different
  }
  return false; // Token is empty or invalid
}

// Function to update the cart count in the navbar
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
    } else if (countSpan) {
        countSpan.remove();
    }
}

// Add event listener for the add-to-cart form
document.getElementById('addToCartForm').addEventListener('submit', function(e) {
    

//function update CSRF TOKEN
function updateCsrfToken(newToken) {
    if (csrfInput) {
        csrfInput.value = newToken;
    }
    else {
        console.error('CSRF token input not found in the form.'); 
    }
  }

  e.preventDefault();
    const form = this;
    const submitBtn = form.querySelector('button[type="submit"]');
    const variant = document.getElementById('variant');

    submitBtn.disabled = true;
    submitBtn.textContent = 'Adding...';
    const csrfInput = document.getElementById('csrf_token');
    const currentCsrfToken = csrfInput ? csrfInput.value : '';
    const productVariant = variant ? variant.value: '';
    const data = new FormData(form);
    // NEW: explicit ajax indicator so server reliably treats request as AJAX
    data.append('ajax', '1');

    // Ensure server sees the add-to-cart submission when sending via AJAX
    data.append('add-to-cart', '1');

    //Also send the Product Variant
    data.append('product_variant', productVariant);
    
    fetch(form.action, {
      method: 'POST',
      body: data,
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': currentCsrfToken
      }
    }).then(response => {
      const ct = response.headers.get('content-type') || '';
      if (ct.includes('application/json')) {
        return response.json().then(json => {
          if (!response.ok) {
            // Server returned error status with JSON body
            throw new Error(json.message || 'Server error: ' + response.status);
          }
          return json;
        });
      }
      // got HTML or text — fail early with the first part of the response for debugging
      return response.text().then(text => {
        if (!response.ok) {
          throw new Error('Server returned error: ' + response.status + ' - ' + text.slice(0, 200));
        }
        throw new Error('Server returned non-JSON response. Start of response: ' + text.slice(0,300));
      });
    })
    .then(json => {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Add To Cart';
      if(json.success){
        //check and update CSRF token if provided
        if(json.csrf_token && json.csrf_token.length > 0){
          if(CheckNewCsrfToken(json.csrf_token, currentCsrfToken)){
            updateCsrfToken(json.csrf_token);
          }
        }else{
          console.error("No Csrf Token found in response.");
        }
        showCartNotice(json.message, json.items || 0);
        if (typeof json.items !== 'undefined') {
            updateCartCount(json.items);
        }

        // Track add to cart event
        if (window.userTracker) {
            const quantity = document.getElementById('quantity') ? parseInt(document.getElementById('quantity').value) : 1;
            window.userTracker.trackAddToCart(
                <?php echo json_encode($row['product_id']); ?>,
                <?php echo json_encode($row['product_name']); ?>,
                quantity,
                <?php echo json_encode($row['product_price']); ?>
            );
        }

        window.scrollTo({
        top: 0,
        behavior: 'smooth'
});
      } else {
         //check and update CSRF token (Even on error) if provided
        if(json.csrf_token){
          if(CheckNewCsrfToken(json.csrf_token, currentCsrfToken)){
            updateCsrfToken(json.csrf_token);
          }
        }
        showCartNotice(json.message || 'Could not add product', null, true);
        window.scrollTo({
        top: 0,
        behavior: 'smooth'
        });
      }
    }).catch(err => {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Add To Cart';
      // show friendly error to user and log the details to console
      showCartNotice('An error occurred while adding to cart', null, true);      // Log the actual error message and stack trace
      let errorMessage = 'Unknown error';
      let errorStack = '';

      if (err instanceof Error) {
        errorMessage = err.message || 'Error object with no message';
        errorStack = err.stack || '';
      } else if (typeof err === 'string') {
        errorMessage = err;
      } else if (err && typeof err === 'object') {
        errorMessage = JSON.stringify(err) || 'Object error';
      } else {
        errorMessage = String(err) || 'Unknown error type';
      }

      if (typeof window.logJSError === 'function') {
        window.logJSError(
          'Add to Cart Error: ' + errorMessage + (errorStack ? '\nStack: ' + errorStack : ''),
          'error',
          window.location.href
        );
      }      console.error(err);
       window.scrollTo({
        top: 0,
        behavior: 'smooth'
        });
    });
  });
  
    
  // function to show the message and view cart button after banner
  function showCartNotice(message =  ' Product has been added to cart', items = null, isError = false){
    const container = document.getElementById('cartAlertContainer');
    container.innerHTML = ''; // reset


    const alert = document.createElement('div');
    alert.className = 'cart-notice d-flex align-items-center justify-content-between p-3 rounded shadow-sm';
    if(isError) alert.classList.add('cart-notice-error'); else alert.classList.add('cart-notice-success');

    const text = document.createElement('div');
    text.innerHTML = `<i class="fa-regular fa-bell"></i>` + `<strong> ${message}</strong>` + (items !== null ? ` <small class="text-muted">(${items} item(s) in cart)</small>` : '');

    const actions = document.createElement('div');
    actions.className = 'd-flex gap-2 align-items-center';
    const viewBtn = document.createElement('a');
    viewBtn.href = '<?php echo universalstore_URL; ?>/cart';
    viewBtn.className = 'btn btn-outline-dark view-cart-btn';
    viewBtn.textContent = 'View Cart';

    const closeBtn = document.createElement('button');
    closeBtn.className = 'btn btn-sm btn-secondary';
    closeBtn.type = 'button';
    closeBtn.textContent = 'Close';
    closeBtn.addEventListener('click', () => {
      container.innerHTML = '';
    });

    if(isError){
       viewBtn.style.display = 'none'; // no view cart button on error
       closeBtn.style.display = 'none'; // no close button on error
    }
    else
    actions.appendChild(viewBtn);
    actions.appendChild(closeBtn);

    alert.appendChild(text);
    alert.appendChild(actions);
    container.appendChild(alert);
    // auto hide after 8 seconds
  /*  setTimeout(() => {
      if(container.contains(alert)) container.removeChild(alert);
    }, 8000);  */
  }

  // --- Review form AJAX submit + live refresh ---
  function showReviewNotice(message, isError = false) {
    const container = document.getElementById('reviewNoticeContainer');
    if (!container) return;
    container.innerHTML = '';

    const alert = document.createElement('div');
    alert.className = isError ? 'alert alert-danger mt-4' : 'alert alert-success mt-4';
    alert.textContent = message;
    container.appendChild(alert);
  }

  function renderStars(container, rating) {
    if (!container) return;
    const full = Math.floor(rating);
    const half = rating - full >= 0.5;
    let html = '';
    for (let i = 1; i <= 5; i++) {
      if (i <= full) {
        html += '<i class="fas fa-star" style="color:#f7c04a"></i>';
      } else if (i === full + 1 && half) {
        html += '<i class="fas fa-star-half-alt" style="color:#f7c04a"></i>';
      } else {
        html += '<i class="far fa-star" style="color:#f7c04a"></i>';
      }
    }
    container.innerHTML = html;
  }

  function updateCsrfToken(newToken) {
    const csrfInput = document.querySelector('#reviewForm input[name="csrf_token"]');
    if (csrfInput) {
        csrfInput.value = newToken;
    }
    else {
        console.error('CSRF token input not found in the form.'); 
    }
  }


  const reviewForm = document.getElementById('reviewForm');
  if (reviewForm) {
    reviewForm.addEventListener('submit', function (e) {
      e.preventDefault();
      const submitBtn = reviewForm.querySelector('button[type="submit"]');
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';
      }

      const csrfInput = document.querySelector('#reviewForm input[name="csrf_token"]');
      const currentCsrfToken = csrfInput ? csrfInput.value : '';

      const data = new FormData(reviewForm);
      data.append('ajax', '1');

      fetch(reviewForm.action, {
        method: 'POST',
        body: data,
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-Token': currentCsrfToken
        }
      })
        .then(response => {
          const ct = response.headers.get('content-type') || '';
          if (ct.includes('application/json')) {
            if (!response.ok) {
              throw new Error('Network response was not ok');
            }
            return response.json();
          }
          return response.text().then(text => {
            throw new Error('Server returned non-JSON response. ' + text.slice(0, 400));
          });
        })
        .then(json => {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit Review';
          }

          if (json.csrf_token) {
            updateCsrfToken(json.csrf_token);
          }

          if (json.success) {
            showReviewNotice(json.message || 'Review submitted successfully.');

            if (json.reviewStats) {
              const avgEl = document.getElementById('reviewAverageValue');
              const countEl = document.getElementById('reviewCount');
              const pluralEl = document.getElementById('reviewCountPlural');
              const starsWrapper = document.getElementById('reviewStars');

              if (avgEl) avgEl.textContent = (json.reviewStats.average ?? 0).toFixed(1);
              if (countEl) countEl.textContent = json.reviewStats.count ?? 0;
              if (pluralEl) pluralEl.textContent = (json.reviewStats.count === 1 ? '' : 's');
              renderStars(starsWrapper, json.reviewStats.average ?? 0);
            }

            if (json.review) {
              const list = document.getElementById('reviewList');
              if (list) {
                // remove placeholder
                const placeholder = list.querySelector('p.text-muted');
                if (placeholder) placeholder.remove();

                const item = document.createElement('div');
                item.className = 'bg-card-bg border rounded-xl bg-white shadow-md border-gray-100 p-3 mb-3 review-item';
                item.innerHTML = `
                  <div class="d-flex justify-content-between">
                    <div>
                      <img src="${json.review.user_avatar || ''}" class="w-8 h-8 rounded-full border border-gray-100" alt="Avatar">
                      <strong>${json.review.name ? (new DOMParser().parseFromString(json.review.name, 'text/html').body.textContent) : 'Anonymous'}</strong>
                      <div class="text-muted small">${json.review.created_at}</div>
                    </div>
                    <div>${(function(){
                      const r = Number(json.review.rating) || 0;
                      let html = '';
                      for (let ii = 1; ii <= 5; ii++) {
                        if (ii <= r) html += '<i class="fas fa-star" style="color:#f7c04a"></i>';
                        else html += '<i class="far fa-star" style="color:#f7c04a"></i>';
                      }
                      return html;
                    })()}</div>
                  </div>
                  <p class="mt-2 mb-0">${json.review.comment ? (new DOMParser().parseFromString(json.review.comment, 'text/html').body.textContent).replace(/\n/g, '<br>') : ''}</p>
                `;
                list.prepend(item);
              }
            }

            // reset form (keep name/email)
            const commentField = document.getElementById('review_comment');
            if (commentField) commentField.value = '';
          } else {
            showReviewNotice(json.message || 'Unable to submit review.', true);
            window.scrollTo({ top:1800, behavior:'smooth' }
            )
          }
        })
        .catch(err => {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit Review';
          }
          showReviewNotice('An error occurred submitting your review.', true);
          console.error(err);
        });
    });
  }

});
</script>
<script>
 var mainImg = document.getElementById('mainImg');
 var smallImg = document.getElementsByClassName('small-img');

 // Ensure the zoom uses the current main image on load
 (function(){
   var zoomInit = document.getElementById('imageZoom');
   if(zoomInit && mainImg && mainImg.src){
     zoomInit.style.setProperty('--url', 'url(' + mainImg.src + ')');
   }
 })();

 // Update main image and the zoom background when a thumbnail is clicked
 for(let i=0; i<smallImg.length; i++){
   smallImg[i].onclick = function(){
     if(mainImg && smallImg[i] && smallImg[i].src){
       mainImg.src = smallImg[i].src;
       var zoomEl = document.getElementById('imageZoom');
       if(zoomEl) zoomEl.style.setProperty('--url', 'url(' + smallImg[i].src + ')');
     }
   }
 }

// javascript for zooming image 
let imageZoom = document.getElementById('imageZoom');

imageZoom.addEventListener('mousemove', (event) => {
  imageZoom.style.setProperty('--display', 'block');
  let pointer ={
    x: (event.offsetX * 100) / imageZoom.offsetWidth,
    y: (event.offsetY * 100) / imageZoom.offsetHeight
  }
  imageZoom.style.setProperty('--zoom-x', pointer.x + '%');
  imageZoom.style.setProperty('--zoom-y', pointer.y + '%');
  console.log(pointer);
})
imageZoom.addEventListener('mouseout',() => {
  imageZoom.style.setProperty('--display', 'none');
})

</script>
<?php  require_once TEMPLATES_PATH .'/footer.php'; ?>
</body>
 <?php  } ?>

</html>