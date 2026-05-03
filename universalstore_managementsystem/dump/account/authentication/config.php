<?php

require_once ('google-api/vendor/autoload.php');
$gClient = new Google_Client();
$gClient->setClientId("965860796397-25tbkhmncnnhmbga07qqdamn2nbdvgs5.apps.googleusercontent.com");
$gClient->setClientSecret("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");
$gClient->setApplicationName("UniversalStore - Authentication");
$gClient->setRedirectUri("http://localhost/PHPPROJECT/universalstore_managementsystem/account/authentication/verify.php");
$gClient->addScope("https://www.googleapis.com/auth/plus.login https://www.googleapis.com/auth/userinfo.email");

$login_url = $gClient->createAuthUrl();



?>