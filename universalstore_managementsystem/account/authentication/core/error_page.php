<?php

require_once __DIR__ . '/../../../../config/config.php';

if(!isset($_GET['code']) && !isset($_GET['message'])) {
    // Default to 500 if no parameters provided
    $_GET['code'] = 500;
    $_GET['message'] = 'An error occurred. Please try again.';
}

$errorCode = htmlspecialchars($_GET['code'] ?? '500');
$errorMessage = htmlspecialchars($_GET['message'] ?? 'An error occurred. Please try again later.');

// SET HTTP STATUS CODE (for search engines/browsers)
// Ensure the code is a valid number for display

if (is_numeric($errorCode) && $errorCode >= 400 && $errorCode <= 599) {
    http_response_code((int)$errorCode);
} else {
    http_response_code(500);
    $errorCode = 500;
}

if ($errorCode == 400) {
    $title = "Bad Request";
    $instruction = "The request could not be understood by the server. Please check your input and try again.";
} elseif($errorCode == 401){
    $title = "Unauthorized";
    $instruction = "Authentication is required. Please log in and try again.";
}
elseif($errorCode == 403){
    $title = "Forbidden";
    $instruction = "You do not have permission to access this resource. Please contact support if you believe this is an error.";
}
elseif($errorCode == 404){
    $title = "Not Found";
    $instruction = "The resource you are looking for could not be found. It may have been moved or deleted.";
}
elseif($errorCode == 405){
    $title ="Method Not Allowed";
    $instruction = "The request method is not supported for this resource.";
}
elseif($errorCode == 415){
    $title = "Unsupported Media Type";
    $instruction = "The server encountered an invalid data type that is not supported.";
}
elseif ($errorCode == 500) {
    $title = "Server Error";
    $instruction = "The server encountered an unexpected error. Our team has been notified.";
}
elseif( $errorCode == 429) {
    $title = "Too Many Requests";
    $instruction = "You have made too many requests in a short period. Please try again later.";
}
else {
    $title = "Error";
    $instruction = "An error occurred while processing your request.";
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title><?php echo htmlspecialchars($errorCode); ?> <?php echo htmlspecialchars($title); ?> Universal - Store</title>

  <!-- bootstrap CDN -->
  <link rel="stylesheet" href="<?php echo ASSETS_URL;?>/includes/bootstrap-5.3.7-dist/css/bootstrap.min.css">

  <!-- custom css file -->
  <link rel="stylesheet" href="<?php echo ASSETS_URL;?>/css/errors_page.css">
  <link href="https://ik.imagekit.io/univ35tore/universal_store/imgs/UNIVERSAL%20STORE.jpg" rel="icon">
    </head>
<body style="text-align: center; padding-top: 50px;">

    <div class="error-container">
        <h1><?php echo $errorCode; ?></h1>
        <h2><?php echo $title; ?></h2>
        <p><?php echo htmlspecialchars($errorMessage); ?></p>
        <p><?php echo htmlspecialchars($instruction); ?></p>
        <hr>
        <p>We're sorry, but the action could not be completed.</p>
        <a href="<?php echo BASE_URL; ?>/universalstore_managementsystem/index.php" class="btn btn-primary">Go to Home</a>
        <a href="javascript:history.back()" class="btn btn-secondary ms-2">Go Back</a>
    </div>
   
</body>
</html>