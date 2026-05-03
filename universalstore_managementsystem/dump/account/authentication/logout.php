<?php

setcookie('user_id', '', time() -60*60*24*30, "/", "");
setcookie('user_session', '', time() -60*60*24*30, "/", "");
header("Location: authentication.php");
die();
?>