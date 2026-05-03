<?php  

require_once("core/google_oauth_handler.php");
require_once("config.php");

// check if code is send by google after login with account
if(isset($_GET['code'])){
    $token = $gClient->fetchAccessTokenWithAuthCode($_GET["code"]);
}
// If there is no code redirect to Authentication page
else{
  header("Location: authentication.php");
  exit();
}
// verify if user's token is valid/correct or trying to hack
if(isset($token["error"]) != "invalid_grant"){
    // initializes the Google OAuth2 service 
    $oAuth = new Google\Service\Oauth2($gClient);
    // Fetch user's basic profile info after the login & grant permission
    $userData = $oAuth->userinfo_v2_me->get();

    //insert data 
    $controller = new Controller;
    echo $controller->insertData(array(
      'email' => $userData['email'],
      'avatar' => $userData['picture'],
      'familyName' => $userData['familyName'],
      'givenName' => $userData['givenName']

    ));
}
// redirect to authentication if he reload page
else{
  header("Location: authentication.php");
  exit();
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Account - Verification || UniversalStore</title>
  
  <!-- custom css file -->
  <link href="../../assets/imgs/apple.jpeg" rel="icon">
  <link rel="stylesheet" href="../../assets/css/stylin01.css">

</head>
<body>
  
  

</body>
</html>