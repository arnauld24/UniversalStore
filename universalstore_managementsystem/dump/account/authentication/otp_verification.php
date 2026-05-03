<!DOCTYPE html>     
<html lang="en">  
    <head>
        <meta charset="UTF-8"/>
        <meta name="viewport" content="width=device-width, initial-scale= 1" />
        <title>Verify account- Universal Store</title>
        
  <!-- bootstrap CDN -->
  <link rel="stylesheet" href="../assets/includes/bootstrap-5.3.7-dist/css/bootstrap.min.css">

  <!-- Font Awesome CDN -->
  <link rel="stylesheet" href="../assets/includes/fontawesome-free-5.15.4-web/css/all.css">
  <link rel="stylesheet" href="../assets/includes/fontawesome/fontawesome-free-6.7.2-web/css/all.css">
  
  <!-- custom css file -->
  <link rel="stylesheet" href="../assets/css/verify.css">
  <link href="../assets/imgs/apple.jpeg" rel="icon">
  <link rel="stylesheet" href="../assets/css/stylin01.css">

</head>
<body>

     <div class="contain">
     <div class="form" style="text-align: center;">
       <h1 class="font-baloo " >Verify Your Account</h1>
       <p class="font-baloo">we emailed you the Six digit otp code. Enter the code below to confirm your email address..</p>
       <form action="" autocomplete="off">
       	<div class="error-text">Error</div>
       	<div class="fields-input">
           <input type="number" name="otp1"  class="otp_field" placeholder="0" min="0" max="9" maxlength="1" required onpaste="false" >
            <input type="number" name="otp2"  class="otp_field" placeholder="0" min="0" max="9" maxlength="1" required onpaste="false" >
             <input type="number" name="otp3"  class="otp_field" placeholder="0" min="0" max="9" maxlength="1" required onpaste="false" >
              <input type="number" name="otp4"  class="otp_field" placeholder="0" min="0" max="9" maxlength="1" required onpaste="false" >
              <input type="number" name="otp5"  class="otp_field" placeholder="0" min="0" max="9" maxlength="1" required onpaste="false" >
              <input type="number" name="otp6"  class="otp_field" placeholder="0" min="0" max="9" maxlength="1" required onpaste="false" >

       	</div>
       	<div class="submit">
       		<input type="submit" value="Verify Now" class="button font-rubik">
       	</div>
        </form>
     </div>
</div>


      <script src="../assets/js/verify.js"></script>
    </body>
</html>
